import { useState, useEffect, useCallback } from "react";
import { MarketCoverageAPI, type MarketCoverage, type MarketTypeCoverage } from "@/lib/api";
import { ApiError } from "@/lib/api";
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
import { RefreshCw, TrendingUp, TrendingDown, Minus, Activity, Globe, Database, Layers } from "lucide-react";

const CAPABILITY_LABELS: Record<string, string> = {
  ohlcv: "OHLCV Data",
  indicators: "Technical Indicators",
  signals: "Signal Generation",
  screening: "Screening Engine",
  valuation: "Valuation (DCF/Relative)",
  trading: "Order Execution",
  portfolio: "Portfolio Management",
  risk: "Risk Assessment",
  backtest: "Backtesting",
  regime: "Market Regime",
  factors: "Factor Matrix",
  nav_tracking: "NAV Tracking",
  yield_tracking: "Yield Tracking",
  greeks: "Greeks Calculation",
};

const ASSET_CLASS_COLORS: Record<string, string> = {
  EQUITY: "bg-blue-500/10 text-blue-700 border-blue-500/20",
  INDEX: "bg-purple-500/10 text-purple-700 border-purple-500/20",
  COMMODITY: "bg-amber-500/10 text-amber-700 border-amber-500/20",
  CURRENCY: "bg-green-500/10 text-green-700 border-green-500/20",
  FIXED_INCOME: "bg-cyan-500/10 text-cyan-700 border-cyan-500/20",
  CRYPTO: "bg-orange-500/10 text-orange-700 border-orange-500/20",
  DERIVATIVE: "bg-red-500/10 text-red-700 border-red-500/20",
  MIXED: "bg-indigo-500/10 text-indigo-700 border-indigo-500/20",
};

function DirectionIcon({ direction }: { direction: string }) {
  if (direction === "BULLISH") return <TrendingUp className="h-4 w-4 text-green-600" />;
  if (direction === "BEARISH") return <TrendingDown className="h-4 w-4 text-red-600" />;
  return <Minus className="h-4 w-4 text-muted-foreground" />;
}

export default function MarketCoveragePage() {
  const [coverage, setCoverage] = useState<MarketCoverage | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await MarketCoverageAPI.getCoverage();
      setCoverage(data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Failed to load market coverage");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const fmt = (v: number | string | null | undefined, decimals = 0) => {
    if (v === null || v === undefined) return "-";
    return Number(v).toLocaleString("id-ID", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  };

  const activeMarketTypes = coverage?.market_types.filter((m) => m.is_active) ?? [];
  const liveDataTypes = coverage?.market_types.filter((m) => m.has_live_data) ?? [];
  const typesWithSignals = coverage?.market_types.filter((m) => m.signals && m.signals.signal_count > 0) ?? [];
  const typesWithPositions = coverage?.market_types.filter((m) => m.positions && m.positions.position_count > 0) ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Market Coverage</h1>
          <p className="text-sm text-muted-foreground">
            Jenis pasar modal yang didukung aplikasi & aktivitas yang sedang berjalan
          </p>
        </div>
        <button
          onClick={fetchData}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm hover:bg-accent disabled:opacity-50"
        >
          <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} /> Refresh
        </button>
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {/* Summary Cards */}
      {coverage && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Layers className="h-5 w-5 text-muted-foreground" />
                <span className="text-sm text-muted-foreground">Supported Types</span>
              </div>
              <p className="mt-2 text-2xl font-bold">{coverage.summary.total_supported_types}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Activity className="h-5 w-5 text-green-600" />
                <span className="text-sm text-muted-foreground">Active Markets</span>
              </div>
              <p className="mt-2 text-2xl font-bold text-green-600">{coverage.summary.active_market_types}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Database className="h-5 w-5 text-blue-600" />
                <span className="text-sm text-muted-foreground">Live Data</span>
              </div>
              <p className="mt-2 text-2xl font-bold text-blue-600">{coverage.summary.instruments_with_live_data}</p>
              <p className="text-xs text-muted-foreground">asset classes with OHLCV</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Globe className="h-5 w-5 text-purple-600" />
                <span className="text-sm text-muted-foreground">Instruments</span>
              </div>
              <p className="mt-2 text-2xl font-bold">{coverage.summary.total_instruments}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Globe className="h-5 w-5 text-amber-600" />
                <span className="text-sm text-muted-foreground">Exchanges</span>
              </div>
              <p className="mt-2 text-2xl font-bold">{coverage.summary.total_exchanges}</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Market Type Cards */}
      {coverage && (
        <div>
          <h2 className="mb-4 text-lg font-semibold">Jenis Pasar Modal yang Didukung</h2>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {coverage.market_types.map((mt: MarketTypeCoverage) => (
              <Card key={`${mt.asset_class}-${mt.instrument_type}`} className={mt.is_active ? "" : "opacity-60"}>
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <div>
                      <CardTitle className="text-base">{mt.market_name}</CardTitle>
                      <CardDescription className="mt-1 text-xs">{mt.description}</CardDescription>
                    </div>
                    <Badge
                      variant="outline"
                      className={`shrink-0 ${ASSET_CLASS_COLORS[mt.asset_class] ?? ""}`}
                    >
                      {mt.asset_class}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex flex-wrap gap-1">
                    {mt.capabilities.map((cap) => (
                      <Badge key={cap} variant="secondary" className="text-xs">
                        {CAPABILITY_LABELS[cap] ?? cap}
                      </Badge>
                    ))}
                  </div>
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <span className="text-muted-foreground">Instruments</span>
                      <p className="font-semibold">{mt.instrument_count}</p>
                    </div>
                    <div>
                      <span className="text-muted-foreground">Data Source</span>
                      <p className="font-semibold">{mt.data_source}</p>
                    </div>
                    {mt.ohlcv && (
                      <div>
                        <span className="text-muted-foreground">OHLCV Records</span>
                        <p className="font-semibold">{fmt(mt.ohlcv.total_records)}</p>
                      </div>
                    )}
                    {mt.ohlcv && (
                      <div>
                        <span className="text-muted-foreground">Data Range</span>
                        <p className="font-semibold text-xs">
                          {mt.ohlcv.earliest_date} → {mt.ohlcv.latest_date}
                        </p>
                      </div>
                    )}
                    {mt.signals && (
                      <div>
                        <span className="text-muted-foreground">Active Signals</span>
                        <p className="font-semibold text-green-600">{mt.signals.signal_count}</p>
                      </div>
                    )}
                    {mt.recommendations && (
                      <div>
                        <span className="text-muted-foreground">Recommendations</span>
                        <p className="font-semibold text-blue-600">{mt.recommendations.rec_count}</p>
                      </div>
                    )}
                    {mt.positions && (
                      <div>
                        <span className="text-muted-foreground">Positions</span>
                        <p className="font-semibold">{mt.positions.position_count}</p>
                      </div>
                    )}
                  </div>
                  <div className="flex gap-2 pt-1">
                    {mt.is_active ? (
                      <Badge className="bg-green-600 text-xs">Active</Badge>
                    ) : (
                      <Badge variant="outline" className="text-xs">Inactive</Badge>
                    )}
                    {mt.has_live_data && (
                      <Badge className="bg-blue-600 text-xs">Live Data</Badge>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}

      {/* Active Decisions Section */}
      {coverage && (
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Signals */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="h-5 w-5" />
                Sinyal Aktif Terbaru
              </CardTitle>
              <CardDescription>Keputusan aplikasi berdasarkan analisa teknikal</CardDescription>
            </CardHeader>
            <CardContent>
              {coverage.recent_signals.length === 0 ? (
                <p className="text-sm text-muted-foreground">Belum ada sinyal aktif</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Market</TableHead>
                      <TableHead>Ticker</TableHead>
                      <TableHead>Type</TableHead>
                      <TableHead>Direction</TableHead>
                      <TableHead className="text-right">Strength</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {coverage.recent_signals.map((sig: any, i: number) => (
                      <TableRow key={sig.signal_id || i}>
                        <TableCell className="text-xs">
                          <Badge variant="outline" className={ASSET_CLASS_COLORS[sig.asset_class] ?? ""}>
                            {sig.asset_class}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{sig.ticker || "-"}</TableCell>
                        <TableCell className="text-xs">{sig.signal_type}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <DirectionIcon direction={sig.direction} />
                            <span className="text-xs">{sig.direction}</span>
                          </div>
                        </TableCell>
                        <TableCell className="text-right font-semibold">{sig.strength}%</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          {/* Recent Recommendations */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingUp className="h-5 w-5" />
                Rekomendasi Terbaru
              </CardTitle>
              <CardDescription>Keputusan aplikasi: BUY / HOLD / SELL</CardDescription>
            </CardHeader>
            <CardContent>
              {coverage.recent_recommendations.length === 0 ? (
                <p className="text-sm text-muted-foreground">Belum ada rekomendasi</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Market</TableHead>
                      <TableHead>Ticker</TableHead>
                      <TableHead>Action</TableHead>
                      <TableHead>Confidence</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {coverage.recent_recommendations.map((rec: any, i: number) => (
                      <TableRow key={rec.recommendation_id || i}>
                        <TableCell className="text-xs">
                          <Badge variant="outline" className={ASSET_CLASS_COLORS[rec.asset_class] ?? ""}>
                            {rec.asset_class}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{rec.ticker || rec.short_name || "-"}</TableCell>
                        <TableCell>
                          <Badge
                            variant={rec.action === "BUY" ? "default" : rec.action === "SELL" ? "destructive" : "secondary"}
                            className="text-xs"
                          >
                            {rec.action}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs">
                          {rec.confidence ? `${rec.confidence}%` : "-"}
                          {rec.confidence_level && (
                            <span className="ml-1 text-muted-foreground">({rec.confidence_level})</span>
                          )}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-xs">{rec.status}</Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* Exchanges */}
      {coverage && coverage.exchanges.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Globe className="h-5 w-5" />
              Bursa yang Didukung
            </CardTitle>
            <CardDescription>Exchange connections untuk trading dan data</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {coverage.exchanges.map((ex) => (
                <div key={ex.mic_code} className="flex items-center justify-between rounded-lg border p-3">
                  <div>
                    <p className="text-sm font-medium">{ex.name}</p>
                    <p className="text-xs text-muted-foreground">{ex.mic_code} · {ex.country}</p>
                  </div>
                  <div className="flex gap-2">
                    <Badge variant="outline" className="text-xs">{ex.currency}</Badge>
                    <Badge className="bg-green-600 text-xs">{ex.status}</Badge>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Activity Summary */}
      {coverage && (
        <Card>
          <CardHeader>
            <CardTitle>Ringkasan Aktivitas Pasar</CardTitle>
            <CardDescription>
              Pasar yang sedang aktif di-aktivitaskan berdasarkan keputusan aplikasi
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">Pasar dengan Live Data</p>
                <p className="mt-1 text-xl font-bold text-blue-600">{liveDataTypes.length}</p>
                <div className="mt-2 flex flex-wrap gap-1">
                  {liveDataTypes.map((m) => (
                    <Badge key={`${m.asset_class}-${m.instrument_type}`} variant="secondary" className="text-xs">
                      {m.asset_class}
                    </Badge>
                  ))}
                </div>
              </div>
              <div className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">Pasar dengan Sinyal Aktif</p>
                <p className="mt-1 text-xl font-bold text-green-600">{typesWithSignals.length}</p>
                <div className="mt-2 flex flex-wrap gap-1">
                  {typesWithSignals.map((m) => (
                    <Badge key={`${m.asset_class}-${m.instrument_type}`} variant="secondary" className="text-xs">
                      {m.asset_class}
                    </Badge>
                  ))}
                </div>
              </div>
              <div className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">Pasar dengan Posisi Portfolio</p>
                <p className="mt-1 text-xl font-bold text-purple-600">{typesWithPositions.length}</p>
                <div className="mt-2 flex flex-wrap gap-1">
                  {typesWithPositions.map((m) => (
                    <Badge key={`${m.asset_class}-${m.instrument_type}`} variant="secondary" className="text-xs">
                      {m.asset_class}
                    </Badge>
                  ))}
                </div>
              </div>
              <div className="rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">Total Pasar Aktif</p>
                <p className="mt-1 text-xl font-bold">{activeMarketTypes.length}</p>
                <p className="text-xs text-muted-foreground">dari {coverage.summary.total_supported_types} jenis didukung</p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
