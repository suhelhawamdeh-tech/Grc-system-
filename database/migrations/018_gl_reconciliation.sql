-- 018: general-ledger loss entries for reconciliation with the incident register.
CREATE TABLE IF NOT EXISTS gl_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  period CHAR(7) NOT NULL,
  account VARCHAR(40) NOT NULL,
  description VARCHAR(250) NULL,
  amount DECIMAL(18,3) NOT NULL,
  imported_by INT UNSIGNED NOT NULL,
  imported_at DATETIME NOT NULL,
  KEY idx_gl_period (period),
  CONSTRAINT fk_gl_user FOREIGN KEY (imported_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
