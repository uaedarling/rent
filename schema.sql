-- Rent Manager AED — Full Schema
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) UNIQUE,
  password VARCHAR(255),
  full_name VARCHAR(120),
  role VARCHAR(40),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_no VARCHAR(50),
  monthly_rent_aed DECIMAL(10,2),
  status ENUM('active','inactive') DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(200),
  email VARCHAR(120),
  phone VARCHAR(32),
  unit_id INT,
  due_day TINYINT DEFAULT 1,
  grace_days TINYINT DEFAULT 3,
  ledger_token CHAR(64),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenant_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  INDEX idx_tenants_ledger_token (ledger_token),
  INDEX idx_tenants_unit_id (unit_id)
);
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT,
  period_ym CHAR(7),
  amount_aed DECIMAL(10,2),
  paid_at DATE,
  method VARCHAR(80),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_payments_tenant_period (tenant_id, period_ym),
  INDEX idx_payments_period_ym (period_ym)
);
-- settings is a singleton row; always use id=1 (INSERT ... ON DUPLICATE KEY UPDATE)
CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY DEFAULT 1,
  company_name VARCHAR(120),
  manager_name VARCHAR(120),
  manager_email VARCHAR(120),
  manager_whatsapp VARCHAR(32),
  whatsapp_phone_id VARCHAR(64),
  whatsapp_token TEXT,
  whatsapp_template VARCHAR(120) DEFAULT 'payment_receipt',
  smtp_host VARCHAR(120),
  smtp_port INT,
  smtp_user VARCHAR(120),
  smtp_pass TEXT,
  from_email VARCHAR(120),
  from_name VARCHAR(120),
  timezone VARCHAR(64) DEFAULT 'Asia/Dubai',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS message_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  channel ENUM('whatsapp','email') NOT NULL,
  recipient VARCHAR(120) NOT NULL,
  subject VARCHAR(200),
  body TEXT,
  status VARCHAR(40) DEFAULT 'sent',
  error TEXT
);
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token (token)
);
CREATE TABLE IF NOT EXISTS deposits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT NOT NULL,
  deposited_by INT NOT NULL COMMENT 'User ID of employee who recorded the deposit',
  deposited_at DATE NOT NULL,
  bank_name VARCHAR(120),
  deposit_ref VARCHAR(120),
  notes TEXT,
  slip_filename VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
  FOREIGN KEY (deposited_by) REFERENCES users(id),
  INDEX idx_deposits_payment_id (payment_id)
);
