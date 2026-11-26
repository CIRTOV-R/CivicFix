<?php

declare(strict_types=1);

use App\Actions\Incidents\EscalateSlaIncidentsAction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn (EscalateSlaIncidentsAction $action): int => $action->execute())
    ->hourly()
    ->name('civicfix:sla-escalation')
    ->withoutOverlapping()
    ->onOneServer();
