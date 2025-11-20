-- License Server Database Schema
-- MySQL 5.7+ / MariaDB 10.2+

CREATE DATABASE IF NOT EXISTS license_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE license_server;

-- Licenses Table
CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) DEFAULT '',
    domain VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    fingerprint VARCHAR(64) NOT NULL,
    product_name VARCHAR(100) DEFAULT 'WhatsApp Bot',
    bot_version VARCHAR(50) DEFAULT '1.0.0',
    status ENUM('active', 'suspended', 'expired') DEFAULT 'active',
    installation_type ENUM('trial', 'paid') DEFAULT 'trial',
    activated_by_admin BOOLEAN DEFAULT FALSE,
    trial_ends_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_online DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_license_key (license_key),
    INDEX idx_domain (domain),
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_status (status),
    INDEX idx_installation_type (installation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT '',
    last_login DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default admin user (username: admin, password: admin123)
-- Change this password after installation!
INSERT INTO admin_users (username, password_hash, email) VALUES
('admin', '$2y$10$gW9mSRYAegaC/onZnZe60umhE.wrUBYvJEeRWR/TGBSAxjJ/1N/RG', 'admin@localhost')
ON DUPLICATE KEY UPDATE username=username;

-- License Activity Log (optional - for tracking changes)
CREATE TABLE IF NOT EXISTS license_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_license_id (license_id),
    INDEX idx_action (action),
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
