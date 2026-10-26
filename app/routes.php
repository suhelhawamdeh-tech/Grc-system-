<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

// Public
$router->get('/login', 'AuthController@loginForm', null);
$router->post('/login', 'AuthController@login', null);
$router->get('/lang', 'AuthController@lang', null);
$router->get('/login/mfa', 'AuthController@mfaForm', null);
$router->post('/login/mfa', 'AuthController@mfaVerify', null);
$router->get('/password/forgot', 'AuthController@forgotForm', null);
$router->post('/password/forgot', 'AuthController@forgot', null);
$router->get('/password/reset', 'AuthController@resetForm', null);
$router->post('/password/reset', 'AuthController@reset', null);
$router->get('/setup', 'SetupController@index', null);
$router->post('/setup/token', 'SetupController@token', null);
$router->post('/setup/db', 'SetupController@saveDb', null);
$router->post('/setup/finish', 'SetupController@finish', null);

// Any signed-in user
$router->post('/logout', 'AuthController@logout');
$router->get('/account', 'AuthController@account');
$router->post('/account/password', 'AuthController@changePassword');
$router->get('/account/mfa', 'AuthController@mfaSetup');
$router->post('/account/mfa/enable', 'AuthController@mfaEnable');
$router->post('/account/mfa/disable', 'AuthController@mfaDisable');
$router->post('/account/language', 'AuthController@setLanguagePref');
$router->get('/attachments/{id}', 'AttachmentController@download');
$router->post('/attachments/{id}/delete', 'AttachmentController@delete');

// Usability: help centre, notes/reminders, my tasks (any signed-in user)
$router->get('/my', 'MyController@index');
$router->get('/help', 'HelpController@index');
$router->get('/help/panel', 'HelpController@panel');
$router->post('/help/dismiss', 'HelpController@dismiss');
$router->get('/notes', 'NoteController@index');
$router->get('/notes/panel', 'NoteController@panel');
$router->post('/notes', 'NoteController@store');
$router->post('/notes/{id}/done', 'NoteController@done');
$router->post('/notes/{id}/delete', 'NoteController@delete');

$router->get('/import', 'ImportController@index');
$router->get('/import/template', 'ImportController@template');
$router->post('/import/preview', 'ImportController@preview');
$router->post('/import/commit', 'ImportController@commit');

// Dashboard
$router->get('/', 'DashboardController@index', 'dashboard.view');

// Risks
$router->get('/risks', 'RiskController@index', 'risk.view');
$router->get('/risks/new', 'RiskController@create', 'risk.create');
$router->post('/risks', 'RiskController@store', 'risk.create');
$router->get('/risks/export', 'RiskController@export', 'export.run');
$router->get('/risks/print', 'RiskController@printList', 'export.run');
$router->get('/risks/{id}', 'RiskController@show', 'risk.view');
$router->get('/risks/{id}/print', 'RiskController@printOne', 'export.run');
$router->get('/risks/{id}/edit', 'RiskController@edit', 'risk.edit');
$router->post('/risks/{id}', 'RiskController@update', 'risk.edit');
$router->post('/risks/{id}/archive', 'RiskController@archive', 'risk.archive');
$router->post('/risks/{id}/restore', 'RiskController@restore', 'risk.archive');
$router->post('/risks/{id}/actions', 'RiskController@addAction', 'risk.edit');
$router->post('/risks/{id}/actions/{aid}', 'RiskController@updateAction', 'risk.edit');
$router->post('/risks/{id}/attachments', 'RiskController@upload', 'risk.edit');
$router->get('/actions', 'ActionController@index', 'risk.view');

// Incidents
$router->get('/incidents', 'IncidentController@index', 'incident.view');
$router->get('/incidents/new', 'IncidentController@create', 'incident.create');
$router->post('/incidents', 'IncidentController@store', 'incident.create');
$router->get('/incidents/reconciliation', 'ReconController@index', 'recon.view');
$router->post('/incidents/reconciliation/delete', 'ReconController@deletePeriod', 'recon.manage');
$router->get('/incidents/patterns', 'IncidentController@patterns', 'incident.view');
$router->get('/incidents/export', 'IncidentController@export', 'export.run');
$router->get('/incidents/print', 'IncidentController@printList', 'export.run');
$router->get('/incidents/{id}', 'IncidentController@show', 'incident.view');
$router->get('/incidents/{id}/edit', 'IncidentController@edit', 'incident.edit');
$router->post('/incidents/{id}', 'IncidentController@update', 'incident.edit');
$router->post('/incidents/{id}/archive', 'IncidentController@archive', 'incident.archive');
$router->post('/incidents/{id}/restore', 'IncidentController@restore', 'incident.archive');
$router->post('/incidents/{id}/attachments', 'IncidentController@upload', 'incident.edit');

// Reports
$router->get('/reports', 'ReportController@index', 'export.run');
$router->get('/reports/board', 'ReportController@board', 'export.run');

// Administration
$router->get('/admin/users', 'UserController@index', 'users.manage');
$router->get('/admin/users/new', 'UserController@create', 'users.manage');
$router->post('/admin/users', 'UserController@store', 'users.manage');
$router->get('/admin/users/{id}/edit', 'UserController@edit', 'users.manage');
$router->post('/admin/users/{id}', 'UserController@update', 'users.manage');
$router->post('/admin/users/{id}/password', 'UserController@resetPassword', 'users.manage');
$router->post('/admin/users/{id}/transfer', 'UserController@transfer', 'ownership.transfer');

foreach (['departments', 'branches', 'categories', 'periods'] as $entity) {
    $router->get("/admin/$entity", "LookupController@index:$entity", 'org.manage');
    $router->get("/admin/$entity/new", "LookupController@create:$entity", 'org.manage');
    $router->post("/admin/$entity", "LookupController@store:$entity", 'org.manage');
    $router->get("/admin/$entity/{id}/edit", "LookupController@edit:$entity", 'org.manage');
    $router->post("/admin/$entity/{id}", "LookupController@update:$entity", 'org.manage');
}

$router->get('/admin/scales', 'ScalesController@index', 'scales.manage');
$router->post('/admin/scales/levels', 'ScalesController@saveLevels', 'scales.manage');
$router->post('/admin/scales/bands', 'ScalesController@saveBands', 'scales.manage');
$router->post('/admin/scales/approve', 'ScalesController@approve', 'scales.manage');
$router->get('/admin/settings', 'SettingsController@index', 'settings.manage');
$router->post('/admin/settings', 'SettingsController@save', 'settings.manage');
$router->get('/admin/system', 'SystemController@index', 'settings.manage');
$router->post('/admin/system/migrate', 'SystemController@migrate', 'settings.manage');
$router->post('/admin/system/test-mail', 'SystemController@testMail', 'settings.manage');
$router->post('/account/notifications', 'AuthController@setNotifications');

$router->get('/audit', 'AuditController@index', 'audit.view');
$router->get('/audit/export', 'AuditController@export', 'audit.view');

// ---- Phase 2 modules
$router->get('/kris', 'KriController@index', 'kri.view');
$router->get('/kris/new', 'KriController@create', 'kri.manage');
$router->post('/kris', 'KriController@store', 'kri.manage');
$router->get('/kris/{id}', 'KriController@show', 'kri.view');
$router->get('/kris/{id}/edit', 'KriController@edit', 'kri.manage');
$router->post('/kris/{id}', 'KriController@update', 'kri.manage');
$router->post('/kris/{id}/readings', 'KriController@addReading', 'kri.report');
$router->post('/kris/{id}/breaches/{bid}', 'KriController@updateBreach', 'kri.report');
$router->get('/kris/{id}/breaches/{bid}/notice', 'KriController@notice', 'kri.view');
$router->get('/kris/{id}/breaches/{bid}/docx', 'KriController@noticeDocx', 'kri.view');

$router->get('/appetite', 'AppetiteController@index', 'appetite.view');
$router->get('/appetite/framework', 'AppetiteController@framework', 'appetite.view');
$router->post('/appetite/framework/template', 'AppetiteController@createTemplate', 'appetite.manage');
$router->get('/appetite/new', 'AppetiteController@create', 'appetite.manage');
$router->post('/appetite', 'AppetiteController@store', 'appetite.manage');
$router->post('/appetite/evaluate', 'AppetiteController@evaluate', 'appetite.manage');
$router->post('/appetite/breaches/{id}', 'AppetiteController@updateBreach', 'appetite.manage');
$router->get('/appetite/{id}/edit', 'AppetiteController@edit', 'appetite.manage');
$router->post('/appetite/{id}', 'AppetiteController@update', 'appetite.manage');
$router->post('/appetite/{id}/breaches', 'AppetiteController@addBreach', 'appetite.manage');

$router->get('/compliance', 'ComplianceController@index', 'compliance.view');
$router->get('/compliance/new', 'ComplianceController@create', 'compliance.manage');
$router->get('/compliance/export', 'ComplianceController@export', 'compliance.view');
$router->post('/compliance', 'ComplianceController@store', 'compliance.manage');
$router->get('/compliance/{id}', 'ComplianceController@show', 'compliance.view');
$router->get('/compliance/{id}/edit', 'ComplianceController@edit', 'compliance.manage');
$router->post('/compliance/{id}', 'ComplianceController@update', 'compliance.manage');
$router->post('/compliance/{id}/status', 'ComplianceController@status', 'compliance.view');
$router->post('/compliance/{id}/roll', 'ComplianceController@roll', 'compliance.manage');
$router->post('/compliance/{id}/archive', 'ComplianceController@archive', 'compliance.manage');
$router->post('/compliance/{id}/attachments', 'ComplianceController@upload', 'compliance.view');

$router->get('/committees', 'CommitteeController@index', 'committee.view');
$router->get('/committees/new', 'CommitteeController@create', 'committee.manage');
$router->post('/committees', 'CommitteeController@store', 'committee.manage');
$router->get('/committees/{id}', 'CommitteeController@show', 'committee.view');
$router->get('/committees/{id}/edit', 'CommitteeController@edit', 'committee.manage');
$router->post('/committees/{id}', 'CommitteeController@update', 'committee.manage');
$router->post('/committees/{id}/actions', 'CommitteeController@addAction', 'committee.manage');
$router->post('/committees/{id}/actions/{aid}', 'CommitteeController@updateAction', 'committee.manage');

$router->get('/policies', 'PolicyController@index', 'policy.view');
$router->get('/policies/new', 'PolicyController@create', 'policy.manage');
$router->get('/policies/framework', 'PolicyController@framework', 'policy.view');
$router->post('/policies/import', 'PolicyController@import', 'policy.manage');
$router->post('/policies', 'PolicyController@store', 'policy.manage');
$router->get('/policies/{id}', 'PolicyController@show', 'policy.view');
$router->get('/policies/{id}/edit', 'PolicyController@edit', 'policy.manage');
$router->post('/policies/{id}', 'PolicyController@update', 'policy.manage');
$router->post('/policies/{id}/versions', 'PolicyController@addVersion', 'policy.manage');
$router->post('/policies/{id}/versions/{vid}/attachments', 'PolicyController@upload', 'policy.manage');

$router->get('/rcsa', 'RcsaController@index', 'rcsa.view');
$router->get('/rcsa/launch', 'RcsaController@launchForm', 'rcsa.manage');
$router->get('/rcsa/schedules', 'RcsaController@schedules', 'rcsa.manage');
$router->post('/rcsa/schedules', 'RcsaController@scheduleStore', 'rcsa.manage');
$router->post('/rcsa/schedules/{id}/toggle', 'RcsaController@scheduleToggle', 'rcsa.manage');
$router->post('/rcsa/launch', 'RcsaController@launch', 'rcsa.manage');
$router->get('/rcsa/templates/new', 'RcsaController@templateForm', 'rcsa.manage');
$router->post('/rcsa/templates', 'RcsaController@templateStore', 'rcsa.manage');
$router->get('/rcsa/templates/{id}/edit', 'RcsaController@templateEdit', 'rcsa.manage');
$router->post('/rcsa/templates/{id}', 'RcsaController@templateUpdate', 'rcsa.manage');
$router->get('/rcsa/{id}', 'RcsaController@show', 'rcsa.view');
$router->post('/rcsa/{id}/save', 'RcsaController@save', 'rcsa.respond');
$router->post('/rcsa/{id}/submit', 'RcsaController@submit', 'rcsa.respond');
$router->post('/rcsa/{id}/review', 'RcsaController@review', 'rcsa.manage');

// ---- Release 3: control library and testing
$router->get('/controls', 'ControlController@index', 'control.view');
$router->get('/controls/new', 'ControlController@create', 'control.manage');
$router->post('/controls', 'ControlController@store', 'control.manage');
$router->get('/controls/{id}', 'ControlController@show', 'control.view');
$router->get('/controls/{id}/edit', 'ControlController@edit', 'control.manage');
$router->post('/controls/{id}', 'ControlController@update', 'control.manage');
$router->post('/controls/{id}/archive', 'ControlController@archive', 'control.manage');
$router->post('/controls/{id}/risks', 'ControlController@link', 'control.manage');
$router->post('/controls/{id}/risks/{rid}/remove', 'ControlController@unlink', 'control.manage');
$router->post('/controls/{id}/tests', 'ControlController@addTest', 'control.test');

// ---- Release 3: Sharia compliance
$router->get('/sharia', 'ShariaController@index', 'sharia.view');
$router->get('/sharia/report', 'ShariaController@report', 'sharia.view');
$router->get('/sharia/rulings', 'ShariaController@rulings', 'sharia.view');
$router->get('/sharia/rulings/new', 'ShariaController@rulingNew', 'sharia.manage');
$router->post('/sharia/rulings', 'ShariaController@rulingStore', 'sharia.manage');
$router->get('/sharia/rulings/{id}', 'ShariaController@rulingShow', 'sharia.view');
$router->get('/sharia/rulings/{id}/edit', 'ShariaController@rulingEdit', 'sharia.manage');
$router->post('/sharia/rulings/{id}', 'ShariaController@rulingUpdate', 'sharia.manage');
$router->post('/sharia/rulings/{id}/attachments', 'ShariaController@rulingUpload', 'sharia.manage');
$router->get('/sharia/reviews', 'ShariaController@reviews', 'sharia.view');
$router->get('/sharia/reviews/new', 'ShariaController@reviewNew', 'sharia.manage');
$router->post('/sharia/reviews', 'ShariaController@reviewStore', 'sharia.manage');
$router->get('/sharia/reviews/{id}', 'ShariaController@reviewShow', 'sharia.view');
$router->get('/sharia/reviews/{id}/edit', 'ShariaController@reviewEdit', 'sharia.manage');
$router->post('/sharia/reviews/{id}', 'ShariaController@reviewUpdate', 'sharia.manage');
$router->get('/sharia/events', 'ShariaController@events', 'sharia.view');
$router->get('/sharia/events/new', 'ShariaController@eventNew', 'sharia.report');
$router->post('/sharia/events', 'ShariaController@eventStore', 'sharia.report');
$router->get('/sharia/events/{id}', 'ShariaController@eventShow', 'sharia.view');
$router->get('/sharia/events/{id}/edit', 'ShariaController@eventEdit', 'sharia.manage');
$router->post('/sharia/events/{id}', 'ShariaController@eventUpdate', 'sharia.manage');
$router->post('/sharia/events/{id}/review', 'ShariaController@eventReview', 'sharia.manage');
$router->post('/sharia/events/{id}/decision', 'ShariaController@eventDecide', 'sharia.manage');
$router->post('/sharia/events/{id}/purify', 'ShariaController@eventPurify', 'sharia.manage');
$router->post('/sharia/events/{id}/close', 'ShariaController@eventClose', 'sharia.manage');
$router->post('/sharia/events/{id}/attachments', 'ShariaController@eventUpload', 'sharia.report');

// ---- Release 3: business impact analysis and approvals
$router->get('/bia', 'BiaController@index', 'bia.view');
$router->get('/bia/new', 'BiaController@create', 'bia.manage');
$router->post('/bia', 'BiaController@store', 'bia.manage');
$router->get('/bia/report', 'BiaController@report', 'bia.view');
$router->get('/bia/systems', 'BiaController@systems', 'bia.view');
$router->get('/bia/systems/new', 'BiaController@systemNew', 'bia.manage');
$router->post('/bia/systems', 'BiaController@systemStore', 'bia.manage');
$router->get('/bia/systems/{id}/edit', 'BiaController@systemEdit', 'bia.manage');
$router->post('/bia/systems/{id}', 'BiaController@systemUpdate', 'bia.manage');
$router->get('/bia/{id}', 'BiaController@show', 'bia.view');
$router->get('/bia/{id}/edit', 'BiaController@edit', 'bia.manage');
$router->post('/bia/{id}', 'BiaController@update', 'bia.manage');
$router->post('/bia/{id}/submit', 'BiaController@submit', 'bia.manage');
$router->post('/bia/{id}/archive', 'BiaController@archive', 'bia.manage');
$router->get('/approvals', 'ApprovalController@index', 'approval.decide');
$router->post('/approvals/{id}/decide', 'ApprovalController@decide', 'approval.decide');

// ---- Release 3: regulatory circulars
$router->get('/circulars', 'CircularController@index', 'circular.view');
$router->get('/circulars/new', 'CircularController@create', 'circular.manage');
$router->post('/circulars', 'CircularController@store', 'circular.manage');
$router->get('/circulars/{id}', 'CircularController@show', 'circular.view');
$router->get('/circulars/{id}/edit', 'CircularController@edit', 'circular.manage');
$router->post('/circulars/{id}', 'CircularController@update', 'circular.manage');
$router->post('/circulars/{id}/distribute', 'CircularController@distribute', 'circular.manage');
$router->post('/circulars/{id}/acknowledge', 'CircularController@acknowledge', 'circular.ack');
$router->post('/circulars/{id}/attachments', 'CircularController@upload', 'circular.manage');

// ---- Release 3: strategic objectives
$router->get('/strategy', 'StrategyController@index', 'strategy.view');
$router->get('/strategy/new', 'StrategyController@create', 'strategy.manage');
$router->post('/strategy', 'StrategyController@store', 'strategy.manage');
$router->get('/strategy/{id}', 'StrategyController@show', 'strategy.view');
$router->get('/strategy/{id}/edit', 'StrategyController@edit', 'strategy.manage');
$router->post('/strategy/{id}', 'StrategyController@update', 'strategy.manage');
$router->post('/strategy/{id}/progress', 'StrategyController@progress', 'strategy.manage');
$router->post('/strategy/{id}/risks', 'StrategyController@link', 'strategy.manage');
$router->post('/strategy/{id}/risks/{rid}/remove', 'StrategyController@unlink', 'strategy.manage');
$router->post('/strategy/{id}/archive', 'StrategyController@archive', 'strategy.manage');

// ---- Release 3: board and committee governance
$router->get('/governance', 'GovernanceController@index', 'governance.view');
$router->get('/governance/new', 'GovernanceController@bodyNew', 'governance.manage');
$router->post('/governance', 'GovernanceController@bodyStore', 'governance.manage');
$router->get('/governance/report', 'GovernanceController@report', 'governance.view');
$router->get('/governance/meetings/{id}', 'GovernanceController@meetingShow', 'governance.view');
$router->post('/governance/meetings/{id}/hold', 'GovernanceController@meetingHold', 'governance.manage');
$router->post('/governance/meetings/{id}/cancel', 'GovernanceController@meetingCancel', 'governance.manage');
$router->get('/governance/{id}', 'GovernanceController@bodyShow', 'governance.view');
$router->get('/governance/{id}/edit', 'GovernanceController@bodyEdit', 'governance.manage');
$router->post('/governance/{id}', 'GovernanceController@bodyUpdate', 'governance.manage');
$router->post('/governance/{id}/members', 'GovernanceController@addMember', 'governance.manage');
$router->post('/governance/{id}/members/{uid}/remove', 'GovernanceController@removeMember', 'governance.manage');
$router->post('/governance/{id}/meetings', 'GovernanceController@meetingStore', 'governance.manage');

// ---- Release 4: internal audit
$router->get('/ia', 'IaController@index', 'ia.view');
$router->get('/ia/universe', 'IaController@universe', 'ia.view');
$router->get('/ia/universe/new', 'IaController@universeNew', 'ia.manage');
$router->post('/ia/universe', 'IaController@universeStore', 'ia.manage');
$router->get('/ia/universe/{id}/edit', 'IaController@universeEdit', 'ia.manage');
$router->post('/ia/universe/{id}', 'IaController@universeUpdate', 'ia.manage');
$router->get('/ia/plans', 'IaController@plans', 'ia.view');
$router->post('/ia/plans', 'IaController@planStore', 'ia.manage');
$router->get('/ia/plans/{id}', 'IaController@planShow', 'ia.view');
$router->post('/ia/plans/{id}/engagements', 'IaController@engagementStore', 'ia.manage');
$router->post('/ia/plans/{id}/approve', 'IaController@planApprove', 'ia.manage');
$router->get('/ia/engagements/{id}', 'IaController@engagementShow', 'ia.view');
$router->post('/ia/engagements/{id}/start', 'IaController@engagementStart', 'ia.manage');
$router->post('/ia/engagements/{id}/reporting', 'IaController@engagementReporting', 'ia.manage');
$router->post('/ia/engagements/{id}/issue', 'IaController@engagementIssue', 'ia.manage');
$router->post('/ia/engagements/{id}/cancel', 'IaController@engagementCancel', 'ia.manage');
$router->post('/ia/engagements/{id}/workpapers', 'IaController@workpaperStore', 'ia.manage');
$router->get('/ia/engagements/{id}/findings/new', 'IaController@findingNew', 'ia.manage');
$router->post('/ia/engagements/{id}/findings', 'IaController@findingStore', 'ia.manage');
$router->get('/ia/workpapers/{id}', 'IaController@workpaperShow', 'ia.view');
$router->post('/ia/workpapers/{id}', 'IaController@workpaperSave', 'ia.manage');
$router->post('/ia/workpapers/{id}/review', 'IaController@workpaperReview', 'ia.manage');
$router->post('/ia/workpapers/{id}/attachments', 'IaController@workpaperUpload', 'ia.manage');
$router->get('/ia/findings', 'IaController@findings', 'ia.findings');
$router->get('/ia/findings/{id}', 'IaController@findingShow', 'ia.findings');
$router->get('/ia/findings/{id}/edit', 'IaController@findingEdit', 'ia.manage');
$router->post('/ia/findings/{id}', 'IaController@findingUpdate', 'ia.manage');
$router->post('/ia/findings/{id}/respond', 'IaController@findingRespond', 'ia.respond');
$router->post('/ia/findings/{id}/implemented', 'IaController@findingImplemented', 'ia.respond');
$router->post('/ia/findings/{id}/verify', 'IaController@findingVerify', 'ia.manage');
$router->post('/ia/findings/{id}/extend', 'IaController@findingExtend', 'ia.manage');
$router->post('/ia/findings/{id}/attachments', 'IaController@findingUpload', 'ia.findings');

// ---- Release 4: anti-money laundering
$router->get('/aml', 'AmlController@index', 'aml.view');
$router->post('/aml/controls', 'AmlController@linkControl', 'aml.manage');
$router->post('/aml/controls/remove', 'AmlController@unlinkControl', 'aml.manage');
$router->get('/aml/factors', 'AmlController@factors', 'aml.view');
$router->get('/aml/factors/new', 'AmlController@factorNew', 'aml.manage');
$router->post('/aml/factors', 'AmlController@factorStore', 'aml.manage');
$router->get('/aml/factors/{id}/edit', 'AmlController@factorEdit', 'aml.manage');
$router->post('/aml/factors/{id}', 'AmlController@factorUpdate', 'aml.manage');
$router->get('/aml/customers', 'AmlController@customers', 'aml.view');
$router->get('/aml/customers/export', 'AmlController@export', 'aml.view');
$router->get('/aml/customers/new', 'AmlController@customerNew', 'aml.manage');
$router->post('/aml/customers', 'AmlController@customerStore', 'aml.manage');
$router->get('/aml/customers/{id}', 'AmlController@customerShow', 'aml.view');
$router->get('/aml/customers/{id}/edit', 'AmlController@customerEdit', 'aml.manage');
$router->post('/aml/customers/{id}', 'AmlController@customerUpdate', 'aml.manage');
$router->post('/aml/customers/{id}/review', 'AmlController@customerReview', 'aml.manage');
$router->post('/aml/customers/{id}/override', 'AmlController@customerOverride', 'aml.manage');
$router->get('/aml/reports', 'AmlController@reports', 'aml.view');
$router->get('/aml/reports/new', 'AmlController@reportNew', 'aml.manage');
$router->post('/aml/reports', 'AmlController@reportStore', 'aml.manage');
$router->get('/aml/reports/{id}', 'AmlController@reportShow', 'aml.view');
$router->get('/aml/reports/{id}/edit', 'AmlController@reportEdit', 'aml.manage');
$router->post('/aml/reports/{id}', 'AmlController@reportUpdate', 'aml.manage');
$router->post('/aml/reports/{id}/submit', 'AmlController@reportSubmit', 'aml.manage');
$router->post('/aml/reports/{id}/attachments', 'AmlController@reportUpload', 'aml.manage');

// ---- Release 4: core-banking integration (Temenos T24)
$router->get('/admin/integrations', 'IntegrationController@index', 'integration.manage');
$router->post('/admin/integrations/settings', 'IntegrationController@saveSettings', 'integration.manage');
$router->post('/admin/integrations/test', 'IntegrationController@test', 'integration.manage');
$router->post('/admin/integrations/upload', 'IntegrationController@upload', 'integration.manage');
$router->post('/admin/integrations/pull', 'IntegrationController@pull', 'integration.manage');
$router->get('/admin/integrations/runs/{id}', 'IntegrationController@run', 'integration.manage');
$router->get('/admin/integrations/sample/customers', 'IntegrationController@sample:customers', 'integration.manage');
$router->get('/admin/integrations/sample/gl', 'IntegrationController@sample:gl', 'integration.manage');

// ---- Release 5: AI assistant and reference library
$router->get('/assistant', 'AssistantController@index', 'ai.use');
$router->post('/assistant/ask', 'AssistantController@ask', 'ai.use');
$router->get('/assistant/history/{id}', 'AssistantController@show', 'ai.use');
$router->get('/assistant/library', 'AssistantController@library', 'ai.use');
$router->get('/assistant/library/new', 'AssistantController@sourceNew', 'kb.manage');
$router->post('/assistant/library', 'AssistantController@sourceStore', 'kb.manage');
$router->get('/assistant/library/{id}', 'AssistantController@sourceShow', 'ai.use');
$router->post('/assistant/library/{id}/retire', 'AssistantController@sourceRetire', 'kb.manage');
$router->post('/circulars/{id}/to-library', 'AssistantController@fromCircular', 'kb.manage');
$router->get('/admin/assistant', 'AssistantController@settings', 'settings.manage');
$router->post('/admin/assistant', 'AssistantController@saveSettings', 'settings.manage');
$router->post('/admin/assistant/test', 'AssistantController@testConnection', 'settings.manage');
$router->post('/admin/assistant/sync', 'AssistantController@syncLibrary', 'settings.manage');

// Risk function workbench (release 1.6)
$router->get('/rm', 'RiskFunctionController@home', 'rm.view');
$router->get('/rm/calendar', 'RiskFunctionController@calendar', 'rm.view');
$router->post('/rm/tasks/{id}', 'RiskFunctionController@completeTask', 'rm.work');
$router->get('/rm/procedures', 'RiskFunctionController@procedures', 'rm.view');
$router->post('/rm/procedures/{id}', 'RiskFunctionController@updateProcedure', 'rm.approve');
$router->get('/rm/opinions', 'RiskFunctionController@opinions', 'rm.view');
$router->get('/rm/opinions/new', 'RiskFunctionController@opinionNew', 'rm.work');
$router->post('/rm/opinions', 'RiskFunctionController@opinionStore', 'rm.work');
$router->get('/rm/opinions/{id}', 'RiskFunctionController@opinionShow', 'rm.view');
$router->get('/rm/opinions/{id}/edit', 'RiskFunctionController@opinionEdit', 'rm.work');
$router->post('/rm/opinions/{id}', 'RiskFunctionController@opinionUpdate', 'rm.work');
$router->post('/rm/opinions/{id}/step', 'RiskFunctionController@opinionStep', 'rm.work');
$router->post('/rm/opinions/{id}/decision', 'RiskFunctionController@opinionDecide', 'rm.work');
$router->get('/rm/opinions/{id}/print', 'RiskFunctionController@opinionPrint', 'rm.view');
$router->get('/rm/opinions/{id}/docx', 'RiskFunctionController@opinionDocx', 'rm.view');
$router->get('/rm/breaches', 'RiskFunctionController@breaches', 'rm.breaches');
$router->get('/rm/breaches/new', 'RiskFunctionController@breachNew', 'rm.work');
$router->post('/rm/breaches', 'RiskFunctionController@breachStore', 'rm.work');
$router->get('/rm/breaches/{id}', 'RiskFunctionController@breachShow', 'rm.breaches');
$router->post('/rm/breaches/{id}/plan', 'RiskFunctionController@breachPlan', 'rm.breaches');
$router->post('/rm/breaches/{id}/resolve', 'RiskFunctionController@breachResolve', 'rm.work');
$router->get('/rm/breaches/{id}/print', 'RiskFunctionController@breachPrint', 'rm.breaches');
$router->get('/rm/breaches/{id}/docx', 'RiskFunctionController@breachDocx', 'rm.breaches');
$router->get('/rm/escalations', 'RiskFunctionController@escalations', 'rm.view');
$router->get('/rm/escalations/new', 'RiskFunctionController@escalationNew', 'rm.work');
$router->post('/rm/escalations', 'RiskFunctionController@escalationStore', 'rm.work');
$router->get('/rm/escalations/{id}', 'RiskFunctionController@escalationShow', 'rm.view');
$router->post('/rm/escalations/{id}/respond', 'RiskFunctionController@escalationRespond', 'rm.work');
$router->post('/rm/escalations/{id}/close', 'RiskFunctionController@escalationClose', 'rm.approve');
$router->get('/rm/escalations/{id}/docx', 'RiskFunctionController@escalationDocx', 'rm.view');

// ICAAP (release 1.6)
// Supporting registers of the risk function (release 1.6)
$router->get('/registers', 'RegisterController@index', 'rm.view');
$router->get('/registers/new', 'RegisterController@create', 'rm.work');
$router->post('/registers', 'RegisterController@store', 'rm.work');
$router->post('/registers/template', 'RegisterController@template', 'rm.work');
$router->get('/registers/{id}', 'RegisterController@show', 'rm.view');
$router->get('/registers/{id}/edit', 'RegisterController@edit', 'rm.work');
$router->post('/registers/{id}', 'RegisterController@update', 'rm.work');
// Quarterly board risk report (release 1.6)
$router->get('/board-reports', 'BoardReportController@index', 'rm.view');
$router->post('/board-reports', 'BoardReportController@store', 'rm.work');
$router->get('/board-reports/{id}', 'BoardReportController@show', 'rm.view');
$router->post('/board-reports/{id}', 'BoardReportController@update', 'rm.work');
$router->post('/board-reports/{id}/rows', 'BoardReportController@addRow', 'rm.work');
$router->post('/board-reports/{id}/rows/{rid}', 'BoardReportController@updateRow', 'rm.work');
$router->post('/board-reports/{id}/rows/{rid}/delete', 'BoardReportController@deleteRow', 'rm.work');
$router->post('/board-reports/{id}/tiles', 'BoardReportController@suggestTiles', 'rm.work');
$router->post('/board-reports/{id}/references', 'BoardReportController@addFrameworkRefs', 'rm.work');
$router->post('/board-reports/{id}/status', 'BoardReportController@status', 'rm.work');
$router->get('/board-reports/{id}/print', 'BoardReportController@print', 'rm.view');
$router->get('/board-reports/{id}/docx', 'BoardReportController@docx', 'rm.view');
$router->get('/icaap', 'IcaapController@index', 'icaap.view');
$router->post('/icaap', 'IcaapController@store', 'icaap.manage');
$router->get('/icaap/{id}', 'IcaapController@show', 'icaap.view');
$router->post('/icaap/{id}/phases/{no}', 'IcaapController@phase', 'icaap.manage');
$router->get('/icaap/{id}/capital', 'IcaapController@capital', 'icaap.view');
$router->post('/icaap/{id}/capital', 'IcaapController@saveCapital', 'icaap.manage');
$router->get('/icaap/{id}/risks', 'IcaapController@risks', 'icaap.view');
$router->post('/icaap/{id}/risks', 'IcaapController@saveRisks', 'icaap.manage');
$router->get('/icaap/{id}/stress', 'IcaapController@stress', 'icaap.view');
$router->post('/icaap/{id}/stress', 'IcaapController@saveStress', 'icaap.manage');
$router->get('/icaap/{id}/log', 'IcaapController@log', 'icaap.view');
$router->post('/icaap/{id}/requests', 'IcaapController@addRequest', 'icaap.manage');
$router->post('/icaap/{id}/requests/{rid}', 'IcaapController@updateRequest', 'icaap.manage');
$router->post('/icaap/{id}/assumptions', 'IcaapController@addAssumption', 'icaap.manage');
$router->get('/icaap/{id}/report', 'IcaapController@report', 'icaap.view');
$router->post('/icaap/{id}/report', 'IcaapController@saveReport', 'icaap.manage');
$router->post('/icaap/{id}/approval', 'IcaapController@approve', 'icaap.manage');
$router->get('/icaap/{id}/print', 'IcaapController@print', 'icaap.view');
$router->get('/icaap/{id}/docx', 'IcaapController@docx', 'icaap.view');
