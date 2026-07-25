import { useState, useEffect, useCallback } from "react";
import { MarketSchedulerAPI } from "@/lib/api";
import { ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  RefreshCw, Play, Clock, Globe, Zap, Activity, AlertCircle, CheckCircle2, Timer
} from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";
import { formatDateTime } from "@/lib/format";

type ActiveSession = {
  code: string;
  name: string;
  region: string;
  influence: string;
  close: string;
};

type SessionOverlap = {
  sessions: string;
  label: string;
  significance: string;
};

type DueTask = {
  id: string;
  name: string;
  description: string;
  phase: string;
  actions: string[];
  window: string;
};

type NextTask = {
  id: string;
  name: string;
  phase: string;
  starts_at: string;
  minutes_until: number;
};

type AllSession = {
  code: string;
  name: string;
  open: string;
  close: string;
  region: string;
  influence: string;
  is_active: boolean;
};

type AllTask = {
  id: string;
  name: string;
  description: string;
  phase: string;
  window: string;
  actions: string[];
  depends_on_sessions: string[];
  status: string;
};

type SchedulerStatus = {
  current_time: string;
  current_date: string;
  day_of_week: string;
  is_weekend: boolean;
  is_trading_day: boolean;
  idx_phase: string;
  active_sessions: ActiveSession[];
  session_overlaps: SessionOverlap[];
  due_tasks: DueTask[];
  next_task: NextTask | null;
  all_sessions: AllSession[];
  all_tasks: AllTask[];
};

type RunResult = {
  task_id: string;
  task_name: string;
  phase: string;
  actions_run: number;
  errors: number;
  elapsed_seconds: number;
  details: Array<{ action: string; status: string; summary?: unknown; error?: string }>;
};

const PHASE_LABELS: Record<string, string> = {
  PRE_MARKET: "Pra-Pasar",
  IDX_PRE_OPEN: "Pra-Pembukaan IDX",
  IDX_REGULAR_1: "Sesi Reguler 1 IDX",
  IDX_LUNCH: "Istirahat IDX",
  IDX_REGULAR_2: "Sesi Reguler 2 IDX",
  IDX_CLOSING: "Penutupan IDX",
  POST_MARKET: "Pasca-Pasar",
  GLOBAL_OVERLAP: "Overlap Global",
  OVERNIGHT: "Semalam",
  WEEKEND: "Akhir Pekan",
};

const TASK_STATUS_CONFIG: Record<string, { variant: "default" | "secondary" | "destructive" | "outline"; label: string; icon: typeof CheckCircle2 }> = {
  DUE_NOW: { variant: "default", label: "Tugas Aktif", icon: Zap },
  PENDING: { variant: "secondary", label: "Menunggu", icon: Timer },
  COMPLETED: { variant: "outline", label: "Selesai", icon: CheckCircle2 },
  WEEKEND_SKIP: { variant: "outline", label: "Lewati (Akhir Pekan)", icon: Clock },
};

export default function MarketScheduler() {
  const [status, setStatus] = useState<SchedulerStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [runResults, setRunResults] = useState<RunResult[] | null>(null);
  const [running, setRunning] = useState(false);

  const fetchStatus = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await MarketSchedulerAPI.getStatus();
      setStatus(res as unknown as SchedulerStatus);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal memuat status scheduler");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStatus();
    const interval = setInterval(fetchStatus, 30000);
    return () => clearInterval(interval);
  }, [fetchStatus]);

  const handleRunDue = async () => {
    setRunning(true);
    setError(null);
    setRunResults(null);
    try {
      const res = await MarketSchedulerAPI.runDueTasks();
      const data = res as unknown as { results: RunResult[] };
      setRunResults(data.results ?? []);
      await fetchStatus();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal menjalankan tugas");
    } finally {
      setRunning(false);
    }
  };

  const handleRunTask = async (taskId: string) => {
    setRunning(true);
    setError(null);
    try {
      await MarketSchedulerAPI.runTask(taskId);
      await fetchStatus();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal menjalankan tugas");
    } finally {
      setRunning(false);
    }
  };

  const formatMinutesUntil = (mins: number) => {
    if (mins < 60) return `${mins} menit`;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return m > 0 ? `${h}j ${m}m` : `${h} jam`;
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Penjadwal Aktivitas Pasar</h1>
          <p className="text-sm text-muted-foreground">
            Jadwal otomatis berdasarkan sesi pasar global dan IDX (GMT+7)
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={fetchStatus} disabled={loading}>
            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} /> Muat Ulang
          </Button>
          <Button onClick={handleRunDue} disabled={running || (status?.due_tasks.length === 0)}>
            <Play className="mr-2 h-4 w-4" /> Jalankan Tugas Aktif
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {status && (
        <>
          {/* Current Status Cards */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-2 mb-1">
                  <Clock className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Waktu Jakarta</span>
                </div>
                <p className="text-xl font-bold">{status.current_time} WIB</p>
                <p className="text-xs text-muted-foreground">{status.day_of_week}, {status.current_date}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-2 mb-1">
                  <Activity className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Fase IDX</span>
                </div>
                <p className="text-xl font-bold">{PHASE_LABELS[status.idx_phase] ?? status.idx_phase}</p>
                <p className="text-xs text-muted-foreground">{status.is_trading_day ? "Hari Trading" : "Bukan Hari Trading"}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-2 mb-1">
                  <Globe className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Sesi Aktif</span>
                </div>
                <p className="text-xl font-bold">{status.active_sessions.length}</p>
                <p className="text-xs text-muted-foreground">
                  {status.active_sessions.map(s => s.code).join(", ") || "Tidak ada"}
                </p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-2 mb-1">
                  <Zap className="h-4 w-4 text-muted-foreground" />
                  <span className="text-sm text-muted-foreground">Tugas Aktif</span>
                </div>
                <p className="text-xl font-bold text-amber-600">{status.due_tasks.length}</p>
                {status.next_task && (
                  <p className="text-xs text-muted-foreground">
                    Berikutnya: {status.next_task.name} ({formatMinutesUntil(status.next_task.minutes_until)})
                  </p>
                )}
              </CardContent>
            </Card>
          </div>

          {/* Active Sessions */}
          {status.active_sessions.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Globe className="h-5 w-5" />
                  Sesi Pasar Aktif
                </CardTitle>
                <CardDescription>Pasar global yang sedang buka dan pengaruhnya terhadap IDX</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {status.active_sessions.map((session) => (
                  <div key={session.code} className="flex items-start justify-between rounded-md border border-border p-3">
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <Badge variant="default" className="text-xs">{session.code}</Badge>
                        <span className="font-medium text-sm">{session.name}</span>
                      </div>
                      <p className="text-xs text-muted-foreground mt-1">{session.influence}</p>
                    </div>
                    <div className="text-right">
                      <span className="text-xs text-muted-foreground">Tutup</span>
                      <p className="text-sm font-mono">{session.close} WIB</p>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Session Overlaps */}
          {status.session_overlaps.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <AlertCircle className="h-5 w-5 text-amber-600" />
                  <TermTooltip term="Overlap">Overlap Sesi Pasar</TermTooltip>
                </CardTitle>
                <CardDescription>
                  Saat dua pasar buka bersamaan, likuiditas dan volatilitas meningkat
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-2">
                {status.session_overlaps.map((overlap, i) => (
                  <div key={i} className="flex items-center gap-3 rounded-md border border-amber-500/30 bg-amber-500/5 p-3">
                    <Badge variant="secondary" className="text-xs">{overlap.sessions}</Badge>
                    <div className="flex-1">
                      <span className="text-sm font-medium">{overlap.label}</span>
                      <p className="text-xs text-muted-foreground">{overlap.significance}</p>
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Due Tasks */}
          {status.due_tasks.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Zap className="h-5 w-5 text-amber-600" />
                  Tugas Aktif Sekarang
                </CardTitle>
                <CardDescription>Tugas yang seharusnya berjalan pada waktu ini</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {status.due_tasks.map((task) => (
                  <div key={task.id} className="rounded-md border border-amber-500/30 bg-amber-500/5 p-4">
                    <div className="flex items-start justify-between mb-2">
                      <div>
                        <h4 className="font-medium text-sm">{task.name}</h4>
                        <p className="text-xs text-muted-foreground mt-1">{task.description}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant="outline" className="text-xs">{task.window}</Badge>
                        <Button size="sm" variant="outline" onClick={() => handleRunTask(task.id)} disabled={running}>
                          <Play className="h-3 w-3 mr-1" /> Jalankan
                        </Button>
                      </div>
                    </div>
                    <div className="flex flex-wrap gap-1 mt-2">
                      {task.actions.map((action) => (
                        <Badge key={action} variant="secondary" className="text-xs font-mono">{action}</Badge>
                      ))}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Run Results */}
          {runResults && runResults.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <CheckCircle2 className="h-5 w-5 text-green-600" />
                  Hasil Eksekusi Tugas
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {runResults.map((result) => (
                  <div key={result.task_id} className="rounded-md border p-3">
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-medium text-sm">{result.task_name}</span>
                      <div className="flex items-center gap-2">
                        {result.errors > 0 ? (
                          <Badge variant="destructive" className="text-xs">{result.errors} error</Badge>
                        ) : (
                          <Badge variant="default" className="text-xs">OK</Badge>
                        )}
                        <span className="text-xs text-muted-foreground">{result.elapsed_seconds}s</span>
                      </div>
                    </div>
                    <div className="space-y-1">
                      {result.details.map((detail, i) => (
                        <div key={i} className="flex items-center gap-2 text-xs">
                          {detail.status === 'OK' ? (
                            <CheckCircle2 className="h-3 w-3 text-green-600" />
                          ) : (
                            <AlertCircle className="h-3 w-3 text-red-600" />
                          )}
                          <span className="font-mono">{detail.action}</span>
                          {detail.error && <span className="text-red-600">— {detail.error}</span>}
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Global Sessions Timeline */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Globe className="h-5 w-5" />
                Sesi Pasar Global (WIB)
              </CardTitle>
              <CardDescription>Jam buka dan tutup semua pasar global dalam waktu Jakarta (GMT+7)</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Pasar</TableHead>
                    <TableHead>Wilayah</TableHead>
                    <TableHead>Buka</TableHead>
                    <TableHead>Tutup</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {status.all_sessions.map((session) => (
                    <TableRow key={session.code}>
                      <TableCell>
                        <div className="font-medium text-sm">{session.name}</div>
                        <div className="text-xs text-muted-foreground">{session.influence}</div>
                      </TableCell>
                      <TableCell className="text-sm">{session.region}</TableCell>
                      <TableCell className="font-mono text-sm">{session.open}</TableCell>
                      <TableCell className="font-mono text-sm">{session.close}</TableCell>
                      <TableCell>
                        {session.is_active ? (
                          <Badge variant="default" className="text-xs">Aktif</Badge>
                        ) : (
                          <Badge variant="outline" className="text-xs">Tutup</Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {/* Full Task Schedule */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Timer className="h-5 w-5" />
                Jadwal Tugas Harian
              </CardTitle>
              <CardDescription>
                Semua tugas terjadwal dengan fase pasar dan ketergantungan sesi global
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Tugas</TableHead>
                    <TableHead>Jendela Waktu</TableHead>
                    <TableHead>Aksi</TableHead>
                    <TableHead>Sesi Depan</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {status.all_tasks.map((task) => {
                    const config = TASK_STATUS_CONFIG[task.status] ?? TASK_STATUS_CONFIG.PENDING;
                    const Icon = config.icon;
                    return (
                      <TableRow key={task.id}>
                        <TableCell>
                          <div className="font-medium text-sm">{task.name}</div>
                          <div className="text-xs text-muted-foreground">{task.description}</div>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{task.window}</TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {task.actions.map((a) => (
                              <Badge key={a} variant="outline" className="text-xs font-mono">{a}</Badge>
                            ))}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {task.depends_on_sessions.map((s) => (
                              <Badge key={s} variant="secondary" className="text-xs">{s}</Badge>
                            ))}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Icon className="h-3 w-3" />
                            <Badge variant={config.variant} className="text-xs">{config.label}</Badge>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleRunTask(task.id)}
                            disabled={running || task.status === 'WEEKEND_SKIP'}
                          >
                            <Play className="h-3 w-3" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <p className="text-xs text-muted-foreground text-center">
            Diperbarui otomatis setiap 30 detik • {formatDateTime(new Date().toISOString())}
          </p>
        </>
      )}
    </div>
  );
}
