-- 013: committee and board governance: bodies, members, meetings, attendance and minutes.
CREATE TABLE IF NOT EXISTS governance_bodies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  name VARCHAR(250) NOT NULL,
  body_type VARCHAR(30) NOT NULL DEFAULT 'other',
  purpose TEXT NULL,
  formed_on DATE NOT NULL,
  min_meetings_per_year TINYINT UNSIGNED NOT NULL DEFAULT 4,
  quorum_percent TINYINT UNSIGNED NOT NULL DEFAULT 50,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_gb_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS governance_members (
  body_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  member_role ENUM('chair','member','secretary') NOT NULL DEFAULT 'member',
  joined_on DATE NOT NULL,
  PRIMARY KEY (body_id, user_id),
  CONSTRAINT fk_gm_body FOREIGN KEY (body_id) REFERENCES governance_bodies(id),
  CONSTRAINT fk_gm_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS governance_meetings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  body_id INT UNSIGNED NOT NULL,
  meeting_date DATE NOT NULL,
  status ENUM('planned','held','cancelled') NOT NULL DEFAULT 'planned',
  agenda TEXT NULL,
  minutes TEXT NULL,
  members_count TINYINT UNSIGNED NULL,
  present_count TINYINT UNSIGNED NULL,
  quorum_met TINYINT(1) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_gmt_body (body_id, meeting_date),
  CONSTRAINT fk_gmt_body FOREIGN KEY (body_id) REFERENCES governance_bodies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS governance_attendance (
  meeting_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (meeting_id, user_id),
  CONSTRAINT fk_ga_meeting FOREIGN KEY (meeting_id) REFERENCES governance_meetings(id),
  CONSTRAINT fk_ga_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
