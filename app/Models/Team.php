<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = ['name', 'color'];

    public function session()
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function members()
    {
        return $this->hasMany(QuizSessionParticipant::class);
    }

    public function totalScore(): int
    {
        return (int) $this->members()->sum('score') + (int) $this->bonus_points;
    }
}
