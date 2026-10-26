-- 015: optional TOTP multi-factor authentication and password reset by e-mail.
ALTER TABLE users
  ADD COLUMN totp_secret_enc TEXT NULL,
  ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN totp_last_step BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN totp_recovery TEXT NULL;

ALTER TABLE email_log MODIFY kind ENUM('digest','escalation','test','reset') NOT NULL;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pr_token (token_hash),
  KEY idx_pr_user (user_id, created_at),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
