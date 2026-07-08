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
            $table->renameColumn('contacts', 'contact');
            $table->renameColumn('medsos', 'social_media');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_infos', function (Blueprint $table) {
            $table->renameColumn('contact', 'contacts');
            $table->renameColumn('social_media', 'medsos');
        });
    }
};
