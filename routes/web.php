<?php
declare(strict_types=1);

use WKS\Controllers\Admin\AuditController;
use WKS\Controllers\Admin\DutybookConfigController;
use WKS\Controllers\Admin\LocationController;
use WKS\Controllers\Admin\RoleController;
use WKS\Controllers\Admin\SettingsController;
use WKS\Controllers\Admin\SpecialReportConfigController;
use WKS\Controllers\Admin\UserController;
use WKS\Controllers\AttachmentController;
use WKS\Controllers\AuthController;
use WKS\Controllers\DashboardController;
use WKS\Controllers\AutosaveController;
use WKS\Controllers\DutybookController;
use WKS\Controllers\HandoverController;
use WKS\Controllers\HouseBanController;
use WKS\Controllers\GlobalSearchController;
use WKS\Controllers\StatisticsController;
use WKS\Controllers\HelpController;
use WKS\Controllers\AnnouncementController;
use WKS\Controllers\NotificationController;
use WKS\Controllers\Admin\TrashController;
use WKS\Controllers\Admin\SystemController;
use WKS\Controllers\Admin\ErrorLogController;
use WKS\Controllers\Admin\CronController;
use WKS\Controllers\Admin\MailController;
use WKS\Controllers\Admin\NotificationRuleController;
use WKS\Controllers\Admin\TemplateController;
use WKS\Controllers\Admin\UpdateController;
use WKS\Controllers\ReleaseNotesController;
use WKS\Controllers\InstallController;
use WKS\Controllers\LocationSelectionController;
use WKS\Controllers\ProfileController;
use WKS\Controllers\ShiftController;
use WKS\Controllers\SpecialReportController;
use WKS\Controllers\SpecialReportReviewController;
use WKS\Controllers\ValuablesController;

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
$router->get('/admin/audit/export.csv', [AuditController::class, 'exportCsv'], ['auth','password','location','permission:system.audit.export']);

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
$router->get('/dutybook-export.pdf', [DutybookController::class, 'exportPdf'], ['auth','password','location','permission:dutybook.export']);
$router->get('/dutybook-export.csv', [DutybookController::class, 'exportCsv'], ['auth','password','location','permission:dutybook.export']);
$router->get('/dutybook-print', [DutybookController::class, 'printDay'], ['auth','password','location','permission:dutybook.export']);
$router->post('/dutybook/archive', [DutybookController::class, 'archive'], ['auth','password','location','permission:dutybook.export']);
$router->get('/dutybook/archive/download', [DutybookController::class, 'archiveDownload'], ['auth','password','location','permission:dutybook.export']);

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


$router->get('/valuables', [ValuablesController::class, 'index'], ['auth','password','location','permission:valuables.read']);
$router->get('/valuables/search', [ValuablesController::class, 'search'], ['auth','password','location','permission:valuables.read']);
$router->get('/valuables/cassettes', [ValuablesController::class, 'cassettes'], ['auth','password','location','permission:valuables.read']);
$router->get('/valuables/long-term', [ValuablesController::class, 'longTerm'], ['auth','password','location','permission:valuables.read']);
$router->get('/valuables/check-seal', [ValuablesController::class, 'checkSeal'], ['auth','password','location','permission:valuables.store']);
$router->get('/valuables/create', [ValuablesController::class, 'create'], ['auth','password','location','permission:valuables.store']);
$router->post('/valuables', [ValuablesController::class, 'store'], ['auth','password','location','permission:valuables.store']);
$router->get('/valuables/{id}', [ValuablesController::class, 'show'], ['auth','password','location','permission:valuables.read']);
$router->get('/valuables/{id}/release', [ValuablesController::class, 'releaseForm'], ['auth','password','location','permission:valuables.release']);
$router->post('/valuables/{id}/release', [ValuablesController::class, 'release'], ['auth','password','location','permission:valuables.release']);
$router->post('/valuables/{id}/note', [ValuablesController::class, 'note'], ['auth','password','location','permission:valuables.add_note']);
$router->get('/valuables/{id}/correction', [ValuablesController::class, 'correction'], ['auth','password','location','permission:valuables.store']);
$router->get('/valuables-export.csv', [ValuablesController::class, 'exportCsv'], ['auth','password','location','permission:valuables.export']);

$router->get('/valuables/{id}/pdf', [ValuablesController::class, 'pdf'], ['auth','password','location','permission:valuables.export']);
$router->get('/valuables/{id}/print', [ValuablesController::class, 'printRecord'], ['auth','password','location','permission:valuables.export']);
$router->get('/admin/settings/valuables', [SettingsController::class, 'valuables'], ['auth','password','location','permission:system.settings.manage']);
$router->post('/admin/settings/valuables', [SettingsController::class, 'updateValuables'], ['auth','password','location','permission:system.settings.manage']);


$router->get('/house-bans', [HouseBanController::class, 'index'], ['auth','password','location','permission:house_bans.read']);
$router->get('/house-bans/create', [HouseBanController::class, 'create'], ['auth','password','location','permission:house_bans.create']);
$router->post('/house-bans', [HouseBanController::class, 'store'], ['auth','password','location','permission:house_bans.create']);
$router->get('/house-bans/{id}', [HouseBanController::class, 'show'], ['auth','password','location','permission:house_bans.read']);
$router->get('/house-bans/{id}/edit', [HouseBanController::class, 'edit'], ['auth','password','location','permission:house_bans.edit']);
$router->post('/house-bans/{id}', [HouseBanController::class, 'update'], ['auth','password','location','permission:house_bans.edit']);
$router->post('/house-bans/{id}/delete', [HouseBanController::class, 'delete'], ['auth','password','location','permission:house_bans.delete']);
$router->get('/house-bans-export.csv', [HouseBanController::class, 'exportCsv'], ['auth','password','location','permission:house_bans.export']);
$router->get('/house-bans-export.pdf', [HouseBanController::class, 'exportPdf'], ['auth','password','location','permission:house_bans.export']);


$router->get('/admin/trash', [TrashController::class, 'index'], ['auth','password','location','permission:system.trash.manage']);
$router->post('/admin/trash/{module}/{id}/move', [TrashController::class, 'move'], ['auth','password','location','permission:system.trash.manage']);
$router->post('/admin/trash/{trashId}/restore', [TrashController::class, 'restore'], ['auth','password','location','permission:system.trash.manage']);
$router->post('/admin/trash/{trashId}/delete', [TrashController::class, 'hardDelete'], ['auth','password','location','permission:system.trash.manage']);


$router->get('/announcements', [AnnouncementController::class, 'index'], ['auth','password','location','permission:messages.read']);
$router->get('/announcements/manage', [AnnouncementController::class, 'manage'], ['auth','password','location','permission:messages.manage']);
$router->get('/announcements/create', [AnnouncementController::class, 'create'], ['auth','password','location','permission:messages.manage']);
$router->post('/announcements', [AnnouncementController::class, 'store'], ['auth','password','location','permission:messages.manage']);
$router->get('/announcements/{id}', [AnnouncementController::class, 'show'], ['auth','password','location','permission:messages.read']);
$router->get('/announcements/{id}/edit', [AnnouncementController::class, 'edit'], ['auth','password','location','permission:messages.manage']);
$router->post('/announcements/{id}', [AnnouncementController::class, 'update'], ['auth','password','location','permission:messages.manage']);
$router->post('/announcements/{id}/confirm', [AnnouncementController::class, 'confirm'], ['auth','password','location','permission:messages.read']);

$router->get('/notifications', [NotificationController::class, 'index'], ['auth','password','location','permission:notifications.read']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'read'], ['auth','password','location','permission:notifications.read']);
$router->post('/notifications/read-all', [NotificationController::class, 'readAll'], ['auth','password','location','permission:notifications.read']);


$router->get('/search', [GlobalSearchController::class, 'index'], ['auth','password','location','permission:search.use']);
$router->get('/statistics', [StatisticsController::class, 'index'], ['auth','password','location','permission:statistics.view']);
$router->get('/help', [HelpController::class, 'index'], ['auth','password','location']);


$router->get('/admin/system/status', [SystemController::class, 'status'], ['auth','password','location','permission:system.status.view']);
$router->post('/admin/system/status/check', [SystemController::class, 'runCheck'], ['auth','password','location','permission:system.status.view']);
$router->post('/admin/system/maintenance', [SystemController::class, 'maintenance'], ['auth','password','location','permission:system.maintenance.manage']);

$router->get('/admin/errors', [ErrorLogController::class, 'index'], ['auth','password','location','permission:system.errors.view']);
$router->post('/admin/errors/{id}/status', [ErrorLogController::class, 'status'], ['auth','password','location','permission:system.errors.manage']);

$router->get('/admin/cron', [CronController::class, 'index'], ['auth','password','location','permission:system.cron.manage']);
$router->post('/admin/cron/run', [CronController::class, 'run'], ['auth','password','location','permission:system.cron.manage']);
$router->post('/admin/cron/{id}', [CronController::class, 'update'], ['auth','password','location','permission:system.cron.manage']);

$router->get('/admin/mail', [MailController::class, 'index'], ['auth','password','location','permission:system.mail.manage']);
$router->post('/admin/mail', [MailController::class, 'update'], ['auth','password','location','permission:system.mail.manage']);

$router->get('/admin/templates', [TemplateController::class, 'index'], ['auth','password','location','permission:system.templates.manage']);
$router->post('/admin/templates/{id}', [TemplateController::class, 'update'], ['auth','password','location','permission:system.templates.manage']);
$router->get('/admin/templates/{id}/preview', [TemplateController::class, 'preview'], ['auth','password','location','permission:system.templates.manage']);


$router->get('/admin/updates', [UpdateController::class, 'index'], ['auth','password','location','permission:system.updates.manage']);
$router->post('/admin/updates/check', [UpdateController::class, 'check'], ['auth','password','location','permission:system.updates.manage']);
$router->post('/admin/updates/install', [UpdateController::class, 'install'], ['auth','password','location','permission:system.updates.manage']);
$router->post('/admin/updates/migrate', [UpdateController::class, 'migrate'], ['auth','password','location','permission:system.updates.manage']);

$router->get('/whats-new', [ReleaseNotesController::class, 'index'], ['auth','password','location']);
$router->post('/whats-new/{version}/seen', [ReleaseNotesController::class, 'seen'], ['auth','password','location']);


$router->post('/autosave', [AutosaveController::class, 'store'], ['auth','password','location']);
$router->post('/autosave/discard', [AutosaveController::class, 'discard'], ['auth','password','location']);


$router->get('/admin/notifications', [NotificationRuleController::class, 'index'], ['auth','password','location','permission:notifications.manage']);
$router->post('/admin/notifications/rule', [NotificationRuleController::class, 'save'], ['auth','password','location','permission:notifications.manage']);

$router->get('/admin/settings/uploads', [SettingsController::class, 'uploads'], ['auth','password','location','permission:system.settings.manage']);
$router->post('/admin/settings/uploads', [SettingsController::class, 'updateUploads'], ['auth','password','location','permission:system.settings.manage']);
$router->get('/admin/settings/dashboard', [SettingsController::class, 'dashboard'], ['auth','password','location','permission:system.settings.manage']);
$router->post('/admin/settings/dashboard', [SettingsController::class, 'updateDashboard'], ['auth','password','location','permission:system.settings.manage']);
