<?php

declare(strict_types=1);

namespace Platform\Core\System;

use Platform\Core\Application;

/**
 * System Environment Detector — detects OS, hardware, GPU/CUDA availability,
 * PHP extensions, memory, and adjusts application behavior accordingly.
 *
 * Supports: Windows, Linux, macOS
 * Detects: CUDA (nvidia-smi), GPU, CPU cores, memory, PHP extensions
 */
final class SystemEnvironment
{
    private static ?SystemEnvironment $instance = null;
    private ?array $cachedInfo = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Detect and return full system environment information.
     */
    public function detect(): array
    {
        if ($this->cachedInfo !== null) {
            return $this->cachedInfo;
        }

        $os = $this->detectOS();
        $gpu = $this->detectGPU();
        $cuda = $this->detectCuda();
        $php = $this->detectPhp();
        $memory = $this->detectMemory();
        $cpu = $this->detectCpu();
        $db = $this->detectDatabase();

        $info = [
            'os' => $os,
            'cpu' => $cpu,
            'memory' => $memory,
            'gpu' => $gpu,
            'cuda' => $cuda,
            'php' => $php,
            'database' => $db,
            'runtime' => $this->detectRuntime(),
            'capabilities' => $this->deriveCapabilities($os, $cuda, $gpu, $php, $memory, $cpu),
            'recommendations' => $this->generateRecommendations($os, $cuda, $gpu, $php, $memory),
            'detected_at' => date('c'),
        ];

        $this->cachedInfo = $info;
        return $info;
    }

    /**
     * Get a capability summary for use by other services.
     */
    public function getCapabilities(): array
    {
        return $this->detect()['capabilities'];
    }

    // ─── OS Detection ─────────────────────────────────────────────────

    private function detectOS(): array
    {
        $phpOs = PHP_OS_FAMILY;
        $osName = 'Unknown';
        $osVersion = '';
        $distribution = null;

        if ($phpOs === 'Windows') {
            $osName = 'Windows';
            $osVersion = php_uname('r');
            // Try to get more detailed Windows version
            $ver = shell_exec('ver 2>nul') ?? '';
            $osVersion = trim($ver) ?: $osVersion;
        } elseif ($phpOs === 'Linux') {
            $osName = 'Linux';
            $osVersion = php_uname('r');
            $distribution = $this->detectLinuxDistro();
        } elseif ($phpOs === 'Darwin') {
            $osName = 'macOS';
            $osVersion = shell_exec('sw_vers -productVersion 2>/dev/null') ?? '';
            $osVersion = trim($osVersion);
        } elseif ($phpOs === 'BSD') {
            $osName = 'BSD';
            $osVersion = php_uname('r');
        }

        return [
            'family' => $phpOs,
            'name' => $osName,
            'version' => $osVersion,
            'distribution' => $distribution,
            'hostname' => gethostname(),
            'arch' => php_uname('m'),
            'is_windows' => $phpOs === 'Windows',
            'is_linux' => $phpOs === 'Linux',
            'is_macos' => $phpOs === 'Darwin',
        ];
    }

    private function detectLinuxDistro(): ?array
    {
        $files = [
            '/etc/os-release' => 'os-release',
            '/etc/lsb-release' => 'lsb-release',
        ];

        foreach ($files as $file => $type) {
            if (file_exists($file) && is_readable($file)) {
                $content = file_get_contents($file);
                $info = [];
                foreach (explode("\n", $content) as $line) {
                    if (str_contains($line, '=')) {
                        [$key, $value] = explode('=', $line, 2);
                        $info[trim($key)] = trim($value, '"\'');
                    }
                }
                return [
                    'name' => $info['NAME'] ?? $info['DISTRIB_ID'] ?? 'Unknown',
                    'version' => $info['VERSION'] ?? $info['DISTRIB_RELEASE'] ?? '',
                    'id' => $info['ID'] ?? $info['DISTRIB_CODENAME'] ?? '',
                ];
            }
        }
        return null;
    }

    // ─── CPU Detection ────────────────────────────────────────────────

    private function detectCpu(): array
    {
        $cores = 1;

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic cpu get NumberOfCores 2>nul');
            if ($output) {
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                if (count($lines) > 1) {
                    $cores = (int) $lines[1] ?: 1;
                }
            }
            // Fallback: number of logical processors
            if ($cores === 1) {
                $output = shell_exec('echo %NUMBER_OF_PROCESSORS%');
                $cores = (int) trim($output ?? '1') ?: 1;
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec('nproc 2>/dev/null');
            $cores = (int) trim($output ?? '1') ?: 1;
            if ($cores === 1) {
                $cpuInfo = file_get_contents('/proc/cpuinfo');
                if ($cpuInfo !== false) {
                    $cores = substr_count($cpuInfo, 'processor');
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            $cores = (int) trim($output ?? '1') ?: 1;
        }

        $model = 'Unknown';
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/cpuinfo')) {
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            if (preg_match('/model name\s*:\s*(.+)/', $cpuInfo, $m)) {
                $model = trim($m[1]);
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic cpu get Name 2>nul');
            if ($output) {
                $lines = array_filter(array_map('trim', explode("\n", $output)));
                if (count($lines) > 1) {
                    $model = $lines[1];
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');
            $model = trim($output ?? '') ?: 'Unknown';
        }

        return [
            'cores' => $cores,
            'model' => $model,
            'threads' => $cores, // Approximation
        ];
    }

    // ─── Memory Detection ─────────────────────────────────────────────

    private function detectMemory(): array
    {
        $total = 0;
        $available = 0;

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $content, $m)) {
                $total = (int) $m[1] * 1024; // KB to bytes
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $content, $m)) {
                $available = (int) $m[1] * 1024;
            } elseif (preg_match('/MemFree:\s+(\d+)/', $content, $m)) {
                $available = (int) $m[1] * 1024;
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic OS get TotalVisibleMemorySize,FreePhysicalMemory /format:list 2>nul');
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'TotalVisibleMemorySize=')) {
                        $total = (int) substr($line, strlen('TotalVisibleMemorySize=')) * 1024;
                    }
                    if (str_starts_with($line, 'FreePhysicalMemory=')) {
                        $available = (int) substr($line, strlen('FreePhysicalMemory=')) * 1024;
                    }
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('sysctl -n hw.memsize 2>/dev/null');
            $total = (int) trim($output ?? '0');
            $output = shell_exec('vm_stat 2>/dev/null');
            if ($output && $total > 0) {
                // Parse vm_stat for free memory (approximate)
                if (preg_match('/Pages free:\s+(\d+)/', $output, $m)) {
                    $pageSize = (int) (shell_exec('sysctl -n hw.pagesize 2>/dev/null') ?? 4096);
                    $available = (int) $m[1] * $pageSize;
                }
            }
        }

        // PHP memory limit
        $phpLimit = ini_get('memory_limit');
        $phpLimitBytes = $this->parseMemoryString($phpLimit);

        return [
            'total_bytes' => $total,
            'total_human' => $this->formatBytes($total),
            'available_bytes' => $available,
            'available_human' => $this->formatBytes($available),
            'php_limit' => $phpLimit,
            'php_limit_bytes' => $phpLimitBytes,
            'php_limit_human' => $this->formatBytes($phpLimitBytes),
        ];
    }

    // ─── GPU Detection ────────────────────────────────────────────────

    private function detectGPU(): array
    {
        $gpus = [];
        $hasNvidia = false;
        $hasAmd = false;
        $hasIntegrated = false;

        // Try nvidia-smi first
        $nvidiaSmi = $this->runCommand('nvidia-smi --query-gpu=name,memory.total,driver_version,compute_cap --format=csv,noheader 2>nul 2>/dev/null');

        if ($nvidiaSmi !== null && $nvidiaSmi !== '') {
            $hasNvidia = true;
            $lines = explode("\n", trim($nvidiaSmi));
            foreach ($lines as $line) {
                $parts = array_map('trim', str_getcsv($line));
                if (count($parts) >= 1) {
                    $gpus[] = [
                        'vendor' => 'NVIDIA',
                        'model' => $parts[0],
                        'memory_bytes' => isset($parts[1]) ? $this->parseMemoryString($parts[1]) : 0,
                        'memory_human' => isset($parts[1]) ? $parts[1] : 'Unknown',
                        'driver_version' => $parts[2] ?? null,
                        'compute_capability' => $parts[3] ?? null,
                        'type' => 'discrete',
                    ];
                }
            }
        }

        // Linux: try lspci for other GPUs
        if (PHP_OS_FAMILY === 'Linux') {
            $lspci = $this->runCommand('lspci 2>/dev/null | grep -i "vga\\|3d\\|display"');
            if ($lspci !== null) {
                foreach (explode("\n", $lspci) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    if (str_contains(strtolower($line), 'nvidia') && !$hasNvidia) {
                        $hasNvidia = true;
                        $gpus[] = [
                            'vendor' => 'NVIDIA',
                            'model' => $this->extractGpuModel($line),
                            'memory_bytes' => 0,
                            'memory_human' => 'Unknown',
                            'driver_version' => null,
                            'compute_capability' => null,
                            'type' => 'discrete',
                        ];
                    } elseif (str_contains(strtolower($line), 'amd') || str_contains(strtolower($line), 'radeon')) {
                        $hasAmd = true;
                        $gpus[] = [
                            'vendor' => 'AMD',
                            'model' => $this->extractGpuModel($line),
                            'memory_bytes' => 0,
                            'memory_human' => 'Unknown',
                            'driver_version' => null,
                            'compute_capability' => null,
                            'type' => 'discrete',
                        ];
                    } elseif (str_contains(strtolower($line), 'intel')) {
                        $hasIntegrated = true;
                        $gpus[] = [
                            'vendor' => 'Intel',
                            'model' => $this->extractGpuModel($line),
                            'memory_bytes' => 0,
                            'memory_human' => 'Shared',
                            'driver_version' => null,
                            'compute_capability' => null,
                            'type' => 'integrated',
                        ];
                    }
                }
            }
        }

        // Windows: try wmic
        if (PHP_OS_FAMILY === 'Windows' && empty($gpus)) {
            $wmic = $this->runCommand('wmic path win32_VideoController get Name,AdapterRAM /format:list 2>nul');
            if ($wmic) {
                $current = [];
                foreach (explode("\n", $wmic) as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'Name=')) {
                        $current['model'] = substr($line, 5);
                    }
                    if (str_starts_with($line, 'AdapterRAM=')) {
                        $current['memory_bytes'] = (int) substr($line, strlen('AdapterRAM='));
                    }
                    if (isset($current['model']) && $current['model'] !== '') {
                        $vendor = 'Unknown';
                        $lower = strtolower($current['model']);
                        if (str_contains($lower, 'nvidia')) {
                            $vendor = 'NVIDIA';
                        } elseif (str_contains($lower, 'amd') || str_contains($lower, 'radeon')) {
                            $vendor = 'AMD';
                        } elseif (str_contains($lower, 'intel')) {
                            $vendor = 'Intel';
                        }

                        $gpus[] = [
                            'vendor' => $vendor,
                            'model' => $current['model'],
                            'memory_bytes' => $current['memory_bytes'] ?? 0,
                            'memory_human' => $this->formatBytes($current['memory_bytes'] ?? 0),
                            'driver_version' => null,
                            'compute_capability' => null,
                            'type' => $vendor === 'Intel' ? 'integrated' : 'discrete',
                        ];
                        $current = [];
                    }
                }
            }
        }

        return [
            'has_gpu' => count($gpus) > 0,
            'has_nvidia' => $hasNvidia,
            'has_amd' => $hasAmd,
            'has_integrated' => $hasIntegrated,
            'gpu_count' => count($gpus),
            'devices' => $gpus,
        ];
    }

    // ─── CUDA Detection ───────────────────────────────────────────────

    private function detectCuda(): array
    {
        $available = false;
        $version = null;
        $toolkitPath = null;
        $devices = [];

        // Check nvidia-smi for CUDA support
        $nvidiaSmi = $this->runCommand('nvidia-smi --query-gpu=name,compute_cap --format=csv,noheader 2>nul 2>/dev/null');
        if ($nvidiaSmi !== null && $nvidiaSmi !== '') {
            // nvidia-smi works, GPU is present
            $nvcc = $this->runCommand('nvcc --version 2>nul 2>/dev/null');
            if ($nvcc !== null && preg_match('/release\s+([\d.]+)/', $nvcc, $m)) {
                $version = $m[1];
                $available = true;
            }

            // Even without nvcc, if nvidia-smi reports compute capability, CUDA is available
            if (!$available) {
                foreach (explode("\n", trim($nvidiaSmi)) as $line) {
                    $parts = array_map('trim', str_getcsv($line));
                    if (count($parts) >= 2 && $parts[1] !== '' && $parts[1] !== '0.0') {
                        $available = true;
                        $devices[] = [
                            'model' => $parts[0],
                            'compute_capability' => $parts[1],
                        ];
                    }
                }
            }

            // Try to detect CUDA version from nvidia-smi
            if (!$version) {
                $smiFull = $this->runCommand('nvidia-smi 2>nul 2>/dev/null');
                if ($smiFull && preg_match('/CUDA Version:\s*([\d.]+)/', $smiFull, $m)) {
                    $version = $m[1];
                }
            }
        }

        // Check environment variables
        $cudaPath = getenv('CUDA_HOME') ?: getenv('CUDA_PATH');
        if ($cudaPath) {
            $toolkitPath = $cudaPath;
            if (!$available) {
                $available = true;
            }
        }

        // Check common paths
        if (!$toolkitPath) {
            $commonPaths = [
                '/usr/local/cuda',
                'C:\\Program Files\\NVIDIA GPU Computing Toolkit\\CUDA',
            ];
            foreach ($commonPaths as $path) {
                if (is_dir($path)) {
                    $toolkitPath = $path;
                    $available = true;
                    break;
                }
            }
        }

        // Check PHP extensions that might leverage CUDA
        $phpExtensions = [];
        if (extension_loaded('cuda')) {
            $phpExtensions[] = 'cuda';
            $available = true;
        }

        return [
            'available' => $available,
            'version' => $version,
            'toolkit_path' => $toolkitPath,
            'devices' => $devices,
            'php_extensions' => $phpExtensions,
            'can_accelerate' => $available,
        ];
    }

    // ─── PHP Environment ──────────────────────────────────────────────

    private function detectPhp(): array
    {
        $extensions = get_loaded_extensions();
        $relevantExtensions = [
            'pdo', 'pdo_mysql', 'pdo_pgsql', 'json', 'mbstring', 'openssl',
            'curl', 'gd', 'xml', 'zip', 'redis', 'memcached', 'opcache',
            'xdebug', 'intl', 'bcmath', 'sockets', 'ffi',
        ];

        $loaded = [];
        foreach ($relevantExtensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return [
            'version' => PHP_VERSION,
            'version_id' => PHP_VERSION_ID,
            'sapi' => PHP_SAPI,
            'extensions' => $loaded,
            'opcache_enabled' => function_exists('opcache_get_status') && (opcache_get_status() !== false),
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'max_input_vars' => (int) ini_get('max_input_vars'),
            'timezone' => date_default_timezone_get(),
        ];
    }

    // ─── Database Detection ───────────────────────────────────────────

    private function detectDatabase(): array
    {
        $info = [
            'driver' => null,
            'version' => null,
            'host' => null,
            'database' => null,
        ];

        try {
            $db = \Platform\Core\Database\MySqlConnection::getInstance();
            $stmt = $db->query('SELECT VERSION() AS version, DATABASE() AS db');
            $row = $stmt->fetch();
            if ($row) {
                $info['version'] = $row['version'] ?? null;
                $info['database'] = $row['db'] ?? null;
            }
            $info['driver'] = 'MySQL/MariaDB';
            $info['host'] = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
        } catch (\Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    // ─── Runtime Detection ────────────────────────────────────────────

    private function detectRuntime(): array
    {
        $isCli = PHP_SAPI === 'cli';
        $isApache = str_contains(PHP_SAPI, 'apache');
        $isFpm = str_contains(PHP_SAPI, 'fpm');
        $isBuiltIn = str_contains(PHP_SAPI, 'cli-server');

        $server = 'Unknown';
        if ($isApache) {
            $server = 'Apache';
        } elseif ($isFpm) {
            $server = 'PHP-FPM';
        } elseif ($isBuiltIn) {
            $server = 'PHP Built-in Server';
        } elseif ($isCli) {
            $server = 'CLI';
        }

        return [
            'sapi' => PHP_SAPI,
            'server' => $server,
            'is_cli' => $isCli,
            'is_web' => !$isCli,
            'is_development' => Application::getInstance()->getEnvironment() === 'development',
            'is_production' => Application::getInstance()->getEnvironment() === 'production',
        ];
    }

    // ─── Capabilities ─────────────────────────────────────────────────

    private function deriveCapabilities(array $os, array $cuda, array $gpu, array $php, array $memory, array $cpu): array
    {
        $canUseGpu = $cuda['available'] || ($gpu['has_nvidia'] && $cuda['can_accelerate']);
        $canUseParallel = $cpu['cores'] >= 4;
        $canHandleLargeData = $memory['available_bytes'] > (2 * 1024 * 1024 * 1024); // >2GB
        $canUseTimescale = $php['extensions']['pdo_pgsql'] ?? false;
        $hasOpCache = $php['opcache_enabled'];

        return [
            'gpu_acceleration' => $canUseGpu,
            'parallel_processing' => $canUseParallel,
            'large_data_processing' => $canHandleLargeData,
            'timescaledb' => $canUseTimescale,
            'opcache' => $hasOpCache,
            'redis_cache' => $php['extensions']['redis'] ?? false,
            'ffi_support' => $php['extensions']['ffi'] ?? false,
            'max_instruments_per_batch' => $canUseGpu ? 200 : ($canUseParallel ? 100 : 50),
            'max_concurrent_analytics' => $canUseParallel ? min($cpu['cores'], 8) : 2,
            'recommended_batch_size' => $canHandleLargeData ? 500 : 100,
            'compute_backend' => $canUseGpu ? 'CUDA' : 'CPU',
            'performance_tier' => $canUseGpu ? 'HIGH' : ($canUseParallel && $canHandleLargeData ? 'MEDIUM' : 'LOW'),
        ];
    }

    // ─── Recommendations ──────────────────────────────────────────────

    private function generateRecommendations(array $os, array $cuda, array $gpu, array $php, array $memory): array
    {
        $recs = [];

        if (!$cuda['available'] && $gpu['has_nvidia']) {
            $recs[] = 'GPU NVIDIA terdeteksi tetpa CUDA belum terkonfigurasi. Instal CUDA Toolkit untuk akselerasi komputasi AI/ML.';
        }

        if (!$cuda['available'] && !$gpu['has_gpu']) {
            $recs[] = 'Tidak ada GPU terdeteksi. Aplikasi akan berjalan dalam mode CPU. Pertimbangkan menambah GPU NVIDIA untuk akselerasi.';
        }

        if ($cuda['available']) {
            $recs[] = 'CUDA tersedia (v' . ($cuda['version'] ?? 'unknown') . '). Aplikasi akan menggunakan akselerasi GPU untuk komputasi berat.';
        }

        if (!$php['opcache_enabled']) {
            $recs[] = 'OPcache belum diaktifkan. Aktifkan OPcache untuk meningkatkan performa PHP secara signifikan.';
        }

        if ($php['max_execution_time'] > 0 && $php['max_execution_time'] < 60) {
            $recs[] = 'max_execution_time hanya ' . $php['max_execution_time'] . ' detik. Tingkatkan ke 300+ untuk komputasi analitik berat.';
        }

        $memLimitBytes = $this->parseMemoryString($php['memory_limit']);
        if ($memLimitBytes > 0 && $memLimitBytes < (512 * 1024 * 1024)) {
            $recs[] = 'PHP memory_limit hanya ' . $php['memory_limit'] . '. Tingkatkan ke 512M+ untuk pemrosesan data besar.';
        }

        if (!$php['extensions']['pdo_pgsql'] ?? false) {
            $recs[] = 'Ekstensi pdo_pgsql tidak tersedia. TimescaleDB tidak dapat digunakan. Instal pgsql untuk fitur time-series.';
        }

        if ($os['is_windows']) {
            $recs[] = 'Berjalan di Windows. Untuk performa optimal produksi, pertimbangkan Linux dengan CUDA.';
        }

        if ($os['is_linux'] && $cuda['available']) {
            $recs[] = 'Linux + CUDA: konfigurasi optimal untuk komputasi heavy-duty.';
        }

        if (empty($recs)) {
            $recs[] = 'Lingkungan sistem terkonfigurasi dengan baik.';
        }

        return $recs;
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function runCommand(string $command): ?string
    {
        // Disable command on potentially unsafe environments
        if (!function_exists('shell_exec')) {
            return null;
        }
        $result = @shell_exec($command);
        return $result !== null ? trim($result) : null;
    }

    private function extractGpuModel(string $line): string
    {
        // Remove PCI address prefix (e.g. "01:00.0 VGA compatible controller: ")
        $parts = explode(':', $line, 2);
        $model = count($parts) > 1 ? trim($parts[1]) : trim($line);
        // Remove bracketed subsystem info
        $model = preg_replace('/\s*\([^)]+\)\s*$/', '', $model) ?? $model;
        return $model;
    }

    private function parseMemoryString(string $value): int
    {
        $value = trim($value);
        if ($value === '-1' || $value === '') {
            return -1; // Unlimited
        }
        $last = strtolower($value[strlen($value) - 1] ?? '');
        $number = (int) $value;
        switch ($last) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
            default:
                // Already in bytes (or MiB from nvidia-smi)
                if (str_contains(strtolower($value), 'mib')) {
                    $number = (int) $value * 1024 * 1024;
                } elseif (str_contains(strtolower($value), 'gib')) {
                    $number = (int) $value * 1024 * 1024 * 1024;
                }
        }
        return $number;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 1) . ' ' . $units[$i];
    }
}
