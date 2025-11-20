<?php
/**
 * License Server - Heartbeat Endpoint
 * Updates last_online timestamp for installations
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Get POST data
$licenseKey = $_POST['license_key'] ?? '';
$ip = $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$version = $_POST['version'] ?? '1.0.0';

if (empty($licenseKey)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing license_key']));
}

try {
    $db = Database::getInstance();

    // Find license
    $license = $db->fetchOne('SELECT id FROM licenses WHERE license_key = ?', [$licenseKey]);

    if (!$license) {
        echo json_encode(['success' => false, 'message' => 'License not found']);
        exit;
    }

    // Update last_online, IP, and version
    $db->query(
        'UPDATE licenses SET last_online = NOW(), ip_address = ?, bot_version = ? WHERE id = ?',
        [$ip, $version, $license['id']]
    );

    echo json_encode(['success' => true, 'message' => 'Heartbeat received']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
