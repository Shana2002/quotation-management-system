<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LoginActivity;

/**
 * ActivityLogController — admin views of the audit trail and login history.
 */
final class ActivityLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $page   = max(1, (int) $this->request->input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $model = new ActivityLog();
        $logs  = $model->paginate(self::PER_PAGE, $offset);
        $total = $model->count();

        $this->view('logs/index', [
            'title'   => 'Activity Logs',
            'logs'    => $logs,
            'page'    => $page,
            'perPage' => self::PER_PAGE,
            'total'   => $total,
        ]);
    }

    public function login(): void
    {
        $this->view('logs/login', [
            'title'    => 'Login Activity',
            'attempts' => (new LoginActivity())->recent(150),
        ]);
    }
}
