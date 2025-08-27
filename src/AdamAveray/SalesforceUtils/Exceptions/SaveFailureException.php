<?php
declare(strict_types=1);

namespace AdamAveray\SalesforceUtils\Exceptions;

use Phpforce\SoapClient\Result\SaveResult;

class SaveFailureException extends \RuntimeException
{
    private $result;

    /**
     * @param SaveResult $result
     * @param \Throwable|null $previous
     */
    public function __construct(SaveResult $result, ?\Throwable $previous = null)
    {
        $this->result = $result;
        $message = 'Save failure: ' . $result->getId();
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return SaveResult
     */
    public function getResult(): SaveResult
    {
        return $this->result;
    }
}
