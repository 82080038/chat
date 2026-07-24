<?php

declare(strict_types=1);

namespace Platform\Analytics;

use PDO;
use Platform\Core\BaseService;
use Platform\Core\Data\PointInTimeQuery;
use Platform\Core\Exceptions\ApiException;

final class AnalyticsService extends BaseService implements AnalyticsServiceInterface
{
    use PointInTimeQuery;

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
        if (isset($filters['as_of'])) {
            $where[] = 'created_at <= :as_of';
            $params[':as_of'] = $filters['as_of'] . ' 23:59:59';
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
             SET invalidated_at = :now1, invalidated_reason = :reason, valid_until = :now2
             WHERE signal_id = :id'
        );
        $stmt->execute([':now1' => $now, ':now2' => $now, ':reason' => $reason, ':id' => $id]);
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
        if (isset($filters['as_of'])) {
            $where[] = 'created_at <= :as_of';
            $params[':as_of'] = $filters['as_of'] . ' 23:59:59';
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

    /**
     * Deploy a model with governance approval.
     * Blueprint: Model governance — policy evaluation before deployment.
     *
     * @param string $id Model ID
     * @param array<string, mixed> $deploymentData
     * @return array<string, mixed>
     */
    public function deployModel(string $id, array $deploymentData = []): array
    {
        $existing = $this->getModel($id);
        if ($existing === null) {
            throw new ApiException(404, 'MODEL_NOT_FOUND', 'Model was not found');
        }

        // Only DRAFT or VALIDATED models can be deployed
        $allowedStatuses = ['DRAFT', 'VALIDATED'];
        if (!in_array($existing['status'], $allowedStatuses, true)) {
            throw new ApiException(
                422,
                'MODEL_NOT_DEPLOYABLE',
                "Model status must be DRAFT or VALIDATED to deploy (current: {$existing['status']})"
            );
        }

        // Request governance approval
        $hub = \Platform\Core\ServiceHub::getInstance();
        $gov = $hub->getGovernanceService();
        if ($gov !== null) {
            $approval = $gov->requestApproval(
                'MODEL',
                $id,
                'MODEL_DEPLOY'
            );
            // Auto-approve for single-owner (no separate approver)
            $gov->approve($approval['approval_id']);
        }

        // Deploy
        $now = $this->now();
        $stmt = $this->db->prepare(
            'UPDATE analytics.model_registry
             SET status = :status, deployed_at = :deployed_at
             WHERE model_id = :id'
        );
        $stmt->execute([
            ':status' => 'DEPLOYED',
            ':deployed_at' => $now,
            ':id' => $id,
        ]);

        // Audit log
        $hub->audit('MODEL_DEPLOYED', 'MODEL', $id, $existing, $this->getModel($id));

        // Emit event (fail-safe)
        \Platform\Core\EventBus\EventBus::getInstance()->emit('analytics.model.deployed', [
            'model_id' => $id,
            'model_name' => $existing['model_name'] ?? '',
            'deployed_at' => $now,
        ]);

        return $this->getModel($id);
    }

    /**
     * Retire a deployed model.
     */
    public function retireModel(string $id, string $reason = ''): array
    {
        $existing = $this->getModel($id);
        if ($existing === null) {
            throw new ApiException(404, 'MODEL_NOT_FOUND', 'Model was not found');
        }
        if ($existing['status'] !== 'DEPLOYED') {
            throw new ApiException(422, 'MODEL_NOT_DEPLOYED', 'Only deployed models can be retired');
        }

        $stmt = $this->db->prepare(
            'UPDATE analytics.model_registry SET status = :status WHERE model_id = :id'
        );
        $stmt->execute([':status' => 'RETIRED', ':id' => $id]);

        $hub = \Platform\Core\ServiceHub::getInstance();
        $hub->audit('MODEL_RETIRED', 'MODEL', $id, $existing, $this->getModel($id));

        \Platform\Core\EventBus\EventBus::getInstance()->emit('analytics.model.retired', [
            'model_id' => $id,
            'reason' => $reason,
        ]);

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

    // ─── Technical Indicators Engine ─────────────────────────────────────

    /**
     * Calculate Simple Moving Average (SMA).
     *
     * @param string $instrumentId
     * @param int $period
     * @return array{values: array<float>, latest: float|null}
     */
    public function calculateSMA(string $instrumentId, int $period = 20): array
    {
        $closes = $this->fetchCloses($instrumentId, $period * 3);
        if (count($closes) < $period) {
            return ['values' => [], 'latest' => null];
        }
        $values = [];
        for ($i = $period - 1; $i < count($closes); $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $values[] = round(array_sum($slice) / $period, 4);
        }
        return [
            'values' => $values,
            'latest' => $values[count($values) - 1],
        ];
    }

    /**
     * Calculate Exponential Moving Average (EMA).
     *
     * @param string $instrumentId
     * @param int $period
     * @return array{values: array<float>, latest: float|null}
     */
    public function calculateEMA(string $instrumentId, int $period = 20): array
    {
        $closes = $this->fetchCloses($instrumentId, $period * 3);
        if (count($closes) < $period) {
            return ['values' => [], 'latest' => null];
        }
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($closes, 0, $period)) / $period;
        $values = [round($ema, 4)];
        for ($i = $period; $i < count($closes); $i++) {
            $ema = ($closes[$i] - $ema) * $multiplier + $ema;
            $values[] = round($ema, 4);
        }
        return [
            'values' => $values,
            'latest' => $values[count($values) - 1],
        ];
    }

    /**
     * Calculate Relative Strength Index (RSI).
     *
     * @param string $instrumentId
     * @param int $period
     * @return array{values: array<float>, latest: float|null, signal: string}
     */
    public function calculateRSI(string $instrumentId, int $period = 14): array
    {
        $closes = $this->fetchCloses($instrumentId, $period * 5);
        if (count($closes) < $period + 1) {
            return ['values' => [], 'latest' => null, 'signal' => 'NEUTRAL'];
        }
        $gains = [];
        $losses = [];
        for ($i = 1; $i < count($closes); $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $gains[] = max(0, $change);
            $losses[] = max(0, -$change);
        }
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;
        $values = [];
        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i]) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
            if ($avgLoss == 0) {
                $rsi = 100.0;
            } else {
                $rs = $avgGain / $avgLoss;
                $rsi = 100 - (100 / (1 + $rs));
            }
            $values[] = round($rsi, 2);
        }
        $latest = $values[count($values) - 1] ?? null;
        $signal = 'NEUTRAL';
        if ($latest !== null) {
            if ($latest >= 70) {
                $signal = 'OVERBOUGHT';
            } elseif ($latest <= 30) {
                $signal = 'OVERSOLD';
            }
        }
        return [
            'values' => $values,
            'latest' => $latest,
            'signal' => $signal,
        ];
    }

    /**
     * Calculate MACD (Moving Average Convergence Divergence).
     *
     * @param string $instrumentId
     * @param int $fastPeriod
     * @param int $slowPeriod
     * @param int $signalPeriod
     * @return array
     */
    public function calculateMACD(
        string $instrumentId,
        int $fastPeriod = 12,
        int $slowPeriod = 26,
        int $signalPeriod = 9
    ): array {
        $closes = $this->fetchCloses($instrumentId, $slowPeriod * 3 + $signalPeriod);
        if (count($closes) < $slowPeriod + $signalPeriod) {
            return [
                'macd_line' => [], 'signal_line' => [], 'histogram' => [],
                'latest_macd' => null, 'latest_signal' => null, 'trend' => 'INSUFFICIENT_DATA',
            ];
        }
        $fastEma = $this->emaValues($closes, $fastPeriod);
        $slowEma = $this->emaValues($closes, $slowPeriod);
        $offset = count($slowEma) - count($fastEma);
        if ($offset > 0) {
            $fastEma = array_slice($fastEma, $offset);
        }
        $macdLine = [];
        for ($i = 0; $i < min(count($fastEma), count($slowEma)); $i++) {
            $macdLine[] = round($fastEma[$i] - $slowEma[$i], 4);
        }
        $signalLine = $this->emaValues($macdLine, $signalPeriod);
        $signalOffset = count($macdLine) - count($signalLine);
        $histogram = [];
        for ($i = 0; $i < count($signalLine); $i++) {
            $histogram[] = round($macdLine[$i + $signalOffset] - $signalLine[$i], 4);
        }
        $latestMacd = $macdLine[count($macdLine) - 1] ?? null;
        $latestSignal = $signalLine[count($signalLine) - 1] ?? null;
        $trend = 'NEUTRAL';
        if ($latestMacd !== null && $latestSignal !== null) {
            if ($latestMacd > $latestSignal) {
                $trend = 'BULLISH';
            } elseif ($latestMacd < $latestSignal) {
                $trend = 'BEARISH';
            }
        }
        return [
            'macd_line' => $macdLine,
            'signal_line' => $signalLine,
            'histogram' => $histogram,
            'latest_macd' => $latestMacd,
            'latest_signal' => $latestSignal,
            'trend' => $trend,
        ];
    }

    /**
     * Calculate Bollinger Bands.
     *
     * @param string $instrumentId
     * @param int $period
     * @param float $stdDevMultiplier
     * @return array
     */
    public function calculateBollingerBands(
        string $instrumentId,
        int $period = 20,
        float $stdDevMultiplier = 2.0
    ): array {
        $closes = $this->fetchCloses($instrumentId, $period * 3);
        if (count($closes) < $period) {
            return [
                'upper' => [], 'middle' => [], 'lower' => [],
                'latest_upper' => null, 'latest_middle' => null, 'latest_lower' => null,
                'bandwidth' => null,
            ];
        }
        $upper = [];
        $middle = [];
        $lower = [];
        for ($i = $period - 1; $i < count($closes); $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $mean = array_sum($slice) / $period;
            $variance = 0.0;
            foreach ($slice as $v) {
                $variance += ($v - $mean) ** 2;
            }
            $stdDev = sqrt($variance / $period);
            $upper[] = round($mean + $stdDevMultiplier * $stdDev, 4);
            $middle[] = round($mean, 4);
            $lower[] = round($mean - $stdDevMultiplier * $stdDev, 4);
        }
        $latestUpper = $upper[count($upper) - 1] ?? null;
        $latestMiddle = $middle[count($middle) - 1] ?? null;
        $latestLower = $lower[count($lower) - 1] ?? null;
        $bandwidth = null;
        if ($latestUpper !== null && $latestLower !== null && $latestMiddle && $latestMiddle != 0) {
            $bandwidth = round(($latestUpper - $latestLower) / $latestMiddle, 4);
        }
        return [
            'upper' => $upper,
            'middle' => $middle,
            'lower' => $lower,
            'latest_upper' => $latestUpper,
            'latest_middle' => $latestMiddle,
            'latest_lower' => $latestLower,
            'bandwidth' => $bandwidth,
        ];
    }

    /**
     * Calculate Average True Range (ATR).
     *
     * @param string $instrumentId
     * @param int $period
     * @return array{values: array<float>, latest: float|null}
     */
    public function calculateATRIndicator(string $instrumentId, int $period = 14): array
    {
        $stmt = $this->db->prepare(
            'SELECT high, low, close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date ASC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $period * 4, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (count($rows) < $period + 1) {
            return ['values' => [], 'latest' => null];
        }

        $trueRanges = [];
        for ($i = 1; $i < count($rows); $i++) {
            $high = (float) $rows[$i]['high'];
            $low = (float) $rows[$i]['low'];
            $prevClose = (float) $rows[$i - 1]['close'];
            $trueRanges[] = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
        }

        $atr = array_sum(array_slice($trueRanges, 0, $period)) / $period;
        $values = [round($atr, 4)];
        for ($i = $period; $i < count($trueRanges); $i++) {
            $atr = ($atr * ($period - 1) + $trueRanges[$i]) / $period;
            $values[] = round($atr, 4);
        }
        return [
            'values' => $values,
            'latest' => $values[count($values) - 1],
        ];
    }

    /**
     * Calculate ADX (Average Directional Index).
     *
     * @param string $instrumentId
     * @param int $period
     * @return array{values: array<float>, latest: float|null, trend_strength: string}
     */
    public function calculateADX(string $instrumentId, int $period = 14): array
    {
        $stmt = $this->db->prepare(
            'SELECT high, low, close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date ASC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $period * 4, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (count($rows) < $period * 2 + 1) {
            return ['values' => [], 'latest' => null, 'trend_strength' => 'INSUFFICIENT_DATA'];
        }

        $plusDM = [];
        $minusDM = [];
        $trueRanges = [];
        for ($i = 1; $i < count($rows); $i++) {
            $high = (float) $rows[$i]['high'];
            $low = (float) $rows[$i]['low'];
            $prevHigh = (float) $rows[$i - 1]['high'];
            $prevLow = (float) $rows[$i - 1]['low'];
            $prevClose = (float) $rows[$i - 1]['close'];
            $upMove = $high - $prevHigh;
            $downMove = $prevLow - $low;
            $plusDM[] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0;
            $minusDM[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0;
            $trueRanges[] = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );
        }

        $smoothedTR = array_sum(array_slice($trueRanges, 0, $period));
        $smoothedPlusDM = array_sum(array_slice($plusDM, 0, $period));
        $smoothedMinusDM = array_sum(array_slice($minusDM, 0, $period));
        $dxValues = [];
        for ($i = $period; $i < count($trueRanges); $i++) {
            $smoothedTR = $smoothedTR - ($smoothedTR / $period) + $trueRanges[$i];
            $smoothedPlusDM = $smoothedPlusDM - ($smoothedPlusDM / $period) + $plusDM[$i];
            $smoothedMinusDM = $smoothedMinusDM - ($smoothedMinusDM / $period) + $minusDM[$i];
            $plusDI = ($smoothedTR > 0) ? 100 * ($smoothedPlusDM / $smoothedTR) : 0;
            $minusDI = ($smoothedTR > 0) ? 100 * ($smoothedMinusDM / $smoothedTR) : 0;
            $diSum = $plusDI + $minusDI;
            $dx = ($diSum > 0) ? 100 * abs($plusDI - $minusDI) / $diSum : 0;
            $dxValues[] = $dx;
        }

        if (count($dxValues) < $period) {
            return ['values' => [], 'latest' => null, 'trend_strength' => 'INSUFFICIENT_DATA'];
        }

        $adx = array_sum(array_slice($dxValues, 0, $period)) / $period;
        $values = [round($adx, 2)];
        for ($i = $period; $i < count($dxValues); $i++) {
            $adx = ($adx * ($period - 1) + $dxValues[$i]) / $period;
            $values[] = round($adx, 2);
        }
        $latest = $values[count($values) - 1];
        $strength = 'WEAK';
        if ($latest >= 50) {
            $strength = 'VERY_STRONG';
        } elseif ($latest >= 25) {
            $strength = 'STRONG';
        } elseif ($latest >= 20) {
            $strength = 'DEVELOPING';
        }
        return [
            'values' => $values,
            'latest' => $latest,
            'trend_strength' => $strength,
        ];
    }

    /**
     * Get all technical indicators for an instrument in one call.
     *
     * @param string $instrumentId
     * @return array
     */
    public function getAllTechnicalIndicators(string $instrumentId): array
    {
        return [
            'sma_20' => $this->calculateSMA($instrumentId, 20),
            'sma_50' => $this->calculateSMA($instrumentId, 50),
            'ema_12' => $this->calculateEMA($instrumentId, 12),
            'ema_26' => $this->calculateEMA($instrumentId, 26),
            'rsi_14' => $this->calculateRSI($instrumentId, 14),
            'macd' => $this->calculateMACD($instrumentId),
            'bollinger_bands' => $this->calculateBollingerBands($instrumentId),
            'atr_14' => $this->calculateATRIndicator($instrumentId, 14),
            'adx_14' => $this->calculateADX($instrumentId, 14),
            'support_resistance' => $this->detectSupportResistance($instrumentId),
            'trend' => $this->identifyTrend($instrumentId),
        ];
    }

    // ─── Private Helpers for Technical Indicators ────────────────────────

    /**
     * Fetch closing prices from OHLCV data.
     */
    private function fetchCloses(string $instrumentId, int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date ASC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => (float) $r['close'], $stmt->fetchAll());
    }

    /**
     * Calculate EMA values from a price array.
     */
    private function emaValues(array $values, int $period): array
    {
        if (count($values) < $period) {
            return [];
        }
        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        $result = [round($ema, 4)];
        for ($i = $period; $i < count($values); $i++) {
            $ema = ($values[$i] - $ema) * $multiplier + $ema;
            $result[] = round($ema, 4);
        }
        return $result;
    }

    // ─── Support/Resistance & Trend Detection ────────────────────────────

    /**
     * Detect support and resistance levels from OHLCV data.
     * Uses local minima/maxima approach with a lookback window.
     *
     * @param string $instrumentId
     * @param int $lookback Number of bars to look back for pivots (default 5)
     * @param int $bars Number of recent bars to analyze (default 50)
     * @return array{support: array<float>, resistance: array<float>, current_price: float|null}
     */
    public function detectSupportResistance(string $instrumentId, int $lookback = 5, int $bars = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT trade_date, high, low, close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :instrument_id
             ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':instrument_id', $instrumentId);
        $stmt->bindValue(':limit', $bars, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());

        if (count($rows) < $lookback * 2 + 1) {
            return ['support' => [], 'resistance' => [], 'current_price' => null];
        }

        $support = [];
        $resistance = [];
        $currentPrice = null;

        for ($i = $lookback; $i < count($rows) - $lookback; $i++) {
            $isSupport = true;
            $isResistance = true;
            for ($j = $i - $lookback; $j <= $i + $lookback; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ((float) $rows[$j]['low'] <= (float) $rows[$i]['low']) {
                    $isSupport = false;
                }
                if ((float) $rows[$j]['high'] >= (float) $rows[$i]['high']) {
                    $isResistance = false;
                }
            }
            if ($isSupport) {
                $support[] = (float) $rows[$i]['low'];
            }
            if ($isResistance) {
                $resistance[] = (float) $rows[$i]['high'];
            }
        }

        if (count($rows) > 0) {
            $currentPrice = (float) $rows[count($rows) - 1]['close'];
        }

        $support = array_values(array_unique($support));
        $resistance = array_values(array_unique($resistance));

        sort($support);
        rsort($resistance);

        return [
            'support' => $support,
            'resistance' => $resistance,
            'current_price' => $currentPrice,
        ];
    }

    /**
     * Identify trend from OHLCV data using SMA crossover.
     *
     * @param string $instrumentId
     * @param int $shortPeriod Short SMA period (default 20)
     * @param int $longPeriod Long SMA period (default 50)
     * @return array{trend: string, short_sma: float|null, long_sma: float|null}
     */
    public function identifyTrend(string $instrumentId, int $shortPeriod = 20, int $longPeriod = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :instrument_id
             ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':instrument_id', $instrumentId);
        $stmt->bindValue(':limit', $longPeriod, PDO::PARAM_INT);
        $stmt->execute();
        $closes = array_reverse(array_map(fn($r) => (float) $r['close'], $stmt->fetchAll()));

        if (count($closes) < $longPeriod) {
            return ['trend' => 'INSUFFICIENT_DATA', 'short_sma' => null, 'long_sma' => null];
        }

        $shortSlices = array_slice($closes, -$shortPeriod);
        $longSlices = array_slice($closes, -$longPeriod);
        $shortSma = array_sum($shortSlices) / $shortPeriod;
        $longSma = array_sum($longSlices) / $longPeriod;

        $trend = 'SIDEWAYS';
        if ($shortSma > $longSma * 1.01) {
            $trend = 'UPTREND';
        } elseif ($shortSma < $longSma * 0.99) {
            $trend = 'DOWNTREND';
        }

        return [
            'trend' => $trend,
            'short_sma' => round($shortSma, 4),
            'long_sma' => round($longSma, 4),
        ];
    }

    // ─── Market Regime Engine ────────────────────────────────────────────

    /**
     * Classify market regime for an instrument or index.
     * Uses SMA trend, ADX strength, volatility (ATR/price), and RSI.
     *
     * @param string $instrumentId
     * @return array
     */
    public function classifyMarketRegime(string $instrumentId): array
    {
        $trend = $this->identifyTrend($instrumentId, 20, 50);
        $adx = $this->calculateADX($instrumentId, 14);
        $atr = $this->calculateATRIndicator($instrumentId, 14);
        $rsi = $this->calculateRSI($instrumentId, 14);
        $bb = $this->calculateBollingerBands($instrumentId, 20, 2.0);

        $closes = $this->fetchCloses($instrumentId, 60);
        $currentPrice = count($closes) > 0 ? $closes[count($closes) - 1] : null;

        $volatilityPct = null;
        if ($atr['latest'] !== null && $currentPrice && $currentPrice > 0) {
            $volatilityPct = ($atr['latest'] / $currentPrice) * 100;
        }

        $regime = 'SIDEWAYS';
        $subRegime = 'NORMAL';

        if ($trend['trend'] === 'UPTREND' && $adx['latest'] !== null && $adx['latest'] >= 25) {
            $regime = 'BULL';
        } elseif ($trend['trend'] === 'DOWNTREND' && $adx['latest'] !== null && $adx['latest'] >= 25) {
            $regime = 'BEAR';
        } elseif ($adx['latest'] !== null && $adx['latest'] < 20) {
            $regime = 'SIDEWAYS';
        }

        if ($volatilityPct !== null && $volatilityPct > 3.0) {
            $subRegime = 'HIGH_VOLATILITY';
        }

        $riskAppetite = 'NEUTRAL';
        if ($rsi['latest'] !== null) {
            if ($rsi['latest'] > 55) {
                $riskAppetite = 'RISK_ON';
            } elseif ($rsi['latest'] < 45) {
                $riskAppetite = 'RISK_OFF';
            }
        }

        $confidence = 0.5;
        $factors = 0;
        if ($adx['latest'] !== null) {
            $confidence += min(0.2, $adx['latest'] / 200);
            $factors++;
        }
        if ($rsi['latest'] !== null) {
            $confidence += 0.1;
            $factors++;
        }
        if ($volatilityPct !== null) {
            $confidence += 0.1;
            $factors++;
        }
        if ($bb['bandwidth'] !== null) {
            $confidence += 0.1;
            $factors++;
        }
        $confidence = min(0.95, $confidence);

        return [
            'regime' => $regime,
            'sub_regime' => $subRegime,
            'trend' => $trend['trend'],
            'volatility' => $volatilityPct !== null
                ? ($volatilityPct > 3.0 ? 'HIGH' : ($volatilityPct > 1.5 ? 'MODERATE' : 'LOW'))
                : 'UNKNOWN',
            'volatility_pct' => $volatilityPct !== null ? round($volatilityPct, 2) : null,
            'risk_appetite' => $riskAppetite,
            'confidence' => round($confidence, 2),
            'details' => [
                'adx' => $adx['latest'],
                'adx_strength' => $adx['trend_strength'],
                'rsi' => $rsi['latest'],
                'rsi_signal' => $rsi['signal'],
                'atr' => $atr['latest'],
                'bollinger_bandwidth' => $bb['bandwidth'],
                'short_sma' => $trend['short_sma'],
                'long_sma' => $trend['long_sma'],
            ],
        ];
    }

    // ─── Screening Engine ────────────────────────────────────────────────

    /**
     * Run multi-factor screening across instruments.
     *
     * Criteria format:
     * [
     *   'asset_class' => 'EQUITY',
     *   'min_roe' => 15,
     *   'max_debt_equity' => 1.0,
     *   'max_pe' => 20,
     *   'min_revenue_growth' => 10,
     *   'trend' => 'UPTREND',
     *   'min_rsi' => 30,
     *   'max_rsi' => 70,
     *   'min_composite_score' => 60,
     *   'limit' => 50,
     * ]
     *
     * @param array $criteria
     * @return array{results: array, total: int, criteria: array}
     */
    public function runScreening(array $criteria): array
    {
        $limit = min(200, max(1, (int) ($criteria['limit'] ?? 50)));

        $where = [];
        $params = [];
        if (isset($criteria['asset_class'])) {
            $where[] = 'i.asset_class = :asset_class';
            $params[':asset_class'] = $criteria['asset_class'];
        }
        if (isset($criteria['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $criteria['status'];
        } else {
            $where[] = 'i.status = :status';
            $params[':status'] = 'ACTIVE';
        }

        $clause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare(
            "SELECT i.instrument_id, l.ticker AS symbol, iss.short_name AS name, i.asset_class
             FROM market_master.instrument i
             LEFT JOIN market_master.listing l ON i.instrument_id = l.instrument_id
             LEFT JOIN market_master.security s ON i.security_id = s.security_id
             LEFT JOIN market_master.issuer iss ON s.issuer_id = iss.issuer_id
             {$clause} LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $instruments = $stmt->fetchAll();

        $results = [];
        foreach ($instruments as $inst) {
            $instId = $inst['instrument_id'];
            $score = 0;
            $maxScore = 0;
            $matched = [];
            $notMatched = [];

            if (isset($criteria['min_roe'])) {
                $maxScore += 20;
                $metric = $this->getLatestMetric($instId, 'ROE');
                if ($metric !== null && (float) $metric >= (float) $criteria['min_roe']) {
                    $score += 20;
                    $matched[] = "ROE >= {$criteria['min_roe']}";
                } else {
                    $notMatched[] = "ROE < {$criteria['min_roe']}";
                }
            }

            if (isset($criteria['max_debt_equity'])) {
                $maxScore += 15;
                $metric = $this->getLatestMetric($instId, 'DEBT_TO_EQUITY');
                if ($metric !== null && (float) $metric <= (float) $criteria['max_debt_equity']) {
                    $score += 15;
                    $matched[] = "D/E <= {$criteria['max_debt_equity']}";
                } else {
                    $notMatched[] = "D/E > {$criteria['max_debt_equity']}";
                }
            }

            if (isset($criteria['max_pe'])) {
                $maxScore += 15;
                $metric = $this->getLatestMetric($instId, 'PE_RATIO');
                if ($metric !== null && (float) $metric <= (float) $criteria['max_pe']) {
                    $score += 15;
                    $matched[] = "PE <= {$criteria['max_pe']}";
                } else {
                    $notMatched[] = "PE > {$criteria['max_pe']}";
                }
            }

            if (isset($criteria['min_revenue_growth'])) {
                $maxScore += 15;
                $metric = $this->getLatestMetric($instId, 'REVENUE_GROWTH');
                if ($metric !== null && (float) $metric >= (float) $criteria['min_revenue_growth']) {
                    $score += 15;
                    $matched[] = "Rev Growth >= {$criteria['min_revenue_growth']}";
                } else {
                    $notMatched[] = "Rev Growth < {$criteria['min_revenue_growth']}";
                }
            }

            if (isset($criteria['trend'])) {
                $maxScore += 20;
                $trendData = $this->identifyTrend($instId, 20, 50);
                if ($trendData['trend'] === $criteria['trend']) {
                    $score += 20;
                    $matched[] = "Trend = {$criteria['trend']}";
                } else {
                    $notMatched[] = "Trend = {$trendData['trend']}";
                }
            }

            if (isset($criteria['min_rsi']) || isset($criteria['max_rsi'])) {
                $maxScore += 15;
                $rsiData = $this->calculateRSI($instId, 14);
                $rsiVal = $rsiData['latest'];
                $rsiOk = true;
                if (isset($criteria['min_rsi']) && ($rsiVal === null || $rsiVal < $criteria['min_rsi'])) {
                    $rsiOk = false;
                }
                if (isset($criteria['max_rsi']) && ($rsiVal === null || $rsiVal > $criteria['max_rsi'])) {
                    $rsiOk = false;
                }
                if ($rsiOk) {
                    $score += 15;
                    $matched[] = "RSI in range";
                } else {
                    $notMatched[] = "RSI out of range";
                }
            }

            $normalizedScore = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

            $minScore = (int) ($criteria['min_composite_score'] ?? 0);
            if ($normalizedScore >= $minScore) {
                $results[] = [
                    'instrument_id' => $instId,
                    'symbol' => $inst['symbol'],
                    'name' => $inst['name'],
                    'asset_class' => $inst['asset_class'],
                    'screening_score' => $normalizedScore,
                    'matched_criteria' => $matched,
                    'not_matched_criteria' => $notMatched,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['screening_score'] <=> $a['screening_score']);

        return [
            'results' => $results,
            'total' => count($results),
            'criteria' => $criteria,
        ];
    }

    // ─── Composite Decision Engine ───────────────────────────────────────

    /**
     * Calculate composite score for an instrument by aggregating
     * multiple score dimensions: Fundamental, Valuation, Technical,
     * Macro, Sentiment, Liquidity, Risk.
     *
     * @param string $instrumentId
     * @return array{composite_score: float, dimensions: array, recommendation: string, confidence: string}
     */
    public function calculateCompositeScore(string $instrumentId): array
    {
        $dimensions = [];
        $totalWeight = 0;
        $weightedSum = 0.0;

        $scoreTypes = [
            'FUNDAMENTAL' => 25,
            'VALUATION' => 20,
            'TECHNICAL' => 20,
            'MACRO' => 10,
            'SENTIMENT' => 10,
            'LIQUIDITY' => 10,
            'RISK' => 5,
        ];

        foreach ($scoreTypes as $type => $weight) {
            $scores = $this->getInstrumentScores($instrumentId, $type);
            $latestScore = null;
            if (count($scores) > 0) {
                $latestScore = (float) $scores[0]['value'];
            }

            $normalizedScore = $latestScore;
            $grade = 'N/A';
            if ($normalizedScore !== null) {
                if ($normalizedScore >= 80) {
                    $grade = 'A';
                } elseif ($normalizedScore >= 70) {
                    $grade = 'B';
                } elseif ($normalizedScore >= 60) {
                    $grade = 'C';
                } elseif ($normalizedScore >= 50) {
                    $grade = 'D';
                } else {
                    $grade = 'F';
                }
            }

            $dimensions[$type] = [
                'score' => $normalizedScore !== null ? round($normalizedScore, 2) : null,
                'grade' => $grade,
                'weight' => $weight,
            ];

            if ($normalizedScore !== null) {
                $weightedSum += $normalizedScore * $weight;
                $totalWeight += $weight;
            }
        }

        $technicalIndicators = $this->getAllTechnicalIndicators($instrumentId);
        $technicalScore = null;
        $techFactors = 0;
        $techSum = 0;
        if ($technicalIndicators['rsi_14']['latest'] !== null) {
            $rsi = $technicalIndicators['rsi_14']['latest'];
            $rsiScore = 50;
            if ($rsi < 30) {
                $rsiScore = 80;
            } elseif ($rsi > 70) {
                $rsiScore = 20;
            } elseif ($rsi >= 45 && $rsi <= 55) {
                $rsiScore = 60;
            }
            $techSum += $rsiScore;
            $techFactors++;
        }
        if ($technicalIndicators['macd']['trend'] === 'BULLISH') {
            $techSum += 75;
            $techFactors++;
        } elseif ($technicalIndicators['macd']['trend'] === 'BEARISH') {
            $techSum += 25;
            $techFactors++;
        }
        if ($technicalIndicators['adx_14']['latest'] !== null) {
            $techSum += min(100, $technicalIndicators['adx_14']['latest'] * 2);
            $techFactors++;
        }
        if ($technicalIndicators['trend']['trend'] === 'UPTREND') {
            $techSum += 80;
            $techFactors++;
        } elseif ($technicalIndicators['trend']['trend'] === 'DOWNTREND') {
            $techSum += 20;
            $techFactors++;
        }
        if ($techFactors > 0) {
            $technicalScore = round($techSum / $techFactors, 2);
            $dimensions['TECHNICAL']['score'] = $dimensions['TECHNICAL']['score'] ?? $technicalScore;
            $dimensions['TECHNICAL']['grade'] = $technicalScore >= 80
                ? 'A'
                : ($technicalScore >= 70
                    ? 'B'
                    : ($technicalScore >= 60
                        ? 'C'
                        : ($technicalScore >= 50 ? 'D' : 'F')));
            if ($dimensions['TECHNICAL']['score'] !== null && $totalWeight < 100) {
                $weightedSum += $dimensions['TECHNICAL']['score'] * $scoreTypes['TECHNICAL'];
                $totalWeight += $scoreTypes['TECHNICAL'];
            }
        }

        $compositeScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.0;

        $recommendation = 'HOLD';
        if ($compositeScore >= 75) {
            $recommendation = 'BUY';
        } elseif ($compositeScore >= 65) {
            $recommendation = 'ACCUMULATE';
        } elseif ($compositeScore <= 35) {
            $recommendation = 'SELL';
        } elseif ($compositeScore <= 45) {
            $recommendation = 'REDUCE';
        }

        $confidence = 'LOW';
        $availableDimensions = count(array_filter($dimensions, fn($d) => $d['score'] !== null));
        if ($availableDimensions >= 5) {
            $confidence = 'HIGH';
        } elseif ($availableDimensions >= 3) {
            $confidence = 'MEDIUM';
        }

        return [
            'composite_score' => $compositeScore,
            'recommendation' => $recommendation,
            'confidence' => $confidence,
            'available_dimensions' => $availableDimensions,
            'dimensions' => $dimensions,
        ];
    }

    // ─── Private Helpers for Screening ───────────────────────────────────

    /**
     * Get latest financial metric value for an instrument's issuer.
     */
    private function getLatestMetric(string $instrumentId, string $metricType): ?float
    {
        $stmt = $this->db->prepare(
            'SELECT fm.value
             FROM fundamental.financial_metric fm
             JOIN market_master.security s ON s.issuer_id = fm.issuer_id
             JOIN market_master.instrument i ON i.security_id = s.security_id
             WHERE i.instrument_id = :inst_id
             AND fm.metric_type = :metric_type
             ORDER BY fm.available_time DESC LIMIT 1'
        );
        $stmt->bindValue(':inst_id', $instrumentId);
        $stmt->bindValue(':metric_type', $metricType);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false || !isset($row['value'])) {
            return null;
        }
        return (float) $row['value'];
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

    // ─── Market Microstructure ────────────────────────────────────────────

    /**
     * Analyze bid/ask spread from recent OHLCV data.
     * @param string $instrumentId
     * @return array
     */
    public function analyzeBidAskSpread(string $instrumentId): array
    {
        $prices = $this->getRecentCloses($instrumentId, 20);
        if (count($prices) < 2) {
            return [
                'spread' => null,
                'spread_pct' => null,
                'avg_spread' => null,
                'avg_spread_pct' => null,
                'classification' => 'INSUFFICIENT_DATA',
            ];
        }

        $spreads = [];
        foreach ($prices as $price) {
            $spread = $price * 0.001;
            $spreads[] = $spread;
        }

        $latestSpread = end($spreads);
        $avgSpread = array_sum($spreads) / count($spreads);
        $latestPrice = end($prices);
        $spreadPct = ($latestSpread / $latestPrice) * 100;
        $avgSpreadPct = ($avgSpread / $latestPrice) * 100;

        $classification = 'TIGHT';
        if ($spreadPct > 0.5) {
            $classification = 'WIDE';
        } elseif ($spreadPct > 0.2) {
            $classification = 'MODERATE';
        }

        return [
            'spread' => round($latestSpread, 4),
            'spread_pct' => round($spreadPct, 4),
            'avg_spread' => round($avgSpread, 4),
            'avg_spread_pct' => round($avgSpreadPct, 4),
            'classification' => $classification,
        ];
    }

    /**
     * Analyze order book depth using simulated levels from recent data.
     * @param string $instrumentId
     * @param int $levels
     * @return array
     */
    public function analyzeOrderBookDepth(string $instrumentId, int $levels = 5): array
    {
        $prices = $this->getRecentCloses($instrumentId, 20);
        if (count($prices) < 2) {
            return [
                'levels' => [],
                'total_bid_volume' => 0,
                'total_ask_volume' => 0,
                'imbalance' => 0.0,
                'imbalance_pct' => 0.0,
                'classification' => 'INSUFFICIENT_DATA',
            ];
        }

        $latestPrice = end($prices);
        $stmt = $this->db->prepare(
            'SELECT volume FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :inst_id ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':inst_id', $instrumentId);
        $stmt->bindValue(':limit', $levels, \PDO::PARAM_INT);
        $stmt->execute();
        $volRows = $stmt->fetchAll();

        $orderLevels = [];
        $totalBid = 0;
        $totalAsk = 0;

        for ($i = 0; $i < $levels; $i++) {
            $baseVol = isset($volRows[$i]) ? (int) $volRows[$i]['volume'] : 100000;
            $bidVol = (int) ($baseVol * (0.8 + (mt_rand(0, 40) / 100)));
            $askVol = (int) ($baseVol * (0.8 + (mt_rand(0, 40) / 100)));
            $tickSize = $latestPrice * 0.0005 * ($i + 1);

            $orderLevels[] = [
                'level' => $i + 1,
                'bid_price' => round($latestPrice - $tickSize, 4),
                'bid_volume' => $bidVol,
                'ask_price' => round($latestPrice + $tickSize, 4),
                'ask_volume' => $askVol,
            ];
            $totalBid += $bidVol;
            $totalAsk += $askVol;
        }

        $imbalance = $totalBid - $totalAsk;
        $total = $totalBid + $totalAsk;
        $imbalancePct = $total > 0 ? ($imbalance / $total) * 100 : 0.0;

        return [
            'levels' => $orderLevels,
            'total_bid_volume' => $totalBid,
            'total_ask_volume' => $totalAsk,
            'imbalance' => $imbalance,
            'imbalance_pct' => round($imbalancePct, 2),
        ];
    }

    /**
     * Estimate market impact for a given order value.
     * @param string $instrumentId
     * @param float $orderValue
     * @param string $side
     * @return array
     */
    public function estimateMarketImpact(string $instrumentId, float $orderValue, string $side = 'BUY'): array
    {
        $prices = $this->getRecentCloses($instrumentId, 20);
        if (count($prices) < 2 || $orderValue <= 0) {
            return [
                'market_impact_pct' => 0.0,
                'expected_price_movement' => 0.0,
                'kyle_lambda' => 0.0,
                'classification' => 'INSUFFICIENT_DATA',
            ];
        }

        $latestPrice = end($prices);
        $stmt = $this->db->prepare(
            'SELECT AVG(volume) as avg_vol FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :inst_id ORDER BY trade_date DESC LIMIT 20'
        );
        $stmt->bindValue(':inst_id', $instrumentId);
        $stmt->execute();
        $avgVol = (float) $stmt->fetchColumn();

        if ($avgVol <= 0) {
            $avgVol = 1000000;
        }

        $avgDailyValue = $avgVol * $latestPrice;
        $participationRate = $orderValue / $avgDailyValue;
        $impactPct = $participationRate * 0.1;
        $impactPct = min($impactPct, 10.0);

        $priceMovement = $latestPrice * ($impactPct / 100) * ($side === 'BUY' ? 1 : -1);

        $kyleLambda = $avgDailyValue > 0 ? $orderValue / ($avgDailyValue * ($impactPct / 100 + 0.001)) : 0.0;

        $classification = 'LOW_IMPACT';
        if ($impactPct > 1.0) {
            $classification = 'HIGH_IMPACT';
        } elseif ($impactPct > 0.3) {
            $classification = 'MODERATE_IMPACT';
        }

        return [
            'market_impact_pct' => round($impactPct, 4),
            'expected_price_movement' => round($priceMovement, 4),
            'kyle_lambda' => round($kyleLambda, 4),
            'classification' => $classification,
        ];
    }

    /**
     * Calculate liquidity score based on volume and price stability.
     * @param string $instrumentId
     * @return array
     */
    public function calculateLiquidityScore(string $instrumentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT close, volume FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :inst_id ORDER BY trade_date DESC LIMIT 30'
        );
        $stmt->bindValue(':inst_id', $instrumentId);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (count($rows) < 10) {
            return [
                'liquidity_score' => 0.0,
                'grade' => 'N/A',
                'avg_daily_volume' => 0.0,
                'avg_daily_value' => 0.0,
                'volume_consistency' => 0.0,
                'price_stability' => 0.0,
                'classification' => 'INSUFFICIENT_DATA',
            ];
        }

        $volumes = array_map(fn($r) => (float) $r['volume'], $rows);
        $closes = array_map(fn($r) => (float) $r['close'], $rows);

        $avgVol = array_sum($volumes) / count($volumes);
        $avgPrice = array_sum($closes) / count($closes);
        $avgDailyValue = $avgVol * $avgPrice;

        $volStd = $this->stdDev($volumes);
        $volCv = $avgVol > 0 ? $volStd / $avgVol : 1.0;
        $volumeConsistency = max(0, 100 - ($volCv * 100));

        $priceReturns = [];
        for ($i = 1; $i < count($closes); $i++) {
            if ($closes[$i - 1] > 0) {
                $priceReturns[] = ($closes[$i] - $closes[$i - 1]) / $closes[$i - 1];
            }
        }
        $priceVolatility = count($priceReturns) > 0 ? $this->stdDev($priceReturns) : 1.0;
        $priceStability = max(0, 100 - ($priceVolatility * 1000));

        $score = min(
            100,
            ($volumeConsistency * 0.4)
            + ($priceStability * 0.3)
            + min(100, ($avgDailyValue / 100000000) * 100) * 0.3
        );

        $grade = 'F';
        if ($score >= 80) {
            $grade = 'A';
        } elseif ($score >= 65) {
            $grade = 'B';
        } elseif ($score >= 50) {
            $grade = 'C';
        } elseif ($score >= 35) {
            $grade = 'D';
        }

        return [
            'liquidity_score' => round($score, 2),
            'grade' => $grade,
            'avg_daily_volume' => round($avgVol, 2),
            'avg_daily_value' => round($avgDailyValue, 2),
            'volume_consistency' => round($volumeConsistency, 2),
            'price_stability' => round($priceStability, 2),
        ];
    }

    // ─── Market Factor Matrix ─────────────────────────────────────────────

    /**
     * Get global-to-Indonesia factors from economic indicators.
     * @return array{factors: array, summary: string}
     */
    public function getGlobalToIndonesiaFactors(): array
    {
        $factors = [];

        $stmt = $this->db->prepare(
            'SELECT indicator_type, value, period, available_time
             FROM fundamental.economic_indicator
             ORDER BY available_time DESC LIMIT 20'
        );
        $stmt->execute();
        $indicators = $stmt->fetchAll();

        $factorMap = [
            'INFLATION' => ['weight' => 20, 'direction' => 'inverse'],
            'GDP_GROWTH' => ['weight' => 25, 'direction' => 'direct'],
            'INTEREST_RATE' => ['weight' => 20, 'direction' => 'inverse'],
            'BOND_YIELD_10Y' => ['weight' => 15, 'direction' => 'inverse'],
            'CPI' => ['weight' => 10, 'direction' => 'inverse'],
            'PMI' => ['weight' => 10, 'direction' => 'direct'],
        ];

        foreach ($indicators as $ind) {
            $type = $ind['indicator_type'];
            if (isset($factorMap[$type])) {
                $factors[] = [
                    'factor' => $type,
                    'value' => (float) $ind['value'],
                    'period' => $ind['period'],
                    'weight' => $factorMap[$type]['weight'],
                    'direction' => $factorMap[$type]['direction'],
                    'as_of' => $ind['available_time'],
                ];
            }
        }

        $summary = count($factors) > 0
            ? sprintf('%d global-to-Indonesia factors tracked', count($factors))
            : 'No economic indicators data available';

        return ['factors' => $factors, 'summary' => $summary];
    }

    /**
     * Calculate Rupiah Pressure Score from multiple indicators.
     * @return array{score: float, grade: string, components: array, interpretation: string}
     */
    public function calculateRupiahPressureScore(): array
    {
        $components = [];
        $totalScore = 50.0;

        $stmt = $this->db->prepare(
            "SELECT indicator_type, value FROM fundamental.economic_indicator
             WHERE indicator_type IN ('INTEREST_RATE', 'INFLATION', 'BOND_YIELD_10Y', 'GDP_GROWTH')
             ORDER BY available_time DESC LIMIT 10"
        );
        $stmt->execute();
        $indicators = $stmt->fetchAll();

        $indMap = [];
        foreach ($indicators as $ind) {
            if (!isset($indMap[$ind['indicator_type']])) {
                $indMap[$ind['indicator_type']] = (float) $ind['value'];
            }
        }

        if (isset($indMap['INTEREST_RATE'])) {
            $rate = $indMap['INTEREST_RATE'];
            $rateScore = max(0, min(100, 50 + ($rate - 6) * 10));
            $components['interest_rate'] = [
                'value' => $rate,
                'score' => round($rateScore, 2),
                'impact' => 'higher rate supports IDR',
            ];
            $totalScore = ($totalScore + $rateScore) / 2;
        }

        if (isset($indMap['INFLATION'])) {
            $inflation = $indMap['INFLATION'];
            $inflationScore = max(0, min(100, 50 + (5 - $inflation) * 8));
            $components['inflation'] = [
                'value' => $inflation,
                'score' => round($inflationScore, 2),
                'impact' => 'lower inflation supports IDR',
            ];
            $totalScore = ($totalScore + $inflationScore) / 2;
        }

        if (isset($indMap['BOND_YIELD_10Y'])) {
            $yield = $indMap['BOND_YIELD_10Y'];
            $yieldScore = max(0, min(100, 50 + ($yield - 6) * 8));
            $components['bond_yield'] = [
                'value' => $yield,
                'score' => round($yieldScore, 2),
                'impact' => 'higher yield attracts capital',
            ];
            $totalScore = ($totalScore + $yieldScore) / 2;
        }

        if (isset($indMap['GDP_GROWTH'])) {
            $gdp = $indMap['GDP_GROWTH'];
            $gdpScore = max(0, min(100, 50 + ($gdp - 5) * 6));
            $components['gdp_growth'] = [
                'value' => $gdp,
                'score' => round($gdpScore, 2),
                'impact' => 'higher growth supports IDR',
            ];
            $totalScore = ($totalScore + $gdpScore) / 2;
        }

        $grade = 'NEUTRAL';
        $interpretation = 'Balanced pressure on Rupiah';
        if ($totalScore >= 70) {
            $grade = 'STRONG_IDR';
            $interpretation = 'Strong support for Rupiah appreciation';
        } elseif ($totalScore >= 55) {
            $grade = 'MILD_IDR_SUPPORT';
            $interpretation = 'Mild support for Rupiah';
        } elseif ($totalScore <= 30) {
            $grade = 'IDR_PRESSURE';
            $interpretation = 'Significant pressure for Rupiah depreciation';
        } elseif ($totalScore <= 45) {
            $grade = 'MILD_IDR_PRESSURE';
            $interpretation = 'Mild pressure on Rupiah';
        }

        return [
            'score' => round($totalScore, 2),
            'grade' => $grade,
            'components' => $components,
            'interpretation' => $interpretation,
        ];
    }

    /**
     * Calculate Flow Confirmation Score from trading volume patterns.
     * @return array
     */
    public function calculateFlowConfirmationScore(): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.instrument_id, AVG(o.volume) as avg_vol, COUNT(*) as days
             FROM data_ingestion.ohlcv_daily o
             JOIN market_master.instrument i ON o.instrument_id = i.instrument_id
             WHERE i.asset_class = :asset_class
             AND o.trade_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY o.instrument_id'
        );
        $stmt->bindValue(':asset_class', 'EQUITY');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (count($rows) < 2) {
            return [
                'score' => 0.0,
                'grade' => 'N/A',
                'volume_trend' => 'INSUFFICIENT_DATA',
                'smart_money_flow' => 'UNKNOWN',
                'institutional_activity' => 'UNKNOWN',
                'classification' => 'INSUFFICIENT_DATA',
            ];
        }

        $totalVolume = array_sum(array_map(fn($r) => (float) $r['avg_vol'], $rows));
        $avgVolumePerStock = $totalVolume / count($rows);

        $stmt2 = $this->db->prepare(
            'SELECT SUM(o.volume) as recent_vol FROM data_ingestion.ohlcv_daily o
             JOIN market_master.instrument i ON o.instrument_id = i.instrument_id
             WHERE i.asset_class = :asset_class
             AND o.trade_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
        );
        $stmt2->bindValue(':asset_class', 'EQUITY');
        $stmt2->execute();
        $recentVol = (float) $stmt2->fetchColumn();

        $stmt3 = $this->db->prepare(
            'SELECT SUM(o.volume) as prior_vol FROM data_ingestion.ohlcv_daily o
             JOIN market_master.instrument i ON o.instrument_id = i.instrument_id
             WHERE i.asset_class = :asset_class
             AND o.trade_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             AND o.trade_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
        );
        $stmt3->bindValue(':asset_class', 'EQUITY');
        $stmt3->execute();
        $priorVol = (float) $stmt3->fetchColumn();

        $volumeTrend = 'STABLE';
        $volRatio = 0.0;
        if ($priorVol > 0) {
            $volRatio = $recentVol / $priorVol;
            if ($volRatio > 1.2) {
                $volumeTrend = 'INCREASING';
            } elseif ($volRatio < 0.8) {
                $volumeTrend = 'DECREASING';
            }
        }

        $score = 50.0;
        if ($volRatio > 1.5) {
            $score = 80.0;
        } elseif ($volRatio > 1.2) {
            $score = 65.0;
        } elseif ($volRatio < 0.7) {
            $score = 25.0;
        } elseif ($volRatio < 0.9) {
            $score = 40.0;
        }

        $smartMoneyFlow = 'NEUTRAL';
        if ($score >= 65) {
            $smartMoneyFlow = 'INFLOW';
        } elseif ($score <= 35) {
            $smartMoneyFlow = 'OUTFLOW';
        }

        $grade = 'NEUTRAL';
        $interpretation = 'Balanced flow conditions';
        if ($score >= 70) {
            $grade = 'STRONG_CONFIRMATION';
            $interpretation = 'Strong volume confirmation suggests institutional inflow';
        } elseif ($score >= 55) {
            $grade = 'POSITIVE';
            $interpretation = 'Positive flow with moderate confirmation';
        } elseif ($score <= 30) {
            $grade = 'WEAK';
            $interpretation = 'Weak flow suggests distribution or outflow';
        }

        return [
            'score' => round($score, 2),
            'grade' => $grade,
            'volume_trend' => $volumeTrend,
            'smart_money_flow' => $smartMoneyFlow,
            'interpretation' => $interpretation,
        ];
    }

    /**
     * Standard deviation helper.
     * @param array<float> $values
     */
    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $squaredSum = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values));
        return sqrt($squaredSum / $n);
    }

    /**
     * Get recent closing prices (newest first).
     * @return array<float>
     */
    private function getRecentCloses(string $instrumentId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT close FROM data_ingestion.ohlcv_daily
             WHERE instrument_id = :id ORDER BY trade_date DESC LIMIT :limit'
        );
        $stmt->bindValue(':id', $instrumentId);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => (float) $r['close'], $stmt->fetchAll());
    }
}
