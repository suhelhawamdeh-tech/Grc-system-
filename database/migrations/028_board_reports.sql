-- Release 1.6: quarterly board risk report built in the platform, following the bank's model report.
-- The report holds its letter, narratives and structured rows (KPI tiles, risk profile, key messages, data sources,
-- references, data-quality observations, proposed decisions, recommendations by department); on board approval the
-- decisions and recommendations become tracked decisions and actions in the follow-up register.

CREATE TABLE IF NOT EXISTS board_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  year SMALLINT UNSIGNED NOT NULL,
  quarter TINYINT UNSIGNED NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  ref_no VARCHAR(60) NULL,
  report_date DATE NOT NULL,
  status ENUM('draft','committee','board','approved') NOT NULL DEFAULT 'draft',
  classification ENUM('internal','confidential','secret') NOT NULL DEFAULT 'confidential',
  narratives MEDIUMTEXT NULL,
  signatory_name VARCHAR(150) NULL,
  signatory_title VARCHAR(150) NULL,
  committee_meeting VARCHAR(40) NULL,
  committee_date DATE NULL,
  board_decision VARCHAR(40) NULL,
  board_date DATE NULL,
  actions_created TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_br_period (year, quarter),
  CONSTRAINT fk_brp_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_report_rows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_id INT UNSIGNED NOT NULL,
  kind ENUM('tile','profile','message','source','reference','observation','dq','decision','recommendation') NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  code VARCHAR(20) NULL,
  title TEXT NOT NULL,
  detail TEXT NULL,
  extra TEXT NULL,
  note TEXT NULL,
  level VARCHAR(20) NULL,
  trend VARCHAR(20) NULL,
  department_id INT UNSIGNED NULL,
  days SMALLINT UNSIGNED NULL,
  priority VARCHAR(10) NULL,
  refs VARCHAR(120) NULL,
  decision_id INT UNSIGNED NULL,
  action_id INT UNSIGNED NULL,
  KEY idx_brr_kind (report_id, kind, sort_order),
  CONSTRAINT fk_brr_report FOREIGN KEY (report_id) REFERENCES board_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_brr_dept FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents registered before release 1.6 take the default level of their type (Framework::DOC_TYPES).
UPDATE policies SET doc_level = CASE category WHEN 'charter' THEN 1 WHEN 'framework' THEN 3 WHEN 'policy' THEN 4 WHEN 'sharia_resolution' THEN 4 WHEN 'manual' THEN 5 WHEN 'procedure' THEN 5
  WHEN 'standard' THEN 6 WHEN 'methodology' THEN 6 WHEN 'tool' THEN 6 ELSE 7 END WHERE doc_level IS NULL;
