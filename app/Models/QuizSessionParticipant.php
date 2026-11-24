<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizSessionParticipant extends Model
{
    protected $fillable = ['quiz_session_id', 'participant_id', 'team_id', 'is_excluded'];

    protected $casts = ['is_excluded' => 'boolean'];

    public const STAT_FIELDS = [
        'score', 'answered', 'correct', 'incorrect', 'partial', 'passed', 'timed_out',
        'current_streak', 'best_streak',
    ];

    public function session()
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class)->withTrashed();
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function accuracy(): float
    {
        $graded = $this->correct + $this->incorrect + $this->partial + $this->timed_out;

        return $graded ? round(($this->correct + 0.5 * $this->partial) / $graded * 100, 1) : 0.0;
    }
}
