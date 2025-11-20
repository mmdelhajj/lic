#!/usr/bin/env php
<?php
/**
 * License Server - Automatic License Checker
 * Runs via cron every 12 hours to check and expire licenses
 */

require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting license check...\n";

try {
    $db = Database::getInstance();
    $now = time();
    $expiredCount = 0;

    // Get all active licenses
    $licenses = $db->fetchAll('SELECT * FROM licenses WHERE status = "active"');

    foreach ($licenses as $license) {
        $expiresAt = strtotime($license['expires_at']);

        if ($expiresAt < $now) {
            // License has expired
            $db->query('UPDATE licenses SET status = "expired" WHERE id = ?', [$license['id']]);

            // Log expiration
            $db->query(
                'INSERT INTO license_logs (license_id, action, details) VALUES (?, ?, ?)',
                [$license['id'], 'expired', 'License expired automatically']
            );

            $expiredCount++;
            echo "  ❌ Expired: {$license['domain']} (ID: {$license['id']})\n";
        }
    }

    if ($expiredCount > 0) {
        echo "✅ Expired {$expiredCount} license(s)\n";
    } else {
        echo "✅ No licenses to expire\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] License check completed\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
