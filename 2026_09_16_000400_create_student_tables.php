<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 30)->unique();
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();     // for the student portal later

            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->date('date_of_birth');
            $table->char('gender', 1);               // M | F
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->date('admission_date');
            $table->string('status', 12)->default('ACTIVE');
            // ACTIVE | WITHDRAWN | GRADUATED | SUSPENDED
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            // Soft deletes, not hard: a withdrawn student's fee and mark
            // history must remain auditable.

            $table->index(['last_name', 'first_name']);
            $table->index('status');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();     // for the parent portal
            $table->string('full_name', 120);
            $table->string('relationship', 40);      // Father, Aunt, Sponsor
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index('phone');
        });

        /**
         * Many-to-many, because siblings share guardians. Storing the
         * guardian on the student would duplicate one parent across four
         * children and guarantee the four copies drift apart.
         */
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fee_payer')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });

        /**
         * A student's class for one academic year. One row per year, never
         * overwritten — this is the student's history.
         */
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_room_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->date('date_enrolled');
            $table->boolean('is_repeating')->default(false);
            $table->string('outcome', 20)->nullable();   // PROMOTED | REPEAT | LEFT
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
            $table->index('class_room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
