import {
  createContext, useCallback, useContext, useEffect, useMemo, useState,
  type ReactNode,
} from 'react';
import { api, ApiError } from '../lib/api';
import type {
  AuthUser, LoginCredentials, LoginResult, Permission, Role,
} from '../types/auth';

interface AuthContextValue {
  user: AuthUser | null;
  /** True only during the initial "am I logged in?" check. */
  initialising: boolean;
  /** Set when a password succeeded but a 2FA code is still needed. */
  awaitingTwoFactor: boolean;
  login: (credentials: LoginCredentials) => Promise<LoginResult>;
  submitTwoFactor: (input: { code?: string; recovery_code?: string }) => Promise<void>;
  cancelTwoFactor: () => void;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  can: (permission: Permission | Permission[]) => boolean;
  is: (role: Role | Role[]) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [initialising, setInitialising] = useState(true);
  const [awaitingTwoFactor, setAwaitingTwoFactor] = useState(false);

  const loadUser = useCallback(async () => {
    try {
      setUser(await api.get<AuthUser>('/auth/me'));
    } catch (error) {
      // 401 here is the normal "not signed in" case, not a failure.
      if (error instanceof ApiError && error.status === 401) {
        setUser(null);
      } else {
        throw error;
      }
    }
  }, []);

  // On mount, ask the server who we are. The session cookie is httpOnly,
  // so this request is the only way to know.
  useEffect(() => {
    loadUser().finally(() => setInitialising(false));
  }, [loadUser]);

  const login = useCallback(async (credentials: LoginCredentials) => {
    const result = await api.post<LoginResult>('/auth/login', credentials);

    if (result.two_factor) {
      setAwaitingTwoFactor(true);
    } else {
      await loadUser();
    }

    return result;
  }, [loadUser]);

  const submitTwoFactor = useCallback(async (input: { code?: string; recovery_code?: string }) => {
    await api.post('/auth/two-factor-challenge', input);
    setAwaitingTwoFactor(false);
    await loadUser();
  }, [loadUser]);

  const cancelTwoFactor = useCallback(() => setAwaitingTwoFactor(false), []);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      setUser(null);
      setAwaitingTwoFactor(false);
    }
  }, []);

  /**
   * Permission checks here are for HIDING UI ONLY. Every one of them must be
   * enforced again server-side — a hidden button is a courtesy, not a control.
   */
  const can = useCallback((permission: Permission | Permission[]) => {
    if (!user) return false;
    if (user.roles.includes('super-admin')) return true;

    const wanted = Array.isArray(permission) ? permission : [permission];
    return wanted.some((p) => user.permissions.includes(p));
  }, [user]);

  const is = useCallback((role: Role | Role[]) => {
    if (!user) return false;
    const wanted = Array.isArray(role) ? role : [role];
    return wanted.some((r) => user.roles.includes(r));
  }, [user]);

  const value = useMemo<AuthContextValue>(() => ({
    user, initialising, awaitingTwoFactor,
    login, submitTwoFactor, cancelTwoFactor, logout,
    refresh: loadUser, can, is,
  }), [user, initialising, awaitingTwoFactor, login, submitTwoFactor,
       cancelTwoFactor, logout, loadUser, can, is]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside <AuthProvider>.');
  }

  return context;
}
