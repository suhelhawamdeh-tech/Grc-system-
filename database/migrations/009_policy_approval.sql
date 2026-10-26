-- 009: optional maker-checker approval of policy versions.
ALTER TABLE policy_versions ADD COLUMN status ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved' AFTER is_current;
