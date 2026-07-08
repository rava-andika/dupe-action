<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withTimestamps();
    }

    public function bans(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_bans')
            ->withTimestamps();
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function generateInviteCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (self::where('invite_code', $code)->exists());
        $this->invite_code = $code;
        return $code;
    }

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            $team->generateInviteCode();
            $team->public_id = Str::uuid();
        });

        static::deleted(function (Team $team) {
            foreach ($team->members as $member) {
                Cache::forget("user-{$member->id}-dashboard");
            };
            admin_log('delete', 'teams', "Name: {$team->name}");
        });

        static::restored(function (Team $team) {
            foreach ($team->members as $member) {
                Cache::forget("user-{$member->id}-dashboard");
            };

            admin_log('restore', 'teams', "Name: {$team->name}");
        });
    }
}
