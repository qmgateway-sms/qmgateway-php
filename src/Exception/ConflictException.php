<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 409: the request conflicts with the current state, for example cancelling a message that already started sending. */
final class ConflictException extends ApiException {}
