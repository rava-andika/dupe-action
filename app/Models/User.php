<?php

namespace App\Models;

use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['password_last_changed'];

    protected $casts = [
        'password' => 'hashed',
        'privileges' => 'array',
        'password_changed_at' => 'datetime',
    ];

    protected function passwordLastChanged(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->password_changed_at?->diffForHumans(),
        );
    }

    protected static function booted(): void
    {
        Gate::before(function (User $user, string $ability) {
            return $user->privileges && in_array($ability, $user->privileges);
        });

        static::forceDeleting(function (User $user) {
            $user->notifications()->delete();
            deleteOldFile($user->avatar);
            $user->profile?->delete();
        });

        static::updating(function (User $user) {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            };
            
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            };
        });

        static::updated(function (User $user) {
            if ($user->isDirty('avatar')) {
                $user->load('teams.members');

                foreach ($user->teams as $team) {
                    foreach ($team->members as $user) {
                        Cache::forget("user-{$user->id}-dashboard");
                    }
                }
            }
            
            if($user->isDirty('privileges')) {
                admin_log('edit', 'users', record: $user);
            }
        });
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            (new PasswordResetNotification($token))
                ->locale(app()->getLocale())
        );
    }

    public function sendEmailVerificationNotification($redirectURL = null): void
    {
        $this->notify(
            (new EmailVerificationNotification($redirectURL))
                ->locale(app()->getLocale())
        );
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withTimestamps();
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }
}
