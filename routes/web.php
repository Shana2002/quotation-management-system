<?php

declare(strict_types=1);

/**
 * Application routes.
 *
 * Each route maps an HTTP method + path to [Controller::class, 'method'] with
 * an optional middleware list:
 *   'auth'                -> must be logged in
 *   'role:admin'          -> must hold one of the comma-separated roles
 *
 * @var \App\Core\Router $router  (provided by App::loadRoutes)
 */

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\PlanController;
use App\Controllers\QuotationController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\UserController;
use App\Controllers\VerifyController;

/* ------------------------------------------------------------------ */
/* Public                                                              */
/* ------------------------------------------------------------------ */
$router->get('/',        [AuthController::class, 'showLogin']);
$router->get('/login',   [AuthController::class, 'showLogin']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// Public quotation verification (QR target).
$router->get('/verify/{token}', [VerifyController::class, 'show']);

/* ------------------------------------------------------------------ */
/* Dashboard (all authenticated roles)                                */
/* ------------------------------------------------------------------ */
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

/* ------------------------------------------------------------------ */
/* User management (managers + executives)                            */
/*   Admin: full. Manager: own executives only.                       */
/* ------------------------------------------------------------------ */
$router->get('/users',             [UserController::class, 'index'],   ['auth', 'role:admin,manager']);
$router->get('/users/create',      [UserController::class, 'create'],  ['auth', 'role:admin,manager']);
$router->post('/users',            [UserController::class, 'store'],   ['auth', 'role:admin,manager']);
$router->get('/users/{id}/edit',   [UserController::class, 'edit'],    ['auth', 'role:admin,manager']);
$router->post('/users/{id}',       [UserController::class, 'update'],  ['auth', 'role:admin,manager']);
$router->post('/users/{id}/delete',[UserController::class, 'destroy'], ['auth', 'role:admin,manager']);

/* ------------------------------------------------------------------ */
/* Customers (all authenticated roles)                                */
/* ------------------------------------------------------------------ */
$router->get('/customers',             [CustomerController::class, 'index'],   ['auth']);
$router->get('/customers/create',      [CustomerController::class, 'create'],  ['auth']);
$router->post('/customers',            [CustomerController::class, 'store'],   ['auth']);
$router->get('/customers/{id}',        [CustomerController::class, 'show'],    ['auth']);
$router->get('/customers/{id}/edit',   [CustomerController::class, 'edit'],    ['auth']);
$router->post('/customers/{id}',       [CustomerController::class, 'update'],  ['auth']);
$router->post('/customers/{id}/delete',[CustomerController::class, 'destroy'], ['auth', 'role:admin,manager']);

/* ------------------------------------------------------------------ */
/* Plans (admin only for writes; all can read for quotations)         */
/* ------------------------------------------------------------------ */
$router->get('/plans',             [PlanController::class, 'index'],   ['auth']);
$router->get('/plans/create',      [PlanController::class, 'create'],  ['auth', 'role:admin']);
$router->post('/plans',            [PlanController::class, 'store'],   ['auth', 'role:admin']);
$router->get('/plans/{id}/edit',   [PlanController::class, 'edit'],    ['auth', 'role:admin']);
$router->post('/plans/{id}',       [PlanController::class, 'update'],  ['auth', 'role:admin']);
$router->post('/plans/{id}/delete',[PlanController::class, 'destroy'], ['auth', 'role:admin']);

/* ------------------------------------------------------------------ */
/* Quotations (all authenticated roles; data scoped per role)         */
/* ------------------------------------------------------------------ */
$router->get('/quotations',            [QuotationController::class, 'index'],  ['auth']);
$router->get('/quotations/create',     [QuotationController::class, 'create'], ['auth']);
$router->post('/quotations',           [QuotationController::class, 'store'],  ['auth']);
$router->get('/quotations/{id}',       [QuotationController::class, 'show'],   ['auth']);
$router->get('/quotations/{id}/pdf',   [QuotationController::class, 'pdf'],    ['auth']);
$router->post('/quotations/{id}/status',[QuotationController::class, 'updateStatus'], ['auth']);
$router->post('/quotations/{id}/delete',[QuotationController::class, 'destroy'], ['auth', 'role:admin,manager']);

/* ------------------------------------------------------------------ */
/* Reports (admin + manager)                                          */
/* ------------------------------------------------------------------ */
$router->get('/reports',             [ReportController::class, 'index'],       ['auth', 'role:admin,manager']);
$router->get('/reports/daily',       [ReportController::class, 'daily'],       ['auth', 'role:admin,manager']);
$router->get('/reports/monthly',     [ReportController::class, 'monthly'],     ['auth', 'role:admin,manager']);
$router->get('/reports/performance', [ReportController::class, 'performance'], ['auth', 'role:admin,manager']);
$router->get('/reports/export',      [ReportController::class, 'export'],      ['auth', 'role:admin,manager']);

/* ------------------------------------------------------------------ */
/* Settings + Activity logs (admin only)                              */
/* ------------------------------------------------------------------ */
$router->get('/settings',  [SettingController::class, 'index'],  ['auth', 'role:admin']);
$router->post('/settings', [SettingController::class, 'update'], ['auth', 'role:admin']);

$router->get('/logs',       [ActivityLogController::class, 'index'], ['auth', 'role:admin']);
$router->get('/logs/login', [ActivityLogController::class, 'login'], ['auth', 'role:admin']);
