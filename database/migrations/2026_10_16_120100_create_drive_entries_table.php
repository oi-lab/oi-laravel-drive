<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_id')->constrained('drives')->cascadeOnDelete();
            $table->morphs('entry');
            $table->string('kind', 16);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('trashed_with_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entry_type', 'entry_id']);
            $table->index(['drive_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_entries');
    }
};
