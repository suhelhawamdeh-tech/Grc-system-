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
            'axis' => ['select', true, ['governance', 'appetite', 'identification', 'measurement', 'monitoring', 'reporting', 'culture', 'data_systems']],
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
            'evidence' => ['textarea', false],
            'gap' => ['textarea', false],
        ],
        'list' => ['clause'],
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
    // Standard annual plan of the board risk committee: [item_type, months, title_ar, title_en]
    'committee_template' => [
        ['standing', [3, 6, 9, 12], 'التقرير الربعي الشامل للمخاطر', 'Quarterly comprehensive risk report'],
        ['standing', [3, 6, 9, 12], 'لوحة مقاييس الرغبة في المخاطر والتجاوزات', 'Risk appetite dashboard and breaches'],
        ['standing', [3, 6, 9, 12], 'متابعة قرارات اللجنة والمجلس السابقة', 'Follow-up of previous committee and board decisions'],
        ['standing', [3, 6, 9, 12], 'الحالات المصعدة إلى رئيس اللجنة', 'Cases escalated to the committee chair'],
        ['annual', [3], 'مراجعة إطار الرغبة في المخاطر والحدود', 'Review of the risk appetite framework and limits'],
        ['annual', [3], 'تقرير التقييم الداخلي لكفاية رأس المال قبل رفعه إلى المجلس', 'ICAAP report before submission to the board'],
        ['annual', [6], 'نتائج اختبارات الضغط', 'Stress test results'],
        ['annual', [9], 'المراجعة الدورية للسياسات والأدلة المستحقة', 'Periodic review of policies and manuals due'],
        ['annual', [12], 'خطة عمل إدارة المخاطر للسنة القادمة وموازنتها', 'Risk function plan and budget for the next year'],
        ['annual', [12], 'التقييم الذاتي لأداء اللجنة ومراجعة الميثاق', 'Committee self-assessment and charter review'],
        ['annual', [12], 'مستوى نضج إطار إدارة المخاطر', 'Maturity of the risk management framework'],
        ['private_session', [6, 12], 'جلسة مغلقة مع مدير إدارة المخاطر دون حضور الإدارة التنفيذية', 'Private session with the risk director without executive management'],
    ],
];
