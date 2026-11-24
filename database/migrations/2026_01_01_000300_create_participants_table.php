<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('display_name', 60);
            $table->string('department', 100)->nullable()->index();
            $table->string('job_title', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
