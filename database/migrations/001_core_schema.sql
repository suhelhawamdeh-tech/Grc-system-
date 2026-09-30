-- GRC platform: core schema (Phase 1). MySQL 5.7+/MariaDB 10.3+, utf8mb4.
-- Statements end with ';' at the end of a line (required by the migration runner).

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(64) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_dept_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  city VARCHAR(100) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_branch_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  kind ENUM('conventional','islamic') NOT NULL DEFAULT 'conventional',
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reporting_periods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_en VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_period_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  email VARCHAR(190) NULL,
  full_name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL,
  department_id INT UNSIGNED NULL,
  branch_id INT UNSIGNED NULL,
  lang CHAR(2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_user_username (username),
  UNIQUE KEY uq_user_email (email),
  KEY idx_user_role (role),
  CONSTRAINT fk_user_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_user_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_la_user (username, attempted_at),
  KEY idx_la_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rating_levels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  scale ENUM('likelihood','impact') NOT NULL,
  level TINYINT UNSIGNED NOT NULL,
  label_en VARCHAR(80) NOT NULL,
  label_ar VARCHAR(80) NOT NULL,
  description_en TEXT NULL,
  description_ar TEXT NULL,
  financial_min DECIMAL(18,3) NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_scale_level (scale, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS score_bands (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  min_score TINYINT UNSIGNED NOT NULL,
  max_score TINYINT UNSIGNED NOT NULL,
  label_en VARCHAR(60) NOT NULL,
  label_ar VARCHAR(60) NOT NULL,
  color CHAR(7) NOT NULL,
  description_en TEXT NULL,
  description_ar TEXT NULL,
  is_high TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  risk_code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  description TEXT NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  causes TEXT NULL,
  consequences TEXT NULL,
  department_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  identified_date DATE NOT NULL,
  review_date DATE NULL,
  inh_likelihood TINYINT UNSIGNED NOT NULL,
  inh_impact TINYINT UNSIGNED NOT NULL,
  inh_score TINYINT UNSIGNED NOT NULL,
  est_financial_impact DECIMAL(18,3) NULL,
  controls_description TEXT NULL,
  control_owner_id INT UNSIGNED NULL,
  control_assessment ENUM('not_assessed','effective','partially_effective','ineffective') NOT NULL DEFAULT 'not_assessed',
  res_likelihood TINYINT UNSIGNED NOT NULL,
  res_impact TINYINT UNSIGNED NOT NULL,
  res_score TINYINT UNSIGNED NOT NULL,
  res_rationale TEXT NULL,
  treatment_decision ENUM('mitigate','accept','transfer','avoid') NOT NULL DEFAULT 'mitigate',
  treatment_notes TEXT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  archived_at DATETIME NULL,
  archived_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_risk_code (risk_code),
  KEY idx_risk_cat (category_id),
  KEY idx_risk_dept (department_id),
  KEY idx_risk_branch (branch_id),
  KEY idx_risk_owner (owner_user_id),
  KEY idx_risk_res (res_score),
  KEY idx_risk_state (status, archived_at),
  CONSTRAINT fk_risk_cat FOREIGN KEY (category_id) REFERENCES risk_categories(id),
  CONSTRAINT fk_risk_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_risk_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_risk_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_risk_ctrl_owner FOREIGN KEY (control_owner_id) REFERENCES users(id),
  CONSTRAINT fk_risk_created FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_actions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  risk_id INT UNSIGNED NOT NULL,
  title VARCHAR(250) NOT NULL,
  description TEXT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  deadline DATE NOT NULL,
  status ENUM('not_started','in_progress','completed','cancelled') NOT NULL DEFAULT 'not_started',
  completed_on DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_act_risk (risk_id),
  KEY idx_act_due (status, deadline),
  KEY idx_act_owner (owner_user_id),
  CONSTRAINT fk_act_risk FOREIGN KEY (risk_id) REFERENCES risks(id),
  CONSTRAINT fk_act_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS risk_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  risk_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  event VARCHAR(30) NOT NULL,
  changes MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_rh_risk (risk_id, created_at),
  CONSTRAINT fk_rh_risk FOREIGN KEY (risk_id) REFERENCES risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  incident_code VARCHAR(20) NULL,
  event_type ENUM('incident','near_miss') NOT NULL DEFAULT 'incident',
  title VARCHAR(250) NOT NULL,
  description TEXT NOT NULL,
  occurrence_date DATE NOT NULL,
  discovery_date DATE NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NOT NULL,
  gross_loss DECIMAL(18,3) NOT NULL DEFAULT 0,
  recoveries DECIMAL(18,3) NOT NULL DEFAULT 0,
  net_loss DECIMAL(18,3) NOT NULL DEFAULT 0,
  root_cause TEXT NULL,
  corrective_actions TEXT NULL,
  status ENUM('open','investigating','closed') NOT NULL DEFAULT 'open',
  reported_by INT UNSIGNED NOT NULL,
  archived_at DATETIME NULL,
  archived_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_inc_code (incident_code),
  KEY idx_inc_date (occurrence_date),
  KEY idx_inc_dept (department_id),
  KEY idx_inc_branch (branch_id),
  KEY idx_inc_cat (category_id),
  CONSTRAINT fk_inc_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_inc_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_inc_cat FOREIGN KEY (category_id) REFERENCES risk_categories(id),
  CONSTRAINT fk_inc_reporter FOREIGN KEY (reported_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incident_risks (
  incident_id INT UNSIGNED NOT NULL,
  risk_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (incident_id, risk_id),
  CONSTRAINT fk_ir_inc FOREIGN KEY (incident_id) REFERENCES incidents(id),
  CONSTRAINT fk_ir_risk FOREIGN KEY (risk_id) REFERENCES risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(30) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  KEY idx_att_entity (entity_type, entity_id),
  UNIQUE KEY uq_att_stored (stored_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username VARCHAR(64) NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  detail TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_audit_time (created_at),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
