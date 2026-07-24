import { useState, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  api,
  AnalyticsAPI,
  type Instrument,
  type Signal,
  type Recommendation,
  type Forecast,
  type Score,
  type NewsItem,
  type AllIndicators,
  type MarketRegime,
  type CompositeScore,
  type BidAskSpread,
  type OrderBookDepth,
  type LiquidityScore,
  type RupiahPressureScore,
  type FlowConfirmationScore,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from "@/components/ui/table";
import {
  ArrowLeft,
  RefreshCw,
  AlertCircle,
  TrendingUp,
  TrendingDown,
  Newspaper,
  Activity,
  Gauge,
  BarChart3,
  Layers,
  DollarSign,
  Waves,
  Shield,
} from "lucide-react";

type Tab =
  | "overview"
  | "indicators"
  | "regime"
  | "composite"
  | "microstructure"
  | "factors"
  | "signals"
  | "recommendations"
  | "forecasts"
  | "news";

export default function StockDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [instrument, setInstrument] = useState<Instrument | null>(null);
  const [signals, setSignals] = useState<Signal[]>([]);
  const [recommendations, setRecommendations] = useState<Recommendation[]>([]);
  const [forecasts, setForecasts] = useState<Forecast[]>([]);
  const [scores, setScores] = useState<Score[]>([]);
  const [news, setNews] = useState<NewsItem[]>([]);
  const [indicators, setIndicators] = useState<AllIndicators | null>(null);
  const [regime, setRegime] = useState<MarketRegime | null>(null);
  const [composite, setComposite] = useState<CompositeScore | null>(null);
  const [bidAsk, setBidAsk] = useState<BidAskSpread | null>(null);
  const [orderBook, setOrderBook] = useState<OrderBookDepth | null>(null);
  const [liquidity, setLiquidity] = useState<LiquidityScore | null>(null);
  const [rupiahPressure, setRupiahPressure] = useState<RupiahPressureScore | null>(null);
  const [flowConfirm, setFlowConfirm] = useState<FlowConfirmationScore | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [tab, setTab] = useState<Tab>("overview");

  async function fetchData() {
    if (!id) return;
    setLoading(true);
    setError("");
    try {
      const results = await Promise.allSettled([
        api.get<Instrument>(`/instruments/${id}`),
        api.get<Signal[]>(`/instruments/${id}/signals?per_page=10`),
        api.get<Recommendation[]>(`/instruments/${id}/recommendations?per_page=10`),
        api.get<Forecast[]>(`/instruments/${id}/forecasts?per_page=10`),
        api.get<Score[]>(`/instruments/${id}/scores?per_page=10`),
        api.get<NewsItem[]>(`/instruments/${id}/news?per_page=10`),
        api.get<AllIndicators>(`/instruments/${id}/indicators`),
        api.get<MarketRegime>(`/instruments/${id}/regime`),
        api.get<CompositeScore>(`/instruments/${id}/composite-score`),
        AnalyticsAPI.getBidAskSpread(id),
        AnalyticsAPI.getOrderBook(id),
        AnalyticsAPI.getLiquidityScore(id),
        AnalyticsAPI.getRupiahPressure(),
        AnalyticsAPI.getFlowConfirmation(),
      ]);

      if (results[0].status === "fulfilled") setInstrument(results[0].value);
      if (results[1].status === "fulfilled") setSignals(results[1].value || []);
      if (results[2].status === "fulfilled") setRecommendations(results[2].value || []);
      if (results[3].status === "fulfilled") setForecasts(results[3].value || []);
      if (results[4].status === "fulfilled") setScores(results[4].value || []);
      if (results[5].status === "fulfilled") setNews(results[5].value || []);
      if (results[6].status === "fulfilled") setIndicators(results[6].value);
      if (results[7].status === "fulfilled") setRegime(results[7].value);
      if (results[8].status === "fulfilled") setComposite(results[8].value);
      if (results[9].status === "fulfilled") setBidAsk(results[9].value);
      if (results[10].status === "fulfilled") setOrderBook(results[10].value);
      if (results[11].status === "fulfilled") setLiquidity(results[11].value);
      if (results[12].status === "fulfilled") setRupiahPressure(results[12].value);
      if (results[13].status === "fulfilled") setFlowConfirm(results[13].value);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load data";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchData();
  }, [id]);

  const tabs: { key: Tab; label: string }[] = [
    { key: "overview", label: "Overview" },
    { key: "indicators", label: "Indicators" },
    { key: "regime", label: "Regime" },
    { key: "composite", label: "Composite" },
    { key: "microstructure", label: "Microstructure" },
    { key: "factors", label: "Factors" },
    { key: "signals", label: `Signals (${signals.length})` },
    { key: "recommendations", label: `Recs (${recommendations.length})` },
    { key: "forecasts", label: `Forecasts (${forecasts.length})` },
    { key: "news", label: `News (${news.length})` },
  ];

  const regimeColor = (r: string) =>
    r === "BULL" ? "success" : r === "BEAR" ? "destructive" : "secondary";

  const recColor = (r: string) =>
    r === "BUY" || r === "ACCUMULATE" ? "success" :
      r === "SELL" || r === "REDUCE" ? "destructive" : "secondary";

  const gradeColor = (g: string) =>
    g === "A" || g === "B" ? "success" :
      g === "D" || g === "F" ? "destructive" : "secondary";

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon" onClick={() => navigate("/instruments")}>
          <ArrowLeft className="h-5 w-5" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">
            {instrument?.symbol || "Loading..."}
          </h1>
          <p className="text-sm text-muted-foreground">{instrument?.name}</p>
        </div>
        {regime && (
          <Badge variant={regimeColor(regime.regime)} className="mr-2">
            {regime.regime}
          </Badge>
        )}
        {composite && (
          <Badge variant={recColor(composite.recommendation)} className="mr-2">
            {composite.recommendation} ({composite.composite_score})
          </Badge>
        )}
        <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* Stats row */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Asset Class</p>
            <p className="text-lg font-bold">{instrument?.asset_class || "—"}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Sector</p>
            <p className="text-lg font-bold">{instrument?.sector || "—"}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Currency</p>
            <p className="text-lg font-bold">{instrument?.currency || "—"}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-sm text-muted-foreground">Status</p>
            <Badge variant={instrument?.status === "ACTIVE" ? "success" : "outline"}>
              {instrument?.status || "—"}
            </Badge>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b border-border pb-2">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${tab === t.key
              ? "bg-primary text-primary-foreground"
              : "text-muted-foreground hover:bg-accent"
              }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Overview Tab */}
      {tab === "overview" && (
        <div className="grid gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Latest Scores</CardTitle>
              <CardDescription>Composite scoring for this instrument</CardDescription>
            </CardHeader>
            <CardContent>
              {scores.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">No scores available</p>
              ) : (
                <div className="space-y-3">
                  {scores.map((s) => (
                    <div key={s.score_id} className="flex items-center justify-between">
                      <span className="text-sm">{s.score_type}</span>
                      <span className="font-medium">{Number(s.value).toFixed(2)}</span>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Recent Signals</CardTitle>
              <CardDescription>Latest trading signals</CardDescription>
            </CardHeader>
            <CardContent>
              {signals.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">No signals available</p>
              ) : (
                <div className="space-y-3">
                  {signals.slice(0, 5).map((sig) => (
                    <div key={sig.signal_id} className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        {sig.direction === "BULLISH" ? (
                          <TrendingUp className="h-4 w-4 text-green-500" />
                        ) : (
                          <TrendingDown className="h-4 w-4 text-red-500" />
                        )}
                        <span className="text-sm">{sig.signal_type}</span>
                      </div>
                      <Badge variant={sig.direction === "BULLISH" ? "success" : "destructive"}>
                        {sig.direction}
                      </Badge>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      )}

      {/* Technical Indicators Tab */}
      {tab === "indicators" && indicators && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">SMA 20</p>
                    <p className="text-xl font-bold">{indicators.sma_20.latest ?? "—"}</p>
                  </div>
                  <Activity className="h-6 w-6 text-blue-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">SMA 50</p>
                    <p className="text-xl font-bold">{indicators.sma_50.latest ?? "—"}</p>
                  </div>
                  <Activity className="h-6 w-6 text-purple-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">RSI 14</p>
                    <p className="text-xl font-bold">{indicators.rsi_14.latest ?? "—"}</p>
                    {indicators.rsi_14.signal && (
                      <Badge variant={indicators.rsi_14.signal === "OVERSOLD" ? "success" : indicators.rsi_14.signal === "OVERBOUGHT" ? "destructive" : "secondary"} className="mt-1">
                        {indicators.rsi_14.signal}
                      </Badge>
                    )}
                  </div>
                  <Gauge className="h-6 w-6 text-orange-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">ADX 14</p>
                    <p className="text-xl font-bold">{indicators.adx_14.latest ?? "—"}</p>
                    {indicators.adx_14.trend_strength && (
                      <Badge variant="secondary" className="mt-1">{indicators.adx_14.trend_strength}</Badge>
                    )}
                  </div>
                  <BarChart3 className="h-6 w-6 text-cyan-500" />
                </div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>MACD</CardTitle>
              <CardDescription>Moving Average Convergence Divergence</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-3">
                <div>
                  <p className="text-sm text-muted-foreground">MACD Line</p>
                  <p className="text-lg font-bold">{indicators.macd.latest_macd ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Signal Line</p>
                  <p className="text-lg font-bold">{indicators.macd.latest_signal ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Trend</p>
                  <Badge variant={indicators.macd.trend === "BULLISH" ? "success" : indicators.macd.trend === "BEARISH" ? "destructive" : "secondary"}>
                    {indicators.macd.trend}
                  </Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Bollinger Bands</CardTitle>
              <CardDescription>20-period, 2 standard deviations</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-4">
                <div>
                  <p className="text-sm text-muted-foreground">Upper</p>
                  <p className="text-lg font-bold">{indicators.bollinger_bands.latest_upper ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Middle</p>
                  <p className="text-lg font-bold">{indicators.bollinger_bands.latest_middle ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Lower</p>
                  <p className="text-lg font-bold">{indicators.bollinger_bands.latest_lower ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Bandwidth</p>
                  <p className="text-lg font-bold">{indicators.bollinger_bands.bandwidth ?? "—"}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>ATR (14)</CardTitle>
                <CardDescription>Average True Range — volatility measure</CardDescription>
              </CardHeader>
              <CardContent>
                <p className="text-2xl font-bold">{indicators.atr_14.latest ?? "—"}</p>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Support / Resistance</CardTitle>
                <CardDescription>Detected pivot levels</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 sm:grid-cols-3">
                  <div>
                    <p className="text-sm text-muted-foreground">Current Price</p>
                    <p className="text-lg font-bold">{indicators.support_resistance.current_price ?? "—"}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Support Levels</p>
                    {indicators.support_resistance.support.length > 0 ? (
                      indicators.support_resistance.support.slice(0, 3).map((s, i) => (
                        <p key={i} className="text-sm font-medium text-green-500">{s}</p>
                      ))
                    ) : <p className="text-sm text-muted-foreground">—</p>}
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Resistance Levels</p>
                    {indicators.support_resistance.resistance.length > 0 ? (
                      indicators.support_resistance.resistance.slice(0, 3).map((r, i) => (
                        <p key={i} className="text-sm font-medium text-red-500">{r}</p>
                      ))
                    ) : <p className="text-sm text-muted-foreground">—</p>}
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Trend Identification</CardTitle>
              <CardDescription>SMA crossover analysis</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 sm:grid-cols-3">
                <div>
                  <p className="text-sm text-muted-foreground">Trend</p>
                  <Badge variant={indicators.trend.trend === "UPTREND" ? "success" : indicators.trend.trend === "DOWNTREND" ? "destructive" : "secondary"}>
                    {indicators.trend.trend}
                  </Badge>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Short SMA (20)</p>
                  <p className="text-lg font-bold">{indicators.trend.short_sma ?? "—"}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Long SMA (50)</p>
                  <p className="text-lg font-bold">{indicators.trend.long_sma ?? "—"}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Market Regime Tab */}
      {tab === "regime" && regime && (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="p-4">
                <p className="text-sm text-muted-foreground">Regime</p>
                <Badge variant={regimeColor(regime.regime)} className="mt-1 text-base">
                  {regime.regime}
                </Badge>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <p className="text-sm text-muted-foreground">Sub-Regime</p>
                <Badge variant={regime.sub_regime === "HIGH_VOLATILITY" ? "destructive" : "secondary"} className="mt-1">
                  {regime.sub_regime}
                </Badge>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <p className="text-sm text-muted-foreground">Risk Appetite</p>
                <Badge variant={regime.risk_appetite === "RISK_ON" ? "success" : regime.risk_appetite === "RISK_OFF" ? "destructive" : "secondary"} className="mt-1">
                  {regime.risk_appetite}
                </Badge>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <p className="text-sm text-muted-foreground">Confidence</p>
                <p className="text-xl font-bold">{(regime.confidence * 100).toFixed(0)}%</p>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Regime Details</CardTitle>
              <CardDescription>Underlying indicators driving regime classification</CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Indicator</TableHead>
                    <TableHead>Value</TableHead>
                    <TableHead>Signal</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow>
                    <TableCell>ADX (14)</TableCell>
                    <TableCell>{regime.details.adx ?? "—"}</TableCell>
                    <TableCell><Badge variant="secondary">{regime.details.adx_strength}</Badge></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>RSI (14)</TableCell>
                    <TableCell>{regime.details.rsi ?? "—"}</TableCell>
                    <TableCell><Badge variant={regime.details.rsi_signal === "OVERSOLD" ? "success" : regime.details.rsi_signal === "OVERBOUGHT" ? "destructive" : "secondary"}>{regime.details.rsi_signal}</Badge></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>ATR (14)</TableCell>
                    <TableCell>{regime.details.atr ?? "—"}</TableCell>
                    <TableCell><Badge variant="secondary">{regime.volatility}</Badge></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>Bollinger Bandwidth</TableCell>
                    <TableCell>{regime.details.bollinger_bandwidth ?? "—"}</TableCell>
                    <TableCell>—</TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>Trend</TableCell>
                    <TableCell>{regime.trend}</TableCell>
                    <TableCell><Badge variant={regime.trend === "UPTREND" ? "success" : regime.trend === "DOWNTREND" ? "destructive" : "secondary"}>{regime.trend}</Badge></TableCell>
                  </TableRow>
                  <TableRow>
                    <TableCell>Volatility %</TableCell>
                    <TableCell>{regime.volatility_pct ?? "—"}</TableCell>
                    <TableCell><Badge variant={regime.volatility === "HIGH" ? "destructive" : "secondary"}>{regime.volatility}</Badge></TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Composite Score Tab */}
      {tab === "composite" && composite && (
        <div className="space-y-6">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-muted-foreground">Composite Score</p>
                  <p className="text-4xl font-bold">{composite.composite_score}</p>
                </div>
                <div className="text-right">
                  <p className="text-sm text-muted-foreground">Recommendation</p>
                  <Badge variant={recColor(composite.recommendation)} className="text-base">
                    {composite.recommendation}
                  </Badge>
                  <p className="mt-2 text-sm text-muted-foreground">
                    Confidence: <span className="font-medium">{composite.confidence}</span>
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Score Dimensions</CardTitle>
              <CardDescription>Weighted aggregation across 7 dimensions</CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Dimension</TableHead>
                    <TableHead>Score</TableHead>
                    <TableHead>Grade</TableHead>
                    <TableHead>Weight</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {Object.entries(composite.dimensions).map(([key, dim]) => (
                    <TableRow key={key}>
                      <TableCell className="font-medium">{key}</TableCell>
                      <TableCell>{dim.score !== null ? dim.score.toFixed(2) : "—"}</TableCell>
                      <TableCell>
                        <Badge variant={gradeColor(dim.grade)}>{dim.grade}</Badge>
                      </TableCell>
                      <TableCell>{dim.weight}%</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Market Microstructure Tab */}
      {tab === "microstructure" && (
        <div className="space-y-6">
          {/* Bid/Ask Spread & Liquidity Score */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">Spread</p>
                    <p className="text-xl font-bold">{bidAsk?.spread !== null && bidAsk?.spread !== undefined ? Number(bidAsk.spread).toFixed(2) : "—"}</p>
                    {bidAsk?.classification && (
                      <Badge variant={bidAsk.classification === "TIGHT" ? "success" : bidAsk.classification === "WIDE" ? "destructive" : "secondary"} className="mt-1">
                        {bidAsk.classification}
                      </Badge>
                    )}
                  </div>
                  <DollarSign className="h-6 w-6 text-green-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">Spread %</p>
                    <p className="text-xl font-bold">{bidAsk?.spread_pct !== null && bidAsk?.spread_pct !== undefined ? Number(bidAsk.spread_pct).toFixed(3) + "%" : "—"}</p>
                  </div>
                  <Activity className="h-6 w-6 text-blue-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">Liquidity Score</p>
                    <p className="text-xl font-bold">{liquidity?.liquidity_score ?? "—"}</p>
                    {liquidity?.grade && (
                      <Badge variant={liquidity.grade === "A" || liquidity.grade === "B" ? "success" : liquidity.grade === "D" || liquidity.grade === "F" ? "destructive" : "secondary"} className="mt-1">
                        Grade: {liquidity.grade}
                      </Badge>
                    )}
                  </div>
                  <Waves className="h-6 w-6 text-cyan-500" />
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-muted-foreground">Avg Daily Volume</p>
                    <p className="text-xl font-bold">{liquidity?.avg_daily_volume ? Number(liquidity.avg_daily_volume).toLocaleString() : "—"}</p>
                  </div>
                  <BarChart3 className="h-6 w-6 text-purple-500" />
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Liquidity Details */}
          {liquidity && (
            <Card>
              <CardHeader>
                <CardTitle>Liquidity Analysis</CardTitle>
                <CardDescription>Volume consistency and price stability breakdown</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 sm:grid-cols-3">
                  <div>
                    <p className="text-sm text-muted-foreground">Volume Consistency</p>
                    <p className="text-lg font-bold">{Number(liquidity.volume_consistency).toFixed(2)}%</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Price Stability</p>
                    <p className="text-lg font-bold">{Number(liquidity.price_stability).toFixed(2)}%</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Avg Daily Value</p>
                    <p className="text-lg font-bold">{Number(liquidity.avg_daily_value).toLocaleString()}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Order Book Depth */}
          {orderBook && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Layers className="h-5 w-5" />
                  Order Book Depth
                </CardTitle>
                <CardDescription>
                  Simulated order book — Imbalance: {Number(orderBook.imbalance_pct).toFixed(2)}% ({orderBook.imbalance > 0 ? "Buy-heavy" : "Sell-heavy"})
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Level</TableHead>
                      <TableHead>Bid Price</TableHead>
                      <TableHead>Bid Volume</TableHead>
                      <TableHead>Ask Price</TableHead>
                      <TableHead>Ask Volume</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {orderBook.levels.map((lvl) => (
                      <TableRow key={lvl.level}>
                        <TableCell className="font-medium">{lvl.level}</TableCell>
                        <TableCell className="text-green-600">{Number(lvl.bid_price).toFixed(2)}</TableCell>
                        <TableCell>{Number(lvl.bid_volume).toLocaleString()}</TableCell>
                        <TableCell className="text-red-600">{Number(lvl.ask_price).toFixed(2)}</TableCell>
                        <TableCell>{Number(lvl.ask_volume).toLocaleString()}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                <div className="border-t border-border p-4 grid gap-4 sm:grid-cols-2">
                  <div>
                    <p className="text-sm text-muted-foreground">Total Bid Volume</p>
                    <p className="font-bold text-green-600">{Number(orderBook.total_bid_volume).toLocaleString()}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Total Ask Volume</p>
                    <p className="font-bold text-red-600">{Number(orderBook.total_ask_volume).toLocaleString()}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {!bidAsk && !orderBook && !liquidity && (
            <Card>
              <CardContent className="py-8 text-center text-sm text-muted-foreground">
                No microstructure data available
              </CardContent>
            </Card>
          )}
        </div>
      )}

      {/* Market Factor Matrix Tab */}
      {tab === "factors" && (
        <div className="space-y-6">
          {/* Rupiah Pressure Score */}
          {rupiahPressure && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Shield className="h-5 w-5" />
                  Rupiah Pressure Score
                </CardTitle>
                <CardDescription>Multi-component IDR strength assessment</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <p className="text-3xl font-bold">{Number(rupiahPressure.score).toFixed(1)}</p>
                    <Badge variant={rupiahPressure.grade === "STRONG" ? "success" : rupiahPressure.grade === "WEAK" ? "destructive" : "secondary"} className="mt-1">
                      {rupiahPressure.grade}
                    </Badge>
                  </div>
                  <p className="text-sm text-muted-foreground max-w-md text-right">{rupiahPressure.interpretation}</p>
                </div>
                {rupiahPressure.components && Object.keys(rupiahPressure.components).length > 0 && (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Component</TableHead>
                        <TableHead>Value</TableHead>
                        <TableHead>Score</TableHead>
                        <TableHead>Impact</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {Object.entries(rupiahPressure.components).map(([key, comp]) => (
                        <TableRow key={key}>
                          <TableCell className="font-medium">{key}</TableCell>
                          <TableCell>{Number(comp.value).toFixed(2)}</TableCell>
                          <TableCell>{Number(comp.score).toFixed(1)}</TableCell>
                          <TableCell>
                            <Badge variant={comp.impact === "POSITIVE" ? "success" : comp.impact === "NEGATIVE" ? "destructive" : "secondary"}>
                              {comp.impact}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          )}

          {/* Flow Confirmation Score */}
          {flowConfirm && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <TrendingUp className="h-5 w-5" />
                  Flow Confirmation Score
                </CardTitle>
                <CardDescription>Volume trend and smart money flow detection</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 sm:grid-cols-4">
                  <div>
                    <p className="text-sm text-muted-foreground">Score</p>
                    <p className="text-2xl font-bold">{Number(flowConfirm.score).toFixed(1)}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Grade</p>
                    <Badge variant={flowConfirm.grade === "CONFIRMED" ? "success" : flowConfirm.grade === "DIVERGENCE" ? "destructive" : "secondary"} className="text-base">
                      {flowConfirm.grade}
                    </Badge>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Volume Trend</p>
                    <Badge variant="secondary">{flowConfirm.volume_trend}</Badge>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Smart Money Flow</p>
                    <Badge variant={flowConfirm.smart_money_flow === "INFLOW" ? "success" : flowConfirm.smart_money_flow === "OUTFLOW" ? "destructive" : "secondary"}>
                      {flowConfirm.smart_money_flow}
                    </Badge>
                  </div>
                </div>
                <p className="mt-4 text-sm text-muted-foreground">{flowConfirm.interpretation}</p>
              </CardContent>
            </Card>
          )}

          {!rupiahPressure && !flowConfirm && (
            <Card>
              <CardContent className="py-8 text-center text-sm text-muted-foreground">
                No factor matrix data available
              </CardContent>
            </Card>
          )}
        </div>
      )}

      {/* Signals Tab */}
      {tab === "signals" && (
        <Card>
          <CardContent className="p-0">
            <div className="divide-y divide-border">
              {signals.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">No signals available</p>
              ) : (
                signals.map((sig) => (
                  <div key={sig.signal_id} className="flex items-center justify-between p-4">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <Badge variant={sig.direction === "BULLISH" ? "success" : "destructive"}>
                          {sig.direction}
                        </Badge>
                        <span className="font-medium">{sig.signal_type}</span>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {sig.timeframe} · {sig.model_version} · {sig.created_at}
                      </p>
                    </div>
                    <span className="font-medium">{Number(sig.strength).toFixed(1)}%</span>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {tab === "recommendations" && (
        <Card>
          <CardContent className="p-0">
            <div className="divide-y divide-border">
              {recommendations.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">No recommendations available</p>
              ) : (
                recommendations.map((rec) => (
                  <div key={rec.recommendation_id} className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Badge
                          variant={
                            rec.action === "BUY" ? "success" :
                              rec.action === "SELL" ? "destructive" : "secondary"
                          }
                        >
                          {rec.action}
                        </Badge>
                        <span className="text-sm">
                          Confidence: {Number(rec.confidence).toFixed(1)}%
                        </span>
                      </div>
                      {rec.target_price && (
                        <span className="text-sm text-muted-foreground">
                          Target: {rec.target_price}
                        </span>
                      )}
                    </div>
                    {rec.reasoning && (
                      <p className="mt-2 text-sm text-muted-foreground">{rec.reasoning}</p>
                    )}
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {tab === "forecasts" && (
        <Card>
          <CardContent className="p-0">
            <div className="divide-y divide-border">
              {forecasts.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">No forecasts available</p>
              ) : (
                forecasts.map((fc) => (
                  <div key={fc.forecast_id} className="flex items-center justify-between p-4">
                    <div className="space-y-1">
                      <span className="font-medium">{fc.model_name}</span>
                      <p className="text-xs text-muted-foreground">
                        Horizon: {fc.horizon} · {fc.created_at}
                      </p>
                    </div>
                    <div className="text-right">
                      <Badge variant={fc.direction === "UP" ? "success" : "destructive"}>
                        {fc.direction}
                      </Badge>
                      <p className="mt-1 text-sm">
                        Expected: {Number(fc.expected_return).toFixed(2)}%
                      </p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {tab === "news" && (
        <Card>
          <CardContent className="p-0">
            <div className="divide-y divide-border">
              {news.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">No news available</p>
              ) : (
                news.map((n) => (
                  <div key={n.news_id} className="p-4">
                    <div className="flex items-start gap-3">
                      <Newspaper className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                      <div className="flex-1">
                        <p className="font-medium">{n.title}</p>
                        <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                          {n.source && <span>{n.source}</span>}
                          <span>{n.published_at}</span>
                          {n.sentiment && (
                            <Badge
                              variant={
                                n.sentiment === "POSITIVE" ? "success" :
                                  n.sentiment === "NEGATIVE" ? "destructive" : "secondary"
                              }
                            >
                              {n.sentiment}
                            </Badge>
                          )}
                        </div>
                        {n.summary && (
                          <p className="mt-2 text-sm text-muted-foreground">{n.summary}</p>
                        )}
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
