<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function playedSessions()
    {
        return $this->hasMany(Participant::class, 'participant_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function isParticipant(): bool
    {
        return $this->role === 'participant';
    }

    public function canManageQuizzes(): bool
    {
        return $this->isAdmin() || $this->isHost();
    }

    public function issueApiToken(): string
    {
        $this->forceFill(['api_token' => Str::random(64)])->save();

        return $this->api_token;
    }
}
