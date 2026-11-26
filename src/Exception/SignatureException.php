<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** A webhook request is not genuine. {@see self::$reason} is bad_signature, stale_timestamp or missing_header. Answer 401 and do nothing else. */
final class SignatureException extends QMGatewayException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The webhook could not be verified: ' . $reason);
    }
}
