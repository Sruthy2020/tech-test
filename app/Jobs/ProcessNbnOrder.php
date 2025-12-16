<?php


namespace App\Jobs;


use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;


class ProcessNbnOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public Application $application)
    {
    }


    public function handle(): void
    {
        //only process expected applications...
        if ($this->application->status !== ApplicationStatus::Order) {
            return;
        }

        // making sure relationships are available for payload..
        $this->application->loadMissing('plan');

        try {
            $response = Http::post(config('services.nbn.endpoint'), [
                'address_1' => $this->application->address_1,
                'address_2' => $this->application->address_2,
                'city' => $this->application->city,
                'state' => $this->application->state,
                'postcode' => $this->application->postcode,
                'plan_name' => $this->application->plan->name,
            ]);
            
            // if successful response with order id, update application..
            if ($response->successful() && isset($response['order_id'])) {
                $this->application->forceFill([
                    'order_id' => (string) $response['order_id'],
                    'status' => ApplicationStatus::Complete,
                ])->save();

                return;
            }

            // if response not successful, mark as failed..
            $this->application->forceFill([
                'status' => ApplicationStatus::OrderFailed,
            ])->save();
        } catch (Throwable $e) {
            $this->application->forceFill([
                'status' => ApplicationStatus::OrderFailed,
            ])->save();
        }
    }
}
