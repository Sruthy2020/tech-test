<?php


namespace App\Console\Commands;


use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnOrder;
use App\Models\Application;
use Illuminate\Console\Command;


class ProcessNbnApplications extends Command
{
    protected $signature = 'applications:process-nbn';


    protected $description = 'Dispatch queued jobs to order all NBN applications with status "order"';


    public function handle(): int
    {
        Application::query()
            //only pick applications that are ready to be ordered..
            ->where('status', ApplicationStatus::Order)

            //ensure the application belongs to an nbn plan..
            ->whereHas('plan', fn ($q) => $q->where('type', 'nbn'))

            //eager load the plan to avoid extra queries in the job..
            ->with('plan')

            //process older applications first..
            ->orderBy('created_at')

            //dispatch a job for each eligible application..
            ->each(function (Application $application) {
                ProcessNbnOrder::dispatch($application);
            });

        //indicate the command executed successfully..
        return self::SUCCESS;
    }
}