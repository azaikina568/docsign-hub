<?php

namespace App\Console\Commands;

use App\Domain\Documents\Actions\ExpireDocumentsAction;
use Illuminate\Console\Command;

class ExpireDocuments extends Command
{
    protected $signature = 'documents:expire';

    protected $description = 'Mark pending or partially signed documents past their expiry date as expired.';

    public function handle(ExpireDocumentsAction $action): int
    {
        $count = $action->execute();

        $this->info("Expired {$count} document(s).");

        return self::SUCCESS;
    }
}
