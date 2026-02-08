<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Terminal session model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $session_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $end_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class TerminalSession extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'started_at',
        'ended_at',
        'ip_address',
        'user_agent',
        'end_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the user that owns the session.
     *
     * @return BelongsTo<\Illuminate\Foundation\Auth\User, $this>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    /**
     * Scope to get only active sessions.
     *
     * @param Builder<TerminalSession> $query
     * @return Builder<TerminalSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope to get sessions for a specific user.
     *
     * @param Builder<TerminalSession> $query
     * @return Builder<TerminalSession>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if the session is active.
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * End the session.
     */
    public function end(string $reason = 'user'): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->update([
            'ended_at' => now(),
            'end_reason' => $reason,
        ]);
    }

    /**
     * Get session duration in seconds.
     */
    public function getDurationInSeconds(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        $endTime = $this->ended_at ?? now();

        return (int) $this->started_at->diffInSeconds($endTime);
    }
}
