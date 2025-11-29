<?php
/**
 * License Extension API
 * Admin uses this to extend/change duration of existing paid licenses
 * IMPORTANT: This keeps the same license key (does not generate a new one)
 */
session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

require_once '../config/database.php';

// Get parameters
$licenseId = isset($_POST['license_id']) ? intval($_POST['license_id']) : 0;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 1; // Years: 1-5

if ($licenseId <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid license ID']));
}

if (!in_array($duration, [1, 2, 3, 4, 5])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Duration must be 1-5 years']));
}

try {
    $db = getDB();

    // Get current license
    $stmt = $db->prepare('SELECT * FROM licenses WHERE id = ? LIMIT 1');
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'License not found']));
    }

    // Only allow extending paid licenses
    if ($license['installation_type'] !== 'paid') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Can only extend paid licenses. Use Activate for trial licenses.']));
    }

    // Calculate new expiry date from TODAY (not from old expiry)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $duration . ' years'));

    // Keep the same license key, just update expiry date
    $updateStmt = $db->prepare('
        UPDATE licenses
        SET expires_at = ?,
            status = "active"
        WHERE id = ?
    ');

    $updateStmt->execute([
        $expiresAt,
        $licenseId
    ]);

    // Log extension
    $logStmt = $db->prepare('
        INSERT INTO license_logs (license_id, action, ip_address, user_agent, response, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    $logStmt->execute([
        $licenseId,
        'admin_extend',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'Admin Panel',
        "Extended to {$duration} year(s) - New expiry: {$expiresAt}"
    ]);

    echo json_encode([
        'success' => true,
        'message' => "License extended to {$duration} year(s) successfully",
        'data' => [
            'license_key' => $license['license_key'], // Same key
            'installation_type' => 'paid',
            'status' => 'active',
            'expires_at' => $expiresAt,
            'duration_years' => $duration,
            'message' => 'License key unchanged - customer can continue using the same key'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Extension failed: ' . $e->getMessage()
    ]);
}
