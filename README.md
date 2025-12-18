# What i implemented
## Task 1: list applications endpoint

- added a new authenticated endpoint: GET /api/applications.
- supports optional filter: ?plan_type=nbn|opticomm|mobile.
- returns only the required fields:
   - application id.
   - customer full name.
   - address fields.
   - plan type, plan name, state.
   - monthly cost converted from cents to dollars.
   - order id only when status is complete.
- results are ordered oldest first.
- results are paginated for scalability.

## Task 2: automate ordering for nbn applications
- added a console command: applications:process-nbn.
- the command selects only:
   - applications with status order.
   - applications whose plan type is nbn.
- dispatches a queued job per application: ProcessNbnOrder.
- the job:
   - posts required payload to the configured b2b endpoint.
   - marks the application complete and stores order_id on success.
   - marks the application order failed on failed response or errors.
- schedule runs every five minutes in App\Console\Kernel.

## Testing approach
- application list endpoint is covered with feature tests:
   - validates oldest first ordering.
   - validates plan_type filtering.
   - validates order_id visibility only for completed applications.
   - validates cents -> dollars formatting.
   - validates pagination behaviour.
- ordering flow is covered with feature tests:
   - command only dispatches jobs for eligible applications.
   - job marks complete and stores order id on success.
   - job marks order failed on failure.
   - edge cases: missing endpoint, wrong status, missing order id, non success status.

## Notes about packages
- added fruitcake/laravel-cors because the project referenced the cors middleware and it was missing in this environment.
- added the minimal guzzle dependency required for Laravel’s Http client to run in tests (to avoid the HandlerStack missing error).
- no runtime http calls are made in tests; all calls are faked using Http::fake() as required.