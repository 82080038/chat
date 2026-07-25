import { useState, useEffect } from "react";
import { getMarketStatus, getActiveGlobalSessions } from "@/lib/market-time";
import { Clock, CircleDot, Globe } from "lucide-react";
import { Tooltip } from "@/components/ui/tooltip";

export function MarketClock() {
  const [status, setStatus] = useState(getMarketStatus());
  const [sessions, setSessions] = useState(getActiveGlobalSessions());

  useEffect(() => {
    const interval = setInterval(() => {
      setStatus(getMarketStatus());
      setSessions(getActiveGlobalSessions());
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 text-muted-foreground" />
          <Tooltip content="Waktu Indonesia Timur (GMT+7), digunakan untuk jadwal aktivitas pasar modal">
            <span className="text-sm font-medium">WIB</span>
          </Tooltip>
        </div>
        <span className="text-lg font-bold tabular-nums">{status.currentTimeJakarta}</span>
      </div>
      <div className="text-xs text-muted-foreground">{status.currentDateJakarta}</div>
      <div className="flex items-center gap-2">
        <CircleDot className={`h-3 w-3 ${status.isMarketOpen ? "text-green-500" : "text-muted-foreground"}`} />
        <span className={`text-sm font-medium ${status.isMarketOpen ? "text-green-600" : "text-muted-foreground"}`}>
          {status.currentSession}
        </span>
      </div>
      {!status.isMarketOpen && status.tradingDay && (
        <div className="text-xs text-muted-foreground">
          Berikutnya: {status.nextEvent} ({status.nextEventTime})
        </div>
      )}
      {sessions.length > 0 && (
        <div className="flex items-center gap-1 border-t border-border pt-2">
          <Globe className="h-3 w-3 text-muted-foreground" />
          <span className="text-xs text-muted-foreground">Sesi aktif:</span>
          {sessions.map((s, i) => (
            <span key={i} className="text-xs font-medium text-foreground">
              {s.name}{i < sessions.length - 1 ? "," : ""}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
