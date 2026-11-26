<?php
declare(strict_types=1);

/**
 * Runs the SDK against a real QMGateway server.
 *   QMGATEWAY_BASE=https://qmgateway.com QMGATEWAY_LIVE_KEY=qm_live_... QMGATEWAY_TEST_KEY=qm_test_... php tests/live.php
 * Test-key sends use no units and reach no phone; the live key is only used for read calls and a scheduled message that is cancelled again.
 */

use QMGateway\{Client, Options};
use QMGateway\Exception\{AuthenticationException, InvalidRequestException};

require __DIR__ . '/../src/autoload.php';

$base = getenv('QMGATEWAY_BASE') ?: 'https://qmgateway.com';
$live = getenv('QMGATEWAY_LIVE_KEY'); $test = getenv('QMGATEWAY_TEST_KEY');
if (!$live || !$test) { fwrite(STDERR, "Set QMGATEWAY_LIVE_KEY and QMGATEWAY_TEST_KEY.\n"); exit(2); }
$opts = new Options(baseUrl: $base, timeout: 20.0, maxRetries: 1);
$qm = Client::create($live, $opts); $qt = Client::forTest($test, $opts);

$n = 0; $bad = 0;
function step(string $name, callable $fn): void { global $n, $bad; $n++; try { $fn(); echo "  ok  $name\n"; } catch (Throwable $e) { $bad++; echo "FAIL  $name: " . $e::class . ': ' . $e->getMessage() . "\n"; } }
function expect(bool $c, string $m = 'unexpected result'): void { if (!$c) throw new RuntimeException($m); }

step('account', function () use ($qm) { $a = $qm->account->get(); expect($a->organisationName !== '' && !$a->isTestKey()); expect($a->service('sms') !== null); });
step('balance', function () use ($qm) { expect($qm->account->balance()->quota > 0); });
step('devices', function () use ($qm) { expect($qm->devices->list() !== []); });
step('statistics', function () use ($qm) { expect(is_array($qm->account->statistics()->overview)); });
step('inbox pages', function () use ($qm) { $c = 0; foreach ($qm->inbox->each(pageSize: 1) as $m) { $c++; } expect($c >= 1, 'no inbox message'); });
step('test-key send then read it back', function () use ($qt) {
    $r = $qt->messages->send('+265991234567', 'SDK live test');
    expect($r->messageId() > 0);
    $m = $qt->messages->get($r->messageId());
    expect($m->phoneNumber !== '' && $m->mode === 'test', 'message should be marked test');
});
step('same Idempotency-Key does not send twice', function () use ($qt) {
    $k = 'sdk-live-' . bin2hex(random_bytes(6));
    $a = $qt->messages->send('+265991234567', 'Once', idempotencyKey: $k);
    $b = $qt->messages->send('+265991234567', 'Once', idempotencyKey: $k);
    expect($a->messageId() === $b->messageId() && $b->idempotentReplay);
});
step('list messages with auto-pagination', function () use ($qt) { for ($i = 0; $i < 3; $i++) (void)$qt->messages->send('+265991234567', "Page test $i"); $c = 0; foreach ($qt->messages->each(pageSize: 2) as $m) { if (++$c >= 5) break; } expect($c >= 3); });
step('schedule, list, reschedule, cancel (live key)', function () use ($qm) {
    $r = $qm->messages->send('+265991234567', 'SDK scheduled', scheduledAt: new DateTimeImmutable('+3 days'));
    $id = $r->messageId(); $found = false;
    foreach ($qm->messages->eachScheduled(pageSize: 5) as $s) if ($s->id === $id) $found = true;
    expect($found, 'scheduled message not listed');
    expect($qm->messages->reschedule($id, new DateTimeImmutable('+4 days'))['id'] === $id);
    expect($qm->messages->cancel($id)['status'] !== '');
});
step('errors are typed', function () use ($base, $qm, $opts) {
    try { Client::create('qm_live_' . str_repeat('0', 48), $opts)->account->get(); expect(false, 'bad key accepted'); } catch (AuthenticationException $e) { expect($e->status === 401 && $e->requestId !== null); }
    try { (void)$qm->messages->send('not-a-number', 'x'); expect(false, 'bad number accepted'); } catch (InvalidRequestException $e) { expect($e->errorCode !== ''); }
});
step('test key is refused on account endpoints', function () use ($qt) {
    try { $qt->account->balance(); expect(false, 'allowed'); } catch (AuthenticationException $e) { expect($e->errorCode === 'TEST_KEY_NOT_ALLOWED', $e->errorCode); }
});
echo "\n" . ($n - $bad) . " passed, $bad failed\n";
exit($bad ? 1 : 0);
