<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->after('teacher_profile_id');
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn('series_id');
        });
    }
};
