<?php

namespace App\Http\Controllers;

use App\Exceptions\GameException;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Models\Team;
use App\Services\GameEngine;
use App\Services\GameState;
use App\Services\QuestionPicker;
use App\Services\ScoringService;
use App\Support\GameDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GameController extends Controller
{
    public function __construct(
        private GameEngine $engine,
        private ScoringService $scoring,
        private GameState $state,
        private QuestionPicker $questions,
    ) {}

    public function show(QuizSession $session)
    {
        if ($session->status === 'draft') {
            $session->status = 'ready';
            $session->save();
        }

        return view('game.play', [
            'session' => $session,
            'categories' => QuestionCategory::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function state(QuizSession $session)
    {
        return response()->json($this->state->full($session));
    }

    public function spin(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'round_number' => ['nullable', 'integer', 'min:1'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
        ]);
        $round = $this->engine->spin($session, $data);
        $round->load(['question.category', 'question.options', 'participant', 'team']);
        $session->refresh();

        return response()->json([
            'round' => $this->state->round($round),
            'reel' => $this->questions->reelDecoys($session),
            'wheel' => $this->state->participants($session),
            'session' => $this->state->sessionInfo($session),
        ]);
    }

    public function score(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'result' => ['required', Rule::in(ScoringService::RESULTS)],
            'round_id' => ['nullable', 'integer'],
            'override_points' => ['nullable', 'integer', 'between:-1000,1000'],
            'seconds_remaining' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'star' => ['nullable', 'boolean'],
        ]);
        $answer = $this->scoring->score($session, $data['result'], $data);

        return response()->json([
            'answer' => $answer->only(['result', 'points', 'base_points', 'streak_bonus', 'time_bonus', 'extra_bonus']),
            'streak' => $session->entries()->where('participant_id', $answer->participant_id)->value('current_streak'),
        ] + $this->state->scores($session));
    }

    public function undo(QuizSession $session)
    {
        $this->scoring->undo($session);

        return response()->json($this->state->scores($session));
    }

    public function void(QuizSession $session)
    {
        $this->engine->void($session);

        return response()->json($this->state->scores($session));
    }

    public function double(Request $request, QuizSession $session)
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);
        $round = $this->scoring->setDoubleOrNothing($session, (bool) $data['on']);
        $round->load(['question.category', 'question.options', 'participant', 'team']);

        return response()->json(['round' => $this->state->round($round)]);
    }

    public function mode(Request $request, QuizSession $session)
    {
        $data = $request->validate(['mode' => ['required', Rule::in(['bonus', 'danger', 'mystery', 'hot_seat', 'speed', 'cancel'])]]);
        $this->engine->queueMode($session, $data['mode']);

        return response()->json(['session' => $this->state->sessionInfo($session->refresh())]);
    }

    public function status(Request $request, QuizSession $session)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'pause', 'resume', 'complete', 'reopen'])]]);
        $this->engine->setStatus($session, $data['action']);

        return response()->json($this->state->scores($session));
    }

    public function final(QuizSession $session)
    {
        return response()->json([
            'leaderboard' => $this->state->leaderboard($session),
            'team_leaderboard' => $this->state->teamLeaderboard($session),
            'stats' => $this->state->finalStats($session),
            'session' => $this->state->sessionInfo($session),
        ]);
    }

    /** Update session configuration from the in-game settings panel without touching play state. */
    public function settings(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'workshop_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'total_rounds' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'selection_mode' => ['sometimes', Rule::in(array_keys(QuizSession::SELECTION_MODES))],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:question_categories,id'],
            'settings' => ['sometimes', 'array'],
        ]);

        DB::transaction(function () use ($session, $data) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            foreach (['title', 'workshop_title', 'duration_minutes', 'selection_mode'] as $f) {
                if (array_key_exists($f, $data)) {
                    $s->{$f} = $data[$f];
                }
            }
            if (isset($data['total_rounds'])) {
                $s->total_rounds = max((int) $data['total_rounds'], $s->current_round);
            }
            if (isset($data['settings'])) {
                $s->settings = SettingsSanitizer::session(GameDefaults::mergeConfig($s->settings ?? [], $data['settings']));
            }
            $s->save();
            if (array_key_exists('category_ids', $data)) {
                $s->categories()->sync($data['category_ids']);
            }
            $s->logEvent('settings_updated', array_keys($data));
        });

        return response()->json(['config' => $session->refresh()->config(), 'session' => $this->state->sessionInfo($session)]);
    }

    // ---------------------------------------------------------------- participants (in-game)

    public function addParticipant(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);
        $p = Participant::create($data + ['is_active' => true]);
        $session->entries()->firstOrCreate(['participant_id' => $p->id]);
        $session->logEvent('participant_added', ['participant_id' => $p->id]);

        return response()->json(['participants' => $this->state->participants($session)]);
    }

    public function attachParticipants(Request $request, QuizSession $session)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:participants,id']]);
        foreach ($data['ids'] as $id) {
            $session->entries()->firstOrCreate(['participant_id' => $id]);
        }

        return response()->json(['participants' => $this->state->participants($session)]);
    }

    public function updateParticipant(Request $request, QuizSession $session, Participant $participant)
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:60'],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
            'is_excluded' => ['sometimes', 'boolean'],
            'team_id' => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')->where('quiz_session_id', $session->id)],
        ]);
        $entry = $session->entries()->where('participant_id', $participant->id)->firstOrFail();
        $participant->fill(collect($data)->only(['display_name', 'full_name', 'is_active'])->all())->save();
        if (array_key_exists('is_excluded', $data)) {
            $entry->is_excluded = $data['is_excluded'];
        }
        if (array_key_exists('team_id', $data)) {
            $entry->team_id = $data['team_id'];
        }
        $entry->save();

        return response()->json(['participants' => $this->state->participants($session), 'teams' => $this->state->teams($session)]);
    }

    public function removeParticipant(QuizSession $session, Participant $participant)
    {
        $entry = $session->entries()->where('participant_id', $participant->id)->firstOrFail();
        if ($session->rounds()->where('participant_id', $participant->id)->exists()) {
            // Keep history intact – exclude instead of deleting.
            $entry->update(['is_excluded' => true]);
        } else {
            $entry->delete();
        }

        return response()->json(['participants' => $this->state->participants($session)]);
    }

    public function generateTeams(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:2', 'max:12'],
            'names' => ['nullable', 'array'],
            'names.*' => ['nullable', 'string', 'max:80'],
        ]);
        $palette = ['#4EAADF', '#86C440', '#F5A623', '#E94B6A', '#9B6BDF', '#2EC4B6', '#FF7F50', '#FFD23F', '#5C7AEA', '#00B894', '#D980FA', '#A3A8B0'];
        $default = ['Blue Forklifts', 'Green Pallets', 'Golden Dispatchers', 'Red Racers', 'Purple Planners', 'Teal Trackers', 'Coral Couriers', 'Yellow Loaders', 'Indigo Inventory', 'Emerald Express', 'Violet Vans', 'Silver Shelves'];

        DB::transaction(function () use ($session, $data, $palette, $default) {
            $session->entries()->update(['team_id' => null]);
            $session->teams()->delete();
            $teams = [];
            for ($i = 0; $i < $data['count']; $i++) {
                $teams[] = $session->teams()->create([
                    'name' => trim($data['names'][$i] ?? '') ?: $default[$i],
                    'color' => $palette[$i % count($palette)],
                ]);
            }
            $entries = $session->entries()->where('is_excluded', false)->get()->shuffle();
            foreach ($entries->values() as $idx => $entry) {
                $entry->update(['team_id' => $teams[$idx % count($teams)]->id]);
            }
            $settings = $session->settings ?? [];
            $settings['modes']['team_mode'] = true;
            $session->settings = $settings;
            $session->save();
            $session->logEvent('teams_generated', ['count' => $data['count']]);
        });

        return response()->json([
            'teams' => $this->state->teams($session),
            'participants' => $this->state->participants($session),
            'team_leaderboard' => $this->state->teamLeaderboard($session),
            'config' => $session->refresh()->config(),
        ]);
    }

    public function updateTeam(Request $request, QuizSession $session, Team $team)
    {
        abort_unless($team->quiz_session_id === $session->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $team->update($data);

        return response()->json(['teams' => $this->state->teams($session), 'participants' => $this->state->participants($session)]);
    }

    // ---------------------------------------------------------------- questions (in-game)

    public function questions(Request $request, QuizSession $session)
    {
        $q = Question::with('category')->latest('id');
        if ($term = $request->string('q')->trim()->value()) {
            $q->where('question_text', 'like', '%'.$term.'%');
        }

        return response()->json([
            'questions' => $q->limit(60)->get()->map(fn ($x) => [
                'id' => $x->id, 'text' => $x->question_text, 'category' => $x->category?->name,
                'difficulty' => $x->difficulty, 'points' => $x->points, 'active' => $x->is_active,
                'type' => $x->type, 'correct_answer' => $x->correct_answer,
            ]),
            'categories' => QuestionCategory::orderBy('name')->get(['id', 'name', 'is_active', 'color']),
            'remaining' => $this->questions->baseQuery($session)->count(),
        ]);
    }

    public function addQuestion(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:2000'],
            'question_category_id' => ['required', 'integer', 'exists:question_categories,id'],
            'type' => ['required', Rule::in(array_keys(Question::TYPES))],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'correct_answer' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'options' => ['nullable', 'array', 'max:8'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'options.*.is_correct' => ['nullable', 'boolean'],
        ]);
        $question = Question::create(collect($data)->except('options')->all() + ['is_active' => true]);
        $question->forceFill(['created_by' => $request->user()->id])->save();
        if (! empty($data['options'])) {
            $question->syncOptions($data['options']);
        }

        return $this->questions($request, $session);
    }

    public function updateQuestion(Request $request, QuizSession $session, Question $question)
    {
        $data = $request->validate([
            'question_text' => ['sometimes', 'string', 'max:2000'],
            'correct_answer' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'points' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $question->update($data);

        return $this->questions($request, $session);
    }

    public function deleteQuestion(Request $request, QuizSession $session, Question $question)
    {
        $question->delete(); // soft delete keeps round history

        return $this->questions($request, $session);
    }

    public function addCategory(Request $request, QuizSession $session)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('question_categories', 'name')],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        QuestionCategory::create($data + ['is_active' => true, 'color' => $data['color'] ?? '#4EAADF']);

        return $this->questions($request, $session);
    }

    public function toggleCategory(Request $request, QuizSession $session, QuestionCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return $this->questions($request, $session);
    }
}
