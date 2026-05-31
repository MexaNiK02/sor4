<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    protected $fillable = ['game_session_id', 'participant_user_id', 'name', 'avatar_color', 'score', 'access_token', 'last_seen_at'];

    protected $casts = [
        'score' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = ['access_token'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ParticipantAnswer::class);
    }
}
