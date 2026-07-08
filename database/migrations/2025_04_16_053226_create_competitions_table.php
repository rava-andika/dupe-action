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
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->index();
            $table->json('description');
            $table->json('short_desc');
            $table->string('image');
            $table->string('guidebook');
            $table->integer('price');
            $table->json('contacts');
            $table->integer('min_team_size')->default(1);
            $table->integer('max_team_size');
            $table->json('timeline');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
