<?php

/**
 * @author Martin Bažík <martin@bazo.sk>
 */
class ValidationException extends RuntimeException
{

	public $code;
	public $dataPath;
	public $schemaPath;
	public $message;

	public function __construct($code, $dataPath, $schemaPath, $errorMessage, $subResults = NULL)
	{
		parent::__construct($errorMessage);
		$this->code			 = $code;
		$this->dataPath		 = $dataPath;
		$this->schemaPath	 = $schemaPath;
		$this->message		 = $errorMessage;
		if ($subResults) {
			$this->subResults = $subResults;
		}
	}


	public function prefix($dataPrefix, $schemaPrefix)
	{
		return new ValidationException($this->code, $dataPrefix . $this->dataPath, $schemaPrefix . $this->schemaPath, $this->message);
	}


}
