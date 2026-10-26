-- 014: risk acceptance authority (approval status on risks) and approval-gated closure of large incidents.
ALTER TABLE risks
  ADD COLUMN acceptance_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  ADD COLUMN acceptance_decided_at DATETIME NULL;
ALTER TABLE incidents MODIFY status ENUM('open','investigating','pending_closure','closed') NOT NULL DEFAULT 'open';
