-- Reference data. Everything here is configurable in the admin UI.
-- IMPORTANT: rating criteria, financial thresholds and score bands are DEMONSTRATION values
-- and must be reviewed and approved by the institution (Risk Committee / Board) before use.

INSERT IGNORE INTO settings (setting_key, setting_value, updated_at) VALUES
('org_name_en', 'Demo Bank (synthetic data)', NOW()),
('org_name_ar', 'مصرف تجريبي (بيانات تجريبية)', NOW()),
('currency_code', 'LYD', NOW()),
('currency_decimals', '3', NOW()),
('currency_symbol_en', 'LYD', NOW()),
('currency_symbol_ar', 'د.ل', NOW()),
('default_lang', 'ar', NOW()),
('thresholds_approved', '0', NOW()),
('review_warning_days', '30', NOW());

INSERT IGNORE INTO risk_categories (code, name_en, name_ar, kind, sort_order, is_active, created_at, updated_at) VALUES
('CR', 'Credit risk', 'مخاطر الائتمان', 'conventional', 10, 1, NOW(), NOW()),
('MR', 'Market risk', 'مخاطر السوق', 'conventional', 20, 1, NOW(), NOW()),
('LQ', 'Liquidity risk', 'مخاطر السيولة', 'conventional', 30, 1, NOW(), NOW()),
('OP', 'Operational risk', 'المخاطر التشغيلية', 'conventional', 40, 1, NOW(), NOW()),
('FRD', 'Fraud (internal / external)', 'الاحتيال (داخلي / خارجي)', 'conventional', 50, 1, NOW(), NOW()),
('CYB', 'Information security and cyber risk', 'مخاطر أمن المعلومات والفضاء السيبراني', 'conventional', 60, 1, NOW(), NOW()),
('CMP', 'Compliance and regulatory risk', 'مخاطر الامتثال والمخاطر الرقابية', 'conventional', 70, 1, NOW(), NOW()),
('AML', 'AML / CFT risk', 'مخاطر مكافحة غسل الأموال وتمويل الإرهاب', 'conventional', 80, 1, NOW(), NOW()),
('LEG', 'Legal risk', 'المخاطر القانونية', 'conventional', 90, 1, NOW(), NOW()),
('REP', 'Reputational risk', 'مخاطر السمعة', 'conventional', 100, 1, NOW(), NOW()),
('STR', 'Strategic risk', 'المخاطر الاستراتيجية', 'conventional', 110, 1, NOW(), NOW()),
('TPR', 'Third-party / outsourcing risk', 'مخاطر الأطراف الثالثة والتعهيد', 'conventional', 120, 1, NOW(), NOW()),
('ISL-SNC', 'Sharia non-compliance risk', 'مخاطر عدم الالتزام بأحكام الشريعة', 'islamic', 200, 1, NOW(), NOW()),
('ISL-DCR', 'Displaced commercial risk', 'مخاطر التجارة المنقولة', 'islamic', 210, 1, NOW(), NOW()),
('ISL-EIR', 'Equity investment risk', 'مخاطر الاستثمار في الأسهم والمشاركات', 'islamic', 220, 1, NOW(), NOW()),
('ISL-ROR', 'Rate of return risk', 'مخاطر معدل العائد', 'islamic', 230, 1, NOW(), NOW()),
('ISL-FID', 'Fiduciary risk (investment account holders)', 'المخاطر الائتمانية تجاه أصحاب حسابات الاستثمار', 'islamic', 240, 1, NOW(), NOW()),
('ISL-ACR', 'Asset / commodity price risk (Ijarah, Salam, Murabaha inventory)', 'مخاطر أسعار الأصول والسلع (الإجارة، السلم، مخزون المرابحة)', 'islamic', 250, 1, NOW(), NOW());

INSERT IGNORE INTO rating_levels (scale, level, label_en, label_ar, description_en, description_ar, financial_min, updated_at) VALUES
('likelihood', 1, 'Rare', 'نادر', 'May occur only in exceptional circumstances (less than once in 10 years).', 'قد يحدث في ظروف استثنائية فقط (أقل من مرة كل 10 سنوات).', NULL, NOW()),
('likelihood', 2, 'Unlikely', 'غير محتمل', 'Could occur at some time (about once in 3 to 10 years).', 'قد يحدث في وقت ما (مرة كل 3 إلى 10 سنوات تقريباً).', NULL, NOW()),
('likelihood', 3, 'Possible', 'ممكن', 'Might occur (about once in 1 to 3 years).', 'من الممكن أن يحدث (مرة كل سنة إلى 3 سنوات تقريباً).', NULL, NOW()),
('likelihood', 4, 'Likely', 'محتمل', 'Will probably occur (several times a year).', 'يرجح حدوثه (عدة مرات في السنة).', NULL, NOW()),
('likelihood', 5, 'Almost certain', 'شبه مؤكد', 'Expected to occur (monthly or more often).', 'متوقع حدوثه (شهرياً أو أكثر).', NULL, NOW()),
('impact', 1, 'Insignificant', 'ضئيل', 'Negligible financial effect; no regulatory, customer or Sharia consequence; absorbed in normal operations.', 'أثر مالي ضئيل؛ بلا تبعات رقابية أو على العملاء أو شرعية؛ يُستوعب ضمن التشغيل المعتاد.', 0.000, NOW()),
('impact', 2, 'Minor', 'بسيط', 'Small financial loss; minor internal breach; limited customer inconvenience.', 'خسارة مالية صغيرة؛ مخالفة داخلية بسيطة؛ إزعاج محدود للعملاء.', 10000.000, NOW()),
('impact', 3, 'Moderate', 'متوسط', 'Noticeable loss; reportable regulatory finding; local reputational effect; service disruption for hours.', 'خسارة ملحوظة؛ ملاحظة رقابية واجبة الإبلاغ؛ أثر محلي على السمعة؛ انقطاع الخدمة لساعات.', 100000.000, NOW()),
('impact', 4, 'Major', 'جسيم', 'Significant loss; regulatory sanction; national adverse publicity; Sharia board finding on product or contract; multi-day disruption.', 'خسارة كبيرة؛ عقوبة رقابية؛ تغطية سلبية على المستوى الوطني؛ ملاحظة من هيئة الرقابة الشرعية على منتج أو عقد؛ انقطاع لعدة أيام.', 1000000.000, NOW()),
('impact', 5, 'Severe', 'حرج', 'Threatens solvency or licence; loss of public confidence; systemic Sharia non-compliance; prolonged disruption of critical services.', 'يهدد الملاءة أو الترخيص؛ فقدان ثقة الجمهور؛ عدم التزام شرعي منهجي؛ تعطل مطوّل للخدمات الحرجة.', 10000000.000, NOW());

INSERT IGNORE INTO score_bands (min_score, max_score, label_en, label_ar, color, description_en, description_ar, is_high, updated_at) VALUES
(1, 4, 'Low', 'منخفض', '#2e7d32', 'Acceptable; manage through routine controls and periodic review.', 'مقبول؛ يُدار عبر الضوابط الروتينية والمراجعة الدورية.', 0, NOW()),
(5, 9, 'Medium', 'متوسط', '#f9a825', 'Monitor; management attention and documented controls required.', 'يتطلب المتابعة؛ اهتمام الإدارة وضوابط موثقة.', 0, NOW()),
(10, 14, 'High', 'مرتفع', '#ef6c00', 'Senior management attention; treatment plan with owner and deadline required.', 'يتطلب اهتمام الإدارة العليا؛ خطة معالجة بمسؤول وموعد نهائي.', 1, NOW()),
(15, 25, 'Critical', 'حرج', '#c62828', 'Immediate escalation to executive management and the risk committee.', 'تصعيد فوري إلى الإدارة التنفيذية ولجنة المخاطر.', 1, NOW());
