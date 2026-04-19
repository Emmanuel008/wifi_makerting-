-- WiFi client sessions: phone, device, SSID, and online time (connected_at → disconnected_at or now)
CREATE TABLE IF NOT EXISTS wifi_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(32) NULL,
  mac VARCHAR(64) NULL,
  device VARCHAR(255) NULL,
  user_agent TEXT NULL,
  ssid VARCHAR(128) NULL,
  client_ip VARCHAR(64) NULL,
  connected_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  disconnected_at DATETIME NULL DEFAULT NULL,
  INDEX idx_mac_open (mac, disconnected_at),
  INDEX idx_phone_open (phone, disconnected_at),
  INDEX idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
