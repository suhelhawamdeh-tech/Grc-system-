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
