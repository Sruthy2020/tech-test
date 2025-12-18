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
        //set a fake b2b endpoint so the job has somewhere to “post” to..
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //fake a successful api response..
        Http::fake([
            '*' => Http::response([
                'id'     => 'ORD000000000000',
                'status' => 'Successful',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        //create an nbn plan (the job reads plan name from here)..
        $plan = Plan::factory()->create([
            'type' => 'nbn',
            'name' => 'NBN Fast',
        ]);

        //create an eligible application (status must be order)..
        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        //run the job directly so we can assert the result immediately..
        (new ProcessNbnOrder($application))->handle();

        //we should have attempted exactly one request..
        Http::assertSentCount(1);

        //pull the latest row from the db..
        $application->refresh();

        //it should move to complete and save the order id..
        $this->assertSame(ApplicationStatus::Complete->value, $application->status->value);
        $this->assertSame('ORD000000000000', $application->order_id);
    }



    public function test_failed_order_marks_order_failed(): void
    {
        //load the provided failure stub so we match the expected payload shape..
        $payload = json_decode(
            file_get_contents(base_path('tests/stubs/nbn-fail-response.json')),
            true
        );

        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //fake a failed response (400)..
        Http::fake([
            '*' => Http::response($payload, 400, ['Content-Type' => 'application/json']),
        ]);

        $plan = Plan::factory()->create([
            'type' => 'nbn',
            'name' => 'NBN Fast',
        ]);

        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        (new ProcessNbnOrder($application))->handle();

        $application->refresh();

        //it should end up in order failed and keep order_id empty..
        $this->assertSame(ApplicationStatus::OrderFailed->value, $application->status->value);
        $this->assertNull($application->order_id);
    }



    public function test_does_not_send_request_if_application_is_not_in_order_status(): void
    {
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);
        Http::fake();

        $plan = Plan::factory()->create(['type' => 'nbn', 'name' => 'NBN Fast']);

        //this app is not eligible (status is prelim)..
        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Prelim,
            'order_id' => null,
        ]);

        (new ProcessNbnOrder($application))->handle();

        //job should exit early and never call the b2b endpoint..
        Http::assertSentCount(0);

        $application->refresh();
        $this->assertSame(ApplicationStatus::Prelim->value, $application->status->value);
    }



    public function test_marks_order_failed_if_endpoint_is_missing(): void
    {
        //simulate missing env/config value..
        config(['services.nbn.endpoint' => null]);
        Http::fake();

        $plan = Plan::factory()->create(['type' => 'nbn', 'name' => 'NBN Fast']);

        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        (new ProcessNbnOrder($application))->handle();

        //no request should happen if we don’t have an endpoint..
        Http::assertSentCount(0);

        $application->refresh();
        $this->assertSame(ApplicationStatus::OrderFailed->value, $application->status->value);
        $this->assertNull($application->order_id);
    }



    public function test_marks_order_failed_if_successful_response_has_no_order_id(): void
    {
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //200 ok but no id field means we can’t store an order id..
        Http::fake([
            '*' => Http::response([
                'status' => 'Successful',
            ], 200),
        ]);

        $plan = Plan::factory()->create(['type' => 'nbn', 'name' => 'NBN Fast']);

        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        (new ProcessNbnOrder($application))->handle();

        Http::assertSentCount(1);

        $application->refresh();
        $this->assertSame(ApplicationStatus::OrderFailed->value, $application->status->value);
        $this->assertNull($application->order_id);
    }

    

    public function test_marks_order_failed_if_status_is_not_successful_even_with_order_id(): void
    {
        config(['services.nbn.endpoint' => 'https://nbn-b2b.test/orders']);

        //even with an id, the status controls whether we mark it complete..
        Http::fake([
            '*' => Http::response([
                'id'     => 'ORD000000000000',
                'status' => 'Failed',
            ], 200),
        ]);

        $plan = Plan::factory()->create(['type' => 'nbn', 'name' => 'NBN Fast']);

        $application = Application::factory()->create([
            'plan_id'  => $plan->id,
            'status'   => ApplicationStatus::Order,
            'order_id' => null,
        ]);

        (new ProcessNbnOrder($application))->handle();

        Http::assertSentCount(1);

        $application->refresh();
        $this->assertSame(ApplicationStatus::OrderFailed->value, $application->status->value);
        $this->assertNull($application->order_id);
    }
}
