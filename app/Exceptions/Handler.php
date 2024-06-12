<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

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
    $this->reportable(function (Throwable $e) {
      //
    });
  }

  public function render($request, Throwable $exception)
  {
    if ($exception instanceof MethodNotAllowedHttpException) {
      return redirect()->route('error');
    }

    if ($exception instanceof QueryException) {
      // Check if the error is due to too many connections
      if ($exception->getCode() == 1040) {
        return redirect()->route('login')->withErrors(['error' => 'Please retry again or signup if you still don\'t have an acount.']);
      }
    }

    return parent::render($request, $exception);
  }
}
