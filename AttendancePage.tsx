import { useEffect, useState } from 'react';
import { useAuth } from '../auth/AuthProvider';
import { useAction, useRequest } from '../hooks/useRequest';
import { school } from '../lib/school';
import type { AttendanceState } from '../lib/domain';
import {
  Empty, Loading, PageHead, Problem, Shell, Stat, StatRow,
} from '../components/Shell';

const STATES: { code: AttendanceState; label: string; tint: string }[] = [
  { code: 'P', label: 'Present', tint: 'bg-[#16283c] text-white border-[#16283c]' },
  { code: 'A', label: 'Absent',  tint: 'bg-[#9e3b32] text-white border-[#9e3b32]' },
  { code: 'L', label: 'Late',    tint: 'bg-[#8a6318] text-white border-[#8a6318]' },
  { code: 'E', label: 'Excused', tint: 'bg-[#2f6f5e] text-white border-[#2f6f5e]' },
];

export default function AttendancePage() {
  const { can } = useAuth();
  const lookups = useRequest(() => school.lookups(), []);

  const [classId, setClassId] = useState<number | ''>('');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [marks, setMarks] = useState<Record<number, AttendanceState>>({});
  const save = useAction();

  useEffect(() => {
    if (lookups.data && classId === '') {
      setClassId(lookups.data.class_rooms[0]?.id ?? '');
    }
  }, [lookups.data]);

  const register = useRequest(
    () => classId !== ''
      ? school.attendance.register({ class_room_id: classId as number, date })
      : Promise.resolve(null),
    [classId, date],
  );

  // Seed local state from whatever is already saved for this day.
  useEffect(() => {
    if (!register.data) return;
    const seeded: Record<number, AttendanceState> = {};
    register.data.rows.forEach((r) => { if (r.state) seeded[r.student_id] = r.state; });
    setMarks(seeded);
  }, [register.data]);

  const counts = STATES.reduce((acc, s) => {
    acc[s.code] = Object.values(marks).filter((m) => m === s.code).length;
    return acc;
  }, {} as Record<AttendanceState, number>);

  const marked = counts.P + counts.A + counts.L + counts.E;
  const rate = counts.P + counts.A + counts.L > 0
    ? Math.round(((counts.P + counts.L) / (counts.P + counts.A + counts.L)) * 100)
    : null;

  const markAll = () => {
    if (!register.data) return;
    const all: Record<number, AttendanceState> = {};
    register.data.rows.forEach((r) => { all[r.student_id] = 'P'; });
    setMarks(all);
  };

  const submit = async () => {
    if (!register.data) return;

    const rows = Object.entries(marks).map(([studentId, state]) => ({
      student_id: Number(studentId), state,
    }));

    if (rows.length === 0) return;

    const result = await save.run(() => school.attendance.save({
      class_room_id: classId as number,
      date,
      rows,
    }));

    if (result) register.reload();
  };

  const select = 'border border-slate-300 bg-white px-3 py-2 text-sm';

  return (
    <Shell>
      <PageHead
        title="Attendance"
        subtitle={register.data
          ? `${register.data.class_room.name} · ${register.data.date}`
          : undefined}
      >
        {can('attendance.record') && register.data && register.data.rows.length > 0 && (
          <>
            <button onClick={markAll} className="border border-slate-300 bg-white px-4 py-2 text-sm">
              Mark all present
            </button>
            <button onClick={submit} disabled={save.pending || marked === 0}
              className="bg-[#2f6f5e] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
              {save.pending ? 'Saving…' : 'Save register'}
            </button>
          </>
        )}
      </PageHead>

      {save.error && <div className="mb-4"><Problem message={save.error.message} /></div>}

      <div className="mb-4 flex flex-wrap gap-2">
        <select className={select} value={classId}
          onChange={(e) => setClassId(Number(e.target.value))}>
          {lookups.data?.class_rooms.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
        <input type="date" className={select} value={date} max={new Date().toISOString().slice(0, 10)}
          onChange={(e) => setDate(e.target.value)} />
      </div>

      {register.data && register.data.rows.length > 0 && (
        <StatRow>
          <Stat label="Present" value={counts.P} note={`of ${register.data.rows.length} on roll`} />
          <Stat label="Absent" value={counts.A} note={`${counts.E} excused`} />
          <Stat label="Late" value={counts.L} />
          <Stat label="Attendance" value={rate === null ? '—' : `${rate}%`} note={`${marked} marked`} />
        </StatRow>
      )}

      {register.loading && <Loading what="Opening the register" />}
      {register.error && (
        <Problem message={register.error.message}
          onRetry={register.error.status === 403 ? undefined : register.reload} />
      )}

      {register.data?.rows.length === 0 && <Empty message="No students in this class." />}

      {register.data && register.data.rows.length > 0 && (
        <div className="mt-4 overflow-x-auto border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs text-slate-500">
                <th className="px-3 py-2 text-left font-semibold">Student</th>
                <th className="px-3 py-2 text-left font-semibold">Mark</th>
                <th className="px-3 py-2 text-right font-semibold">Term rate</th>
              </tr>
            </thead>
            <tbody>
              {register.data.rows.map((row) => (
                <tr key={row.student_id} className="border-b border-slate-100 last:border-0">
                  <td className="px-3 py-2">
                    {row.name}
                    <span className="block text-xs text-slate-500">{row.admission_number}</span>
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex gap-1">
                      {STATES.map((s) => {
                        const on = marks[row.student_id] === s.code;
                        return (
                          <button
                            key={s.code}
                            title={s.label}
                            disabled={!can('attendance.record')}
                            onClick={() => setMarks((m) => {
                              const next = { ...m };
                              if (next[row.student_id] === s.code) delete next[row.student_id];
                              else next[row.student_id] = s.code;
                              return next;
                            })}
                            className={`h-7 w-8 border text-xs ${
                              on ? s.tint : 'border-slate-300 bg-white text-slate-700'
                            }`}
                          >
                            {s.code}
                          </button>
                        );
                      })}
                    </div>
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {row.term_rate === null ? '—' : `${row.term_rate}%`}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <p className="mt-3 text-xs text-slate-500">
        Excused absences are left out of the attendance rate entirely — they
        are neither attendance nor a mark against the student.
      </p>
    </Shell>
  );
}
