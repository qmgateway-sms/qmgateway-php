<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 429: too many requests. The SDK already waited and retried; this is thrown when the retries ran out. */
final class RateLimitException extends ApiException
{
    /** @param array<string,mixed> $details */
    public function __construct(string $message, int $status, string $errorCode, ?string $requestId, array $details, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $status, $errorCode, $requestId, $details);
    }
}
