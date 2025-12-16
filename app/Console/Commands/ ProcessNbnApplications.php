<?php


namespace App\Console\Commands;


use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnOrder;
use App\Models\Application;
use Illuminate\Console\Command;


class ProcessNbnApplications extends Command
{
    protected $signature = 'applications:process-nbn';


    protected $description = 'Dispatch queued jobs to order all  NBN applications wiht status "order"';


    public function handle(): int
    {
        Application::query()
            ->where('status', ApplicationStatus::Order)
            ->whereHas('plan', fn ($q) => $q->where('type', 'nbn'))
            ->with('plan')
            ->orderBy('created_at')
            ->each(function (Application $application) {
                ProcessNbnOrder::dispatch($application);
            });

        return self::SUCCESS;
    }
}
