-- Release 1.6: internal capital adequacy assessment process (ICAAP).
-- Annual cycle with nine phases and checklists, capital inputs and reconciliation, materiality of 16 risks,
-- Pillar 2 capital, stress tests (3 scenarios x 3 years), assumptions log, data requests, report and approvals.
-- Amounts in the reporting currency; ratios stored as fractions (0.125 = 12.5%).

CREATE TABLE IF NOT EXISTS icaap_cycles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  year SMALLINT UNSIGNED NOT NULL,
  data_date DATE NOT NULL,
  regulatory_deadline DATE NULL,
  horizon TINYINT UNSIGNED NOT NULL DEFAULT 3,
  status ENUM('preparation','in_progress','committee','board','submitted','closed') NOT NULL DEFAULT 'preparation',
  tier1 DECIMAL(18,3) NULL,
  tier2 DECIMAL(18,3) NULL,
  rwa_credit DECIMAL(18,3) NULL,
  rwa_market DECIMAL(18,3) NULL,
  rwa_operational DECIMAL(18,3) NULL,
  reg_min DECIMAL(9,6) NULL,
  target_ratio DECIMAL(9,6) NULL,
  warning_ratio DECIMAL(9,6) NULL,
  trigger_ratio DECIMAL(9,6) NULL,
  last_reg_ratio DECIMAL(9,6) NULL,
  alpha DECIMAL(9,6) NULL,
  mgmt_buffer DECIMAL(9,6) NULL,
  aggregation VARCHAR(250) NULL,
  narratives MEDIUMTEXT NULL,
  declared_by INT UNSIGNED NULL,
  declared_at DATETIME NULL,
  committee_meeting VARCHAR(40) NULL,
  committee_date DATE NULL,
  board_decision VARCHAR(40) NULL,
  board_date DATE NULL,
  submitted_on DATE NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_icaap_year (year),
  CONSTRAINT fk_icaap_decl FOREIGN KEY (declared_by) REFERENCES users(id),
  CONSTRAINT fk_icaap_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS icaap_phases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  phase_no TINYINT UNSIGNED NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('not_started','in_progress','complete') NOT NULL DEFAULT 'not_started',
  checklist VARCHAR(255) NULL,
  note TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_icp_phase (cycle_id, phase_no),
  CONSTRAINT fk_icp_cycle FOREIGN KEY (cycle_id) REFERENCES icaap_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS icaap_risks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  risk_key VARCHAR(20) NOT NULL,
  section VARCHAR(20) NOT NULL,
  impact TINYINT UNSIGNED NULL,
  likelihood TINYINT UNSIGNED NULL,
  justification TEXT NULL,
  change_note VARCHAR(500) NULL,
  method TEXT NULL,
  capital DECIMAL(18,3) NULL,
  notes TEXT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_icr_risk (cycle_id, risk_key),
  CONSTRAINT fk_icr_cycle FOREIGN KEY (cycle_id) REFERENCES icaap_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS icaap_stress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  scenario ENUM('base','moderate','severe') NOT NULL,
  year_no TINYINT UNSIGNED NOT NULL,
  profit DECIMAL(18,3) NULL,
  distributions DECIMAL(18,3) NULL,
  extra_provisions DECIMAL(18,3) NULL,
  rwa_growth DECIMAL(9,6) NULL,
  UNIQUE KEY uq_ics_cell (cycle_id, scenario, year_no),
  CONSTRAINT fk_ics_cycle FOREIGN KEY (cycle_id) REFERENCES icaap_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS icaap_assumptions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  stage VARCHAR(80) NOT NULL,
  assumption TEXT NOT NULL,
  rationale TEXT NULL,
  impact VARCHAR(250) NULL,
  made_by VARCHAR(150) NULL,
  made_on DATE NULL,
  approved_by VARCHAR(150) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_ica_cycle FOREIGN KEY (cycle_id) REFERENCES icaap_cycles(id) ON DELETE CASCADE,
  CONSTRAINT fk_ica_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS icaap_data_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  provider VARCHAR(150) NOT NULL,
  data_items TEXT NOT NULL,
  data_date DATE NULL,
  format VARCHAR(40) NULL,
  due_on DATE NULL,
  received_on DATE NULL,
  note VARCHAR(500) NULL,
  CONSTRAINT fk_icd_cycle FOREIGN KEY (cycle_id) REFERENCES icaap_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
