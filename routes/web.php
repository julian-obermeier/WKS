<?php
declare(strict_types=1);

use WKS\Controllers\Admin\AuditController;
use WKS\Controllers\Admin\LocationController;
use WKS\Controllers\Admin\RoleController;
use WKS\Controllers\Admin\SettingsController;
use WKS\Controllers\Admin\UserController;
use WKS\Controllers\AuthController;
use WKS\Controllers\DashboardController;
use WKS\Controllers\InstallController;
use WKS\Controllers\LocationSelectionController;
use WKS\Controllers\ProfileController;

/** @var WKS\Core\Router $router */

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'install']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/password/change', [AuthController::class, 'showChangePassword'], ['auth']);
$router->post('/password/change', [AuthController::class, 'changePassword'], ['auth']);

$router->get('/location/select', [LocationSelectionController::class, 'show'], ['auth', 'password']);
$router->post('/location/select', [LocationSelectionController::class, 'select'], ['auth', 'password']);

$router->post('/profile/theme', [ProfileController::class, 'theme'], ['auth']);

$router->get('/', [DashboardController::class, 'index'], ['auth', 'password', 'location']);

$router->get('/admin/users', [UserController::class, 'index'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->get('/admin/users/create', [UserController::class, 'create'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->post('/admin/users', [UserController::class, 'store'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->post('/admin/users/{id}', [UserController::class, 'update'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->post('/admin/users/{id}/status', [UserController::class, 'status'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->post('/admin/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['auth', 'password', 'location', 'permission:system.users.manage']);
$router->post('/admin/users/{id}/revoke-sessions', [UserController::class, 'revokeSessions'], ['auth', 'password', 'location', 'permission:system.users.manage']);

$router->get('/admin/roles', [RoleController::class, 'index'], ['auth', 'password', 'location', 'permission:system.roles.manage']);
$router->post('/admin/roles/{id}', [RoleController::class, 'update'], ['auth', 'password', 'location', 'permission:system.roles.manage']);

$router->get('/admin/locations', [LocationController::class, 'index'], ['auth', 'password', 'location', 'permission:system.locations.manage']);
$router->get('/admin/locations/create', [LocationController::class, 'create'], ['auth', 'password', 'location', 'permission:system.locations.manage']);
$router->post('/admin/locations', [LocationController::class, 'store'], ['auth', 'password', 'location', 'permission:system.locations.manage']);
$router->get('/admin/locations/{id}/edit', [LocationController::class, 'edit'], ['auth', 'password', 'location', 'permission:system.locations.manage']);
$router->post('/admin/locations/{id}', [LocationController::class, 'update'], ['auth', 'password', 'location', 'permission:system.locations.manage']);

$router->get('/admin/audit', [AuditController::class, 'index'], ['auth', 'password', 'location', 'permission:system.audit.view']);

$router->get('/admin/settings/security', [SettingsController::class, 'security'], ['auth', 'password', 'location', 'permission:system.settings.manage']);
$router->post('/admin/settings/security', [SettingsController::class, 'updateSecurity'], ['auth', 'password', 'location', 'permission:system.settings.manage']);


$router->get('/dutybook', [DutybookController::class, 'index'], ['auth','password','location','permission:dutybook.read']);
$router->get('/dutybook/search', [DutybookController::class, 'search'], ['auth','password','location','permission:dutybook.read']);
$router->get('/dutybook/create', [DutybookController::class, 'create'], ['auth','password','location','permission:dutybook.create']);
$router->post('/dutybook', [DutybookController::class, 'store'], ['auth','password','location','permission:dutybook.create']);
$router->get('/dutybook/{id}', [DutybookController::class, 'show'], ['auth','password','location','permission:dutybook.read']);
$router->get('/dutybook/{id}/edit', [DutybookController::class, 'edit'], ['auth','password','location','permission:dutybook.edit']);
$router->post('/dutybook/{id}', [DutybookController::class, 'update'], ['auth','password','location','permission:dutybook.edit']);
$router->post('/dutybook/{id}/addendum', [DutybookController::class, 'addendum'], ['auth','password','location','permission:dutybook.addendum']);
$router->get('/dutybook-export.csv', [DutybookController::class, 'exportCsv'], ['auth','password','location','permission:dutybook.export']);
$router->get('/dutybook-print', [DutybookController::class, 'printDay'], ['auth','password','location','permission:dutybook.export']);

$router->post('/shift/accept', [ShiftController::class, 'accept'], ['auth','password','location','permission:dutybook.create']);
$router->post('/shift/{id}/end', [ShiftController::class, 'end'], ['auth','password','location','permission:dutybook.create']);

$router->get('/handover/{sessionId}', [HandoverController::class, 'show'], ['auth','password','location','permission:dutybook.read']);
$router->post('/handover/{sessionId}', [HandoverController::class, 'save'], ['auth','password','location','permission:dutybook.create']);
$router->post('/handover/{sessionId}/confirm-outgoing', [HandoverController::class, 'confirmOutgoing'], ['auth','password','location','permission:dutybook.create']);
$router->post('/handover/{sessionId}/confirm-incoming', [HandoverController::class, 'confirmIncoming'], ['auth','password','location','permission:dutybook.create']);

$router->get('/attachments/{id}/download', [AttachmentController::class, 'download'], ['auth','password','location']);

$router->get('/admin/dutybook', [DutybookConfigController::class, 'index'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/shift', [DutybookConfigController::class, 'saveShift'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/category', [DutybookConfigController::class, 'saveCategory'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/event-type', [DutybookConfigController::class, 'saveEventType'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/dynamic-field', [DutybookConfigController::class, 'saveDynamicField'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/place', [DutybookConfigController::class, 'savePlace'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/measure', [DutybookConfigController::class, 'saveMeasure'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/person-role', [DutybookConfigController::class, 'savePersonRole'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/dutybook/external', [DutybookConfigController::class, 'saveExternal'], ['auth','password','location','permission:system.masterdata.manage']);


$router->get('/special-reports', [SpecialReportController::class, 'index'], ['auth','password','location','permission:special_reports.read']);
$router->get('/special-reports/search', [SpecialReportController::class, 'search'], ['auth','password','location','permission:special_reports.read']);
$router->get('/special-reports/create', [SpecialReportController::class, 'create'], ['auth','password','location','permission:special_reports.create']);
$router->post('/special-reports', [SpecialReportController::class, 'store'], ['auth','password','location','permission:special_reports.create']);
$router->get('/special-reports/{id}', [SpecialReportController::class, 'show'], ['auth','password','location','permission:special_reports.read']);
$router->get('/special-reports/{id}/edit', [SpecialReportController::class, 'edit'], ['auth','password','location','permission:special_reports.create']);
$router->post('/special-reports/{id}', [SpecialReportController::class, 'update'], ['auth','password','location','permission:special_reports.create']);
$router->post('/special-reports/{id}/complete', [SpecialReportController::class, 'complete'], ['auth','password','location','permission:special_reports.close']);
$router->post('/special-reports/{id}/revision/{requestId}/done', [SpecialReportController::class, 'completeRevisionRequest'], ['auth','password','location','permission:special_reports.create']);
$router->post('/special-reports/{id}/addendum', [SpecialReportController::class, 'addendum'], ['auth','password','location','permission:special_reports.close']);
$router->get('/special-reports/{id}/pdf', [SpecialReportController::class, 'pdf'], ['auth','password','location','permission:special_reports.export']);
$router->get('/special-reports/{id}/docx', [SpecialReportController::class, 'docx'], ['auth','password','location','permission:special_reports.export']);
$router->get('/special-reports/{id}/print', [SpecialReportController::class, 'printReport'], ['auth','password','location','permission:special_reports.export']);

$router->post('/special-reports/{id}/review/request', [SpecialReportReviewController::class, 'requestRevision'], ['auth','password','location','permission:special_reports.request_revision']);
$router->post('/special-reports/{id}/review/approve', [SpecialReportReviewController::class, 'approve'], ['auth','password','location','permission:special_reports.review']);

$router->get('/admin/special-reports', [SpecialReportConfigController::class, 'index'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/special-reports/type', [SpecialReportConfigController::class, 'saveType'], ['auth','password','location','permission:system.masterdata.manage']);
$router->post('/admin/special-reports/dynamic-field', [SpecialReportConfigController::class, 'saveField'], ['auth','password','location','permission:system.masterdata.manage']);
