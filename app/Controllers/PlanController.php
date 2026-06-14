<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Plan;

/**
 * PlanController — CRUD for sellable plans. Reads are open to all roles
 * (so quotations can reference plans); writes are admin-only (enforced
 * by route middleware).
 */
final class PlanController extends Controller
{
    public function index(): void
    {
        $this->view('plans/index', [
            'title' => 'Plans',
            'plans' => (new Plan())->all('name', 'ASC'),
        ]);
    }

    public function create(): void
    {
        $this->view('plans/form', [
            'title' => 'New Plan',
            'plan'  => null,
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
     * Validate plan input; returns the clean data or null on failure
     * (errors + old input are flashed by the caller via back()).
     *
     * @return array<string,mixed>|null
     */
    private function validatePlan(): ?array
    {
        $input = $this->request->only(['name', 'description', 'amount', 'status']);
        $validator = new Validator($input, [
            'name'   => 'required|max:150',
            'amount' => 'required|numeric',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            \App\Core\Session::set('errors', $validator->flatErrors());
            \App\Core\Session::set('old', $input);
            return null;
        }

        return [
            'name'        => $input['name'],
            'description' => $input['description'] ?? '',
            'amount'      => (float) $input['amount'],
            'status'      => $input['status'],
        ];
    }
}
