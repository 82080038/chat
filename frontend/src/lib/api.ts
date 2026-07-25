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
    credentials: "include",
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
  getPaginated: async <T>(path: string): Promise<{ data: T[]; meta?: PaginationMeta }> => {
    const res = await fetch(`${API_BASE}${path}`, {
      headers: await buildHeaders(),
      credentials: "include",
    });
    const text = await res.text();
    const json = text ? JSON.parse(text) : null;
    if (!res.ok) {
      const err = json?.error || {};
      throw new ApiError(res.status, err.code || "UNKNOWN", err.message || `HTTP ${res.status}`, err.field_errors);
    }
    return { data: json?.data ?? [], meta: json?.meta };
  },
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
  patch: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: "PATCH",
      body: body ? JSON.stringify(body) : undefined,
    }),
  del: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};

async function buildHeaders(): Promise<Record<string, string>> {
  const token = getToken();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };
  if (token) headers["Authorization"] = `Bearer ${token}`;
  return headers;
}

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

export type Instrument = {
  instrument_id: string;
  symbol: string;
  name: string;
  asset_class: string;
  sector: string | null;
  industry: string | null;
  currency: string;
  status: string;
  created_at: string;
};

export type Listing = {
  listing_id: string;
  instrument_id: string;
  exchange_id: string;
  ticker: string;
  isin: string;
  status: string;
  listed_at: string;
};

export type Exchange = {
  exchange_id: string;
  code: string;
  name: string;
  country: string;
  currency: string;
  timezone: string;
  status: string;
};

export type Recommendation = {
  recommendation_id: string;
  instrument_id: string;
  action: string;
  confidence: number;
  target_price: string | null;
  reasoning: string | null;
  created_at: string;
};

export type Forecast = {
  forecast_id: string;
  instrument_id: string;
  model_name: string;
  direction: string;
  expected_return: string;
  horizon: string;
  created_at: string;
};

export type Score = {
  score_id: string;
  instrument_id: string;
  score_type: string;
  value: number;
  created_at: string;
};

export type Order = {
  order_id: string;
  portfolio_id: string;
  instrument_id: string;
  side: string;
  order_type: string;
  quantity: string;
  price: string | null;
  status: string;
  created_at: string;
};

export type OrderIntent = {
  intent_id: string;
  portfolio_id: string;
  instrument_id: string;
  side: string;
  order_type: string;
  quantity: string;
  status: string;
  created_at: string;
};

export type Decision = {
  decision_id: string;
  portfolio_id: string;
  instrument_id: string;
  action: string;
  status: string;
  policy_result: string;
  created_at: string;
};

export type RiskProfile = {
  risk_profile_id: string;
  name: string;
  risk_tolerance: string;
  status: string;
  created_at: string;
};

export type RiskAssessment = {
  risk_assessment_id: string;
  portfolio_id: string;
  var_95: string | null;
  max_drawdown: string | null;
  concentration_index: string | null;
  created_at: string;
};

export type RiskEvent = {
  risk_event_id: string;
  portfolio_id: string | null;
  event_type: string;
  severity: string;
  status: string;
  description: string | null;
  created_at: string;
};

export type NewsItem = {
  news_id: string;
  title: string;
  source: string | null;
  sentiment: string | null;
  published_at: string;
  summary: string | null;
};

export type FinancialStatement = {
  statement_id: string;
  issuer_id: string;
  period_type: string;
  fiscal_year: string;
  fiscal_period: string;
  status: string;
  created_at: string;
};

export type ConfigEntry = {
  config_id: string;
  key: string;
  value: string;
  category: string;
  status: string;
  created_at: string;
};

export type Broker = {
  broker_id: string;
  name: string;
  code: string;
  status: string;
  created_at: string;
};

export type PaginationMeta = {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta?: PaginationMeta;
};

// ─── Technical Indicators ──────────────────────────────────────────────

export type IndicatorResult = {
  values?: number[];
  latest?: number | null;
  signal?: string;
  trend?: string;
  trend_strength?: string;
};

export type MACDResult = {
  macd_line: number[];
  signal_line: number[];
  histogram: number[];
  latest_macd: number | null;
  latest_signal: number | null;
  trend: string;
};

export type BollingerResult = {
  upper: number[];
  middle: number[];
  lower: number[];
  latest_upper: number | null;
  latest_middle: number | null;
  latest_lower: number | null;
  bandwidth: number | null;
};

export type AllIndicators = {
  sma_20: IndicatorResult;
  sma_50: IndicatorResult;
  ema_12: IndicatorResult;
  ema_26: IndicatorResult;
  rsi_14: IndicatorResult;
  macd: MACDResult;
  bollinger_bands: BollingerResult;
  atr_14: IndicatorResult;
  adx_14: IndicatorResult;
  support_resistance: {
    support: number[];
    resistance: number[];
    current_price: number | null;
  };
  trend: {
    trend: string;
    short_sma: number | null;
    long_sma: number | null;
  };
};

// ─── Market Regime ─────────────────────────────────────────────────────

export type MarketRegime = {
  regime: string;
  sub_regime: string;
  trend: string;
  volatility: string;
  volatility_pct: number | null;
  risk_appetite: string;
  confidence: number;
  details: {
    adx: number | null;
    adx_strength: string;
    rsi: number | null;
    rsi_signal: string;
    atr: number | null;
    bollinger_bandwidth: number | null;
    short_sma: number | null;
    long_sma: number | null;
  };
};

// ─── Screening ─────────────────────────────────────────────────────────

export type ScreeningResult = {
  instrument_id: string;
  symbol: string | null;
  name: string | null;
  asset_class: string;
  screening_score: number;
  matched_criteria: string[];
  not_matched_criteria: string[];
};

export type ScreeningResponse = {
  results: ScreeningResult[];
  total: number;
  criteria: Record<string, unknown>;
};

// ─── Composite Score ───────────────────────────────────────────────────

export type ScoreDimension = {
  score: number | null;
  grade: string;
  weight: number;
};

export type CompositeScore = {
  composite_score: number;
  recommendation: string;
  confidence: string;
  available_dimensions: number;
  dimensions: Record<string, ScoreDimension>;
};

// ─── Stop Loss ─────────────────────────────────────────────────────────

export type StopLossResult = {
  stop_loss_price: number;
  method: string;
  entry_price: number;
  side: string;
  risk_amount: number;
  risk_percent: number;
};

// ─── Correlation Matrix ────────────────────────────────────────────────

export type CorrelationMatrix = {
  instruments: string[];
  matrix: number[][];
};

// ─── Data Quality ──────────────────────────────────────────────────────

export type DataQualityCheck = {
  check: string;
  status: string;
  detail: string;
};

export type DataQualityResult = {
  instrument_id: string;
  total_records?: number;
  date_range?: { from: string; to: string };
  checks: DataQualityCheck[];
  total_issues: number;
  passed: boolean;
};

// ─── Market Microstructure ─────────────────────────────────────────────

export type BidAskSpread = {
  spread: number | null;
  spread_pct: number | null;
  avg_spread: number | null;
  avg_spread_pct: number | null;
  classification: string;
};

export type OrderBookLevel = {
  level: number;
  bid_price: number;
  bid_volume: number;
  ask_price: number;
  ask_volume: number;
};

export type OrderBookDepth = {
  levels: OrderBookLevel[];
  total_bid_volume: number;
  total_ask_volume: number;
  imbalance: number;
  imbalance_pct: number;
};

export type MarketImpact = {
  market_impact_pct: number;
  expected_price_movement: number;
  kyle_lambda: number;
  classification: string;
};

export type LiquidityScore = {
  liquidity_score: number;
  grade: string;
  avg_daily_volume: number;
  avg_daily_value: number;
  volume_consistency: number;
  price_stability: number;
};

// ─── Market Factor Matrix ──────────────────────────────────────────────

export type GlobalFactor = {
  factor: string;
  value: number;
  period: string;
  weight: number;
  direction: string;
  as_of: string;
};

export type GlobalFactorsResult = {
  factors: GlobalFactor[];
  summary: string;
};

export type RupiahPressureScore = {
  score: number;
  grade: string;
  components: Record<string, { value: number; score: number; impact: string }>;
  interpretation: string;
};

export type FlowConfirmationScore = {
  score: number;
  grade: string;
  volume_trend: string;
  smart_money_flow: string;
  interpretation: string;
};

// ─── Support/Resistance & Trend ────────────────────────────────────────

export type SupportResistance = {
  support: number[];
  resistance: number[];
  current_price: number | null;
};

export type TrendResult = {
  trend: string;
  short_sma: number | null;
  long_sma: number | null;
};

// ─── AI Analysis ───────────────────────────────────────────────────────

export type AIAnalysis = {
  analysis_id: string;
  analysis_type: string;
  instrument_id?: string;
  sentiment_score?: number | null;
  sentiment_label?: string | null;
  entities?: string | null;
  events?: string | null;
  pattern_type?: string | null;
  pattern_confidence?: number | null;
  anomaly_score?: number | null;
  anomaly_type?: string | null;
  summary?: string | null;
  created_at: string;
};

// ─── Compliance & Risk Types ───────────────────────────────────────────

export type ComplianceWarning = {
  type: string;
  severity: string;
  message: string;
  [key: string]: unknown;
};

export type DuplicateOrderResult = {
  check: string;
  passed: boolean;
  is_duplicate: boolean;
  duplicate_count: number;
  duplicates: unknown[];
  message: string;
};

export type ErroneousOrderResult = {
  check: string;
  passed: boolean;
  warning_count: number;
  warnings: ComplianceWarning[];
  market_price: number;
  order_value: number;
  message: string;
};

export type CapitalThresholdResult = {
  check: string;
  passed: boolean;
  violation_count: number;
  violations: ComplianceWarning[];
  cash_balance: number;
  position_value: number;
  total_capital: number;
  order_value: number;
  cash_after_order: number;
  message: string;
};

export type FeeBreakdown = {
  broker_commission: number;
  commission_rate: number;
  vat_on_commission: number;
  vat_rate: number;
  bei_fee: number;
  bei_fee_rate: number;
  kpei_fee: number;
  kpei_fee_rate: number;
  sales_tax: number;
  total_fees: number;
};

export type MinimumCapitalResult = {
  check: string;
  portfolio_id: string;
  instrument_id: string;
  ticker: string;
  side: string;
  quantity_requested: number;
  quantity_effective: number;
  lots: number;
  lot_size: number;
  lot_warning: string | null;
  price: number;
  effective_price: number;
  cost_per_share: number;
  order_value: number;
  fee_breakdown: FeeBreakdown;
  minimum_capital: number;
  cash_balance: number;
  shortfall: number;
  sufficient: boolean;
  limit_checks: { limit_type: string; limit_value: number; cash_after_order: number; passed: boolean }[];
  limit_violations: ComplianceWarning[];
  violation_count: number;
  passed: boolean;
  message: string;
};

export type LiquidityRiskResult = {
  portfolio_id: string;
  total_positions: number;
  total_value: number;
  high_risk_positions: number;
  avg_liquidation_days: number;
  portfolio_liquidity_risk_score: number;
  portfolio_risk_level: string;
  positions: {
    instrument_id: string;
    quantity: number;
    position_value: number;
    avg_daily_volume: number;
    max_daily_sellable: number;
    liquidation_days: number;
    liquidity_risk_score: number;
    risk_level: string;
  }[];
};

export type GapRiskResult = {
  portfolio_id: string;
  total_positions: number;
  total_value: number;
  total_gap_risk_value: number;
  portfolio_gap_risk_pct: number;
  portfolio_risk_level: string;
  positions: {
    instrument_id: string;
    quantity: number;
    position_value: number;
    atr_14: number;
    gap_risk_pct: number;
    gap_risk_value: number;
    risk_level: string;
  }[];
};

// ─── API Helper Methods for New Endpoints ──────────────────────────────

export const AnalyticsAPI = {
  // Market Microstructure
  getBidAskSpread: (instrumentId: string) =>
    api.get<BidAskSpread>(`/instruments/${instrumentId}/bid-ask-spread`),

  getOrderBook: (instrumentId: string, levels = 5) =>
    api.get<OrderBookDepth>(`/instruments/${instrumentId}/order-book?levels=${levels}`),

  getMarketImpact: (instrumentId: string, orderValue: number, side = 'BUY') =>
    api.post<MarketImpact>(`/instruments/${instrumentId}/market-impact`, { order_value: orderValue, side }),

  getLiquidityScore: (instrumentId: string) =>
    api.get<LiquidityScore>(`/instruments/${instrumentId}/liquidity-score`),

  // Market Factor Matrix
  getGlobalFactors: () =>
    api.get<GlobalFactorsResult>(`/factors/global-indonesia`),

  getRupiahPressure: () =>
    api.get<RupiahPressureScore>(`/factors/rupiah-pressure`),

  getFlowConfirmation: () =>
    api.get<FlowConfirmationScore>(`/factors/flow-confirmation`),

  // Support/Resistance & Trend
  getSupportResistance: (instrumentId: string) =>
    api.get<SupportResistance>(`/instruments/${instrumentId}/support-resistance`),

  getTrend: (instrumentId: string) =>
    api.get<TrendResult>(`/instruments/${instrumentId}/trend`),

  // Stop Loss
  calculateStopLoss: (instrumentId: string, entryPrice: number, side = 'BUY') =>
    api.post<StopLossResult>(`/instruments/${instrumentId}/stop-loss`, { entry_price: entryPrice, side }),

  // Data Quality
  getDataQuality: (instrumentId: string) =>
    api.get<DataQualityResult>(`/ingestion/quality/${instrumentId}`),

  // Correlation Matrix
  getCorrelationMatrix: (portfolioId: string) =>
    api.get<CorrelationMatrix>(`/portfolios/${portfolioId}/correlation-matrix`),

  // Liquidity Risk & Gap Risk
  getLiquidityRisk: (portfolioId: string) =>
    api.get<LiquidityRiskResult>(`/portfolios/${portfolioId}/liquidity-risk`),

  getGapRisk: (portfolioId: string) =>
    api.get<GapRiskResult>(`/portfolios/${portfolioId}/gap-risk`),

  // Compliance Checks
  checkDuplicateOrder: (portfolioId: string, instrumentId: string, side: string, quantity: number, price: number) =>
    api.post<DuplicateOrderResult>('/compliance/duplicate-order', { portfolio_id: portfolioId, instrument_id: instrumentId, side, quantity, price }),

  checkErroneousOrder: (portfolioId: string, instrumentId: string, side: string, quantity: number, price: number) =>
    api.post<ErroneousOrderResult>('/compliance/erroneous-order', { portfolio_id: portfolioId, instrument_id: instrumentId, side, quantity, price }),

  checkCapitalThreshold: (portfolioId: string, orderValue: number) =>
    api.post<CapitalThresholdResult>('/compliance/capital-threshold', { portfolio_id: portfolioId, order_value: orderValue }),

  calculateMinimumCapital: (portfolioId: string, instrumentId: string, quantity: number, price: number, side = 'BUY') =>
    api.post<MinimumCapitalResult>('/compliance/minimum-capital', { portfolio_id: portfolioId, instrument_id: instrumentId, quantity, price, side }),
};

// ─── Backtesting Types & API ───────────────────────────────────────────

export type BacktestRun = {
  run_id: string;
  strategy_name: string;
  instrument_id: string | null;
  portfolio_id: string | null;
  start_date: string;
  end_date: string;
  initial_capital: string;
  final_capital: string | null;
  status: string;
  parameters: Record<string, unknown> | null;
  created_at: string;
};

export type BacktestTrade = {
  trade_id: string;
  run_id: string;
  instrument_id: string;
  side: string;
  quantity: number;
  entry_price: number;
  exit_price: number;
  entry_date: string;
  exit_date: string;
  pnl: number;
  pnl_pct: number;
};

export type BacktestMetrics = {
  metrics_id: string;
  run_id: string;
  total_return: number;
  annualized_return: number;
  sharpe_ratio: number;
  sortino_ratio: number;
  max_drawdown: number;
  win_rate: number;
  profit_factor: number;
  total_trades: number;
  winning_trades: number;
  losing_trades: number;
  avg_win: number;
  avg_loss: number;
};

export const BacktestAPI = {
  createRun: (data: {
    strategy_name: string;
    start_date: string;
    end_date: string;
    initial_capital: number;
    instrument_id?: string;
    portfolio_id?: string;
    parameters?: Record<string, unknown>;
  }) => api.post<BacktestRun>('/backtests', data),

  listRuns: (page = 1, perPage = 20) =>
    api.getPaginated<BacktestRun>(`/backtests?page=${page}&per_page=${perPage}`),

  getRun: (runId: string) =>
    api.get<BacktestRun>(`/backtests/${runId}`),

  executeRun: (runId: string, priceData: Array<{ date: string; close: number; high: number; low: number; open: number; volume?: number }>) =>
    api.post<{ run_id: string; status: string; final_capital: number; total_trades: number; metrics: BacktestMetrics }>(
      `/backtests/${runId}/execute`,
      { price_data: priceData }
    ),

  getRunTrades: (runId: string) =>
    api.get<BacktestTrade[]>(`/backtests/${runId}/trades`),

  getRunMetrics: (runId: string) =>
    api.get<BacktestMetrics>(`/backtests/${runId}/metrics`),
};

// ─── Paper Trading Types & API ─────────────────────────────────────────

export type PaperOrder = {
  order_id: string;
  portfolio_id: string;
  instrument_id: string;
  side: string;
  order_type: string;
  quantity: number;
  price: number | null;
  status: string;
  filled_quantity: number;
  avg_fill_price: number | null;
  created_at: string;
};

export type PaperPosition = {
  position_id: string;
  portfolio_id: string;
  instrument_id: string;
  quantity: number;
  average_cost: number;
  market_price: number;
  market_value: number;
  unrealized_pnl: number;
  unrealized_pnl_pct: number;
  status: string;
};

export const PaperTradingAPI = {
  createAccount: (data: { portfolio_id: string; initial_capital: number; base_currency?: string }) =>
    api.post<PaperOrder>('/paper/accounts', data),

  getAccount: (accountId: string) =>
    api.get<PaperOrder>(`/paper/accounts/${accountId}`),

  placeOrder: (accountId: string, data: {
    instrument_id: string;
    side: string;
    order_type: string;
    quantity: number;
    price?: number;
  }) => api.post<PaperOrder>(`/paper/accounts/${accountId}/orders`, data),

  listOrders: (accountId: string, page = 1, perPage = 20) =>
    api.getPaginated<PaperOrder>(`/paper/accounts/${accountId}/orders?page=${page}&per_page=${perPage}`),

  cancelOrder: (accountId: string, orderId: string) =>
    api.del<PaperOrder>(`/paper/accounts/${accountId}/orders/${orderId}`),

  listPositions: (accountId: string) =>
    api.get<PaperPosition[]>(`/paper/accounts/${accountId}/positions`),

  getBalance: (accountId: string) =>
    api.get<{ portfolio_id: string; cash_balance: number; available_balance: number; total_value: number }>(`/paper/accounts/${accountId}/summary`),
};

// ─── AI Engine Types & API ─────────────────────────────────────────────

export const AIAPI = {
  analyzeSentiment: (data: { text: string; instrument_id?: string; source_type?: string }) =>
    api.post<AIAnalysis>('/ai/sentiment', data),

  recognizePattern: (data: { instrument_id: string; price_data: Array<{ date: string; open: number; high: number; low: number; close: number }> }) =>
    api.post<AIAnalysis>('/ai/pattern', data),

  detectAnomaly: (data: { instrument_id: string; values: number[] }) =>
    api.post<AIAnalysis>('/ai/anomaly', data),

  listAnalyses: (page = 1, perPage = 20, filters?: { analysis_type?: string; instrument_id?: string }) => {
    let path = `/ai/analyses?page=${page}&per_page=${perPage}`;
    if (filters?.analysis_type) path += `&analysis_type=${filters.analysis_type}`;
    if (filters?.instrument_id) path += `&instrument_id=${filters.instrument_id}`;
    return api.getPaginated<AIAnalysis>(path);
  },

  getAnalysis: (analysisId: string) =>
    api.get<AIAnalysis>(`/ai/analyses/${analysisId}`),
};

// ─── Data Ingestion Fetch API ──────────────────────────────────────────

export const DataIngestionAPI = {
  fetchFromExternal: (data: { provider: string; symbol: string; from_date?: string; to_date?: string }) =>
    api.post<{ provider: string; symbol: string; instrument_id: string; records_ingested: number; records_skipped: number; date_range: { from: string; to: string } }>('/ingestion/fetch', data),

  seedMarketData: (data?: { days?: number; delay?: number; symbol?: string }) =>
    api.post<{ total_records_ingested: number; symbols_processed: number; errors: number; details: Array<{ symbol: string; name: string; instrument_id?: string; records_ingested?: number; status: string; error?: string }> }>('/ingestion/seed-market-data', data ?? {}),

  checkCompleteness: () =>
    api.get<Record<string, unknown>>('/data-completeness'),
};

// ─── Market Scheduler API ─────────────────────────────────────────────

export const MarketSchedulerAPI = {
  getStatus: () =>
    api.get<Record<string, unknown>>('/market-scheduler/status'),

  getSchedule: () =>
    api.get<Record<string, unknown>>('/market-scheduler/schedule'),

  runDueTasks: () =>
    api.post<Record<string, unknown>>('/market-scheduler/run', {}),

  runTask: (taskId: string) =>
    api.post<Record<string, unknown>>(`/market-scheduler/run/${taskId}`, {}),
};

// ─── System Environment API ───────────────────────────────────────────

export const SystemEnvironmentAPI = {
  getEnvironment: () =>
    api.get<Record<string, unknown>>('/system/environment'),

  getCapabilities: () =>
    api.get<Record<string, unknown>>('/system/capabilities'),
};

// ─── Market Coverage API ──────────────────────────────────────────────

export type MarketTypeCoverage = {
  asset_class: string;
  instrument_type: string;
  market_name: string;
  description: string;
  capabilities: string[];
  data_source: string;
  instrument_count: number;
  ohlcv: { instruments_with_data: number; total_records: number; earliest_date: string; latest_date: string } | null;
  signals: { signal_count: number; latest_signal: string } | null;
  recommendations: { rec_count: number; latest_rec: string } | null;
  positions: { position_count: number; total_quantity: number; total_unrealized_pnl: number } | null;
  is_active: boolean;
  has_live_data: boolean;
};

export type MarketCoverage = {
  market_types: MarketTypeCoverage[];
  exchanges: Array<{ name: string; country: string; mic_code: string; currency: string; status: string }>;
  recent_recommendations: Array<Record<string, unknown>>;
  recent_signals: Array<Record<string, unknown>>;
  summary: {
    total_supported_types: number;
    active_market_types: number;
    total_instruments: number;
    instruments_with_live_data: number;
    total_exchanges: number;
  };
};

export const MarketCoverageAPI = {
  getCoverage: () => api.get<MarketCoverage>('/market-coverage'),
};
