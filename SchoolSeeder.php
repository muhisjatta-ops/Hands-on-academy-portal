<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\GradeBand;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for Hand On Academy: one year, three terms, three classes,
 * six subjects, twelve students with guardians, a fee structure, invoices
 * and a few payments.
 *
 * Run on a fresh database only:  php artisan db:seed --class=SchoolSeeder
 */
class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (Student::exists()) {
            $this->command->warn('Students already exist. Skipping to avoid duplicates.');
            return;
        }

        // ---- grading scale ----
        $bands = [
            ['A', 80, 100, 'Excellent'],
            ['B', 70, 79.99, 'Very good'],
            ['C', 60, 69.99, 'Good'],
            ['D', 50, 59.99, 'Satisfactory'],
            ['E', 40, 49.99, 'Weak'],
            ['F', 0,  39.99, 'Fail'],
        ];

        foreach ($bands as $i => [$letter, $min, $max, $remark]) {
            GradeBand::create([
                'letter' => $letter, 'min_percent' => $min,
                'max_percent' => $max, 'remark' => $remark, 'sequence' => $i,
            ]);
        }

        // ---- academic year and terms ----
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-07',
            'end_date' => '2027-07-16',
            'is_current' => true,
        ]);

        $terms = [
            ['Term 1', '2026-09-07', '2026-12-11'],
            ['Term 2', '2027-01-05', '2027-04-02'],
            ['Term 3', '2027-04-19', '2027-07-16'],
        ];

        foreach ($terms as $i => [$name, $start, $end]) {
            $year->terms()->create([
                'name' => $name, 'sequence' => $i + 1,
                'start_date' => $start, 'end_date' => $end,
                'is_current' => $i === 0,
            ]);
        }

        $term1 = $year->terms()->where('sequence', 1)->first();

        // ---- grade levels, classes, subjects ----
        $levels = collect(['Grade 4', 'Grade 5', 'Grade 6'])
            ->map(fn ($name, $i) => GradeLevel::create(['name' => $name, 'sequence' => $i + 4]));

        $subjects = collect([
            ['Mathematics', 'MATH'],
            ['English Language', 'ENG'],
            ['Integrated Science', 'SCI'],
            ['Social Studies', 'SOC'],
            ['Quranic Studies', 'QUR'],
            ['Physical Education', 'PE'],
        ])->map(function ($pair) use ($levels) {
            $subject = Subject::create(['name' => $pair[0], 'code' => $pair[1]]);
            $subject->gradeLevels()->sync($levels->pluck('id'));
            return $subject;
        });

        $teacher = User::where('email', 'teacher@school.test')->first()
            ?? User::create([
                'name' => 'Mrs Jallow', 'username' => 'mjallow',
                'email' => 'teacher@school.test', 'password' => 'Teacher12345',
                'is_active' => true,
            ])->assignRole('teacher');

        $classes = $levels->map(fn ($level) => ClassRoom::create([
            'grade_level_id'   => $level->id,
            'academic_year_id' => $year->id,
            'stream'           => 'Blue',
            'class_teacher_id' => $teacher->id ?? null,
            'capacity'         => 40,
        ]));

        // Give the teacher real assignments, so the row-level policy has
        // something to allow as well as something to refuse.
        foreach ($classes as $class) {
            foreach ($subjects->take(3) as $subject) {
                TeachingAssignment::create([
                    'user_id'       => $teacher->id,
                    'class_room_id' => $class->id,
                    'subject_id'    => $subject->id,
                ]);
            }
        }

        // ---- fees ----
        $tuition = FeeCategory::create(['name' => 'Tuition fee']);
        $registration = FeeCategory::create(['name' => 'Registration fee']);
        $exam = FeeCategory::create(['name' => 'Examination fee']);

        foreach ($year->terms as $term) {
            foreach ($levels as $i => $level) {
                FeeStructure::create([
                    'fee_category_id' => $tuition->id,
                    'grade_level_id'  => $level->id,
                    'term_id'         => $term->id,
                    'amount'          => 15000 + ($i * 1000),
                ]);
                FeeStructure::create([
                    'fee_category_id' => $exam->id,
                    'grade_level_id'  => $level->id,
                    'term_id'         => $term->id,
                    'amount'          => 750,
                ]);
            }
        }

        // Registration only in Term 1.
        foreach ($levels as $level) {
            FeeStructure::create([
                'fee_category_id' => $registration->id,
                'grade_level_id'  => $level->id,
                'term_id'         => $term1->id,
                'amount'          => 1000,
            ]);
        }

        // ---- students ----
        $names = [
            ['Muhammed', 'Jatta', 'M'],   ['Fatoumata', 'Ceesay', 'F'],
            ['Amadou', 'Sanneh', 'M'],    ['Isatou', 'Bah', 'F'],
            ['Lamin', 'Touray', 'M'],     ['Mariama', 'Jallow', 'F'],
            ['Ebrima', 'Camara', 'M'],    ['Aminata', 'Njie', 'F'],
            ['Ousman', 'Darboe', 'M'],    ['Binta', 'Sow', 'F'],
            ['Modou', 'Faal', 'M'],       ['Jankeba', 'Kanteh', 'F'],
        ];

        foreach ($names as $i => [$first, $last, $gender]) {
            $student = Student::create([
                'admission_number' => 'HOA-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'first_name'    => $first,
                'last_name'     => $last,
                'date_of_birth' => now()->subYears(10 + ($i % 3))->subDays($i * 11)->toDateString(),
                'gender'        => $gender,
                'admission_date' => '2026-09-07',
                'status'        => 'ACTIVE',
            ]);

            $guardian = Guardian::create([
                'full_name'    => ($gender === 'M' ? 'Mr ' : 'Mrs ') . $last,
                'relationship' => $gender === 'M' ? 'Father' : 'Mother',
                'phone'        => '44' . (70 + $i) . str_pad((string) ($i * 7), 3, '0', STR_PAD_LEFT),
            ]);

            $student->guardians()->attach($guardian->id, [
                'is_primary' => true, 'is_fee_payer' => true,
            ]);

            $student->enrollments()->create([
                'class_room_id'    => $classes[$i % 3]->id,
                'academic_year_id' => $year->id,
                'date_enrolled'    => '2026-09-07',
            ]);
        }

        // ---- invoices and some payments ----
        DB::transaction(function () use ($term1) {
            $generator = app(\App\Services\InvoiceGenerator::class);
            $recorder = app(\App\Services\PaymentRecorder::class);

            foreach (Student::active()->get() as $i => $student) {
                $invoice = $generator->forStudent($student, $term1);

                if ($i % 4 === 3) {
                    continue;   // leave a quarter of the roll unpaid
                }

                $recorder->record($student, [
                    'amount'  => $i % 3 === 0 ? $invoice->total : round($invoice->total * 0.4, 2),
                    'method'  => ['CASH', 'MOBILE', 'BANK'][$i % 3],
                    'paid_on' => now()->subDays(20 - $i)->toDateString(),
                ]);
            }
        });

        $this->command->info('Hand On Academy sample data loaded.');
        $this->command->info('12 students, 3 classes, 6 subjects, Term 1 invoices and payments.');
    }
}
