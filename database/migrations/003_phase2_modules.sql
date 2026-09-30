-- Phase 2 modules: KRIs, risk appetite + breaches, compliance obligations, committee decisions, policies, RCSA.

CREATE TABLE IF NOT EXISTS kris (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(250) NOT NULL,
  description TEXT NULL,
  category_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  risk_id INT UNSIGNED NULL,
  unit VARCHAR(30) NOT NULL DEFAULT '',
  direction ENUM('higher_worse','lower_worse') NOT NULL DEFAULT 'higher_worse',
  amber_threshold DECIMAL(18,3) NOT NULL,
  red_threshold DECIMAL(18,3) NOT NULL,
  frequency ENUM('monthly','quarterly') NOT NULL DEFAULT 'monthly',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_kri_code (code),
  KEY idx_kri_dept (department_id),
  CONSTRAINT fk_kri_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_kri_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_kri_cat FOREIGN KEY (category_id) REFERENCES risk_categories(id),
  CONSTRAINT fk_kri_risk FOREIGN KEY (risk_id) REFERENCES risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kri_readings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kri_id INT UNSIGNED NOT NULL,
  period_id INT UNSIGNED NULL,
  reading_date DATE NOT NULL,
  value DECIMAL(18,3) NOT NULL,
  status ENUM('green','amber','red') NOT NULL,
  comment TEXT NULL,
  entered_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_kr_kri (kri_id, reading_date),
  CONSTRAINT fk_kr_kri FOREIGN KEY (kri_id) REFERENCES kris(id),
  CONSTRAINT fk_kr_period FOREIGN KEY (period_id) REFERENCES reporting_periods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appetite_statements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  statement TEXT NOT NULL,
  metric ENUM('max_residual_score','max_net_loss') NOT NULL,
  limit_value DECIMAL(18,3) NOT NULL,
  category_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  approved_by VARCHAR(150) NULL,
  approved_on DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_app_code (code),
  CONSTRAINT fk_app_cat FOREIGN KEY (category_id) REFERENCES risk_categories(id),
  CONSTRAINT fk_app_dept FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appetite_breaches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  statement_id INT UNSIGNED NOT NULL,
  detected_on DATE NOT NULL,
  measured_value DECIMAL(18,3) NULL,
  description TEXT NOT NULL,
  source ENUM('auto','manual') NOT NULL DEFAULT 'manual',
  status ENUM('open','escalated','accepted','resolved') NOT NULL DEFAULT 'open',
  owner_user_id INT UNSIGNED NULL,
  resolution_note TEXT NULL,
  resolved_on DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_ab_stmt (statement_id, status),
  CONSTRAINT fk_ab_stmt FOREIGN KEY (statement_id) REFERENCES appetite_statements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compliance_obligations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  source VARCHAR(250) NOT NULL,
  description TEXT NULL,
  department_id INT UNSIGNED NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  frequency ENUM('once','monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'annual',
  due_date DATE NOT NULL,
  status ENUM('not_started','in_progress','compliant','non_compliant','not_applicable') NOT NULL DEFAULT 'not_started',
  status_note TEXT NULL,
  last_reviewed_on DATE NULL,
  archived_at DATETIME NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ob_code (code),
  KEY idx_ob_due (status, due_date),
  KEY idx_ob_dept (department_id),
  CONSTRAINT fk_ob_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_ob_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS committee_decisions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  committee VARCHAR(30) NOT NULL,
  meeting_date DATE NOT NULL,
  title VARCHAR(250) NOT NULL,
  decision_text TEXT NOT NULL,
  status ENUM('open','implemented','superseded') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_cd_code (code),
  KEY idx_cd_date (meeting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decision_actions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  decision_id INT UNSIGNED NOT NULL,
  title VARCHAR(250) NOT NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  deadline DATE NOT NULL,
  status ENUM('not_started','in_progress','completed','cancelled') NOT NULL DEFAULT 'not_started',
  completed_on DATE NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_da_due (status, deadline),
  CONSTRAINT fk_da_dec FOREIGN KEY (decision_id) REFERENCES committee_decisions(id),
  CONSTRAINT fk_da_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'policy',
  department_id INT UNSIGNED NULL,
  owner_user_id INT UNSIGNED NOT NULL,
  status ENUM('draft','approved','retired') NOT NULL DEFAULT 'draft',
  review_months TINYINT UNSIGNED NOT NULL DEFAULT 12,
  next_review_date DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_pol_code (code),
  CONSTRAINT fk_pol_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_pol_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS policy_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  policy_id INT UNSIGNED NOT NULL,
  version_label VARCHAR(30) NOT NULL,
  effective_date DATE NOT NULL,
  approved_by VARCHAR(150) NULL,
  approval_date DATE NULL,
  change_summary TEXT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pv (policy_id, version_label),
  CONSTRAINT fk_pv_pol FOREIGN KEY (policy_id) REFERENCES policies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcsa_templates (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(250) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcsa_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  question_en VARCHAR(500) NOT NULL,
  question_ar VARCHAR(500) NOT NULL,
  category_id INT UNSIGNED NULL,
  KEY idx_rq_t (template_id, sort_order),
  CONSTRAINT fk_rq_t FOREIGN KEY (template_id) REFERENCES rcsa_templates(id),
  CONSTRAINT fk_rq_cat FOREIGN KEY (category_id) REFERENCES risk_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcsa_assessments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  template_id INT UNSIGNED NOT NULL,
  period_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  assessor_user_id INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('draft','submitted','approved','returned') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL,
  reviewer_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_comment TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_rcsa_code (code),
  KEY idx_ra_status (status, due_date),
  CONSTRAINT fk_ra_t FOREIGN KEY (template_id) REFERENCES rcsa_templates(id),
  CONSTRAINT fk_ra_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_ra_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_ra_assessor FOREIGN KEY (assessor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcsa_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  rating ENUM('effective','partially_effective','ineffective','not_applicable') NULL,
  comment TEXT NULL,
  risk_id INT UNSIGNED NULL,
  UNIQUE KEY uq_rans (assessment_id, question_id),
  CONSTRAINT fk_rans_a FOREIGN KEY (assessment_id) REFERENCES rcsa_assessments(id),
  CONSTRAINT fk_rans_q FOREIGN KEY (question_id) REFERENCES rcsa_questions(id),
  CONSTRAINT fk_rans_risk FOREIGN KEY (risk_id) REFERENCES risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
