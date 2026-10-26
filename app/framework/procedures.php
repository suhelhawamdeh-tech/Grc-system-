<?php
// Catalogue of the risk function's control procedures (second line of defence), by section.
// Generic wording based on common practice for a risk department's section manuals; the bank adjusts titles,
// frequencies and approvers in the platform. Recurring procedures (monthly or longer) create dated tasks;
// daily and weekly ones are standing duties; event procedures are carried out through the registers.
// Row: [code, section, frequency, approver, title_ar, title_en, output_ar, output_en]
// frequency: daily | weekly | monthly | quarterly | semiannual | annual | event
// approver:  director | head | committee | board | owner
return [
    'version' => '2026.10.1',
    'procedures' => [
        // Credit risk section
        ['CRD-01', 'credit', 'event', 'director', 'إصدار الرأي المستقل في طلبات التمويل المحددة', 'Independent opinion on specified financing requests', 'مذكرة رأي مستقل معتمدة', 'Approved independent opinion memo'],
        ['CRD-02', 'credit', 'event', 'head', 'مراجعة التصنيف الائتماني الداخلي للتعرضات فوق الحد المقرر', 'Review of internal credit ratings above the set threshold', 'تصنيف مُراجَع ومسجل', 'Reviewed and recorded rating'],
        ['CRD-03', 'credit', 'annual', 'committee', 'التحقق الدوري من صلاحية منهجية التصنيف الائتماني', 'Periodic validation of the credit rating methodology', 'تقرير التحقق وتوصيات التعديل', 'Validation report and recommended changes'],
        ['CRD-04', 'credit', 'daily', 'director', 'مراقبة حدود التركز وإعداد النماذج الرقابية', 'Monitoring of concentration limits and regulatory returns', 'تقرير الحدود اليومي والنماذج الرقابية', 'Daily limits report and regulatory returns'],
        ['CRD-05', 'credit', 'monthly', 'director', 'مراقبة جودة المحفظة ومراجعة قائمة المتابعة', 'Portfolio quality monitoring and watch-list review', 'تقرير مراجعة قائمة المتابعة والتوصيات', 'Watch-list review report and recommendations'],
        ['CRD-06', 'credit', 'quarterly', 'director', 'المراجعة المستقلة لتصنيف التمويلات وكفاية المخصصات قبل القيد', 'Independent review of loan classification and provision adequacy before booking', 'مذكرة مراجعة المخصصات', 'Provision review memo'],
        ['CRD-07', 'credit', 'semiannual', 'committee', 'اختبارات الضغط الائتمانية', 'Credit stress testing', 'تقرير نتائج الضغط', 'Stress test results report'],
        ['CRD-08', 'credit', 'event', 'director', 'تقييم مخاطر الائتمان في المنتجات والبرامج الجديدة', 'Credit risk assessment of new products and programmes', 'رأي القسم وحدود مقترحة للمنتج', 'Section opinion and proposed product limits'],
        ['CRD-09', 'credit', 'daily', 'director', 'مراقبة حدود الأطراف المقابلة والدول', 'Monitoring of counterparty and country limits', 'تقرير استغلال الحدود وإشعارات التدهور', 'Limit utilisation report and deterioration notices'],
        ['CRD-10', 'credit', 'event', 'director', 'تسجيل تجاوزات الحدود والاستثناءات وتصعيدها', 'Recording and escalating limit breaches and exceptions', 'سجل محدث وإشعار تصعيد', 'Updated register and escalation notice'],
        ['CRD-11', 'credit', 'quarterly', 'director', 'تقرير مخاطر الائتمان الدوري إلى لجنة المخاطر', 'Periodic credit risk report to the risk committee', 'تقرير معتمد مرفوع إلى رئيس لجنة المخاطر', 'Approved report to the risk committee chair'],
        ['CRD-12', 'credit', 'event', 'director', 'مراجعة اتساق سياسات الائتمان التشغيلية مع إطار المخاطر', 'Consistency review of operational credit policies with the risk framework', 'مذكرة ملاحظات الاتساق', 'Consistency comments memo'],
        // Market and liquidity risk section
        ['MKT-01', 'market', 'daily', 'director', 'التقرير اليومي المستقل للسيولة والمراكز', 'Independent daily liquidity and positions report', 'تقرير يومي معتمد', 'Approved daily report'],
        ['MKT-02', 'market', 'monthly', 'director', 'جدول الاستحقاقات والفجوات والمؤشرات الشهرية', 'Monthly maturity ladder, gaps and indicators', 'جدول الاستحقاقات والمؤشرات', 'Maturity ladder and indicators'],
        ['MKT-03', 'market', 'annual', 'committee', 'التحقق من الافتراضات السلوكية للودائع والحسابات', 'Validation of behavioural assumptions for deposits and accounts', 'تقرير التحقق والافتراضات المقترحة', 'Validation report and proposed assumptions'],
        ['MKT-04', 'market', 'quarterly', 'director', 'قياس مخاطر معدل العائد في السجل المصرفي', 'Measurement of rate-of-return risk in the banking book', 'تقرير مخاطر معدل العائد', 'Rate-of-return risk report'],
        ['MKT-05', 'market', 'monthly', 'director', 'مراقبة السلع والاستثمارات والتحقق المستقل من التقييم', 'Monitoring of commodities and investments and independent price verification', 'تقرير السلع والاستثمارات ومذكرة التحقق من الأسعار', 'Commodities and investments report and price verification memo'],
        ['MKT-06', 'market', 'monthly', 'director', 'اختبارات ضغط السيولة (شهريًا) والسوق ومعدل العائد (ربع سنويًا)', 'Liquidity stress tests (monthly) and market and rate-of-return stress tests (quarterly)', 'تقرير نتائج الضغط وأيام البقاء', 'Stress results and survival days'],
        ['MKT-07', 'market', 'daily', 'director', 'مراقبة مؤشرات الإنذار المبكر والتوصية بتفعيل خطة التمويل الطارئ', 'Early-warning indicators and recommendation to activate the contingency funding plan', 'تقرير المؤشرات وتوصية مكتوبة بتفعيل مرحلة', 'Indicator report and written activation recommendation'],
        ['MKT-08', 'market', 'annual', 'committee', 'إعداد تقييم كفاية السيولة الداخلي (ILAAP)', 'Internal liquidity adequacy assessment (ILAAP)', 'تقرير تقييم كفاية السيولة', 'Liquidity adequacy assessment report'],
        ['MKT-09', 'market', 'event', 'director', 'التقرير المستقل إلى لجنة الأصول والخصوم قبل كل اجتماع', 'Independent report to each ALCO meeting', 'تقرير مستقل مرفق بالمحضر', 'Independent report attached to the minutes'],
        ['MKT-10', 'market', 'event', 'director', 'تسجيل تجاوزات حدود السوق والسيولة وتصعيدها', 'Recording and escalating market and liquidity limit breaches', 'سجل محدث وإشعار تصعيد', 'Updated register and escalation notice'],
        ['MKT-11', 'market', 'quarterly', 'director', 'التقرير الربع سنوي لمخاطر السوق والسيولة إلى لجنة المخاطر', 'Quarterly market and liquidity risk report to the risk committee', 'تقرير مرفوع إلى رئيس لجنة المخاطر', 'Report to the risk committee chair'],
        ['MKT-12', 'market', 'event', 'director', 'الرأي في المنتجات وأدوات التحوط ومنهجية التسعير الداخلي للأموال', 'Opinion on products, hedging instruments and funds transfer pricing', 'مذكرة رأي', 'Opinion memo'],
        // Operational risk section (including business continuity)
        ['OPR-01', 'operational', 'event', 'head', 'استلام بلاغات الأحداث التشغيلية وتسجيلها', 'Receiving and recording operational event reports', 'حدث مسجل برقم مرجعي', 'Event recorded with a reference'],
        ['OPR-02', 'operational', 'monthly', 'head', 'تصنيف الأحداث ومطابقة الخسائر مع الإدارة المالية', 'Classifying events and reconciling losses with finance', 'أحداث مصنفة بخسائر مطابقة', 'Classified events with reconciled losses'],
        ['OPR-03', 'operational', 'event', 'owner', 'تحدي تحليل السبب الجذري للأحداث الجوهرية ومتابعة المعالجة', 'Challenging root-cause analysis of material events and following treatment', 'مذكرة تحدٍّ ومتابعة حتى الإغلاق', 'Challenge memo and follow-up to closure'],
        ['OPR-04', 'operational', 'annual', 'owner', 'تيسير التقييم الذاتي للمخاطر والضوابط وتحديه', 'Facilitating and challenging risk and control self-assessments', 'تقييم معتمد ومذكرة تحدٍّ وسجل مخاطر محدث', 'Approved assessment, challenge memo, updated register'],
        ['OPR-05', 'operational', 'semiannual', 'director', 'التحقق بالعينات من نتائج الاختبار الذاتي للضوابط', 'Sample verification of control self-testing results', 'ورقة عمل التحقق ونتائجه', 'Verification workpaper and results'],
        ['OPR-06', 'operational', 'monthly', 'head', 'مراقبة مؤشرات مخاطر التشغيل الرئيسية', 'Monitoring of operational key risk indicators', 'لوحة المؤشرات وطلبات المعالجة', 'Indicator dashboard and treatment requests'],
        ['OPR-07', 'operational', 'annual', 'committee', 'تحليل السيناريوهات التشغيلية', 'Operational scenario analysis', 'تقرير السيناريوهات', 'Scenario analysis report'],
        ['OPR-08', 'operational', 'event', 'director', 'الرأي المستقل في المنتجات والتغييرات الجوهرية والتعهيد', 'Independent opinion on products, material changes and outsourcing', 'مذكرة رأي', 'Opinion memo'],
        ['OPR-09', 'operational', 'annual', 'board', 'مراجعة تحليل الأثر على الأعمال وتحديه', 'Review and challenge of the business impact analysis', 'مذكرة ملاحظات وقائمة موحدة مقترحة للخدمات الحرجة', 'Comments memo and proposed list of critical services'],
        ['OPR-10', 'operational', 'annual', 'director', 'مراقبة جاهزية خطط الاستمرارية واختباراتها', 'Monitoring continuity plan readiness and testing', 'تقرير جاهزية الاستمرارية', 'Continuity readiness report'],
        ['OPR-11', 'operational', 'event', 'director', 'تسجيل الحالات الجوهرية وتصعيدها', 'Recording and escalating material cases', 'إشعار تصعيد', 'Escalation notice'],
        ['OPR-12', 'operational', 'quarterly', 'director', 'التقرير الربع سنوي لمخاطر التشغيل إلى لجنة المخاطر', 'Quarterly operational risk report to the risk committee', 'تقرير مرفوع إلى رئيس لجنة المخاطر', 'Report to the risk committee chair'],
        // Information security risk section
        ['ISR-01', 'infosec', 'annual', 'owner', 'مراجعة تقييمات مخاطر أمن المعلومات وتحديها', 'Review and challenge of information security risk assessments', 'مذكرة تحدٍّ وسجل مخاطر محدث', 'Challenge memo and updated register'],
        ['ISR-02', 'infosec', 'monthly', 'director', 'المراقبة الشهرية لمؤشرات المخاطر الأمنية', 'Monthly monitoring of security risk indicators', 'لوحة مؤشرات أمن المعلومات', 'Information security indicator dashboard'],
        ['ISR-03', 'infosec', 'semiannual', 'director', 'التحقق بالعينات من مراجعات الصلاحيات ومعالجة الثغرات', 'Sample verification of access reviews and vulnerability remediation', 'ورقة عمل التحقق', 'Verification workpaper'],
        ['ISR-04', 'infosec', 'annual', 'director', 'مراجعة الخطة السنوية للاختبارات الأمنية ونتائجها', 'Review of the annual security testing plan and results', 'رأي في الخطة وسجل ملاحظات الاختبارات', 'Opinion on the plan and log of test findings'],
        ['ISR-05', 'infosec', 'event', 'director', 'مراجعة الحوادث الأمنية الجوهرية', 'Review of material security incidents', 'مذكرة مراجعة وتسجيل في قاعدة الأحداث', 'Review memo and entry in the event database'],
        ['ISR-06', 'infosec', 'event', 'director', 'الرأي المستقل في المشاريع التقنية والتعهيد والخدمات السحابية', 'Independent opinion on IT projects, outsourcing and cloud services', 'مذكرة رأي', 'Opinion memo'],
        ['ISR-07', 'infosec', 'event', 'director', 'الرأي في أدوات الذكاء الاصطناعي ومراقبة استخدامها', 'Opinion on AI tools and monitoring of their use', 'مذكرة رأي وتقرير المراقبة', 'Opinion memo and monitoring report'],
        ['ISR-08', 'infosec', 'quarterly', 'director', 'الرقابة على مخاطر حماية البيانات ونقلها', 'Oversight of data protection and data transfer risks', 'رأي في التقييمات وتقرير المراقبة', 'Opinion on assessments and monitoring report'],
        ['ISR-09', 'infosec', 'monthly', 'director', 'متابعة سجل المخاطر الأمنية المقبولة مؤقتًا', 'Follow-up of the register of temporarily accepted security risks', 'سجل محدث ورفع المنتهي منها', 'Updated register; expired items escalated'],
        ['ISR-10', 'infosec', 'event', 'director', 'مراجعة اتساق السياسات الأمنية التشغيلية', 'Consistency review of operational security policies', 'مذكرة ملاحظات', 'Comments memo'],
        ['ISR-11', 'infosec', 'quarterly', 'director', 'التقرير الربع سنوي إلى لجنة المخاطر وقياس النضج الأمني السنوي', 'Quarterly report to the risk committee and annual security maturity measurement', 'تقرير مرفوع إلى رئيس لجنة المخاطر', 'Report to the risk committee chair'],
        // Basel unit
        ['BSL-01', 'basel', 'monthly', 'director', 'تقرير نسب كفاية رأس المال والرافعة المالية', 'Capital adequacy and leverage ratio report', 'تقرير النسب الشهري', 'Monthly ratios report'],
        ['BSL-02', 'basel', 'annual', 'board', 'إعداد تقرير التقييم الداخلي لكفاية رأس المال (ICAAP)', 'Internal capital adequacy assessment report (ICAAP)', 'تقرير ICAAP معتمد من المجلس', 'ICAAP report approved by the board'],
        ['BSL-03', 'basel', 'semiannual', 'committee', 'اختبارات الضغط على مستوى المصرف', 'Bank-wide stress testing', 'تقرير نتائج الضغط وأثرها على رأس المال', 'Stress results and capital impact'],
        // Risk director's office
        ['OFF-01', 'office', 'monthly', 'director', 'لوحة مقاييس الرغبة في المخاطر', 'Risk appetite dashboard', 'اللوحة الشهرية، وربع سنويًا إلى لجنة المخاطر', 'Monthly dashboard; quarterly to the risk committee'],
        ['OFF-02', 'office', 'quarterly', 'director', 'التقرير الربع سنوي الموحد إلى لجنة المخاطر ومجلس الإدارة', 'Consolidated quarterly risk report to the risk committee and the board', 'التقرير الربعي المعتمد', 'Approved quarterly report'],
        ['OFF-03', 'office', 'monthly', 'director', 'متابعة توصيات المجلس واللجنة وسجل التصعيد', 'Follow-up of board and committee recommendations and the escalation log', 'تقرير متابعة شهري إلى لجنة المخاطر', 'Monthly follow-up report to the risk committee'],
        ['OFF-04', 'office', 'annual', 'committee', 'مذكرة المخاطر الناشئة', 'Emerging risks memo', 'مذكرة موحدة مرفوعة إلى لجنة المخاطر', 'Consolidated memo to the risk committee'],
        ['OFF-05', 'office', 'annual', 'committee', 'تقييم نضج إطار إدارة المخاطر المؤسسية', 'Maturity assessment of the enterprise risk management framework', 'نتيجة التقييم وخطة التطوير', 'Assessment result and development plan'],
        ['OFF-06', 'office', 'annual', 'board', 'التقرير السنوي لإدارة المخاطر وأدائها', 'Annual report of the risk function and its performance', 'تقرير سنوي إلى المجلس عبر لجنة المخاطر', 'Annual report to the board through the risk committee'],
        ['OFF-07', 'office', 'annual', 'board', 'مراجعة بيان الرغبة في المخاطر مع دورة الخطة والموازنة', 'Review of the risk appetite statement with the planning and budget cycle', 'مقترح محدث بتوصية لجنة المخاطر', 'Updated proposal recommended by the risk committee'],
        ['OFF-08', 'office', 'annual', 'committee', 'مراجعة سجل النماذج والتحقق المستقل من النماذج الجوهرية', 'Review of the model register and independent validation of material models', 'سجل نماذج محدث ونتائج التحقق', 'Updated model register and validation results'],
    ],
];
