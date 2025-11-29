<?php
/**
 * License Server - Validation Endpoint
 * Validates license keys and returns status
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Get GET parameters
$licenseKey = $_GET['key'] ?? '';
$domain = $_GET['domain'] ?? '';
$fingerprint = $_GET['fingerprint'] ?? '';
$serverIp = $_GET['server_ip'] ?? $_POST['server_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Validate required fields
if (empty($licenseKey) || empty($domain)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Missing required fields: key and domain'
    ]));
}

try {
    $db = getDB();

    // Find license
    $stmt = $db->prepare('SELECT * FROM licenses WHERE license_key = ?');
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid license key'
        ]);
        exit;
    }

    // Check domain match
    if ($license['domain'] !== $domain) {
        echo json_encode([
            'success' => false,
            'message' => 'License is registered for a different domain'
        ]);
        exit;
    }

    // Check status
    if ($license['status'] === 'suspended') {
        echo json_encode([
            'success' => false,
            'message' => 'License has been suspended. Contact support.'
        ]);
        exit;
    }

    if ($license['status'] === 'expired') {
        echo json_encode([
            'success' => false,
            'message' => 'License has expired. Contact support to renew.'
        ]);
        exit;
    }

    // Check expiry date
    $now = time();
    $expiresAt = strtotime($license['expires_at']);

    if ($expiresAt < $now) {
        // Mark as expired
        $updateStmt = $db->prepare('UPDATE licenses SET status = ? WHERE id = ?');
        $updateStmt->execute(['expired', $license['id']]);

        echo json_encode([
            'success' => false,
            'message' => 'License has expired on ' . $license['expires_at']
        ]);
        exit;
    }

    // License is valid - Update last_check, last_online, check_count
    $updateStmt = $db->prepare('UPDATE licenses SET last_check = NOW(), last_online = NOW(), check_count = check_count + 1, ip_address = ? WHERE id = ?');
    $updateStmt->execute([$serverIp, $license['id']]);

    $daysLeft = ceil(($expiresAt - $now) / 86400);

    echo json_encode([
        'success' => true,
        'message' => 'License is valid',
        'data' => [
            'license_key' => $license['license_key'],
            'customer' => $license['customer_name'],
            'domain' => $license['domain'],
            'installation_type' => $license['installation_type'],
            'status' => $license['status'],
            'expires_at' => $license['expires_at'],
            'days_left' => $daysLeft
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
