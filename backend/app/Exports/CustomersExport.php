<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected array $filters = [])
    {
    }

    public function collection(): Collection
    {
        return Customer::query()
            ->with('tags')
            ->when($this->filters['search'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%");
            }))
            ->when(array_key_exists('is_favorite', $this->filters) && $this->filters['is_favorite'] !== null, fn ($q) => $q->where('is_favorite', filter_var($this->filters['is_favorite'], FILTER_VALIDATE_BOOLEAN)))
            ->when(array_key_exists('is_blacklisted', $this->filters) && $this->filters['is_blacklisted'] !== null, fn ($q) => $q->where('is_blacklisted', filter_var($this->filters['is_blacklisted'], FILTER_VALIDATE_BOOLEAN)))
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Phone', 'Address', 'Birthday', 'Gender', 'Tags', 'Favorite', 'Blacklisted'];
    }

    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->email,
            $customer->phone,
            $customer->address,
            $customer->birthday?->format('Y-m-d'),
            $customer->gender?->value,
            $customer->tags->pluck('name')->implode(', '),
            $customer->is_favorite ? 'Yes' : 'No',
            $customer->is_blacklisted ? 'Yes' : 'No',
        ];
    }
}
