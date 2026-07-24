<?php

/**
 * Data retention and archival job runner.
 *
 * Usage:
 *   php bin/retention-job.php [--dry-run] [--category=<name>] [--gdpr=<owner_id>]
 *
 * Examples:
 *   php bin/retention-job.php --dry-run
 *   php bin/retention-job.php --category=api_access_log
 *   php bin/retention-job.php --gdpr=0192f5a3-1234-5678-9abc-def012345678
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\Core\Application;
use Platform\Core\Data\RetentionJob;

Application::getInstance();

$opts = getopt('', ['dry-run', 'category::', 'gdpr::']);

$dryRun = isset($opts['dry-run']);
$job = new RetentionJob($dryRun);

if (isset($opts['gdpr'])) {
    $result = $job->gdprErasure($opts['gdpr']);
    echo "GDPR Erasure Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    $category = $opts['category'] ?? null;
    $results = $job->run($category);
    echo "Retention Job Results" . ($dryRun ? ' (DRY RUN)' : '') . ":\n";
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
}
