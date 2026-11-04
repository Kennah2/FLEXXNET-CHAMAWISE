<?php

use App\Http\Middleware\SetPermissionsTeamFromOrganization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetPermissionsTeamFromOrganization::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Shared cPanel hosting typically offers one cron entry calling `schedule:run` every
        // minute (see docs/SHARED_HOSTING_DEPLOYMENT.md) — individual job timing lives here,
        // not in the crontab. Runs daily shortly after midnight Africa/Nairobi so that a
        // chama's overdue status and penalties stay current even if nobody visits its
        // contributions page that day.
        $schedule->command('contributions:assess')
            ->dailyAt('00:10')
            ->timezone('Africa/Nairobi')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
