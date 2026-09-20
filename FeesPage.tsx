import { useState } from 'react';
import { useAuth } from '../auth/AuthProvider';
import { useAction, useRequest } from '../hooks/useRequest';
import { school } from '../lib/school';
import type { PaymentMethod, Receipt } from '../lib/domain';
import {
  Empty, Loading, money, PageHead, Problem, Shell, Stat, StatRow,
} from '../components/Shell';

export default function FeesPage() {
  const { can } = useAuth();
  const [classId, setClassId] = useState<number | ''>('');
  const [payingFor, setPayingFor] = useState<{ id: number; name: string } | null>(null);
  const [receipt, setReceipt] = useState<Receipt | null>(null);

  const lookups = useRequest(() => school.lookups(), []);
  const ledger = useRequest(() => school.fees.ledger({ class_room_id: classId }), [classId]);
  const generate = useAction();

  const currentTerm = lookups.data?.terms.find((t) => t.is_current);

  const runGenerate = async () => {
    if (!currentTerm) return;
    const result = await generate.run(() => school.fees.generateInvoices({
      term_id: currentTerm.id,
      class_room_id: classId === '' ? null : classId,
    }));
    if (result) ledger.reload();
  };

  return (
    <Shell>
      <PageHead title="Fees" subtitle={currentTerm?.label}>
        {can('fees.invoice_generate') && (
          <button onClick={runGenerate} disabled={generate.pending}
            className="border border-slate-300 bg-white px-4 py-2 text-sm disabled:opacity-50">
            {generate.pending ? 'Generating…' : 'Generate invoices'}
          </button>
        )}
      </PageHead>

      {generate.error && <div className="mb-4"><Problem message={generate.error.message} /></div>}

      {ledger.data && (
        <StatRow>
          <Stat label="Billed" value={money(ledger.data.totals.billed)} />
          <Stat label="Collected" value={money(ledger.data.totals.collected)} />
          <Stat label="Outstanding" value={money(ledger.data.totals.outstanding)} />
          <Stat label="Students owing" value={ledger.data.totals.debtors} />
        </StatRow>
      )}

      <div className="my-4 flex flex-wrap gap-2">
        <select value={classId}
          onChange={(e) => setClassId(e.target.value ? Number(e.target.value) : '')}
          className="border border-slate-300 bg-white px-3 py-2 text-sm">
          <option value="">All classes</option>
          {lookups.data?.class_rooms.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </select>
      </div>

      {ledger.loading && <Loading what="Reading the ledger" />}
      {ledger.error && <Problem message={ledger.error.message} onRetry={ledger.reload} />}

      {ledger.data?.rows.length === 0 && <Empty message="No students on roll." />}

      {ledger.data && ledger.data.rows.length > 0 && (
        <div className="overflow-x-auto border border-slate-200 bg-white">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs text-slate-500">
                <th className="px-3 py-2 text-left font-semibold">Student</th>
                <th className="px-3 py-2 text-left font-semibold">Class</th>
                <th className="px-3 py-2 text-left font-semibold">Invoice</th>
                <th className="px-3 py-2 text-right font-semibold">Billed</th>
                <th className="px-3 py-2 text-right font-semibold">Paid</th>
                <th className="px-3 py-2 text-right font-semibold">Balance</th>
                {can('fees.record_payment') && <th />}
              </tr>
            </thead>
            <tbody>
              {ledger.data.rows.map((r) => (
                <tr key={r.student_id} className="border-b border-slate-100 last:border-0">
                  <td className="px-3 py-2.5">
                    {r.name}
                    <span className="block text-xs text-slate-500">{r.admission_number}</span>
                  </td>
                  <td className="px-3 py-2.5">{r.class ?? '—'}</td>
                  <td className="px-3 py-2.5 font-serif text-xs">
                    {r.invoice_number ?? <span className="text-slate-400">not billed</span>}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">{money(r.billed)}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">{money(r.paid)}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {r.balance > 0
                      ? <span className="text-[#9e3b32]">{money(r.balance)}</span>
                      : r.balance < 0
                        ? <span className="text-[#8a6318]">{money(-r.balance)} credit</span>
                        : <span className="text-[#2f6f5e]">settled</span>}
                  </td>
                  {can('fees.record_payment') && (
                    <td className="px-3 py-2.5 text-right">
                      <button
                        onClick={() => setPayingFor({ id: r.student_id, name: r.name })}
                        className="border border-slate-300 px-2.5 py-1 text-xs"
                      >
                        Pay
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {payingFor && (
        <PaymentForm
          student={payingFor}
          onClose={() => setPayingFor(null)}
          onDone={(r) => { setPayingFor(null); setReceipt(r); ledger.reload(); }}
        />
      )}

      {receipt && <ReceiptView receipt={receipt} onClose={() => setReceipt(null)} />}
    </Shell>
  );
}

function PaymentForm({
  student, onClose, onDone,
}: {
  student: { id: number; name: string };
  onClose: () => void;
  onDone: (receipt: Receipt) => void;
}) {
  const { run, pending, error } = useAction();
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState<PaymentMethod>('CASH');
  const [paidOn, setPaidOn] = useState(new Date().toISOString().slice(0, 10));
  const [reference, setReference] = useState('');

  const submit = async () => {
    const result = await run(() => school.payments.record({
      student_id: student.id,
      amount: Number(amount),
      method,
      paid_on: paidOn,
      reference: reference || undefined,
    }));

    if (result) onDone(result.receipt);
  };

  const field = 'w-full border border-slate-300 bg-white px-3 py-2 text-sm';
  const label = 'mb-1 block text-xs font-medium text-slate-700';

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/55 p-5"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-md border border-slate-200 bg-white p-6">
        <h3 className="mb-1 font-serif text-xl text-slate-900">Record a payment</h3>
        <p className="mb-4 text-sm text-slate-600">{student.name}</p>

        {error && <div className="mb-4"><Problem message={error.message} /></div>}

        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className={label}>Amount</label>
            <input type="number" min="0" className={field} value={amount} autoFocus
              onChange={(e) => setAmount(e.target.value)} />
            {error?.field('amount') && (
              <p className="mt-1 text-xs text-[#7d2f27]">{error.field('amount')}</p>
            )}
          </div>
          <div>
            <label className={label}>Date</label>
            <input type="date" className={field} value={paidOn}
              onChange={(e) => setPaidOn(e.target.value)} />
          </div>
          <div>
            <label className={label}>Method</label>
            <select className={field} value={method}
              onChange={(e) => setMethod(e.target.value as PaymentMethod)}>
              <option value="CASH">Cash</option>
              <option value="BANK">Bank transfer</option>
              <option value="MOBILE">Mobile money</option>
              <option value="CHEQUE">Cheque</option>
            </select>
          </div>
          <div>
            <label className={label}>Reference</label>
            <input className={field} value={reference} placeholder="Optional"
              onChange={(e) => setReference(e.target.value)} />
          </div>
        </div>

        <p className="mt-3 text-xs text-slate-500">
          The payment is applied to the oldest unpaid invoice first, so arrears
          clear before the current term.
        </p>

        <div className="mt-5 flex justify-end gap-2">
          <button onClick={onClose} className="border border-slate-300 px-4 py-2 text-sm">
            Cancel
          </button>
          <button onClick={submit} disabled={pending || !amount}
            className="bg-[#2f6f5e] px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
            {pending ? 'Recording…' : 'Record and issue receipt'}
          </button>
        </div>
      </div>
    </div>
  );
}

function ReceiptView({ receipt, onClose }: { receipt: Receipt; onClose: () => void }) {
  const line = 'flex justify-between border-b border-slate-100 py-1.5 last:border-0';

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/55 p-5"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="w-full max-w-sm border border-slate-200 bg-white p-6">
        <h3 className="mb-4 font-serif text-xl text-slate-900">
          Receipt {receipt.number}
        </h3>

        <div className="text-sm">
          <div className={line}><span className="text-slate-500">Student</span><span>{receipt.student}</span></div>
          <div className={line}><span className="text-slate-500">Adm. no.</span><span className="font-serif">{receipt.admission_number}</span></div>
          <div className={line}><span className="text-slate-500">Date</span><span>{receipt.paid_on}</span></div>
          <div className={line}><span className="text-slate-500">Method</span><span>{receipt.method}</span></div>
          <div className={line}><span className="text-slate-500">Amount</span><span className="font-medium">{money(receipt.amount)}</span></div>
        </div>

        {receipt.allocations.length > 0 && (
          <>
            <p className="mt-4 text-xs font-medium text-slate-700">Applied to</p>
            <div className="text-sm">
              {receipt.allocations.map((a) => (
                <div key={a.invoice} className={line}>
                  <span className="font-serif text-xs">{a.invoice}</span>
                  <span className="tabular-nums">{money(a.amount)}</span>
                </div>
              ))}
            </div>
          </>
        )}

        {receipt.unallocated > 0 && (
          <p className="mt-3 text-sm text-[#8a6318]">
            {money(receipt.unallocated)} remains as a credit on the account.
          </p>
        )}

        <p className="mt-4 text-sm text-slate-600">
          {receipt.balance_now > 0
            ? `${money(receipt.balance_now)} still outstanding.`
            : 'Account fully settled.'}
        </p>

        <div className="mt-5 flex justify-end">
          <button onClick={onClose} className="bg-[#16283c] px-4 py-2 text-sm text-white">
            Close
          </button>
        </div>
      </div>
    </div>
  );
}
