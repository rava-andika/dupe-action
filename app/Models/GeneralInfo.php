<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GeneralInfo extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    protected $casts = [
        'sponsors' => 'array',
        'contact' => 'array',
        'social_media' => 'array',
        'social_media_to_follow' => 'array',
        'payment_methods' => 'array',
    ];

    // Clear cache when a general info is updated or deleted
    protected static function booted(): void
    {
        /*
            Created and deleted not being added since at this table only will be one row
        */
        static::updated(function (GeneralInfo $generalInfo) {
            Cache::forget('general_info');
            admin_log('edit', 'general-info', record: $generalInfo);
        });
    }
}
