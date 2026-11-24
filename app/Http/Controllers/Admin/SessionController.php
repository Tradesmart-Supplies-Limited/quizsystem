<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SettingsSanitizer;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Services\GameEngine;
use App\Services\GameState;
use App\Support\Csv;
use App\Support\GameDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    public function __construct(private GameEngine $engine, private GameState $state) {}

    public function index(Request $request)
    {
        $q = QuizSession::with('facilitator')->withCount(['entries', 'rounds']);
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($term = $request->string('q')->trim()->value()) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('workshop_title', 'like', "%{$term}%"));
        }

        return view('admin.sessions.index', ['sessions' => $q->latest()->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.sessions.form', $this->formData(new QuizSession([
            'title' => 'Operations Quiz – '.now()->format('j M Y'),
            'session_date' => now(),
            'department' => 'Operations',
            'selection_mode' => 'fair_rotation',
            'total_rounds' => 20,
        ])));
    }

    public function store(Request $request)
    {
        $session = DB::transaction(function () use ($request) {
            [$data, $settings, $participantIds, $categoryIds, $questionIds] = $this->validated($request);
            $session = new QuizSession($data);
            $session->settings = $settings;
            $session->facilitator_id = $request->user()->id;
            $session->status = $participantIds ? 'ready' : 'draft';
            $session->save();
            $session->categories()->sync($categoryIds);
            $session->questionPool()->sync($questionIds);
            foreach ($participantIds as $pid) {
                $session->entries()->create(['participant_id' => $pid]);
            }
            $session->logEvent('created');

            return $session;
        });

        if ($request->input('after') === 'play') {
            return redirect()->route('play', $session);
        }

        return redirect()->route('admin.sessions.show', $session)->with('success', 'Quiz session created.');
    }

    public function show(QuizSession $session)
    {
        $session->load(['facilitator', 'categories']);
        $rounds = $session->rounds()->with(['question.category', 'participant', 'team'])->get();
        $categoryPerf = $rounds->where('status', 'answered')->groupBy(fn ($r) => $r->question?->category?->name ?? 'Challenges')
            ->map(fn ($g) => [
                'asked' => $g->count(),
                'correct' => $g->where('result', 'correct')->count(),
                'points' => $g->sum('points_awarded'),
            ]);

        return view('admin.sessions.show', [
            'session' => $session,
            'leaderboard' => $this->state->leaderboard($session),
            'teamLeaderboard' => $this->state->teamLeaderboard($session),
            'stats' => $this->state->finalStats($session),
            'rounds' => $rounds,
            'categoryPerf' => $categoryPerf,
            'events' => $session->events()->with('user')->limit(40)->get(),
        ]);
    }

    public function edit(QuizSession $session)
    {
        return view('admin.sessions.form', $this->formData($session));
    }

    public function update(Request $request, QuizSession $session)
    {
        DB::transaction(function () use ($request, $session) {
            [$data, $settings, $participantIds, $categoryIds, $questionIds] = $this->validated($request);
            $session->fill($data);
            $session->total_rounds = max($session->total_rounds, $session->current_round);
            $session->settings = $settings;
            if ($session->status === 'draft' && $participantIds) {
                $session->status = 'ready';
            }
            $session->save();
            $session->categories()->sync($categoryIds);
            $session->questionPool()->sync($questionIds);
            // Add new participants; remove only those who never played (history is preserved).
            $existing = $session->entries()->pluck('participant_id')->all();
            foreach (array_diff($participantIds, $existing) as $pid) {
                $session->entries()->create(['participant_id' => $pid]);
            }
            foreach (array_diff($existing, $participantIds) as $pid) {
                $played = $session->rounds()->where('participant_id', $pid)->exists();
                $played
                    ? $session->entries()->where('participant_id', $pid)->update(['is_excluded' => true])
                    : $session->entries()->where('participant_id', $pid)->delete();
            }
            $session->logEvent('updated');
        });

        return redirect()->route('admin.sessions.show', $session)->with('success', 'Session updated.');
    }

    public function destroy(QuizSession $session)
    {
        Gate::authorize('admin');
        $session->delete();

        return redirect()->route('admin.sessions.index')->with('success', 'Session deleted.');
    }

    public function status(QuizSession $session, string $action)
    {
        $this->engine->setStatus($session, $action);

        return back()->with('success', 'Session '.$session->refresh()->status.'.');
    }

    public function duplicate(QuizSession $session)
    {
        $copy = DB::transaction(function () use ($session) {
            $copy = $session->replicate(['status', 'current_round', 'state', 'started_at', 'paused_at', 'ended_at']);
            $copy->title = $session->title.' (copy)';
            $copy->session_date = now();
            $copy->status = 'ready';
            $copy->current_round = 0;
            $copy->facilitator_id = auth()->id();
            $copy->save();
            $copy->categories()->sync($session->categories()->pluck('question_categories.id'));
            $copy->questionPool()->sync($session->questionPool()->pluck('questions.id'));
            foreach ($session->entries()->pluck('participant_id') as $pid) {
                $copy->entries()->create(['participant_id' => $pid]);
            }

            return $copy;
        });

        return redirect()->route('admin.sessions.edit', $copy)->with('success', 'Session duplicated – adjust and start when ready.');
    }

    public function export(QuizSession $session)
    {
        $lb = $this->state->leaderboard($session);
        $rounds = $session->rounds()->with(['question.category', 'participant', 'team'])->get();
        $rows = [];
        $rows[] = ['SESSION', $session->title, $session->workshop_title, optional($session->session_date)->format('Y-m-d'), $session->status];
        $rows[] = [];
        $rows[] = ['LEADERBOARD'];
        $rows[] = ['rank', 'participant', 'team', 'score', 'correct', 'answered', 'accuracy %', 'best streak'];
        foreach ($lb as $r) {
            $rows[] = [$r['rank'], $r['full_name'], $r['team'], $r['score'], $r['correct'], $r['answered'], $r['accuracy'], $r['best_streak']];
        }
        $rows[] = [];
        $rows[] = ['ROUNDS'];
        $rows[] = ['round', 'type', 'participant', 'team', 'category', 'question', 'result', 'points available', 'points awarded', 'answered at'];
        foreach ($rounds as $r) {
            $rows[] = [$r->round_number, $r->round_type.($r->mystery_effect ? ' / '.$r->mystery_effect : ''), $r->participant?->full_name,
                $r->team?->name, $r->question?->category?->name, $r->question?->question_text ?? $r->challenge_text,
                $r->result, $r->points_available, $r->points_awarded, optional($r->answered_at)->format('Y-m-d H:i:s')];
        }

        return Csv::download('session-'.$session->id.'-results.csv', ['TradeSmart Operations Quiz Arena – Results'], $rows);
    }

    public function report(QuizSession $session)
    {
        return view('admin.sessions.report', [
            'session' => $session->load('facilitator'),
            'leaderboard' => $this->state->leaderboard($session),
            'teamLeaderboard' => $this->state->teamLeaderboard($session),
            'stats' => $this->state->finalStats($session),
            'rounds' => $session->rounds()->with(['question.category', 'participant'])->get(),
        ]);
    }

    private function formData(QuizSession $session): array
    {
        return [
            'session' => $session,
            'config' => $session->exists ? $session->config() : GameDefaults::session(),
            'participants' => Participant::active()->orderBy('full_name')->get(),
            'selectedParticipants' => $session->exists ? $session->entries()->where('is_excluded', false)->pluck('participant_id')->all() : Participant::active()->pluck('id')->all(),
            'categories' => QuestionCategory::active()->withCount(['questions' => fn ($q) => $q->active()])->orderBy('sort_order')->orderBy('name')->get(),
            'selectedCategories' => $session->exists ? $session->categories()->pluck('question_categories.id')->all() : [],
            'questions' => Question::active()->with('category')->orderBy('question_category_id')->get(['id', 'question_text', 'question_category_id', 'difficulty']),
            'selectedQuestions' => $session->exists ? $session->questionPool()->pluck('questions.id')->all() : [],
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'workshop_title' => ['nullable', 'string', 'max:150'],
            'session_date' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:100'],
            'selection_mode' => ['required', Rule::in(array_keys(QuizSession::SELECTION_MODES))],
            'total_rounds' => ['required', 'integer', 'min:1', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:participants,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:question_categories,id'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            'settings' => ['nullable', 'array'],
        ]);
        $settings = SettingsSanitizer::session($this->checkboxes($request->input('settings', [])));
        $sel = fn ($k) => array_values(array_unique(array_map('intval', $data[$k] ?? [])));

        return [
            collect($data)->only(['title', 'workshop_title', 'session_date', 'department', 'selection_mode', 'total_rounds', 'duration_minutes'])->all(),
            $settings,
            $sel('participant_ids'),
            $sel('category_ids'),
            $sel('question_ids'),
        ];
    }

    /** HTML checkboxes are absent when unticked – fill booleans from the hidden "_present" marker. */
    private function checkboxes(array $input): array
    {
        foreach (GameDefaults::base() as $group => $defaults) {
            if (array_is_list($defaults)) {
                continue;
            }
            foreach ($defaults as $k => $v) {
                if (is_bool($v) && isset($input[$group]) && is_array($input[$group])) {
                    $input[$group][$k] = ! empty($input[$group][$k]);
                }
            }
        }

        return $input;
    }
}
