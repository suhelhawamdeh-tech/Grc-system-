-- 005: central control library, risk-control mapping and independent control testing.
CREATE TABLE IF NOT EXISTS controls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(250) NOT NULL,
  description TEXT NULL,
  control_type ENUM('preventive','detective','corrective') NOT NULL DEFAULT 'preventive',
  nature ENUM('manual','automated','semi_automated') NOT NULL DEFAULT 'manual',
  frequency ENUM('continuous','daily','weekly','monthly','quarterly','semiannual','annual','event') NOT NULL DEFAULT 'monthly',
  is_key TINYINT(1) NOT NULL DEFAULT 0,
  department_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  test_frequency ENUM('quarterly','semiannual','annual') NOT NULL DEFAULT 'annual',
  next_test_due DATE NULL,
  last_tested_on DATE NULL,
  last_result ENUM('effective','partially_effective','ineffective') NULL,
  archived_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ctl_code (code),
  KEY idx_ctl_dept (department_id),
  KEY idx_ctl_due (archived_at, next_test_due),
  CONSTRAINT fk_ctl_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_ctl_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_controls (
  risk_id INT UNSIGNED NOT NULL,
  control_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (risk_id, control_id),
  KEY idx_rc_control (control_id),
  CONSTRAINT fk_rc_risk FOREIGN KEY (risk_id) REFERENCES risks(id),
  CONSTRAINT fk_rc_control FOREIGN KEY (control_id) REFERENCES controls(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS control_tests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  control_id INT UNSIGNED NOT NULL,
  test_type ENUM('design','operating') NOT NULL,
  test_date DATE NOT NULL,
  tester_user_id INT UNSIGNED NOT NULL,
  sample_size INT UNSIGNED NULL,
  exceptions INT UNSIGNED NULL,
  result ENUM('effective','partially_effective','ineffective') NOT NULL,
  conclusion TEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ct_control (control_id, test_date),
  CONSTRAINT fk_ct_control FOREIGN KEY (control_id) REFERENCES controls(id),
  CONSTRAINT fk_ct_tester FOREIGN KEY (tester_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
