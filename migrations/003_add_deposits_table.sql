-- Migration: Add deposits table for tracking bank deposits after payment collection
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
