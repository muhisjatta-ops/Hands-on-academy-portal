<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The academic spine. Everything else in the system points at a term,
 * which points at an academic year. Build this first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();          // "2026/2027"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);                    // "Term 1"
            $table->unsignedSmallInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
        });

        // The rung on the ladder: Grade 4, Form 3. Survives across years.
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->unsignedSmallInteger('sequence')->unique();
            $table->timestamps();
        });

        // A concrete class group in one year: "Grade 4 Blue, 2026/2027".
        Schema::create('class_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('stream', 20)->nullable();      // "Blue", "A"
            $table->foreignId('class_teacher_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->timestamps();

            $table->unique(['grade_level_id', 'academic_year_id', 'stream']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 20)->unique();
            $table->boolean('is_examinable')->default(true);
            $table->timestamps();
        });

        // Which subjects a grade level takes.
        Schema::create('grade_level_subject', function (Blueprint $table) {
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->primary(['grade_level_id', 'subject_id']);
        });

        /**
         * Which teacher teaches which subject in which class.
         *
         * This table is what makes "a teacher may only enter marks for their
         * own classes" enforceable. Without it that rule cannot be expressed,
         * and you end up trusting the frontend.
         */
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'class_room_id', 'subject_id']);
            $table->index(['class_room_id', 'subject_id']);
        });

        // Editable grading scale, so the school can change bands without
        // touching a single stored mark.
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->string('letter', 5);
            $table->decimal('min_percent', 5, 2);
            $table->decimal('max_percent', 5, 2);
            $table->string('remark', 60)->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('grade_level_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('class_rooms');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
