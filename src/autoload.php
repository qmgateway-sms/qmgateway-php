<?php
declare(strict_types=1);

/**
 * For projects that do not use Composer: require this file once and every QMGateway class loads on demand.
 * With Composer you do not need it; "vendor/autoload.php" already knows the classes.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'QMGateway\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});
