<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $user->tenant_id === $invoice->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update') && $user->tenant_id === $invoice->tenant_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.delete') && $user->tenant_id === $invoice->tenant_id;
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send') && $user->tenant_id === $invoice->tenant_id;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.void') && $user->tenant_id === $invoice->tenant_id;
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.record') && $user->tenant_id === $invoice->tenant_id;
    }

    public function deletePayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.delete') && $user->tenant_id === $invoice->tenant_id;
    }
}
