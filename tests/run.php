<?php
declare(strict_types=1);

/**
 * Test runner with no dependencies:  php tests/run.php
 * Shared contract files are read from QMGATEWAY_CONTRACT_DIR, else ../../sdk-contract, else tests/contract.
 */

use QMGateway\{Client, Options, Webhook};
use QMGateway\Exception\{ApiException, AuthenticationException, ConnectionException, InvalidRequestException, NotFoundException, PermissionException, RateLimitException, ServerException, SignatureException};
use QMGateway\Testing\FakeTransport;

require __DIR__ . '/../src/autoload.php';

$pass = 0; $fail = [];
function check(string $name, callable $fn): void
{
    global $pass, $fail;
    try { $fn(); $pass++; echo "  ok  $name\n"; } catch (Throwable $e) { $fail[] = $name; echo "FAIL  $name: " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n"; }
}
function same(mixed $a, mixed $b, string $what = ''): void
{
    if ($a !== $b) throw new RuntimeException(($what !== '' ? "$what: " : '') . 'expected ' . var_export($b, true) . ', got ' . var_export($a, true));
}
function raises(string $class, callable $fn): Throwable
{
    try { $fn(); } catch (Throwable $e) { if ($e instanceof $class) return $e; throw new RuntimeException("expected $class, got " . $e::class . ': ' . $e->getMessage()); }
    throw new RuntimeException("expected $class, nothing was thrown");
}

$dir = getenv('QMGATEWAY_CONTRACT_DIR') ?: (is_dir(__DIR__ . '/../../sdk-contract') ? __DIR__ . '/../../sdk-contract' : __DIR__ . '/contract');
$contract = fn(string $f) => json_decode(file_get_contents("$dir/$f"), true, flags: JSON_THROW_ON_ERROR);

/** @param list<float> $sleeps */
function client(FakeTransport $t, array &$sleeps = [], int $retries = 4): Client
{
    return Client::create('qm_test_abc', new Options(transport: $t, maxRetries: $retries, sleeper: function (float $s) use (&$sleeps) { $sleeps[] = $s; }));
}
$sent = ['success' => true, 'data' => ['message_ids' => [7], 'recipients' => 1, 'sms_units' => 1, 'status' => 'Queued', 'remaining_units' => 99, 'idempotent_replay' => false, 'numbers' => ['+265999123456']]];

echo "Requests\n";
check('send builds the request and the result', function () use ($sent) {
    $t = (new FakeTransport())->queueJson($sent, 202);
    $r = client($t)->messages->send('+265999123456', 'Hi', scheduledAt: '2026-12-01 08:00:00');
    same($r->messageId(), 7); same($r->remainingUnits, 99);
    $q = $t->last();
    same($q->method, 'POST'); same($q->url, 'https://qmgateway.com/api/v1/messages');
    same($q->header('X-API-Key'), 'qm_test_abc');
    same(json_decode($q->body, true), ['phone' => '+265999123456', 'message' => 'Hi', 'scheduled_at' => '2026-12-01 08:00:00']);
    same(strlen((string)$q->header('Idempotency-Key')), 32);
});
check('sendMany uses recipients', function () use ($sent) {
    $t = (new FakeTransport())->queueJson($sent, 202);
    (void)client($t)->messages->sendMany(['+265999123456', '+265888123456'], 'Hi');
    same(json_decode($t->last()->body, true)['recipients'], ['+265999123456', '+265888123456']);
});
check('a DateTime is sent as Malawi time', function () use ($sent) {
    $t = (new FakeTransport())->queueJson($sent, 202);
    (void)client($t)->messages->send('+265999123456', 'Hi', scheduledAt: new DateTimeImmutable('2026-12-01 06:00:00', new DateTimeZone('UTC')));
    same(json_decode($t->last()->body, true)['scheduled_at'], '2026-12-01 08:00:00');
});
check('unknown fields in answers are ignored and kept in raw', function () use ($sent) {
    $sent['data']['brand_new'] = 'x';
    $r = client((new FakeTransport())->queueJson($sent, 202))->messages->send('+265999123456', 'Hi');
    same($r->raw['brand_new'], 'x');
});
check('query strings drop nulls and encode', function () {
    $t = (new FakeTransport())->queueJson(['success' => true, 'data' => [], 'next_before_id' => null]);
    client($t)->messages->list(status: 'Failed', phone: '+265999123456');
    same($t->last()->url, 'https://qmgateway.com/api/v1/messages?status=Failed&phone=%2B265999123456');
});
check('Options rejects http for a real host, a bad key and bad numbers', function () {
    raises(InvalidArgumentException::class, fn() => new Options(baseUrl: 'http://qmgateway.com'));
    raises(InvalidArgumentException::class, fn() => new Options(timeout: 0));
    raises(InvalidArgumentException::class, fn() => Client::create('bad key'));
    new Options(baseUrl: 'http://127.0.0.1:8120');
    same((new Options())->with(['timeout' => 5.0])->timeout, 5.0);
});

check('an idempotency key with a line break is refused', function () {
    raises(InvalidArgumentException::class, fn() => client(new FakeTransport())->messages->send('+265999123456', 'x', idempotencyKey: "a
X-Evil: 1"));
});

echo "Errors\n";
check('statuses map to typed errors with code and request id', function () {
    foreach ([[401, AuthenticationException::class], [403, PermissionException::class], [404, NotFoundException::class], [422, InvalidRequestException::class], [400, InvalidRequestException::class]] as [$status, $class]) {
        $t = (new FakeTransport())->queueError($status, 'SOME_CODE', 'Bad thing');
        $e = raises($class, fn() => client($t)->account->get());
        same($e->errorCode, 'SOME_CODE'); same($e->requestId, 'req_fake'); same($e->status, $status); same($e->plainMessage(), 'Bad thing');
        same(count($t->requests), 1, 'no retry on ' . $status);
    }
});
check('error details are kept', function () {
    $e = raises(ApiException::class, fn() => client((new FakeTransport())->queueError(402, 'INSUFFICIENT_BALANCE', 'No units', details: ['remaining_units' => 0]))->messages->send('+265999123456', 'x'));
    same($e->details['remaining_units'], 0);
});
check('an unreadable answer is a typed error, not a crash', function () {
    $e = raises(ApiException::class, fn() => client((new FakeTransport())->queue(200, '<html>oops</html>'))->account->get());
    same($e->errorCode, 'INVALID_RESPONSE');
});

echo "Retries\n";
check('rate limit then ok: waits Retry-After, same idempotency key', function () use ($sent) {
    $t = (new FakeTransport())->queueError(429, 'RATE_LIMITED', headers: ['Retry-After' => '1'])->queueError(503, 'SERVICE_UNAVAILABLE')->queueJson($sent, 202);
    $s = [];
    (void)client($t, $s)->messages->send('+265999123456', 'Hi');
    same(count($t->requests), 3); same($s[0], 1.0);
    same($s[1] >= 2.0 && $s[1] <= 2.4, true, 'second wait is 2s plus jitter');
    $keys = array_unique(array_map(fn($r) => $r->header('Idempotency-Key'), $t->requests));
    same(count($keys), 1);
});
check('always unavailable: 5 attempts then ServerException', function () {
    $t = new FakeTransport();
    for ($i = 0; $i < 5; $i++) $t->queueError(503, 'SERVICE_UNAVAILABLE');
    $e = raises(ServerException::class, fn() => client($t)->messages->send('+265999123456', 'Hi'));
    same(count($t->requests), 5); same($e->status, 503);
});
check('Retry-After is capped at 60 seconds', function () use ($sent) {
    $t = (new FakeTransport())->queueError(429, 'RATE_LIMITED', headers: ['Retry-After' => '3600'])->queueJson($sent, 202);
    $s = [];
    (void)client($t, $s)->messages->send('+265999123456', 'Hi');
    same($s, [60.0]);
});
check('network errors are retried, then ConnectionException', function () {
    $t = new FakeTransport();
    for ($i = 0; $i < 3; $i++) $t->queueNetworkError();
    raises(ConnectionException::class, fn() => client($t, retries: 2)->messages->send('+265999123456', 'Hi'));
    same(count($t->requests), 3);
});
check('maxRetries 0 turns retries off', function () {
    $t = (new FakeTransport())->queueError(503, 'SERVICE_UNAVAILABLE');
    raises(ServerException::class, fn() => client($t, retries: 0)->account->get());
    same(count($t->requests), 1);
});
check('sending a code is not repeated after a 5xx or a network error, but is after 429', function () {
    $t = (new FakeTransport())->queueError(503, 'SERVICE_UNAVAILABLE');
    raises(ServerException::class, fn() => client($t)->otp->send(phone: '+265999123456'));
    same(count($t->requests), 1);
    $t = (new FakeTransport())->queueNetworkError();
    raises(ConnectionException::class, fn() => client($t)->otp->send(phone: '+265999123456'));
    same(count($t->requests), 1);
    $t = (new FakeTransport())->queueError(429, 'RATE_LIMITED', headers: ['Retry-After' => '1'])->queueJson(['success' => true, 'data' => ['request_reference' => 'r1', 'expires_in' => 300, 'status' => 'sent', 'channel' => 'sms']]);
    same(client($t)->otp->send(phone: '+265999123456')->requestReference, 'r1');
});
check('a rate limit that finally fails carries retryAfter', function () {
    $t = new FakeTransport();
    for ($i = 0; $i < 5; $i++) $t->queueError(429, 'RATE_LIMITED', headers: ['Retry-After' => '7']);
    same(raises(RateLimitException::class, fn() => client($t)->account->get())->retryAfter, 7);
});

echo "Pagination\n";
foreach ($contract('pagination-cases.json')['cases'] as $case) {
    check('pagination: ' . $case['name'], function () use ($case) {
        $t = new FakeTransport();
        $isInbox = $case['operationId'] === 'get_inbox';
        foreach ($case['pages'] as $i => $ids) {
            $items = array_map(fn($id) => $isInbox ? ['id' => $id, 'from' => '+265999', 'message' => 'm', 'received_at' => '2026-11-19 10:29:41']
                : ['id' => $id, 'phone_number' => '+265999', 'status' => 'Sent', 'message_type' => 'sms', 'sms_units' => 1], $ids);
            $next = $i + 1 < count($case['pages']) ? end($ids) : null;
            $t->queueJson(['success' => true, 'data' => $items, $case['next_field'] => $next]);
        }
        $c = client($t);
        $got = [];
        foreach ($isInbox ? $c->inbox->each() : $c->messages->each() as $m) $got[] = $m->id;
        same($got, $case['expect_ids']); same(count($t->requests), count($case['pages']));
        if (count($case['pages']) > 1) same(str_contains($t->requests[1]->url, $case['param'] . '='), true, 'second request carries ' . $case['param']);
    });
}

echo "Webhooks (shared cases)\n";
$wc = $contract('webhook-cases.json');
foreach ($wc['cases'] as $case) {
    check('webhook: ' . $case['id'], function () use ($wc, $case) {
        try {
            $event = Webhook::verify($case['body'], $case['headers'], $wc['secret'], $wc['tolerance_seconds'], $wc['now']);
            same($case['expect_verified'], true, 'should have been rejected');
            same($event->type, $case['headers']['X-QMGateway-Event']); same($event->messageId(), 1052);
        } catch (SignatureException $e) {
            same($case['expect_verified'], false, 'should have been accepted');
            same($e->reason, $case['reason']);
        }
    });
}
check('Webhook::sign makes a request verify() accepts', function () {
    $r = Webhook::sign('{"message_id":5,"status":"Failed"}', 'whsec_x', 'sms.failed');
    $e = Webhook::verify($r['body'], array_change_key_case($r['headers'], CASE_LOWER), 'whsec_x');
    same($e->type, 'sms.failed'); same($e->status(), 'Failed');
});

echo "Models\n";
check('Account, Balance, Device parse', function () {
    $a = client((new FakeTransport())->queueJson(['success' => true, 'data' => ['organisation' => ['id' => 3, 'name' => 'Acme'], 'key' => ['mode' => 'test', 'scopes' => ['messages:send']], 'services' => [['key' => 'sms', 'name' => 'SMS', 'status' => 'active', 'details' => ['ready_to_send' => true]]]]]))->account->get();
    same($a->organisationName, 'Acme'); same($a->isTestKey(), true); same($a->service('sms')?->readyToSend(), true);
    $b = client((new FakeTransport())->queueJson(['success' => true, 'data' => ['active' => true, 'plan' => 'Starter', 'quota' => 100, 'used' => 10, 'reserved' => 0, 'remaining' => 90, 'percent_left' => 90, 'level' => 'ok', 'starts_at' => null, 'expires_at' => null, 'days_left' => null]]))->account->balance();
    same($b->remaining, 90); same($b->isLow(), false);
    $d = client((new FakeTransport())->queueJson(['success' => true, 'data' => [['id' => 1, 'name' => 'Phone', 'status' => 'online', 'last_seen_at' => '2026-11-19 10:29:41', 'networks' => ['Airtel'], 'capacity' => 2, 'queued' => 0]]]))->devices->list();
    same($d[0]->isOnline(), true); same($d[0]->lastSeenAt?->format('H:i'), '10:29');
});
check('a message reports when it is finished', function () {
    $m = client((new FakeTransport())->queueJson(['success' => true, 'data' => ['id' => 9, 'phone_number' => '+265999123456', 'status' => 'Delivered', 'message_type' => 'sms', 'sms_units' => 1, 'delivered_at' => '2026-11-19 10:30:00']]))->messages->get(9);
    same($m->isDelivered() && $m->isFinished(), true);
});

echo "\n$pass passed, " . count($fail) . " failed\n";
exit($fail ? 1 : 0);
