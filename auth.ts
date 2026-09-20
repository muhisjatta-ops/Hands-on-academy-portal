// Keep this list in sync with RolePermissionSeeder.php.
// A union type instead of `string` means a typo in a permission check is a
// compile error rather than a silently hidden button.
export type Permission =
  | 'students.view' | 'students.create' | 'students.update'
  | 'students.delete' | 'students.export'
  | 'classes.manage' | 'subjects.manage'
  | 'assessments.manage' | 'scores.enter' | 'scores.publish'
  | 'reports.generate'
  | 'fees.view' | 'fees.structure_manage' | 'fees.invoice_generate'
  | 'fees.record_payment' | 'fees.void_payment' | 'fees.discount_grant'
  | 'attendance.view' | 'attendance.record'
  | 'users.manage' | 'roles.manage' | 'audit.view' | 'settings.manage';

export type Role =
  | 'super-admin' | 'admin' | 'bursar'
  | 'teacher' | 'registrar' | 'parent' | 'student';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  username: string | null;
  roles: Role[];
  permissions: Permission[];
  two_factor_enabled: boolean;
  two_factor_required: boolean;
  must_change_password: boolean;
  last_login_at: string | null;
}

export interface LoginCredentials {
  login: string;
  password: string;
  remember?: boolean;
}

export interface LoginResult {
  two_factor: boolean;
}

export interface UserSession {
  id: string;
  ip_address: string | null;
  device: string;
  platform: string | false;
  browser: string | false;
  last_active: string;
  is_current: boolean;
}

export interface TwoFactorSetup {
  secret: string;
  otpauth_uri: string;
  recovery_codes: string[];
}
