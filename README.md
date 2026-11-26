# QMGateway for PHP

Send SMS and one-time passwords from PHP. Needs **PHP 8.5 or newer**, the built-in `curl` and `json` extensions, and nothing else (no other packages).

```
composer require qmgateway/qmgateway-php
```

No Composer? `require __DIR__ . '/qmgateway-php/src/autoload.php';` works too (use the folder where you put the SDK).

## Send a message

```php
<?php
require 'vendor/autoload.php';

$qm = QMGateway\Client::create(getenv('QMGATEWAY_API_KEY'));

$sent = $qm->messages->send('+265999123456', 'Your order is ready.');
echo "Queued message {$sent->messageId()}, {$sent->remainingUnits} SMS left\n";

$message = $qm->messages->get($sent->messageId());
echo $message->status, "\n";
```

Use a test key (it starts with `qm_test_`; create one on the API keys page of your account) while you build: nothing reaches a phone and no SMS are used.
Keep keys out of your code: read them from the environment.

## What you can do

| | |
|---|---|
| `$qm->messages->send($to, $text, scheduledAt: '2026-12-01 08:00:00')` | one number, or a list for several (`sendMany`) |
| `$qm->messages->get($id)` / `list(status: 'Failed')` / `each(status: 'Failed')` | track delivery; `each()` fetches more pages for you |
| `$qm->messages->scheduled()` / `cancel($id)` / `reschedule($id, $time)` | scheduled messages |
| `$qm->inbox->list()` / `each()` | replies received |
| `$qm->otp->send(phone: '+265999123456')` / `verify($ref, $code)` | one-time passwords |
| `$qm->devices->list()` | your gateway phones |
| `$qm->account->get()` / `balance()` / `statistics()` | account and usage |

```php
foreach ($qm->messages->each(status: 'Failed') as $m) {
    echo "{$m->id} to {$m->phoneNumber} failed: {$m->failureReason}\n";
}
```

## Errors

Everything the SDK throws extends `QMGateway\Exception\QMGatewayException`. API errors are `ApiException`s with a stable `errorCode`,
the HTTP `status` and a `requestId` to quote to support.

```php
use QMGateway\Exception\{ApiException, RateLimitException, ConnectionException};

try {
    $qm->messages->send($phone, $text);
} catch (RateLimitException $e) {
    // still limited after the automatic retries; $e->retryAfter seconds
} catch (ApiException $e) {
    error_log("{$e->errorCode} {$e->status} request {$e->requestId}");
} catch (ConnectionException $e) {
    // no answer: the message may or may not have been queued; check with the same idempotency key
}
```

## Retries and duplicates

Network errors, rate limits (429) and server errors (5xx) are retried up to 4 times, waiting for `Retry-After` when given, otherwise
1, 2, 4, 8 seconds. Other errors are never retried. Every send carries an `Idempotency-Key` (made for you), kept the same across retries,
so a retry can never send twice. For jobs that may run again, pass your own: `send($to, $text, idempotencyKey: "order-$orderId")`.
Sending a one-time password is retried only after a 429.

Change the behaviour with `Options` (immutable):

```php
$qm = QMGateway\Client::create($key, new QMGateway\Options(timeout: 10.0, maxRetries: 2));
```

## Webhooks

```php
$event = QMGateway\Webhook::verify(file_get_contents('php://input'), getallheaders(), getenv('QMGATEWAY_WEBHOOK_SECRET'));
// throws SignatureException when the request is not genuine: answer 401 and stop
if ($event->type === 'sms.delivered') { /* $event->messageId() */ }
```

Use the raw body exactly as received. Requests older than 5 minutes are refused.

## Testing your own code

```php
$fake = (new QMGateway\Testing\FakeTransport())->queueJson(['success' => true, 'data' => ['message_ids' => [7], 'recipients' => 1, 'sms_units' => 1, 'status' => 'Queued', 'remaining_units' => 99, 'idempotent_replay' => false, 'numbers' => ['+265999123456']]], 202);
$qm = QMGateway\Client::create('qm_test_x', new QMGateway\Options(transport: $fake));

$sent = $qm->messages->send('+265999123456', 'Hello');
echo $sent->messageId();                   // 7
echo $fake->last()->header('X-API-Key');     // qm_test_x
```

`FakeTransport::queueError()` and `queueNetworkError()` play failures; `$fake->requests` shows what was sent.
`Webhook::sign()` builds a correctly signed request for testing your handler.

## Command line

```
# paste a test key from the API keys page of your QMGateway account (it starts with qm_test_)
export QMGATEWAY_API_KEY="<your test key>"      # Windows PowerShell: $env:QMGATEWAY_API_KEY = "<your test key>"
php bin/qmgateway send +265999123456 "Hello"
php bin/qmgateway help
```

## Developing this SDK

`php tests/run.php` runs the tests (no tools to install). They use the shared contract files in `tests/contract` (or `QMGATEWAY_CONTRACT_DIR`).
`php tests/live.php` checks it against a real server: set `QMGATEWAY_BASE`, `QMGATEWAY_LIVE_KEY` and `QMGATEWAY_TEST_KEY` first. Use the keys of a test organisation, because the live checks queue real messages.

Security problems: see [SECURITY.md](SECURITY.md). Licence: MIT.
