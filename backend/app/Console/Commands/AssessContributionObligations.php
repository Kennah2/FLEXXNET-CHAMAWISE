<?php

namespace App\Console\Commands;

use App\Models\ContributionSchedule;
use App\Services\Contributions\ObligationGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Generates any obligation rows that should now exist, and assesses overdue status / penalties,
 * for every active contribution schedule across every chama.
 *
 * Obligations are also generated lazily whenever someone views a chama's contributions page or
 * verifies a payment, so this command is a backstop rather than the only path — per
 * docs/CPANEL_CONFIGURATION.md, shared cPanel hosting cron cannot be assumed to fire at an exact
 * moment, so a chama with nobody actively looking at it still needs its overdue status and
 * penalties kept current. Registered to run daily at 00:10 Africa/Nairobi in
 * bootstrap/app.php's withSchedule() — see docs/CPANEL_CONFIGURATION.md for the one cron entry
 * the host itself needs (schedule:run every minute).
 */
class AssessContributionObligations extends Command
{
    protected $signature = 'contributions:assess';

    protected $description = "Generate due contribution obligations and assess overdue status/penalties for every chama's active schedules";

    public function handle(ObligationGenerator $generator): int
    {
        $now = CarbonImmutable::now();
        $count = 0;

        ContributionSchedule::where('status', ContributionSchedule::STATUS_ACTIVE)
            ->chunkById(50, function ($schedules) use ($generator, $now, &$count) {
                foreach ($schedules as $schedule) {
                    $generator->generate($schedule, $now);
                    $count++;
                }
            });

        $this->info("Assessed {$count} active contribution schedule(s).");

        return self::SUCCESS;
    }
}
