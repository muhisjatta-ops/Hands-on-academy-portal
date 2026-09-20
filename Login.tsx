import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthProvider';
import { ApiError } from '../lib/api';

/**
 * Two steps in one screen. Keeping the 2FA challenge here rather than on a
 * separate route means a refresh mid-challenge lands somewhere sensible
 * instead of on a dead URL.
 */
export default function Login() {
  const { user, awaitingTwoFactor } = useAuth();
  const location = useLocation();
  const from = (location.state as { from?: Location })?.from?.pathname ?? '/';

  if (user) return <Navigate to={from} replace />;

  return (
    <div className="grid min-h-screen lg:grid-cols-[5fr_4fr]">
      <aside className="hidden flex-col justify-between bg-[#16283c] p-12 text-slate-200 lg:flex">
        <p className="font-serif text-2xl tracking-tight text-white">
          Nyaka Memorial School
        </p>

        <div className="max-w-sm">
          <h1 className="font-serif text-4xl leading-tight text-white">
            Everything about every student, in one place.
          </h1>
          <p className="mt-4 text-sm leading-relaxed text-slate-400">
            Enrolment, fees, marks and attendance for the 2026/2027 academic
            year. Term 1 closes on 12 December.
          </p>
        </div>

        <p className="text-xs text-slate-500">
          Trouble signing in? Call the school office on 4497 812.
        </p>
      </aside>

      <main className="flex items-center justify-center bg-[#fafaf8] px-6 py-12">
        <div className="w-full max-w-sm">
          {awaitingTwoFactor ? <TwoFactorStep /> : <PasswordStep />}
        </div>
      </main>
    </div>
  );
}

function PasswordStep() {
  const { login } = useAuth();
  const [form, setForm] = useState({ login: '', password: '', remember: false });
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);

    try {
      await login(form);
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Network error.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <h2 className="font-serif text-3xl text-slate-900">Sign in</h2>
      <p className="mt-2 text-sm text-slate-600">
        Use your staff email or admission number.
      </p>

      {error && !error.field('login') && (
        <p role="alert" className="mt-6 border-l-2 border-[#9e3b32] bg-[#9e3b32]/5 px-3 py-2 text-sm text-[#7d2f27]">
          {error.message}
        </p>
      )}

      <div className="mt-8 space-y-5">
        <Field
          label="Email or admission number"
          name="login"
          autoComplete="username"
          value={form.login}
          error={error?.field('login')}
          onChange={(v) => setForm({ ...form, login: v })}
          onEnter={submit}
        />

        <div>
          <Field
            label="Password"
            name="password"
            type="password"
            autoComplete="current-password"
            value={form.password}
            error={error?.field('password')}
            onChange={(v) => setForm({ ...form, password: v })}
            onEnter={submit}
          />
          <Link
            to="/forgot-password"
            className="mt-2 inline-block text-sm text-[#2f6f5e] underline decoration-[#2f6f5e]/30 underline-offset-4 hover:decoration-[#2f6f5e]"
          >
            I forgot my password
          </Link>
        </div>

        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input
            type="checkbox"
            checked={form.remember}
            onChange={(e) => setForm({ ...form, remember: e.target.checked })}
            className="h-4 w-4 rounded border-slate-400 text-[#2f6f5e] focus:ring-[#2f6f5e]"
          />
          Keep me signed in on this device
        </label>

        <button
          onClick={submit}
          disabled={busy || !form.login || !form.password}
          className="w-full bg-[#16283c] px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-[#1f3853] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2f6f5e] disabled:cursor-not-allowed disabled:bg-slate-400"
        >
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </div>
    </div>
  );
}

function TwoFactorStep() {
  const { submitTwoFactor, cancelTwoFactor } = useAuth();
  const [code, setCode] = useState('');
  const [recovery, setRecovery] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    setError(null);

    try {
      await submitTwoFactor(useRecovery ? { recovery_code: recovery } : { code });
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Network error.'));
      setCode('');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <h2 className="font-serif text-3xl text-slate-900">One more step</h2>
      <p className="mt-2 text-sm text-slate-600">
        {useRecovery
          ? 'Enter one of the recovery codes you saved during setup.'
          : 'Enter the 6-digit code from your authenticator app.'}
      </p>

      {error && (
        <p role="alert" className="mt-6 border-l-2 border-[#9e3b32] bg-[#9e3b32]/5 px-3 py-2 text-sm text-[#7d2f27]">
          {error.field('code') ?? error.message}
        </p>
      )}

      <div className="mt-8 space-y-5">
        {useRecovery ? (
          <Field
            label="Recovery code"
            name="recovery_code"
            value={recovery}
            onChange={setRecovery}
            onEnter={submit}
          />
        ) : (
          <div>
            <label htmlFor="code" className="block text-sm font-medium text-slate-800">
              Verification code
            </label>
            <input
              id="code"
              name="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              autoFocus
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              onKeyDown={(e) => e.key === 'Enter' && code.length === 6 && submit()}
              className="mt-1.5 w-full border border-slate-300 bg-white px-3 py-2.5 text-center text-2xl tracking-[0.4em] text-slate-900 focus:border-[#2f6f5e] focus:outline-none focus:ring-1 focus:ring-[#2f6f5e]"
            />
          </div>
        )}

        <button
          onClick={submit}
          disabled={busy || (useRecovery ? !recovery : code.length !== 6)}
          className="w-full bg-[#16283c] px-4 py-3 text-sm font-medium text-white hover:bg-[#1f3853] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2f6f5e] disabled:cursor-not-allowed disabled:bg-slate-400"
        >
          {busy ? 'Checking…' : 'Verify'}
        </button>

        <div className="flex justify-between text-sm">
          <button
            onClick={() => { setUseRecovery(!useRecovery); setError(null); }}
            className="text-[#2f6f5e] underline decoration-[#2f6f5e]/30 underline-offset-4"
          >
            {useRecovery ? 'Use my authenticator app' : 'Use a recovery code'}
          </button>
          <button onClick={cancelTwoFactor} className="text-slate-500 underline underline-offset-4">
            Start over
          </button>
        </div>
      </div>
    </div>
  );
}

interface FieldProps {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  onEnter?: () => void;
  type?: string;
  autoComplete?: string;
  error?: string;
}

function Field({
  label, name, value, onChange, onEnter, type = 'text', autoComplete, error,
}: FieldProps) {
  return (
    <div>
      <label htmlFor={name} className="block text-sm font-medium text-slate-800">
        {label}
      </label>
      <input
        id={name}
        name={name}
        type={type}
        autoComplete={autoComplete}
        value={value}
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${name}-error` : undefined}
        onChange={(e) => onChange(e.target.value)}
        onKeyDown={(e) => e.key === 'Enter' && onEnter?.()}
        className={`mt-1.5 w-full border bg-white px-3 py-2.5 text-slate-900 focus:outline-none focus:ring-1 ${
          error
            ? 'border-[#9e3b32] focus:border-[#9e3b32] focus:ring-[#9e3b32]'
            : 'border-slate-300 focus:border-[#2f6f5e] focus:ring-[#2f6f5e]'
        }`}
      />
      {error && (
        <p id={`${name}-error`} className="mt-1.5 text-sm text-[#7d2f27]">{error}</p>
      )}
    </div>
  );
}
