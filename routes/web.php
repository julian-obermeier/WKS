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
