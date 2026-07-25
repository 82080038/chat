/**
 * Format utilities for Indonesian locale (id-ID)
 * Timezone: Asia/Jakarta (GMT+7)
 */

const LOCALE = "id-ID";
const TIMEZONE = "Asia/Jakarta";

export function formatCurrency(value: number | string | null | undefined, decimals = 0): string {
  if (value === null || value === undefined) return "-";
  const num = Number(value);
  if (isNaN(num)) return "-";
  return num.toLocaleString(LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}

export function formatRupiah(value: number | string | null | undefined, decimals = 0): string {
  const formatted = formatCurrency(value, decimals);
  return formatted === "-" ? "-" : `Rp ${formatted}`;
}

export function formatDate(value: string | Date | null | undefined): string {
  if (!value) return "-";
  const date = typeof value === "string" ? new Date(value) : value;
  if (isNaN(date.getTime())) return "-";
  return date.toLocaleDateString(LOCALE, {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: TIMEZONE,
  });
}

export function formatDateTime(value: string | Date | null | undefined): string {
  if (!value) return "-";
  const date = typeof value === "string" ? new Date(value) : value;
  if (isNaN(date.getTime())) return "-";
  return date.toLocaleString(LOCALE, {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    timeZone: TIMEZONE,
  });
}

export function formatTime(value: string | Date | null | undefined): string {
  if (!value) return "-";
  const date = typeof value === "string" ? new Date(value) : value;
  if (isNaN(date.getTime())) return "-";
  return date.toLocaleTimeString(LOCALE, {
    hour: "2-digit",
    minute: "2-digit",
    timeZone: TIMEZONE,
  });
}

export function formatPercent(value: number | string | null | undefined, decimals = 2): string {
  if (value === null || value === undefined) return "-";
  const num = Number(value);
  if (isNaN(num)) return "-";
  return `${num.toLocaleString(LOCALE, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}%`;
}

export function formatNumber(value: number | string | null | undefined, decimals = 0): string {
  if (value === null || value === undefined) return "-";
  const num = Number(value);
  if (isNaN(num)) return "-";
  return num.toLocaleString(LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}
