<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_folder_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->unique()->constrained('folders')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folder_links');
    }
};
