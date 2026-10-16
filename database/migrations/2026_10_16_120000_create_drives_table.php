<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drives', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->morphs('driveable');
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('props')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drives');
    }
};
