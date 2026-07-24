<?php

declare(strict_types=1);

namespace Platform\Identity;

interface IdentityServiceInterface
{
    public function isSetupRequired(): bool;

    public function setupOwner(array $data, array $context = []): array;

    public function authenticate(string $email, string $password, array $context = []): array;

    public function refresh(string $refreshToken, array $context = []): array;

    public function logout(string $accessJti, array $context = []): void;

    public function verifyAccessToken(string $jwt): array;

    public function getOwner(): ?array;

    public function changePassword(string $ownerId, string $currentPassword, string $newPassword): void;

    public function getPreferences(string $ownerId): array;

    public function updatePreferences(string $ownerId, array $data): array;

    /**
     * Emergency kill switch — locks owner account and revokes all sessions.
     * Halts all trading and sensitive operations.
     */
    public function activateKillSwitch(string $reason): array;

    /**
     * Deactivate kill switch — unlocks owner account.
     */
    public function deactivateKillSwitch(): array;

    /**
     * Check if kill switch is active (owner is locked).
     */
    public function isKillSwitchActive(): bool;
}
