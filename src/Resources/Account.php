<?php
declare(strict_types=1);

namespace QMGateway\Resources;

use QMGateway\Models\Account as AccountModel;
use QMGateway\Models\Balance;
use QMGateway\Models\Statistics;

/** Account, balance and statistics. */
final class Account extends Resource
{
    public function get(): AccountModel
    {
        return AccountModel::fromArray($this->data($this->http->request('GET', '/api/v1/account')));
    }

    public function balance(): Balance
    {
        return Balance::fromArray($this->data($this->http->request('GET', '/api/v1/balance')));
    }

    /** @param string|null $period for example "today", "week", "month" (see the API reference) */
    public function statistics(?string $period = null, ?string $startDate = null, ?string $endDate = null): Statistics
    {
        return Statistics::fromArray($this->http->request('GET', '/api/v1/statistics', ['period' => $period, 'start_date' => $startDate, 'end_date' => $endDate]));
    }
}
