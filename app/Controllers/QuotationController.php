<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\Setting;
use App\PlanTypes\PlanTypeRegistry;
use App\Services\PdfService;
use App\Services\QuotationNumberService;

/**
 * QuotationController
 *
 * Plan-type quotations: the operator picks a customer + an OXIAURA plan, fills
 * the plan-specific inputs, and the system computes a projection (intro + table
 * + summary) that is stored as JSON and rendered both on screen and as a
 * letter-style PDF. All reads/writes are scoped per role.
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
        $plans = (new Plan())->active();

        // Pre-build each plan's input-field schema (crop options depend on params).
        $planForms = [];
        foreach ($plans as $plan) {
            $type = PlanTypeRegistry::get($plan['plan_type']);
            if ($type === null) {
                continue;
            }
            $planForms[$plan['id']] = [
                'type_label' => $type->label(),
                'note'       => $type->formulaNote(),
                'fields'     => $type->inputFields(Plan::parameters($plan)),
            ];
        }

        $this->view('quotations/create', [
            'title'            => 'New Quotation',
            'customers'        => (new Customer())->allWithCreator(),
            'plans'            => $plans,
            'planForms'        => $planForms,
            'selectedCustomer' => (int) $this->request->input('customer_id', 0),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $input = $this->request->all();

        $validator = new Validator($input, [
            'customer_id' => 'required|integer',
            'plan_id'     => 'required|integer',
            'expiry_date' => 'date',
        ]);

        $plan = (new Plan())->find((int) ($input['plan_id'] ?? 0));
        $type = $plan !== null ? PlanTypeRegistry::get($plan['plan_type']) : null;

        $errors = $validator->flatErrors();
        if ($plan === null || $type === null) {
            $errors[] = 'Please select a valid plan.';
        }

        if ($errors !== []) {
            $this->back('/quotations/create', $errors, ['customer_id' => $input['customer_id'] ?? '']);
            return;
        }

        // Capture only the inputs declared by this plan type.
        $params = Plan::parameters($plan);
        $inputs = [];
        foreach ($type->inputFields($params) as $field) {
            $inputs[$field['name']] = $input[$field['name']] ?? null;
        }

        $inputErrors = $type->validate($inputs);
        if ($inputErrors !== []) {
            $this->back('/quotations/create', $inputErrors, ['customer_id' => $input['customer_id']]);
            return;
        }

        // Compute the projection and enrich it with self-contained render data
        // (label, title, benefits snapshot) so historical PDFs never change.
        $projection = $type->compute($inputs, $params);
        $projection['plan_label']   = $type->label();
        $projection['letter_title'] = $type->letterTitle();
        $projection['benefits']     = (string) ($plan['benefits'] ?? '');

        $headline = (float) ($projection['headline_amount'] ?? 0);
        $numberService = new QuotationNumberService();

        $quotationId = (new Quotation())->create([
            'quotation_number'   => $numberService->next(),
            'customer_id'        => (int) $input['customer_id'],
            'created_by'         => Auth::id(),
            'plan_id'            => (int) $plan['id'],
            'plan_type'          => $plan['plan_type'],
            'inputs'             => json_encode($inputs, JSON_UNESCAPED_UNICODE),
            'projection'         => json_encode($projection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subtotal'           => $headline,
            'discount'           => 0,
            'tax'                => 0,
            'total'              => $headline,
            'notes'              => trim((string) ($input['notes'] ?? '')),
            'terms'              => trim((string) ($input['terms'] ?? '')),
            'expiry_date'        => !empty($input['expiry_date']) ? $input['expiry_date'] : null,
            'status'             => in_array($input['status'] ?? 'draft', ['draft', 'sent'], true) ? $input['status'] : 'draft',
            'verification_token' => $numberService->token(),
        ]);

        ActivityLog::log('create', 'quotation', $quotationId, 'Created ' . $type->label() . ' quotation');
        Flash::success('Quotation created successfully.');
        $this->redirect('/quotations/' . $quotationId);
    }

    public function show(string $id): void
    {
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        $this->view('quotations/show', [
            'title'      => $quotation['quotation_number'],
            'quotation'  => $quotation,
            'projection' => Quotation::projection($quotation),
            'verifyUrl'  => url('/verify/' . $quotation['verification_token']),
        ]);
    }

    public function pdf(string $id): void
    {
        $quotation = $this->findAccessible((int) $id);
        if ($quotation === null) {
            return;
        }

        $settings   = (new Setting())->allAsMap();
        $projection = Quotation::projection($quotation);
        $verifyUrl  = url('/verify/' . $quotation['verification_token']);

        $pdf = (new PdfService())->generateQuotation($quotation, $projection, $settings, $verifyUrl);

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

        $status  = (string) $this->request->input('status', '');
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

        (new Quotation())->delete((int) $id);
        ActivityLog::log('delete', 'quotation', (int) $id, 'Deleted quotation ' . $quotation['quotation_number']);
        Flash::success('Quotation deleted.');
        $this->redirect('/quotations');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Fetch a quotation and enforce per-role access; handles the redirect/abort
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
}
