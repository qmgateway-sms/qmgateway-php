<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** No answer could be read: DNS, connection, TLS or a timeout. After the retries were used up. */
final class ConnectionException extends QMGatewayException {}
