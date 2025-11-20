<?php
/**
 * License Server - Deactivate/Delete Endpoint
 * Suspends or deletes licenses
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
$action = $_POST['action'] ?? ''; // 'suspend' or 'delete'

if (empty($licenseId) || empty($action)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing license_id or action']));
}

if (!\in_array($action, ['suspend', 'delete'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Action must be suspend or delete']));
}

try {
    $db = Database::getInstance();

    if ($action === 'suspend') {
        // Suspend license
        $db->query('UPDATE licenses SET status = "suspended", updated_at = NOW() WHERE id = ?', [$licenseId]);

        // Log action
        $db->query(
            'INSERT INTO license_logs (license_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [$licenseId, 'suspended', 'Suspended by admin', $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        );

        echo json_encode(['success' => true, 'message' => 'License suspended successfully']);

    } elseif ($action === 'delete') {
        // Delete license
        $db->query('DELETE FROM licenses WHERE id = ?', [$licenseId]);

        echo json_encode(['success' => true, 'message' => 'License deleted successfully']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
