<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Placeholder;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\PlanTypes\PlanTypeRegistry;

/**
 * PlanController — CRUD for OXIAURA's plan-type products. Reads are open to all
 * roles (so quotations can reference plans); writes are admin-only (enforced by
 * route middleware). Each plan carries a plan_type, an editable `parameters`
 * JSON (rates/prices), and the `${token}` letter templates for its Investment
 * Summary and Terms & Conditions.
 */
final class PlanController extends Controller
{
    public function index(): void
    {
        $this->view('plans/index', [
            'title'   => 'Plans',
            'plans'   => (new Plan())->all('name', 'ASC'),
            'typeMap' => PlanTypeRegistry::options(),
        ]);
    }

    public function create(): void
    {
        $this->view('plans/form', [
            'title' => 'New Plan',
            'plan'  => null,
            'types' => PlanTypeRegistry::all(),
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->validatePlan();
        if ($data === null) {
            $this->back('/plans/create');
            return;
        }

        $id = (new Plan())->create($data);
        ActivityLog::log('create', 'plan', $id, 'Created plan: ' . $data['name']);
        Flash::success('Plan created successfully.');
        $this->redirect('/plans');
    }

    public function edit(string $id): void
    {
        $plan = (new Plan())->find((int) $id);
        if ($plan === null) {
            Flash::error('Plan not found.');
            $this->redirect('/plans');
            return;
        }

        $this->view('plans/form', [
            'title' => 'Edit Plan',
            'plan'  => $plan,
            'types' => PlanTypeRegistry::all(),
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $plan = (new Plan())->find((int) $id);
        if ($plan === null) {
            Flash::error('Plan not found.');
            $this->redirect('/plans');
            return;
        }

        $data = $this->validatePlan();
        if ($data === null) {
            $this->back('/plans/' . (int) $id . '/edit');
            return;
        }

        (new Plan())->update((int) $id, $data);
        ActivityLog::log('update', 'plan', (int) $id, 'Updated plan: ' . $data['name']);
        Flash::success('Plan updated successfully.');
        $this->redirect('/plans');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        try {
            (new Plan())->delete((int) $id);
            ActivityLog::log('delete', 'plan', (int) $id, 'Deleted plan');
            Flash::success('Plan deleted.');
        } catch (\Throwable $e) {
            Flash::error('Cannot delete this plan because it is referenced by quotations. Set it to inactive instead.');
        }
        $this->redirect('/plans');
    }

    /**
     * Validate plan input. The `parameters` field must be valid JSON whose
     * shape matches the chosen plan type, and the summary/terms templates may
     * only reference `${token}`s that plan type can actually supply.
     *
     * @return array<string,mixed>|null
     */
    private function validatePlan(): ?array
    {
        $input = $this->request->only([
            'name', 'plan_type', 'description', 'parameters', 'status',
            'summary_template', 'terms_template',
        ]);

        $validator = new Validator($input, [
            'name'      => 'required|max:150',
            'plan_type' => 'required',
            'status'    => 'required|in:active,inactive',
        ]);

        $errors = $validator->flatErrors();

        $type = PlanTypeRegistry::get((string) $input['plan_type']);
        if ($type === null) {
            $errors[] = 'Unknown plan type.';
        }

        // Parameters must be valid JSON (empty allowed → {}).
        $paramsJson = trim((string) ($input['parameters'] ?? ''));
        $params     = [];
        if ($paramsJson !== '') {
            $decoded = json_decode($paramsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Parameters must be valid JSON: ' . json_last_error_msg();
            } elseif (is_array($decoded)) {
                $params = $decoded;
            }
        }

        // Reject ${token}s the plan type cannot resolve — far better to catch a
        // typo here than to print "${amunt}" on a customer's letter.
        if ($type !== null) {
            try {
                $available = $type->availableTokens($params);
            } catch (\Throwable $e) {
                // A plan type that cannot be sampled (odd parameters) skips the
                // check rather than flagging every token as unknown.
                $available = null;
            }

            if ($available !== null) {
                foreach (['summary_template' => 'Investment Summary', 'terms_template' => 'Terms & Conditions'] as $field => $label) {
                    $unknown = Placeholder::unknown((string) ($input[$field] ?? ''), $available);
                    if ($unknown !== []) {
                        $quoted = implode(', ', array_map(static fn (string $t): string => '${' . $t . '}', $unknown));
                        $errors[] = $label . ' uses placeholder(s) this plan type cannot fill: ' . $quoted . '.';
                    }
                }
            }
        }

        if ($errors !== []) {
            Session::set('errors', $errors);
            Session::set('old', $input);
            return null;
        }

        return [
            'name'             => $input['name'],
            'plan_type'        => $input['plan_type'],
            'description'      => $input['description'] ?? '',
            'amount'           => 0,
            'parameters'       => $paramsJson !== '' ? $paramsJson : '{}',
            'summary_template' => (string) ($input['summary_template'] ?? ''),
            'terms_template'   => (string) ($input['terms_template'] ?? ''),
            'status'           => $input['status'],
        ];
    }
}
