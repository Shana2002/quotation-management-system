<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\PlanTypes\PlanTypeRegistry;

/**
 * PlanController — CRUD for OXIAURA's plan-type products. Reads are open to all
 * roles (so quotations can reference plans); writes are admin-only (enforced by
 * route middleware). Each plan carries a plan_type, an editable `parameters`
 * JSON (rates/prices), and `benefits` text.
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
     * shape matches the chosen plan type.
     *
     * @return array<string,mixed>|null
     */
    private function validatePlan(): ?array
    {
        $input = $this->request->only(['name', 'plan_type', 'description', 'benefits', 'parameters', 'status']);

        $validator = new Validator($input, [
            'name'      => 'required|max:150',
            'plan_type' => 'required',
            'status'    => 'required|in:active,inactive',
        ]);

        $errors = $validator->flatErrors();

        if (PlanTypeRegistry::get((string) $input['plan_type']) === null) {
            $errors[] = 'Unknown plan type.';
        }

        // Parameters must be valid JSON (empty allowed → {}).
        $params = trim((string) ($input['parameters'] ?? ''));
        if ($params !== '') {
            json_decode($params);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Parameters must be valid JSON: ' . json_last_error_msg();
            }
        }

        if ($errors !== []) {
            Session::set('errors', $errors);
            Session::set('old', $input);
            return null;
        }

        return [
            'name'        => $input['name'],
            'plan_type'   => $input['plan_type'],
            'description' => $input['description'] ?? '',
            'amount'      => 0,
            'parameters'  => $params !== '' ? $params : '{}',
            'benefits'    => $input['benefits'] ?? '',
            'status'      => $input['status'],
        ];
    }
}
