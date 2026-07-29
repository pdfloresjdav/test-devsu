import { createContext, useEffect, useState, type ReactNode } from 'react';
import type { User } from 'oidc-client-ts';
import { userManager } from './oidcConfig';

export interface AuthContextValue {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  accessToken: string | null;
  login: () => Promise<void>;
  logout: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/**
 * Manages the session (item 9.5): loads the persisted user on mount,
 * listens for silent token renewal (refresh_token, since the scope
 * includes offline_access) and session expiration.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    userManager
      .getUser()
      .then((storedUser) => setUser(storedUser && !storedUser.expired ? storedUser : null))
      .finally(() => setIsLoading(false));

    const onUserLoaded = (loadedUser: User) => setUser(loadedUser);
    const onUserUnloaded = () => setUser(null);
    const onAccessTokenExpired = () => setUser(null);

    userManager.events.addUserLoaded(onUserLoaded);
    userManager.events.addUserUnloaded(onUserUnloaded);
    userManager.events.addAccessTokenExpired(onAccessTokenExpired);

    return () => {
      userManager.events.removeUserLoaded(onUserLoaded);
      userManager.events.removeUserUnloaded(onUserUnloaded);
      userManager.events.removeAccessTokenExpired(onAccessTokenExpired);
    };
  }, []);

  const value: AuthContextValue = {
    user,
    isAuthenticated: user !== null,
    isLoading,
    accessToken: user?.access_token ?? null,
    login: () => userManager.signinRedirect(),
    logout: () => userManager.signoutRedirect(),
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
