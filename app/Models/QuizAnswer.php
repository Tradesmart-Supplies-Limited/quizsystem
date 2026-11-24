<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
        'is_override' => 'boolean',
        'undone_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(QuizRound::class, 'quiz_round_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class)->withTrashed();
    }

    public function scopeLive($q)
    {
        return $q->whereNull('undone_at');
    }
}
