<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Setting;
use App\Services\PdfService;
use App\Services\ReportService;

/**
 * ReportController — daily, monthly and employee-performance reports with
 * on-screen tables/charts and PDF export. Restricted to admin + manager by
 * route middleware; data is scoped per role inside ReportService.
 */
final class ReportController extends Controller
{
    public function index(): void
    {
        $this->view('reports/index', ['title' => 'Reports']);
    }

    public function daily(): void
    {
        $date = $this->validDate((string) $this->request->input('date', date('Y-m-d')), 'Y-m-d', date('Y-m-d'));
        $data = (new ReportService())->daily(Auth::user(), $date);

        $this->view('reports/daily', [
            'title'   => 'Daily Report',
            'date'    => $date,
            'rows'    => $data['rows'],
            'summary' => $data['summary'],
        ]);
    }

    public function monthly(): void
    {
        $month = $this->validDate((string) $this->request->input('month', date('Y-m')), 'Y-m', date('Y-m'));
        $data = (new ReportService())->monthly(Auth::user(), $month);

        $this->view('reports/monthly', [
            'title'   => 'Monthly Report',
            'month'   => $month,
            'rows'    => $data['rows'],
            'summary' => $data['summary'],
        ]);
    }

    public function performance(): void
    {
        $rows = (new ReportService())->performance(Auth::user());

        $this->view('reports/performance', [
            'title' => 'Employee Performance',
            'rows'  => $rows,
        ]);
    }

    /**
     * Export any report as a PDF: /reports/export?type=daily|monthly|performance
     */
    public function export(): void
    {
        $type     = (string) $this->request->input('type', 'daily');
        $service  = new ReportService();
        $settings = (new Setting())->allAsMap();
        $user     = Auth::user();
        $currency = $settings['currency_symbol'] ?? 'Rs.';
        $money    = static fn ($n) => $currency . ' ' . number_format((float) $n, 2);

        switch ($type) {
            case 'monthly':
                $month = $this->validDate((string) $this->request->input('month', date('Y-m')), 'Y-m', date('Y-m'));
                $data  = $service->monthly($user, $month);
                $headers = ['Date', 'Quotations', 'Accepted', 'Total Value'];
                $rows = array_map(static fn ($r) => [
                    $r['day'], (string) $r['count'], (string) $r['accepted'], $money($r['total']),
                ], $data['rows']);
                $title = 'Monthly Quotation Report — ' . $month;
                $meta  = ['Total Quotations' => (string) $data['summary']['count'], 'Total Value' => $money($data['summary']['total'])];
                break;

            case 'performance':
                $data = $service->performance($user);
                $headers = ['Employee', 'Role', 'Quotations', 'Accepted', 'Total Value', 'Accepted Value'];
                $rows = array_map(static fn ($r) => [
                    $r['employee'], ucfirst((string) $r['role']), (string) $r['total_count'],
                    (string) $r['accepted_count'], $money($r['total_value']), $money($r['accepted_value']),
                ], $data);
                $title = 'Employee Performance Report';
                $meta  = ['Employees' => (string) count($data)];
                break;

            case 'daily':
            default:
                $date = $this->validDate((string) $this->request->input('date', date('Y-m-d')), 'Y-m-d', date('Y-m-d'));
                $data = $service->daily($user, $date);
                $headers = ['Number', 'Customer', 'Created By', 'Total', 'Status'];
                $rows = array_map(static fn ($r) => [
                    $r['quotation_number'], $r['customer_name'], $r['created_by_name'] ?? '—',
                    $money($r['total']), ucfirst((string) $r['status']),
                ], $data['rows']);
                $title = 'Daily Quotation Report — ' . $date;
                $meta  = ['Total Quotations' => (string) $data['summary']['count'], 'Total Value' => $money($data['summary']['total'])];
                break;
        }

        $pdf = (new PdfService())->generateReport($title, $headers, $rows, $settings, $meta);
        Response::download($pdf, str_replace(' ', '_', $title) . '.pdf');
    }

    /**
     * Validate a date/month string against a format, falling back safely.
     */
    private function validDate(string $value, string $format, string $fallback): string
    {
        $dt = \DateTime::createFromFormat($format, $value);
        return ($dt && $dt->format($format) === $value) ? $value : $fallback;
    }
}
