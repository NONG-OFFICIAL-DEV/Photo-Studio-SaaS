<?php

namespace App\Imports;

use App\Enums\CustomerGender;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Row-by-row customer import. Invalid rows are skipped and collected via
 * SkipsOnFailure (see failures()) rather than aborting the whole file —
 * a studio importing 500 contacts from an old spreadsheet shouldn't lose
 * the 495 good rows because of 5 bad ones.
 */
class CustomersImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use Importable, SkipsFailures;

    protected int $imported = 0;

    public function __construct(protected ?User $creator = null)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            if (blank($row['name'] ?? null)) {
                continue;
            }

            Customer::create([
                'name' => $row['name'],
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'address' => $row['address'] ?? null,
                'birthday' => $row['birthday'] ?? null,
                'gender' => $this->normalizeGender($row['gender'] ?? null),
                'created_by' => $this->creator?->id,
            ]);

            $this->imported++;
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female,other'],
        ];
    }

    public function importedCount(): int
    {
        return $this->imported;
    }

    protected function normalizeGender(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return CustomerGender::tryFrom($normalized)?->value;
    }
}
