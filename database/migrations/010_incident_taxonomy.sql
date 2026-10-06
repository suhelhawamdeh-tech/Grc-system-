-- 010: Basel event-type and business-line taxonomy, recovery source, non-financial impact, escalation flag for incidents.
CREATE TABLE IF NOT EXISTS basel_event_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_bet_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(10) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  UNIQUE KEY uq_bl_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO basel_event_types (code, name_en, name_ar) VALUES
('IF', 'Internal fraud', 'الاحتيال الداخلي'),
('EF', 'External fraud', 'الاحتيال الخارجي'),
('EPWS', 'Employment practices and workplace safety', 'ممارسات التوظيف وسلامة بيئة العمل'),
('CPBP', 'Clients, products and business practices', 'العملاء والمنتجات وممارسات العمل'),
('DPA', 'Damage to physical assets', 'الأضرار بالأصول المادية'),
('BDSF', 'Business disruption and system failures', 'انقطاع الأعمال وفشل الأنظمة'),
('EDPM', 'Execution, delivery and process management', 'التنفيذ والتسليم وإدارة العمليات');

INSERT IGNORE INTO business_lines (code, name_en, name_ar) VALUES
('CF', 'Corporate finance', 'تمويل الشركات'),
('TS', 'Trading and sales', 'التداول والمبيعات'),
('RB', 'Retail banking', 'الخدمات المصرفية للأفراد'),
('CB', 'Commercial banking', 'الخدمات المصرفية التجارية'),
('PS', 'Payment and settlement', 'المدفوعات والتسويات'),
('AS', 'Agency services', 'الخدمات الوكالية'),
('AM', 'Asset management', 'إدارة الأصول'),
('RBR', 'Retail brokerage', 'الوساطة للأفراد');

ALTER TABLE incidents
  ADD COLUMN basel_event_type_id INT UNSIGNED NULL AFTER category_id,
  ADD COLUMN business_line_id INT UNSIGNED NULL AFTER basel_event_type_id,
  ADD COLUMN product VARCHAR(100) NULL AFTER business_line_id,
  ADD COLUMN system_name VARCHAR(100) NULL AFTER product,
  ADD COLUMN recovery_source ENUM('insurance','customer','employee','supplier','other') NULL AFTER recoveries,
  ADD COLUMN non_financial_impact TEXT NULL AFTER recovery_source,
  ADD COLUMN escalated_at DATETIME NULL AFTER status,
  ADD CONSTRAINT fk_inc_basel FOREIGN KEY (basel_event_type_id) REFERENCES basel_event_types(id),
  ADD CONSTRAINT fk_inc_bline FOREIGN KEY (business_line_id) REFERENCES business_lines(id);
