-- Release 1.6: risk management framework foundation.
-- Document register (policies gain document control fields), ERM taxonomy grouping on risk categories, unified 5x5 scale.

ALTER TABLE policies
  MODIFY code VARCHAR(40) NULL,
  ADD COLUMN title_en VARCHAR(250) NULL AFTER title,
  ADD COLUMN doc_level TINYINT UNSIGNED NULL AFTER category,
  ADD COLUMN classification ENUM('public','internal','confidential','secret') NOT NULL DEFAULT 'internal' AFTER doc_level,
  ADD COLUMN approval_body VARCHAR(150) NULL AFTER classification,
  ADD COLUMN parent_id INT UNSIGNED NULL AFTER approval_body,
  ADD COLUMN parent_code VARCHAR(40) NULL AFTER parent_id,
  ADD COLUMN rm_section VARCHAR(20) NULL AFTER parent_id,
  ADD COLUMN refs TEXT NULL,
  ADD COLUMN kb_source_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_pol_parent FOREIGN KEY (parent_id) REFERENCES policies(id);

ALTER TABLE kb_sources ADD COLUMN policy_id INT UNSIGNED NULL AFTER circular_id;

ALTER TABLE risk_categories
  ADD COLUMN erm_group VARCHAR(20) NULL AFTER kind,
  ADD COLUMN monitor_section VARCHAR(20) NULL AFTER erm_group;

INSERT IGNORE INTO risk_categories (code, name_en, name_ar, kind, sort_order, is_active, created_at, updated_at) VALUES
('BCM', 'Business continuity risk', 'مخاطر استمرارية الأعمال', 'conventional', 45, 1, NOW(), NOW()),
('CAP', 'Capital adequacy risk', 'مخاطر كفاية رأس المال', 'conventional', 35, 1, NOW(), NOW());

UPDATE risk_categories SET erm_group = CASE code
    WHEN 'CR' THEN 'credit' WHEN 'ISL-EIR' THEN 'credit'
    WHEN 'MR' THEN 'market' WHEN 'ISL-ACR' THEN 'market'
    WHEN 'ISL-ROR' THEN 'rate_of_return' WHEN 'ISL-DCR' THEN 'rate_of_return' WHEN 'ISL-FID' THEN 'rate_of_return'
    WHEN 'LQ' THEN 'liquidity'
    WHEN 'OP' THEN 'operational' WHEN 'FRD' THEN 'operational' WHEN 'LEG' THEN 'operational' WHEN 'TPR' THEN 'operational'
    WHEN 'BCM' THEN 'continuity' WHEN 'CYB' THEN 'infosec' WHEN 'ISL-SNC' THEN 'sharia' WHEN 'CAP' THEN 'capital'
    WHEN 'STR' THEN 'strategic' WHEN 'REP' THEN 'reputation' WHEN 'CMP' THEN 'compliance' WHEN 'AML' THEN 'compliance'
    ELSE erm_group END
WHERE erm_group IS NULL;

UPDATE risk_categories SET monitor_section = CASE erm_group
    WHEN 'credit' THEN 'credit' WHEN 'market' THEN 'market' WHEN 'rate_of_return' THEN 'market' WHEN 'liquidity' THEN 'market'
    WHEN 'operational' THEN 'operational' WHEN 'continuity' THEN 'operational' WHEN 'sharia' THEN 'operational'
    WHEN 'infosec' THEN 'infosec' WHEN 'capital' THEN 'basel' ELSE 'office' END
WHERE monitor_section IS NULL AND erm_group IS NOT NULL;

-- Unified scale (likelihood x impact): 1-4 low, 5-9 medium, 10-16 high, 20-25 critical (17-19 cannot occur).
-- Applied only while the bands are still the shipped defaults, so a bank that already changed its scale keeps it.
UPDATE score_bands b
JOIN (SELECT COUNT(*) AS n, SUM((min_score = 1 AND max_score = 4) OR (min_score = 5 AND max_score = 9) OR (min_score = 10 AND max_score = 14) OR (min_score = 15 AND max_score = 25)) AS d FROM score_bands) x ON x.n = 4 AND x.d = 4
SET b.max_score = CASE b.min_score WHEN 10 THEN 16 ELSE b.max_score END,
    b.min_score = CASE b.min_score WHEN 15 THEN 17 ELSE b.min_score END,
    b.description_en = CASE b.min_score
        WHEN 1 THEN 'Low: monitor within routine activities.'
        WHEN 5 THEN 'Medium: treat within department plans; quarterly follow-up. Residual risk may be accepted by the owning department head.'
        WHEN 10 THEN 'High: treatment plan with owner and dates, monthly follow-up. Residual risk accepted only by the chief executive.'
        ELSE 'Critical: immediate treatment and escalation to the risk committee and the board. Residual risk accepted only by the risk committee or the board.' END,
    b.description_ar = CASE b.min_score
        WHEN 1 THEN 'منخفض: متابعة ضمن الأنشطة الاعتيادية.'
        WHEN 5 THEN 'متوسط: معالجة ضمن خطط الإدارات ومتابعة ربع سنوية، ويقبل الخطر المتبقي مدير الإدارة المالكة.'
        WHEN 10 THEN 'مرتفع: خطة معالجة بمسؤول ومواعيد ومتابعة شهرية، ولا يقبل الخطر المتبقي إلا الرئيس التنفيذي.'
        ELSE 'حرج: معالجة فورية وتصعيد إلى لجنة المخاطر ومجلس الإدارة، ولا يُقبل الخطر المتبقي إلا بقرار من لجنة المخاطر أو المجلس.' END,
    b.updated_at = NOW();

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES ('scale_basis', 'erm', NOW());
