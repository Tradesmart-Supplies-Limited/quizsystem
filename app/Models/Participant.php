<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['full_name', 'display_name', 'department', 'job_title', 'email', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['photo_url', 'initials'];

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            if (blank($p->display_name)) {
                $p->display_name = strtok(trim($p->full_name), ' ') ?: $p->full_name;
            }
        });
    }

    public function sessionEntries()
    {
        return $this->hasMany(QuizSessionParticipant::class);
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class)->whereNull('undone_at');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('uploads/'.$this->photo_path) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name ?? '')) ?: [];
        $init = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $init .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        return $init ?: '?';
    }

    /** Lifetime totals across all sessions (only non-undone answers). */
    public function lifetimeStats(): array
    {
        $rows = $this->sessionEntries()->get();

        return [
            'score' => (int) $rows->sum('score'),
            'answered' => (int) $rows->sum('answered'),
            'correct' => (int) $rows->sum('correct'),
            'incorrect' => (int) $rows->sum('incorrect'),
            'sessions' => $rows->count(),
        ];
    }
}
