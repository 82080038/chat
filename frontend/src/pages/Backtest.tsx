import { useState, useEffect, useCallback } from "react";
import { BacktestAPI, type BacktestRun, type BacktestMetrics, type BacktestTrade } from "@/lib/api";
import { ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
import { Play, RefreshCw, TrendingUp, TrendingDown, BarChart3 } from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";
import { formatRupiah, formatNumber, formatPercent } from "@/lib/format";

const STRATEGIES = [
  { value: "SMA_CROSSOVER", label: "SMA Crossover" },
  { value: "RSI_MEAN_REVERSION", label: "RSI Mean Reversion" },
  { value: "MOMENTUM", label: "Momentum" },
  { value: "MEAN_REVERSION", label: "Bollinger Bands" },
  { value: "BUY_AND_HOLD", label: "Buy & Hold" },
];

export default function Backtest() {
  const [runs, setRuns] = useState<BacktestRun[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedRun, setSelectedRun] = useState<BacktestRun | null>(null);
  const [metrics, setMetrics] = useState<BacktestMetrics | null>(null);
  const [trades, setTrades] = useState<BacktestTrade[]>([]);

  const [form, setForm] = useState({
    strategy_name: "SMA_CROSSOVER",
    start_date: "",
    end_date: "",
    initial_capital: "100000000",
    instrument_id: "",
  });

  const fetchRuns = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await BacktestAPI.listRuns(1, 50);
      setRuns(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal memuat data backtest");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRuns();
  }, [fetchRuns]);

  const handleCreate = async () => {
    setError(null);
    try {
      const run = await BacktestAPI.createRun({
        strategy_name: form.strategy_name,
        start_date: form.start_date,
        end_date: form.end_date,
        initial_capital: parseFloat(form.initial_capital),
        instrument_id: form.instrument_id || undefined,
      });
      setRuns((prev) => [run, ...prev]);
      setForm({ ...form, instrument_id: "" });
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal membuat backtest");
    }
  };

  const handleSelectRun = async (run: BacktestRun) => {
    setSelectedRun(run);
    setMetrics(null);
    setTrades([]);
    if (run.status === "COMPLETED") {
      try {
        const [m, t] = await Promise.all([
          BacktestAPI.getRunMetrics(run.run_id),
          BacktestAPI.getRunTrades(run.run_id),
        ]);
        setMetrics(m);
        setTrades(t);
      } catch {
        // metrics may not exist yet
      }
    }
  };

  const fmt = (v: number | string | null, decimals = 2) => {
    return formatNumber(v, decimals);
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Backtesting</h1>
        <p className="text-sm text-muted-foreground"><TermTooltip term="Backtest">Buat dan jalankan</TermTooltip> backtest strategi</p>
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Backtest Baru</CardTitle>
          <CardDescription>Konfigurasi strategi dan rentang tanggal</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Strategi</label>
            <select
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              value={form.strategy_name}
              onChange={(e) => setForm({ ...form, strategy_name: e.target.value })}
            >
              {STRATEGIES.map((s) => (
                <option key={s.value} value={s.value}>{s.label}</option>
              ))}
            </select>
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Tanggal Mulai</label>
            <Input type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Tanggal Akhir</label>
            <Input type="date" value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Modal Awal (Rp)</label>
            <Input type="number" value={form.initial_capital} onChange={(e) => setForm({ ...form, initial_capital: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">ID Instrumen (opsional)</label>
            <Input value={form.instrument_id} onChange={(e) => setForm({ ...form, instrument_id: e.target.value })} placeholder="e.g. inst_bbcaxyz" />
          </div>
          <div className="flex items-end">
            <Button onClick={handleCreate} className="w-full">
              <Play className="mr-2 h-4 w-4" /> Buat Backtest
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-1">
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Daftar Backtest</CardTitle>
              <Button variant="ghost" size="icon" onClick={fetchRuns} disabled={loading}>
                <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-2 max-h-[500px] overflow-y-auto">
            {runs.length === 0 ? (
              <p className="text-sm text-muted-foreground">Belum ada backtest</p>
            ) : (
              runs.map((run) => (
                <button
                  key={run.run_id}
                  onClick={() => handleSelectRun(run)}
                  className={`w-full rounded-md border p-3 text-left transition-colors ${
                    selectedRun?.run_id === run.run_id
                      ? "border-primary bg-primary/5"
                      : "border-border hover:bg-accent"
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">{run.strategy_name}</span>
                    <Badge variant={run.status === "COMPLETED" ? "default" : run.status === "PENDING" ? "secondary" : "destructive"}>
                      {run.status}
                    </Badge>
                  </div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    {run.start_date} → {run.end_date}
                  </div>
                  <div className="text-xs text-muted-foreground">
                    Modal: {formatRupiah(run.initial_capital, 0)}
                  </div>
                </button>
              ))
            )}
          </CardContent>
        </Card>

        <div className="lg:col-span-2 space-y-4">
          {selectedRun ? (
            <>
              <Card>
                <CardHeader>
                  <CardTitle>Detail Backtest</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <span className="text-xs text-muted-foreground">Strategi</span>
                    <p className="text-sm font-medium">{selectedRun.strategy_name}</p>
                  </div>
                  <div>
                    <span className="text-xs text-muted-foreground">Status</span>
                    <p className="text-sm font-medium">{selectedRun.status}</p>
                  </div>
                  <div>
                    <span className="text-xs text-muted-foreground">Modal Awal</span>
                    <p className="text-sm font-medium">{formatRupiah(selectedRun.initial_capital, 0)}</p>
                  </div>
                  <div>
                    <span className="text-xs text-muted-foreground">Modal Akhir</span>
                    <p className="text-sm font-medium">
                      {selectedRun.final_capital ? formatRupiah(selectedRun.final_capital, 0) : "-"}
                    </p>
                  </div>
                </CardContent>
              </Card>

              {metrics && (
                <Card>
                  <CardHeader>
                    <CardTitle>Metrik Performa</CardTitle>
                  </CardHeader>
                  <CardContent className="grid gap-4 sm:grid-cols-3">
                    <MetricCard label="Total Return" value={formatPercent(metrics.total_return)} positive={metrics.total_return >= 0} />
                    <MetricCard label="Return Tahunan" value={formatPercent(metrics.annualized_return)} positive={metrics.annualized_return >= 0} />
                    <MetricCard label={<TermTooltip term="Sharpe">Rasio Sharpe</TermTooltip>} value={fmt(metrics.sharpe_ratio)} positive={metrics.sharpe_ratio > 0} />
                    <MetricCard label={<TermTooltip term="Sortino">Rasio Sortino</TermTooltip>} value={fmt(metrics.sortino_ratio)} positive={metrics.sortino_ratio > 0} />
                    <MetricCard label={<TermTooltip term="Drawdown">Max Drawdown</TermTooltip>} value={formatPercent(metrics.max_drawdown)} negative />
                    <MetricCard label="Win Rate" value={formatPercent(metrics.win_rate, 1)} />
                    <MetricCard label="Profit Factor" value={fmt(metrics.profit_factor)} positive={metrics.profit_factor > 1} />
                    <MetricCard label="Total Transaksi" value={String(metrics.total_trades)} />
                    <MetricCard label="Menang/Kalah" value={`${metrics.winning_trades}/${metrics.losing_trades}`} />
                  </CardContent>
                </Card>
              )}

              {trades.length > 0 && (
                <Card>
                  <CardHeader>
                    <CardTitle>Transaksi ({trades.length})</CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className="max-h-[300px] overflow-y-auto">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Entry</TableHead>
                            <TableHead>Exit</TableHead>
                            <TableHead className="text-right">Jumlah</TableHead>
                            <TableHead className="text-right">Entry</TableHead>
                            <TableHead className="text-right">Exit</TableHead>
                            <TableHead className="text-right">PnL</TableHead>
                            <TableHead className="text-right">PnL %</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {trades.map((t) => (
                            <TableRow key={t.trade_id}>
                              <TableCell className="text-xs">{t.entry_date}</TableCell>
                              <TableCell className="text-xs">{t.exit_date}</TableCell>
                              <TableCell className="text-right">{t.quantity}</TableCell>
                              <TableCell className="text-right">{fmt(t.entry_price)}</TableCell>
                              <TableCell className="text-right">{fmt(t.exit_price)}</TableCell>
                              <TableCell className={`text-right font-medium ${t.pnl >= 0 ? "text-green-600" : "text-red-600"}`}>
                                {fmt(t.pnl, 0)}
                              </TableCell>
                              <TableCell className={`text-right ${t.pnl_pct >= 0 ? "text-green-600" : "text-red-600"}`}>
                                {fmt(t.pnl_pct)}%
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </div>
                  </CardContent>
                </Card>
              )}
            </>
          ) : (
            <Card>
              <CardContent className="flex h-full min-h-[300px] items-center justify-center">
                <div className="text-center">
                  <BarChart3 className="mx-auto h-12 w-12 text-muted-foreground" />
                  <p className="mt-2 text-sm text-muted-foreground">Pilih backtest untuk melihat detail</p>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}

function MetricCard({ label, value, positive, negative }: { label: React.ReactNode; value: string; positive?: boolean; negative?: boolean }) {
  const color = negative ? "text-red-600" : positive === true ? "text-green-600" : positive === false ? "text-red-600" : "";
  return (
    <div className="rounded-md border border-border p-3">
      <span className="text-xs text-muted-foreground">{label}</span>
      <p className={`text-lg font-bold ${color}`}>{value}</p>
    </div>
  );
}
