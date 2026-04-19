-- Captive portal guest leads (used by captive-register.php and /wifi)
CREATE TABLE IF NOT EXISTS wifi_guest_leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(32) NOT NULL,
  name VARCHAR(255) NULL,
  mac VARCHAR(64) NULL,
  client_ip VARCHAR(64) NULL,
  original_url TEXT NULL,
  terms_accepted_at DATETIME NOT NULL,
  verified_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_phone (phone),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
