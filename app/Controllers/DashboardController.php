<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\User;

/**
 * DashboardController — role-aware overview with statistics and charts.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();

        $quotations = new Quotation();
        $stats      = $quotations->statsForUser($user);
        $byStatus   = $quotations->countByStatus($user);
        $trend      = $quotations->monthlyTrend($user, 6);
        $recent     = $quotations->recentForUser($user, 6);

        $conditions = [];

        if (Auth::isExecutive() || Auth::isManager()) {
            $conditions['created_by'] = Auth::id();
        }
        // Secondary counts (role-aware where relevant).
        $customerCount = (new Customer())->count($conditions);
        $planCount     = (new Plan())->count(['status' => 'active']);

        $teamCount = 0;
        if (Auth::isAdmin()) {
            $teamCount = (new User())->count() ;
        } elseif (Auth::isManager()) {
            $teamCount = count((new User())->executiveIdsForManager((int) $user['id']));
        }

        $this->view('dashboard/index', [
            'title'         => 'Dashboard',
            'stats'         => $stats,
            'byStatus'      => $byStatus,
            'trend'         => $trend,
            'recent'        => $recent,
            'customerCount' => $customerCount,
            'planCount'     => $planCount,
            'teamCount'     => $teamCount,
        ]);
    }
}
