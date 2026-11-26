<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 5xx: a problem on QMGateway's side. The SDK already retried; try again later. */
final class ServerException extends ApiException {}
