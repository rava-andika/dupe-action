<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Translation extends Model
{

    protected $guarded = [];

    // Clear cache when a translation is updated or deleted
    protected static function booted(): void
    {
        /*
            Created not being added since at default when created the translation
            is not active so there is no need to clear the cache

            the translation can't be deleted
        */

        static::updated(function (Translation $translation) {
            Cache::forget("translations.$translation->locale.{$translation->getOriginal('group')}");

            if ($translation->isDirty(['group', 'key', 'value'])) {
                admin_log('edit', 'translation', record: $translation);
            }
        });
    }
}
