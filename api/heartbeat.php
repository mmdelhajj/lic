<?php
/**
 * Heartbeat/Check-in API
 * Bot installations call this periodically to update status
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

// Get parameters
$licenseKey = isset($_POST['license_key']) ? trim($_POST['license_key']) : (isset($_GET['license_key']) ? trim($_GET['license_key']) : '');
$ip = isset($_POST['ip']) ? trim($_POST['ip']) : (isset($_GET['ip']) ? trim($_GET['ip']) : $_SERVER['REMOTE_ADDR']);
$version = isset($_POST['version']) ? trim($_POST['version']) : (isset($_GET['version']) ? trim($_GET['version']) : '1.0.0');

if (empty($licenseKey)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing license key']));
}

try {
    $db = getDB();
    
    // Get license
    $stmt = $db->prepare('SELECT * FROM licenses WHERE license_key = ? LIMIT 1');
    $stmt->execute([$licenseKey]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$license) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'License not found']));
    }
    
    // Update last_online timestamp
    $updateStmt = $db->prepare('
        UPDATE licenses 
        SET last_online = NOW(), 
            ip_address = ?,
            bot_version = ?
        WHERE id = ?
    ');
    $updateStmt->execute([$ip, $version, $license['id']]);
    
    // Check status
    $isExpired = false;
    $daysLeft = 0;
    $expiresAt = '';
    
    if ($license['installation_type'] === 'trial') {
        $trialEnd = strtotime($license['trial_ends_at']);
        $now = time();
        $daysLeft = max(0, ceil(($trialEnd - $now) / 86400));
        $isExpired = $trialEnd < $now;
        $expiresAt = $license['trial_ends_at'];
        
        // Auto-expire trial if needed
        if ($isExpired && $license['status'] === 'active') {
            $db->prepare('UPDATE licenses SET status = "expired" WHERE id = ?')->execute([$license['id']]);
            $license['status'] = 'expired';
        }
    } else {
        $expiry = strtotime($license['expires_at']);
        $now = time();
        $daysLeft = max(0, ceil(($expiry - $now) / 86400));
        $isExpired = $expiry < $now;
        $expiresAt = $license['expires_at'];
        
        // Auto-expire if needed
        if ($isExpired && $license['status'] === 'active') {
            $db->prepare('UPDATE licenses SET status = "expired" WHERE id = ?')->execute([$license['id']]);
            $license['status'] = 'expired';
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Heartbeat received',
        'data' => [
            'installation_type' => $license['installation_type'],
            'status' => $license['status'],
            'expires_at' => $expiresAt,
            'days_left' => $daysLeft,
            'is_expired' => $isExpired,
            'activated' => $license['activated_by_admin'] == 1
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Heartbeat failed: ' . $e->getMessage()]);
}
