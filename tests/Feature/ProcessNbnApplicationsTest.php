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
        //fake the queue so jobs don’t actually run during this test..
        Queue::fake();

        //create a couple of plans so we can test eligibility rules..
        $nbnPlan    = Plan::factory()->create(['type' => 'nbn']);
        $mobilePlan = Plan::factory()->create(['type' => 'mobile']);

        //this one should be picked up (nbn + order)..
        $eligible = Application::factory()->create([
            'plan_id' => $nbnPlan->id,
            'status'  => ApplicationStatus::Order,
        ]);

        //this one should be ignored (nbn but not in order status)..
        Application::factory()->create([
            'plan_id' => $nbnPlan->id,
            'status'  => ApplicationStatus::Prelim,
        ]);

        //this one should be ignored (order status but not nbn)..
        Application::factory()->create([
            'plan_id' => $mobilePlan->id,
            'status'  => ApplicationStatus::Order,
        ]);

        //run the command..
        Artisan::call('applications:process-nbn');

        //confirm we dispatched a job for the eligible one only..
        Queue::assertPushed(ProcessNbnOrder::class, function (ProcessNbnOrder $job) use ($eligible) {
            return $job->application->id === $eligible->id;
        });

        //and confirm it only happened once..
        Queue::assertPushed(ProcessNbnOrder::class, 1);
    }


    
    public function test_command_dispatches_no_jobs_when_no_eligible_applications_exist(): void
    {
        Queue::fake();

        //create nbn apps that are not in order status..
        $nbnPlan = Plan::factory()->create(['type' => 'nbn']);

        Application::factory()->create([
            'plan_id' => $nbnPlan->id,
            'status'  => ApplicationStatus::Prelim,
        ]);

        Artisan::call('applications:process-nbn');

        //nothing should be queued because nothing matched the rules..
        Queue::assertNothingPushed();
    }
}
