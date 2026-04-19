-- Users: User Management + dashboard login (login.php reads this table)
CREATE TABLE IF NOT EXISTS managed_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  role ENUM('admin', 'business') NOT NULL DEFAULT 'business',
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_managed_users_email (email),
  UNIQUE KEY uq_managed_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default password for all rows below: admin  (bcrypt)
INSERT INTO managed_users (name, email, phone, role, password_hash) VALUES (
  'Administrator',
  'admin@admin.com',
  '+00000000001',
  'admin',
  '$2y$12$ms7hV3ERCWRWgJBnycinIeeRJ9m5b6AfuB9XvW1cysxuTesCgPXL2'
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  phone = VALUES(phone),
  role = VALUES(role);
