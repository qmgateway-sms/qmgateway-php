<?php
declare(strict_types=1);

namespace QMGateway\Testing;

use QMGateway\Exception\ConnectionException;
use QMGateway\HttpRequest;
use QMGateway\HttpResponse;
use QMGateway\Transport;

/**
 * A pretend network for your own tests: queue the answers you want, run your code, then look at what was sent.
 *
 *     $fake = (new FakeTransport())->queueJson(['success' => true, 'data' => [...]], 202);
 *     $qm = Client::create('qm_test_x', new Options(transport: $fake));
 */
final class FakeTransport implements Transport
{
    /** @var list<HttpResponse|ConnectionException|callable> */
    private array $queue = [];
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function queue(int $status, string $body, array $headers = []): self
    {
        $this->queue[] = new HttpResponse($status, array_change_key_case($headers, CASE_LOWER), $body);
        return $this;
    }

    /** @param array<string,mixed> $json @param array<string,string> $headers */
    public function queueJson(array $json, int $status = 200, array $headers = []): self
    {
        return $this->queue($status, json_encode($json, JSON_THROW_ON_ERROR), $headers);
    }

    /** An error answer in the API's own shape. */
    public function queueError(int $status, string $code, string $message = 'Error', array $headers = [], array $details = []): self
    {
        return $this->queueJson(['success' => false, 'error' => ['code' => $code, 'message' => $message, 'request_id' => 'req_fake', 'details' => $details]], $status, $headers);
    }

    public function queueNetworkError(string $message = 'Could not connect'): self
    {
        $this->queue[] = new ConnectionException($message);
        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new \LogicException('FakeTransport has no answer queued for ' . $request->method . ' ' . $request->url);
        if ($next instanceof ConnectionException) throw $next;
        return $next;
    }

    public function last(): HttpRequest
    {
        return array_last($this->requests) ?? throw new \LogicException('Nothing was sent yet.');
    }
}
