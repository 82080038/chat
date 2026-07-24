import { useState, useEffect, useCallback } from "react";
import {
  api,
  type RiskProfile,
  type RiskAssessment,
  type RiskEvent,
  type Portfolio,
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
import { Shield, RefreshCw, AlertCircle, AlertTriangle, Activity } from "lucide-react";

export default function RiskMonitor() {
  const [profiles, setProfiles] = useState<RiskProfile[]>([]);
  const [assessments, setAssessments] = useState<RiskAssessment[]>([]);
  const [events, setEvents] = useState<RiskEvent[]>([]);
  const [portfolios, setPortfolios] = useState<Portfolio[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [pfRes, asRes, evRes, portRes] = await Promise.allSettled([
        api.getPaginated<RiskProfile>("/risk-profiles?per_page=20"),
        api.getPaginated<RiskAssessment>("/risk-assessments?per_page=20"),
        api.getPaginated<RiskEvent>("/risk-events?per_page=20"),
        api.get<Portfolio[]>("/portfolios?per_page=50"),
      ]);
      if (pfRes.status === "fulfilled") setProfiles(pfRes.value.data || []);
      if (asRes.status === "fulfilled") setAssessments(asRes.value.data || []);
      if (evRes.status === "fulfilled") setEvents(evRes.value.data || []);
      if (portRes.status === "fulfilled") setPortfolios(portRes.value || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load risk data";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const openEvents = events.filter((e) => e.status === "OPEN");
  const acknowledgedEvents = events.filter((e) => e.status === "ACKNOWLEDGED");

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Risk Monitor</h1>
          <p className="text-sm text-muted-foreground">
            Risk profiles, assessments, and events
          </p>
        </div>
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

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Risk Profiles</p>
                <p className="text-2xl font-bold">{profiles.length}</p>
              </div>
              <Shield className="h-8 w-8 text-primary" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Assessments</p>
                <p className="text-2xl font-bold">{assessments.length}</p>
              </div>
              <Activity className="h-8 w-8 text-blue-500" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Open Events</p>
                <p className="text-2xl font-bold text-red-500">{openEvents.length}</p>
              </div>
              <AlertTriangle className="h-8 w-8 text-red-500" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">Acknowledged</p>
                <p className="text-2xl font-bold text-orange-500">{acknowledgedEvents.length}</p>
              </div>
              <AlertCircle className="h-8 w-8 text-orange-500" />
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Risk Profiles */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Shield className="h-5 w-5" />
            Risk Profiles
          </CardTitle>
          <CardDescription>Configured risk tolerance profiles</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Tolerance</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {profiles.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-center text-muted-foreground">
                    No risk profiles
                  </TableCell>
                </TableRow>
              ) : (
                profiles.map((p) => (
                  <TableRow key={p.risk_profile_id}>
                    <TableCell className="font-medium">{p.name}</TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          p.risk_tolerance === "AGGRESSIVE" ? "destructive" :
                          p.risk_tolerance === "CONSERVATIVE" ? "secondary" : "default"
                        }
                      >
                        {p.risk_tolerance}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={p.status === "ACTIVE" ? "success" : "outline"}>
                        {p.status}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Risk Assessments */}
      <Card>
        <CardHeader>
          <CardTitle>Risk Assessments</CardTitle>
          <CardDescription>Portfolio risk measurements</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Portfolio</TableHead>
                <TableHead>VaR 95%</TableHead>
                <TableHead>Max Drawdown</TableHead>
                <TableHead>Concentration</TableHead>
                <TableHead>Date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {assessments.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground">
                    No risk assessments
                  </TableCell>
                </TableRow>
              ) : (
                assessments.map((a) => {
                  const pf = portfolios.find((p) => p.portfolio_id === a.portfolio_id);
                  return (
                    <TableRow key={a.risk_assessment_id}>
                      <TableCell>{pf?.name || a.portfolio_id.slice(0, 8)}</TableCell>
                      <TableCell>
                        {a.var_95 ? `${Number(a.var_95).toFixed(2)}%` : "—"}
                      </TableCell>
                      <TableCell>
                        {a.max_drawdown ? `${Number(a.max_drawdown).toFixed(2)}%` : "—"}
                      </TableCell>
                      <TableCell>
                        {a.concentration_index ? Number(a.concentration_index).toFixed(3) : "—"}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {a.created_at}
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {/* Risk Events */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5" />
            Risk Events
          </CardTitle>
          <CardDescription>Risk breaches and alerts</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Type</TableHead>
                <TableHead>Severity</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {events.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground">
                    No risk events
                  </TableCell>
                </TableRow>
              ) : (
                events.map((e) => (
                  <TableRow key={e.risk_event_id}>
                    <TableCell>
                      <Badge variant="secondary">{e.event_type}</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          e.severity === "CRITICAL" ? "destructive" :
                          e.severity === "HIGH" ? "destructive" :
                          e.severity === "MEDIUM" ? "default" : "secondary"
                        }
                      >
                        {e.severity}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          e.status === "OPEN" ? "destructive" :
                          e.status === "ACKNOWLEDGED" ? "default" : "secondary"
                        }
                      >
                        {e.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="max-w-xs truncate text-sm">
                      {e.description || "—"}
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">
                      {e.created_at}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
