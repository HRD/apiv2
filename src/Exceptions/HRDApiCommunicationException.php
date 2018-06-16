<?php

namespace HRDBase\Api\Exceptions;

use Exception;

class HRDApiCommunicationException extends HRDApiException
{
    /**
     * @inheritdoc
     */
    public function __construct($type = null, $message = null)
    {
        $previous = $type ? new Exception($type) : null;

        parent::__construct(
            $message ?? 'Internal server error - connection. Please try again or contact server administrator.',
            null,
            $previous
        );
    }
}
