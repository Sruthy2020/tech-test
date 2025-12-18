<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationListTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_authenticated_user_can_list_applications()
    {
        //log in as an authenticated user..
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //set up a known customer so we can assert the full name format..
        $customer = Customer::factory()->create([
            'first_name' => 'Joseph',
            'last_name'  => 'Button',
        ]);

        //set up two plans so we can test filtering + plan info in the response..
        $nbnPlan = Plan::factory()->create([
            'type'         => 'nbn',
            'name'         => 'NBN Fast',
            'monthly_cost' => 7500,
        ]);

        $mobilePlan = Plan::factory()->create([
            'type'         => 'mobile',
            'name'         => 'Mobile Basic',
            'monthly_cost' => 1000,
        ]);

        //create the oldest record first so we can check ordering (oldest first)..
        $oldest = Application::factory()->create([
            'customer_id' => $customer->id,
            'plan_id'     => $nbnPlan->id,
            'status'      => ApplicationStatus::Complete,
            'order_id'    => 'ORDER-123',
            'created_at'  => now()->subDays(2),
        ]);

        //create a newer record that should appear second..
        $newest = Application::factory()->create([
            'customer_id' => $customer->id,
            'plan_id'     => $mobilePlan->id,
            'status'      => ApplicationStatus::Prelim,
            'order_id'    => 'SHOULD_NOT_SHOW',
            'created_at'  => now()->subDay(),
        ]);

        //hit the endpoint..
        $response = $this->getJson('/api/applications');

        //basic success check..
        $response->assertOk();

        //we expect both applications back..
        $data = $response->json('data');
        $this->assertCount(2, $data);

        //oldest must be first..
        $this->assertSame($oldest->id, $data[0]['application_id']);
        $this->assertSame($newest->id, $data[1]['application_id']);

        //spot check a few returned fields + formatting..
        $this->assertSame('Joseph Button', $data[0]['customer_full_name']);
        $this->assertSame('nbn', $data[0]['plan_type']);
        $this->assertSame('NBN Fast', $data[0]['plan_name']);

        //monthly_cost is stored in cents, so we expect dollars in the response..
        $this->assertSame('75.00', $data[0]['plan_monthly_cost']);

        //order_id should only show when status is complete..
        $this->assertSame('ORDER-123', $data[0]['order_id']);
        $this->assertNull($data[1]['order_id']);

        //filtering by plan_type should reduce the list..
        $filtered = $this->getJson('/api/applications?plan_type=nbn');
        $filtered->assertOk();

        $filteredData = $filtered->json('data');
        $this->assertCount(1, $filteredData);
        $this->assertSame($oldest->id, $filteredData[0]['application_id']);
    }

    
    public function test_list_is_paginated(): void
    {
        //log in as an authenticated user..
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        //create enough records to force pagination behaviour..
        $plan = Plan::factory()->create(['type' => 'nbn']);
        $customer = Customer::factory()->create();

        Application::factory()->count(30)->create([
            'customer_id' => $customer->id,
            'plan_id'     => $plan->id,
        ]);

        $response = $this->getJson('/api/applications');

        $response->assertOk();

        //we always expect a data array back..
        $response->assertJsonStructure([
            'data',
        ]);

        //it should not dump all 30 results in one response..
        $data = $response->json('data');
        $this->assertLessThan(30, count($data));

        //we should see some kind of pagination info depending on paginator used..
        $this->assertTrue(
            $response->json('links') !== null
            || $response->json('next_page_url') !== null
            || $response->json('prev_page_url') !== null
        );
    }
}
