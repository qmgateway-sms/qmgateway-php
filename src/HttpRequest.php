<?php
declare(strict_types=1);

namespace QMGateway;

final readonly class HttpRequest
{
    /**
     * @param array<string,string> $headers
     * @param bool $retryable true when sending it twice is safe, so a 5xx or a lost connection may be retried
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public float $timeout = 30.0,
        public bool $retryable = true,
    ) {}

    public function header(string $name): ?string
    {
        foreach ($this->headers as $k => $v) if (strcasecmp($k, $name) === 0) return $v;
        return null;
    }
}
