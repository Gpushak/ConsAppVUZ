<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            throw new \RuntimeException('Схема записи на консультацию рассчитана на MySQL 8.');
        }

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('group_name', 32);
            $table->timestamps();
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('department');
            $table->timestamps();
        });

        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('building', 32);
            $table->string('room', 32);
            $table->string('comment', 500)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->dateTime('conducted_at')->nullable();
            $table->timestamps();
            $table->index(['teacher_profile_id', 'starts_at']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('student_profile_id');
            $table->string('status', 32);
            $table->string('teacher_comment', 500)->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('booked_pair', 64)->nullable()->storedAs(
                "IF(status = 'booked', CONCAT(slot_id, ':', student_profile_id), NULL)"
            );
            $table->timestamps();
            $table->index(['slot_id', 'student_profile_id']);
            $table->unique('booked_pair');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('slot_id')->references('id')->on('slots');
            $table->foreign('student_profile_id')->references('id')->on('student_profiles');
            $table->foreign('cancelled_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['student_profile_id', 'teacher_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('slots');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('student_profiles');
    }
};
