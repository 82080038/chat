import { useState, useEffect, useCallback } from "react";
import { AIAPI, type AIAnalysis } from "@/lib/api";
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
import { Brain, RefreshCw, Sparkles, AlertTriangle, TrendingUp } from "lucide-react";

type Tab = "sentiment" | "pattern" | "anomaly" | "history";

export default function AIEngine() {
  const [tab, setTab] = useState<Tab>("sentiment");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<AIAnalysis | null>(null);
  const [history, setHistory] = useState<AIAnalysis[]>([]);

  const [sentimentForm, setSentimentForm] = useState({
    text: "",
    instrument_id: "",
  });

  const [patternForm, setPatternForm] = useState({
    instrument_id: "",
    priceDataJson: "",
  });

  const [anomalyForm, setAnomalyForm] = useState({
    instrument_id: "",
    valuesJson: "",
  });

  const fetchHistory = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await AIAPI.listAnalyses(1, 50);
      setHistory(res.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Failed to load analyses");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (tab === "history") fetchHistory();
  }, [tab, fetchHistory]);

  const handleSentiment = async () => {
    setError(null);
    setResult(null);
    setLoading(true);
    try {
      const r = await AIAPI.analyzeSentiment({
        text: sentimentForm.text,
        instrument_id: sentimentForm.instrument_id || undefined,
      });
      setResult(r);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Analysis failed");
    } finally {
      setLoading(false);
    }
  };

  const handlePattern = async () => {
    setError(null);
    setResult(null);
    setLoading(true);
    try {
      const priceData = JSON.parse(patternForm.priceDataJson);
      const r = await AIAPI.recognizePattern({
        instrument_id: patternForm.instrument_id,
        price_data: priceData,
      });
      setResult(r);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Analysis failed");
    } finally {
      setLoading(false);
    }
  };

  const handleAnomaly = async () => {
    setError(null);
    setResult(null);
    setLoading(true);
    try {
      const values = JSON.parse(anomalyForm.valuesJson);
      const r = await AIAPI.detectAnomaly({
        instrument_id: anomalyForm.instrument_id,
        values,
      });
      setResult(r);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Analysis failed");
    } finally {
      setLoading(false);
    }
  };

  const tabs: { key: Tab; label: string; icon: typeof Brain }[] = [
    { key: "sentiment", label: "Sentiment", icon: Sparkles },
    { key: "pattern", label: "Pattern", icon: TrendingUp },
    { key: "anomaly", label: "Anomaly", icon: AlertTriangle },
    { key: "history", label: "History", icon: RefreshCw },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold">
          <Brain className="h-6 w-6" /> AI Engine
        </h1>
        <p className="text-sm text-muted-foreground">Sentiment analysis, pattern recognition, and anomaly detection</p>
      </div>

      <div className="flex gap-2 border-b border-border">
        {tabs.map((t) => {
          const Icon = t.icon;
          return (
            <button
              key={t.key}
              onClick={() => { setTab(t.key); setResult(null); setError(null); }}
              className={`flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
                tab === t.key
                  ? "border-primary text-primary"
                  : "border-transparent text-muted-foreground hover:text-foreground"
              }`}
            >
              <Icon className="h-4 w-4" />
              {t.label}
            </button>
          );
        })}
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {tab === "sentiment" && (
        <Card>
          <CardHeader>
            <CardTitle>Sentiment Analysis</CardTitle>
            <CardDescription>Analyze text for market sentiment using weighted keyword scoring with negation and intensifier handling</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Text to Analyze</label>
              <textarea
                className="w-full min-h-[120px] rounded-md border border-border bg-background px-3 py-2 text-sm"
                value={sentimentForm.text}
                onChange={(e) => setSentimentForm({ ...sentimentForm, text: e.target.value })}
                placeholder="Paste news article, social media post, or any financial text..."
              />
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Instrument ID (optional)</label>
              <Input value={sentimentForm.instrument_id} onChange={(e) => setSentimentForm({ ...sentimentForm, instrument_id: e.target.value })} placeholder="inst_xxx" />
            </div>
            <Button onClick={handleSentiment} disabled={loading || !sentimentForm.text}>
              <Sparkles className="mr-2 h-4 w-4" /> Analyze Sentiment
            </Button>
          </CardContent>
        </Card>
      )}

      {tab === "pattern" && (
        <Card>
          <CardHeader>
            <CardTitle>Pattern Recognition</CardTitle>
            <CardDescription>Detect chart patterns from OHLCV price data</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Instrument ID</label>
              <Input value={patternForm.instrument_id} onChange={(e) => setPatternForm({ ...patternForm, instrument_id: e.target.value })} placeholder="inst_xxx" />
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Price Data (JSON array)</label>
              <textarea
                className="w-full min-h-[150px] rounded-md border border-border bg-background px-3 py-2 font-mono text-xs"
                value={patternForm.priceDataJson}
                onChange={(e) => setPatternForm({ ...patternForm, priceDataJson: e.target.value })}
                placeholder='[{"date":"2024-01-01","open":100,"high":105,"low":99,"close":103}, ...]'
              />
            </div>
            <Button onClick={handlePattern} disabled={loading || !patternForm.instrument_id || !patternForm.priceDataJson}>
              <TrendingUp className="mr-2 h-4 w-4" /> Detect Pattern
            </Button>
          </CardContent>
        </Card>
      )}

      {tab === "anomaly" && (
        <Card>
          <CardHeader>
            <CardTitle>Anomaly Detection</CardTitle>
            <CardDescription>Detect price or volume anomalies using z-score analysis</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Instrument ID</label>
              <Input value={anomalyForm.instrument_id} onChange={(e) => setAnomalyForm({ ...anomalyForm, instrument_id: e.target.value })} placeholder="inst_xxx" />
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Values (JSON array of numbers)</label>
              <textarea
                className="w-full min-h-[100px] rounded-md border border-border bg-background px-3 py-2 font-mono text-xs"
                value={anomalyForm.valuesJson}
                onChange={(e) => setAnomalyForm({ ...anomalyForm, valuesJson: e.target.value })}
                placeholder="[100, 102, 101, 99, 150, 103, 101]"
              />
            </div>
            <Button onClick={handleAnomaly} disabled={loading || !anomalyForm.instrument_id || !anomalyForm.valuesJson}>
              <AlertTriangle className="mr-2 h-4 w-4" /> Detect Anomalies
            </Button>
          </CardContent>
        </Card>
      )}

      {tab === "history" && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Analysis History</CardTitle>
              <Button variant="ghost" size="icon" onClick={fetchHistory} disabled={loading}>
                <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} />
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-2 max-h-[500px] overflow-y-auto">
            {history.length === 0 ? (
              <p className="text-sm text-muted-foreground">No analyses yet</p>
            ) : (
              history.map((a) => (
                <div key={a.analysis_id} className="rounded-md border border-border p-3">
                  <div className="flex items-center justify-between">
                    <Badge variant="secondary">{a.analysis_type}</Badge>
                    <span className="text-xs text-muted-foreground">{new Date(a.created_at).toLocaleString()}</span>
                  </div>
                  <div className="mt-2 space-y-1 text-sm">
                    {a.sentiment_label && (
                      <div className="flex gap-2">
                        <span className="text-muted-foreground">Sentiment:</span>
                        <Badge variant={a.sentiment_label === "POSITIVE" ? "default" : a.sentiment_label === "NEGATIVE" ? "destructive" : "secondary"}>
                          {a.sentiment_label} ({a.sentiment_score})
                        </Badge>
                      </div>
                    )}
                    {a.pattern_type && (
                      <div className="flex gap-2">
                        <span className="text-muted-foreground">Pattern:</span>
                        <span className="font-medium">{a.pattern_type} ({a.pattern_confidence}% confidence)</span>
                      </div>
                    )}
                    {a.anomaly_type && (
                      <div className="flex gap-2">
                        <span className="text-muted-foreground">Anomaly:</span>
                        <span className="font-medium">{a.anomaly_type} (score: {a.anomaly_score})</span>
                      </div>
                    )}
                    {a.summary && <p className="text-xs text-muted-foreground">{a.summary}</p>}
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      )}

      {result && (
        <Card>
          <CardHeader>
            <CardTitle>Result</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-md border border-border p-3">
                <span className="text-xs text-muted-foreground">Analysis ID</span>
                <p className="text-sm font-mono">{result.analysis_id}</p>
              </div>
              <div className="rounded-md border border-border p-3">
                <span className="text-xs text-muted-foreground">Type</span>
                <p className="text-sm font-medium">{result.analysis_type}</p>
              </div>
              {result.sentiment_label && (
                <div className="rounded-md border border-border p-3">
                  <span className="text-xs text-muted-foreground">Sentiment</span>
                  <p className="text-sm font-medium">{result.sentiment_label} (score: {result.sentiment_score})</p>
                </div>
              )}
              {result.pattern_type && (
                <div className="rounded-md border border-border p-3">
                  <span className="text-xs text-muted-foreground">Pattern</span>
                  <p className="text-sm font-medium">{result.pattern_type} ({result.pattern_confidence}% confidence)</p>
                </div>
              )}
              {result.anomaly_type && (
                <div className="rounded-md border border-border p-3">
                  <span className="text-xs text-muted-foreground">Anomaly</span>
                  <p className="text-sm font-medium">{result.anomaly_type} (score: {result.anomaly_score})</p>
                </div>
              )}
            </div>
            {result.summary && (
              <div className="rounded-md border border-border p-3">
                <span className="text-xs text-muted-foreground">Summary</span>
                <p className="mt-1 text-sm">{result.summary}</p>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
