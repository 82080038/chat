import { useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { api, type ScreeningResult, type ScreeningResponse } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from "@/components/ui/table";
import { RefreshCw, AlertCircle, Filter, Search } from "lucide-react";

export default function Screening() {
  const navigate = useNavigate();
  const [results, setResults] = useState<ScreeningResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [assetClass, setAssetClass] = useState("EQUITY");
  const [minRoe, setMinRoe] = useState("");
  const [maxPe, setMaxPe] = useState("");
  const [maxDe, setMaxDe] = useState("");
  const [minRevGrowth, setMinRevGrowth] = useState("");
  const [trend, setTrend] = useState("");
  const [minScore, setMinScore] = useState("");

  const runScreening = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const criteria: Record<string, unknown> = { limit: 100 };
      if (assetClass) criteria.asset_class = assetClass;
      if (minRoe) criteria.min_roe = Number(minRoe);
      if (maxPe) criteria.max_pe = Number(maxPe);
      if (maxDe) criteria.max_debt_equity = Number(maxDe);
      if (minRevGrowth) criteria.min_revenue_growth = Number(minRevGrowth);
      if (trend) criteria.trend = trend;
      if (minScore) criteria.min_composite_score = Number(minScore);

      const res = await api.post<ScreeningResponse>("/screening", criteria);
      setResults(res.results || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to run screening";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, [assetClass, minRoe, maxPe, maxDe, minRevGrowth, trend, minScore]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Screening</h1>
          <p className="text-sm text-muted-foreground">
            Multi-factor screening and scanning
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={runScreening} disabled={loading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Scan
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Filter className="h-5 w-5" />
            Screening Criteria
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">Asset Class</label>
              <Select value={assetClass} onChange={(e) => setAssetClass(e.target.value)}>
                <option value="EQUITY">Equity</option>
                <option value="BOND">Bond</option>
                <option value="ETF">ETF</option>
                <option value="DERIVATIVE">Derivative</option>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Min ROE (%)</label>
              <Input
                type="number"
                placeholder="e.g. 15"
                value={minRoe}
                onChange={(e) => setMinRoe(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Max P/E</label>
              <Input
                type="number"
                placeholder="e.g. 20"
                value={maxPe}
                onChange={(e) => setMaxPe(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Max Debt/Equity</label>
              <Input
                type="number"
                placeholder="e.g. 1.0"
                value={maxDe}
                onChange={(e) => setMaxDe(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Min Revenue Growth (%)</label>
              <Input
                type="number"
                placeholder="e.g. 10"
                value={minRevGrowth}
                onChange={(e) => setMinRevGrowth(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Trend</label>
              <Select value={trend} onChange={(e) => setTrend(e.target.value)}>
                <option value="">Any</option>
                <option value="UPTREND">Uptrend</option>
                <option value="DOWNTREND">Downtrend</option>
                <option value="SIDEWAYS">Sideways</option>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Min Screening Score</label>
              <Input
                type="number"
                placeholder="0-100"
                value={minScore}
                onChange={(e) => setMinScore(e.target.value)}
              />
            </div>
            <div className="flex items-end">
              <Button className="w-full" onClick={runScreening} disabled={loading}>
                <Search className="mr-2 h-4 w-4" />
                Run Screening
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Symbol</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Asset Class</TableHead>
                <TableHead className="text-center">Score</TableHead>
                <TableHead>Matched Criteria</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {results.length === 0 && !loading ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground">
                    {results.length === 0 ? "Click \"Run Screening\" to search" : "No instruments match the criteria"}
                  </TableCell>
                </TableRow>
              ) : (
                results.map((r) => (
                  <TableRow
                    key={r.instrument_id}
                    className="cursor-pointer"
                    onClick={() => navigate(`/instruments/${r.instrument_id}`)}
                  >
                    <TableCell className="font-medium">{r.symbol || r.instrument_id.slice(0, 8)}</TableCell>
                    <TableCell className="max-w-xs truncate">{r.name || "—"}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{r.asset_class}</Badge>
                    </TableCell>
                    <TableCell className="text-center">
                      <Badge variant={r.screening_score >= 70 ? "success" : r.screening_score >= 40 ? "secondary" : "destructive"}>
                        {r.screening_score}
                      </Badge>
                    </TableCell>
                    <TableCell className="max-w-md">
                      <div className="flex flex-wrap gap-1">
                        {r.matched_criteria.map((c, i) => (
                          <Badge key={i} variant="success" className="text-xs">{c}</Badge>
                        ))}
                        {r.not_matched_criteria.map((c, i) => (
                          <Badge key={`n${i}`} variant="destructive" className="text-xs">{c}</Badge>
                        ))}
                      </div>
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
