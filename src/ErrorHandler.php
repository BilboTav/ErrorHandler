<?php
declare(strict_types=1);

namespace Bilbofox;

use ErrorException;
use Throwable;

/**
 *
 *
 * @author Michal Kvita <Mikvt@seznam.cz>
 */
final class ErrorHandler
{

    /**
     * Register global handler for exceptions and PHP errors (transformed into exceptions)
     *
     * @param callable $handler
     * @param callable|null $exceptionBuilder
     * @return void
     */
    public static function register(int $errorLevels, callable $handler, ?callable $exceptionBuilder = null): void
    {
        error_reporting($errorLevels);

        $exceptionBuilder ??= fn(int $type, string $message, string $file, int $line): Throwable => new ErrorException(
            message: $message,
            severity: $type,
            filename: $file,
            line: $line,
        );

        // All errors --> exceptions
        set_error_handler(
            callback: static function (int $type, string $message, string $file, int $line) use ($exceptionBuilder): bool {
                if (!(error_reporting() & $type)) {
                    // This error code is not included in error_reporting, so let it fall
                    // through to the standard PHP error handler
                    return false;
                }

                throw $exceptionBuilder(type: $type, message: $message, file: $file, line: $line);
            },
            error_levels: error_reporting(),
        );

        set_exception_handler($handler);

        // Fatal errors needs to be handled in shutdown handler
        register_shutdown_function(static function () use ($handler, $exceptionBuilder): void {
            $error = error_get_last();
            if ($error !== null) {
                extract($error);

                if (in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
                    $handler($exceptionBuilder(type: $type, message: $message, file: $file, line: $line));
                }
            }
        });
    }
}