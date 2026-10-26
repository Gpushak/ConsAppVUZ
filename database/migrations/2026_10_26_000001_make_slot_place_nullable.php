<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->string('building', 32)->nullable()->change();
            $table->string('room', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->string('building', 32)->nullable(false)->change();
            $table->string('room', 32)->nullable(false)->change();
        });
    }
};
