/**
 * Domain types, mirroring what the Laravel controllers return.
 *
 * These are hand-written rather than generated, so when a controller's shape
 * changes you must change it here too. The payoff is that a renamed field
 * becomes a compile error in every screen that reads it, instead of
 * `undefined` appearing in the UI at runtime.
 */

export type StudentStatus = 'ACTIVE' | 'WITHDRAWN' | 'GRADUATED' | 'SUSPENDED';
export type PaymentMethod = 'CASH' | 'BANK' | 'MOBILE' | 'CHEQUE';
export type AttendanceState = 'P' | 'A' | 'L' | 'E';

export interface NamedRef {
  id: number;
  name: string;
}

export interface Lookups {
  academic_years: { id: number; name: string; is_current: boolean }[];
  terms: { id: number; name: string; label: string; is_current: boolean }[];
  grade_levels: { id: number; name: string; sequence: number }[];
  class_rooms: NamedRef[];
  subjects: { id: number; name: string; code: string }[];
  grade_bands: {
    id: number; letter: string;
    min_percent: string; max_percent: string; remark: string | null;
  }[];
}

export interface StudentRow {
  id: number;
  admission_number: string;
  full_name: string;
  first_name: string;
  last_name: string;
  gender: 'M' | 'F';
  date_of_birth: string | null;
  status: StudentStatus;
  class_room: NamedRef | null;
  balance: number;
}

export interface Paginated<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number; per_page?: number };
}

export interface StudentDetail {
  id: number;
  admission_number: string;
  full_name: string;
  first_name: string;
  middle_name: string | null;
  last_name: string;
  date_of_birth: string | null;
  gender: 'M' | 'F';
  address: string | null;
  admission_date: string | null;
  status: StudentStatus;
  notes: string | null;
  balance: number;
  guardians: {
    id: number; full_name: string; relationship: string;
    phone: string; email: string | null;
    is_primary: boolean; is_fee_payer: boolean;
  }[];
  history: {
    academic_year: string; class: string;
    outcome: string | null; is_repeating: boolean;
  }[];
}

export interface AdmissionPayload {
  first_name: string;
  middle_name?: string;
  last_name: string;
  date_of_birth: string;
  gender: 'M' | 'F';
  address?: string;
  class_room_id: number;
  guardians: {
    full_name: string; relationship: string; phone: string;
    email?: string; is_primary?: boolean; is_fee_payer?: boolean;
  }[];
}

export interface DashboardData {
  ready: boolean;
  message?: string;
  context?: { academic_year: string | null; term: string | null };
  counts?: { students: number; classes: number; teachers: number };
  fees?: { billed: number; collected: number; outstanding: number; rate: number };
  attendance?: { marked: number; rate: number | null };
  top_debtors?: { student_id: number; name: string; balance: number }[];
  recent_payments?: { receipt: string; student: string | null; amount: number; paid_on: string }[];
}

export interface FeeLedger {
  term_id: number;
  rows: {
    student_id: number;
    name: string;
    admission_number: string;
    class: string | null;
    invoice_id: number | null;
    invoice_number: string | null;
    billed: number;
    paid: number;
    balance: number;
    total_outstanding: number;
  }[];
  totals: { billed: number; collected: number; outstanding: number; debtors: number };
}

export interface FeeStructureData {
  term_id: number;
  categories: { id: number; name: string; is_optional: boolean }[];
  structures: {
    id: number;
    category: NamedRef;
    grade_level: NamedRef;
    amount: number;
  }[];
}

export interface Receipt {
  id: number;
  number: string;
  student: string;
  admission_number: string;
  amount: number;
  method: PaymentMethod;
  paid_on: string;
  allocations: { invoice: string; amount: number }[];
  unallocated: number;
  balance_now: number;
}

export interface GradeSheet {
  class_room: NamedRef;
  subject: NamedRef;
  term: { id: number; label: string };
  assessments: { id: number; name: string; max_score: number; weight: number }[];
  weight_total: number;
  rows: {
    student_id: number;
    name: string;
    admission_number: string;
    components: {
      assessment_id: number; name: string;
      max_score: number; weight: number; score: number | null;
    }[];
    total: number | null;
    complete: boolean;
    grade: string | null;
    remark: string | null;
    position: number | null;
  }[];
}

export interface Register {
  class_room: NamedRef;
  date: string;
  term: { id: number; label: string } | null;
  states: Record<AttendanceState, string>;
  rows: {
    student_id: number;
    name: string;
    admission_number: string;
    state: AttendanceState | null;
    reason: string | null;
    term_rate: number | null;
  }[];
}

export interface ReportCard {
  student: { id: number; name: string; admission_number: string; class: string };
  term: string;
  subjects: {
    subject: string; total: number; grade: string | null;
    remark: string | null; position: number | null; class_size: number;
  }[];
  summary: {
    subjects_taken: number; average: number | null;
    grade: string | null; remark: string | null; attendance: number | null;
  };
}
