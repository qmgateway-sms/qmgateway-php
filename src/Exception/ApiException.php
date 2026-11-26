<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/**
 * QMGateway answered with an error. Branch on {@see self::$errorCode} (it is stable); show getMessage() to people (it may be reworded).
 * The subclasses group the errors by what you can do about them.
 */
class ApiException extends QMGatewayException
{
    /**
     * @param array<string,mixed> $details Extra facts for some codes, for example remaining_units for INSUFFICIENT_BALANCE
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly ?string $requestId = null,
        public readonly array $details = [],
    ) {
        parent::__construct($message . ($requestId !== null ? " (request $requestId)" : ''), $status);
    }

    /** The message without the request id on the end, for showing to a person. */
    public function plainMessage(): string
    {
        return $this->requestId !== null ? substr($this->getMessage(), 0, -strlen(" (request {$this->requestId})")) : $this->getMessage();
    }
}
