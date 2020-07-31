<?php

namespace Ada\HttpProxy;

class ConnectionException extends ApiException
{
    public function __construct($message, $code = 0, $previous = null)
    {
        parent::__construct([
            'message' => $message,
            'code'    => $code,
        ], $previous);
    }
}
