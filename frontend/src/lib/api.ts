const API_BASE = "";

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public fieldErrors?: Record<string, string[]>
  ) {
    super(message);
  }
}

function getToken(): string | null {
  return localStorage.getItem("access_token");
}

async function request<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...((options.headers as Record<string, string>) || {}),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  const text = await res.text();
  const data = text ? JSON.parse(text) : null;

  if (!res.ok) {
    const err = data?.error || {};
    throw new ApiError(
      res.status,
      err.code || "UNKNOWN",
      err.message || `HTTP ${res.status}`,
      err.field_errors
    );
  }

  // Unwrap { data: ... } envelope from API responses
  return (data?.data !== undefined ? data.data : data) as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: "POST",
      body: body ? JSON.stringify(body) : undefined,
    }),
  put: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: "PUT",
      body: body ? JSON.stringify(body) : undefined,
    }),
  del: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};

export type ApiResponse<T> = {
  data: T;
  meta?: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
};

export type AuthTokens = {
  token: string;
  refresh_token: string;
  expires_in: number;
  token_type: string;
};

export type Owner = {
  owner_id: string;
  email: string;
  display_name: string;
  created_at: string;
};

export type Signal = {
  signal_id: string;
  instrument_id: string;
  signal_type: string;
  direction: string;
  strength: string;
  timeframe: string;
  model_version: string;
  created_at: string;
};

export type Portfolio = {
  portfolio_id: string;
  name: string;
  base_currency: string;
  status: string;
};

export type Alert = {
  alert_id: string;
  alert_type: string;
  condition_op: string;
  threshold: number;
  is_active: number;
  description: string | null;
};
