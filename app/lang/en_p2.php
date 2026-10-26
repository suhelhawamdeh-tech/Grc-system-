<?php
// Phase 2 module strings (English). Keep keys identical to ar_p2.php.
return [
    'nav.governance' => 'Governance and compliance', 'nav.kris' => 'Key risk indicators', 'nav.appetite' => 'Risk appetite', 'nav.compliance' => 'Compliance obligations',
    'nav.committees' => 'Committee decisions', 'nav.policies' => 'Policies', 'nav.rcsa' => 'RCSA',

    'dash.governance' => 'Governance and compliance', 'dash.kri_red' => 'KRIs in red (latest reading)', 'dash.kri_amber' => 'KRIs in amber (latest reading)', 'dash.breaches' => 'Open risk-appetite breaches',
    'dash.obl_overdue' => 'Overdue compliance obligations', 'dash.dec_overdue' => 'Overdue committee follow-up actions', 'dash.rcsa_review' => 'RCSA awaiting review', 'dash.rcsa_overdue' => 'Overdue RCSA assessments',
    'dash.pol_overdue' => 'Policies past review date',

    'enum.status.green' => 'Green', 'enum.status.amber' => 'Amber', 'enum.status.red' => 'Red', 'enum.status.open' => 'Open', 'enum.status.escalated' => 'Escalated', 'enum.status.accepted' => 'Accepted',
    'enum.status.resolved' => 'Resolved', 'enum.status.draft' => 'Draft', 'enum.status.submitted' => 'Submitted', 'enum.status.approved' => 'Approved', 'enum.status.returned' => 'Returned',
    'enum.status.not_started' => 'Not started', 'enum.status.in_progress' => 'In progress', 'enum.status.compliant' => 'Compliant', 'enum.status.non_compliant' => 'Non-compliant',
    'enum.status.not_applicable' => 'Not applicable', 'enum.status.completed' => 'Completed', 'enum.status.implemented' => 'Implemented', 'enum.status.superseded' => 'Superseded',
    'enum.status.effective' => 'Effective', 'enum.status.partially_effective' => 'Partially effective', 'enum.status.ineffective' => 'Ineffective', 'enum.status.retired' => 'Retired', 'enum.status.cancelled' => 'Cancelled',
    'enum.bsrc.auto' => 'Auto-detected', 'enum.bsrc.manual' => 'Recorded manually',
    'enum.committee.board_risk' => 'Board Risk Committee', 'enum.committee.board_audit' => 'Board Audit Committee', 'enum.committee.executive_risk' => 'Executive Risk Committee', 'enum.committee.alco' => 'ALCO',
    'enum.committee.sharia_board' => 'Sharia Supervisory Board', 'enum.committee.other' => 'Other committee',
    'enum.dir.higher_worse' => 'Higher is worse', 'enum.dir.lower_worse' => 'Lower is worse',
    'enum.dstatus.open' => 'Open', 'enum.dstatus.implemented' => 'Implemented', 'enum.dstatus.superseded' => 'Superseded',
    'enum.freq.once' => 'One-off', 'enum.freq.monthly' => 'Monthly', 'enum.freq.quarterly' => 'Quarterly', 'enum.freq.semiannual' => 'Semi-annual', 'enum.freq.annual' => 'Annual',
    'enum.kfreq.monthly' => 'Monthly', 'enum.kfreq.quarterly' => 'Quarterly',
    'enum.metric.max_residual_score' => 'Maximum residual risk score', 'enum.metric.max_net_loss' => 'Maximum net loss in the current period',
    'enum.obl_status.not_started' => 'Not started', 'enum.obl_status.in_progress' => 'In progress', 'enum.obl_status.compliant' => 'Compliant', 'enum.obl_status.non_compliant' => 'Non-compliant', 'enum.obl_status.not_applicable' => 'Not applicable',
    'enum.pcat.policy' => 'Policy', 'enum.pcat.procedure' => 'Procedure', 'enum.pcat.standard' => 'Standard', 'enum.pcat.charter' => 'Charter / terms of reference', 'enum.pcat.sharia_resolution' => 'Sharia board resolution',
    'enum.pstatus.draft' => 'Draft', 'enum.pstatus.approved' => 'Approved', 'enum.pstatus.retired' => 'Retired',
    'enum.rating.effective' => 'Effective', 'enum.rating.partially_effective' => 'Partially effective', 'enum.rating.ineffective' => 'Ineffective', 'enum.rating.not_applicable' => 'Not applicable', 'enum.rating.unanswered' => 'Unanswered',

    // KRIs
    'kri.title_plural' => 'Key risk indicators', 'kri.subtitle' => 'Configurable thresholds, reporting cycles and alerts', 'kri.new' => 'New KRI', 'kri.edit' => 'Edit KRI', 'kri.id' => 'KRI ID', 'kri.name' => 'Indicator',
    'kri.description' => 'Description / how it is measured', 'kri.department' => 'Department', 'kri.owner' => 'Owner', 'kri.category' => 'Risk category', 'kri.linked_risk' => 'Linked risk', 'kri.unit' => 'Unit',
    'kri.unit_hint' => 'For example %, count, days, LYD.', 'kri.frequency' => 'Reporting frequency', 'kri.direction' => 'Direction', 'kri.amber' => 'Amber threshold', 'kri.red' => 'Red threshold',
    'kri.amber_hint' => 'Higher-is-worse: amber when value ≥ this. Lower-is-worse: amber when value ≤ this.', 'kri.red_hint' => 'Must be at or beyond the amber threshold in the chosen direction.',
    'kri.thresholds' => 'Thresholds (amber / red)', 'kri.last_value' => 'Latest value', 'kri.last_status' => 'Latest status', 'kri.alerts_only' => 'Alerts only (amber/red)', 'kri.missing_only' => 'Missing current-cycle reading',
    'kri.missing' => 'Reading due', 'kri.definition' => 'Definition', 'kri.trend' => 'Trend (last 12 readings)', 'kri.add_reading' => 'Add reading', 'kri.reading_date' => 'Reading date', 'kri.value' => 'Value',
    'kri.period' => 'Reporting period', 'kri.comment' => 'Comment', 'kri.comment_hint' => 'Explain any amber/red reading and the planned response.', 'kri.readings' => 'Readings',
    'kri.err_thresholds' => 'The red threshold must be at or beyond the amber threshold for the chosen direction.', 'kri.err_inactive' => 'This KRI is inactive.',
    'kri.reading_saved' => 'Reading saved.', 'kri.reading_saved_alert' => 'Reading saved. The KRI is outside its green zone — an alert is shown on the KRI list and dashboard.',
    'kri.demo_note' => 'Threshold values must come from the institution’s approved risk appetite / KRI framework.',

    // appetite
    'appetite.title' => 'Risk appetite', 'appetite.subtitle' => 'Approved appetite statements, live measurement against stored data, and breach tracking', 'appetite.new' => 'New appetite statement', 'appetite.edit' => 'Edit appetite statement',
    'appetite.id' => 'ID', 'appetite.name' => 'Title', 'appetite.statement' => 'Appetite statement', 'appetite.metric' => 'Measure', 'appetite.limit' => 'Limit',
    'appetite.limit_hint' => 'Residual score: whole number 1–25. Net loss: amount in the base currency.', 'appetite.category' => 'Category (blank = all)', 'appetite.department' => 'Department (blank = all)',
    'appetite.all_categories' => 'All categories', 'appetite.all_departments' => 'All departments', 'appetite.approved_by' => 'Approved by', 'appetite.approved_hint' => 'Record the approving body, e.g. Board Risk Committee.',
    'appetite.approved_on' => 'Approval date', 'appetite.not_approved' => 'Approval not recorded', 'appetite.current' => 'Current measure', 'appetite.breached' => 'Breached', 'appetite.within' => 'Within appetite',
    'appetite.n_over' => ':n risk(s) above the limit', 'appetite.open_breaches' => 'Open breaches', 'appetite.evaluate' => 'Evaluate now and open breach records', 'appetite.evaluated' => 'Evaluation complete: :n new breach record(s).',
    'appetite.breaches' => 'Breach register', 'appetite.record_breach' => 'Record a breach manually', 'appetite.detected_on' => 'Detected on', 'appetite.breach_description' => 'Description', 'appetite.owner' => 'Owner',
    'appetite.breach_status' => 'Breach status', 'appetite.resolution_note' => 'Resolution / acceptance note', 'appetite.resolved_on' => 'Closed on', 'appetite.measured' => 'Measured value',
    'appetite.open_and_escalated' => 'Open and escalated', 'appetite.err_note_required' => 'A note is required to accept or resolve a breach.',
    'appetite.auto_description' => 'Automatic detection: :metric exceeded the approved limit (:detail).',
    'appetite.disclaimer' => 'Measurements are calculated from the recorded data only. Appetite limits shown are configured by the institution and must be approved by the Board / Risk Committee; the software does not establish regulatory compliance.',

    // compliance
    'comp.title_plural' => 'Compliance obligations', 'comp.subtitle' => 'Regulatory and internal obligations with owners, deadlines and evidence', 'comp.new' => 'New obligation', 'comp.edit' => 'Edit obligation',
    'comp.id' => 'ID', 'comp.name' => 'Obligation', 'comp.source' => 'Source (law / circular / standard)', 'comp.source_hint' => 'Cite the exact regulation or circular reference as issued by the authority.',
    'comp.description' => 'Requirement / description', 'comp.department' => 'Responsible department', 'comp.owner' => 'Responsible owner', 'comp.frequency' => 'Frequency', 'comp.due_date' => 'Next due date',
    'comp.status' => 'Status', 'comp.status_note' => 'Status note / basis', 'comp.status_note_hint' => 'Required for non-compliant and not-applicable; required for compliant when no evidence is attached.',
    'comp.last_reviewed' => 'Last reviewed', 'comp.evidence' => 'Evidence', 'comp.details' => 'Details', 'comp.update_status' => 'Update status', 'comp.overdue_only' => 'Overdue only',
    'comp.roll' => 'Roll to next cycle', 'comp.confirm_roll' => 'Move the due date to the next cycle and reset the status?', 'comp.rolled' => 'Due date moved to the next cycle.',
    'comp.err_note_required' => 'A note explaining the basis is required for this status.', 'comp.err_evidence_required' => 'Attach evidence or write a note before marking as compliant.',
    'comp.err_not_recurring' => 'This obligation is one-off and cannot be rolled forward.',
    'comp.disclaimer' => 'This register tracks the institution’s own assessment of obligations. Entries do not constitute legal advice or a determination of regulatory or Sharia compliance.',

    // committees
    'cmt.title_plural' => 'Committee decisions', 'cmt.new' => 'Record decision', 'cmt.edit' => 'Edit decision', 'cmt.id' => 'ID', 'cmt.committee' => 'Committee', 'cmt.meeting_date' => 'Meeting date', 'cmt.name' => 'Decision title',
    'cmt.decision' => 'Decision', 'cmt.status' => 'Status', 'cmt.actions' => 'Follow-up actions', 'cmt.followup' => 'Follow-up actions', 'cmt.overdue_only' => 'With overdue actions',

    // policies
    'pol.title_plural' => 'Policies and procedures', 'pol.new' => 'New policy document', 'pol.edit' => 'Edit policy document', 'pol.id' => 'ID', 'pol.name' => 'Title', 'pol.category' => 'Type', 'pol.department' => 'Department',
    'pol.institution_wide' => 'Institution-wide', 'pol.owner' => 'Owner', 'pol.status' => 'Status', 'pol.review_months' => 'Review cycle (months)', 'pol.next_review' => 'Next review', 'pol.current_version' => 'Current version',
    'pol.versions' => 'Version history', 'pol.no_versions' => 'No versions recorded yet.', 'pol.current' => 'Current', 'pol.superseded' => 'Superseded', 'pol.add_version' => 'Add new version', 'pol.version' => 'Version label',
    'pol.effective_date' => 'Effective date', 'pol.approved_by' => 'Approved by', 'pol.approved_hint' => 'Approving body, e.g. Board of Directors resolution no.', 'pol.approval_date' => 'Approval date',
    'pol.change_summary' => 'Summary of changes', 'pol.version_note' => 'Versions are immutable history. Adding a version makes it current, keeps earlier versions, resets the next review date and, when an approval date is given, marks the policy approved.',
    'pol.err_version_exists' => 'This version label already exists for the policy.', 'pol.version_added' => 'Version added.', 'pol.review_overdue_only' => 'Review overdue',

    // RCSA
    'rcsa.title' => 'Risk and control self-assessments (RCSA)', 'rcsa.subtitle' => 'Questionnaires completed by units, submitted, then reviewed', 'rcsa.launch' => 'Launch assessment', 'rcsa.template_new' => 'New questionnaire',
    'rcsa.template_edit' => 'Edit questionnaire', 'rcsa.templates' => 'Questionnaire templates', 'rcsa.template' => 'Questionnaire', 'rcsa.template_title' => 'Title', 'rcsa.description' => 'Description',
    'rcsa.questions' => 'Questions', 'rcsa.existing_questions' => 'Existing questions', 'rcsa.add_questions' => 'Add questions', 'rcsa.questions_hint' => 'Each question needs an English and an Arabic text. Leave unused rows empty.',
    'rcsa.question_en' => 'Question (English)', 'rcsa.question_ar' => 'Question (Arabic)', 'rcsa.id' => 'ID', 'rcsa.assessor' => 'Assessor', 'rcsa.due_date' => 'Due date', 'rcsa.status' => 'Status',
    'rcsa.overdue_only' => 'Overdue only', 'rcsa.summary' => 'Result summary', 'rcsa.rating' => 'Control rating', 'rcsa.comment' => 'Comment / evidence', 'rcsa.linked_risk' => 'Related risk',
    'rcsa.save_draft' => 'Save draft', 'rcsa.submit' => 'Submit for review', 'rcsa.submit_hint' => 'Submitting requires a rating for every question and a comment for partially effective / ineffective answers.',
    'rcsa.review' => 'Review', 'rcsa.decision' => 'Decision', 'rcsa.approve' => 'Approve', 'rcsa.return' => 'Return for changes', 'rcsa.review_comment' => 'Reviewer comment',
    'rcsa.launched' => 'Assessment launched.', 'rcsa.saved' => 'Draft saved.', 'rcsa.submitted' => 'Submitted for review.', 'rcsa.approved_msg' => 'Assessment approved.', 'rcsa.returned_msg' => 'Assessment returned to the assessor.',
    'rcsa.err_question' => 'Every question needs both English and Arabic text (max 500 characters).', 'rcsa.err_no_questions' => 'Add at least one question.', 'rcsa.err_unanswered' => 'Rate this question before submitting.',
    'rcsa.err_comment_required' => 'A comment is required for this rating.', 'rcsa.err_not_submitted' => 'Only submitted assessments can be reviewed.', 'rcsa.err_self_review' => 'The assessor cannot review their own assessment.',
    'rcsa.err_return_comment' => 'Explain what must change when returning an assessment.',
];
