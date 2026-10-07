<?php

namespace App\Console\Commands;

use App\Services\CostThresholdService;
use Illuminate\Console\Command;

class CheckCostThresholdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thresholds:check {--force : Force evaluation and notification even if already sent this month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate monthly cost budget thresholds and dispatch email notifications if exceeded.';

    /**
     * Execute the console command.
     */
    public function handle(CostThresholdService $service): int
    {
        $this->info('Evaluating monthly cost budget thresholds...');

        $force = (bool) $this->option('force');
        $results = $service->evaluateAll($force);

        $totalEvaluated = count($results);
        $totalAlerted = 0;

        foreach ($results as $res) {
            if (!empty($res['alerts_sent'])) {
                $totalAlerted += count($res['alerts_sent']);
                $this->warn(sprintf(
                    'Rule #%d reached threshold tiers: %s (Current spend: $%.2f / $%.2f)',
                    $res['rule_id'],
                    implode('%, ', $res['alerts_sent']) . '%',
                    $res['current_spend'],
                    $res['budget']
                ));
            }
        }

        $this->info("Completed threshold check: {$totalEvaluated} rules evaluated, {$totalAlerted} alerts dispatched.");

        return Command::SUCCESS;
    }
}
