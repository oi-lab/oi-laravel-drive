<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_entries', function (Blueprint $table) {
            $table->string('key', 191)->nullable()->after('kind');
            $table->string('role', 32)->nullable()->after('key');
            $table->nullableMorphs('source');
            $table->boolean('locked')->default(false);
            $table->boolean('sealed')->default(false);

            $table->unique(['drive_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('drive_entries', function (Blueprint $table) {
            $table->dropUnique(['drive_id', 'key']);
            $table->dropMorphs('source');
            $table->dropColumn(['key', 'role', 'locked', 'sealed']);
        });
    }
};
