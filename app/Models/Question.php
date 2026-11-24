<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'multiple_choice' => 'Multiple choice',
        'true_false' => 'True or false',
        'short_answer' => 'Short answer',
        'open_ended' => 'Open-ended',
        'scenario' => 'Scenario-based',
        'practical' => 'Practical challenge',
        'team' => 'Team question',
        'bonus' => 'Bonus question',
        'mystery' => 'Mystery question',
    ];

    public const DIFFICULTIES = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];

    protected $fillable = [
        'question_category_id', 'type', 'difficulty', 'question_text', 'correct_answer',
        'explanation', 'points', 'time_limit', 'is_bonus', 'is_danger', 'is_active',
    ];

    protected $casts = [
        'is_bonus' => 'boolean',
        'is_danger' => 'boolean',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'points' => 'integer',
        'time_limit' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id')->withTrashed();
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function rounds()
    {
        return $this->hasMany(QuizRound::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /** Replace all options from a simple array of ['text' => .., 'is_correct' => ..]. */
    public function syncOptions(array $options): void
    {
        $this->options()->delete();
        $labels = range('A', 'H');
        $i = 0;
        foreach ($options as $opt) {
            $text = trim((string) ($opt['text'] ?? ''));
            if ($text === '' || $i >= count($labels)) {
                continue;
            }
            $this->options()->create([
                'label' => $labels[$i],
                'option_text' => $text,
                'is_correct' => ! empty($opt['is_correct']),
                'sort_order' => $i,
            ]);
            $i++;
        }
    }
}
