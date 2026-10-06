-- 011: advanced KRIs: fourth (critical) level, data source, breach register.
ALTER TABLE kris
  ADD COLUMN critical_threshold DECIMAL(18,3) NULL AFTER red_threshold,
  ADD COLUMN data_source ENUM('manual','import','api') NOT NULL DEFAULT 'manual' AFTER frequency;
ALTER TABLE kri_readings MODIFY status ENUM('green','amber','red','critical') NOT NULL;

CREATE TABLE IF NOT EXISTS kri_breaches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kri_id INT UNSIGNED NOT NULL,
  reading_id INT UNSIGNED NOT NULL,
  level ENUM('red','critical') NOT NULL,
  justification TEXT NULL,
  action_plan TEXT NULL,
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  escalated_at DATETIME NULL,
  resolved_on DATE NULL,
  resolution_note TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_kb_status (status, level),
  KEY idx_kb_kri (kri_id),
  CONSTRAINT fk_kb_kri FOREIGN KEY (kri_id) REFERENCES kris(id),
  CONSTRAINT fk_kb_reading FOREIGN KEY (reading_id) REFERENCES kri_readings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
