<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Models\QuizSessionParticipant;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $topParticipants = QuizSessionParticipant::query()
            ->select('participant_id', DB::raw('SUM(score) as total_score'), DB::raw('SUM(correct) as total_correct'), DB::raw('SUM(answered) as total_answered'))
            ->groupBy('participant_id')->orderByDesc('total_score')->limit(8)
            ->with('participant')->get();

        return view('admin.dashboard', [
            'stats' => [
                'questions' => Question::count(),
                'active_questions' => Question::active()->count(),
                'participants' => Participant::count(),
                'sessions' => QuizSession::count(),
                'categories' => QuestionCategory::active()->count(),
                'live' => QuizSession::whereIn('status', ['active', 'paused'])->count(),
            ],
            'recentSessions' => QuizSession::with('facilitator')->latest()->limit(8)->get(),
            'topQuestions' => Question::with('category')->where('times_used', '>', 0)->orderByDesc('times_used')->limit(8)->get(),
            'topParticipants' => $topParticipants,
            'liveSessions' => QuizSession::whereIn('status', ['ready', 'active', 'paused'])->latest('updated_at')->limit(5)->get(),
        ]);
    }
}
