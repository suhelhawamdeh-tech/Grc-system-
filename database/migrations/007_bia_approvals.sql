-- 007: business impact analysis (processes, supporting systems, recovery objectives) and a generic approval (maker-checker) log.
CREATE TABLE IF NOT EXISTS bia_systems (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(250) NOT NULL,
  description TEXT NULL,
  department_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  recovery_hours DECIMAL(8,2) NOT NULL,
  data_loss_hours DECIMAL(8,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_bsys_code (code),
  CONSTRAINT fk_bsys_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_bsys_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bia_processes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(250) NOT NULL,
  description TEXT NULL,
  department_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  impact_financial TINYINT UNSIGNED NOT NULL,
  impact_operational TINYINT UNSIGNED NOT NULL,
  impact_legal TINYINT UNSIGNED NOT NULL,
  impact_reputation TINYINT UNSIGNED NOT NULL,
  criticality ENUM('critical','high','medium','low') NOT NULL,
  mtpd_hours DECIMAL(8,2) NOT NULL,
  rto_hours DECIMAL(8,2) NOT NULL,
  rpo_hours DECIMAL(8,2) NOT NULL,
  dependencies TEXT NULL,
  status ENUM('draft','pending_approval','approved') NOT NULL DEFAULT 'draft',
  approved_at DATETIME NULL,
  next_review_due DATE NULL,
  archived_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_bp_code (code),
  KEY idx_bp_dept (department_id),
  KEY idx_bp_status (status, criticality),
  CONSTRAINT fk_bp_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_bp_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bia_process_systems (
  process_id INT UNSIGNED NOT NULL,
  system_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (process_id, system_id),
  CONSTRAINT fk_bps_p FOREIGN KEY (process_id) REFERENCES bia_processes(id),
  CONSTRAINT fk_bps_s FOREIGN KEY (system_id) REFERENCES bia_systems(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approvals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(30) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by INT UNSIGNED NOT NULL,
  requested_at DATETIME NOT NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  comment TEXT NULL,
  KEY idx_appr_entity (entity_type, entity_id, status),
  KEY idx_appr_status (status, requested_at),
  CONSTRAINT fk_appr_req FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_appr_dec FOREIGN KEY (decided_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
