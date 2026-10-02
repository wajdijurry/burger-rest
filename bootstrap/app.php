<?php

use App\Domain\Shared\Exceptions\DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent API error envelope: { "error": { code, message, fields? } }.
        // Section 7 of the brief: a stable machine-readable code, an
        // actionable message, optional field errors; never a stack trace.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof DomainException) {
                return errorResponse($e->errorCode(), $e->getMessage(), $e->httpStatus(), $e->fieldErrors());
            }

            if ($e instanceof ValidationException) {
                return errorResponse('VALIDATION_FAILED', 'The given data was invalid.', 422, $e->errors());
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return errorResponse('NOT_FOUND', 'The requested resource was not found.', 404);
            }

            if (config('app.debug')) {
                // Let the default (verbose, dev-only) handler show the trace.
                return null;
            }

            report($e);

            return errorResponse('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
        });
    })->create();

function errorResponse(string $code, string $message, int $status, array $fields = []): JsonResponse
{
    $error = ['code' => $code, 'message' => $message];
    if ($fields !== []) {
        $error['fields'] = $fields;
    }

    return response()->json(['error' => $error], $status);
}
