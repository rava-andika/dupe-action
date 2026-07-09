<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class TranslationsSeeder extends Seeder
{
    public function run()
    {
        $langPath = resource_path('lang');

        foreach (File::directories($langPath) as $localePath) {
            $locale = basename($localePath);

            foreach (File::files($localePath) as $file) {
                $group = $file->getFilenameWithoutExtension();
                $translations = require $file->getRealPath();

                $flattened = $this->flattenArray($translations);

                foreach ($flattened as $key => $value) {
                    Translation::updateOrCreate([
                        'locale' => $locale,
                        'group'  => $group,
                        'key'    => $key,
                    ], [
                        'value'     => $value,
                        'is_active' => true,
                    ]);
                }
                Cache::forget("translations.$locale.$group");
            }
        }
        Cache::forget('supportedLocales');
    }

    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : "$prefix.$key";

            if (is_array($value)) {
                $result += $this->flattenArray($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
