-- Release 1.6: supporting registers of the risk function (models, data deliveries, maturity, temporary security
-- acceptances, emerging risks, regulatory requirements matrix, committee annual plan). Common columns are indexed;
-- register-specific fields are kept as JSON in `data` (see app/framework/registers.php).

CREATE TABLE IF NOT EXISTS rm_register_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  register VARCHAR(20) NOT NULL,
  code VARCHAR(20) NULL,
  title VARCHAR(250) NOT NULL,
  status VARCHAR(20) NOT NULL,
  department_id INT UNSIGNED NULL,
  owner_user_id INT UNSIGNED NULL,
  due_date DATE NULL,
  ref_id INT UNSIGNED NULL,
  data TEXT NULL,
  escalation_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_reg_code (code),
  KEY idx_reg_list (register, status, due_date),
  KEY idx_reg_owner (owner_user_id, status),
  KEY idx_reg_ref (register, ref_id),
  CONSTRAINT fk_reg_dept FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_reg_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
  CONSTRAINT fk_reg_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An expired temporary security acceptance is escalated like the other risk-function escalations.
ALTER TABLE rm_escalations MODIFY source_type ENUM('appetite_red','regulatory_breach','material_breach','contrary_decision','critical_event','access_restriction','data_integrity','icaap','security_exception','other') NOT NULL;
