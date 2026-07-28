<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerNoteRequest;
use App\Http\Resources\CustomerNoteResource;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Services\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerNoteController extends Controller
{
    use ApiResponse;

    public function __construct(protected CustomerService $customers)
    {
    }

    public function store(StoreCustomerNoteRequest $request, Customer $customer): JsonResponse
    {
        $note = $this->customers->addNote($customer, $request->string('note')->toString(), $request->user());

        return $this->created(new CustomerNoteResource($note->load('user')), 'Note added successfully.');
    }

    public function destroy(Customer $customer, CustomerNote $note): JsonResponse
    {
        $this->authorize('update', $customer);
        abort_unless($note->customer_id === $customer->id, 404);

        $this->customers->deleteNote($note);

        return $this->noContent('Note deleted successfully.');
    }
}
