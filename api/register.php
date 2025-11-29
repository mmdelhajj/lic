<?php
/**
 * Auto-Registration API
 * New bot installations call this to get 3-day trial license
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Get parameters
$domain = isset($_POST['domain']) ? trim($_POST['domain']) : (isset($_GET['domain']) ? trim($_GET['domain']) : '');
$ip = isset($_POST['ip']) ? trim($_POST['ip']) : (isset($_GET['ip']) ? trim($_GET['ip']) : $_SERVER['REMOTE_ADDR']);
$fingerprint = isset($_POST['fingerprint']) ? trim($_POST['fingerprint']) : (isset($_GET['fingerprint']) ? trim($_GET['fingerprint']) : '');
$version = isset($_POST['version']) ? trim($_POST['version']) : (isset($_GET['version']) ? trim($_GET['version']) : '1.0.0');

// Validate required parameters
if (empty($domain) || empty($fingerprint)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Missing required parameters: domain and fingerprint'
    ]));
}

try {
    $db = getDB();
    
    // Check if installation already exists (by domain OR fingerprint)
    $stmt = $db->prepare('
        SELECT * FROM licenses 
        WHERE domain = ? OR server_fingerprint = ? 
        ORDER BY id DESC 
        LIMIT 1
    ');
    $stmt->execute([$domain, $fingerprint]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Installation already registered - return existing license
        
        // Update last_online and IP
        $updateStmt = $db->prepare('
            UPDATE licenses 
            SET last_online = NOW(), 
                ip_address = ?,
                bot_version = ?
            WHERE id = ?
        ');
        $updateStmt->execute([$ip, $version, $existing['id']]);
        
        // Check if trial expired
        $isExpired = false;
        $daysLeft = 0;
        
        if ($existing['installation_type'] === 'trial') {
            $trialEnd = strtotime($existing['trial_ends_at']);
            $now = time();
            $daysLeft = max(0, ceil(($trialEnd - $now) / 86400));
            $isExpired = $trialEnd < $now;
        } else {
            $expiresAt = strtotime($existing['expires_at']);
            $now = time();
            $daysLeft = max(0, ceil(($expiresAt - $now) / 86400));
            $isExpired = $expiresAt < $now && $existing['status'] === 'expired';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Installation already registered',
            'already_registered' => true,
            'data' => [
                'license_key' => $existing['license_key'],
                'installation_type' => $existing['installation_type'],
                'status' => $existing['status'],
                'expires_at' => $existing['installation_type'] === 'trial' ? $existing['trial_ends_at'] : $existing['expires_at'],
                'days_left' => $daysLeft,
                'is_expired' => $isExpired,
                'activated' => $existing['activated_by_admin'] == 1
            ]
        ]);
        exit;
    }
    
    // Generate new trial license
    $licenseKey = 'TRIAL-' . strtoupper(bin2hex(random_bytes(16)));
    $trialEndsAt = date('Y-m-d H:i:s', strtotime('+3 days'));
    $expiresAt = $trialEndsAt; // Same as trial for now
    
    $insertStmt = $db->prepare('
        INSERT INTO licenses (
            license_key,
            customer_name,
            customer_email,
            domain,
            ip_address,
            server_fingerprint,
            product_name,
            bot_version,
            status,
            installation_type,
            activated_by_admin,
            trial_ends_at,
            expires_at,
            first_seen,
            last_online
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ');
    
    $insertStmt->execute([
        $licenseKey,
        'New Customer - ' . $domain,  // Temporary name
        '',  // No email yet
        $domain,
        $ip,
        $fingerprint,
        'WhatsApp Bot',
        $version,
        'active',
        'trial',
        0,  // Not activated by admin yet
        $trialEndsAt,
        $expiresAt
    ]);
    
    // Log registration
    $licenseId = $db->lastInsertId();
    $logStmt = $db->prepare('
        INSERT INTO license_logs (license_id, action, ip_address, user_agent, response, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    $logStmt->execute([
        $licenseId,
        'auto_register',
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'Trial license created - 3 days'
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '3-day trial license created successfully',
        'already_registered' => false,
        'data' => [
            'license_key' => $licenseKey,
            'installation_type' => 'trial',
            'status' => 'active',
            'expires_at' => $trialEndsAt,
            'days_left' => 3,
            'is_expired' => false,
            'activated' => false,
            'trial_message' => 'You have 3 days trial. Contact support to activate full license.'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}
