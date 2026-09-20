# Phase 2–4: students, fees, grades, attendance

This adds the four working modules on top of the Phase 1 auth foundation.
It assumes Phase 1 is already installed and you can log in.

Same rule as before: two terminals, `php artisan serve` and `npm run dev`.

---

## 1. Where the backend files go

All paths are inside your `backend/` folder. Create folders that don't exist.

| File | Destination |
|---|---|
| `2026_09_16_000300_create_academic_structure_tables.php` | `database/migrations/` |
| `2026_09_16_000400_create_student_tables.php` | `database/migrations/` |
| `2026_09_16_000500_create_fee_tables.php` | `database/migrations/` |
| `2026_09_16_000600_create_academic_record_tables.php` | `database/migrations/` |
| `AcademicYear.php` `Term.php` `GradeLevel.php` `ClassRoom.php` `Subject.php` `TeachingAssignment.php` `GradeBand.php` | `app/Models/` |
| `Student.php` `Guardian.php` `Enrollment.php` | `app/Models/` |
| `FeeCategory.php` `FeeStructure.php` `Discount.php` `Invoice.php` `InvoiceLine.php` `Payment.php` `PaymentAllocation.php` | `app/Models/` |
| `Assessment.php` `Score.php` `AttendanceRecord.php` | `app/Models/` |
| `InvoiceGenerator.php` `PaymentRecorder.php` `GradeCalculator.php` | `app/Services/` |
| `StudentController.php` `FeeController.php` `PaymentController.php` `GradeController.php` `AttendanceController.php` `DashboardController.php` `SetupController.php` | `app/Http/Controllers/` |
| `ScorePolicy.php` `StudentPolicy.php` | `app/Policies/` |
| `SchoolSeeder.php` | `database/seeders/` |
| `api-phase2.php` | see step 2 — don't copy it as a file |

## 2. Wire up the routes

Open `routes/api.php` from Phase 1. Find this comment inside the
`Route::middleware('two-factor')->group(...)` block:

```php
// --- Phase 2+ goes here ---
```

Replace it with the contents of `api-phase2.php`, minus its `<?php` line and
its `use` statements — move those `use` statements to the top of
`routes/api.php` alongside the ones already there.

## 3. Register the policies

In `app/Providers/AppServiceProvider.php`, inside `boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::policy(\App\Models\Score::class, \App\Policies\ScorePolicy::class);
Gate::policy(\App\Models\Student::class, \App\Policies\StudentPolicy::class);
```

## 4. Migrate and seed

```bash
php artisan migrate
php artisan db:seed --class=SchoolSeeder
```

The seeder creates the 2026/2027 year with three terms, three classes, six
subjects, twelve students with guardians, a Term 1 fee structure, invoices
and some payments. It refuses to run twice, so you can't duplicate data by
accident.

Then set up assessment components once, so the Grades screen has columns.
The seeder doesn't do this because your weighting is a school decision:

```bash
php artisan tinker
```

```php
$term = App\Models\Term::current();
foreach (App\Models\ClassRoom::all() as $class) {
    foreach (App\Models\Subject::all() as $subject) {
        foreach ([['Continuous assessment', 30], ['Mid-term', 20], ['Final examination', 50]] as [$name, $weight]) {
            App\Models\Assessment::create([
                'class_room_id' => $class->id,
                'subject_id' => $subject->id,
                'term_id' => $term->id,
                'name' => $name,
                'weight' => $weight,
                'max_score' => 100,
            ]);
        }
    }
}
```

## 5. Where the frontend files go

Inside `frontend/`:

| File | Destination |
|---|---|
| `domain.ts` | `src/lib/domain.ts` |
| `school.ts` | `src/lib/school.ts` |
| `useRequest.ts` | `src/hooks/useRequest.ts` |
| `Shell.tsx` | `src/components/Shell.tsx` |
| `DashboardPage.tsx` `StudentsPage.tsx` `FeesPage.tsx` `GradesPage.tsx` `AttendancePage.tsx` | `src/pages/` |
| `main.tsx` | `src/main.tsx` — **overwrite** the Phase 1 one |

Nothing new to install. Restart `npm run dev` and sign in.

---

## 6. What to check

1. Dashboard shows 12 students and Term 1 figures
2. Students — search "Jatta", filter by class, admit a new student and watch
   the admission number generate as HOA-2026-0013
3. Fees — press **Generate invoices**, then **Pay** on a student. Pay less
   than the balance and confirm the receipt shows a part payment
4. Grades — enter marks, press Save, confirm totals and positions compute
5. Attendance — mark a register, save, reload the page and confirm it persists
6. **The important one:** sign in as `teacher@school.test` / `Teacher12345`.
   The Fees tab should be gone. Grades should work for Mathematics, English
   and Integrated Science, and return a 403 for Social Studies — because the
   seeder only assigned the first three subjects to that teacher.

That last check is the row-level policy working. If a teacher can open a
subject they don't teach, something is wrong in `ScorePolicy` or the
`authorizeSheet` method.

---

## 7. What is deliberately not here

- **Report card PDFs.** The data endpoint exists
  (`GET /students/{id}/report-card`) but nothing renders it yet. Add that
  with the `pdf` skill when you're ready.
- **A Setup screen.** The endpoints exist under `/setup/*`; the UI doesn't.
  Use tinker for now.
- **Guardian management UI.** Admission creates one guardian; adding a second
  or editing one needs a screen.
- **Timetable, announcements, SMS.** Phases 5 and 6 in your document.

## 8. Two design decisions worth knowing

**No balance column anywhere.** Balances are computed from invoice lines
minus payment allocations on every read. A stored balance eventually
disagrees with the rows beneath it, and then nobody can tell which is right.

**Payments are append-only.** There is no endpoint that edits or deletes a
payment. A mistake is corrected by `POST /payments/{id}/reverse`, which
writes a negative row pointing at the original. The receipt sequence stays
unbroken and the error stays visible — which is exactly what you want when a
parent disputes what they paid.
