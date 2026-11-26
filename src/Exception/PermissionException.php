<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 403: the key lacks the permission, or this server's address is not allowed for the key. */
final class PermissionException extends ApiException {}
