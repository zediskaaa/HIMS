<?php

namespace App\Rules;

use App\Models\Supplier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProcurementEligibleSupplier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $supplier = Supplier::find($value);

        if ($supplier === null || ! $supplier->isProcurementEligible()) {
            $fail('The selected supplier is not currently approved and compliant for new procurement.');
        }
    }
}
