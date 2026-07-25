/**
 * Market timezone awareness for Indonesia (GMT+7 / Asia/Jakarta)
 * Handles market hours, trading sessions, and activity scheduling
 */

export const TIMEZONE = "Asia/Jakarta";
export const UTC_OFFSET = 7;

// Market hours (Indonesia Stock Exchange - IDX)
const MARKET_HOURS = {
  preOpen: { start: "08:45", end: "09:00" },
  regular: { start: "09:00", end: "11:30" },
  lunchBreak: { start: "11:30", end: "13:30" },
  regular2: { start: "13:30", end: "15:50" },
  closing: { start: "15:50", end: "16:00" },
};

// Global market sessions in GMT+7
const GLOBAL_SESSIONS = [
  { name: "Sesi Sydney", start: "05:00", end: "14:00", region: "Asia-Pasifik" },
  { name: "Sesi Tokyo", start: "07:00", end: "16:00", region: "Asia" },
  { name: "Sesi London", start: "15:00", end: "00:00", region: "Eropa" },
  { name: "Sesi New York", start: "21:00", end: "06:00", region: "Amerika" },
];

export type MarketStatus = {
  isMarketOpen: boolean;
  currentSession: string;
  nextEvent: string;
  nextEventTime: string;
  tradingDay: boolean;
  currentTimeJakarta: string;
  currentDateJakarta: string;
};

function parseTimeToMinutes(time: string): number {
  const [h, m] = time.split(":").map(Number);
  return h * 60 + m;
}

function getJakartaNow(): Date {
  return new Date();
}

function getJakartaMinutes(): number {
  const now = getJakartaNow();
  const jakartaTime = new Intl.DateTimeFormat("en-US", {
    timeZone: TIMEZONE,
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  }).formatToParts(now);

  const hour = parseInt(jakartaTime.find((p) => p.type === "hour")?.value ?? "0");
  const minute = parseInt(jakartaTime.find((p) => p.type === "minute")?.value ?? "0");
  return hour * 60 + minute;
}

function getJakartaDayOfWeek(): number {
  const now = getJakartaNow();
  const jakartaTime = new Intl.DateTimeFormat("en-US", {
    timeZone: TIMEZONE,
    weekday: "short",
  }).format(now);
  const days: Record<string, number> = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
  return days[jakartaTime] ?? 0;
}

export function getMarketStatus(): MarketStatus {
  const minutes = getJakartaMinutes();
  const dayOfWeek = getJakartaDayOfWeek();
  const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

  const now = getJakartaNow();
  const timeStr = new Intl.DateTimeFormat("id-ID", {
    timeZone: TIMEZONE,
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).format(now);

  const dateStr = new Intl.DateTimeFormat("id-ID", {
    timeZone: TIMEZONE,
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(now);

  if (isWeekend) {
    return {
      isMarketOpen: false,
      currentSession: "Akhir Pekan",
      nextEvent: "Pasar buka Senin pukul 09:00 WIB",
      nextEventTime: "Senin 09:00",
      tradingDay: false,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  const preOpenStart = parseTimeToMinutes(MARKET_HOURS.preOpen.start);
  const preOpenEnd = parseTimeToMinutes(MARKET_HOURS.preOpen.end);
  const regular1Start = parseTimeToMinutes(MARKET_HOURS.regular.start);
  const regular1End = parseTimeToMinutes(MARKET_HOURS.regular.end);
  const regular2Start = parseTimeToMinutes(MARKET_HOURS.regular2.start);
  const regular2End = parseTimeToMinutes(MARKET_HOURS.regular2.end);
  const closingEnd = parseTimeToMinutes(MARKET_HOURS.closing.end);

  if (minutes >= preOpenStart && minutes < preOpenEnd) {
    return {
      isMarketOpen: false,
      currentSession: "Pra-Pembukaan",
      nextEvent: "Sesi Reguler 1 dimulai",
      nextEventTime: "09:00 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  if (minutes >= regular1Start && minutes < regular1End) {
    return {
      isMarketOpen: true,
      currentSession: "Sesi Reguler 1 (09:00 - 11:30)",
      nextEvent: "Istirahat makan siang",
      nextEventTime: "11:30 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  if (minutes >= regular1End && minutes < regular2Start) {
    return {
      isMarketOpen: false,
      currentSession: "Istirahat (11:30 - 13:30)",
      nextEvent: "Sesi Reguler 2 dimulai",
      nextEventTime: "13:30 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  if (minutes >= regular2Start && minutes < regular2End) {
    return {
      isMarketOpen: true,
      currentSession: "Sesi Reguler 2 (13:30 - 15:50)",
      nextEvent: "Sesi Penutupan",
      nextEventTime: "15:50 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  if (minutes >= regular2End && minutes < closingEnd) {
    return {
      isMarketOpen: false,
      currentSession: "Sesi Penutupan (15:50 - 16:00)",
      nextEvent: "Pasar ditutup",
      nextEventTime: "16:00 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  if (minutes < preOpenStart) {
    return {
      isMarketOpen: false,
      currentSession: "Pra-Pasar",
      nextEvent: "Pra-pembukaan dimulai",
      nextEventTime: "08:45 WIB",
      tradingDay: true,
      currentTimeJakarta: timeStr,
      currentDateJakarta: dateStr,
    };
  }

  return {
    isMarketOpen: false,
    currentSession: "Pasar Ditutup",
    nextEvent: "Pasar buka besok pukul 08:45 WIB",
    nextEventTime: "Besok 08:45",
    tradingDay: true,
    currentTimeJakarta: timeStr,
    currentDateJakarta: dateStr,
  };
}

export function getActiveGlobalSessions(): Array<{ name: string; region: string }> {
  const minutes = getJakartaMinutes();
  return GLOBAL_SESSIONS.filter((s) => {
    const start = parseTimeToMinutes(s.start);
    const end = parseTimeToMinutes(s.end);
    if (start < end) {
      return minutes >= start && minutes < end;
    }
    // Session wraps midnight
    return minutes >= start || minutes < end;
  }).map((s) => ({ name: s.name, region: s.region }));
}

export type GlobalSession = (typeof GLOBAL_SESSIONS)[number];
