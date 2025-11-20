<?php
/**
 * License Server - Admin Dashboard
 */

require_once __DIR__ . '/../config/database.php';
session_start();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $db = Database::getInstance();
    $user = $db->fetchOne('SELECT * FROM admin_users WHERE username = ?', [$username]);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        
        $db->query('UPDATE admin_users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Check if logged in
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

if (!$loggedIn) {
    include 'pages/login.php';
    exit;
}

// Get statistics
$db = Database::getInstance();
$stats = [
    'total' => $db->fetchOne('SELECT COUNT(*) as count FROM licenses')['count'] ?? 0,
    'active' => $db->fetchOne('SELECT COUNT(*) as count FROM licenses WHERE status = "active"')['count'] ?? 0,
    'trial' => $db->fetchOne('SELECT COUNT(*) as count FROM licenses WHERE installation_type = "trial"')['count'] ?? 0,
    'paid' => $db->fetchOne('SELECT COUNT(*) as count FROM licenses WHERE installation_type = "paid"')['count'] ?? 0,
];

// Get all installations
$installations = $db->fetchAll('SELECT * FROM licenses ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>License Server - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .header-content { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 0.85em; margin-bottom: 10px; text-transform: uppercase; }
        .stat-card .value { font-size: 2.5em; font-weight: bold; color: #333; }
        .section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 500; }
        .badge-trial { background: #fef3c7; color: #d97706; }
        .badge-paid { background: #d1fae5; color: #059669; }
        .badge-active { background: #dbeafe; color: #0284c7; }
        .badge-suspended { background: #fee2e2; color: #dc2626; }
        .badge-expired { background: #e5e7eb; color: #6b7280; }
        .online { color: #10b981; }
        .offline { color: #9ca3af; }
        .btn { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85em; margin-right: 5px; }
        .btn-activate { background: #10b981; color: white; }
        .btn-suspend { background: #f59e0b; color: white; }
        .btn-reactivate { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn:hover { opacity: 0.9; }
        .logout-btn { background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🔐 License Server Dashboard</h1>
            <div>
                <span style="opacity: 0.9; margin-right: 15px;">👤 <?= $_SESSION['admin_username'] ?></span>
                <a href="?logout" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Installations</h3>
                <div class="value"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Licenses</h3>
                <div class="value"><?= $stats['active'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Trial Licenses</h3>
                <div class="value"><?= $stats['trial'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Paid Licenses</h3>
                <div class="value"><?= $stats['paid'] ?></div>
            </div>
        </div>

        <div class="section">
            <h2 style="margin-bottom: 20px;">All Installations</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Domain</th>
                        <th>IP Address</th>
                        <th>Type</th>
                        <th>Expires</th>
                        <th>Days Left</th>
                        <th>Online</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installations as $inst): ?>
                    <?php
                        $daysLeft = max(0, ceil((strtotime($inst['expires_at']) - time()) / 86400));
                        $isOnline = $inst['last_online'] && (time() - strtotime($inst['last_online'])) < 300;
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?= $inst['status'] ?>"><?= ucfirst($inst['status']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($inst['customer_name']) ?></td>
                        <td><?= htmlspecialchars($inst['domain']) ?></td>
                        <td><?= htmlspecialchars($inst['ip_address'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge badge-<?= $inst['installation_type'] ?>"><?= ucfirst($inst['installation_type']) ?></span>
                        </td>
                        <td><?= $inst['expires_at'] ?></td>
                        <td><?= $daysLeft ?></td>
                        <td>
                            <span class="<?= $isOnline ? 'online' : 'offline' ?>">●</span>
                        </td>
                        <td>
                            <?php if ($inst['installation_type'] === 'trial'): ?>
                                <button class="btn btn-activate" onclick="activateLicense(<?= $inst['id'] ?>, '<?= addslashes($inst['domain']) ?>')">Activate</button>
                            <?php elseif ($inst['status'] === 'active'): ?>
                                <button class="btn btn-suspend" onclick="suspendLicense(<?= $inst['id'] ?>, '<?= addslashes($inst['domain']) ?>')">Suspend</button>
                            <?php elseif ($inst['status'] === 'suspended'): ?>
                                <button class="btn btn-reactivate" onclick="reactivateLicense(<?= $inst['id'] ?>, '<?= addslashes($inst['domain']) ?>')">Reactivate</button>
                            <?php endif; ?>
                            <button class="btn btn-delete" onclick="deleteLicense(<?= $inst['id'] ?>, '<?= addslashes($inst['domain']) ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function activateLicense(licenseId, domain) {
            const duration = prompt("Activate license for " + domain + "\n\nEnter duration in years (1-5):", "1");
            if (!duration || isNaN(duration) || duration < 1 || duration > 5) {
                alert("Invalid duration. Must be 1-5 years.");
                return;
            }
            
            const customerName = prompt("Enter customer name:");
            if (!customerName) return;
            
            const customerEmail = prompt("Enter customer email (optional):", "");
            
            const formData = new FormData();
            formData.append("license_id", licenseId);
            formData.append("duration", duration);
            formData.append("customer_name", customerName);
            formData.append("customer_email", customerEmail);
            
            fetch("../api/activate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("License activated for " + duration + " year(s) successfully!\n\nNew License Key: " + data.data.new_license_key);
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }

        function suspendLicense(licenseId, domain) {
            if (!confirm("Suspend license for " + domain + "?\n\nCustomer will not be able to use the bot.")) return;
            
            const formData = new FormData();
            formData.append("license_id", licenseId);
            formData.append("action", "suspend");
            
            fetch("../api/deactivate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("License suspended successfully!");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }

        function reactivateLicense(licenseId, domain) {
            if (!confirm("Reactivate license for " + domain + "?\n\nCustomer will be able to use the bot again.")) return;
            
            const formData = new FormData();
            formData.append("license_id", licenseId);
            
            fetch("../api/reactivate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("License reactivated successfully!");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }

        function deleteLicense(licenseId, domain) {
            if (!confirm("DELETE license for " + domain + "?\n\nThis action CANNOT be undone!")) return;
            if (!confirm("Are you ABSOLUTELY sure you want to permanently delete this license?")) return;
            
            const formData = new FormData();
            formData.append("license_id", licenseId);
            formData.append("action", "delete");
            
            fetch("../api/deactivate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("License deleted successfully!");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }
    </script>
</body>
</html>
