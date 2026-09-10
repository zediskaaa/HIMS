<?php

namespace App\Console\Commands;

use App\Services\SupplierComplianceAlertService;
use Illuminate\Console\Command;

class CheckSupplierCompliance extends Command
{
    protected $signature = 'suppliers:check-compliance';

    protected $description = 'Raise or resolve supplier accreditation, document, and contract expiry alerts';

    public function handle(SupplierComplianceAlertService $alerts): int
    {
        $result = $alerts->sweep();

        $this->info(sprintf(
            'Supplier compliance checked: %d active, %d resolved.',
            $result['active'],
            $result['resolved'],
        ));

        return self::SUCCESS;
    }
}
