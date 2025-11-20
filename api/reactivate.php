<?php
/**
 * License Server - Reactivate Endpoint
 * Restores suspended licenses
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

if (empty($licenseId)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing license_id']));
}

try {
    $db = Database::getInstance();

    // Get license
    $license = $db->fetchOne('SELECT * FROM licenses WHERE id = ?', [$licenseId]);

    if (!$license) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'License not found']));
    }

    if ($license['status'] !== 'suspended') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'License is not suspended']));
    }

    // Reactivate license
    $db->query('UPDATE licenses SET status = "active", updated_at = NOW() WHERE id = ?', [$licenseId]);

    // Log action
    $db->query(
        'INSERT INTO license_logs (license_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
        [$licenseId, 'reactivated', 'Reactivated by admin', $_SERVER['REMOTE_ADDR'] ?? 'unknown']
    );

    echo json_encode(['success' => true, 'message' => 'License reactivated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
