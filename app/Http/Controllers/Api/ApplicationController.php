<?php


namespace App\Http\Controllers\Api;


use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;


class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([     //first validate which plan_type is being requested...
            'plan_type' => 'nullable|in:nbn,opticomm,mobile',
        ]);
        $applications = Application::query()
            ->with(['customer', 'plan'])      //then loads the customer and plans.
            ->when($validated['plan_type'] ?? null, function ($query, string $planType) {
                $query->whereHas('plan', fn ($q) => $q->where('type', $planType));
            })
            ->orderBy('created_at')           //so oldest applications come first...
            ->paginate(15)                    //paginate the results to 15 per page..
            ->through(function (Application $application) {        // maps to required response fields..
                return [  
                    'application_id' => $application->id,
                    'customer_full_name' => trim($application->customer->first_name.' '.$application->customer->last_name),
                    'address' => $application->address_1,
                    'plan_type' => $application->plan->type,
                    'plan_name' => $application->plan->name,
                    'state' => $application->state,
                    'plan_monthly_cost' => number_format($application->plan->monthly_cost / 100, 2, '.', ''),         //formats from cents to dollar..
                    'order_id' => $application->status === ApplicationStatus::Complete ? $application->order_id : null,     //if the status is complete, return order id else null..
                ];
            });
        return response()->json($applications);
    }
}
