<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

// Public
$router->get('/login', 'AuthController@loginForm', null);
$router->post('/login', 'AuthController@login', null);
$router->get('/lang', 'AuthController@lang', null);
$router->get('/setup', 'SetupController@index', null);
$router->post('/setup/token', 'SetupController@token', null);
$router->post('/setup/db', 'SetupController@saveDb', null);
$router->post('/setup/finish', 'SetupController@finish', null);

// Any signed-in user
$router->post('/logout', 'AuthController@logout');
$router->get('/account', 'AuthController@account');
$router->post('/account/password', 'AuthController@changePassword');
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
$router->post('/rcsa/launch', 'RcsaController@launch', 'rcsa.manage');
$router->get('/rcsa/templates/new', 'RcsaController@templateForm', 'rcsa.manage');
$router->post('/rcsa/templates', 'RcsaController@templateStore', 'rcsa.manage');
$router->get('/rcsa/templates/{id}/edit', 'RcsaController@templateEdit', 'rcsa.manage');
$router->post('/rcsa/templates/{id}', 'RcsaController@templateUpdate', 'rcsa.manage');
$router->get('/rcsa/{id}', 'RcsaController@show', 'rcsa.view');
$router->post('/rcsa/{id}/save', 'RcsaController@save', 'rcsa.respond');
$router->post('/rcsa/{id}/submit', 'RcsaController@submit', 'rcsa.respond');
$router->post('/rcsa/{id}/review', 'RcsaController@review', 'rcsa.manage');
