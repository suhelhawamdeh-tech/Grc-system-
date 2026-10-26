-- 023: reference library (trusted sources split into passages) and the AI assistant's question log.
CREATE TABLE IF NOT EXISTS kb_sources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  title_ar VARCHAR(250) NOT NULL,
  title_en VARCHAR(250) NOT NULL,
  issuer VARCHAR(150) NOT NULL,
  category ENUM('aml','compliance','risk','audit','sharia','governance','continuity','security','platform','bank') NOT NULL,
  jurisdiction ENUM('international','regional','libya','bank') NOT NULL,
  authority ENUM('law','regulation','standard','guidance','list','internal') NOT NULL,
  pub_year SMALLINT UNSIGNED NULL,
  url VARCHAR(300) NULL,
  modules VARCHAR(200) NOT NULL DEFAULT '',
  note_ar TEXT NULL,
  note_en TEXT NULL,
  is_builtin TINYINT(1) NOT NULL DEFAULT 0,
  restricted TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','retired') NOT NULL DEFAULT 'active',
  circular_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_kbs_code (code),
  KEY idx_kbs_cat (status, category),
  CONSTRAINT fk_kbs_cir FOREIGN KEY (circular_id) REFERENCES regulatory_circulars(id),
  CONSTRAINT fk_kbs_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_chunks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  source_id INT UNSIGNED NOT NULL,
  ref VARCHAR(60) NULL,
  heading_ar VARCHAR(250) NOT NULL,
  heading_en VARCHAR(250) NOT NULL,
  body_ar TEXT NOT NULL,
  body_en TEXT NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_kbc_src (source_id, sort_order),
  CONSTRAINT fk_kbc_src FOREIGN KEY (source_id) REFERENCES kb_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  question TEXT NOT NULL,
  answer MEDIUMTEXT NULL,
  mode ENUM('library','llm') NOT NULL,
  model VARCHAR(60) NULL,
  chunk_ids VARCHAR(500) NOT NULL DEFAULT '',
  context_type VARCHAR(30) NULL,
  redactions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('ok','no_sources','refused','error') NOT NULL,
  error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_aim_user (user_id, created_at),
  CONSTRAINT fk_aim_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES
('ai_enabled', '1', NOW()), ('ai_mode', 'library', NOW()), ('ai_model', 'claude-opus-5-5', NOW()), ('ai_effort', 'medium', NOW()), ('ai_rate_limit', '20', NOW());
