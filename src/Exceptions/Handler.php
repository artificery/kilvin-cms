<?php

namespace Kilvin\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Kilvin\Exceptions\CmsTemplateException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        HttpException::class
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof TokenMismatchException){
            return $this->sendCsrfErrorPage();
        }

        // Demo server, unwritable check
        if ($e instanceof \Illuminate\Database\QueryException) {
            if (preg_match('/([A-Z]+) command denied to user/i', $e->getMessage(), $match)) {
                if (REQUEST === 'CP') {
                    $vars = [
                        'title'     => 'Database Error',
                        'errors'    => (array) sprintf('Your DB user does not have %s permissions.', $match[1])
                    ];

                    return response()->view('kilvin::cp.errors.error', $vars, 400);
                }

                exit(sprintf('Your DB user does not have %s permissions.', $match[1]));
            }
        }

        // Something has gone horribly wrong
        if ($e instanceof CmsFatalException){
            return response()->view('kilvin::fatal-error', ['message' => $e->getMessage()], 500);
        }

        // The CMS tried to do something it could not do
        if ($e instanceof CmsFailureException){
            if (REQUEST === 'CP') {
                $vars = [
                    'title'     => 'Fatal Error',
                    'errors'    => (array) $e->getMessage()
                ];

                return response()->view('kilvin::cp.errors.error', $vars, 400);
            } else {
                return response()->view('_errors.400', ['error_name' => $e->getMessage()], 400);
            }
        }

        if ($e instanceof CmsCpPageNotFound) {
           $vars = [
                'title'  => 'Page Not Found',
                'errors' => ['Oh, dear me. How did that happen? Well, sorry about that. We do apologize.'],
                'link'   => ['url' => 'JavaScript:history.go(-1)', 'name' => __('kilvin::core.go_back')]
            ];

            return response()->view('kilvin::cp.errors.error', $vars, 404);
        }

        // Something went wrong when rendering the template
        if ($e instanceof CmsTemplateException ) {
            if (config('app.debug') == false) {
                return response()->view('_errors.500', [], 500);
            }
        }

        if ($e instanceof NotFoundHttpException) {
            if (REQUEST === 'SITE') {
                // Should be in the ./templates directory
                return response()->view('_errors.404', [], 404);
            }
        }

        if ($e instanceof HttpException) {

            $http_code = $e->getStatusCode();

            // Kilvin HTTP Errors for Sites, uses _global directory
            if (REQUEST === 'SITE' && view()->exists("_errors.".$http_code)) {
                return response()->view('_errors.'.$http_code, [], $http_code);
            }

            return $this->renderHttpException($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Send CSRF Error page
     *
     */
    protected function sendCsrfErrorPage()
    {
        if (REQUEST === 'CP') {
            $vars = [
                'title'  => 'Invalid CSRF Token',
                'errors' => ['Oops! Your form submission failed because of a missing or invalid CSRF token.'],
                'link'   => ['url' => 'JavaScript:history.go(-1)', 'name' => __('kilvin::core.go_back')]
            ];

            return response()->view('kilvin::cp.errors.error', $vars, 500);
        }

        exit('Invalid CSRF Token');
    }
}
