import type { ReactNode } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthProvider';
import type { Permission } from '../types/auth';

const LINKS: { to: string; label: string; permission?: Permission }[] = [
  { to: '/',           label: 'Dashboard' },
  { to: '/students',   label: 'Students',   permission: 'students.view' },
  { to: '/fees',       label: 'Fees',       permission: 'fees.view' },
  { to: '/grades',     label: 'Grades',     permission: 'scores.enter' },
  { to: '/attendance', label: 'Attendance', permission: 'attendance.view' },
  { to: '/setup',      label: 'Setup',      permission: 'settings.manage' },
];

/**
 * The nav is filtered by permission, which is a courtesy to the user, not a
 * security control — every route behind it is permissioned server-side too.
 */
export function Shell({ children }: { children: ReactNode }) {
  const { user, logout, can } = useAuth();
  const location = useLocation();

  const visible = LINKS.filter((l) => !l.permission || can(l.permission));

  return (
    <div className="grid min-h-screen lg:grid-cols-[212px_1fr]">
      <nav className="flex flex-col bg-[#16283c] py-5 text-slate-300">
        <div className="px-5 pb-5">
          <p className="font-serif text-lg leading-tight text-white">Hand On Academy</p>
          <span className="text-xs text-slate-500">Administration portal</span>
        </div>

        <div className="flex overflow-x-auto lg:flex-col">
          {visible.map((link) => {
            const active = link.to === '/'
              ? location.pathname === '/'
              : location.pathname.startsWith(link.to);

            return (
              <Link
                key={link.to}
                to={link.to}
                className={`whitespace-nowrap px-5 py-2.5 text-sm hover:bg-white/5 hover:text-white lg:border-l-[3px] ${
                  active
                    ? 'bg-white/[0.07] text-white lg:border-[#2f6f5e]'
                    : 'lg:border-transparent'
                }`}
              >
                {link.label}
              </Link>
            );
          })}
        </div>

        <div className="mt-auto px-5 pt-6 text-xs text-slate-500">
          <p className="text-slate-300">{user?.name}</p>
          <p>{user?.roles.join(', ')}</p>
          <button
            onClick={logout}
            className="mt-3 border border-[#34506e] px-3 py-1.5 text-slate-200 hover:bg-white/5"
          >
            Sign out
          </button>
        </div>
      </nav>

      <main className="min-w-0 bg-[#f7f7f4] px-6 pb-16 pt-6 lg:px-8">{children}</main>
    </div>
  );
}

/* --------------------- small shared pieces --------------------- */

export function PageHead({
  title, subtitle, children,
}: { title: string; subtitle?: string; children?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 className="font-serif text-2xl text-slate-900">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-slate-600">{subtitle}</p>}
      </div>
      {children && <div className="flex gap-2">{children}</div>}
    </div>
  );
}

export function Stat({ label, value, note }: { label: string; value: string | number; note?: string }) {
  return (
    <div className="bg-white p-4">
      <p className="text-xs text-slate-500">{label}</p>
      <p className="mt-0.5 font-serif text-2xl text-slate-900">{value}</p>
      {note && <p className="text-xs text-slate-500">{note}</p>}
    </div>
  );
}

export function StatRow({ children }: { children: ReactNode }) {
  return (
    <div className="grid gap-px border border-slate-200 bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
      {children}
    </div>
  );
}

export function Loading({ what = 'Loading' }: { what?: string }) {
  return <p className="py-10 text-center text-sm text-slate-500">{what}…</p>;
}

export function Problem({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="border-l-2 border-[#9e3b32] bg-[#9e3b32]/5 px-4 py-3 text-sm text-[#7d2f27]">
      <p>{message}</p>
      {onRetry && (
        <button onClick={onRetry} className="mt-2 underline underline-offset-4">
          Try again
        </button>
      )}
    </div>
  );
}

export function Empty({ message, children }: { message: string; children?: ReactNode }) {
  return (
    <div className="border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
      <p className="text-sm text-slate-600">{message}</p>
      {children && <div className="mt-3">{children}</div>}
    </div>
  );
}

export const money = (amount: number) =>
  'D' + amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
