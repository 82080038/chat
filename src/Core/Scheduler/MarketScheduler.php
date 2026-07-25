<?php

declare(strict_types=1);

namespace Platform\Core\Scheduler;

use PDO;
use Platform\Core\Database\MySqlConnection;
use Platform\Core\Application;
use Platform\Core\System\SystemEnvironment;
use Platform\Analytics\AnalyticsService;
use Platform\Analytics\AnalyticsServiceInterface;
use Platform\Risk\RiskService;
use Platform\Risk\RiskServiceInterface;
use Platform\Portfolio\PortfolioService;
use Platform\Portfolio\PortfolioServiceInterface;

/**
 * Market Activity Scheduler — determines what tasks should run and when,
 * based on global market sessions and IDX trading hours in GMT+7 (Asia/Jakarta).
 *
 * Design:
 * - Global markets influence each other (e.g. Tokyo→London overlap affects IDX).
 * - Tasks are scheduled based on market phase, not fixed clock times.
 * - The scheduler knows which sessions are active and which tasks are due.
 */
final class MarketScheduler
{
    private PDO $db;

    /** All times in GMT+7 (WIB) */
    private const TZ_OFFSET_HOURS = 7;

    /**
     * Global market sessions (times in GMT+7/WIB).
     * These define when global markets are open relative to Jakarta time.
     */
    private array $globalSessions = [
        [
            'code' => 'SYDNEY',
            'name' => 'Bursa Australia (ASX)',
            'open' => '05:00',
            'close' => '14:00',
            'region' => 'Asia-Pasifik',
            'influence' => 'Komoditas, mineral — mempengaruhi saham tambang IDX',
        ],
        [
            'code' => 'TOKYO',
            'name' => 'Bursa Tokyo (TSE)',
            'open' => '07:00',
            'close' => '16:00',
            'region' => 'Asia',
            'influence' => 'Ekspor Jepang, yen — mempengaruhi saham manufaktur IDX',
        ],
        [
            'code' => 'IDX',
            'name' => 'Bursa Efek Indonesia (IDX)',
            'open' => '09:00',
            'close' => '15:50',
            'region' => 'Indonesia',
            'influence' => 'Pasar utama — semua perhitungan fokus di sini',
            'sessions' => [
                ['name' => 'Pra-Pembukaan', 'start' => '08:45', 'end' => '09:00'],
                ['name' => 'Sesi Reguler 1', 'start' => '09:00', 'end' => '11:30'],
                ['name' => 'Istirahat', 'start' => '11:30', 'end' => '13:30'],
                ['name' => 'Sesi Reguler 2', 'start' => '13:30', 'end' => '15:50'],
                ['name' => 'Penutupan', 'start' => '15:50', 'end' => '16:00'],
            ],
        ],
        [
            'code' => 'LONDON',
            'name' => 'Bursa London (LSE)',
            'open' => '15:00',
            'close' => '00:00',
            'region' => 'Eropa',
            'influence' => 'Likuiditas global, pound — mempengaruhi aliran modal ke emerging markets',
        ],
        [
            'code' => 'NEW_YORK',
            'name' => 'Bursa New York (NYSE)',
            'open' => '21:00',
            'close' => '06:00',
            'region' => 'Amerika',
            'influence' => 'Sentimen global, USD — mempengaruhi semua pasar emerging termasuk IDX',
        ],
    ];

    /**
     * Scheduled tasks — each tied to a market phase.
     * The scheduler checks which tasks are due based on current market state.
     */
    private array $taskDefinitions = [
        [
            'id' => 'pre_market_global_scan',
            'name' => 'Pemindaian Pasar Global Pra-Buka',
            'description' => 'Analisis penutupan pasar AS semalam dan sesi Asia yang sedang berjalan',
            'phase' => 'PRE_MARKET',
            'time_window' => ['start' => '07:00', 'end' => '08:45'],
            'frequency' => 'DAILY',
            'actions' => ['global_factors', 'rupiah_pressure', 'flow_confirmation'],
            'depends_on_sessions' => ['SYDNEY', 'TOKYO'],
        ],
        [
            'id' => 'idx_opening_signals',
            'name' => 'Sinyal Pembukaan IDX',
            'description' => 'Hasilkan sinyal trading untuk semua instrumen aktif saat pembukaan',
            'phase' => 'IDX_REGULAR_1',
            'time_window' => ['start' => '09:00', 'end' => '09:30'],
            'frequency' => 'DAILY',
            'actions' => ['generate_signals', 'technical_indicators'],
            'depends_on_sessions' => ['IDX', 'TOKYO'],
        ],
        [
            'id' => 'idx_morning_screening',
            'name' => 'Screening Pagi',
            'description' => 'Jalankan mesin screening multi-faktor untuk identifikasi peluang',
            'phase' => 'IDX_REGULAR_1',
            'time_window' => ['start' => '09:30', 'end' => '11:00'],
            'frequency' => 'DAILY',
            'actions' => ['run_screening'],
            'depends_on_sessions' => ['IDX', 'TOKYO'],
        ],
        [
            'id' => 'idx_morning_composite',
            'name' => 'Skor Komposit Pagi',
            'description' => 'Hitung composite score untuk semua instrumen yang di-screening',
            'phase' => 'IDX_REGULAR_1',
            'time_window' => ['start' => '10:00', 'end' => '11:30'],
            'frequency' => 'DAILY',
            'actions' => ['composite_scores'],
            'depends_on_sessions' => ['IDX'],
        ],
        [
            'id' => 'idx_lunch_risk',
            'name' => 'Penilaian Risiko Makan Siang',
            'description' => 'Evaluasi risiko portofolio selama istirahat IDX',
            'phase' => 'IDX_LUNCH',
            'time_window' => ['start' => '11:30', 'end' => '13:30'],
            'frequency' => 'DAILY',
            'actions' => ['risk_assessment', 'regime_detection'],
            'depends_on_sessions' => ['IDX', 'TOKYO'],
        ],
        [
            'id' => 'idx_afternoon_signals',
            'name' => 'Sinyal Sore IDX',
            'description' => 'Sinyal trading diperbarui untuk sesi reguler 2',
            'phase' => 'IDX_REGULAR_2',
            'time_window' => ['start' => '13:30', 'end' => '14:00'],
            'frequency' => 'DAILY',
            'actions' => ['generate_signals', 'technical_indicators'],
            'depends_on_sessions' => ['IDX', 'TOKYO', 'LONDON'],
        ],
        [
            'id' => 'idx_afternoon_screening',
            'name' => 'Screening Sore',
            'description' => 'Screening ulang dengan data sesi 2 + pengaruh London',
            'phase' => 'IDX_REGULAR_2',
            'time_window' => ['start' => '14:00', 'end' => '15:30'],
            'frequency' => 'DAILY',
            'actions' => ['run_screening', 'composite_scores'],
            'depends_on_sessions' => ['IDX', 'LONDON'],
        ],
        [
            'id' => 'idx_closing_analysis',
            'name' => 'Analisis Penutupan IDX',
            'description' => 'Analisis penutupan: regime pasar, faktor global, ringkasan harian',
            'phase' => 'IDX_CLOSING',
            'time_window' => ['start' => '15:50', 'end' => '16:30'],
            'frequency' => 'DAILY',
            'actions' => ['regime_detection', 'global_factors', 'rupiah_pressure', 'flow_confirmation'],
            'depends_on_sessions' => ['IDX', 'LONDON'],
        ],
        [
            'id' => 'post_market_risk',
            'name' => 'Penilaian Risiko Pasca-Pasar',
            'description' => 'Full risk assessment untuk semua portofolio setelah penutupan',
            'phase' => 'POST_MARKET',
            'time_window' => ['start' => '16:30', 'end' => '18:00'],
            'frequency' => 'DAILY',
            'actions' => ['risk_assessment', 'portfolio_analysis'],
            'depends_on_sessions' => ['LONDON'],
        ],
        [
            'id' => 'london_ny_overlap',
            'name' => 'Analisis Overlap London-New York',
            'description' => 'Analisis aliran modal global saat London & NY buka bersamaan',
            'phase' => 'GLOBAL_OVERLAP',
            'time_window' => ['start' => '21:00', 'end' => '00:00'],
            'frequency' => 'DAILY',
            'actions' => ['global_factors', 'flow_confirmation', 'rupiah_pressure'],
            'depends_on_sessions' => ['LONDON', 'NEW_YORK'],
        ],
        [
            'id' => 'overnight_prep',
            'name' => 'Persiapan Semalam',
            'description' => 'Persiapan untuk hari berikutnya: update data, pre-compute indikator',
            'phase' => 'OVERNIGHT',
            'time_window' => ['start' => '01:00', 'end' => '05:00'],
            'frequency' => 'DAILY',
            'actions' => ['technical_indicators', 'regime_detection'],
            'depends_on_sessions' => ['NEW_YORK'],
        ],
    ];

    public function __construct()
    {
        $this->db = MySqlConnection::getInstance();
    }

    /**
     * Get current scheduler status: active sessions, current phase, due tasks.
     */
    public function getStatus(): array
    {
        $now = $this->getJakartaTime();
        $minutes = $now['hour'] * 60 + $now['minute'];
        $dayOfWeek = $now['day_of_week'];
        $isWeekend = $dayOfWeek === 0 || $dayOfWeek === 6;

        $activeSessions = [];
        foreach ($this->globalSessions as $session) {
            $isActive = $this->isSessionActive($session, $minutes);
            if ($isActive) {
                $activeSessions[] = [
                    'code' => $session['code'],
                    'name' => $session['name'],
                    'region' => $session['region'],
                    'influence' => $session['influence'],
                    'close' => $session['close'],
                ];
            }
        }

        $idxPhase = $this->getIdxPhase($minutes, $isWeekend);
        $dueTasks = $this->getDueTasks($minutes, $isWeekend, $idxPhase);
        $nextTask = $this->getNextTask($minutes, $isWeekend);

        // Session overlaps
        $overlaps = $this->getSessionOverlaps($activeSessions);

        return [
            'current_time' => $now['time_str'],
            'current_date' => $now['date_str'],
            'day_of_week' => $now['weekday'],
            'is_weekend' => $isWeekend,
            'is_trading_day' => !$isWeekend,
            'idx_phase' => $idxPhase,
            'active_sessions' => $activeSessions,
            'session_overlaps' => $overlaps,
            'due_tasks' => $dueTasks,
            'next_task' => $nextTask,
            'all_sessions' => $this->formatAllSessions($minutes),
            'all_tasks' => $this->formatAllTasks($minutes, $isWeekend),
            'system_capabilities' => SystemEnvironment::getInstance()->getCapabilities(),
        ];
    }

    /**
     * Run all due tasks now.
     */
    public function runDueTasks(): array
    {
        $now = $this->getJakartaTime();
        $minutes = $now['hour'] * 60 + $now['minute'];
        $isWeekend = $now['day_of_week'] === 0 || $now['day_of_week'] === 6;
        $idxPhase = $this->getIdxPhase($minutes, $isWeekend);
        $dueTasks = $this->getDueTasks($minutes, $isWeekend, $idxPhase);

        $results = [];
        foreach ($dueTasks as $task) {
            $results[] = $this->executeTask($task);
        }

        return [
            'executed_at' => date('c'),
            'tasks_run' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Run a specific task by ID.
     */
    public function runTask(string $taskId): array
    {
        foreach ($this->taskDefinitions as $task) {
            if ($task['id'] === $taskId) {
                return $this->executeTask($task);
            }
        }
        return ['error' => "Task '{$taskId}' not found"];
    }

    /**
     * Get the full schedule definition.
     */
    public function getSchedule(): array
    {
        return [
            'global_sessions' => $this->globalSessions,
            'tasks' => $this->taskDefinitions,
            'timezone' => 'Asia/Jakarta (GMT+7)',
        ];
    }

    // ─── Private Methods ──────────────────────────────────────────────

    private function executeTask(array $task): array
    {
        $startTime = microtime(true);
        $actionsRun = [];
        $errors = [];

        foreach ($task['actions'] as $action) {
            try {
                $result = $this->executeAction($action);
                $actionsRun[] = [
                    'action' => $action,
                    'status' => 'OK',
                    'summary' => $result,
                ];
            } catch (\Throwable $e) {
                $actionsRun[] = [
                    'action' => $action,
                    'status' => 'ERROR',
                    'error' => $e->getMessage(),
                ];
                $errors[] = $action . ': ' . $e->getMessage();
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        return [
            'task_id' => $task['id'],
            'task_name' => $task['name'],
            'phase' => $task['phase'],
            'actions_run' => count($actionsRun),
            'errors' => count($errors),
            'elapsed_seconds' => $elapsed,
            'details' => $actionsRun,
        ];
    }

    private function executeAction(string $action): array
    {
        $app = Application::getInstance();

        switch ($action) {
            case 'generate_signals':
                return $this->actionGenerateSignals($app);

            case 'technical_indicators':
                return $this->actionTechnicalIndicators($app);

            case 'run_screening':
                return $this->actionRunScreening($app);

            case 'composite_scores':
                return $this->actionCompositeScores($app);

            case 'regime_detection':
                return $this->actionRegimeDetection($app);

            case 'global_factors':
                return $this->actionGlobalFactors($app);

            case 'rupiah_pressure':
                return $this->actionRupiahPressure($app);

            case 'flow_confirmation':
                return $this->actionFlowConfirmation($app);

            case 'risk_assessment':
                return $this->actionRiskAssessment($app);

            case 'portfolio_analysis':
                return $this->actionPortfolioAnalysis($app);

            default:
                return ['status' => 'UNKNOWN_ACTION', 'action' => $action];
        }
    }

    private function getInstruments(): array
    {
        $capabilities = SystemEnvironment::getInstance()->getCapabilities();
        $limit = $capabilities['max_instruments_per_batch'] ?? 50;

        try {
            $stmt = $this->db->prepare(
                "SELECT i.instrument_id, l.ticker AS symbol
                 FROM market_master.instrument i
                 LEFT JOIN market_master.listing l ON i.instrument_id = l.instrument_id
                 WHERE i.status = 'ACTIVE'
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function actionGenerateSignals(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        $instruments = $this->getInstruments();
        $generated = 0;
        foreach ($instruments as $inst) {
            try {
                $trend = $analytics->identifyTrend($inst['instrument_id'], 20, 50);
                $rsi = $analytics->calculateRSI($inst['instrument_id'], 14);
                $direction = 'NEUTRAL';
                $signalType = 'TECHNICAL';
                $strength = 50.0;

                if ($trend['trend'] === 'UPTREND' && ($rsi['latest'] ?? 50) < 70) {
                    $direction = 'LONG';
                    $strength = 70.0;
                } elseif ($trend['trend'] === 'DOWNTREND' && ($rsi['latest'] ?? 50) > 30) {
                    $direction = 'SHORT';
                    $strength = 70.0;
                }

                if ($direction !== 'NEUTRAL') {
                    $analytics->createSignal([
                        'instrument_id' => $inst['instrument_id'],
                        'signal_type' => $signalType,
                        'direction' => $direction,
                        'strength' => $strength,
                        'timeframe' => 'DAILY',
                        'model_version' => 'scheduler-v1',
                    ]);
                    $generated++;
                }
            } catch (\Throwable) {
                // Skip individual instrument errors
            }
        }
        return ['status' => 'OK', 'signals_generated' => $generated, 'instruments_scanned' => count($instruments)];
    }

    private function actionTechnicalIndicators(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        $instruments = $this->getInstruments();
        $computed = 0;
        foreach ($instruments as $inst) {
            try {
                $analytics->getAllTechnicalIndicators($inst['instrument_id']);
                $computed++;
            } catch (\Throwable) {
                // Skip
            }
        }
        return ['status' => 'OK', 'indicators_computed' => $computed, 'instruments' => count($instruments)];
    }

    private function actionRunScreening(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        try {
            $result = $analytics->runScreening([
                'asset_class' => 'EQUITY',
                'limit' => 50,
            ]);
            return ['status' => 'OK', 'results_count' => $result['total'] ?? 0];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function actionCompositeScores(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        $instruments = $this->getInstruments();
        $computed = 0;
        foreach ($instruments as $inst) {
            try {
                $analytics->calculateCompositeScore($inst['instrument_id']);
                $computed++;
            } catch (\Throwable) {
                // Skip
            }
        }
        return ['status' => 'OK', 'scores_computed' => $computed, 'instruments' => count($instruments)];
    }

    private function actionRegimeDetection(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        $instruments = $this->getInstruments();
        $computed = 0;
        foreach ($instruments as $inst) {
            try {
                $analytics->classifyMarketRegime($inst['instrument_id']);
                $computed++;
            } catch (\Throwable) {
                // Skip
            }
        }
        return ['status' => 'OK', 'regimes_classified' => $computed, 'instruments' => count($instruments)];
    }

    private function actionGlobalFactors(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        try {
            $result = $analytics->getGlobalToIndonesiaFactors();
            return ['status' => 'OK', 'factors_count' => count($result['factors'] ?? [])];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function actionRupiahPressure(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        try {
            $analytics->calculateRupiahPressureScore();
            return ['status' => 'OK'];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function actionFlowConfirmation(Application $app): array
    {
        $analytics = $app->getService('analytics');
        if (!$analytics instanceof AnalyticsServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Analytics service unavailable'];
        }
        try {
            $analytics->calculateFlowConfirmationScore();
            return ['status' => 'OK'];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function actionRiskAssessment(Application $app): array
    {
        $risk = $app->getService('risk');
        if (!$risk instanceof RiskServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Risk service unavailable'];
        }
        try {
            $profiles = $risk->listRiskProfiles([], 1, 50);
            $assessed = 0;
            foreach ($profiles['data'] ?? [] as $profile) {
                try {
                    $risk->triggerAssessment($profile['risk_profile_id'], ['assessment_type' => 'SCHEDULED']);
                    $assessed++;
                } catch (\Throwable) {
                    // Skip
                }
            }
            return ['status' => 'OK', 'profiles_assessed' => $assessed];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    private function actionPortfolioAnalysis(Application $app): array
    {
        $portfolio = $app->getService('portfolio');
        if (!$portfolio instanceof PortfolioServiceInterface) {
            return ['status' => 'SKIP', 'reason' => 'Portfolio service unavailable'];
        }
        try {
            $portfolios = $portfolio->listPortfolios([], 1, 50);
            return ['status' => 'OK', 'portfolios_analyzed' => count($portfolios['data'] ?? [])];
        } catch (\Throwable $e) {
            return ['status' => 'ERROR', 'error' => $e->getMessage()];
        }
    }

    // ─── Time & Session Helpers ───────────────────────────────────────

    private function getJakartaTime(): array
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $jakarta = $now->setTimezone(new \DateTimeZone('Asia/Jakarta'));

        $dayMap = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return [
            'hour' => (int) $jakarta->format('H'),
            'minute' => (int) $jakarta->format('i'),
            'time_str' => $jakarta->format('H:i:s'),
            'date_str' => $jakarta->format('Y-m-d'),
            'weekday' => $dayMap[(int) $jakarta->format('w')],
            'day_of_week' => (int) $jakarta->format('w'),
        ];
    }

    private function parseTime(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }

    private function isSessionActive(array $session, int $currentMinutes): bool
    {
        $open = $this->parseTime($session['open']);
        $close = $this->parseTime($session['close']);

        if ($close > $open) {
            return $currentMinutes >= $open && $currentMinutes < $close;
        }
        // Wraps past midnight (e.g. New York 21:00-06:00)
        return $currentMinutes >= $open || $currentMinutes < $close;
    }

    private function getIdxPhase(int $minutes, bool $isWeekend): string
    {
        if ($isWeekend) {
            return 'WEEKEND';
        }

        $preOpen = $this->parseTime('08:45');
        $reg1Start = $this->parseTime('09:00');
        $reg1End = $this->parseTime('11:30');
        $reg2Start = $this->parseTime('13:30');
        $reg2End = $this->parseTime('15:50');
        $closingEnd = $this->parseTime('16:00');

        if ($minutes < $preOpen) {
            return 'PRE_MARKET';
        }
        if ($minutes < $reg1Start) {
            return 'IDX_PRE_OPEN';
        }
        if ($minutes < $reg1End) {
            return 'IDX_REGULAR_1';
        }
        if ($minutes < $reg2Start) {
            return 'IDX_LUNCH';
        }
        if ($minutes < $reg2End) {
            return 'IDX_REGULAR_2';
        }
        if ($minutes < $closingEnd) {
            return 'IDX_CLOSING';
        }
        if ($minutes < $this->parseTime('18:00')) {
            return 'POST_MARKET';
        }
        if ($minutes >= $this->parseTime('21:00') || $minutes < $this->parseTime('05:00')) {
            return 'GLOBAL_OVERLAP';
        }
        return 'OVERNIGHT';
    }

    private function getDueTasks(int $minutes, bool $isWeekend, string $idxPhase): array
    {
        $due = [];
        foreach ($this->taskDefinitions as $task) {
            if ($isWeekend && !in_array($task['phase'], ['GLOBAL_OVERLAP', 'OVERNIGHT'])) {
                continue;
            }

            $taskStart = $this->parseTime($task['time_window']['start']);
            $taskEnd = $this->parseTime($task['time_window']['end']);

            // Handle overnight wrap
            if ($taskEnd < $taskStart) {
                $inWindow = $minutes >= $taskStart || $minutes < $taskEnd;
            } else {
                $inWindow = $minutes >= $taskStart && $minutes < $taskEnd;
            }

            if ($inWindow) {
                $due[] = [
                    'id' => $task['id'],
                    'name' => $task['name'],
                    'description' => $task['description'],
                    'phase' => $task['phase'],
                    'actions' => $task['actions'],
                    'window' => $task['time_window']['start'] . ' - ' . $task['time_window']['end'] . ' WIB',
                ];
            }
        }
        return $due;
    }

    private function getNextTask(int $minutes, bool $isWeekend): ?array
    {
        $candidates = [];
        foreach ($this->taskDefinitions as $task) {
            if ($isWeekend && !in_array($task['phase'], ['GLOBAL_OVERLAP', 'OVERNIGHT'])) {
                continue;
            }
            $taskStart = $this->parseTime($task['time_window']['start']);
            if ($taskStart > $minutes) {
                $candidates[] = [
                    'id' => $task['id'],
                    'name' => $task['name'],
                    'phase' => $task['phase'],
                    'starts_at' => $task['time_window']['start'] . ' WIB',
                    'minutes_until' => $taskStart - $minutes,
                ];
            }
        }

        if (empty($candidates)) {
            // Next task is tomorrow
            $first = $this->taskDefinitions[0];
            return [
                'id' => $first['id'],
                'name' => $first['name'],
                'phase' => $first['phase'],
                'starts_at' => $first['time_window']['start'] . ' WIB (besok)',
                'minutes_until' => (24 * 60 - $minutes) + $this->parseTime($first['time_window']['start']),
            ];
        }

        usort($candidates, fn($a, $b) => $a['minutes_until'] <=> $b['minutes_until']);
        return $candidates[0];
    }

    private function getSessionOverlaps(array $activeSessions): array
    {
        $overlaps = [];
        $codes = array_column($activeSessions, 'code');
        $overlapPairs = [
            ['SYDNEY', 'TOKYO', 'Overlap Asia-Pasifik'],
            ['TOKYO', 'IDX', 'Overlap Asia Tenggara'],
            ['IDX', 'LONDON', 'Overlap Eropa-Indonesia'],
            ['LONDON', 'NEW_YORK', 'Overlap Eropa-Amerika (likuiditas tertinggi)'],
        ];

        foreach ($overlapPairs as [$a, $b, $label]) {
            if (in_array($a, $codes) && in_array($b, $codes)) {
                $overlaps[] = [
                    'sessions' => "$a + $b",
                    'label' => $label,
                    'significance' => 'Tinggi — aliran modal lintas pasar aktif',
                ];
            }
        }
        return $overlaps;
    }

    private function formatAllSessions(int $currentMinutes): array
    {
        $result = [];
        foreach ($this->globalSessions as $session) {
            $isActive = $this->isSessionActive($session, $currentMinutes);
            $result[] = [
                'code' => $session['code'],
                'name' => $session['name'],
                'open' => $session['open'] . ' WIB',
                'close' => $session['close'] . ' WIB',
                'region' => $session['region'],
                'influence' => $session['influence'],
                'is_active' => $isActive,
            ];
        }
        return $result;
    }

    private function formatAllTasks(int $currentMinutes, bool $isWeekend): array
    {
        $result = [];
        foreach ($this->taskDefinitions as $task) {
            $taskStart = $this->parseTime($task['time_window']['start']);
            $taskEnd = $this->parseTime($task['time_window']['end']);

            if ($taskEnd < $taskStart) {
                $inWindow = $currentMinutes >= $taskStart || $currentMinutes < $taskEnd;
            } else {
                $inWindow = $currentMinutes >= $taskStart && $currentMinutes < $taskEnd;
            }

            $isPast = $currentMinutes > $taskEnd && !($taskEnd < $taskStart);

            $result[] = [
                'id' => $task['id'],
                'name' => $task['name'],
                'description' => $task['description'],
                'phase' => $task['phase'],
                'window' => $task['time_window']['start'] . ' - ' . $task['time_window']['end'] . ' WIB',
                'actions' => $task['actions'],
                'depends_on_sessions' => $task['depends_on_sessions'],
                'status' => $isWeekend ? 'WEEKEND_SKIP' : ($inWindow ? 'DUE_NOW' : ($isPast ? 'COMPLETED' : 'PENDING')),
            ];
        }
        return $result;
    }
}
