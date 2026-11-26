<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\HttpClient;

/** Base of the resource classes. */
abstract class Resource
{
    public function __construct(protected readonly HttpClient $http) {}

    /** @return array<string,mixed> the "data" part of an answer */
    protected function data(array $answer): array
    {
        return is_array($answer['data'] ?? null) ? $answer['data'] : [];
    }
}
