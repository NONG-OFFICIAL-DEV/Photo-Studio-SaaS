<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Order::query()->with('customer')->latest()->get();
    }

    public function headings(): array
    {
        return ['Customer', 'Status', 'Subtotal', 'Discount', 'Total', 'Created At'];
    }

    public function map($order): array
    {
        return [
            $order->customer?->name,
            $order->status?->value,
            $order->subtotal,
            $order->discount_amount,
            $order->total,
            $order->created_at?->format('Y-m-d'),
        ];
    }
}
