<?php

namespace App\Exceptions;

use RuntimeException;

/** A game rule prevented the action (shown to the facilitator, HTTP 409/422). */
class GameException extends RuntimeException
{
    public function __construct(string $message, public int $status = 409)
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->status);
        }

        return back()->with('error', $this->getMessage());
    }
}
