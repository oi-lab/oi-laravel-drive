<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_folder_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->morphs('shareable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['folder_id', 'shareable_type', 'shareable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folder_shares');
    }
};
