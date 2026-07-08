<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('general_infos', function (Blueprint $table) {
            $table->json('social_media_to_follow')->after('social_media');
            $table->string('twibbon_url')->after('social_media_to_follow');
            $table->json('payment_methods')->after('twibbon_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_infos', function (Blueprint $table) {
            $table->dropColumn('social_media_to_follow');
            $table->dropColumn('twibbon_url');
            $table->dropColumn('payment_methods');
        });
    }
};
