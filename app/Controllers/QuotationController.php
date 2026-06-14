<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Setting;
use App\Services\PdfService;
use App\Services\QuotationNumberService;

/**
 * QuotationController
 *
 * Create, list, view, status-update, delete quotations and stream their PDFs.
 * All reads/writes are scoped to what the current user is permitted to see.
 */
final class QuotationController extends Controller
{
    public function index(): void
    {
        $this->view('quotations/index', [
            'title'      => 'Quotations',
            'quotations' => (new Quotation())->listForUser(Auth::user()),
        ]);
    }

    public function create(): void
    {
        $settings = new Setting();
        $this->view('quotations/create', [
            'title'        => 'New Quotation',
            'customers'    => (new Customer())->allWithCreator(),
            'plans'        => (new Plan())->active(),
            'defaultTerms' => $settings->get('default_terms'),
            'taxRate'      => (float) $settings->get('tax_rate', '0'),
            'selectedCustomer' => (int) $this->request->input('customer_id', 0),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $input = $this->request->all();
        $items = $this->sanitiseItems($input['items'] ?? []);

        $validator = new Validator($input, [
            'customer_id' => 'required|integer',
            'expiry_date' => 'date',
        ]);

        if ($validator->fails() || $items === []) {
            $errors = $validator->flatErrors();
            if ($items === []) {
                $errors[] = 'At least one valid line item is required.';
            }
            $this->back('/quotations/create', $errors, ['customer_id' => $input['customer_id'] ?? '']);
            return;
        }

        // Compute monetary totals on the server (never trust client values).
        $subtotal = 0.0;
        foreach ($items as &$item) {
            $item['line_total'] = round($item['quantity'] * $item['unit_price'], 2);
            $subtotal += $item['line_total'];
        }
        unset($item);

        $discount = max(0.0, (float) ($input['discount'] ?? 0));
        $taxRate  = max(0.0, (float) ($input['tax_rate'] ?? 0));
        $taxable  = max($subtotal - $discount, 0);
        $tax      = round($taxable * ($taxRate / 100), 2);
        $total    = round($taxable + $tax, 2);

        $numberService = new QuotationNumberService();

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $quotationModel = new Quotation();
            $quotationId = $quotationModel->create([
                'quotation_number'   => $numberService->next(),
                'customer_id'        => (int) $input['customer_id'],
                'created_by'         => Auth::id(),
                'subtotal'           => $subtotal,
                'discount'           => $discount,
                'tax'                => $tax,
                'total'              => $total,
                'notes'              => trim((string) ($input['notes'] ?? '')),
                'terms'              => trim((string) ($input['terms'] ?? '')),
                'expiry_date'        => !empty($input['expiry_date']) ? $input['expiry_date'] : null,
                'status'             => in_array($input['status'] ?? 'draft', ['draft', 'sent'], true) ? $input['status'] : 'draft',
                'verification_token' => $numberService->token(),
            ]);

            (new QuotationItem())->replaceForQuotation($quotationId, $items);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            Flash::error('Could not save the quotation. Please try again.');
            $this->back('/quotations/create');
            return;
        }

        ActivityLog::log('create', 'quotation', $quotationId, 'Created quotation ' . ($input['customer_id'] ?? ''));
        Flash::success('Quotation created successfully.');
        $this->redirect('/quotations/' . $quotationId);
    }

    public function show(string $id): void
    {
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        $settings = new Setting();
        $verifyUrl = url('/verify/' . $quotation['verification_token']);

        $this->view('quotations/show', [
            'title'     => $quotation['quotation_number'],
            'quotation' => $quotation,
            'items'     => (new QuotationItem())->forQuotation((int) $id),
            'verifyUrl' => $verifyUrl,
        ]);
    }

    public function pdf(string $id): void
    {
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        $items    = (new QuotationItem())->forQuotation((int) $id);
        $settings = (new Setting())->allAsMap();
        $verifyUrl = url('/verify/' . $quotation['verification_token']);

        $pdf = (new PdfService())->generateQuotation($quotation, $items, $settings, $verifyUrl);

        ActivityLog::log('download', 'quotation', (int) $id, 'Downloaded quotation PDF');
        Response::download($pdf, $quotation['quotation_number'] . '.pdf');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        $status = (string) $this->request->input('status', '');
        $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        if (!in_array($status, $allowed, true)) {
            Flash::error('Invalid status.');
            $this->redirect('/quotations/' . (int) $id);
            return;
        }

        (new Quotation())->update((int) $id, ['status' => $status]);
        ActivityLog::log('update', 'quotation', (int) $id, 'Changed status to ' . $status);
        Flash::success('Status updated to ' . ucfirst($status) . '.');
        $this->redirect('/quotations/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        (new Quotation())->delete((int) $id); // items cascade via FK
        ActivityLog::log('delete', 'quotation', (int) $id, 'Deleted quotation ' . $quotation['quotation_number']);
        Flash::success('Quotation deleted.');
        $this->redirect('/quotations');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Fetch a quotation and enforce per-role access; handles the redirect
     * and returns null when not found / not permitted.
     *
     * @return array<string,mixed>|null
     */
    private function findAccessible(int $id): ?array
    {
        $model = new Quotation();
        $quotation = $model->findDetailed($id);

        if ($quotation === null) {
            Flash::error('Quotation not found.');
            $this->redirect('/quotations');
            return null;
        }

        if (!$model->userCanAccess(Auth::user(), $quotation)) {
            Response::html(\App\Core\View::render('errors/403', [], 'layouts/app'), 403);
            exit;
        }

        return $quotation;
    }

    /**
     * Normalise and filter posted line items into a clean array.
     *
     * @param mixed $raw
     * @return array<int,array<string,mixed>>
     */
    private function sanitiseItems(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $description = trim((string) ($row['description'] ?? ''));
            $quantity    = (float) ($row['quantity'] ?? 0);
            $unitPrice   = (float) ($row['unit_price'] ?? 0);

            if ($description === '' || $quantity <= 0) {
                continue;
            }

            $items[] = [
                'plan_id'     => !empty($row['plan_id']) ? (int) $row['plan_id'] : null,
                'description' => mb_substr($description, 0, 500),
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'line_total'  => 0.0, // recomputed in store()
            ];
        }

        return $items;
    }
}
