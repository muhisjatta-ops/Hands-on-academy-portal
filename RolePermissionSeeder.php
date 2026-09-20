<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions are verbs on nouns: "students.view", "fees.record_payment".
 * Roles are bundles of those verbs. Code should check PERMISSIONS, not roles
 * — then the head teacher can grant a clerk one extra ability without you
 * shipping a new release.
 *
 * The one thing RBAC cannot express is SCOPE: "a teacher may enter scores,
 * but only for classes they teach". That is a row-level rule and belongs in
 * a Laravel Policy, not here. Keep the two ideas separate.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // students
            'students.view', 'students.create', 'students.update',
            'students.delete', 'students.export',
            // academics
            'classes.manage', 'subjects.manage',
            'assessments.manage', 'scores.enter', 'scores.publish',
            'reports.generate',
            // fees
            'fees.view', 'fees.structure_manage', 'fees.invoice_generate',
            'fees.record_payment', 'fees.void_payment', 'fees.discount_grant',
            // attendance
            'attendance.view', 'attendance.record',
            // admin
            'users.manage', 'roles.manage', 'audit.view', 'settings.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = [
            'super-admin' => $permissions,

            'admin' => array_diff($permissions, ['fees.void_payment', 'roles.manage']),

            'bursar' => [
                'students.view',
                'fees.view', 'fees.structure_manage', 'fees.invoice_generate',
                'fees.record_payment', 'fees.void_payment', 'fees.discount_grant',
                'audit.view',
            ],

            'teacher' => [
                'students.view',
                'assessments.manage', 'scores.enter',
                'attendance.view', 'attendance.record',
                'reports.generate',
            ],

            'registrar' => [
                'students.view', 'students.create', 'students.update',
                'students.export', 'classes.manage', 'attendance.view',
            ],

            'parent' => ['students.view', 'fees.view', 'attendance.view'],

            'student' => ['attendance.view'],
        ];

        foreach ($roles as $role => $granted) {
            Role::findOrCreate($role)->syncPermissions($granted);
        }
    }
}
