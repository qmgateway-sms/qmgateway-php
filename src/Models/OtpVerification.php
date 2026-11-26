<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** The answer to a code check. A wrong code is a normal answer (verified is false), not an error. */
final readonly class OtpVerification
{
    public function __construct(public bool $verified, public string $status) {}

    /** @param array<string,mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self((bool)$d['verified'], (string)$d['status']);
    }
}
