-- 012: regulatory circulars and instructions: register, distribution to departments, acknowledgement and action taken.
CREATE TABLE IF NOT EXISTS regulatory_circulars (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  issuer VARCHAR(150) NOT NULL,
  reference_no VARCHAR(80) NULL,
  issued_on DATE NOT NULL,
  received_on DATE NOT NULL,
  summary TEXT NOT NULL,
  required_action TEXT NULL,
  due_date DATE NULL,
  obligation_id INT UNSIGNED NULL,
  status ENUM('draft','distributed','closed') NOT NULL DEFAULT 'draft',
  closed_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_cir_code (code),
  KEY idx_cir_status (status, due_date),
  CONSTRAINT fk_cir_obl FOREIGN KEY (obligation_id) REFERENCES compliance_obligations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS circular_distribution (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  circular_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  status ENUM('pending','acknowledged') NOT NULL DEFAULT 'pending',
  acknowledged_by INT UNSIGNED NULL,
  acknowledged_at DATETIME NULL,
  action_taken TEXT NULL,
  UNIQUE KEY uq_cd (circular_id, department_id),
  KEY idx_cd_dept (department_id, status),
  CONSTRAINT fk_cd_cir FOREIGN KEY (circular_id) REFERENCES regulatory_circulars(id),
  CONSTRAINT fk_cd_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_cd_user FOREIGN KEY (acknowledged_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
