-- 022: core-banking integration (Temenos T24): log of every data load, from a file or the API.
CREATE TABLE IF NOT EXISTS integration_runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  system_code VARCHAR(20) NOT NULL,
  dataset VARCHAR(30) NOT NULL,
  mode ENUM('file','api','cli') NOT NULL,
  file_name VARCHAR(200) NULL,
  status ENUM('success','failed') NOT NULL,
  rows_read INT UNSIGNED NOT NULL DEFAULT 0,
  rows_created INT UNSIGNED NOT NULL DEFAULT 0,
  rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
  error_text TEXT NULL,
  run_by INT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NOT NULL,
  KEY idx_ir_sys (system_code, dataset, started_at),
  CONSTRAINT fk_intr_user FOREIGN KEY (run_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
