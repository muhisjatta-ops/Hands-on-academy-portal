import { useAuth } from '../auth/AuthProvider';
import { useRequest } from '../hooks/useRequest';
import { school } from '../lib/school';
import { Empty, Loading, money, PageHead, Problem, Shell, Stat, StatRow } from '../components/Shell';

export default function DashboardPage() {
  const { user } = useAuth();
  const { data, loading, error, reload } = useRequest(() => school.dashboard(), []);

  return (
    <Shell>
      <PageHead
        title={`Good day, ${user?.name.split(' ')[0] ?? 'there'}`}
        subtitle={data?.context
          ? `${data.context.academic_year} · ${data.context.term}`
          : undefined}
      />

      {loading && <Loading what="Gathering figures" />}
      {error && <Problem message={error.message} onRetry={reload} />}

      {data && !data.ready && (
        <Empty message={data.message ?? 'The portal is not set up yet.'} />
      )}

      {data?.ready && (
        <>
          <StatRow>
            <Stat label="Students on roll" value={data.counts!.students}
              note={`${data.counts!.classes} classes`} />
            <Stat label="Billed this term" value={money(data.fees!.billed)} />
            <Stat label="Collected" value={money(data.fees!.collected)}
              note={`${data.fees!.rate}% of billing`} />
            <Stat label="Outstanding" value={money(data.fees!.outstanding)}
              note={`${data.top_debtors!.length} largest shown below`} />
          </StatRow>

          <div className="mt-5 grid gap-5 lg:grid-cols-2">
            <section className="border border-slate-200 bg-white p-5">
              <h2 className="mb-3 text-sm font-semibold text-slate-900">
                Largest outstanding balances
              </h2>
              {data.top_debtors!.length === 0 ? (
                <p className="text-sm text-slate-500">Nothing outstanding this term.</p>
              ) : (
                <table className="w-full text-sm">
                  <tbody>
                    {data.top_debtors!.map((d) => (
                      <tr key={d.student_id} className="border-b border-slate-100 last:border-0">
                        <td className="py-2">{d.name}</td>
                        <td className="py-2 text-right tabular-nums">{money(d.balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>

            <section className="border border-slate-200 bg-white p-5">
              <h2 className="mb-3 text-sm font-semibold text-slate-900">Recent payments</h2>
              {data.recent_payments!.length === 0 ? (
                <p className="text-sm text-slate-500">No payments recorded yet.</p>
              ) : (
                <table className="w-full text-sm">
                  <tbody>
                    {data.recent_payments!.map((p) => (
                      <tr key={p.receipt} className="border-b border-slate-100 last:border-0">
                        <td className="py-2 font-serif">{p.receipt}</td>
                        <td className="py-2">{p.student}</td>
                        <td className="py-2 text-right tabular-nums">{money(p.amount)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          </div>

          {data.attendance!.rate !== null && (
            <p className="mt-5 text-sm text-slate-600">
              Attendance this term is {data.attendance!.rate}% across{' '}
              {data.attendance!.marked.toLocaleString()} marked records.
            </p>
          )}
        </>
      )}
    </Shell>
  );
}
