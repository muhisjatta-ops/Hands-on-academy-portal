import { useCallback, useEffect, useRef, useState } from 'react';
import { ApiError } from '../lib/api';

interface State<T> {
  data: T | null;
  loading: boolean;
  error: ApiError | null;
}

/**
 * Run an async fetch and track its three states.
 *
 * The `sequence` ref is the part worth keeping: when a user types in a
 * search box, several requests are in flight at once and they do not come
 * back in order. Without this, a slow response for "Jat" can overwrite the
 * fast response for "Jatta" and show the wrong rows.
 */
export function useRequest<T>(
  fetcher: () => Promise<T>,
  deps: unknown[],
): State<T> & { reload: () => void } {
  const [state, setState] = useState<State<T>>({ data: null, loading: true, error: null });
  const sequence = useRef(0);
  const [nonce, setNonce] = useState(0);

  const run = useCallback(async () => {
    const mine = ++sequence.current;
    setState((s) => ({ ...s, loading: true, error: null }));

    try {
      const data = await fetcher();
      if (mine === sequence.current) setState({ data, loading: false, error: null });
    } catch (error) {
      if (mine !== sequence.current) return;
      setState({
        data: null,
        loading: false,
        error: error instanceof ApiError ? error : new ApiError(0, 'Network error.'),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  useEffect(() => { run(); }, [run, nonce]);

  return { ...state, reload: () => setNonce((n) => n + 1) };
}

/** Wrap a mutating call: tracks pending state and surfaces the error. */
export function useAction() {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  const run = async <T,>(fn: () => Promise<T>): Promise<T | null> => {
    setPending(true);
    setError(null);

    try {
      return await fn();
    } catch (e) {
      setError(e instanceof ApiError ? e : new ApiError(0, 'Network error.'));
      return null;
    } finally {
      setPending(false);
    }
  };

  return { run, pending, error, clearError: () => setError(null) };
}
