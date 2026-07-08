<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Competition extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'description' => 'array',
        'short_desc' => 'array',
        'contacts' => 'array',
        'enforce_single_team_rule' => 'boolean',
        'timeline' => 'array'
    ];

    public static function clearCompetitionCaches()
    {
        Cache::forget('competitions');
        Cache::forget('competitions_home');
    }

    private static function clearRelatedDashboardCaches(Competition $competition): void
    {
        $competition->load('teams.members');

        foreach ($competition->teams as $team) {
            foreach ($team->members as $user) {
                Cache::forget("user-{$user->id}-dashboard");
            }
        }
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class);
    }

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    // Clear cache when a competition is created, updated or deleted
    protected static function booted(): void
    {
        static::created(function (Competition $competition) {
            self::clearCompetitionCaches();
            admin_log('create', 'competitions', "Name: {$competition->name}");
        });

        static::deleted(function (Competition $competition) {
            self::clearCompetitionCaches();
            self::clearRelatedDashboardCaches($competition);

            // Delete the associated children
            $competition->faqs()->delete();
            Cache::forget('faq');
            admin_log('delete', 'competitions', "Name: {$competition->name}");
        });

        static::restored(function (Competition $competition) {
            self::clearCompetitionCaches();
            self::clearRelatedDashboardCaches($competition);

            $competition->faqs()->withTrashed()->restore();
            Cache::forget('faq');
            admin_log('restore', 'competitions', "Name: {$competition->name}");
        });

        static::updated(function (Competition $competition) {
            if ($competition->isDirty('name')) {
                Cache::forget('competitions');
                Cache::forget('faq');
            }

            if ($competition->isDirty(['name', 'image', 'short_desc', 'timeline'])) {
                Cache::forget('competitions_home');
            }

            if ($competition->isDirty('name', 'timeline')) {
                self::clearRelatedDashboardCaches($competition);
            }

            Cache::forget('competition_' . $competition->getOriginal('name'));

            admin_log('edit', 'competitions', record: $competition);
        });
    }
}
