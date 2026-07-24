import { useState, useEffect } from "react";
import { useAuth } from "@/lib/auth";
import { api, type Signal, type Portfolio, type Alert } from "@/lib/api";
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
  TrendingUp,
  Wallet,
  Bell,
  Activity,
  RefreshCw,
  AlertCircle,
} from "lucide-react";

type HealthStatus = {
  status: string;
  timestamp: string;
  version: string;
};

type MetricsData = {
  info: { version: string; environment: string };
  uptime_seconds: number;
  services_registered: number;
};

export default function Dashboard() {
  const { owner } = useAuth();
  const [health, setHealth] = useState<HealthStatus | null>(null);
  const [metrics, setMetrics] = useState<MetricsData | null>(null);
  const [signals, setSignals] = useState<Signal[]>([]);
  const [portfolios, setPortfolios] = useState<Portfolio[]>([]);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  async function fetchData() {
    setLoading(true);
    setError("");
    try {
      const [healthRes, metricsRes, signalsRes, portfoliosRes, alertsRes] =
        await Promise.allSettled([
          api.get<HealthStatus>("/health"),
          api.get<MetricsData>("/metrics"),
          api.get<Signal[]>("/signals?per_page=5"),
          api.get<Portfolio[]>("/portfolios?per_page=5"),
          api.get<Alert[]>("/alerts?per_page=5"),
        ]);

      if (healthRes.status === "fulfilled") setHealth(healthRes.value);
      if (metricsRes.status === "fulfilled") setMetrics(metricsRes.value);
      if (signalsRes.status === "fulfilled")
        setSignals(signalsRes.value || []);
      if (portfoliosRes.status === "fulfilled")
        setPortfolios(portfoliosRes.value || []);
      if (alertsRes.status === "fulfilled")
        setAlerts(alertsRes.value || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load data";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchData();
  }, []);

  return (
    <div className="space-y-6">
      {/* Page Title */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Dashboard</h1>
          <p className="text-sm text-muted-foreground">
            Market overview, portfolio summary, and recent signals
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </Button>
      </div>

      {/* Error Banner */}
      {error && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Platform Status"
          value={health?.status || "—"}
          icon={<Activity className="h-5 w-5 text-green-500" />}
          subtitle={metrics ? `${metrics.services_registered} services` : ""}
        />
        <StatCard
          title="Portfolios"
          value={String(portfolios.length)}
          icon={<Wallet className="h-5 w-5 text-blue-500" />}
          subtitle="Active portfolios"
        />
        <StatCard
          title="Recent Signals"
          value={String(signals.length)}
          icon={<TrendingUp className="h-5 w-5 text-purple-500" />}
          subtitle="Latest 5 signals"
        />
        <StatCard
          title="Active Alerts"
          value={String(alerts.filter((a) => a.is_active).length)}
          icon={<Bell className="h-5 w-5 text-orange-500" />}
          subtitle="Monitoring alerts"
        />
      </div>

      {/* Two Column Layout */}
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Recent Signals */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="h-5 w-5 text-primary" />
              Recent Signals
            </CardTitle>
            <CardDescription>Latest trading signals generated</CardDescription>
          </CardHeader>
          <CardContent>
            {signals.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No signals available
              </p>
            ) : (
              <div className="space-y-3">
                {signals.map((sig) => (
                  <div
                    key={sig.signal_id}
                    className="flex items-center justify-between rounded-md border border-border p-3"
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <Badge variant={sig.direction === "BULLISH" ? "success" : sig.direction === "BEARISH" ? "destructive" : "secondary"}>
                          {sig.direction}
                        </Badge>
                        <span className="text-sm font-medium">
                          {sig.signal_type}
                        </span>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {sig.timeframe} · {sig.model_version}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium">
                        {parseFloat(sig.strength).toFixed(1)}%
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {sig.signal_type}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        {/* Active Alerts */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Bell className="h-5 w-5 text-primary" />
              Active Alerts
            </CardTitle>
            <CardDescription>Price, signal, and risk alerts</CardDescription>
          </CardHeader>
          <CardContent>
            {alerts.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">
                No alerts configured
              </p>
            ) : (
              <div className="space-y-3">
                {alerts.map((alert) => (
                  <div
                    key={alert.alert_id}
                    className="flex items-center justify-between rounded-md border border-border p-3"
                  >
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <Badge
                          variant={
                            alert.alert_type === "RISK"
                              ? "destructive"
                              : alert.alert_type === "SIGNAL"
                                ? "default"
                                : "secondary"
                          }
                        >
                          {alert.alert_type}
                        </Badge>
                        <span className="text-sm font-medium">
                          {alert.condition_op} {alert.threshold}
                        </span>
                      </div>
                      {alert.description && (
                        <p className="text-xs text-muted-foreground">
                          {alert.description}
                        </p>
                      )}
                    </div>
                    <Badge variant={alert.is_active ? "success" : "outline"}>
                      {alert.is_active ? "Active" : "Inactive"}
                    </Badge>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Portfolios */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Wallet className="h-5 w-5 text-primary" />
            Portfolios
          </CardTitle>
          <CardDescription>Your investment portfolios</CardDescription>
        </CardHeader>
        <CardContent>
          {portfolios.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted-foreground">
              No portfolios created yet
            </p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {portfolios.map((pf) => (
                <div
                  key={pf.portfolio_id}
                  className="rounded-md border border-border p-4"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-medium">{pf.name}</span>
                    <Badge variant={pf.status === "ACTIVE" ? "success" : "outline"}>
                      {pf.status}
                    </Badge>
                  </div>
                  <p className="mt-2 text-xs text-muted-foreground">
                    Base currency: {pf.base_currency}
                  </p>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function StatCard({
  title,
  value,
  icon,
  subtitle,
}: {
  title: string;
  value: string;
  icon: React.ReactNode;
  subtitle?: string;
}) {
  return (
    <Card>
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div className="space-y-1">
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            <p className="text-2xl font-bold">{value}</p>
            {subtitle && (
              <p className="text-xs text-muted-foreground">{subtitle}</p>
            )}
          </div>
          <div className="rounded-lg bg-secondary p-2.5">{icon}</div>
        </div>
      </CardContent>
    </Card>
  );
}
