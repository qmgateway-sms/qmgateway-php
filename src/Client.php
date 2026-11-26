<?php
declare(strict_types=1);

namespace QMGateway;

use QMGateway\Resources\{Account, Devices, Inbox, Messages, Otp};

/**
 * The entry point.
 *
 *     $qm = QMGateway\Client::create(getenv('QMGATEWAY_API_KEY'));
 *     $sent = $qm->messages->send('+265999123456', 'Your order is ready.');
 */
final class Client
{
    public const VERSION = '0.1.0';

    public readonly Messages $messages;
    public readonly Inbox $inbox;
    public readonly Devices $devices;
    public readonly Otp $otp;
    public readonly Account $account;

    private function __construct(public readonly Options $options, HttpClient $http)
    {
        $this->messages = new Messages($http);
        $this->inbox = new Inbox($http);
        $this->devices = new Devices($http);
        $this->otp = new Otp($http);
        $this->account = new Account($http);
    }

    /** @param Options|array<string,mixed> $options an Options object, or the same settings as an array */
    public static function create(string $apiKey, Options|array $options = []): self
    {
        $o = is_array($options) ? new Options(...$options) : $options;
        return new self($o, new HttpClient($apiKey, $o));
    }

    /** A client for a test key (qm_test_...). Nothing is sent to a real phone and no units are used. Same as create(). */
    public static function forTest(string $apiKey, Options|array $options = []): self
    {
        return self::create($apiKey, $options);
    }
}
