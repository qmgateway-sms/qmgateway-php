<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\Models\{OtpRequest, OtpVerification};

/** One-time passwords by SMS or email. */
final class Otp extends Resource
{
    /** @param string $channel "sms" or "email"; give $phone for sms and $email for email */
    public function send(string $channel = 'sms', ?string $phone = null, ?string $email = null, ?string $purpose = null, ?int $expirySeconds = null): OtpRequest
    {
        $body = array_filter(['channel' => $channel, 'phone' => $phone, 'email' => $email, 'purpose' => $purpose, 'expiry_seconds' => $expirySeconds], static fn($v) => $v !== null);
        return OtpRequest::fromArray($this->data($this->http->request('POST', '/api/v1/otp/send', body: $body, safeToRepeat: false)));
    }

    /** A wrong or expired code gives verified = false. It is only an error when the request itself is wrong. */
    public function verify(string $requestReference, string $code): OtpVerification
    {
        return OtpVerification::fromArray($this->data($this->http->request('POST', '/api/v1/otp/verify', body: ['request_reference' => $requestReference, 'code' => $code], safeToRepeat: false)));
    }
}
