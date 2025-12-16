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
        $user = User::factory()->create();
        Sanctum::actingAs($user);


        $customer = Customer::factory()->create([
            'first_name' => 'Joseph',
            'last_name' => 'Button',
        ]);


        $nbnPlan = Plan::factory()->create([
            'type' => 'nbn',
            'name' => 'NBN Fast',
            'monthly_cost' => 7500,
        ]);

    
        $mobilePlan = Plan::factory()->create([
            'type' => 'mobile',
            'name' => 'Mobile Basic',
            'monthly_cost' => 1000,
        ]);


        $oldest = Application::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $nbnPlan->id,
            'status' => ApplicationStatus::Complete,
            'order_id' => 'ORDER-123',
            'created_at' => now()->subDays(2),
        ]);


        $newest = Application::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $mobilePlan->id,
            'status' => ApplicationStatus::Prelim,
            'order_id' => 'SHOULD_NOT_SHOW',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/applications');


        $response->assertOk();


        $data = $response->json('data');
        $this->assertCount(2, $data);


        $this->assertSame($oldest->id, $data[0]['application_id']);
        $this->assertSame($newest->id, $data[1]['application_id']);

        
        $this->assertSame('Joseph Button', $data[0]['customer_full_name']);
        $this->assertSame('nbn', $data[0]['plan_type']);
        $this->assertSame('NBN Fast', $data[0]['plan_name']);
        $this->assertSame('75.00', $data[0]['plan_monthly_cost']);


        $this->assertSame('ORDER-123', $data[0]['order_id']);
        $this->assertNull($data[1]['order_id']);


        $filtered = $this->getJson('/api/applications?plan_type=nbn');
        $filtered->assertOk();
        $filteredData = $filtered->json('data');
        $this->assertCount(1, $filteredData);
        $this->assertSame($oldest->id, $filteredData[0]['application_id']);
    }
}
