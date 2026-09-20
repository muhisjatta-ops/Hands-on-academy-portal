import { useState } from 'react';
import { useAuth } from '../auth/AuthProvider';
import { useAction, useRequest } from '../hooks/useRequest';
import { school } from '../lib/school';
import type { AdmissionPayload, Lookups } from '../lib/domain';
import {
  Empty, Loading, money, PageHead, Problem, Shell,
} from '../components/Shell';

export default function StudentsPage() {
  const { can } = useAuth();
  const [query, setQuery] = useState('');
  const [classId, setClassId] = useState<number | ''>('');
  const [page, setPage] = useState(1);
  const [admitting, setAdmitting] = useState(false);

  const lookups = useRequest(() => school.lookups(), []);

  // Debouncing is deliberately left out: the sequence guard in useRequest
  // already discards out-of-order responses, and at school scale the
  // server handles a request per keystroke without noticing.
  const students = useRequest(
    () => school.students.list({ q: query, class_room_id: classId, page }),
    [query, classId, page],
  );

  return (
    <Shell>
      <PageHead
        title="Students"
        subtitle={students.data ? `${students.data.meta.total} on roll` : undefined}
      >
        {can('students.create') && (
          <button onClick={() => setAdmitting(true)}
            className="bg-[#16283c] px-4 py-2 text-sm font-medium text-white hover:bg-[#1f3853]">
            Admit a student
          </button>
        )}
      </PageHead>

      <div className="mb-4 flex flex-wrap gap-2">
        <input
          value={query}
          onChange={(e) => { setQuery(e.target.value); setPage(1); }}
          placeholder="Search name or admission number"
          className="w-64 border border-slate-300 bg-white px-3 py-2 text-sm"
        />
        <select
          value={classId}
          onChange={(e) => {
            setClassId(e.target.value ? Number(e.target.value) : '');
            setPage(1);
          }}
          className="border border-slate-300 bg-white px-3 py-2 text-sm"
        >
          <option value="">All classes</option>
          {lookups.data?.class_rooms.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </div>

      {students.loading && <Loading what="Fetching students" />}
      {students.error && <Problem message={students.error.message} onRetry={students.reload} />}

      {students.data && students.data.data.length === 0 && (
        <Empty message={query ? 'No students match that search.' : 'No students admitted yet.'} />
      )}

      {students.data && students.data.data.length > 0 && (
        <>
          <div className="overflow-x-auto border border-slate-200 bg-white">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs text-slate-500">
                  <th className="px-3 py-2 text-left font-semibold">Adm. no.</th>
                  <th className="px-3 py-2 text-left font-semibold">Name</th>
                  <th className="px-3 py-2 text-left font-semibold">Class</th>
                  <th className="px-3 py-2 text-left font-semibold">Status</th>
                  <th className="px-3 py-2 text-right font-semibold">Balance</th>
                </tr>
              </thead>
              <tbody>
                {students.data.data.map((s) => (
                  <tr key={s.id} className="border-b border-slate-100 last:border-0">
                    <td className="px-3 py-2.5 font-serif">{s.admission_number}</td>
                    <td className="px-3 py-2.5">{s.full_name}</td>
                    <td className="px-3 py-2.5">{s.class_room?.name ?? '—'}</td>
                    <td className="px-3 py-2.5">
                      <span className="border border-slate-300 px-1.5 py-0.5 text-xs">
                        {s.status}
                      </span>
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums">
                      {s.balance > 0
                        ? <span className="text-[#9e3b32]">{money(s.balance)}</span>
                        : <span className="text-[#2f6f5e]">settled</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {students.data.meta.last_page > 1 && (
            <div className="mt-4 flex items-center gap-3 text-sm">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="border border-slate-300 px-3 py-1.5 disabled:opacity-40"
              >
                Previous
              </button>
              <span className="text-slate-600">
                Page {students.data.meta.current_page} of {students.data.meta.last_page}
              </span>
              <button
                disabled={page >= students.data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="border border-slate-300 px-3 py-1.5 disabled:opacity-40"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}

      {admitting && lookups.data && (
        <AdmissionForm
          lookups={lookups.data}
          onClose={() => setAdmitting(false)}
          onDone={() => { setAdmitting(false); students.reload(); }}
        />
      )}
    </Shell>
  );
}

/**
 * Admission creates the student, their guardians and their enrollment in
 * one request, because a student without a class is not a usable record.
 * The admission number is assigned by the server, never typed here.
 */
function AdmissionForm({
  lookups, onClose, onDone,
}: { lookups: Lookups; onClose: () => void; onDone: () => void }) {
  const { run, pending, error } = useAction();
  const [form, setForm] = useState<AdmissionPayload>({
    first_name: '', last_name: '', date_of_birth: '', gender: 'M',
    class_room_id: lookups.class_rooms[0]?.id ?? 0,
    guardians: [{ full_name: '', relationship: 'Father', phone: '', is_primary: true, is_fee_payer: true }],
  });

  const set = <K extends keyof AdmissionPayload>(key: K, value: AdmissionPayload[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const setGuardian = (key: string, value: string) =>
    setForm((f) => ({
      ...f,
      guardians: [{ ...f.guardians[0], [key]: value }],
    }));

  const submit = async () => {
    const result = await run(() => school.students.admit(form));
    if (result) onDone();
  };

  const field = 'w-full border border-slate-300 bg-white px-3 py-2 text-sm';
  const label = 'mb-1 block text-xs font-medium text-slate-700';

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/55 p-5"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="max-h-[88vh] w-full max-w-lg overflow-y-auto border border-slate-200 bg-white p-6">
        <h3 className="mb-4 font-serif text-xl text-slate-900">Admit a student</h3>

        {error && <div className="mb-4"><Problem message={error.message} /></div>}

        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className={label}>First name</label>
            <input className={field} value={form.first_name}
              onChange={(e) => set('first_name', e.target.value)} />
            {error?.field('first_name') && (
              <p className="mt-1 text-xs text-[#7d2f27]">{error.field('first_name')}</p>
            )}
          </div>
          <div>
            <label className={label}>Last name</label>
            <input className={field} value={form.last_name}
              onChange={(e) => set('last_name', e.target.value)} />
          </div>
          <div>
            <label className={label}>Date of birth</label>
            <input type="date" className={field} value={form.date_of_birth}
              onChange={(e) => set('date_of_birth', e.target.value)} />
            {error?.field('date_of_birth') && (
              <p className="mt-1 text-xs text-[#7d2f27]">{error.field('date_of_birth')}</p>
            )}
          </div>
          <div>
            <label className={label}>Gender</label>
            <select className={field} value={form.gender}
              onChange={(e) => set('gender', e.target.value as 'M' | 'F')}>
              <option value="M">Male</option>
              <option value="F">Female</option>
            </select>
          </div>
          <div className="sm:col-span-2">
            <label className={label}>Class</label>
            <select className={field} value={form.class_room_id}
              onChange={(e) => set('class_room_id', Number(e.target.value))}>
              {lookups.class_rooms.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          </div>
        </div>

        <h4 className="mb-2 mt-5 border-t border-slate-200 pt-4 text-sm font-semibold">
          Guardian
        </h4>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <label className={label}>Full name</label>
            <input className={field} value={form.guardians[0].full_name}
              onChange={(e) => setGuardian('full_name', e.target.value)} />
          </div>
          <div>
            <label className={label}>Relationship</label>
            <input className={field} value={form.guardians[0].relationship}
              onChange={(e) => setGuardian('relationship', e.target.value)} />
          </div>
          <div>
            <label className={label}>Phone</label>
            <input className={field} value={form.guardians[0].phone}
              onChange={(e) => setGuardian('phone', e.target.value)} />
          </div>
        </div>
        <p className="mt-2 text-xs text-slate-500">
          A guardian with this phone number already on file will be linked rather
          than duplicated, so siblings share one parent record.
        </p>

        <div className="mt-5 flex justify-end gap-2">
          <button onClick={onClose} className="border border-slate-300 px-4 py-2 text-sm">
            Cancel
          </button>
          <button onClick={submit} disabled={pending}
            className="bg-[#16283c] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
            {pending ? 'Admitting…' : 'Admit student'}
          </button>
        </div>
      </div>
    </div>
  );
}
