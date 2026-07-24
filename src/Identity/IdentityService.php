<?php

declare(strict_types=1);

namespace Platform\Identity;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;
use Platform\Core\Application;
use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;
use Throwable;

final class IdentityService extends BaseService implements IdentityServiceInterface
{
    private const PASSWORD_MIN_LENGTH = 12;

    public function isSetupRequired(): bool
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM identity.owner_account')->fetchColumn() === 0;
    }

    public function setupOwner(array $data, array $context = []): array
    {
        $fieldErrors = $this->validateOwnerData($data);
        if ($fieldErrors !== []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Owner setup data is invalid', $fieldErrors);
        }

        $ownerId = $this->uuid();
        $this->db->beginTransaction();

        try {
            if (!$this->isSetupRequired()) {
                throw new ApiException(409, 'OWNER_ALREADY_EXISTS', 'The owner account has already been configured');
            }

            $stmt = $this->db->prepare(
                'INSERT INTO identity.owner_account
                 (owner_id, singleton_key, email, password_hash, legal_name, display_name, phone,
                  status, password_changed_at, created_at, updated_at)
                 VALUES
                 (:owner_id, 1, :email, :password_hash, :legal_name, :display_name, :phone,
                  :status, :password_changed_at, :created_at, :updated_at)'
            );
            $now = $this->now();
            $stmt->execute([
                ':owner_id' => $ownerId,
                ':email' => strtolower(trim((string) $data['email'])),
                ':password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                ':legal_name' => $data['legal_name'] ?? null,
                ':display_name' => $data['display_name'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':status' => 'ACTIVE',
                ':password_changed_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $preferenceStmt = $this->db->prepare(
                'INSERT INTO identity.owner_preference
                 (owner_id, timezone, language, base_currency, theme, updated_at)
                 VALUES (:owner_id, :timezone, :language, :base_currency, :theme, :updated_at)'
            );
            $preferenceStmt->execute([
                ':owner_id' => $ownerId,
                ':timezone' => 'Asia/Jakarta',
                ':language' => 'id',
                ':base_currency' => 'IDR',
                ':theme' => 'light',
                ':updated_at' => $now,
            ]);

            $this->writeAudit('OWNER', 'OWNER_SETUP', 'OWNER_ACCOUNT', $ownerId, $context);
            $this->writeOwnerActivity('OWNER_SETUP', 'OWNER_ACCOUNT', $ownerId, 'Owner account configured', $context);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $this->sanitizeOwner($this->findOwnerById($ownerId));
    }

    public function authenticate(string $email, string $password, array $context = []): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Email or password is invalid');
        }

        $owner = $this->findOwnerByEmail(strtolower(trim($email)));
        if ($owner === null) {
            password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Email or password is invalid');
        }

        $owner = $this->unlockExpiredLock($owner);
        if ($owner['status'] === 'LOCKED') {
            throw new ApiException(423, 'OWNER_LOCKED', 'The owner account is locked');
        }

        if (!password_verify($password, $owner['password_hash'])) {
            $this->recordFailedLogin($owner);
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Email or password is invalid');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE identity.owner_account
                 SET failed_login_attempts = 0, locked_until = NULL, status = :status, last_login_at = :last_login
                 WHERE owner_id = :owner_id'
            );
            $stmt->execute([
                ':status' => 'ACTIVE',
                ':last_login' => $this->now(),
                ':owner_id' => $owner['owner_id'],
            ]);

            $tokens = $this->issueSession($owner['owner_id'], $context);
            $this->writeAudit('OWNER', 'OWNER_LOGIN', 'OWNER_ACCOUNT', $owner['owner_id'], $context);
            $this->writeOwnerActivity('OWNER_LOGIN', 'OWNER_ACCOUNT', $owner['owner_id'], 'Owner logged in', $context);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        $tokens['owner'] = $this->sanitizeOwner($this->findOwnerById($owner['owner_id']));
        return $tokens;
    }

    public function refresh(string $refreshToken, array $context = []): array
    {
        if ($refreshToken === '') {
            throw new ApiException(401, 'INVALID_REFRESH_TOKEN', 'Refresh token is required');
        }

        $hash = hash('sha256', $refreshToken);
        $stmt = $this->db->prepare(
            'SELECT s.*, o.status AS owner_status, o.locked_until
             FROM identity.owner_session s
             JOIN identity.owner_account o ON o.owner_id = s.owner_id
             WHERE s.refresh_token_hash = :hash
               AND s.revoked_at IS NULL
               AND s.expires_at > UTC_TIMESTAMP(6)
             LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $session = $stmt->fetch();

        if (!$session || $session['owner_status'] !== 'ACTIVE') {
            throw new ApiException(401, 'INVALID_REFRESH_TOKEN', 'Refresh token is invalid or expired');
        }

        $this->db->beginTransaction();
        try {
            $revoke = $this->db->prepare(
                'UPDATE identity.owner_session SET revoked_at = :revoked_at, last_used_at = :last_used_at
                 WHERE session_id = :session_id AND revoked_at IS NULL'
            );
            $revoke->execute([
                ':revoked_at' => $this->now(),
                ':last_used_at' => $this->now(),
                ':session_id' => $session['session_id'],
            ]);
            if ($revoke->rowCount() !== 1) {
                throw new ApiException(401, 'INVALID_REFRESH_TOKEN', 'Refresh token has already been used');
            }

            $tokens = $this->issueSession($session['owner_id'], $context);
            $this->db->commit();
            return $tokens;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function logout(string $accessJti, array $context = []): void
    {
        $ownerStmt = $this->db->prepare(
            'SELECT owner_id FROM identity.owner_session WHERE access_jti = :access_jti LIMIT 1'
        );
        $ownerStmt->execute([':access_jti' => $accessJti]);
        $ownerId = $ownerStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'UPDATE identity.owner_session SET revoked_at = :revoked_at
             WHERE access_jti = :access_jti AND revoked_at IS NULL'
        );
        $stmt->execute([
            ':revoked_at' => $this->now(),
            ':access_jti' => $accessJti,
        ]);
        if (is_string($ownerId) && $ownerId !== '') {
            $this->writeAudit('OWNER', 'OWNER_LOGOUT', 'OWNER_ACCOUNT', $ownerId, $context);
            $this->writeOwnerActivity('OWNER_LOGOUT', 'OWNER_ACCOUNT', $ownerId, 'Owner logged out', $context);
        }
    }

    public function verifyAccessToken(string $jwt): array
    {
        try {
            $claims = (array) JWT::decode($jwt, new Key($this->jwtSecret(), 'HS256'));
        } catch (Throwable) {
            throw new ApiException(401, 'INVALID_TOKEN', 'Invalid or expired token');
        }

        $ownerId = $claims['owner_id'] ?? null;
        $jti = $claims['jti'] ?? null;
        if (!is_string($ownerId) || $ownerId === '' || !is_string($jti) || $jti === '') {
            throw new ApiException(401, 'INVALID_TOKEN', 'Token does not identify an active owner session');
        }

        $stmt = $this->db->prepare(
            'SELECT s.session_id
             FROM identity.owner_session s
             JOIN identity.owner_account o ON o.owner_id = s.owner_id
             WHERE s.owner_id = :owner_id
               AND s.access_jti = :jti
               AND s.revoked_at IS NULL
               AND s.expires_at > UTC_TIMESTAMP(6)
               AND o.status = :status
             LIMIT 1'
        );
        $stmt->execute([
            ':owner_id' => $ownerId,
            ':jti' => $jti,
            ':status' => 'ACTIVE',
        ]);
        if (!$stmt->fetch()) {
            throw new ApiException(401, 'INVALID_TOKEN', 'Owner session is revoked or inactive');
        }

        return $claims;
    }

    public function getOwner(): ?array
    {
        $owner = $this->db->query('SELECT * FROM identity.owner_account LIMIT 1')->fetch();
        return $owner ? $this->sanitizeOwner($owner) : null;
    }

    public function changePassword(string $ownerId, string $currentPassword, string $newPassword): void
    {
        $owner = $this->findOwnerById($ownerId);
        if ($owner === null || !password_verify($currentPassword, $owner['password_hash'])) {
            throw new ApiException(401, 'INVALID_CREDENTIALS', 'Current password is invalid');
        }
        if (!$this->isValidPassword($newPassword)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'New password does not meet security requirements', [
                'new_password' => ['Use at least 12 characters with upper, lower, number, and symbol'],
            ]);
        }
        if (password_verify($newPassword, $owner['password_hash'])) {
            throw new ApiException(422, 'PASSWORD_REUSED', 'New password must differ from the current password');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE identity.owner_account
                 SET password_hash = :password_hash, password_changed_at = :changed_at
                 WHERE owner_id = :owner_id'
            );
            $stmt->execute([
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':changed_at' => $this->now(),
                ':owner_id' => $ownerId,
            ]);
            $revoke = $this->db->prepare(
                'UPDATE identity.owner_session SET revoked_at = :revoked_at
                 WHERE owner_id = :owner_id AND revoked_at IS NULL'
            );
            $revoke->execute([':revoked_at' => $this->now(), ':owner_id' => $ownerId]);
            $this->writeAudit('OWNER', 'OWNER_PASSWORD_CHANGED', 'OWNER_ACCOUNT', $ownerId);
            $this->writeOwnerActivity('OWNER_PASSWORD_CHANGED', 'OWNER_ACCOUNT', $ownerId, 'Owner password changed');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function getPreferences(string $ownerId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM identity.owner_preference WHERE owner_id = :owner_id');
        $stmt->execute([':owner_id' => $ownerId]);
        $preferences = $stmt->fetch();
        if (!$preferences) {
            throw new ApiException(404, 'PREFERENCES_NOT_FOUND', 'Owner preferences were not found');
        }
        return $preferences;
    }

    public function updatePreferences(string $ownerId, array $data): array
    {
        $allowed = ['timezone', 'language', 'base_currency', 'default_exchange', 'theme'];
        $updates = [];
        $params = [':owner_id' => $ownerId, ':updated_at' => $this->now()];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        if ($updates === []) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'No supported preference fields were provided');
        }
        if (isset($data['base_currency']) && !preg_match('/^[A-Z]{3}$/', (string) $data['base_currency'])) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Base currency must be an ISO 4217 code');
        }
        if (isset($data['theme']) && !in_array($data['theme'], ['light', 'dark', 'system'], true)) {
            throw new ApiException(422, 'VALIDATION_ERROR', 'Theme must be light, dark, or system');
        }

        $updates[] = 'updated_at = :updated_at';
        $stmt = $this->db->prepare(
            'UPDATE identity.owner_preference SET ' . implode(', ', $updates) . ' WHERE owner_id = :owner_id'
        );
        $stmt->execute($params);
        return $this->getPreferences($ownerId);
    }

    private function issueSession(string $ownerId, array $context): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $accessTtl = (int) Application::getInstance()->getConfig('JWT_TTL', 3600);
        $refreshTtl = (int) Application::getInstance()->getConfig('JWT_REFRESH_TTL', 86400);
        $jti = $this->uuid();
        $sessionId = $this->uuid();
        $refreshToken = bin2hex(random_bytes(32));
        $issuer = (string) Application::getInstance()->getConfig('APP_URL', 'http://localhost:8000');

        $claims = [
            'iss' => $issuer,
            'sub' => $ownerId,
            'owner_id' => $ownerId,
            'jti' => $jti,
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + $accessTtl,
        ];
        $accessToken = JWT::encode($claims, $this->jwtSecret(), 'HS256');
        $expiresAt = $now->add(new DateInterval('PT' . $refreshTtl . 'S'))->format('Y-m-d H:i:s.u');

        $stmt = $this->db->prepare(
            'INSERT INTO identity.owner_session
             (session_id, owner_id, refresh_token_hash, access_jti, ip_address, user_agent, expires_at, created_at)
             VALUES
             (:session_id, :owner_id, :refresh_hash, :access_jti, :ip_address, :user_agent, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':session_id' => $sessionId,
            ':owner_id' => $ownerId,
            ':refresh_hash' => hash('sha256', $refreshToken),
            ':access_jti' => $jti,
            ':ip_address' => $context['ip_address'] ?? null,
            ':user_agent' => $context['user_agent'] ?? null,
            ':expires_at' => $expiresAt,
            ':created_at' => $this->now(),
        ]);

        return [
            'token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_token' => $refreshToken,
        ];
    }

    private function recordFailedLogin(array $owner): void
    {
        $maxAttempts = (int) Application::getInstance()->getConfig('AUTH_MAX_ATTEMPTS', 5);
        $lockSeconds = (int) Application::getInstance()->getConfig('AUTH_LOCK_SECONDS', 900);
        $attempts = (int) $owner['failed_login_attempts'] + 1;
        $locked = $attempts >= $maxAttempts;
        $lockedUntil = $locked
            ? (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->add(new DateInterval('PT' . $lockSeconds . 'S'))
                ->format('Y-m-d H:i:s.u')
            : null;

        $stmt = $this->db->prepare(
            'UPDATE identity.owner_account
             SET failed_login_attempts = :attempts, status = :status, locked_until = :locked_until
             WHERE owner_id = :owner_id'
        );
        $stmt->execute([
            ':attempts' => $attempts,
            ':status' => $locked ? 'LOCKED' : 'ACTIVE',
            ':locked_until' => $lockedUntil,
            ':owner_id' => $owner['owner_id'],
        ]);
    }

    private function unlockExpiredLock(array $owner): array
    {
        if ($owner['status'] !== 'LOCKED' || $owner['locked_until'] === null) {
            return $owner;
        }
        $lockedUntil = new DateTimeImmutable($owner['locked_until'], new DateTimeZone('UTC'));
        if ($lockedUntil > new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            return $owner;
        }

        $stmt = $this->db->prepare(
            'UPDATE identity.owner_account
             SET status = :status, failed_login_attempts = 0, locked_until = NULL
             WHERE owner_id = :owner_id'
        );
        $stmt->execute([':status' => 'ACTIVE', ':owner_id' => $owner['owner_id']]);
        $owner['status'] = 'ACTIVE';
        $owner['failed_login_attempts'] = 0;
        $owner['locked_until'] = null;
        return $owner;
    }

    private function validateOwnerData(array $data): array
    {
        $errors = [];
        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'A valid email is required';
        }
        if (!isset($data['password']) || !$this->isValidPassword((string) $data['password'])) {
            $errors['password'][] = 'Use at least 12 characters with upper, lower, number, and symbol';
        }
        return $errors;
    }

    private function isValidPassword(string $password): bool
    {
        return strlen($password) >= self::PASSWORD_MIN_LENGTH
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && preg_match('/[^a-zA-Z\d]/', $password) === 1;
    }

    private function findOwnerByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM identity.owner_account WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        return $owner ?: null;
    }

    private function findOwnerById(string $ownerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM identity.owner_account WHERE owner_id = :owner_id LIMIT 1');
        $stmt->execute([':owner_id' => $ownerId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        return $owner ?: null;
    }

    private function sanitizeOwner(?array $owner): array
    {
        if ($owner === null) {
            throw new ApiException(404, 'OWNER_NOT_FOUND', 'Owner account was not found');
        }
        unset(
            $owner['password_hash'],
            $owner['failed_login_attempts'],
            $owner['locked_until'],
            $owner['singleton_key']
        );
        return $owner;
    }

    private function jwtSecret(): string
    {
        $secret = (string) Application::getInstance()->getConfig('JWT_SECRET', '');
        if (strlen($secret) < 32 || $secret === 'change-me-in-production') {
            throw new ApiException(
                500,
                'JWT_NOT_CONFIGURED',
                'JWT secret must be configured with at least 32 characters'
            );
        }
        return $secret;
    }

    private function writeAudit(
        string $actorType,
        string $action,
        string $entityType,
        string $entityId,
        array $context = []
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO governance.audit_log
             (audit_log_id, actor_type, action, entity_type, entity_id, ip_address,
              user_agent, correlation_id, created_at)
             VALUES
             (:id, :actor_type, :action, :entity_type, :entity_id, :ip_address,
              :user_agent, :correlation_id, :created_at)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':actor_type' => $actorType,
            ':action' => $action,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':ip_address' => $context['ip_address'] ?? null,
            ':user_agent' => $context['user_agent'] ?? null,
            ':correlation_id' => $context['correlation_id'] ?? null,
            ':created_at' => $this->now(),
        ]);
    }

    private function writeOwnerActivity(
        string $activityType,
        string $entityType,
        string $entityId,
        string $description,
        array $context = []
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO config.owner_activity_log
             (activity_id, activity_type, entity_type, entity_id, description, ip_address, created_at)
             VALUES (:id, :activity_type, :entity_type, :entity_id, :description, :ip_address, :created_at)'
        );
        $stmt->execute([
            ':id' => $this->uuid(),
            ':activity_type' => $activityType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':ip_address' => $context['ip_address'] ?? null,
            ':created_at' => $this->now(),
        ]);
    }
}
