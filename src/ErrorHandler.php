<?php
declare(strict_types=1);

namespace Bilbofox;

use ErrorException;
use Throwable;

/**
 * Universal error handler for PHP 8 with custom handling of errors
 * All errors are transformed into exceptions
 *
 * @author Michal Kvita <Mikvt@seznam.cz>
 */
final class ErrorHandler
{
    private const FATAL_ERROR_TYPES = [
        E_ERROR,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_PARSE,
        E_RECOVERABLE_ERROR,
        E_USER_ERROR
    ];

    /**
     * Register a global handler for exceptions and PHP errors (transformed into exceptions)
     *
     * @param int $errorLevels Error reporting levels to report - pass the same value as to error_reporting() function
     * @param callable $handler Logic for handling exceptions - callback is the same as passed to set_exception_handler() function
     * handler(Throwable $ex): void
     * @param callable|null $exceptionBuilder Custom exception builder, by default, transforms native errors into ErrorException class
     *
     * @throws Throwable
     * @see set_exception_handler
     *
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

        // Fatal errors need to be handled in the shutdown handler
        register_shutdown_function(static function () use ($handler, $exceptionBuilder): void {
            $error = error_get_last();
            if ($error !== null) {
                extract($error);

                if (in_array($type, self::FATAL_ERROR_TYPES, true)) {
                    $handler($exceptionBuilder(type: $type, message: $message, file: $file, line: $line));
                }
            }
        });
    }
}