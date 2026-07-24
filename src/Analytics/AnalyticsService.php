<?php

declare(strict_types=1);

namespace Platform\Analytics;

use PDO;
use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class AnalyticsService extends BaseService implements AnalyticsServiceInterface
{
    // ─── Feature Definitions ─────────────────────────────────────────────

    public function listFeatures(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.feature_definition', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.feature_definition {$clause} "
            . "ORDER BY feature_name ASC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createFeature(array $data): array
    {
        $this->validateRequired($data, ['feature_name', 'feature_version']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.feature_definition
             (feature_id, feature_name, feature_version, description,
              calculation_method, input_dependencies, output_type, status, created_at)
             VALUES
             (:id, :name, :version, :description, :calc_method,
              :input_deps, :output_type, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['feature_name'],
            ':version' => $data['feature_version'],
            ':description' => $data['description'] ?? null,
            ':calc_method' => $data['calculation_method'] ?? null,
            ':input_deps' => isset($data['input_dependencies'])
                ? json_encode($data['input_dependencies'])
                : null,
            ':output_type' => $data['output_type'] ?? null,
            ':status' => $data['status'] ?? 'EXPERIMENTAL',
            ':now' => $now,
        ]);
        return $this->getFeature($id);
    }

    public function getFeature(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.feature_definition WHERE feature_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateFeature(string $id, array $data): array
    {
        $existing = $this->getFeature($id);
        if ($existing === null) {
            throw new ApiException(404, 'FEATURE_NOT_FOUND', 'Feature definition was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'description', 'calculation_method', 'output_type', 'status',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if (array_key_exists('input_dependencies', $data)) {
            $fields[] = 'input_dependencies = :input_deps';
            $params[':input_deps'] = json_encode($data['input_dependencies']);
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE analytics.feature_definition SET ' . implode(', ', $fields)
            . ' WHERE feature_id = :id'
        );
        $stmt->execute($params);
        return $this->getFeature($id);
    }

    // ─── Feature Values ──────────────────────────────────────────────────

    public function getFeatureValues(string $featureId, array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = ['feature_id = :feature_id'];
        $params = [':feature_id' => $featureId];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['from'])) {
            $where[] = 'timestamp >= :from_date';
            $params[':from_date'] = $filters['from'];
        }
        if (isset($filters['to'])) {
            $where[] = 'timestamp <= :to_date';
            $params[':to_date'] = $filters['to'];
        }
        $clause = 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.feature_value', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.feature_value {$clause} "
            . "ORDER BY timestamp DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function ingestFeatureValues(string $featureId, array $data): array
    {
        $this->validateRequired($data, ['values']);
        $inserted = 0;
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO analytics.feature_value
             (feature_value_id, feature_id, instrument_id, timestamp,
              value, quality_score, model_version, calculated_at)
             VALUES
             (:id, :feature_id, :instrument_id, :timestamp,
              :value, :quality, :model_version, :now)'
        );
        $now = $this->now();
        foreach ((array) $data['values'] as $entry) {
            $stmt->execute([
                ':id' => $this->uuid(),
                ':feature_id' => $featureId,
                ':instrument_id' => $entry['instrument_id'],
                ':timestamp' => $entry['timestamp'],
                ':value' => $entry['value'] ?? null,
                ':quality' => $entry['quality_score'] ?? null,
                ':model_version' => $entry['model_version'] ?? null,
                ':now' => $entry['calculated_at'] ?? $now,
            ]);
            $inserted += $stmt->rowCount();
        }
        return ['feature_id' => $featureId, 'ingested' => $inserted];
    }

    // ─── Signals ─────────────────────────────────────────────────────────

    public function listSignals(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['signal_type'])) {
            $where[] = 'signal_type = :signal_type';
            $params[':signal_type'] = $filters['signal_type'];
        }
        if (isset($filters['direction'])) {
            $where[] = 'direction = :direction';
            $params[':direction'] = $filters['direction'];
        }
        if (isset($filters['status'])) {
            if ($filters['status'] === 'ACTIVE') {
                $where[] = 'invalidated_at IS NULL AND (valid_until IS NULL OR valid_until > :now)';
                $params[':now'] = $this->now();
            } else {
                $where[] = 'invalidated_at IS NOT NULL';
            }
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.signal', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.signal {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createSignal(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'signal_type', 'direction', 'timeframe']);
        $this->assertDirection((string) $data['direction']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.signal
             (signal_id, instrument_id, signal_type, direction, strength,
              timeframe, model_version, created_at, valid_from, valid_until)
             VALUES
             (:id, :instrument_id, :signal_type, :direction, :strength,
              :timeframe, :model_version, :now, :valid_from, :valid_until)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':signal_type' => $data['signal_type'],
            ':direction' => $data['direction'],
            ':strength' => $data['strength'] ?? null,
            ':timeframe' => $data['timeframe'],
            ':model_version' => $data['model_version'] ?? null,
            ':now' => $now,
            ':valid_from' => $data['valid_from'] ?? $now,
            ':valid_until' => $data['valid_until'] ?? null,
        ]);
        return $this->getSignal($id);
    }

    public function getSignal(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM analytics.signal WHERE signal_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getActiveSignals(string $instrumentId): array
    {
        $now = $this->now();
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.signal
             WHERE instrument_id = :instrument_id
               AND invalidated_at IS NULL
               AND (valid_until IS NULL OR valid_until > :now)
             ORDER BY created_at DESC'
        );
        $stmt->execute([':instrument_id' => $instrumentId, ':now' => $now]);
        return $stmt->fetchAll();
    }

    public function invalidateSignal(string $id, string $reason): array
    {
        $existing = $this->getSignal($id);
        if ($existing === null) {
            throw new ApiException(404, 'SIGNAL_NOT_FOUND', 'Signal was not found');
        }
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE analytics.signal
             SET invalidated_at = :now, invalidated_reason = :reason, valid_until = :now
             WHERE signal_id = :id'
        );
        $stmt->execute([':now' => $now, ':reason' => $reason, ':id' => $id]);
        return $this->getSignal($id);
    }

    // ─── Forecasts ───────────────────────────────────────────────────────

    public function listForecasts(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['target_variable'])) {
            $where[] = 'target_variable = :target_variable';
            $params[':target_variable'] = $filters['target_variable'];
        }
        if (isset($filters['model_version'])) {
            $where[] = 'model_version = :model_version';
            $params[':model_version'] = $filters['model_version'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.forecast', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.forecast {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createForecast(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'target_variable', 'horizon']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.forecast
             (forecast_id, instrument_id, target_variable, horizon,
              prediction_value, confidence_interval_low, confidence_interval_high,
              confidence, model_version, feature_snapshot_id, created_at, valid_until)
             VALUES
             (:id, :instrument_id, :target_variable, :horizon,
              :prediction, :ci_low, :ci_high, :confidence,
              :model_version, :feature_snapshot_id, :now, :valid_until)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':target_variable' => $data['target_variable'],
            ':horizon' => $data['horizon'],
            ':prediction' => $data['prediction_value'] ?? null,
            ':ci_low' => $data['confidence_interval_low'] ?? null,
            ':ci_high' => $data['confidence_interval_high'] ?? null,
            ':confidence' => $data['confidence'] ?? null,
            ':model_version' => $data['model_version'] ?? null,
            ':feature_snapshot_id' => $data['feature_snapshot_id'] ?? null,
            ':now' => $now,
            ':valid_until' => $data['valid_until'] ?? null,
        ]);
        return $this->getForecast($id);
    }

    public function getForecast(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM analytics.forecast WHERE forecast_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLatestForecast(string $instrumentId, string $targetVariable): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.forecast
             WHERE instrument_id = :instrument_id
               AND target_variable = :target_variable
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([
            ':instrument_id' => $instrumentId,
            ':target_variable' => $targetVariable,
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    // ─── Recommendations ─────────────────────────────────────────────────

    public function listRecommendations(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['action'])) {
            $where[] = 'action = :action';
            $params[':action'] = $filters['action'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['min_confidence'])) {
            $where[] = 'confidence >= :min_confidence';
            $params[':min_confidence'] = $filters['min_confidence'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.recommendation', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.recommendation {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createRecommendation(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'action']);
        $this->assertAction((string) $data['action']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.recommendation
             (recommendation_id, instrument_id, action, thesis, confidence,
              confidence_level, horizon, model_version, signal_ids, forecast_ids,
              created_at, valid_until, status)
             VALUES
             (:id, :instrument_id, :action, :thesis, :confidence,
              :confidence_level, :horizon, :model_version, :signal_ids,
              :forecast_ids, :now, :valid_until, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':action' => $data['action'],
            ':thesis' => $data['thesis'] ?? null,
            ':confidence' => $data['confidence'] ?? null,
            ':confidence_level' => $data['confidence_level'] ?? null,
            ':horizon' => $data['horizon'] ?? null,
            ':model_version' => $data['model_version'] ?? null,
            ':signal_ids' => isset($data['signal_ids'])
                ? json_encode($data['signal_ids'])
                : null,
            ':forecast_ids' => isset($data['forecast_ids'])
                ? json_encode($data['forecast_ids'])
                : null,
            ':now' => $now,
            ':valid_until' => $data['valid_until'] ?? null,
            ':status' => $data['status'] ?? 'ACTIVE',
        ]);
        return $this->getRecommendation($id);
    }

    public function getRecommendation(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.recommendation WHERE recommendation_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['signals'] = $this->fetchLinkedSignals($row);
        $row['forecasts'] = $this->fetchLinkedForecasts($row);
        return $row;
    }

    public function getLatestRecommendation(string $instrumentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.recommendation
             WHERE instrument_id = :instrument_id AND status = :status
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([
            ':instrument_id' => $instrumentId,
            ':status' => 'ACTIVE',
        ]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['signals'] = $this->fetchLinkedSignals($row);
        $row['forecasts'] = $this->fetchLinkedForecasts($row);
        return $row;
    }

    // ─── Scores ──────────────────────────────────────────────────────────

    public function listScores(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['instrument_id'])) {
            $where[] = 'instrument_id = :instrument_id';
            $params[':instrument_id'] = $filters['instrument_id'];
        }
        if (isset($filters['score_type'])) {
            $where[] = 'score_type = :score_type';
            $params[':score_type'] = $filters['score_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.score', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.score {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createScore(array $data): array
    {
        $this->validateRequired($data, ['instrument_id', 'score_type']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.score
             (score_id, instrument_id, score_type, value, component_scores,
              model_version, created_at, valid_until)
             VALUES
             (:id, :instrument_id, :score_type, :value, :component_scores,
              :model_version, :now, :valid_until)'
        );
        $stmt->execute([
            ':id' => $id,
            ':instrument_id' => $data['instrument_id'],
            ':score_type' => $data['score_type'],
            ':value' => $data['value'] ?? null,
            ':component_scores' => isset($data['component_scores'])
                ? json_encode($data['component_scores'])
                : null,
            ':model_version' => $data['model_version'] ?? null,
            ':now' => $now,
            ':valid_until' => $data['valid_until'] ?? null,
        ]);
        return $this->getScore($id);
    }

    public function getScore(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM analytics.score WHERE score_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getInstrumentScores(string $instrumentId, ?string $scoreType): array
    {
        if ($scoreType !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM analytics.score
                 WHERE instrument_id = :instrument_id AND score_type = :score_type
                 ORDER BY created_at DESC'
            );
            $stmt->execute([
                ':instrument_id' => $instrumentId,
                ':score_type' => $scoreType,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM analytics.score
                 WHERE instrument_id = :instrument_id
                 ORDER BY score_type, created_at DESC'
            );
            $stmt->execute([':instrument_id' => $instrumentId]);
        }
        return $stmt->fetchAll();
    }

    // ─── Model Registry ──────────────────────────────────────────────────

    public function listModels(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['model_type'])) {
            $where[] = 'model_type = :model_type';
            $params[':model_type'] = $filters['model_type'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.model_registry', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.model_registry {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createModel(array $data): array
    {
        $this->validateRequired($data, ['model_name', 'model_version']);
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.model_registry
             (model_id, model_name, model_version, model_type, description,
              storage_object_id, training_dataset_id, metrics, status, deployed_at, created_at)
             VALUES
             (:id, :name, :version, :type, :description,
              :storage_object_id, :training_dataset_id, :metrics, :status, :deployed_at, :now)'
        );
        $deployedAt = null;
        if (($data['status'] ?? 'DRAFT') === 'DEPLOYED') {
            $deployedAt = $now;
        }
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['model_name'],
            ':version' => $data['model_version'],
            ':type' => $data['model_type'] ?? null,
            ':description' => $data['description'] ?? null,
            ':storage_object_id' => $data['storage_object_id'] ?? null,
            ':training_dataset_id' => $data['training_dataset_id'] ?? null,
            ':metrics' => isset($data['metrics']) ? json_encode($data['metrics']) : null,
            ':status' => $data['status'] ?? 'DRAFT',
            ':deployed_at' => $deployedAt,
            ':now' => $now,
        ]);
        return $this->getModel($id);
    }

    public function getModel(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.model_registry WHERE model_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateModel(string $id, array $data): array
    {
        $existing = $this->getModel($id);
        if ($existing === null) {
            throw new ApiException(404, 'MODEL_NOT_FOUND', 'Model was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'model_type', 'description', 'storage_object_id',
            'training_dataset_id', 'status',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if (array_key_exists('metrics', $data)) {
            $fields[] = 'metrics = :metrics';
            $params[':metrics'] = json_encode($data['metrics']);
        }
        if (array_key_exists('status', $data) && $data['status'] === 'DEPLOYED' && $existing['deployed_at'] === null) {
            $fields[] = 'deployed_at = :deployed_at';
            $params[':deployed_at'] = $this->now();
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE analytics.model_registry SET ' . implode(', ', $fields)
            . ' WHERE model_id = :id'
        );
        $stmt->execute($params);
        return $this->getModel($id);
    }

    // ─── Backtests ───────────────────────────────────────────────────────

    public function listBacktests(array $filters, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );
        $where = [];
        $params = [];
        if (isset($filters['strategy_name'])) {
            $where[] = 'strategy_name = :strategy_name';
            $params[':strategy_name'] = $filters['strategy_name'];
        }
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        $clause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = $this->countRows('analytics.backtest_run', $clause, $params);
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.backtest_run {$clause} "
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function createBacktest(array $data): array
    {
        $this->validateRequired(
            $data,
            ['strategy_name', 'strategy_version', 'start_date', 'end_date', 'initial_capital']
        );
        $id = $this->uuid();
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO analytics.backtest_run
             (backtest_id, strategy_name, strategy_version, model_id,
              portfolio_id, start_date, end_date, initial_capital,
              parameters, status, created_at)
             VALUES
             (:id, :strategy_name, :strategy_version, :model_id,
              :portfolio_id, :start_date, :end_date, :initial_capital,
              :parameters, :status, :now)'
        );
        $stmt->execute([
            ':id' => $id,
            ':strategy_name' => $data['strategy_name'],
            ':strategy_version' => $data['strategy_version'],
            ':model_id' => $data['model_id'] ?? null,
            ':portfolio_id' => $data['portfolio_id'] ?? null,
            ':start_date' => $data['start_date'],
            ':end_date' => $data['end_date'],
            ':initial_capital' => $data['initial_capital'],
            ':parameters' => isset($data['parameters']) ? json_encode($data['parameters']) : null,
            ':status' => 'PENDING',
            ':now' => $now,
        ]);
        return $this->getBacktest($id);
    }

    public function getBacktest(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM analytics.backtest_run WHERE backtest_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getBacktestStatus(string $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT backtest_id, status, created_at FROM analytics.backtest_run
             WHERE backtest_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function updateBacktestResults(string $id, array $data): array
    {
        $existing = $this->getBacktest($id);
        if ($existing === null) {
            throw new ApiException(404, 'BACKTEST_NOT_FOUND', 'Backtest run was not found');
        }
        $fields = [];
        $params = [':id' => $id];
        foreach (
            [
            'final_capital', 'returns', 'sharpe_ratio',
            'max_drawdown', 'win_rate', 'results_object_id', 'status',
            ] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($fields === []) {
            return $existing;
        }
        $stmt = $this->db->prepare(
            'UPDATE analytics.backtest_run SET ' . implode(', ', $fields)
            . ' WHERE backtest_id = :id'
        );
        $stmt->execute($params);
        return $this->getBacktest($id);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private function fetchLinkedSignals(array $recommendation): array
    {
        $signalIds = json_decode($recommendation['signal_ids'] ?? '[]', true);
        if (!is_array($signalIds) || $signalIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($signalIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.signal WHERE signal_id IN ({$placeholders})"
        );
        $stmt->execute($signalIds);
        return $stmt->fetchAll();
    }

    private function fetchLinkedForecasts(array $recommendation): array
    {
        $forecastIds = json_decode($recommendation['forecast_ids'] ?? '[]', true);
        if (!is_array($forecastIds) || $forecastIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($forecastIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM analytics.forecast WHERE forecast_id IN ({$placeholders})"
        );
        $stmt->execute($forecastIds);
        return $stmt->fetchAll();
    }

    private function assertDirection(string $direction): void
    {
        $valid = ['BULLISH', 'BEARISH', 'NEUTRAL'];
        if (!in_array($direction, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid direction. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function assertAction(string $action): void
    {
        $valid = ['BUY', 'HOLD', 'SELL', 'ABSTAIN', 'NO_ACTION'];
        if (!in_array($action, $valid, true)) {
            throw new ApiException(
                422,
                'VALIDATION_ERROR',
                'Invalid action. Must be one of: ' . implode(', ', $valid)
            );
        }
    }

    private function validateRequired(array $data, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                $errors[$field][] = 'This field is required';
            }
        }
        if ($errors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Required fields are missing', $errors);
        }
    }

    private function countRows(string $table, string $clause = '', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} {$clause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
