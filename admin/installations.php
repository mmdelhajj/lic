<?php
session_start();
require_once "../config/database.php";

// Handle login
if (isset($_POST["login"])) {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin["password_hash"])) {
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_username"] = $admin["username"];
        header("Location: installations.php");
        exit;
    } else {
        $error = "Invalid credentials";
    }
}

// Handle logout
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: installations.php");
    exit;
}

// Check auth
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>License Server - Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 350px; }
            h2 { margin-bottom: 30px; color: #333; text-align: center; }
            input { width: 100%; padding: 12px; margin: 8px 0 16px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
            button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            button:hover { background: #5568d3; }
            .error { background: #fee; color: #c33; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>License Server Login</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required autofocus>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get all installations
$db = getDB();
$stmt = $db->query("SELECT * FROM licenses ORDER BY first_seen DESC");
$installations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$totalInstallations = count($installations);
$trialCount = count(array_filter($installations, fn($l) => $l["installation_type"] === "trial"));
$paidCount = count(array_filter($installations, fn($l) => $l["installation_type"] === "paid"));
$activeCount = count(array_filter($installations, fn($l) => $l["status"] === "active"));
$expiredCount = count(array_filter($installations, fn($l) => $l["status"] === "expired"));
$suspendedCount = count(array_filter($installations, fn($l) => $l["status"] === "suspended"));
?>
<!DOCTYPE html>
<html>
<head>
    <title>License Server - Installations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header h1 { font-size: 24px; }
        .header a { color: white; text-decoration: none; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 5px; }
        .header a:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 14px; color: #888; font-weight: normal; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #333; }
        .installations { background: white; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        .installations h2 { padding: 20px; border-bottom: 1px solid #eee; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 13px; font-weight: 600; color: #666; border-bottom: 1px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        tr:hover { background: #f9fafb; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge.trial { background: #fff3cd; color: #856404; }
        .badge.paid { background: #d4edda; color: #155724; }
        .badge.active { background: #d1ecf1; color: #0c5460; }
        .badge.expired { background: #f8d7da; color: #721c24; }
        .badge.suspended { background: #e2e3e5; color: #383d41; }
        .btn { padding: 6px 12px; border: none; border-radius: 5px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-activate { background: #28a745; color: white; }
        .btn-activate:hover { background: #218838; }
        .btn-extend { background: #8b5cf6; color: white; }
        .btn-extend:hover { background: #7c3aed; }
        .btn-suspend { background: #ffc107; color: #333; }
        .btn-suspend:hover { background: #e0a800; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-delete:hover { background: #c82333; }
        .online { color: #28a745; font-weight: 600; }
        .offline { color: #dc3545; font-weight: 600; }
    </style>
    <script>
        function activateLicense(licenseId, domain) {
            const duration = prompt("Activate license for " + domain + "\n\nEnter duration in years (1-5):", "1");
            if (!duration || !["1", "2", "3", "4", "5"].includes(duration)) {
                if (duration !== null) alert("Invalid duration. Must be 1-5 years.");
                return;
            }
            
            const customerName = prompt("Enter customer name (optional):", "");
            const customerEmail = prompt("Enter customer email (optional):", "");
            
            if (!confirm("Activate license for " + domain + " for " + duration + " year(s)?")) {
                return;
            }
            
            const formData = new FormData();
            formData.append("license_id", licenseId);
            formData.append("duration", duration);
            if (customerName) formData.append("customer_name", customerName);
            if (customerEmail) formData.append("customer_email", customerEmail);
            
            fetch("../api/activate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("License activated successfully!\n\nNew License Key: " + data.data.new_license_key + "\n\nExpires: " + data.data.expires_at);
                    location.reload();
                } else {
                    alert("Activation failed: " + data.message);
                }
            })
            .catch(err => alert("Error: " + err));
        }
        
        function suspendLicense(licenseId, domain) {
            if (!confirm("Suspend license for " + domain + "?\n\nCustomer will NOT be able to use the bot.")) {
                return;
            }
            
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
                    alert("Suspension failed: " + data.message);
                }
            })
            .catch(err => alert("Error: " + err));
        }
        
        function reactivateLicense(licenseId, domain) {
            if (!confirm("Reactivate license for " + domain + "?\n\nCustomer will be able to use the bot again.")) {
                return;
            }
            
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
                    alert("Reactivation failed: " + data.message);
                }
            })
            .catch(err => alert("Error: " + err));
        }
        

        function deleteLicense(licenseId, domain) {
            if (!confirm("DELETE license for " + domain + "?\n\nThis action CANNOT be undone!\n\nCustomer will need to re-register.")) {
                return;
            }
            
            if (!confirm("Are you ABSOLUTELY SURE?\n\nThis will permanently delete the license!")) {
                return;
            }
            
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
                    alert("Deletion failed: " + data.message);
                }
            })
            .catch(err => alert("Error: " + err));
        }
        
        function autoRefresh() {
            setTimeout(() => location.reload(), 30000);
        }
        
        window.onload = autoRefresh;

        function extendLicense(licenseId, domain) {
            const duration = prompt("Extend/Change license duration for " + domain + "\\n\\nEnter NEW total duration in years (1-5):\\n(This will replace the current expiry date)", "5");
            if (!duration || isNaN(duration) || duration < 1 || duration > 5) {
                alert("Invalid duration. Must be 1-5 years.");
                return;
            }

            if (!confirm("Change license to " + duration + " year(s)?\\n\\nThis will SET a new expiry date from today.")) return;

            const formData = new FormData();
            formData.append("license_id", licenseId);
            formData.append("duration", duration);
            formData.append("customer_name", domain);
            formData.append("customer_email", "");

            fetch("../api/activate.php", {
                method: "POST",
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert("✅ License extended to " + duration + " year(s) successfully!\\n\\nLicense key unchanged - customer can keep using the same bot.\\nNew expiry date will take effect on next license validation (within 1 hour).");
                    location.reload();
                } else {
                    alert("Error: " + data.message);
                }
            });
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>License Server - Installations</h1>
        <div>
            <a href="settings.php" style="margin-right: 10px;">⚙️ Settings</a>
            <a href="?logout=1">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Installations</h3>
                <div class="value"><?php echo $totalInstallations; ?></div>
            </div>
            <div class="stat-card">
                <h3>Trial Installations</h3>
                <div class="value"><?php echo $trialCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Paid Licenses</h3>
                <div class="value"><?php echo $paidCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active</h3>
                <div class="value"><?php echo $activeCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Expired</h3>
                <div class="value"><?php echo $expiredCount; ?></div>
            </div>
            <div class="stat-card">
                <h3>Suspended</h3>
                <div class="value"><?php echo $suspendedCount; ?></div>
            </div>
        </div>
        
        <div class="installations">
            <h2>All Installations</h2>
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>License Key</th>
                        <th>IP</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Days Left</th>
                        <th>Last Online</th>
                        <th>First Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installations as $inst): 
                        $isOnline = $inst["last_online"] && (strtotime($inst["last_online"]) > strtotime("-5 minutes"));
                        $expiresAt = $inst["installation_type"] === "trial" ? $inst["trial_ends_at"] : $inst["expires_at"];
                        $daysLeft = $expiresAt ? max(0, ceil((strtotime($expiresAt) - time()) / 86400)) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inst["domain"]); ?></strong></td>
                            <td><code style="font-size:11px;"><?php echo htmlspecialchars($inst["license_key"]); ?></code></td>
                            <td><?php echo htmlspecialchars($inst["ip_address"] ?: "-"); ?></td>
                            <td><span class="badge <?php echo $inst["installation_type"]; ?>"><?php echo strtoupper($inst["installation_type"]); ?></span></td>
                            <td><span class="badge <?php echo $inst["status"]; ?>"><?php echo strtoupper($inst["status"]); ?></span></td>
                            <td><?php echo $expiresAt ? date("Y-m-d H:i", strtotime($expiresAt)) : "-"; ?></td>
                            <td><?php echo $daysLeft; ?> days</td>
                            <td class="<?php echo $isOnline ? "online" : "offline"; ?>">
                                <?php echo $inst["last_online"] ? date("Y-m-d H:i", strtotime($inst["last_online"])) : "Never"; ?>
                            </td>
                            <td><?php echo date("Y-m-d H:i", strtotime($inst["first_seen"])); ?></td>
                            <td>
                                <?php if ($inst["installation_type"] === "trial" && $inst["status"] !== "expired"): ?>
                                    <button class="btn btn-activate" onclick="activateLicense(<?php echo $inst["id"]; ?>, '<?php echo addslashes($inst["domain"]); ?>')">Activate (1-5 years)</button>
                                <?php endif; ?>
                                <?php if ($inst["status"] === "suspended"): ?>
                                    <button class="btn btn-activate" onclick="reactivateLicense(<?php echo $inst["id"]; ?>, '<?php echo addslashes($inst["domain"]); ?>')">Reactivate</button>
                                <?php endif; ?>
                                <?php if ($inst["status"] === "active"): ?>
                                    <button class="btn btn-suspend" onclick="suspendLicense(<?php echo $inst["id"]; ?>, '<?php echo addslashes($inst["domain"]); ?>')">Suspend</button>
                                    <?php if ($inst["installation_type"] === "paid"): ?>
                                    <button class="btn btn-extend" onclick="extendLicense(<?php echo $inst["id"]; ?>, '<?php echo addslashes($inst["domain"]); ?>')">Extend</button>
                                <?php endif; ?>
                                <?php endif; ?>
                                <button class="btn btn-delete" onclick="deleteLicense(<?php echo $inst["id"]; ?>, '<?php echo addslashes($inst["domain"]); ?>')">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
