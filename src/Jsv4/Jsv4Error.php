<?php

namespace Jsv4;

class Jsv4Error extends \Exception
{
    public $code;
    public $dataPath;
    public $schemaPath;
    public $message;

    public function __construct($code, $dataPath, $schemaPath, $errorMessage, $subResults = null)
    {
        parent::__construct($errorMessage);
        $this->code = $code;
        $this->dataPath = $dataPath;
        $this->schemaPath = $schemaPath;
        $this->message = $errorMessage;
        if ($subResults) {
            $this->subResults = $subResults;
        }
    }

    public function prefix($dataPrefix, $schemaPrefix)
    {
        return new Jsv4Error(
            $this->code,
            $dataPrefix . $this->dataPath,
            $schemaPrefix . $this->schemaPath,
            $this->message
        );
    }
}
