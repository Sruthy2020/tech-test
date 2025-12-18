<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnOrder;
use App\Models\Application;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessNbnApplicationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_for_nbn_applications(): void
    {
        //prevent jobs from actually running and allow assertions on dispatches....
        Queue::fake();

        //create plans for different types..
        $nbnPlan    = Plan::factory()->create(['type' => 'nbn']);
        $mobilePlan = Plan::factory()->create(['type' => 'mobile']);

        //eligible application:
        // - NBN plan
        // - status = order
        $eligible = Application::factory()->create([
            'plan_id' => $nbnPlan->id,
            'status'  => ApplicationStatus::Order,
        ]);

        //Ineligible application:
        // - NBN plan
        // - Status not "order"
        Application::factory()->create([
            'plan_id' => $nbnPlan->id,
            'status'  => ApplicationStatus::Prelim,
        ]);

        // Ineligible application:
        // - Non-NBN plan
        // - Status = order
        Application::factory()->create([
            'plan_id' => $mobilePlan->id,
            'status'  => ApplicationStatus::Order,
        ]);

        //run the console command that processes NBN applications
        Artisan::call('applications:process-nbn');

        //assert that a ProcessNbnOrder job was dispatched
        // and that it was dispatched for the eligible application only
        Queue::assertPushed(ProcessNbnOrder::class, function (ProcessNbnOrder $job) use ($eligible) {
            return $job->application->id === $eligible->id;
        });

        //ensure exactly one job was dispatched
        Queue::assertPushed(ProcessNbnOrder::class, 1);
    }
}
