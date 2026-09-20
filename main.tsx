import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthProvider';
import { RequireAuth } from './auth/RequireAuth';
import Login from './pages/Login';
import DashboardPage from './pages/DashboardPage';
import StudentsPage from './pages/StudentsPage';
import FeesPage from './pages/FeesPage';
import GradesPage from './pages/GradesPage';
import AttendancePage from './pages/AttendancePage';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route path="/" element={
            <RequireAuth><DashboardPage /></RequireAuth>
          } />

          {/* The permission prop is a convenience, not the control: each of
              these endpoints is permissioned again server-side. */}
          <Route path="/students" element={
            <RequireAuth permission="students.view"><StudentsPage /></RequireAuth>
          } />
          <Route path="/fees" element={
            <RequireAuth permission="fees.view"><FeesPage /></RequireAuth>
          } />
          <Route path="/grades" element={
            <RequireAuth permission="scores.enter"><GradesPage /></RequireAuth>
          } />
          <Route path="/attendance" element={
            <RequireAuth permission="attendance.view"><AttendancePage /></RequireAuth>
          } />

          <Route path="/security/two-factor" element={
            <RequireAuth><Stub title="Set up two-step verification" /></RequireAuth>
          } />
          <Route path="/password/change" element={
            <RequireAuth><Stub title="Change your password" /></RequireAuth>
          } />
          <Route path="/forgot-password" element={<Stub title="Reset your password" />} />
          <Route path="/no-access" element={
            <Stub title="You don't have access to that" />
          } />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
);

function Stub({ title }: { title: string }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[#f7f7f4] px-6">
      <div className="max-w-sm text-center">
        <h1 className="font-serif text-2xl text-slate-900">{title}</h1>
        <p className="mt-2 text-sm text-slate-600">This screen isn't built yet.</p>
      </div>
    </div>
  );
}
