-- 021: anti-money laundering: risk factors in four dimensions, customer risk rating, links to the control library, regulatory reports.
CREATE TABLE IF NOT EXISTS aml_factors (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dimension ENUM('customer','product','channel','geography') NOT NULL,
  code VARCHAR(30) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150) NOT NULL,
  risk_level TINYINT UNSIGNED NOT NULL,
  t24_code VARCHAR(60) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_af_code (code),
  KEY idx_af_dim (dimension, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aml_customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_ref VARCHAR(40) NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  customer_type_id INT UNSIGNED NOT NULL,
  geography_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  is_pep TINYINT(1) NOT NULL DEFAULT 0,
  sanctions_match TINYINT(1) NOT NULL DEFAULT 0,
  source ENUM('manual','t24') NOT NULL DEFAULT 'manual',
  onboarded_on DATE NULL,
  score DECIMAL(4,2) NOT NULL,
  calc_rating ENUM('low','medium','high') NOT NULL,
  override_rating ENUM('low','medium','high') NULL,
  override_reason TEXT NULL,
  override_by INT UNSIGNED NULL,
  rating ENUM('low','medium','high') NOT NULL,
  cdd_level ENUM('simplified','standard','enhanced') NOT NULL,
  last_review_on DATE NULL,
  next_review_due DATE NOT NULL,
  status ENUM('active','exited') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ac_ref (customer_ref),
  KEY idx_ac_rating (status, rating),
  KEY idx_ac_review (status, next_review_due),
  CONSTRAINT fk_ac_type FOREIGN KEY (customer_type_id) REFERENCES aml_factors(id),
  CONSTRAINT fk_ac_geo FOREIGN KEY (geography_id) REFERENCES aml_factors(id),
  CONSTRAINT fk_ac_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
  CONSTRAINT fk_ac_ovr FOREIGN KEY (override_by) REFERENCES users(id),
  CONSTRAINT fk_ac_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products and delivery channels used by each customer (many per customer).
CREATE TABLE IF NOT EXISTS aml_customer_factors (
  customer_id INT UNSIGNED NOT NULL,
  factor_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (customer_id, factor_id),
  CONSTRAINT fk_acf_cust FOREIGN KEY (customer_id) REFERENCES aml_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_acf_fac FOREIGN KEY (factor_id) REFERENCES aml_factors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aml_customer_reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  reviewed_on DATE NOT NULL,
  rating ENUM('low','medium','high') NOT NULL,
  note TEXT NOT NULL,
  reviewed_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_acr_cust (customer_id),
  CONSTRAINT fk_acr_cust FOREIGN KEY (customer_id) REFERENCES aml_customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_acr_user FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controls from the control library that mitigate each AML risk dimension.
CREATE TABLE IF NOT EXISTS aml_dimension_controls (
  dimension ENUM('customer','product','channel','geography') NOT NULL,
  control_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (dimension, control_id),
  CONSTRAINT fk_adc_ctl FOREIGN KEY (control_id) REFERENCES controls(id),
  CONSTRAINT fk_adc_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aml_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NULL,
  report_type ENUM('str','ctr','periodic','ewra','other') NOT NULL,
  title VARCHAR(250) NOT NULL,
  customer_id INT UNSIGNED NULL,
  recipient VARCHAR(150) NOT NULL,
  reference_no VARCHAR(80) NULL,
  due_date DATE NOT NULL,
  summary TEXT NOT NULL,
  confidential TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  submitted_on DATE NULL,
  submitted_by INT UNSIGNED NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ar_code (code),
  KEY idx_ar_status (status, due_date),
  CONSTRAINT fk_ar_cust FOREIGN KEY (customer_id) REFERENCES aml_customers(id),
  CONSTRAINT fk_ar_sub FOREIGN KEY (submitted_by) REFERENCES users(id),
  CONSTRAINT fk_ar_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Illustrative reference factors (risk levels 1 = low, 2 = medium, 3 = high). The bank must review and approve them.
INSERT IGNORE INTO aml_factors (dimension, code, name_en, name_ar, risk_level, t24_code, is_default, is_active, created_at, updated_at) VALUES
('customer', 'CUS-IND-RES', 'Individual, resident', 'فرد مقيم', 1, '1001', 1, 1, NOW(), NOW()),
('customer', 'CUS-IND-NRS', 'Individual, non-resident', 'فرد غير مقيم', 2, '1002', 0, 1, NOW(), NOW()),
('customer', 'CUS-CORP', 'Company', 'شركة', 2, '2001', 0, 1, NOW(), NOW()),
('customer', 'CUS-CASH', 'Cash-intensive business', 'نشاط كثيف التعامل النقدي', 3, '2002', 0, 1, NOW(), NOW()),
('customer', 'CUS-NPO', 'Charity / non-profit organisation', 'جمعية خيرية / منظمة غير ربحية', 3, '3001', 0, 1, NOW(), NOW()),
('customer', 'CUS-GOV', 'Government entity', 'جهة حكومية', 1, '4001', 0, 1, NOW(), NOW()),
('product', 'PRD-CUR', 'Current account', 'حساب جارٍ', 1, '1001', 1, 1, NOW(), NOW()),
('product', 'PRD-SAV', 'Investment savings account (mudaraba)', 'حساب ادخار استثماري (مضاربة)', 1, '6001', 0, 1, NOW(), NOW()),
('product', 'PRD-MUR', 'Murabaha financing', 'تمويل مرابحة', 2, '21051', 0, 1, NOW(), NOW()),
('product', 'PRD-TRADE', 'Trade finance and letters of credit', 'التمويل التجاري والاعتمادات المستندية', 3, '21070', 0, 1, NOW(), NOW()),
('product', 'PRD-WIRE', 'International transfers', 'الحوالات الدولية', 3, 'FT', 0, 1, NOW(), NOW()),
('product', 'PRD-FX', 'Foreign currency cash exchange', 'صرف العملات الأجنبية نقداً', 3, 'TT', 0, 1, NOW(), NOW()),
('channel', 'CHN-BRANCH', 'Branch, face to face', 'الفرع، حضورياً', 1, 'BRANCH', 1, 1, NOW(), NOW()),
('channel', 'CHN-ONLINE', 'Internet banking', 'الخدمات المصرفية عبر الإنترنت', 2, 'INTERNET', 0, 1, NOW(), NOW()),
('channel', 'CHN-MOBILE', 'Mobile banking', 'تطبيق الهاتف', 2, 'MOBILE', 0, 1, NOW(), NOW()),
('channel', 'CHN-AGENT', 'Third party / introducer', 'طرف ثالث / وسيط', 3, 'AGENT', 0, 1, NOW(), NOW()),
('geography', 'GEO-LY', 'Libya', 'ليبيا', 1, 'LY', 1, 1, NOW(), NOW()),
('geography', 'GEO-NEIGH', 'Neighbouring country', 'دولة مجاورة', 2, 'TN|EG|DZ|NE|TD|SD', 0, 1, NOW(), NOW()),
('geography', 'GEO-OTHER', 'Other country', 'دولة أخرى', 2, NULL, 0, 1, NOW(), NOW()),
('geography', 'GEO-HIGH', 'High-risk jurisdiction (per current FATF lists)', 'دولة عالية المخاطر (حسب قوائم مجموعة العمل المالي السارية)', 3, NULL, 0, 1, NOW(), NOW());
