import { useEffect, useState } from 'react';
import { useAuth } from '../auth/AuthProvider';
import { useAction, useRequest } from '../hooks/useRequest';
import { school } from '../lib/school';
import { Empty, Loading, PageHead, Problem, Shell } from '../components/Shell';

/**
 * Bulk mark entry.
 *
 * Marks are held in local state and sent in one request per assessment
 * column when the teacher presses Save. Saving each keystroke would mean
 * forty requests per class and no way to tell what landed; one transaction
 * either takes the sheet or none of it.
 */
export default function GradesPage() {
  const { can } = useAuth();
  const lookups = useRequest(() => school.lookups(), []);

  const [classId, setClassId] = useState<number | ''>('');
  const [subjectId, setSubjectId] = useState<number | ''>('');
  const [termId, setTermId] = useState<number | ''>('');

  // Pending edits: assessmentId -> studentId -> mark
  const [edits, setEdits] = useState<Record<number, Record<number, string>>>({});
  const save = useAction();

  useEffect(() => {
    if (!lookups.data) return;
    if (classId === '') setClassId(lookups.data.class_rooms[0]?.id ?? '');
    if (subjectId === '') setSubjectId(lookups.data.subjects[0]?.id ?? '');
    if (termId === '') setTermId(lookups.data.terms.find((t) => t.is_current)?.id ?? '');
  }, [lookups.data]);

  const ready = classId !== '' && subjectId !== '';

  const sheet = useRequest(
    () => ready
      ? school.grades.sheet({
          class_room_id: classId as number,
          subject_id: subjectId as number,
          term_id: termId === '' ? undefined : (termId as number),
        })
      : Promise.resolve(null),
    [classId, subjectId, termId],
  );

  const setMark = (assessmentId: number, studentId: number, value: string) =>
    setEdits((prev) => ({
      ...prev,
      [assessmentId]: { ...(prev[assessmentId] ?? {}), [studentId]: value },
    }));

  const dirty = Object.keys(edits).length > 0;

  const saveAll = async () => {
    for (const [assessmentId, rows] of Object.entries(edits)) {
      const scores = Object.entries(rows).map(([studentId, value]) => ({
        student_id: Number(studentId),
        score: value === '' ? null : Number(value),
      }));

      const result = await save.run(() => school.grades.saveScores({
        assessment_id: Number(assessmentId),
        scores,
      }));

      if (!result) return;   // stop on the first failure, keep the edits
    }

    setEdits({});
    sheet.reload();
  };

  const select = 'border border-slate-300 bg-white px-3 py-2 text-sm';

  return (
    <Shell>
      <PageHead
        title="Grades"
        subtitle={sheet.data
          ? `${sheet.data.class_room.name} · ${sheet.data.subject.name} · ${sheet.data.term.label}`
          : undefined}
      >
        {dirty && can('scores.enter') && (
          <button onClick={saveAll} disabled={save.pending}
            className="bg-[#2f6f5e] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
            {save.pending ? 'Saving…' : 'Save marks'}
          </button>
        )}
      </PageHead>

      {save.error && <div className="mb-4"><Problem message={save.error.message} /></div>}

      <div className="mb-4 flex flex-wrap gap-2">
        <select className={select} value={classId}
          onChange={(e) => { setClassId(Number(e.target.value)); setEdits({}); }}>
          {lookups.data?.class_rooms.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
        <select className={select} value={subjectId}
          onChange={(e) => { setSubjectId(Number(e.target.value)); setEdits({}); }}>
          {lookups.data?.subjects.map((s) => (
            <option key={s.id} value={s.id}>{s.name}</option>
          ))}
        </select>
        <select className={select} value={termId}
          onChange={(e) => { setTermId(Number(e.target.value)); setEdits({}); }}>
          {lookups.data?.terms.map((t) => (
            <option key={t.id} value={t.id}>{t.label}</option>
          ))}
        </select>
      </div>

      {sheet.loading && <Loading what="Loading the mark sheet" />}

      {/* A 403 here is the row-level policy working, not a bug. */}
      {sheet.error && (
        <Problem
          message={sheet.error.status === 403
            ? sheet.error.message
            : sheet.error.message}
          onRetry={sheet.error.status === 403 ? undefined : sheet.reload}
        />
      )}

      {sheet.data && sheet.data.assessments.length === 0 && (
        <Empty message="No assessment components have been set up for this subject and term yet." />
      )}

      {sheet.data && sheet.data.assessments.length > 0 && (
        <>
          <div className="overflow-x-auto border border-slate-200 bg-white">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-xs text-slate-500">
                  <th className="px-3 py-2 text-left font-semibold">Student</th>
                  {sheet.data.assessments.map((a) => (
                    <th key={a.id} className="px-3 py-2 text-right font-semibold">
                      {a.name}
                      <span className="block font-normal">
                        /{a.max_score} · {a.weight}%
                      </span>
                    </th>
                  ))}
                  <th className="px-3 py-2 text-right font-semibold">Total</th>
                  <th className="px-3 py-2 text-left font-semibold">Grade</th>
                  <th className="px-3 py-2 text-right font-semibold">Position</th>
                </tr>
              </thead>
              <tbody>
                {sheet.data.rows.map((row) => (
                  <tr key={row.student_id} className="border-b border-slate-100 last:border-0">
                    <td className="px-3 py-2">
                      {row.name}
                      <span className="block text-xs text-slate-500">{row.admission_number}</span>
                    </td>

                    {row.components.map((component) => {
                      const edited = edits[component.assessment_id]?.[row.student_id];
                      const value = edited !== undefined
                        ? edited
                        : (component.score === null ? '' : String(component.score));

                      return (
                        <td key={component.assessment_id} className="px-3 py-2 text-right">
                          {can('scores.enter') ? (
                            <input
                              type="number" min="0" max={component.max_score}
                              value={value}
                              onChange={(e) => setMark(
                                component.assessment_id, row.student_id, e.target.value,
                              )}
                              className={`w-16 border px-2 py-1 text-right text-sm ${
                                edited !== undefined
                                  ? 'border-[#2f6f5e] bg-[#e8f1ee]'
                                  : 'border-slate-300'
                              }`}
                            />
                          ) : (
                            <span>{component.score ?? '—'}</span>
                          )}
                        </td>
                      );
                    })}

                    <td className="px-3 py-2 text-right tabular-nums">
                      {row.total === null ? '—' : row.total.toFixed(1)}
                      {row.total !== null && !row.complete && (
                        <span className="ml-1 text-xs text-slate-400" title="Not all components marked">
                          partial
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      {row.grade
                        ? <span className={row.total! >= 50 ? 'text-[#2f6f5e]' : 'text-[#9e3b32]'}>
                            {row.grade}
                          </span>
                        : '—'}
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums">{row.position ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <p className="mt-3 text-xs text-slate-500">
            Totals shown as "partial" are scaled from the components marked so
            far, so a student with only continuous assessment entered is not
            dragged to zero by exams that have not happened.
          </p>
        </>
      )}
    </Shell>
  );
}
