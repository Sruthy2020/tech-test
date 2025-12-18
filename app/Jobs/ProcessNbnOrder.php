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

        $endpoint = config('services.nbn.endpoint');
        if (!is_string($endpoint) || trim($endpoint) === '') {
            $this->application->forceFill(['status' => ApplicationStatus::OrderFailed])->save();
            return;
        }

        $planName = $this->application->plan?->name;
        if (!is_string($planName) || trim($planName) === '') {
            $this->application->forceFill(['status' => ApplicationStatus::OrderFailed])->save();
            return;
        }

        $response = Http::post($endpoint, [
            'address_1' => $this->application->address_1,
            'address_2' => $this->application->address_2,
            'city'      => $this->application->city,
            'state'     => $this->application->state,
            'postcode'  => $this->application->postcode,
            'plan_name' => $planName,
        ]);

        $body = $response->json() ?? [];

        $orderId = $body['order_id'] ?? $body['id'] ?? null;
        $status  = $body['status'] ?? null;

        // if successful response with order id, update application..
        if ($response->successful() && filled($orderId) && is_string($status) && strcasecmp($status, 'Successful') === 0) {
            $this->application->forceFill([
                'order_id' => (string) $orderId,
                'status'   => ApplicationStatus::Complete,
            ])->save();
            return;
        }

        // if response not successful, mark as failed..
        $this->application->forceFill([
            'status' => ApplicationStatus::OrderFailed,
        ])->save();
    }
}
//in a real environment we would wrap this call in a try/catch to handle transport level failures (timeouts, DNS, etc).
