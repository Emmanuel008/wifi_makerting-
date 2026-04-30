-- Add company_name to managed_users for User Management module.
ALTER TABLE managed_users
  ADD COLUMN company_name VARCHAR(255) NOT NULL DEFAULT '' AFTER name;

-- Backfill existing rows.
UPDATE managed_users
SET company_name = 'Unknown Company'
WHERE company_name = '';
