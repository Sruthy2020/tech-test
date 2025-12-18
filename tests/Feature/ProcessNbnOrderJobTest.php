<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Jobs\ProcessNbnOrder;
use App\Models\Application;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessNbnOrderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_order_marks_completed(): void
    {
        //configure a fake NBN B2B endpoint for the job..
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //fake a successful B2B response..
        Http::fake([
            '*' => Http::response([
                'id'     => 'ORD000000000000',
                'status' => 'Successful',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        //create an NBN plan..
        $plan = Plan::factory()->create([
            'type' => 'nbn',
            'name' => 'NBN Fast',
        ]);

        //create an application eligible for ordering....
        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        //execute the job synchronously...
        (new ProcessNbnOrder($application))->handle();

        //ensure the HTTP request was sent exactly once...
        Http::assertSentCount(1);

        //refresh the application to get persisted changes
        $application->refresh();

        //application should be marked complete and store the order ID..
        $this->assertSame(ApplicationStatus::Complete->value, $application->status->value);
        $this->assertSame('ORD000000000000', $application->order_id);
    }

    public function test_failed_order_marks_order_failed(): void
    {
        //load a sample failed response payload
        $payload = json_decode(
            file_get_contents(base_path('tests/stubs/nbn-fail-response.json')),
            true
        );

        //configure a fake NBN B2B endpoint for the job
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //fake a failed B2B response
        Http::fake([
            '*' => Http::response($payload, 400, ['Content-Type' => 'application/json']),
        ]);

        //create an NBN plan
        $plan = Plan::factory()->create([
            'type' => 'nbn',
            'name' => 'NBN Fast',
        ]);

        //create an application eligible for ordering...
        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        //execute the job synchronously..
        (new ProcessNbnOrder($application))->handle();

        //refresh the application to get persisted changes..
        $application->refresh();

        //application should be marked as order failed and have no order ID..
        $this->assertSame(ApplicationStatus::OrderFailed->value, $application->status->value);
        $this->assertNull($application->order_id);
    }
}
