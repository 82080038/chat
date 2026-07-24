import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { api, type Instrument } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
import { Search, RefreshCw, AlertCircle } from "lucide-react";

export default function Instruments() {
  const navigate = useNavigate();
  const [instruments, setInstruments] = useState<Instrument[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const fetchData = useCallback(async (p: number, s: string) => {
    setLoading(true);
    setError("");
    try {
      const params = new URLSearchParams({ per_page: "20", page: String(p) });
      if (s) params.set("search", s);
      const { data, meta } = await api.getPaginated<Instrument>(
        `/instruments?${params}`
      );
      setInstruments(data || []);
      if (meta) setTotalPages(meta.total_pages);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Failed to load instruments";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => fetchData(page, search), 300);
    return () => clearTimeout(timer);
  }, [page, search, fetchData]);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Instruments</h1>
          <p className="text-sm text-muted-foreground">
            Browse and search market instruments
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => fetchData(page, search)}
          disabled={loading}
        >
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

      <Card>
        <CardHeader>
          <div className="flex items-center gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Search by symbol or name..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                className="pl-10"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Symbol</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Asset Class</TableHead>
                <TableHead>Sector</TableHead>
                <TableHead>Currency</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {instruments.length === 0 && !loading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-muted-foreground">
                    No instruments found
                  </TableCell>
                </TableRow>
              ) : (
                instruments.map((inst) => (
                  <TableRow
                    key={inst.instrument_id}
                    className="cursor-pointer"
                    onClick={() => navigate(`/instruments/${inst.instrument_id}`)}
                  >
                    <TableCell className="font-medium">{inst.symbol}</TableCell>
                    <TableCell className="max-w-xs truncate">{inst.name}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{inst.asset_class}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {inst.sector || "—"}
                    </TableCell>
                    <TableCell>{inst.currency}</TableCell>
                    <TableCell>
                      <Badge variant={inst.status === "ACTIVE" ? "success" : "outline"}>
                        {inst.status}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          {totalPages > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <span className="text-sm text-muted-foreground">
                Page {page} of {totalPages}
              </span>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= totalPages}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
