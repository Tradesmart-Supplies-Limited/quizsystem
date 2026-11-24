<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizRound;
use App\Models\QuizSession;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from');
        $to = $request->date('to');
        $sessions = QuizSession::query()
            ->when($from, fn ($q) => $q->whereDate('session_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('session_date', '<=', $to));
        $sessionIds = (clone $sessions)->pluck('id');

        $answers = QuizAnswer::live()->whereIn('quiz_session_id', $sessionIds);
        $byResult = (clone $answers)->select('result', DB::raw('COUNT(*) as c'))->groupBy('result')->pluck('c', 'result');

        $categoryPerf = QuizRound::query()
            ->join('questions', 'questions.id', '=', 'quiz_rounds.question_id')
            ->join('question_categories', 'question_categories.id', '=', 'questions.question_category_id')
            ->whereIn('quiz_rounds.quiz_session_id', $sessionIds)
            ->where('quiz_rounds.status', 'answered')
            ->groupBy('question_categories.name', 'question_categories.color')
            ->select('question_categories.name', 'question_categories.color',
                DB::raw('COUNT(*) as asked'),
                DB::raw("SUM(CASE WHEN quiz_rounds.result = 'correct' THEN 1 ELSE 0 END) as correct"))
            ->orderByDesc('asked')->get();

        $participants = Participant::withTrashed()
            ->joinSub(
                DB::table('quiz_session_participants')->whereIn('quiz_session_id', $sessionIds)
                    ->select('participant_id', DB::raw('SUM(score) as score'), DB::raw('SUM(answered) as answered'),
                        DB::raw('SUM(correct) as correct'), DB::raw('SUM(incorrect) as incorrect'),
                        DB::raw('COUNT(*) as sessions'), DB::raw('MAX(best_streak) as best_streak'))
                    ->groupBy('participant_id'),
                'agg', 'agg.participant_id', '=', 'participants.id')
            ->orderByDesc('agg.score')->get(['participants.*', 'agg.score', 'agg.answered', 'agg.correct', 'agg.incorrect', 'agg.sessions', 'agg.best_streak']);

        $completed = (clone $sessions)->where('status', 'completed')->pluck('id');
        $avgScore = DB::table('quiz_session_participants')->whereIn('quiz_session_id', $completed)->avg('score');

        return view('admin.reports.index', [
            'summary' => [
                'sessions' => $sessionIds->count(),
                'completed' => $completed->count(),
                'rounds' => QuizRound::whereIn('quiz_session_id', $sessionIds)->where('status', 'answered')->count(),
                'correct' => (int) ($byResult['correct'] ?? 0),
                'incorrect' => (int) (($byResult['incorrect'] ?? 0) + ($byResult['timed_out'] ?? 0)),
                'partial' => (int) ($byResult['partial'] ?? 0),
                'passed' => (int) ($byResult['passed'] ?? 0),
                'avg_score' => round((float) $avgScore, 1),
                'participants' => $participants->count(),
            ],
            'categoryPerf' => $categoryPerf,
            'participants' => $participants,
            'sessions' => (clone $sessions)->withCount('rounds')->latest('session_date')->limit(30)->get(),
            'topQuestions' => Question::with('category')->orderByDesc('times_used')->limit(10)->get(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function participantsCsv()
    {
        $rows = DB::table('quiz_session_participants as e')
            ->join('participants as p', 'p.id', '=', 'e.participant_id')
            ->groupBy('p.id', 'p.full_name', 'p.department', 'p.job_title')
            ->select('p.full_name', 'p.department', 'p.job_title', DB::raw('COUNT(*) as sessions'), DB::raw('SUM(e.score) as score'),
                DB::raw('SUM(e.answered) as answered'), DB::raw('SUM(e.correct) as correct'), DB::raw('SUM(e.incorrect) as incorrect'),
                DB::raw('SUM(e.passed) as passed'), DB::raw('MAX(e.best_streak) as best_streak'))
            ->orderByDesc('score')->get()
            ->map(fn ($r) => [$r->full_name, $r->department, $r->job_title, $r->sessions, $r->score, $r->answered, $r->correct, $r->incorrect, $r->passed,
                $r->answered ? round($r->correct / $r->answered * 100, 1) : 0, $r->best_streak]);

        return Csv::download('participant-performance.csv',
            ['participant', 'department', 'job_title', 'sessions', 'total_score', 'answered', 'correct', 'incorrect', 'passed', 'accuracy_%', 'best_streak'], $rows);
    }

    public function questionsCsv()
    {
        $stats = QuizRound::where('status', 'answered')->whereNotNull('question_id')
            ->groupBy('question_id')
            ->select('question_id', DB::raw('COUNT(*) as asked'), DB::raw("SUM(CASE WHEN result = 'correct' THEN 1 ELSE 0 END) as correct"))
            ->get()->keyBy('question_id');
        $rows = Question::withTrashed()->with('category')->orderBy('id')->get()->map(function ($q) use ($stats) {
            $s = $stats[$q->id] ?? null;

            return [$q->id, $q->category?->name, $q->difficulty, $q->type, $q->question_text, $q->times_used,
                $s->asked ?? 0, $s->correct ?? 0, ($s && $s->asked) ? round($s->correct / $s->asked * 100, 1) : '', $q->trashed() ? 'deleted' : ($q->is_active ? 'active' : 'disabled')];
        });

        return Csv::download('question-statistics.csv', ['id', 'category', 'difficulty', 'type', 'question', 'times_used', 'times_answered', 'times_correct', 'correct_%', 'status'], $rows);
    }
}
