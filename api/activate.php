<?php
/**
 * License Server - Manual Activation Endpoint
 * Converts trial licenses to paid (1-5 years)
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Get POST data
$licenseId = $_POST['license_id'] ?? 0;
$duration = (int)($_POST['duration'] ?? 0);
$customerName = $_POST['customer_name'] ?? '';
$customerEmail = $_POST['customer_email'] ?? '';

// Validate
if (empty($licenseId) || empty($duration) || empty($customerName)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

if (!\in_array($duration, [1, 2, 3, 4, 5])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Duration must be 1-5 years']));
}

try {
    $db = Database::getInstance();

    // Get license
    $license = $db->fetchOne('SELECT * FROM licenses WHERE id = ?', [$licenseId]);

    if (!$license) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'License not found']));
    }

    // Generate new PAID license key
    $newLicenseKey = 'PAID-' . strtoupper(bin2hex(random_bytes(16)));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $duration . ' years'));

    // Update license
    $updateStmt = $db->query(
        'UPDATE licenses SET
            license_key = ?,
            customer_name = ?,
            customer_email = ?,
            installation_type = "paid",
            status = "active",
            activated_by_admin = TRUE,
            expires_at = ?,
            updated_at = NOW()
        WHERE id = ?',
        [$newLicenseKey, $customerName, $customerEmail, $expiresAt, $licenseId]
    );

    // Log activation
    $db->query(
        'INSERT INTO license_logs (license_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
        [
            $licenseId,
            'activated',
            "Activated for $duration year(s) by admin",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => "License activated for $duration year(s) successfully",
        'data' => [
            'new_license_key' => $newLicenseKey,
            'expires_at' => $expiresAt
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
