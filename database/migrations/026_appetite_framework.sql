-- Release 1.6: risk appetite framework on top of KRIs.
-- Board-level appetite metrics (tier = board) carry an appetite level, a warning limit (amber) and a maximum (red),
-- with their definition card; template metrics stay uncalibrated until the board sets the values.

ALTER TABLE kris
  ADD COLUMN tier ENUM('kri','board','policy','operational') NOT NULL DEFAULT 'kri' AFTER frequency,
  ADD COLUMN raf_category VARCHAR(20) NULL AFTER tier,
  ADD COLUMN appetite_value DECIMAL(18,3) NULL AFTER raf_category,
  ADD COLUMN formula TEXT NULL,
  ADD COLUMN source_note VARCHAR(250) NULL,
  ADD COLUMN monitor_section VARCHAR(20) NULL,
  ADD COLUMN report_frequency VARCHAR(20) NULL,
  ADD COLUMN is_regulatory TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN calibrated TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN action_amber TEXT NULL,
  ADD COLUMN action_red TEXT NULL,
  ADD COLUMN card_reviewed_on DATE NULL,
  ADD COLUMN template_key VARCHAR(30) NULL,
  ADD UNIQUE KEY uq_kri_template (template_key),
  ADD KEY idx_kri_tier (tier, raf_category);

-- Amber notices for board metrics (warning limit reached: analysis and preventive plan).
ALTER TABLE kri_breaches MODIFY level ENUM('amber','red','critical') NOT NULL;
