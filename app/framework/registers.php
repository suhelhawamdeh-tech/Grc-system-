<?php
// Supporting registers of the risk function (release 1.6). Each register shares the common columns of
// rm_register_items (code, title, status, department, owner, due date, linked record) and keeps its own fields as JSON.
//
// register => [
//   prefix, icon, section (risk-function section), due (label key of the due date, or null), ref (linked record type or null),
//   statuses, open (statuses still being followed), fields: name => [type, required, options], list (extra list columns),
//   template (standard items a user can add in one step; generic wording, no bank data)
// ]
// Field types: text, textarea, date, int, select (options), score (1-5), dept, user.
return [
    'models' => [
        'prefix' => 'MDL', 'icon' => 'layers', 'section' => 'basel', 'due' => 'reg.f_next_validation', 'ref' => null,
        'statuses' => ['in_use', 'under_development', 'under_validation', 'retired'], 'open' => ['in_use', 'under_development', 'under_validation'],
        'fields' => [
            'model_type' => ['select', true, ['rating', 'pd_lgd', 'provisioning', 'capital', 'stress', 'liquidity', 'pricing', 'aml_screening', 'other']],
            'tier' => ['select', true, ['high', 'medium', 'low']],
            'purpose' => ['textarea', true],
            'developer' => ['text', false],
            'validator' => ['text', false],
            'last_validated' => ['date', false],
            'findings' => ['textarea', false],
        ],
        'list' => ['model_type', 'tier', 'last_validated'],
    ],
    'data' => [
        'prefix' => 'DLV', 'icon' => 'pulse', 'section' => 'office', 'due' => 'reg.f_delivery_due', 'ref' => null,
        'statuses' => ['expected', 'received', 'late_received', 'not_received'], 'open' => ['expected'],
        'fields' => [
            'period' => ['text', true],
            'frequency' => ['select', true, ['monthly', 'quarterly', 'semiannual', 'annual', 'ad_hoc']],
            'received_on' => ['date', false],
            'quality' => ['select', false, ['complete', 'partial', 'issues']],
            'issues' => ['textarea', false],
        ],
        'list' => ['period', 'received_on', 'quality'],
    ],
    'maturity' => [
        'prefix' => 'MAT', 'icon' => 'target', 'section' => 'office', 'due' => null, 'ref' => null,
        'statuses' => ['assessed', 'approved'], 'open' => [],
        'fields' => [
            'axis' => ['select', true, ['governance', 'appetite', 'policies', 'methodologies', 'data_systems', 'reporting', 'culture', 'competencies']],
            'year' => ['int', true],
            'level' => ['score', false],
            'target' => ['score', false],
            'evidence' => ['textarea', false],
            'gap_actions' => ['textarea', false],
        ],
        'list' => ['axis', 'year', 'level', 'target'],
        // One row per axis for the year being assessed.
        'template' => 'axes',
    ],
    'security' => [
        'prefix' => 'SEC', 'icon' => 'lock', 'section' => 'infosec', 'due' => 'reg.f_expiry', 'ref' => null,
        'statuses' => ['active', 'renewed', 'closed', 'expired'], 'open' => ['active', 'renewed'],
        'fields' => [
            'system' => ['text', true],
            'control_ref' => ['text', true],
            'reason' => ['textarea', true],
            'compensating' => ['textarea', true],
            'risk_level' => ['select', true, ['low', 'medium', 'high', 'critical']],
            'granted_on' => ['date', true],
            'approver' => ['text', true],
        ],
        'list' => ['system', 'risk_level', 'granted_on'],
    ],
    'emerging' => [
        'prefix' => 'EMR', 'icon' => 'spark', 'section' => 'office', 'due' => 'reg.f_next_review', 'ref' => 'risk',
        'statuses' => ['monitoring', 'escalated', 'closed'], 'open' => ['monitoring'],
        'fields' => [
            'horizon' => ['select', true, ['short', 'medium', 'long']],
            'likelihood' => ['score', false],
            'impact' => ['score', false],
            'description' => ['textarea', true],
            'signals' => ['textarea', false],
            'response' => ['textarea', false],
        ],
        'list' => ['horizon', 'likelihood', 'impact'],
    ],
    'requirements' => [
        'prefix' => 'REQ', 'icon' => 'book', 'section' => 'office', 'due' => 'reg.f_req_due', 'ref' => 'circular',
        'statuses' => ['not_started', 'in_progress', 'partial', 'compliant', 'not_applicable'], 'open' => ['not_started', 'in_progress', 'partial'],
        'fields' => [
            'clause' => ['text', false],
            'risk_role' => ['textarea', false],
            'output' => ['text', false],
            'kri' => ['text', false],
            'evidence' => ['textarea', false],
            'gap' => ['textarea', false],
        ],
        'list' => ['clause', 'output', 'kri'],
    ],
    'committee_plan' => [
        'prefix' => 'CMP', 'icon' => 'clipboard', 'section' => 'office', 'due' => 'reg.f_meeting_target', 'ref' => null,
        'statuses' => ['planned', 'presented', 'deferred', 'cancelled'], 'open' => ['planned', 'deferred'],
        'fields' => [
            'committee' => ['select', true, ['board_risk', 'board', 'executive_risk', 'alco', 'board_audit', 'sharia_board', 'other']],
            'year' => ['int', true],
            'item_type' => ['select', true, ['standing', 'annual', 'private_session', 'ad_hoc']],
            'description' => ['textarea', false],
        ],
        'list' => ['committee', 'item_type'],
        'template' => 'committee',
    ],
    // Standard annual plan of the board risk committee, by quarter: [item_type, months, title_ar, title_en]
    'committee_template' => [
        // Every meeting (standing agenda)
        ['standing', [3, 6, 9, 12], 'متابعة محضر الاجتماع السابق والقرارات والتوصيات', 'Previous minutes, decisions and recommendations follow-up'],
        ['standing', [3, 6, 9, 12], 'التقرير الربعي الموحد للمخاطر ولوحة مقاييس الرغبة في المخاطر', 'Quarterly consolidated risk report and risk appetite dashboard'],
        ['standing', [3, 6, 9, 12], 'الحالات المصعدة منذ الاجتماع السابق وخطط معالجتها', 'Cases escalated since the last meeting and their treatment plans'],
        ['private_session', [3, 6, 9, 12], 'جلسة منفردة مع مدير إدارة المخاطر دون حضور الإدارة التنفيذية', 'Private session with the risk director without executive management'],
        // First quarter
        ['annual', [3], 'خطة عمل إدارة المخاطر وخطة اللجنة للسنة', 'Risk function work plan and committee plan for the year'],
        ['annual', [3], 'مراجعة بيان الرغبة في المخاطر ومعايرة مقاييسه', 'Review of the risk appetite statement and calibration of its metrics'],
        ['annual', [3], 'برنامج اختبارات الضغط وجدول التقييم الداخلي لكفاية رأس المال', 'Stress testing programme and ICAAP timetable'],
        // Second quarter
        ['annual', [6], 'تقرير التقييم الداخلي لكفاية رأس المال وتقييم كفاية السيولة قبل رفعهما إلى المجلس', 'ICAAP report and liquidity adequacy assessment before submission to the board'],
        ['annual', [6], 'رأي وحدة بازل في خطة رأس المال', 'Basel unit opinion on the capital plan'],
        // Third quarter
        ['annual', [9], 'تقرير جاهزية استمرارية الأعمال', 'Business continuity readiness report'],
        ['annual', [9], 'نتائج الاختبارات الأمنية المستقلة', 'Results of independent security testing'],
        ['annual', [9], 'نتائج التحقق من المنهجيات والنماذج', 'Results of methodology and model validation'],
        // Fourth quarter
        ['annual', [12], 'المراجعة السنوية للسياسات', 'Annual review of policies'],
        ['annual', [12], 'اتساق الخطة والموازنة مع الرغبة في المخاطر', 'Consistency of the plan and budget with the risk appetite'],
        ['annual', [12], 'تقييم أداء إدارة المخاطر وتقييم اللجنة الذاتي ومستوى نضج الإطار', 'Evaluation of the risk function, committee self-assessment and framework maturity'],
        ['annual', [12], 'التقرير السنوي للجنة إلى المجلس', 'Committee annual report to the board'],
        ['private_session', [12], 'جلسة منفردة مع مدير المراجعة الداخلية', 'Private session with the head of internal audit'],
    ],
];
