<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\Faq;
use App\Models\GeneralInfo;
use App\Models\Registration;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use App\Models\UserProfile;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(TranslationsSeeder::class);

        if (User::count() == 0) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'privileges' => getAdminResources(),
            ]);
        }

        // need to be seed since the admin can't create it
        if (GeneralInfo::count() == 0){
            GeneralInfo::factory()->create();
        }

        if (env('APP_ENV') === 'local') {
            if (Competition::count() == 0){
                Competition::factory(5)->create();
            }
            if (Faq::count() === 0){
                Faq::factory(20)->create();
            }
            if (Team::count() === 0){
                Team::factory(10)->create();
            }
            if (UserProfile::count() === 0){
                UserProfile::factory(5)->create();
            }
            if (Registration::count() === 0){
                Registration::factory(8)->create();
            }
            if (Submission::count() === 0){
                Submission::factory(8)->create();
            }
        }
    }
}
