<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Exception\ConnectionException;

/** Sends one HTTP request. The cURL transport is the default; tests use QMGateway\Testing\FakeTransport. */
interface Transport
{
    /**
     * @throws ConnectionException when no answer could be read (DNS, connection, TLS or timeout). A 4xx or 5xx answer is NOT an exception here.
     */
    public function send(HttpRequest $request): HttpResponse;
}
