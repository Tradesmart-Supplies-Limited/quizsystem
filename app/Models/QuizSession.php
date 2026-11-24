<?php

namespace App\Models;

use App\Support\GameDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class QuizSession extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'ready' => 'Ready', 'active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed'];

    public const SELECTION_MODES = [
        'random' => 'Completely random',
        'fair_rotation' => 'Fair rotation (unanswered first)',
        'remaining' => 'Random among remaining (no repeats until all picked)',
        'facilitator' => 'Facilitator selection',
        'team' => 'Team selection (team first, then member)',
    ];

    protected $fillable = [
        'title', 'workshop_title', 'session_date', 'department', 'selection_mode',
        'total_rounds', 'duration_minutes', 'settings',
    ];

    protected $casts = [
        'session_date' => 'date',
        'settings' => 'array',
        'state' => 'array',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function facilitator()
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function entries()
    {
        return $this->hasMany(QuizSessionParticipant::class);
    }

    public function participants()
    {
        return $this->belongsToMany(Participant::class, 'quiz_session_participants')
            ->withPivot(['id', 'team_id', 'is_excluded', 'score', 'answered', 'correct', 'incorrect', 'times_selected'])
            ->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(QuestionCategory::class, 'quiz_session_categories');
    }

    public function questionPool()
    {
        return $this->belongsToMany(Question::class, 'quiz_session_questions');
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function rounds()
    {
        return $this->hasMany(QuizRound::class)->orderBy('round_number');
    }

    public function answers()
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function events()
    {
        return $this->hasMany(SessionEvent::class)->latest('id');
    }

    /** Settings merged over defaults – always returns a complete structure. */
    public function config(?string $key = null, $default = null)
    {
        $merged = GameDefaults::mergeConfig(GameDefaults::session(), $this->settings ?? []);

        return $key ? Arr::get($merged, $key, $default) : $merged;
    }

    public function stateValue(string $key, $default = null)
    {
        return Arr::get($this->state ?? [], $key, $default);
    }

    public function setStateValue(string $key, $value): void
    {
        $state = $this->state ?? [];
        Arr::set($state, $key, $value);
        $this->state = $state;
    }

    public function isPlayable(): bool
    {
        return in_array($this->status, ['ready', 'active', 'paused'], true);
    }

    public function logEvent(string $type, array $payload = [], ?int $userId = null): void
    {
        $this->events()->create(['type' => $type, 'payload' => $payload, 'user_id' => $userId ?? auth()->id()]);
    }
}
