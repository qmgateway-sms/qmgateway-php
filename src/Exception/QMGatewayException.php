<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** Everything this SDK throws extends this class, so one catch block can handle it all. */
class QMGatewayException extends \RuntimeException {}
