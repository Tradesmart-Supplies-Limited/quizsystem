<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QuestionCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'color', 'icon', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $c) {
            if (blank($c->slug) || $c->isDirty('name')) {
                $base = Str::slug($c->name) ?: 'category';
                $slug = $base;
                $i = 2;
                while (static::withTrashed()->where('slug', $slug)->where('id', '!=', $c->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $c->slug = $slug;
            }
        });
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
