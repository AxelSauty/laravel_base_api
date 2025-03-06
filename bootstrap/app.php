<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            return response()->json(['status' => 'error', 'hint' => 'not_authenticated'], 403);
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('*')) {
                return true;
            }

            return $request->expectsJson();
        });

        $exceptions->render(function (Exception $exception, Request $request) {
            $exception_response = [
                'status' => 'error',
            ];
            if (config()->get('app.debug') == 'true') {
                $exception_response = [
                    'status' => 'error',
                    'exception' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace(),
                ];
            }

            if ($exception instanceof AuthenticationException) {
                return response()->json(array_merge($exception_response, ['hint' => 'not_authenticated']), 401);
            } else if ($exception instanceof AuthorizationException) {
                return response()->json(array_merge($exception_response, ['hint' => 'not_authorized']), 403);
            } else if ($exception instanceof NotFoundHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'resource_not_found']), 404);
            } else if ($exception instanceof MethodNotAllowedHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'method_not_allowed']), 405);
            } else if ($exception instanceof NotAcceptableHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'requested_resource_not_acceptable']), 406);
            } else if ($exception instanceof ConflictHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'request_conflict']), 409);
            } else if ($exception instanceof ValidationException) {
                return response()->json(array_merge($exception_response, ['hint' => 'validation_failed']), 422);
            } else if ($exception instanceof TooManyRequestsHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'too_many_requests']), 429);
            } else if ($exception instanceof ServiceUnavailableHttpException) {
                return response()->json(array_merge($exception_response, ['hint' => 'service_unavailable']), 503);
            }
            return response()->json(array_merge($exception_response, ['hint' => 'unexpected_error']), 500);
        });
    })->create();
