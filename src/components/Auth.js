import React from 'react';

const STORAGE_KEY = 'wm_auth_v1';

const AuthContext = React.createContext(null);

function readStored() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return null;
    return parsed;
  } catch {
    return null;
  }
}

export function AuthProvider({ children }) {
  const [session, setSession] = React.useState(() => readStored());

  const signIn = React.useCallback(({ email, userId, role, name }) => {
    const next = {
      email,
      userId: userId ?? null,
      role: role === 'admin' || role === 'business' ? role : null,
      name: typeof name === 'string' && name.trim() ? name.trim() : null,
      at: Date.now(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    setSession(next);
  }, []);

  const signOut = React.useCallback(() => {
    localStorage.removeItem(STORAGE_KEY);
    setSession(null);
  }, []);

  const value = React.useMemo(
    () => ({
      isAuthed: Boolean(session),
      session,
      signIn,
      signOut,
    }),
    [session, signIn, signOut]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = React.useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}

