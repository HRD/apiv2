<?php

namespace HRDBase\Api\Exceptions;

use Exception;

/**
 * Class HRDApiIncorrectDataReceivedException
 */
class HRDApiIncorrectDataReceivedException extends HRDApiException
{
    /**
     * @inheritdoc
     */
    public function __construct($message = null)
    {
        $previous = $message ? new Exception($message) : null;
        parent::__construct(
            'Internal server error  - data received. Please try again or contact server administrator.',
            null,
            $previous
        );
    }
}
