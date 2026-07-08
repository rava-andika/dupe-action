<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submission' => 'array',
        'feedback' => 'array',
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
        static::updated(function (Submission $submission) {
            admin_log('edit', 'submissions', record: $submission);
        });
    }
}
