<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value application settings (replaces quiz_settings + sound_settings tables).
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 30)->index();
            $table->string('key', 80)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('reaction_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 15)->index(); // correct | incorrect
            $table->string('message', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reaction_messages');
        Schema::dropIfExists('app_settings');
    }
};
