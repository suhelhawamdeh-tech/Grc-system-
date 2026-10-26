-- 016: recurring RCSA cycles and event-triggered reassessments.
CREATE TABLE IF NOT EXISTS rcsa_schedules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  assessor_user_id INT UNSIGNED NOT NULL,
  frequency ENUM('quarterly','semiannual','annual') NOT NULL DEFAULT 'annual',
  lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  next_run DATE NOT NULL,
  last_run_on DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_rs_run (is_active, next_run),
  CONSTRAINT fk_rs_t FOREIGN KEY (template_id) REFERENCES rcsa_templates(id),
  CONSTRAINT fk_rs_d FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_rs_u FOREIGN KEY (assessor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rcsa_assessments
  ADD COLUMN origin ENUM('manual','scheduled','triggered') NOT NULL DEFAULT 'manual' AFTER status,
  ADD COLUMN trigger_reason VARCHAR(60) NULL AFTER origin;
