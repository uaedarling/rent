-- Minimal schema
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(120) UNIQUE, password VARCHAR(255), full_name VARCHAR(120), role VARCHAR(40), created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE units (id INT AUTO_INCREMENT PRIMARY KEY, unit_no VARCHAR(50), monthly_rent_aed DECIMAL(10,2), status ENUM('active','inactive') DEFAULT 'active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE tenants (id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(200), email VARCHAR(120), phone VARCHAR(32), unit_id INT, due_day TINYINT DEFAULT 1, grace_days TINYINT DEFAULT 3, ledger_token CHAR(64), created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenant_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  INDEX idx_tenants_ledger_token (ledger_token));
CREATE TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT, period_ym CHAR(7), amount_aed DECIMAL(10,2), paid_at DATE, method VARCHAR(80), created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_payments_tenant_period (tenant_id, period_ym));
-- settings is a singleton row; always use id=1 (INSERT ... ON DUPLICATE KEY UPDATE)
CREATE TABLE settings (id INT PRIMARY KEY DEFAULT 1, company_name VARCHAR(120), manager_name VARCHAR(120), manager_email VARCHAR(120), manager_whatsapp VARCHAR(32), whatsapp_phone_id VARCHAR(64), whatsapp_token TEXT, smtp_host VARCHAR(120), smtp_port INT, smtp_user VARCHAR(120), smtp_pass TEXT, from_email VARCHAR(120), from_name VARCHAR(120), timezone VARCHAR(64) DEFAULT 'Asia/Dubai', created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE message_logs (id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, channel ENUM('whatsapp','email') NOT NULL, recipient VARCHAR(120) NOT NULL, subject VARCHAR(200), body TEXT, status VARCHAR(40) DEFAULT 'sent', error TEXT);
