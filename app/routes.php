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

$router->get('/appetite', 'AppetiteController@index', 'appetite.view');
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
