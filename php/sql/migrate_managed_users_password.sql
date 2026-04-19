-- One-time migration: add password_hash to existing managed_users (password: admin for any row missing hash)
ALTER TABLE managed_users
  ADD COLUMN password_hash VARCHAR(255) NULL
  AFTER role;

UPDATE managed_users
SET password_hash = '$2y$12$ms7hV3ERCWRWgJBnycinIeeRJ9m5b6AfuB9XvW1cysxuTesCgPXL2'
WHERE password_hash IS NULL OR password_hash = '';

ALTER TABLE managed_users
  MODIFY password_hash VARCHAR(255) NOT NULL;
