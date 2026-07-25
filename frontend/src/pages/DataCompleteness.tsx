import { useState, useEffect, useCallback } from "react";
import { DataIngestionAPI } from "@/lib/api";
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
import { RefreshCw, AlertTriangle, CheckCircle2, XCircle, Database, Lightbulb } from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";
import { formatDateTime, formatNumber } from "@/lib/format";

type TableStatus = 'OK' | 'EMPTY' | 'INSUFFICIENT' | 'STALE' | 'TABLE_NOT_FOUND';

type TableResult = {
  table: string;
  label: string;
  module: string;
  row_count: number;
  min_expected: number;
  latest_record_date: string | null;
  is_stale: boolean;
  days_since_last_record?: number;
  status: TableStatus;
  error?: string;
};

type ModuleData = {
  tables: TableResult[];
  module_status: string;
};

type CompletenessResponse = {
  summary: {
    total_tables: number;
    populated: number;
    empty: number;
    stale: number;
    completeness_pct: number;
  };
  modules: Record<string, ModuleData>;
  missing_instruments: Array<{ instrument_id: string; symbol: string; asset_class: string; instrument_type: string }>;
  recommendations: string[];
  checked_at: string;
};

const STATUS_CONFIG: Record<TableStatus, { color: string; icon: typeof CheckCircle2; label: string }> = {
  OK: { color: "text-green-600", icon: CheckCircle2, label: "OK" },
  EMPTY: { color: "text-red-600", icon: XCircle, label: "Kosong" },
  INSUFFICIENT: { color: "text-amber-600", icon: AlertTriangle, label: "Tidak Cukup" },
  STALE: { color: "text-orange-600", icon: AlertTriangle, label: "Usang" },
  TABLE_NOT_FOUND: { color: "text-red-600", icon: XCircle, label: "Tabel Tidak Ditemukan" },
};

const MODULE_STATUS_CONFIG: Record<string, { variant: "default" | "secondary" | "destructive" | "outline"; label: string }> = {
  OK: { variant: "default", label: "OK" },
  INCOMPLETE: { variant: "secondary", label: "Tidak Lengkap" },
  EMPTY: { variant: "destructive", label: "Kosong" },
};

export default function DataCompleteness() {
  const [data, setData] = useState<CompletenessResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [expandedModule, setExpandedModule] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await DataIngestionAPI.checkCompleteness();
      setData(res as unknown as CompletenessResponse);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal memuat data kelengkapan");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleSeed = async () => {
    setLoading(true);
    setError(null);
    try {
      await DataIngestionAPI.seedMarketData({ days: 730, delay: 2 });
      await fetchData();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal menjalankan seeder");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Pemeriksa Kelengkapan Data</h1>
          <p className="text-sm text-muted-foreground">
            Audit kelengkapan data di seluruh modul aplikasi
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={fetchData} disabled={loading}>
            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} /> Muat Ulang
          </Button>
          <Button onClick={handleSeed} disabled={loading}>
            <Database className="mr-2 h-4 w-4" /> Isi Data Pasar
          </Button>
        </div>
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {data && (
        <>
          {/* Summary Cards */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card>
              <CardContent className="pt-6">
                <span className="text-sm text-muted-foreground">Total Tabel</span>
                <p className="mt-2 text-2xl font-bold">{data.summary.total_tables}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <span className="text-sm text-muted-foreground">Terisi</span>
                <p className="mt-2 text-2xl font-bold text-green-600">{data.summary.populated}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <span className="text-sm text-muted-foreground">Kosong</span>
                <p className="mt-2 text-2xl font-bold text-red-600">{data.summary.empty}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <span className="text-sm text-muted-foreground">Usang</span>
                <p className="mt-2 text-2xl font-bold text-orange-600">{data.summary.stale}</p>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <span className="text-sm text-muted-foreground"><TermTooltip term="Completeness">Kelengkapan</TermTooltip></span>
                <p className="mt-2 text-2xl font-bold">{data.summary.completeness_pct}%</p>
              </CardContent>
            </Card>
          </div>

          {/* Completeness Progress Bar */}
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium">Persentase Kelengkapan Data</span>
                <span className="text-sm text-muted-foreground">{data.summary.populated}/{data.summary.total_tables} tabel terisi</span>
              </div>
              <div className="h-3 w-full rounded-full bg-muted overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all ${
                    data.summary.completeness_pct >= 80 ? "bg-green-600" :
                    data.summary.completeness_pct >= 50 ? "bg-amber-600" : "bg-red-600"
                  }`}
                  style={{ width: `${data.summary.completeness_pct}%` }}
                />
              </div>
            </CardContent>
          </Card>

          {/* Recommendations */}
          {data.recommendations.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Lightbulb className="h-5 w-5" />
                  Rekomendasi
                </CardTitle>
                <CardDescription>Tindakan yang diperlukan untuk melengkapi data</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2">
                {data.recommendations.map((rec, i) => (
                  <div key={i} className="flex items-start gap-2 rounded-md border border-border p-3 text-sm">
                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0 text-amber-600" />
                    <span>{rec}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          {/* Missing Instruments */}
          {data.missing_instruments.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <XCircle className="h-5 w-5 text-red-600" />
                  Instrumen Tanpa Data OHLCV ({data.missing_instruments.length})
                </CardTitle>
                <CardDescription>Instrumen yang terdaftar namun belum memiliki data harga</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="max-h-[300px] overflow-y-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Simbol</TableHead>
                        <TableHead>Kelas Aset</TableHead>
                        <TableHead>Tipe Instrumen</TableHead>
                        <TableHead>ID</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.missing_instruments.map((inst) => (
                        <TableRow key={inst.instrument_id}>
                          <TableCell className="font-mono text-xs font-medium">{inst.symbol}</TableCell>
                          <TableCell><Badge variant="outline" className="text-xs">{inst.asset_class}</Badge></TableCell>
                          <TableCell className="text-xs">{inst.instrument_type}</TableCell>
                          <TableCell className="font-mono text-xs text-muted-foreground">{inst.instrument_id}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Module-by-Module Breakdown */}
          <div className="space-y-4">
            <h2 className="text-lg font-semibold">Rincian per Modul</h2>
            {Object.entries(data.modules).map(([moduleName, moduleData]) => {
              const modConfig = MODULE_STATUS_CONFIG[moduleData.module_status] ?? { variant: "outline" as const, label: moduleData.module_status };
              const isExpanded = expandedModule === moduleName;
              const emptyCount = moduleData.tables.filter(t => t.status === 'EMPTY' || t.status === 'TABLE_NOT_FOUND').length;
              const insufficientCount = moduleData.tables.filter(t => t.status === 'INSUFFICIENT' || t.status === 'STALE').length;

              return (
                <Card key={moduleName}>
                  <CardHeader
                    className="cursor-pointer"
                    onClick={() => setExpandedModule(isExpanded ? null : moduleName)}
                  >
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-base">{moduleName}</CardTitle>
                      <div className="flex items-center gap-2">
                        {emptyCount > 0 && (
                          <Badge variant="destructive" className="text-xs">{emptyCount} kosong</Badge>
                        )}
                        {insufficientCount > 0 && (
                          <Badge variant="secondary" className="text-xs">{insufficientCount} bermasalah</Badge>
                        )}
                        <Badge variant={modConfig.variant} className="text-xs">{modConfig.label}</Badge>
                      </div>
                    </div>
                  </CardHeader>
                  {isExpanded && (
                    <CardContent>
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Tabel</TableHead>
                            <TableHead className="text-right">Jumlah Record</TableHead>
                            <TableHead className="text-right">Minimal Diharapkan</TableHead>
                            <TableHead>Terakhir Diperbarui</TableHead>
                            <TableHead>Status</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          {moduleData.tables.map((t) => {
                            const config = STATUS_CONFIG[t.status] ?? STATUS_CONFIG.EMPTY;
                            const Icon = config.icon;
                            return (
                              <TableRow key={t.table}>
                                <TableCell>
                                  <div className="font-medium text-sm">{t.label}</div>
                                  <div className="text-xs text-muted-foreground font-mono">{t.table}</div>
                                </TableCell>
                                <TableCell className="text-right font-mono text-sm">{formatNumber(t.row_count, 0)}</TableCell>
                                <TableCell className="text-right font-mono text-sm text-muted-foreground">
                                  {t.min_expected > 0 ? formatNumber(t.min_expected, 0) : "-"}
                                </TableCell>
                                <TableCell className="text-xs">
                                  {t.latest_record_date ? (
                                    <div>
                                      <div>{formatDateTime(t.latest_record_date)}</div>
                                      {t.days_since_last_record !== undefined && (
                                        <div className="text-muted-foreground">{t.days_since_last_record} hari lalu</div>
                                      )}
                                    </div>
                                  ) : "-"}
                                </TableCell>
                                <TableCell>
                                  <div className={`flex items-center gap-1 ${config.color}`}>
                                    <Icon className="h-4 w-4" />
                                    <span className="text-xs font-medium">{config.label}</span>
                                  </div>
                                </TableCell>
                              </TableRow>
                            );
                          })}
                        </TableBody>
                      </Table>
                    </CardContent>
                  )}
                </Card>
              );
            })}
          </div>

          <p className="text-xs text-muted-foreground text-center">
            Diperiksa pada: {formatDateTime(data.checked_at)}
          </p>
        </>
      )}
    </div>
  );
}
