import {
  createContext,
  useContext,
  useState,
  useEffect,
  type ReactNode,
} from "react";
import { api, type AuthTokens, type Owner } from "./api";

interface AuthContextType {
  owner: Owner | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [owner, setOwner] = useState<Owner | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (token) {
      api
        .get<Owner>("/auth/me")
        .then((data) => setOwner(data))
        .catch(() => {
          localStorage.removeItem("access_token");
          localStorage.removeItem("refresh_token");
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  async function login(email: string, password: string) {
    const tokens = await api.post<AuthTokens>("/auth/login", {
      email,
      password,
    });
    localStorage.setItem("access_token", tokens.access_token);
    localStorage.setItem("refresh_token", tokens.refresh_token);
    const me = await api.get<Owner>("/auth/me");
    setOwner(me);
  }

  function logout() {
    api.post("/auth/logout").catch(() => {});
    localStorage.removeItem("access_token");
    localStorage.removeItem("refresh_token");
    setOwner(null);
  }

  return (
    <AuthContext.Provider value={{ owner, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
