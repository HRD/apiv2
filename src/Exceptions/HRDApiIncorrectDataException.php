<?php

namespace HRDBase\Api\Exceptions;

use Exception;

/**
 * Class HRDApiIncorrectDataException
 */
class HRDApiIncorrectDataException extends HRDApiException
{
    /**
     * @inheritdoc
     */
    public function __construct($message = null)
    {
        $previous = $message ? new Exception($message) : null;
        parent::__construct('Entered information is not valid.', null, $previous);
    }
}
