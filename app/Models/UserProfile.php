<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UserProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'birth_date' => 'date',
        'follow_proof' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (UserProfile $userProfile) {
            deleteOldFile($userProfile->institution_card, 'local', 'institution-card.show');
            deleteOldFile($userProfile->follow_proof, 'local', 'follow-proof.show');
            deleteOldFile($userProfile->twibbon_proof, 'local', 'twibbon-proof.show');
            Cache::forget("user-profile-{$userProfile->user_id}");
            Cache::forget("user-{$userProfile->user_id}-dashboard");
        });

        static::updated(function (UserProfile $userProfile) {
            Cache::forget("user-profile-{$userProfile->user_id}");
        });
    }
}
