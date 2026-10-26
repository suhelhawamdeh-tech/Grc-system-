-- 004: usability foundation: personal notes/reminders and per-user preferences (help tour state).
CREATE TABLE IF NOT EXISTS user_notes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(500) NOT NULL,
  page_path VARCHAR(255) NULL,
  remind_on DATE NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  done_at DATETIME NULL,
  KEY idx_note_user (user_id, is_done, remind_on),
  CONSTRAINT fk_note_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_prefs (
  user_id INT UNSIGNED NOT NULL,
  pkey VARCHAR(40) NOT NULL,
  pvalue VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (user_id, pkey),
  CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
