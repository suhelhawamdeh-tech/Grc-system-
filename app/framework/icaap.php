<?php
// ICAAP methodology data used by the platform: risks assessed for materiality, the nine phases of the annual cycle with
// their checklists, the narrative fields of the annual report and the standard data requests. Generic wording.
return [
    // [key, section that provides the assessment, name_ar, name_en]
    'risks' => [
        ['credit', 'credit', 'مخاطر الائتمان', 'Credit risk'],
        ['concentration', 'credit', 'مخاطر التركز الائتماني', 'Credit concentration risk'],
        ['equity_investment', 'credit', 'مخاطر الاستثمار في الملكية (مشاركة/مضاربة)', 'Equity investment risk (musharaka / mudaraba)'],
        ['market', 'market', 'مخاطر السوق (الصرف والسلع)', 'Market risk (foreign exchange and commodities)'],
        ['rate_of_return', 'market', 'مخاطر معدل العائد', 'Rate-of-return risk'],
        ['displaced_commercial', 'basel', 'المخاطر التجارية المنقولة', 'Displaced commercial risk'],
        ['liquidity', 'market', 'مخاطر السيولة', 'Liquidity risk'],
        ['operational', 'operational', 'مخاطر التشغيل', 'Operational risk'],
        ['cyber', 'infosec', 'مخاطر أمن المعلومات والمخاطر السيبرانية', 'Information security and cyber risk'],
        ['sharia', 'operational', 'مخاطر عدم الالتزام الشرعي', 'Sharia non-compliance risk'],
        ['legal_compliance', 'office', 'المخاطر القانونية والامتثال', 'Legal and compliance risk'],
        ['reputation', 'office', 'مخاطر السمعة', 'Reputation risk'],
        ['strategic', 'office', 'المخاطر الاستراتيجية', 'Strategic risk'],
        ['country_transfer', 'credit', 'مخاطر الدول والتحويل', 'Country and transfer risk'],
        ['real_estate', 'credit', 'مخاطر العقارات', 'Real estate risk'],
        ['climate', 'office', 'مخاطر المناخ والبيئة', 'Climate and environmental risk'],
    ],
    // phase number => [title_ar, title_en, owner_ar, owner_en, weeks, checklist [[ar, en], ...]]
    'phases' => [
        1 => ['التحضير والجدول الزمني', 'Preparation and timetable', 'وحدة بازل', 'Basel unit', '0–3', [
            ['الجدول الزمني معتمد ومحسوب عكسيًا من الموعد الرقابي بهامش كافٍ', 'Timetable approved, worked back from the regulatory deadline with a margin'],
            ['طلبات البيانات أُرسلت لكل الجهات بمواعيد محددة', 'Data requests sent to every provider with due dates'],
            ['لكل ملاحظة من الدورة السابقة إجراء محدد', 'Every finding of the previous cycle has a defined action'],
            ['المنهجيات المعدلة معروضة على لجنة المخاطر', 'Changed methodologies presented to the risk committee'],
        ]],
        2 => ['تحديد المخاطر وتقييم الجوهرية', 'Risk identification and materiality', 'وحدة بازل والأقسام', 'Basel unit and sections', '2–5', [
            ['قُيّمت جميع المخاطر من الأقسام المختصة', 'All risks assessed by the responsible sections'],
            ['طُبقت قاعدة الجوهرية الموحدة دون استبعاد خطر لصعوبة قياسه', 'The materiality rule applied; no risk excluded because it is hard to measure'],
            ['مبرر كل تقييم موثق', 'The reason for every assessment is documented'],
            ['التغير عن السنة السابقة موضح', 'Changes from the previous year explained'],
        ]],
        3 => ['رأس المال المتاح ومتطلبات الدعامة الأولى', 'Available capital and Pillar 1', 'وحدة بازل والإدارة المالية', 'Basel unit and finance', '3–6', [
            ['عناصر رأس المال والاستقطاعات مطابقة للقوائم المالية', 'Capital elements and deductions reconcile with the financial statements'],
            ['النسبة المحتسبة مطابقة لآخر نموذج رقابي أو الفرق مبرر', 'The calculated ratio matches the last regulatory return, or the difference is explained'],
            ['معالجة حسابات الاستثمار ومعامل ألفا موثقة', 'Treatment of investment accounts and the alpha factor documented'],
        ]],
        4 => ['تقدير رأس المال الداخلي', 'Internal capital (Pillar 2)', 'وحدة بازل', 'Basel unit', '5–8', [
            ['لكل خطر جوهري منهجية موثقة ومتحقق منها', 'Every material risk has a documented and checked method'],
            ['الافتراضات مسجلة في سجل الافتراضات', 'Assumptions recorded in the assumptions log'],
            ['طريقة التجميع والهامش الإداري معتمدان', 'Aggregation method and management buffer agreed'],
        ]],
        5 => ['اختبارات الضغط', 'Stress testing', 'وحدة بازل والأقسام', 'Basel unit and sections', '6–9', [
            ['السيناريوهات وشدتها معتمدة من لجنة المخاطر', 'Scenarios and their severity approved by the risk committee'],
            ['الأثر محتسب لكل سنة من أفق التخطيط', 'Impact calculated for every year of the horizon'],
            ['أُجري اختبار الضغط العكسي وقُيّمت معقوليته', 'Reverse stress test performed and its plausibility assessed'],
            ['الإجراءات الإدارية الممكنة وأثرها موضحة', 'Possible management actions and their effect described'],
        ]],
        6 => ['مراجعة خطة رأس المال وخطة الطوارئ', 'Capital plan and contingency plan review', 'وحدة بازل', 'Basel unit', '8–10', [
            ['خطة رأس المال واردة من الإدارة المالية والتنفيذية', 'Capital plan received from finance and executive management'],
            ['رأي الوحدة في الخطة موثق', 'The unit\'s opinion on the plan documented'],
            ['مؤشرات تفعيل خطة الطوارئ وحالتها محدثة', 'Contingency plan triggers and their status updated'],
        ]],
        7 => ['التقييم الكلي وإعداد التقرير', 'Overall assessment and report', 'وحدة بازل / مدير إدارة المخاطر', 'Basel unit / risk director', '9–12', [
            ['قوائم التحقق للمراحل 1 إلى 6 مكتملة', 'Checklists of phases 1 to 6 complete'],
            ['نسبة الكفاية في التقرير = الأداة = النموذج الرقابي', 'Ratio in the report = tool = regulatory return'],
            ['أدنى نسبة في الضغط الشديد ومنطقتها في الملخص', 'Lowest severe-stress ratio and its zone shown in the summary'],
            ['نقاط الضعف وخطة التطوير وحالة الملاحظات السابقة مذكورة', 'Weaknesses, development plan and status of prior findings stated'],
        ]],
        8 => ['الاعتماد والرفع', 'Approval and submission', 'مدير إدارة المخاطر / رئيس لجنة المخاطر', 'Risk director / risk committee chair', '12–14', [
            ['إقرار مدير إدارة المخاطر موقع', 'Risk director\'s declaration signed'],
            ['توصية لجنة المخاطر مسجلة', 'Risk committee recommendation recorded'],
            ['قرار مجلس الإدارة مسجل', 'Board decision recorded'],
            ['رُفع التقرير إلى المصرف المركزي في الموعد', 'Report submitted to the Central Bank on time'],
        ]],
        9 => ['المراجعة المستقلة والمتابعة', 'Independent review and follow-up', 'المراجعة الداخلية / وحدة بازل', 'Internal audit / Basel unit', 'بعد الرفع', [
            ['المراجعة المستقلة مجدولة أو منجزة', 'Independent review scheduled or done'],
            ['الملاحظات مسجلة كأول مدخل للدورة القادمة', 'Findings logged as the first input of the next cycle'],
            ['أمثلة اختبار الاستخدام موثقة', 'Use-test examples documented'],
        ]],
    ],
    // Narrative fields of the annual report, by report section number.
    'narratives' => [
        2 => ['s2_conclusion', 's2_actions', 's2_weaknesses'],
        3 => ['s3_business', 's3_strategy', 's3_environment', 's3_changes'],
        4 => ['s4_governance', 's4_appetite', 's4_compliance'],
        5 => ['s5_results'],
        6 => ['s6_elements', 's6_evolution', 's6_distributions', 's6_reserves'],
        7 => ['s7_treatment'],
        9 => ['s9_base', 's9_moderate', 's9_severe', 's9_specific', 's9_reverse'],
        10 => ['s10_plan', 's10_opinion', 's10_gaps'],
        11 => ['s11_triggers', 's11_actions', 's11_test'],
        12 => ['s12_ilaap'],
        13 => ['s13_comparison', 's13_conclusion', 's13_actions'],
        14 => ['s14_limitations', 's14_simplified', 's14_plan', 's14_prior'],
        15 => ['s15_use'],
    ],
    // Standard data requests: [provider_ar, provider_en, items_ar, items_en, format]
    'data_requests' => [
        ['الإدارة المالية', 'Finance', 'ميزان المراجعة، رأس المال المتاح وعناصره والاستقطاعات، التوقعات المالية، خطة رأس المال، بيانات توزيع أرباح حسابات الاستثمار', 'Trial balance, available capital and deductions, financial projections, capital plan, investment account profit distribution', 'Excel'],
        ['قسم مخاطر الائتمان', 'Credit risk section', 'التعرضات حسب التصنيف والقطاع، أكبر التعرضات والمجموعات، نتائج الضغط الائتماني', 'Exposures by grade and sector, largest exposures and groups, credit stress results', 'Excel'],
        ['قسم مخاطر السوق والسيولة', 'Market and liquidity risk section', 'المراكز المفتوحة، قياس معدل العائد، نتائج الضغط، ملخص تقييم كفاية السيولة', 'Open positions, rate-of-return measurement, stress results, ILAAP summary', 'Excel'],
        ['قسم مخاطر التشغيل', 'Operational risk section', 'الخسائر التشغيلية، نتائج تحليل السيناريوهات، بيانات عدم الالتزام الشرعي', 'Operational losses, scenario analysis results, Sharia non-compliance data', 'Excel'],
        ['قسم مخاطر أمن المعلومات', 'Information security risk section', 'سيناريوهات الأحداث السيبرانية الجسيمة وتقديراتها', 'Severe cyber event scenarios and their estimates', 'Word'],
        ['الإدارة التنفيذية', 'Executive management', 'الخطة الاستراتيجية والموازنة وتوقعات النمو لكل نشاط', 'Strategic plan, budget and growth projections by activity', 'Word/Excel'],
    ],
];
