<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Incidents\EscalateSlaIncidentsAction;
use Illuminate\Console\Command;

final class CheckSlaEscalationsCommand extends Command
{
    protected $signature = 'civicfix:check-sla';

    protected $description = 'Escala a CRÍTICA las incidencias de alta prioridad sin atención después del SLA';

    public function handle(EscalateSlaIncidentsAction $action): int
    {
        $escalated = $action->execute();

        $this->info(sprintf('Incidencias escaladas: %d', $escalated));

        return Command::SUCCESS;
    }
}
