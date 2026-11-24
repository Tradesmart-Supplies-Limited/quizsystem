<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizRound extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'double_or_nothing' => 'boolean',
        'multiplier' => 'float',
        'answered_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class)->withTrashed();
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class);
    }
}
