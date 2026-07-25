import { useState, useEffect, useCallback } from "react";
import { SystemEnvironmentAPI } from "@/lib/api";
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
import {
  RefreshCw, Cpu, MemoryStick, HardDrive, Database, Zap, Monitor,
  CheckCircle2, XCircle, AlertTriangle, Lightbulb, Server, Gauge
} from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";
import { formatDateTime } from "@/lib/format";

type OSInfo = {
  family: string;
  name: string;
  version: string;
  distribution: { name: string; version: string; id: string } | null;
  hostname: string;
  arch: string;
  is_windows: boolean;
  is_linux: boolean;
  is_macos: boolean;
};

type CpuInfo = {
  cores: number;
  model: string;
  threads: number;
};

type MemoryInfo = {
  total_bytes: number;
  total_human: string;
  available_bytes: number;
  available_human: string;
  php_limit: string;
  php_limit_bytes: number;
  php_limit_human: string;
};

type GpuDevice = {
  vendor: string;
  model: string;
  memory_bytes: number;
  memory_human: string;
  driver_version: string | null;
  compute_capability: string | null;
  type: string;
};

type GpuInfo = {
  has_gpu: boolean;
  has_nvidia: boolean;
  has_amd: boolean;
  has_integrated: boolean;
  gpu_count: number;
  devices: GpuDevice[];
};

type CudaInfo = {
  available: boolean;
  version: string | null;
  toolkit_path: string | null;
  devices: Array<{ model: string; compute_capability: string }>;
  php_extensions: string[];
  can_accelerate: boolean;
};

type PhpInfo = {
  version: string;
  version_id: number;
  sapi: string;
  extensions: Record<string, boolean>;
  opcache_enabled: boolean;
  max_execution_time: number;
  memory_limit: string;
  max_input_vars: number;
  timezone: string;
};

type DatabaseInfo = {
  driver: string | null;
  version: string | null;
  host: string | null;
  database: string | null;
  error?: string;
};

type RuntimeInfo = {
  sapi: string;
  server: string;
  is_cli: boolean;
  is_web: boolean;
  is_development: boolean;
  is_production: boolean;
};

type Capabilities = {
  gpu_acceleration: boolean;
  parallel_processing: boolean;
  large_data_processing: boolean;
  timescaledb: boolean;
  opcache: boolean;
  redis_cache: boolean;
  ffi_support: boolean;
  max_instruments_per_batch: number;
  max_concurrent_analytics: number;
  recommended_batch_size: number;
  compute_backend: string;
  performance_tier: string;
};

type EnvironmentData = {
  os: OSInfo;
  cpu: CpuInfo;
  memory: MemoryInfo;
  gpu: GpuInfo;
  cuda: CudaInfo;
  php: PhpInfo;
  database: DatabaseInfo;
  runtime: RuntimeInfo;
  capabilities: Capabilities;
  recommendations: string[];
  detected_at: string;
};

const TIER_CONFIG: Record<string, { color: string; label: string }> = {
  HIGH: { color: "text-green-600", label: "Tinggi" },
  MEDIUM: { color: "text-amber-600", label: "Sedang" },
  LOW: { color: "text-red-600", label: "Rendah" },
};

const OS_ICONS: Record<string, string> = {
  Windows: "🪟",
  Linux: "🐧",
  macOS: "🍎",
  BSD: "💻",
};

export default function SystemEnvironmentPage() {
  const [data, setData] = useState<EnvironmentData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await SystemEnvironmentAPI.getEnvironment();
      setData(res as unknown as EnvironmentData);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal memuat informasi sistem");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const extList = data ? Object.entries(data.php.extensions).filter(([, v]) => v).map(([k]) => k) : [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Lingkungan Sistem</h1>
          <p className="text-sm text-muted-foreground">
            Deteksi otomatis OS, GPU/CUDA, dan penyesuaian performa aplikasi
          </p>
        </div>
        <button
          onClick={fetchData}
          className="inline-flex items-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent"
          disabled={loading}
        >
          <RefreshCw className={`h-4 w-4 ${loading ? "animate-spin" : ""}`} /> Muat Ulang
        </button>
      </div>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {data && (
        <>
          {/* Performance Tier Banner */}
          <Card className={data.capabilities.performance_tier === 'HIGH' ? 'border-green-500/50' : data.capabilities.performance_tier === 'MEDIUM' ? 'border-amber-500/50' : 'border-red-500/50'}>
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Gauge className={`h-8 w-8 ${TIER_CONFIG[data.capabilities.performance_tier]?.color ?? 'text-muted-foreground'}`} />
                  <div>
                    <span className="text-sm text-muted-foreground">Tingkat Performa</span>
                    <p className={`text-2xl font-bold ${TIER_CONFIG[data.capabilities.performance_tier]?.color ?? ''}`}>
                      {TIER_CONFIG[data.capabilities.performance_tier]?.label ?? data.capabilities.performance_tier}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-right">
                    <span className="text-sm text-muted-foreground">Backend Komputasi</span>
                    <p className="text-lg font-bold">{data.capabilities.compute_backend}</p>
                  </div>
                  <div className="text-right">
                    <span className="text-sm text-muted-foreground">Batch Maksimal</span>
                    <p className="text-lg font-bold">{data.capabilities.max_instruments_per_batch}</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* OS & Runtime */}
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Monitor className="h-5 w-5" />
                  Sistem Operasi
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">OS</span>
                  <span className="text-sm font-medium">
                    {OS_ICONS[data.os.name] ?? "💻"} {data.os.name} {data.os.version}
                  </span>
                </div>
                {data.os.distribution && (
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Distribusi</span>
                    <span className="text-sm font-medium">{data.os.distribution.name} {data.os.distribution.version}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Arsitektur</span>
                  <span className="text-sm font-medium font-mono">{data.os.arch}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Hostname</span>
                  <span className="text-sm font-medium font-mono">{data.os.hostname}</span>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Server className="h-5 w-5" />
                  Runtime
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Server</span>
                  <span className="text-sm font-medium">{data.runtime.server}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">SAPI</span>
                  <span className="text-sm font-medium font-mono">{data.runtime.sapi}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Mode</span>
                  <Badge variant={data.runtime.is_production ? "default" : "secondary"} className="text-xs">
                    {data.runtime.is_production ? "Produksi" : "Pengembangan"}
                  </Badge>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Zona Waktu</span>
                  <span className="text-sm font-medium">{data.php.timezone}</span>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* CPU & Memory */}
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Cpu className="h-5 w-5" />
                  CPU
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <div>
                  <span className="text-sm text-muted-foreground">Model</span>
                  <p className="text-sm font-medium">{data.cpu.model}</p>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Core</span>
                  <span className="text-lg font-bold">{data.cpu.cores}</span>
                </div>
                <div className="flex items-center gap-2">
                  {data.capabilities.parallel_processing ? (
                    <><CheckCircle2 className="h-4 w-4 text-green-600" /><span className="text-xs text-green-600">Pemrosesan paralel didukung</span></>
                  ) : (
                    <><XCircle className="h-4 w-4 text-red-600" /><span className="text-xs text-red-600">Core terbatas, mode single-thread</span></>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <MemoryStick className="h-5 w-5" />
                  Memori
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Total</span>
                  <span className="text-sm font-medium">{data.memory.total_human}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Tersedia</span>
                  <span className="text-sm font-medium">{data.memory.available_human}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">PHP memory_limit</span>
                  <span className="text-sm font-medium font-mono">{data.memory.php_limit}</span>
                </div>
                <div className="flex items-center gap-2">
                  {data.capabilities.large_data_processing ? (
                    <><CheckCircle2 className="h-4 w-4 text-green-600" /><span className="text-xs text-green-600">Memori cukup untuk data besar</span></>
                  ) : (
                    <><AlertTriangle className="h-4 w-4 text-amber-600" /><span className="text-xs text-amber-600">Memori terbatas, batch kecil disarankan</span></>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* GPU & CUDA */}
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <HardDrive className="h-5 w-5" />
                  GPU
                </CardTitle>
                <CardDescription>Kartu grafis terdeteksi</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.gpu.has_gpu ? (
                  data.gpu.devices.map((device, i) => (
                    <div key={i} className="rounded-md border p-3">
                      <div className="flex items-center justify-between mb-1">
                        <span className="font-medium text-sm">{device.vendor}</span>
                        <Badge variant={device.type === 'discrete' ? "default" : "secondary"} className="text-xs">
                          {device.type === 'discrete' ? "Diskrit" : "Terintegrasi"}
                        </Badge>
                      </div>
                      <p className="text-sm">{device.model}</p>
                      <p className="text-xs text-muted-foreground mt-1">VRAM: {device.memory_human}</p>
                      {device.driver_version && (
                        <p className="text-xs text-muted-foreground">Driver: {device.driver_version}</p>
                      )}
                    </div>
                  ))
                ) : (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <XCircle className="h-4 w-4" /> Tidak ada GPU terdeteksi
                  </div>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Zap className="h-5 w-5" />
                  <TermTooltip term="CUDA">CUDA</TermTooltip>
                </CardTitle>
                <CardDescription>Akselerasi GPU untuk komputasi AI/ML</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-center gap-2">
                  {data.cuda.available ? (
                    <><CheckCircle2 className="h-5 w-5 text-green-600" /><span className="text-sm font-medium text-green-600">CUDA Tersedia</span></>
                  ) : (
                    <><XCircle className="h-5 w-5 text-red-600" /><span className="text-sm font-medium text-red-600">CUDA Tidak Tersedia</span></>
                  )}
                </div>
                {data.cuda.version && (
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Versi CUDA</span>
                    <span className="text-sm font-medium font-mono">{data.cuda.version}</span>
                  </div>
                )}
                {data.cuda.toolkit_path && (
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Toolkit Path</span>
                    <span className="text-sm font-mono text-xs">{data.cuda.toolkit_path}</span>
                  </div>
                )}
                {data.cuda.devices.length > 0 && (
                  <div className="space-y-1">
                    {data.cuda.devices.map((dev, i) => (
                      <div key={i} className="flex justify-between text-xs">
                        <span>{dev.model}</span>
                        <span className="font-mono text-muted-foreground">CC {dev.compute_capability}</span>
                      </div>
                    ))}
                  </div>
                )}
                <div className="flex items-center gap-2 pt-2 border-t">
                  {data.capabilities.gpu_acceleration ? (
                    <><CheckCircle2 className="h-4 w-4 text-green-600" /><span className="text-xs text-green-600">Akselerasi GPU aktif — komputasi akan dipercepat</span></>
                  ) : (
                    <><XCircle className="h-4 w-4 text-muted-foreground" /><span className="text-xs text-muted-foreground">Mode CPU — tanpa akselerasi GPU</span></>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Capabilities */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Gauge className="h-5 w-5" />
                Kapabilitas Sistem
              </CardTitle>
              <CardDescription>Penyesuaian otomatis berdasarkan hardware yang tersedia</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {[
                  { label: "Akselerasi GPU", value: data.capabilities.gpu_acceleration },
                  { label: "Pemrosesan Paralel", value: data.capabilities.parallel_processing },
                  { label: "Data Besar", value: data.capabilities.large_data_processing },
                  { label: "TimescaleDB", value: data.capabilities.timescaledb },
                  { label: "OPcache", value: data.capabilities.opcache },
                  { label: "Redis Cache", value: data.capabilities.redis_cache },
                  { label: "FFI Support", value: data.capabilities.ffi_support },
                ].map((cap) => (
                  <div key={cap.label} className="flex items-center gap-2 rounded-md border p-2">
                    {cap.value ? (
                      <CheckCircle2 className="h-4 w-4 text-green-600 shrink-0" />
                    ) : (
                      <XCircle className="h-4 w-4 text-muted-foreground shrink-0" />
                    )}
                    <span className="text-sm">{cap.label}</span>
                  </div>
                ))}
              </div>
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <div className="rounded-md border p-3 text-center">
                  <p className="text-xs text-muted-foreground">Maks. Instrumen per Batch</p>
                  <p className="text-xl font-bold">{data.capabilities.max_instruments_per_batch}</p>
                </div>
                <div className="rounded-md border p-3 text-center">
                  <p className="text-xs text-muted-foreground">Maks. Analytics Concurrent</p>
                  <p className="text-xl font-bold">{data.capabilities.max_concurrent_analytics}</p>
                </div>
                <div className="rounded-md border p-3 text-center">
                  <p className="text-xs text-muted-foreground">Ukuran Batch Disarankan</p>
                  <p className="text-xl font-bold">{data.capabilities.recommended_batch_size}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* PHP Environment */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Cpu className="h-5 w-5" />
                PHP Environment
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">Versi PHP</span>
                  <span className="text-sm font-medium font-mono">{data.php.version}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">SAPI</span>
                  <span className="text-sm font-medium font-mono">{data.php.sapi}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">max_execution_time</span>
                  <span className="text-sm font-medium font-mono">{data.php.max_execution_time}s</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-muted-foreground">max_input_vars</span>
                  <span className="text-sm font-medium font-mono">{data.php.max_input_vars}</span>
                </div>
              </div>
              <div>
                <span className="text-sm text-muted-foreground mb-2 block">Ekstensi Terloaded</span>
                <div className="flex flex-wrap gap-1">
                  {extList.map((ext) => (
                    <Badge key={ext} variant="outline" className="text-xs font-mono">{ext}</Badge>
                  ))}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Database */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Database className="h-5 w-5" />
                Database
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {data.database.error ? (
                <div className="text-sm text-destructive">Error: {data.database.error}</div>
              ) : (
                <>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Driver</span>
                    <span className="text-sm font-medium">{data.database.driver}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Versi</span>
                    <span className="text-sm font-medium font-mono">{data.database.version}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Host</span>
                    <span className="text-sm font-medium font-mono">{data.database.host}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-sm text-muted-foreground">Database</span>
                    <span className="text-sm font-medium font-mono">{data.database.database}</span>
                  </div>
                </>
              )}
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
                <CardDescription>Saran untuk mengoptimalkan lingkungan</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2">
                {data.recommendations.map((rec, i) => (
                  <div key={i} className="flex items-start gap-2 rounded-md border p-3 text-sm">
                    <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0 text-amber-600" />
                    <span>{rec}</span>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}

          <p className="text-xs text-muted-foreground text-center">
            Terdeteksi pada: {formatDateTime(data.detected_at)}
          </p>
        </>
      )}
    </div>
  );
}
