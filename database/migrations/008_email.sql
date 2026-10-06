-- 008: e-mail notification log (digests, escalations, test messages). No message bodies are stored.
CREATE TABLE IF NOT EXISTS email_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  kind ENUM('digest','escalation','test') NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  status ENUM('sent','failed') NOT NULL,
  error VARCHAR(250) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_mail_user_day (user_id, kind, created_at),
  KEY idx_mail_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
