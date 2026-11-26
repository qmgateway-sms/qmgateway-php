<?php
declare(strict_types=1);

namespace QMGateway\Exception;

/** 401: the key is missing, wrong, expired, or a test key was used where only a live key works. */
final class AuthenticationException extends ApiException {}
