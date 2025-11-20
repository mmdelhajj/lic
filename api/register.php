<?php
/**
 * License Server - Auto-Registration Endpoint
 * Creates 3-day trial licenses for new installations
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Get POST data
$domain = $_POST['domain'] ?? '';
$fingerprint = $_POST['fingerprint'] ?? '';
$ip = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$version = $_POST['version'] ?? '1.0.0';

// Validate required fields
if (empty($domain) || empty($fingerprint)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Missing required fields: domain and fingerprint'
    ]));
}

try {
    $db = Database::getInstance();

    // Check if installation already exists (by domain OR fingerprint)
    $existing = $db->fetchOne(
        'SELECT * FROM licenses WHERE domain = ? OR fingerprint = ? LIMIT 1',
        [$domain, $fingerprint]
    );

    if ($existing) {
        // Return existing license
        $daysLeft = max(0, ceil((strtotime($existing['expires_at']) - time()) / 86400));

        echo json_encode([
            'success' => true,
            'message' => 'Installation already registered',
            'data' => [
                'license_key' => $existing['license_key'],
                'installation_type' => $existing['installation_type'],
                'days_left' => $daysLeft,
                'expires_at' => $existing['expires_at']
            ]
        ]);
        exit;
    }

    // Generate new trial license
    $licenseKey = 'TRIAL-' . strtoupper(bin2hex(random_bytes(16)));
    $trialEndsAt = date('Y-m-d H:i:s', strtotime('+3 days'));
    $expiresAt = $trialEndsAt;

    // Insert new license
    $insertStmt = $db->query(
        'INSERT INTO licenses (
            license_key, customer_name, customer_email, domain, ip_address,
            fingerprint, product_name, bot_version, status, installation_type,
            activated_by_admin, trial_ends_at, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $licenseKey,
            'New Customer - ' . $domain,
            '',
            $domain,
            $ip,
            $fingerprint,
            'WhatsApp Bot',
            $version,
            'active',
            'trial',
            0,
            $trialEndsAt,
            $expiresAt
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => '3-day trial license created successfully',
        'data' => [
            'license_key' => $licenseKey,
            'installation_type' => 'trial',
            'days_left' => 3,
            'expires_at' => $expiresAt
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
