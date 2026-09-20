<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * One graded event: "Term 1 Mathematics mid-term, Grade 4 Blue".
         * `weight` is how much it contributes to the subject's term mark,
         * so your 30/20/50 split lives in data, not in code.
         */
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);               // Continuous assessment
            $table->decimal('max_score', 6, 2)->default(100);
            $table->decimal('weight', 5, 2)->default(100);
            $table->date('assessed_on')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['class_room_id', 'subject_id', 'term_id', 'name'], 'assessment_unique');
        });

        /**
         * Store the RAW mark only. Percentages, letter grades and positions
         * are all computed on read — so re-grading the scale, or fixing a
         * weighting mistake, never requires rewriting student marks.
         */
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();   // null = not sat
            $table->string('remark', 200)->nullable();
            $table->foreignId('entered_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->date('attended_on');
            $table->char('state', 1);                 // P | A | L | E
            $table->string('reason', 200)->nullable();
            $table->foreignId('recorded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'attended_on']);
            $table->index(['class_room_id', 'attended_on']);
            $table->index(['term_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('assessments');
    }
};
