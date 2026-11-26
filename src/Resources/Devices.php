<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\Models\Device;

final class Devices extends Resource
{
    /** @return list<Device> */
    public function list(): array
    {
        return array_map(Device::fromArray(...), $this->http->request('GET', '/api/v1/devices')['data'] ?? []);
    }
}
