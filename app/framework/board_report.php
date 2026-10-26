<?php
// Structure of the quarterly board risk report (the bank's model report). The platform keeps the structure, the
// row types of each section and short default wording; every figure and assessment is entered for the quarter.
return [
    // section => [title_ar, title_en, purpose]
    'sections' => [
        1 => ['الملخص التنفيذي ولوحة المؤشرات', 'Executive summary and dashboard', 'info'],
        2 => ['نطاق التقرير ومصادر البيانات والمرجعية', 'Scope, data sources and references', 'info'],
        3 => ['مخاطر الائتمان', 'Credit risk', 'info_direction'],
        4 => ['مخاطر السوق والخزانة', 'Market risk and treasury', 'decision'],
        5 => ['المخاطر التشغيلية والتقنية والامتثال', 'Operational, technology and compliance risk', 'direction'],
        6 => ['كفاية رأس المال والسيولة والربحية', 'Capital adequacy, liquidity and profitability', 'info_direction'],
        7 => ['ملاحظات جودة البيانات والتقارير', 'Data quality and reporting observations', 'info'],
        8 => ['القرارات والتوجيهات المقترحة على مجلس الإدارة', 'Decisions and directions proposed to the board', 'decision'],
        9 => ['خارطة الطريق ومسار الحوكمة والمتابعة', 'Roadmap, governance path and follow-up', 'info'],
        10 => ['التوصيات التنفيذية حسب الإدارة', 'Recommendations by department', 'execution'],
        11 => ['ملحق: منهجية التقييم وسجل الأحداث التشغيلية', 'Annex: assessment method and operational events log', 'reference'],
    ],
    // Narrative fields => section
    'narratives' => ['letter_intro' => 0, 'letter_messages' => 0, 'letter_request' => 0, 's1_overview' => 1, 's3_credit' => 3, 's3_assessment' => 3, 's4_market' => 4, 's4_responsibility' => 4,
        's5_operational' => 5, 's6_capital' => 6, 's6_reading' => 6, 's7_intro' => 7, 's9_roadmap' => 9, 's10_intro' => 10, 's10_followup' => 10],
    // Risk profile domains offered when a report is created: [title_ar, title_en]
    'profile' => [['مخاطر الائتمان', 'Credit risk'], ['مخاطر السوق', 'Market risk'], ['المخاطر التشغيلية والتقنية', 'Operational and technology risk'], ['مخاطر الامتثال والعقوبات', 'Compliance and sanctions risk'],
        ['مخاطر عدم الالتزام الشرعي', 'Sharia non-compliance risk'], ['مخاطر السيولة', 'Liquidity risk'], ['كفاية رأس المال', 'Capital adequacy']],
    'reading_ar' => ['الأرقام الواردة كما أفادت بها الإدارات المختصة، وما احتسبته إدارة المخاطر أو اشتقته موسوم بعبارة «احتساب إدارة المخاطر».',
        'تقييمات مستوى المخاطر (حرج/مرتفع/متوسط/منخفض) هي تقييم إدارة المخاطر للمخاطر المتبقية وفق مصفوفة (5×5) المبينة في الملحق.',
        'المدد والحدود المقترحة موسومة بعبارة «مقترح يحتاج إلى اعتماد»، ولا تُعد نافذة إلا بعد اعتمادها من الجهة المختصة.'],
    'reading_en' => ['Figures are as reported by the departments; anything the risk function calculated or derived is marked "risk management calculation".',
        'Risk levels (critical / high / medium / low) are the risk function\'s assessment of residual risk on the 5×5 matrix in the annex.',
        'Proposed durations and limits are marked "proposal requiring approval" and take effect only once approved by the competent body.'],
    'priorities' => ['urgent', 'high', 'medium'],
    'followup_ar' => 'التبليغ: تُبلغ الإدارة العامة كل إدارة بالجزء الخاص بها خلال 3 أيام عمل من الاعتماد. الإغلاق: لا يُغلق أي إجراء إلا بدليل إنجاز تتحقق منه إدارة المخاطر، وللإجراءات العاجلة يُرفع تقرير إنجاز إلى لجنة المخاطر. التصعيد: يُصعد أي إجراء متأخر إلى المدير العام، ثم إلى لجنة المخاطر إن تجاوز التأخير 15 يومًا. التأكيد: تتحقق المراجعة الداخلية من عينة من الإجراءات المغلقة ضمن خطتها.',
    'followup_en' => 'Communication: executive management sends each department its part within 3 working days of approval. Closure: no action is closed without evidence checked by the risk function; urgent actions are reported to the risk committee. Escalation: an overdue action goes to the chief executive, then to the risk committee if more than 15 days late. Assurance: internal audit checks a sample of closed actions.',
];
