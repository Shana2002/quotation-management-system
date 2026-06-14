<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Quotation;

/**
 * CustomerController — CRUD plus per-customer quotation history.
 */
final class CustomerController extends Controller
{
    public function index(): void
    {
        $term      = (string) $this->request->input('q', '');
        $model     = new Customer();
        $customers = $term !== '' ? $model->search($term) : $model->allWithCreator();

        $this->view('customers/index', [
            'title'     => 'Customers',
            'customers' => $customers,
            'term'      => $term,
        ]);
    }

    public function create(): void
    {
        $this->view('customers/form', [
            'title'    => 'New Customer',
            'customer' => null,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->validateCustomer(null);
        if ($data === null) {
            $this->back('/customers/create');
            return;
        }

        $data['created_by'] = Auth::id();
        $id = (new Customer())->create($data);
        ActivityLog::log('create', 'customer', $id, 'Created customer: ' . $data['name']);
        Flash::success('Customer created successfully.');
        $this->redirect('/customers');
    }

    public function show(string $id): void
    {
        $customer = (new Customer())->find((int) $id);
        if ($customer === null) {
            Flash::error('Customer not found.');
            $this->redirect('/customers');
            return;
        }

        // Quotation history (scoped to what the current user may see).
        $all  = (new Quotation())->forCustomer((int) $id);
        $user = Auth::user();
        $history = array_values(array_filter(
            $all,
            static fn ($q) => (new Quotation())->userCanAccess($user, $q)
        ));

        $this->view('customers/show', [
            'title'    => $customer['name'],
            'customer' => $customer,
            'history'  => $history,
        ]);
    }

    public function edit(string $id): void
    {
        $customer = (new Customer())->find((int) $id);
        if ($customer === null) {
            Flash::error('Customer not found.');
            $this->redirect('/customers');
            return;
        }

        $this->view('customers/form', [
            'title'    => 'Edit Customer',
            'customer' => $customer,
        ]);
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $customer = (new Customer())->find((int) $id);
        if ($customer === null) {
            Flash::error('Customer not found.');
            $this->redirect('/customers');
            return;
        }

        $data = $this->validateCustomer((int) $id);
        if ($data === null) {
            $this->back('/customers/' . (int) $id . '/edit');
            return;
        }

        (new Customer())->update((int) $id, $data);
        ActivityLog::log('update', 'customer', (int) $id, 'Updated customer: ' . $data['name']);
        Flash::success('Customer updated successfully.');
        $this->redirect('/customers/' . (int) $id);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        try {
            (new Customer())->delete((int) $id);
            ActivityLog::log('delete', 'customer', (int) $id, 'Deleted customer');
            Flash::success('Customer deleted.');
        } catch (\Throwable $e) {
            Flash::error('Cannot delete this customer because they have existing quotations.');
        }
        $this->redirect('/customers');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function validateCustomer(?int $ignoreId): ?array
    {
        $input = $this->request->only(['name', 'address', 'telephone', 'nic', 'email']);

        $uniqueRule = 'required|max:30|unique:customers,nic';
        if ($ignoreId !== null) {
            $uniqueRule .= ',' . $ignoreId;
        }

        $validator = new Validator($input, [
            'name'      => 'required|max:150',
            'nic'       => $uniqueRule,
            'email'     => 'email|max:190',
            'telephone' => 'max:40',
        ]);

        if ($validator->fails()) {
            Session::set('errors', $validator->flatErrors());
            Session::set('old', $input);
            return null;
        }

        return [
            'name'      => $input['name'],
            'address'   => $input['address'] ?? '',
            'telephone' => $input['telephone'] ?? '',
            'nic'       => $input['nic'],
            'email'     => $input['email'] ?? '',
        ];
    }
}
