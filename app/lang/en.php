<?php
// English strings. Keep keys identical to ar.php (verified by tests/run.php).
return [
    'app.name' => 'Governance, Risk and Compliance Platform',

    // common
    'common.active' => 'Active', 'common.inactive' => 'Inactive', 'common.active_filters' => 'Active filters', 'common.add' => 'Add', 'common.all' => 'All',
    'common.apply' => 'Apply', 'common.archive' => 'Archive', 'common.back' => 'Back', 'common.cancel' => 'Cancel', 'common.continue' => 'Continue',
    'common.csv_semicolon_hint' => 'Semicolon-separated CSV for Excel installations that use ";" as list separator',
    'common.delete' => 'Delete', 'common.edit' => 'Edit', 'common.filter' => 'Filter', 'common.from' => 'From', 'common.home' => 'Home', 'common.language' => 'Language',
    'common.main_nav' => 'Main navigation', 'common.menu' => 'Menu', 'common.next' => 'Next', 'common.no' => 'No', 'common.no_data' => 'No data for the current selection.',
    'common.no_results' => 'No records match your filters.', 'common.none' => '— None —', 'common.pagination' => 'Pagination', 'common.prev' => 'Previous', 'common.print' => 'Print',
    'common.reset' => 'Reset', 'common.restore' => 'Restore', 'common.save' => 'Save', 'common.search' => 'Search', 'common.select' => '— Select —', 'common.skip' => 'Skip to content',
    'common.status' => 'Status', 'common.to' => 'To', 'common.total_n' => ':n record(s)', 'common.update' => 'Update', 'common.yes' => 'Yes',

    // navigation
    'nav.account' => 'My account', 'nav.actions' => 'Treatment actions', 'nav.admin' => 'Administration', 'nav.audit' => 'Audit log', 'nav.dashboard' => 'Dashboard',
    'nav.incidents' => 'Incidents and losses', 'nav.reports' => 'Reports', 'nav.risks' => 'Risk register',

    // errors
    'error.title' => 'Something went wrong', 'error.403' => 'You do not have permission to perform this action.', 'error.404' => 'The page or record was not found.',
    'error.405' => 'This request method is not allowed.', 'error.419' => 'Your session or form token has expired. Go back, reload the page and try again.',
    'error.500' => 'An unexpected error occurred. It has been logged. Please contact the system administrator.', 'error.503' => 'The service is temporarily unavailable (database connection).',
    'error.reference' => 'Reference: :ref',

    // auth / account
    'auth.login' => 'Sign in', 'auth.login_intro' => 'Enter your credentials to access the platform.', 'auth.username' => 'Username', 'auth.password' => 'Password', 'auth.logout' => 'Sign out',
    'auth.invalid' => 'Invalid username or password.', 'auth.throttled' => 'Too many failed attempts. Try again in about :minutes minute(s).', 'auth.session_expired' => 'Your session expired. Please sign in again.',
    'account.title' => 'My account', 'account.change_password' => 'Change password', 'account.current_password' => 'Current password', 'account.new_password' => 'New password',
    'account.confirm_password' => 'Confirm new password', 'account.current_wrong' => 'The current password is incorrect.', 'account.must_change' => 'You must change your password before continuing.',
    'account.language' => 'Language', 'account.preferred_language' => 'Preferred interface language', 'account.password_changed' => 'Password changed successfully.', 'account.language_saved' => 'Language preference saved.',
    'password.policy' => 'At least 12 characters, including letters and at least one digit.', 'password.too_short' => 'The password must be at least 12 characters.',
    'password.same_as_username' => 'The password must not equal the username.', 'password.needs_mix' => 'The password must contain letters and at least one digit.',
    'password.same_as_old' => 'The new password must differ from the current one.', 'password.mismatch' => 'The password confirmation does not match.', 'password.changed' => 'Password changed.',

    // validation
    'validation.required' => 'The field ":field" is required.', 'validation.max_length' => 'The field ":field" must not exceed :max characters.', 'validation.email' => 'The field ":field" must be a valid email address.',
    'validation.invalid_choice' => 'The value selected for ":field" is not valid.', 'validation.int_range' => 'The field ":field" must be a whole number between :min and :max.',
    'validation.date' => 'The field ":field" must be a valid date (YYYY-MM-DD).', 'validation.amount' => 'The field ":field" must be a valid non-negative amount.',
    'validation.not_future' => 'The field ":field" cannot be in the future.', 'validation.after_date' => 'The field ":field" cannot be earlier than ":other".',
    'validation.out_of_scope' => 'You may only use records within your assigned department or branch.', 'validation.fix_errors' => 'Please correct the highlighted fields.',

    // dashboard
    'dash.subtitle' => 'All figures are calculated from the recorded data and are limited to what you are authorised to see.', 'dash.scoped_note' => 'Restricted to your department / branch',
    'dash.period' => 'Reporting period', 'dash.custom_range' => 'Custom date range', 'dash.active_risks' => 'Active risks', 'dash.high_residual' => 'High residual risks',
    'dash.overdue_actions' => 'Overdue treatment actions', 'dash.review_overdue' => 'Risks past review date', 'dash.incidents_in_period' => 'Incidents / near misses in period', 'dash.near_misses' => 'near misses',
    'dash.net_loss_period' => 'Net loss in period', 'dash.heat_inh' => 'Inherent risk heat map', 'dash.heat_res' => 'Residual risk heat map',
    'dash.heat_caption_inh' => 'Number of active risks by inherent likelihood and impact', 'dash.heat_caption_res' => 'Number of active risks by residual likelihood and impact',
    'dash.heat_cell' => 'Likelihood :l, impact :i, score :s (:band): :n risk(s)', 'dash.heat_axes' => 'Columns: likelihood (1–5). Rows: impact (5 at top). Large number = active risks; small number = score. Click a cell to list the risks.',
    'dash.legend' => 'Score bands', 'dash.by_category' => 'Active risks by category', 'dash.by_department' => 'Active risks by department', 'dash.by_branch' => 'Active risks by branch', 'dash.no_branch' => 'No branch (head office / department level)',
    'dash.top_residual' => 'High residual risks', 'dash.view_all' => 'View all', 'dash.incidents_by_category' => 'Net loss by category', 'dash.incidents_by_month' => 'Incidents by month',
    'dash.incidents_count_note' => 'Number of incidents and near misses per month.', 'dash.data_note' => 'Every figure and chart on this page links to the underlying records and applies your access permissions.',

    // risks
    'risk.register' => 'Risk register', 'risk.new' => 'New risk', 'risk.edit' => 'Edit risk', 'risk.id' => 'Risk ID', 'risk.title' => 'Title', 'risk.description' => 'Description', 'risk.category' => 'Category',
    'risk.causes' => 'Causes', 'risk.consequences' => 'Consequences', 'risk.department' => 'Department', 'risk.branch' => 'Branch', 'risk.owner' => 'Risk owner', 'risk.identified_date' => 'Identification date',
    'risk.review_date' => 'Review date', 'risk.likelihood' => 'Likelihood', 'risk.impact' => 'Impact', 'risk.score' => 'Score', 'risk.inherent' => 'Inherent', 'risk.residual' => 'Residual',
    'risk.inh_likelihood' => 'Inherent likelihood', 'risk.inh_impact' => 'Inherent impact', 'risk.inh_score' => 'Inherent score', 'risk.inh_band' => 'Inherent band',
    'risk.res_likelihood' => 'Residual likelihood', 'risk.res_impact' => 'Residual impact', 'risk.res_score' => 'Residual score', 'risk.res_band' => 'Residual band',
    'risk.res_rationale' => 'Residual assessment rationale', 'risk.rationale_hint' => 'Required whenever the residual score differs from the inherent score. Explain the control effect you assessed.',
    'risk.est_financial_impact' => 'Estimated financial impact', 'risk.est_financial_hint' => 'Optional, in :cur. Used only to compare with the impact thresholds; it does not change the score.',
    'risk.financial_mismatch' => 'Financial thresholds suggest impact level :suggested; level :chosen was selected.',
    'risk.controls' => 'Existing controls', 'risk.control_owner' => 'Control owner', 'risk.control_assessment' => 'Control assessment', 'risk.treatment' => 'Treatment decision',
    'risk.treatment_notes' => 'Treatment notes / justification', 'risk.status' => 'Status', 'risk.archived' => 'Archived', 'risk.hide_archived' => 'Hide archived', 'risk.only_archived' => 'Archived only',
    'risk.include_archived' => 'Include archived', 'risk.overdue' => 'Overdue', 'risk.open_actions' => 'Open actions', 'risk.next_deadline' => 'Next action deadline',
    'risk.section_identification' => 'Identification', 'risk.section_inherent' => 'Inherent risk assessment (before controls)', 'risk.section_controls' => 'Existing controls',
    'risk.section_residual' => 'Residual risk assessment (after controls)', 'risk.section_treatment' => 'Treatment', 'risk.section_assessment' => 'Assessment',
    'risk.inherent_help' => 'Score = likelihood × impact. Score bands and their meaning are configured by the institution.',
    'risk.residual_help' => 'Enter the residual likelihood and impact explicitly. The system does not deduct control effectiveness automatically.',
    'risk.actions_after_save' => 'Treatment actions (owner, deadline, status) can be added on the risk page after saving.',
    'risk.history' => 'Change history', 'risk.history_changes' => 'Changes', 'risk.linked_incidents' => 'Linked incidents and near misses',
    'risk.created' => 'Risk created.', 'risk.updated' => 'Risk updated.', 'risk.archived_msg' => 'Risk archived.', 'risk.restored' => 'Risk restored.', 'risk.no_changes' => 'No changes were made.',
    'risk.confirm_archive' => 'Archive this record? It will be hidden from lists and dashboards but kept for audit.', 'risk.confirm_restore' => 'Restore this record?',
    'risk.err_archived_readonly' => 'Archived records are read-only. Restore the record first.', 'risk.err_rationale_required' => 'A rationale is required when the residual score differs from the inherent score.',
    'risk.err_accept_high_needs_notes' => 'Accepting a high-rated residual risk requires a written justification.',
    'risk.archive' => 'Archive risk', 'risk.create' => 'Create risk', 'risk.view' => 'View risk',

    // actions
    'action.title_plural' => 'Treatment actions', 'action.title' => 'Action', 'action.description' => 'Description', 'action.owner' => 'Action owner', 'action.deadline' => 'Deadline', 'action.status' => 'Action status',
    'action.none' => 'No treatment actions recorded.', 'action.add' => 'Add treatment action', 'action.overdue_only' => 'Overdue only', 'action.added' => 'Treatment action added.', 'action.updated' => 'Treatment action updated.',
    'risk.action_added' => 'Action added', 'risk.action_updated' => 'Action updated',

    // incidents
    'incident.title_plural' => 'Incidents and losses', 'incident.new' => 'Report incident / near miss', 'incident.edit' => 'Edit incident', 'incident.id' => 'Incident ID', 'incident.type' => 'Type', 'incident.name' => 'Title',
    'incident.description' => 'Description', 'incident.occurrence_date' => 'Occurrence date', 'incident.discovery_date' => 'Discovery date', 'incident.category' => 'Category', 'incident.department' => 'Department',
    'incident.branch' => 'Branch', 'incident.gross_loss' => 'Gross loss', 'incident.recoveries' => 'Recoveries', 'incident.net_loss' => 'Net loss', 'incident.root_cause' => 'Root cause',
    'incident.corrective_actions' => 'Corrective actions', 'incident.status' => 'Status', 'incident.linked_risks' => 'Linked risks', 'incident.linked_risk' => 'Linked risk', 'incident.reported_by' => 'Reported by',
    'incident.archived' => 'Archived', 'incident.section_event' => 'Event details', 'incident.section_loss' => 'Financial impact', 'incident.section_analysis' => 'Analysis and follow-up',
    'incident.net_formula' => 'Calculated automatically: net loss = gross loss − recoveries.', 'incident.recoveries_hint' => 'Amounts recovered (insurance, customer, other). Cannot exceed the gross loss.',
    'incident.near_miss_hint' => 'A near miss caused no financial loss: gross loss and recoveries must be 0.', 'incident.linked_hint' => 'Hold Ctrl / Cmd to select several. Only risks you are authorised to see are listed.',
    'incident.totals_filtered' => 'Totals for :n filtered record(s)', 'incident.created' => 'Incident recorded.', 'incident.updated' => 'Incident updated.', 'incident.archived_msg' => 'Incident archived.', 'incident.restored' => 'Incident restored.',
    'incident.err_archived_readonly' => 'Archived records are read-only. Restore the record first.', 'incident.err_recoveries_exceed' => 'Recoveries cannot exceed the gross loss.',
    'incident.err_near_miss_no_loss' => 'A near miss must have zero gross loss and zero recoveries.', 'incident.err_closed_needs_root_cause' => 'A root cause is required before an incident can be closed.',
    'incident.err_bad_risk_link' => 'One or more selected risks are not available to you.', 'incident.archive' => 'Archive incident', 'incident.create' => 'Create incident', 'incident.view' => 'View incident',

    // enums
    'enum.role.admin' => 'System administrator', 'enum.role.risk_manager' => 'Risk manager', 'enum.role.compliance_officer' => 'Compliance officer', 'enum.role.dept_manager' => 'Department manager',
    'enum.role.branch_liaison' => 'Branch liaison officer', 'enum.role.exec_viewer' => 'Executive viewer', 'enum.role.auditor' => 'Auditor',
    'enum.control.not_assessed' => 'Not assessed', 'enum.control.effective' => 'Effective', 'enum.control.partially_effective' => 'Partially effective', 'enum.control.ineffective' => 'Ineffective',
    'enum.treatment.mitigate' => 'Mitigate', 'enum.treatment.accept' => 'Accept', 'enum.treatment.transfer' => 'Transfer', 'enum.treatment.avoid' => 'Avoid',
    'enum.risk_status.active' => 'Active', 'enum.risk_status.closed' => 'Closed',
    'enum.action_status.not_started' => 'Not started', 'enum.action_status.in_progress' => 'In progress', 'enum.action_status.completed' => 'Completed', 'enum.action_status.cancelled' => 'Cancelled',
    'enum.event.incident' => 'Incident', 'enum.event.near_miss' => 'Near miss', 'enum.incident_status.open' => 'Open', 'enum.incident_status.investigating' => 'Investigating', 'enum.incident_status.closed' => 'Closed',
    'enum.kind.conventional' => 'Conventional', 'enum.kind.islamic' => 'Islamic banking',
    'history.created' => 'Created', 'history.updated' => 'Updated', 'history.archived' => 'Archived', 'history.restored' => 'Restored', 'history.action_added' => 'Action added', 'history.action_updated' => 'Action updated',

    // attachments
    'upload.title' => 'Attachments', 'upload.empty' => 'No attachments.', 'upload.choose' => 'Choose a file', 'upload.submit' => 'Upload', 'upload.rules' => 'Allowed: :types. Maximum :max.',
    'upload.none' => 'No file was selected.', 'upload.too_big' => 'The file is empty or larger than the allowed size (:max).', 'upload.bad_type' => 'This file type is not allowed. Allowed: :types.',
    'upload.failed' => 'The upload failed. Please try again.', 'upload.done' => 'File uploaded.', 'upload.deleted' => 'Attachment removed.', 'upload.confirm_delete' => 'Remove this attachment?',

    // reports
    'report.intro' => 'Printable reports and CSV exports use the same access rules as the on-screen lists.', 'report.risk_register' => 'Risk register report', 'report.incident_report' => 'Incident and loss report',
    'report.open_printable' => 'Open printable report', 'report.generated' => 'Generated', 'report.by' => 'By', 'report.confidential' => 'Confidential — internal use', 'report.filters' => 'Filters',
    'report.rows' => 'Showing :shown of :total record(s)', 'report.truncated' => 'The printable report is limited to :n rows. Use the CSV export for the complete list.',
    'report.use_dates' => 'Use the date range', 'report.periods_hint' => 'Reporting periods', 'report.csv_note' => 'CSV files are UTF-8 with BOM (Arabic displays correctly in Excel). Cells that could be interpreted as formulas are neutralised. If columns are not split, use the “CSV (;)” variant.',
    'report.risks_print' => 'Risk report printed', 'report.risk_print' => 'Risk printed', 'report.incidents_print' => 'Incident report printed',

    // administration
    'admin.users' => 'Users', 'admin.user_new' => 'New user', 'admin.user_edit' => 'Edit user', 'admin.username' => 'Username', 'admin.full_name' => 'Full name', 'admin.email' => 'Email', 'admin.role' => 'Role',
    'admin.role_hint' => 'Department managers must be assigned a department; branch liaison officers must be assigned a branch. Access is restricted accordingly on the server.',
    'admin.department' => 'Department', 'admin.branch' => 'Branch', 'admin.language' => 'Preferred language', 'admin.initial_password' => 'Initial password', 'admin.must_change_hint' => 'The user must change it at first sign-in.',
    'admin.reset_password' => 'Reset password', 'admin.new_temp_password' => 'New temporary password', 'admin.scope' => 'Scope', 'admin.owned_risks' => 'Owned risks', 'admin.last_login' => 'Last sign-in',
    'admin.transfer_ownership' => 'Transfer risk ownership', 'admin.transfer_to' => 'Transfer to', 'admin.transfer' => 'Transfer', 'admin.confirm_transfer' => 'Transfer all active risks owned by this user?',
    'admin.transfer_done' => ':n risk(s) transferred.', 'admin.user_created' => 'User created.', 'admin.user_updated' => 'User updated.', 'admin.password_reset_done' => 'Password reset. The user must change it at next sign-in.',
    'admin.saved' => 'Saved.', 'admin.err_username' => 'Username must be 3–64 characters: letters, digits, dot, dash or underscore.', 'admin.err_username_taken' => 'This username is already in use.',
    'admin.err_email_taken' => 'This email is already in use.', 'admin.err_dept_required' => 'A department is required for this role.', 'admin.err_branch_required' => 'A branch is required for this role.',
    'admin.err_self_deactivate' => 'You cannot deactivate your own account.', 'admin.err_last_admin' => 'At least one active system administrator must remain.',
    'admin.departments' => 'Departments', 'admin.branches' => 'Branches', 'admin.categories' => 'Risk categories', 'admin.periods' => 'Reporting periods', 'admin.category' => 'Risk category', 'admin.period' => 'Reporting period',
    'admin.code' => 'Code', 'admin.name_en' => 'Name (English)', 'admin.name_ar' => 'Name (Arabic)', 'admin.city' => 'City', 'admin.kind' => 'Kind', 'admin.sort_order' => 'Sort order', 'admin.start_date' => 'Start date', 'admin.end_date' => 'End date',
    'admin.in_use' => 'Records using it', 'admin.no_delete_note' => 'Entries that are in use cannot be deleted; mark them inactive instead. Inactive entries stay on historical records but cannot be selected for new ones.',
    'admin.islamic_note' => 'Islamic banking categories (Sharia non-compliance, displaced commercial risk, equity investment risk, etc.) are configurable. Recording them here supports risk management; it does not establish Sharia compliance, which remains the responsibility of the Sharia supervisory board.',
    'admin.err_code' => 'Code must be 1–20 characters: letters, digits, dash or underscore.', 'admin.err_code_taken' => 'This code is already in use.',
    'admin.settings' => 'Settings', 'admin.system' => 'System status', 'admin.environment' => 'Environment', 'admin.migrations' => 'Database migrations', 'admin.applied' => 'Applied', 'admin.pending' => 'Pending',
    'admin.apply_migrations' => 'Apply pending migrations', 'admin.confirm_migrate' => 'Apply pending database migrations now? Take a database backup first.', 'admin.backup_first' => 'Always back up the database before upgrading.',
    'admin.up_to_date' => 'Database schema is up to date.', 'admin.migrated' => ':n migration(s) applied.',
    'sys.php' => 'PHP version', 'sys.db' => 'Database server', 'sys.storage_logs' => 'Logs folder writable', 'sys.storage_uploads' => 'Uploads folder writable', 'sys.storage_sessions' => 'Sessions folder writable',
    'sys.config_outside_web' => 'Config file outside web root', 'sys.https' => 'Connection', 'sys.upload_limit' => 'PHP upload limits', 'sys.attention' => 'Attention',
    'settings.org_name_en' => 'Institution name (English)', 'settings.org_name_ar' => 'Institution name (Arabic)', 'settings.currency_code' => 'Currency code (ISO 4217)', 'settings.currency_decimals' => 'Decimal places shown',
    'settings.decimals_hint' => 'Amounts are stored with 3 decimals (LYD has 3). This only changes display and input precision.', 'settings.symbol_en' => 'Currency label (English)', 'settings.symbol_ar' => 'Currency label (Arabic)',
    'settings.default_lang' => 'Default language', 'settings.review_warning_days' => 'Review reminder window (days)', 'settings.err_currency' => 'Currency code must be 3 letters.', 'settings.updated' => 'Settings updated.',

    // scales
    'scales.title' => 'Risk assessment scales', 'scales.intro' => 'Configure the 5 × 5 likelihood × impact matrix: labels, descriptions, financial thresholds and score bands. Scores are always likelihood × impact.',
    'scales.demo_banner' => 'Scoring thresholds, financial thresholds and score bands are DEMONSTRATION settings pending institutional approval.', 'scales.review_link' => 'Review and approve',
    'scales.approve_hint' => 'Only mark as approved after the Risk Committee / Board has approved the criteria. Changing any criterion resets this status.', 'scales.mark_approved' => 'Mark as approved by the institution',
    'scales.confirm_approve' => 'Confirm that these criteria have been approved by the institution?', 'scales.approved_by' => 'Criteria approved. Recorded by :who on :when.',
    'scales.likelihood' => 'Likelihood scale', 'scales.impact' => 'Impact scale (qualitative criteria and financial thresholds)', 'scales.label_en' => 'Label (English)', 'scales.label_ar' => 'Label (Arabic)',
    'scales.desc_en' => 'Description (English)', 'scales.desc_ar' => 'Description (Arabic)', 'scales.description' => 'Description', 'scales.financial_min' => 'Financial threshold (from)',
    'scales.financial_hint' => 'Lowest estimated loss that corresponds to each impact level. Must increase with the level; level 1 starts at 0.', 'scales.save_levels' => 'Save scales', 'scales.bands' => 'Score bands',
    'scales.bands_hint' => 'Bands must cover every score from 1 to 25 with no gaps or overlaps. Leave spare rows empty. “High” bands drive the high-residual-risk dashboard measures.',
    'scales.min' => 'From', 'scales.max' => 'To', 'scales.color' => 'Colour', 'scales.is_high' => 'Counts as high', 'scales.save_bands' => 'Save bands', 'scales.preview' => 'Current bands',
    'scales.matrix_preview' => 'Matrix preview', 'scales.formula_note' => 'Residual scores are recorded explicitly by the assessor; control effectiveness is never deducted automatically.',
    'scales.reference' => 'Impact criteria and financial thresholds', 'scales.print_note' => 'Scores = likelihood × impact using the institution’s configured scales.',
    'scales.err_labels' => 'English and Arabic labels are required (max 80 characters; bands max 60).', 'scales.err_fin_first' => 'Level 1 threshold must be 0.', 'scales.err_fin_increasing' => 'Financial thresholds must increase with each level.',
    'scales.err_band_range' => 'Band limits must be whole numbers from 1 to 25.', 'scales.err_color' => 'Colour must be a valid hex colour.', 'scales.err_band_order' => 'A band’s “From” cannot exceed its “To”.',
    'scales.err_band_coverage' => 'Bands must cover every score from 1 to 25 exactly once (no gaps, no overlaps).', 'scales.saved_unapproved' => 'Saved. The criteria now require institutional approval again.',
    'scales.approved_done' => 'Criteria marked as approved.', 'scales.manage' => 'Manage scales',

    // audit
    'audit.time' => 'Time', 'audit.user' => 'User', 'audit.action' => 'Action', 'audit.entity' => 'Record type', 'audit.entity_id' => 'Record ID', 'audit.detail' => 'Details', 'audit.view' => 'View audit log',

    // setup
    'setup.title' => 'First-run setup', 'setup.intro' => 'Install the platform: verify access, connect the database and create the first administrator. This page becomes unavailable once setup is complete.',
    'setup.requirements' => 'Server requirements', 'setup.step_token' => 'Step 1 — Prove you control the server', 'setup.token' => 'Setup token', 'setup.err_token' => 'The setup token is not correct.',
    'setup.token_where' => 'Open the file :file with the cPanel File Manager and paste the token it contains.', 'setup.token_manual' => 'The token file could not be created automatically. Create :file containing a random 32-character string and reload.',
    'setup.step_db' => 'Step 2 — Database connection', 'setup.db_help' => 'Use the database and user created in cPanel (MySQL Databases). The full names include your cPanel prefix.',
    'setup.db_host' => 'Database host', 'setup.db_port' => 'Port', 'setup.db_name' => 'Database name', 'setup.db_user' => 'Database user', 'setup.db_pass' => 'Database password', 'setup.timezone' => 'Time zone',
    'setup.force_https' => 'Redirect all HTTP requests to HTTPS', 'setup.force_https_hint' => 'Enable only after an SSL certificate is active for this domain.', 'setup.test_save' => 'Test connection and save',
    'setup.err_db' => 'Could not connect to the database (error code :code). Check host, name, user and password.', 'setup.db_saved' => 'Database settings saved.',
    'setup.manual_config' => 'The configuration file could not be written automatically. Create it manually at one of the paths below (preferably outside the public web root), paste the content, then continue.',
    'setup.manual_config_after' => 'Keep this file private. Never commit it or share it.', 'setup.step_admin' => 'Step 3 — Institution and first administrator', 'setup.load_demo' => 'Also load synthetic demonstration data',
    'setup.demo_hint' => 'Creates fictitious departments, branches, users, risks and incidents for evaluation. Do not use on a production database.', 'setup.demo_password' => 'Password for all demonstration users',
    'setup.install' => 'Install', 'setup.err_install' => 'Installation failed: :detail', 'setup.done' => 'Installation complete. Sign in with the administrator account you created.',
    'setup.completed' => 'Setup completed', 'setup.demo_data_loaded' => 'Demo data loaded',
];
