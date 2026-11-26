<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** Who the API key belongs to and which services are ready. Call it at start-up or in a health check. */
final readonly class Account
{
    /**
     * @param list<string> $scopes
     * @param list<Service> $services
     * @param array<string,mixed> $raw
     */
    public function __construct(public int $organisationId, public string $organisationName, public string $keyMode, public array $scopes, public array $services, public array $raw = []) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((int)$d['organisation']['id'], (string)$d['organisation']['name'], (string)($d['key']['mode'] ?? 'live'), array_map('strval', $d['key']['scopes'] ?? []),
            array_map(Service::fromArray(...), $d['services'] ?? []), $d);
    }

    public function service(string $key): ?Service
    {
        return array_first(array_filter($this->services, static fn(Service $s) => $s->key === $key));
    }

    public function isTestKey(): bool { return $this->keyMode === 'test'; }
}
