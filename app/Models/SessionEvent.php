<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'payload', 'user_id'];

    protected $casts = ['payload' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
