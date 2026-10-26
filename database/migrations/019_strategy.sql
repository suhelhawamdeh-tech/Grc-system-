-- 019: strategic objectives and balanced scorecard with linked risks.
CREATE TABLE IF NOT EXISTS strategic_objectives (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  description TEXT NULL,
  perspective ENUM('financial','customer','internal','learning') NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT '',
  direction ENUM('higher_better','lower_better') NOT NULL DEFAULT 'higher_better',
  baseline DECIMAL(18,3) NOT NULL,
  target DECIMAL(18,3) NOT NULL,
  current_value DECIMAL(18,3) NOT NULL,
  due_date DATE NOT NULL,
  archived_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_obj_code (code),
  KEY idx_obj_persp (perspective),
  CONSTRAINT fk_obj_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS objective_updates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objective_id INT UNSIGNED NOT NULL,
  value DECIMAL(18,3) NOT NULL,
  note VARCHAR(500) NULL,
  updated_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_ou_obj (objective_id, created_at),
  CONSTRAINT fk_ou_obj FOREIGN KEY (objective_id) REFERENCES strategic_objectives(id),
  CONSTRAINT fk_ou_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS objective_risks (
  objective_id INT UNSIGNED NOT NULL,
  risk_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (objective_id, risk_id),
  CONSTRAINT fk_or_obj FOREIGN KEY (objective_id) REFERENCES strategic_objectives(id),
  CONSTRAINT fk_or_risk FOREIGN KEY (risk_id) REFERENCES risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
