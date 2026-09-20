/**
 * Every API call the school modules make, in one typed module.
 *
 * Screens never call `api.get('/students')` directly — they call
 * `school.students.list()`. When an endpoint's path or payload changes, it
 * changes here once, not in five components.
 */
import { api } from './api';
import type {
  AdmissionPayload, DashboardData, FeeLedger, FeeStructureData, GradeSheet,
  Lookups, Paginated, PaymentMethod, Receipt, Register, ReportCard,
  StudentDetail, StudentRow,
} from './domain';

const qs = (params: Record<string, string | number | undefined | null>) => {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      search.set(key, String(value));
    }
  });
  const text = search.toString();
  return text ? `?${text}` : '';
};

export const school = {
  lookups: () => api.get<Lookups>('/lookups'),
  dashboard: () => api.get<DashboardData>('/dashboard'),

  students: {
    list: (params: {
      q?: string; class_room_id?: number | ''; status?: string; page?: number;
    }) => api.get<Paginated<StudentRow>>('/students' + qs(params)),

    get: (id: number) => api.get<StudentDetail>(`/students/${id}`),

    admit: (payload: AdmissionPayload) =>
      api.post<{ message: string; id: number }>('/students', payload),

    update: (id: number, payload: Partial<StudentDetail>) =>
      api.patch<{ message: string }>(`/students/${id}`, payload),

    transfer: (id: number, classRoomId: number) =>
      api.post<{ message: string }>(`/students/${id}/transfer`, { class_room_id: classRoomId }),

    statement: (id: number) => api.get<unknown>(`/students/${id}/statement`),

    reportCard: (id: number, termId?: number) =>
      api.get<ReportCard>(`/students/${id}/report-card` + qs({ term_id: termId })),
  },

  fees: {
    ledger: (params: { term_id?: number; class_room_id?: number | '' }) =>
      api.get<FeeLedger>('/fees/ledger' + qs(params)),

    structure: (termId?: number) =>
      api.get<FeeStructureData>('/fees/structure' + qs({ term_id: termId })),

    saveStructure: (payload: {
      fee_category_id: number; grade_level_id: number;
      term_id: number; amount: number;
    }) => api.post<{ message: string }>('/fees/structure', payload),

    removeStructure: (id: number) =>
      api.delete<{ message: string }>(`/fees/structure/${id}`),

    addCategory: (name: string) =>
      api.post<{ message: string; id: number }>('/fees/categories', { name }),

    generateInvoices: (payload: { term_id: number; class_room_id?: number | null }) =>
      api.post<{ message: string; report: unknown }>('/fees/invoices/generate', payload),
  },

  payments: {
    record: (payload: {
      student_id: number; amount: number; method: PaymentMethod;
      paid_on: string; reference?: string; note?: string;
    }) => api.post<{ message: string; receipt: Receipt }>('/payments', payload),

    reverse: (id: number, reason: string) =>
      api.post<{ message: string }>(`/payments/${id}/reverse`, { reason }),

    list: (params: { student_id?: number; page?: number }) =>
      api.get<Paginated<{
        id: number; receipt: string; student: string | null; student_id: number;
        amount: number; method: string; reference: string | null;
        paid_on: string; received_by: string | null; reversal: boolean;
      }>>('/payments' + qs(params)),
  },

  grades: {
    sheet: (params: { class_room_id: number; subject_id: number; term_id?: number }) =>
      api.get<GradeSheet>('/grades/sheet' + qs(params)),

    saveAssessments: (payload: {
      class_room_id: number; subject_id: number; term_id: number;
      components: { name: string; weight: number; max_score: number }[];
    }) => api.post<{ message: string }>('/grades/assessments', payload),

    saveScores: (payload: {
      assessment_id: number;
      scores: { student_id: number; score: number | null }[];
    }) => api.post<{ message: string }>('/grades/scores', payload),
  },

  attendance: {
    register: (params: { class_room_id: number; date?: string }) =>
      api.get<Register>('/attendance/register' + qs(params)),

    save: (payload: {
      class_room_id: number; date: string;
      rows: { student_id: number; state: string; reason?: string }[];
    }) => api.post<{ message: string }>('/attendance', payload),

    absentees: (termId?: number) =>
      api.get<{ term_id: number; rows: {
        student_id: number; admission_number: string; name: string;
        days_marked: number; days_absent: number; rate: number;
      }[] }>('/attendance/absentees' + qs({ term_id: termId })),
  },

  setup: {
    setCurrentTerm: (termId: number) =>
      api.post<{ message: string }>('/setup/current-term', { term_id: termId }),

    addClassRoom: (payload: {
      grade_level_id: number; stream?: string; capacity?: number;
    }) => api.post<{ message: string; id: number }>('/setup/class-rooms', payload),

    addSubject: (payload: {
      name: string; code: string; grade_level_ids: number[];
    }) => api.post<{ message: string; id: number }>('/setup/subjects', payload),
  },
};
