<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'notes' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::updated(function (Registration $registration) {
            admin_log('edit', 'registrations', record: $registration);
        });
    }
}
