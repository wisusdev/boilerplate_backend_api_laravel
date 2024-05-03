<?php

namespace App\Exceptions;

use App\Http\Responses\JsonApiValidationErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (NotFoundHttpException $e) {
            throw new JsonApi\NotFoundHttpException;
        });

        $this->renderable(function (BadRequestHttpException $e) {
            throw new JsonApi\BadRequestHttpException($e->getMessage());
        });

        $this->renderable(function (AuthenticationException $e) {
            throw new JsonApi\AuthenticationException;
        });

        $this->renderable(function (AccessDeniedHttpException $e) {
            throw new JsonApi\UnauthorizedException;
        });
    }

    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        if(! $request->routeIs('api.v1.auth.login') && ! $request->routeIs('api.v1.auth.register')){
            return new JsonApiValidationErrorResponse($exception);
        }
        return parent::invalidJson($request, $exception);
    }
}
