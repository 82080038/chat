<?php

declare(strict_types=1);

namespace Platform\DataIngestion;

use PDO;
use Platform\Core\Database\MySqlConnection;

/**
 * Data Completeness Checker — scans all critical database tables
 * and reports what data is present, missing, or stale.
 *
 * This module answers: "Can the application do its job? What data is missing?"
 */
final class DataCompletenessChecker
{
    private PDO $db;

    /**
     * Tables that are critical for the application to function.
     * Each entry maps a logical module to its database table and expected minimum.
     */
    private array $criticalTables = [
        // ── Market Master ──────────────────────────────────────────────
        ['module' => 'Market Master', 'label' => 'Bursa (Exchanges)', 'table' => 'market_master.exchange', 'min_expected' => 1],
        ['module' => 'Market Master', 'label' => 'Penerbit (Issuers)', 'table' => 'market_master.issuer', 'min_expected' => 1],
        ['module' => 'Market Master', 'label' => 'Efek (Securities)', 'table' => 'market_master.security', 'min_expected' => 1],
        ['module' => 'Market Master', 'label' => 'Instrumen (Instruments)', 'table' => 'market_master.instrument', 'min_expected' => 1],
        ['module' => 'Market Master', 'label' => 'Listing', 'table' => 'market_master.listing', 'min_expected' => 1],
        ['module' => 'Market Master', 'label' => 'Kalender Pasar', 'table' => 'market_master.market_calendar', 'min_expected' => 0],
        ['module' => 'Market Master', 'label' => 'Aksi Korporasi', 'table' => 'market_master.corporate_action', 'min_expected' => 0],
        ['module' => 'Market Master', 'label' => 'Indeks Master', 'table' => 'market_master.index_master', 'min_expected' => 0],

        // ── Data Ingestion ─────────────────────────────────────────────
        ['module' => 'Data Ingestion', 'label' => 'Data OHLCV Harian', 'table' => 'data_ingestion.ohlcv_daily', 'min_expected' => 100],

        // ── Analytics ──────────────────────────────────────────────────
        ['module' => 'Analytics', 'label' => 'Sinyal Trading', 'table' => 'analytics.signal', 'min_expected' => 1],
        ['module' => 'Analytics', 'label' => 'Nilai Fitur (Feature Values)', 'table' => 'analytics.feature_value', 'min_expected' => 1],
        ['module' => 'Analytics', 'label' => 'Rekomendasi', 'table' => 'analytics.recommendation', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Skor Komposit', 'table' => 'analytics.composite_score', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Indikator Teknikal', 'table' => 'analytics.technical_indicator', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Regime Pasar', 'table' => 'analytics.market_regime', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Hasil Screening', 'table' => 'analytics.screening_result', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Keputusan Komposit', 'table' => 'analytics.composite_decision', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Faktor Pasar', 'table' => 'analytics.factor_value', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Registry Model', 'table' => 'analytics.model_registry', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'Prakiraan (Forecast)', 'table' => 'analytics.forecast', 'min_expected' => 0],
        ['module' => 'Analytics', 'label' => 'XAI (Explainable AI)', 'table' => 'analytics.xai_explanation', 'min_expected' => 0],

        // ── Fundamental ────────────────────────────────────────────────
        ['module' => 'Fundamental', 'label' => 'Laporan Keuangan', 'table' => 'fundamental.financial_statement', 'min_expected' => 0],
        ['module' => 'Fundamental', 'label' => 'Metrik Keuangan', 'table' => 'fundamental.financial_metric', 'min_expected' => 0],
        ['module' => 'Fundamental', 'label' => 'Indikator Ekonomi', 'table' => 'fundamental.economic_indicator', 'min_expected' => 0],
        ['module' => 'Fundamental', 'label' => 'Berita Pasar', 'table' => 'fundamental.news', 'min_expected' => 0],

        // ── Portfolio ──────────────────────────────────────────────────
        ['module' => 'Portfolio', 'label' => 'Portofolio', 'table' => 'portfolio.portfolio', 'min_expected' => 1],
        ['module' => 'Portfolio', 'label' => 'Posisi Portofolio', 'table' => 'portfolio.position', 'min_expected' => 0],
        ['module' => 'Portfolio', 'label' => 'Saldo Kas', 'table' => 'portfolio.cash_balance', 'min_expected' => 0],
        ['module' => 'Portfolio', 'label' => 'Transaksi Kas', 'table' => 'portfolio.cash_transaction', 'min_expected' => 0],
        ['module' => 'Portfolio', 'label' => 'Target Portofolio', 'table' => 'portfolio.portfolio_target', 'min_expected' => 0],
        ['module' => 'Portfolio', 'label' => 'Akun Tertaut', 'table' => 'portfolio.linked_account', 'min_expected' => 0],

        // ── Risk ───────────────────────────────────────────────────────
        ['module' => 'Risk', 'label' => 'Profil Risiko', 'table' => 'risk.risk_profile', 'min_expected' => 1],
        ['module' => 'Risk', 'label' => 'Penilaian Risiko', 'table' => 'risk.risk_assessment', 'min_expected' => 0],
        ['module' => 'Risk', 'label' => 'Peristiwa Risiko', 'table' => 'risk.risk_event', 'min_expected' => 0],
        ['module' => 'Risk', 'label' => 'Aturan Kepatuhan', 'table' => 'risk.compliance_rule', 'min_expected' => 0],
        ['module' => 'Risk', 'label' => 'Cek Kepatuhan', 'table' => 'risk.compliance_check', 'min_expected' => 0],

        // ── Trading ────────────────────────────────────────────────────
        ['module' => 'Trading', 'label' => 'Broker', 'table' => 'trading.broker', 'min_expected' => 1],
        ['module' => 'Trading', 'label' => 'Keputusan Trading', 'table' => 'trading.decision', 'min_expected' => 0],
        ['module' => 'Trading', 'label' => 'Niat Order (Order Intent)', 'table' => 'trading.order_intent', 'min_expected' => 0],
        ['module' => 'Trading', 'label' => 'Order', 'table' => 'trading.order', 'min_expected' => 0],
        ['module' => 'Trading', 'label' => 'Eksekusi Order', 'table' => 'trading.execution', 'min_expected' => 0],

        // ── Alert ──────────────────────────────────────────────────────
        ['module' => 'Alert', 'label' => 'Peringatan (Alerts)', 'table' => 'alert.alert', 'min_expected' => 0],

        // ── Valuation ──────────────────────────────────────────────────
        ['module' => 'Valuation', 'label' => 'Hasil Valuasi', 'table' => 'valuation.valuation_result', 'min_expected' => 0],

        // ── Microstructure ─────────────────────────────────────────────
        ['module' => 'Microstructure', 'label' => 'Snapshot Order Book', 'table' => 'microstructure.order_book_snapshot', 'min_expected' => 0],
        ['module' => 'Microstructure', 'label' => 'Metrik Microstructure', 'table' => 'microstructure.metrics', 'min_expected' => 0],

        // ── Backtesting ────────────────────────────────────────────────
        ['module' => 'Backtesting', 'label' => 'Backtest Run', 'table' => 'backtesting.backtest_run', 'min_expected' => 0],
        ['module' => 'Backtesting', 'label' => 'Backtest Trade', 'table' => 'backtesting.backtest_trade', 'min_expected' => 0],
        ['module' => 'Backtesting', 'label' => 'Backtest Metrics', 'table' => 'backtesting.backtest_metrics', 'min_expected' => 0],

        // ── Paper Trading ──────────────────────────────────────────────
        ['module' => 'Paper Trading', 'label' => 'Akun Paper Trading', 'table' => 'paper_trading.paper_account', 'min_expected' => 0],
        ['module' => 'Paper Trading', 'label' => 'Order Paper Trading', 'table' => 'paper_trading.paper_order', 'min_expected' => 0],
        ['module' => 'Paper Trading', 'label' => 'Posisi Paper Trading', 'table' => 'paper_trading.paper_position', 'min_expected' => 0],

        // ── AI Engine ──────────────────────────────────────────────────
        ['module' => 'AI Engine', 'label' => 'Analisis AI', 'table' => 'ai_engine.ai_analysis', 'min_expected' => 0],
        ['module' => 'AI Engine', 'label' => 'Model Run AI', 'table' => 'ai_engine.ai_model_run', 'min_expected' => 0],

        // ── Governance ─────────────────────────────────────────────────
        ['module' => 'Governance', 'label' => 'Audit Log', 'table' => 'governance.audit_log', 'min_expected' => 0],
    ];

    public function __construct()
    {
        $this->db = MySqlConnection::getInstance();
    }

    /**
     * Run a full data completeness scan across all critical tables.
     *
     * @return array{
     *   summary: array{total_tables: int, populated: int, empty: int, stale: int, completeness_pct: float},
     *   modules: array<string, array{tables: array, module_status: string}>,
     *   missing_instruments: array,
     *   recommendations: array<string>
     * }
     */
    public function checkAll(): array
    {
        $tableResults = [];
        $modules = [];
        $populated = 0;
        $empty = 0;
        $stale = 0;
        $totalTables = count($this->criticalTables);

        foreach ($this->criticalTables as $entry) {
            $result = $this->checkTable($entry['table'], $entry['min_expected']);
            $result['label'] = $entry['label'];
            $result['module'] = $entry['module'];
            $result['min_expected'] = $entry['min_expected'];

            if ($result['row_count'] > 0) {
                $populated++;
                if ($result['is_stale'] ?? false) {
                    $stale++;
                }
            } else {
                $empty++;
            }

            $modules[$entry['module']]['tables'][] = $result;
        }

        // Determine module status
        foreach ($modules as $moduleName => &$moduleData) {
            $allEmpty = true;
            $anyCritical = false;
            foreach ($moduleData['tables'] as $t) {
                if ($t['row_count'] > 0) {
                    $allEmpty = false;
                }
                if ($t['min_expected'] > 0 && $t['row_count'] < $t['min_expected']) {
                    $anyCritical = true;
                }
            }
            $moduleData['module_status'] = $allEmpty ? 'EMPTY' : ($anyCritical ? 'INCOMPLETE' : 'OK');
        }
        unset($moduleData);

        // Check which instruments have no OHLCV data
        $missingInstruments = $this->findInstrumentsWithoutOhlcv();

        // Generate recommendations
        $recommendations = $this->generateRecommendations($modules, $missingInstruments, $empty);

        $completenessPct = $totalTables > 0 ? round(($populated / $totalTables) * 100, 1) : 0.0;

        return [
            'summary' => [
                'total_tables' => $totalTables,
                'populated' => $populated,
                'empty' => $empty,
                'stale' => $stale,
                'completeness_pct' => $completenessPct,
            ],
            'modules' => $modules,
            'missing_instruments' => $missingInstruments,
            'recommendations' => $recommendations,
            'checked_at' => date('c'),
        ];
    }

    /**
     * Check a single table: row count, latest record date, staleness.
     */
    private function checkTable(string $table, int $minExpected): array
    {
        $result = [
            'table' => $table,
            'row_count' => 0,
            'latest_record_date' => null,
            'is_stale' => false,
            'status' => 'EMPTY',
        ];

        try {
            $countStmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM {$table}");
            $countStmt->execute();
            $result['row_count'] = (int) $countStmt->fetchColumn();

            // Try to find a date column for staleness check
            $dateColumn = $this->findDateColumn($table);
            if ($dateColumn !== null && $result['row_count'] > 0) {
                $dateStmt = $this->db->prepare("SELECT MAX(`{$dateColumn}`) AS latest FROM {$table}");
                $dateStmt->execute();
                $latest = $dateStmt->fetchColumn();
                $result['latest_record_date'] = $latest ?: null;

                if ($result['latest_record_date']) {
                    $daysSince = (time() - strtotime($result['latest_record_date'])) / 86400;
                    $result['is_stale'] = $daysSince > 7;
                    $result['days_since_last_record'] = (int) $daysSince;
                }
            }

            // Determine status
            if ($result['row_count'] === 0) {
                $result['status'] = 'EMPTY';
            } elseif ($result['row_count'] < $minExpected) {
                $result['status'] = 'INSUFFICIENT';
            } elseif ($result['is_stale']) {
                $result['status'] = 'STALE';
            } else {
                $result['status'] = 'OK';
            }
        } catch (\Throwable $e) {
            $result['status'] = 'TABLE_NOT_FOUND';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Find a date-like column in a table for staleness checking.
     */
    private function findDateColumn(string $table): ?string
    {
        $dateColumns = [
            'trade_date', 'created_at', 'updated_at', 'assessment_date',
            'event_date', 'execution_date', 'order_date', 'signal_date',
            'metric_date', 'snapshot_date', 'run_date', 'analysis_date',
            'statement_date', 'news_date', 'calendar_date', 'ex_date',
            'target_date', 'transaction_date', 'timestamp',
        ];

        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table}");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columnNames = array_map(fn($c) => $c['Field'], $columns);

            foreach ($dateColumns as $candidate) {
                if (in_array($candidate, $columnNames, true)) {
                    return $candidate;
                }
            }

            // Fallback: look for any column with 'date' or 'time' in the name
            foreach ($columnNames as $col) {
                if (str_contains(strtolower($col), 'date') || str_contains(strtolower($col), 'time')) {
                    return $col;
                }
            }
        } catch (\Throwable) {
            // Table might not exist
        }

        return null;
    }

    /**
     * Find instruments that have no OHLCV data.
     */
    private function findInstrumentsWithoutOhlcv(): array
    {
        $missing = [];

        try {
            $stmt = $this->db->prepare(
                'SELECT i.instrument_id, i.symbol, i.asset_class, i.instrument_type
                 FROM market_master.instrument i
                 LEFT JOIN data_ingestion.ohlcv_daily o ON o.instrument_id = i.instrument_id
                 WHERE o.ohlcv_id IS NULL
                 ORDER BY i.symbol
                 LIMIT 100'
            );
            $stmt->execute();
            $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // Tables might not exist
        }

        return $missing;
    }

    /**
     * Generate actionable recommendations based on what's missing.
     *
     * @param array<string, array> $modules
     * @param array $missingInstruments
     * @param int $emptyCount
     * @return array<string>
     */
    private function generateRecommendations(array $modules, array $missingInstruments, int $emptyCount): array
    {
        $recs = [];

        // OHLCV data
        $ingestionModule = $modules['Data Ingestion']['tables'] ?? [];
        $ohlcvTable = null;
        foreach ($ingestionModule as $t) {
            if ($t['table'] === 'data_ingestion.ohlcv_daily') {
                $ohlcvTable = $t;
                break;
            }
        }

        if ($ohlcvTable !== null) {
            if ($ohlcvTable['row_count'] === 0) {
                $recs[] = 'Data OHLCV kosong. Jalankan seeder: POST /ingestion/seed-market-data untuk mengisi data historis.';
            } elseif ($ohlcvTable['row_count'] < 100) {
                $recs[] = 'Data OHLCV sangat sedikit (' . $ohlcvTable['row_count'] . ' record). Jalankan seeder untuk menambah data: POST /ingestion/seed-market-data';
            } elseif ($ohlcvTable['is_stale'] ?? false) {
                $recs[] = 'Data OHLCV sudah usang (terakhir: ' . $ohlcvTable['latest_record_date'] . '). Jalankan seeder untuk memperbarui: POST /ingestion/seed-market-data';
            }
        }

        // Missing instruments
        if (count($missingInstruments) > 0) {
            $recs[] = count($missingInstruments) . ' instrumen belum memiliki data OHLCV. Jalankan seeder untuk mengisi data: POST /ingestion/seed-market-data';
        }

        // Market Master
        $mmStatus = $modules['Market Master']['module_status'] ?? 'OK';
        if ($mmStatus === 'EMPTY') {
            $recs[] = 'Data master pasar (bursa, penerbit, instrumen) kosong. Jalankan migrasi database untuk mengisi data awal.';
        } elseif ($mmStatus === 'INCOMPLETE') {
            $recs[] = 'Data master pasar tidak lengkap. Periksa tabel market_master yang masih kosong.';
        }

        // Portfolio
        $pfStatus = $modules['Portfolio']['module_status'] ?? 'OK';
        if ($pfStatus === 'EMPTY') {
            $recs[] = 'Belum ada portofolio. Buat portofolio melalui: POST /portfolios';
        }

        // Risk
        $riskStatus = $modules['Risk']['module_status'] ?? 'OK';
        if ($riskStatus === 'EMPTY') {
            $recs[] = 'Belum ada profil risiko. Buat profil risiko melalui: POST /risk/profiles';
        }

        // Trading
        $tradingStatus = $modules['Trading']['module_status'] ?? 'OK';
        if ($tradingStatus === 'EMPTY') {
            $recs[] = 'Belum ada broker terdaftar. Tambahkan broker melalui: POST /brokers';
        }

        // Analytics
        $analyticsStatus = $modules['Analytics']['module_status'] ?? 'OK';
        if ($analyticsStatus === 'INCOMPLETE') {
            $recs[] = 'Data analytics tidak lengkap. Jalankan engine analisis untuk menghasilkan sinyal dan indikator.';
        }

        // Fundamental
        $fundStatus = $modules['Fundamental']['module_status'] ?? 'OK';
        if ($fundStatus === 'EMPTY') {
            $recs[] = 'Data fundamental (laporan keuangan, metrik) kosong. Tambahkan data fundamental untuk analisis valuasi.';
        }

        if ($emptyCount === 0) {
            $recs[] = 'Semua tabel kritis telah terisi. Periksa kestalan data secara berkala.';
        }

        return $recs;
    }
}
