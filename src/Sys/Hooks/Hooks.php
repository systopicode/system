<?php

declare(strict_types=1);

namespace Systopic\System\Sys\Hooks;

/**
 * Named request hooks — several listeners per event, like addEventListener.
 *
 * Register from any project or panel file that runs before emit
 * (typically lib/funcs.lib.php):
 *
 *   hooks::on('beforeDispatch', function (string $mode) { ... });
 *
 * The loader emits `beforeDispatch` after panelTree->build(), before
 * site.php / app.php. Higher $priority runs first (Symfony-style).
 *
 * Registering after emit() has already run for that name logs a p()
 * warning — the callback will never fire for this request.
 */
class Hooks
{
    /** @var array<string, list<array{0: int, 1: callable}>> */
    private static array $listeners = [];

    /** @var array<string, true> */
    private static array $emitted = [];

    public static function on(string $name, callable $fn, int $priority = 0): void
    {
        if (isset(self::$emitted[$name])) {
            p("hooks::on('{$name}') too late — emit() already ran; listener will not fire this request");
        }
        self::$listeners[$name][] = [$priority, $fn];
    }

    public static function emit(string $name, mixed ...$args): void
    {
        self::$emitted[$name] = true;
        $list = self::$listeners[$name] ?? [];
        if ($list === []) {
            return;
        }
        usort($list, static fn(array $a, array $b): int => $b[0] <=> $a[0]);
        foreach ($list as [, $fn]) {
            $fn(...$args);
        }
    }

    public static function clear(?string $name = null): void
    {
        if ($name === null) {
            self::$listeners = [];
            self::$emitted = [];
            return;
        }
        unset(self::$listeners[$name], self::$emitted[$name]);
    }
}

class_alias(Hooks::class, 'hooks', false);
