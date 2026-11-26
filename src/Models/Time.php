<?php
declare(strict_types=1);

namespace QMGateway\Models;

/** Reads the dates QMGateway sends. "2026-11-19 10:29:41" is Malawi time (Africa/Blantyre); "2026-12-01T08:00:00+02:00" carries its own offset. */
final class Time
{
    public static function parse(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') return null;
        try { return new \DateTimeImmutable($value, new \DateTimeZone('Africa/Blantyre')); } catch (\Throwable) { return null; }
    }
}
