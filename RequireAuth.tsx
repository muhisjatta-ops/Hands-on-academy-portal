import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthProvider';
import type { Permission } from '../types/auth';

interface RequireAuthProps {
  children: ReactNode;
  /** If given, the user must hold at least one of these. */
  permission?: Permission | Permission[];
}

export function RequireAuth({ children, permission }: RequireAuthProps) {
  const { user, initialising, can } = useAuth();
  const location = useLocation();

  // Without this guard the app redirects to /login on every refresh,
  // before /auth/me has had a chance to answer.
  if (initialising) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        Loading…
      </div>
    );
  }

  if (!user) {
    // Remember where they were headed so login can send them back.
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  if (user.must_change_password && location.pathname !== '/password/change') {
    return <Navigate to="/password/change" replace />;
  }

  if (user.two_factor_required && !user.two_factor_enabled
      && location.pathname !== '/security/two-factor') {
    return <Navigate to="/security/two-factor" replace />;
  }

  if (permission && !can(permission)) {
    return <Navigate to="/no-access" replace />;
  }

  return <>{children}</>;
}

/** Conditionally render a fragment of UI. */
export function Can({
  permission, children, fallback = null,
}: {
  permission: Permission | Permission[];
  children: ReactNode;
  fallback?: ReactNode;
}) {
  const { can } = useAuth();
  return <>{can(permission) ? children : fallback}</>;
}
