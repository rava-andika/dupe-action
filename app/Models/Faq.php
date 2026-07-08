<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Faq extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'question' => 'array',
        'answer' => 'array',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    // Clear cache when a faq is created, updated or deleted
    protected static function booted(): void
    {
        static::created(function (Faq $faq) {
            Cache::forget('faq');
            $question = json_encode($faq->question);
            admin_log('create', 'faqs', "Question: {$question}");
        });
        
        static::updated(function (Faq $faq) {
            Cache::forget('faq');
            admin_log('edit', 'faqs', record: $faq);
        });

        static::deleted(function (Faq $faq) {
            Cache::forget('faq');
            $question = json_encode($faq->question);
            admin_log('delete', 'faqs', "Question: {$question}");
        });

        static::restored(function (Faq $faq) {
            Cache::forget('faq');
            $question = json_encode($faq->question);
            admin_log('restore', 'faqs', "Question: {$question}");
        });
    }
}
