<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 400 or 422: the request needs a change (a bad number, no balance, a field missing). Do not retry it unchanged. */
final class InvalidRequestException extends ApiException {}
