# AussieBroadband Laravel Tech Test: Implementation Notes

## Overview

This submission implements both required tasks using standard Laravel conventions,
with a focus on clarity, test coverage, and safe handling of edge cases.

All functionality is covered by feature tests and can be verified by running:

```bash
php artisan test
```

## Task 1: list applications endpoint
A new authenticated API endpoint (/api/applications) was added to list applications
for internal use.

### Features
- only accessible to authenticated users
- optional plan_type filter (nbn, mobile, opticomm)
- results are ordered oldest first
- response is paginated for scalability
- plan monthly cost is converted from cents to a dollar format
- order id is only included when application status is complete

### Testing
Feature tests verify:
- authentication requirements
- correct ordering
- correct field visibility
- plan type filtering
- pagination behaviour

## Task 2: automate ordering for nbn applications
A scheduled console command processes NBN applications that are ready to be ordered.
### Behaviour
- runs every 5 minutes
- selects only nbn applications with status order
- dispatches one queue job per eligible application
- sends application and plan details to the B2B endpoint
- updates application status based on response outcome

### Error Handling
Applications are marked as order failed if:
- the endpoint is missing
- the HTTP request fails
- the response is not successful
- the response is missing an order id
- the response status is not Successful

### Testing
Feature tests cover:
- correct job dispatching
- no dispatch when no eligible applications exist
- successful and failed order flows
- all major edge cases

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

## Packages
- No additional packages were required for core functionality.
- The following packages were added only to support framework features used in testing:
     - fruitcake/laravel-cors (middleware dependency)
     - guzzlehttp/psr7 (required by Laravel HTTP client in the test environment)

## Notes
Given more time, the solution could be extended with:
- request validation for query parameters
- API resource classes for response formatting
- retry/backoff strategies for failed B2B calls