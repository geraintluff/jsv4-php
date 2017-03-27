<?php

namespace Jsv4;


class Validator
{

	const INVALID_TYPE				 = 0;
	const ENUM_MISMATCH				 = 1;
	const ANY_OF_MISSING				 = 10;
	const ONE_OF_MISSING				 = 11;
	const ONE_OF_MULTIPLE				 = 12;
	const NOT_PASSED					 = 13;
	// Numeric errors
	const NUMBER_MULTIPLE_OF			 = 100;
	const NUMBER_MINIMUM				 = 101;
	const NUMBER_MINIMUM_EXCLUSIVE	 = 102;
	const NUMBER_MAXIMUM				 = 103;
	const NUMBER_MAXIMUM_EXCLUSIVE	 = 104;
	// String errors
	const STRING_LENGTH_SHORT			 = 200;
	const STRING_LENGTH_LONG			 = 201;
	const STRING_PATTERN				 = 202;
	// Object errors
	const OBJECT_PROPERTIES_MINIMUM	 = 300;
	const OBJECT_PROPERTIES_MAXIMUM	 = 301;
	const OBJECT_REQUIRED				 = 302;
	const OBJECT_ADDITIONAL_PROPERTIES = 303;
	const OBJECT_DEPENDENCY_KEY		 = 304;
	// Array errors
	const ARRAY_LENGTH_SHORT			 = 400;
	const ARRAY_LENGTH_LONG			 = 401;
	const ARRAY_UNIQUE				 = 402;
	const ARRAY_ADDITIONAL_ITEMS		 = 403;

	private $data;
	private $schema;
	private $firstErrorOnly;
	private $coerce;
	public $valid;
	public $errors;

	private function __construct(&$data, $schema, $firstErrorOnly = FALSE, $coerce = FALSE)
	{
		$this->data				 = & $data;
		$this->schema			 = & $schema;
		$this->firstErrorOnly	 = $firstErrorOnly;
		$this->coerce			 = $coerce;
		$this->valid			 = TRUE;
		$this->errors			 = [];

		try {
			$this->checkTypes();
			$this->checkEnum();
			$this->checkObject();
			$this->checkArray();
			$this->checkString();
			$this->checkNumber();
			$this->checkComposite();
		} catch (ValidationException $e) {

		}
	}


	static public function validate($data, $schema)
	{
		return new Validator($data, $schema);
	}


	static public function isValid($data, $schema)
	{
		$result = new Validator($data, $schema, TRUE);
		return $result->valid;
	}


	static public function coerce($data, $schema)
	{
		if (is_object($data) || is_array($data)) {
			$data = unserialize(serialize($data));
		}
		$result = new Validator($data, $schema, FALSE, TRUE);
		if ($result->valid) {
			$result->value = $result->data;
		}
		return $result;
	}


	static public function pointerJoin($parts)
	{
		$result = "";
		foreach ($parts as $part) {
			$part	 = str_replace("~", "~0", $part);
			$part	 = str_replace("/", "~1", $part);
			$result .= "/" . $part;
		}
		return $result;
	}


	static public function recursiveEqual($a, $b)
	{
		if (is_object($a)) {
			if (!is_object($b)) {
				return FALSE;
			}
			foreach ($a as $key => $value) {
				if (!isset($b->$key)) {
					return FALSE;
				}
				if (!self::recursiveEqual($value, $b->$key)) {
					return FALSE;
				}
			}
			foreach ($b as $key => $value) {
				if (!isset($a->$key)) {
					return FALSE;
				}
			}
			return TRUE;
		}
		if (is_array($a)) {
			if (!is_array($b)) {
				return FALSE;
			}
			foreach ($a as $key => $value) {
				if (!isset($b[$key])) {
					return FALSE;
				}
				if (!self::recursiveEqual($value, $b[$key])) {
					return FALSE;
				}
			}
			foreach ($b as $key => $value) {
				if (!isset($a[$key])) {
					return FALSE;
				}
			}
			return TRUE;
		}
		return $a === $b;
	}


	private function fail($code, $dataPath, $schemaPath, $errorMessage, $subErrors = NULL)
	{
		$this->valid	 = FALSE;
		$error			 = new ValidationException($code, $dataPath, $schemaPath, $errorMessage, $subErrors);
		$this->errors[]	 = $error;
		if ($this->firstErrorOnly) {
			throw $error;
		}
	}


	private function subResult(&$data, $schema, $allowCoercion = TRUE)
	{
		return new Validator($data, $schema, $this->firstErrorOnly, $allowCoercion && $this->coerce);
	}


	private function includeSubResult($subResult, $dataPrefix, $schemaPrefix)
	{
		if (!$subResult->valid) {
			$this->valid = FALSE;
			foreach ($subResult->errors as $error) {
				$this->errors[] = $error->prefix($dataPrefix, $schemaPrefix);
			}
		}
	}


	private function checkTypes()
	{
		if (isset($this->schema->type)) {
			$types = (array) $this->schema->type;

			foreach ($types as $type) {
				if ($type == "object" && is_object($this->data)) {
					return;
				} elseif ($type == "array" && is_array($this->data)) {
					return;
				} elseif ($type == "string" && is_string($this->data)) {
					return;
				} elseif ($type == "number" && !is_string($this->data) && is_numeric($this->data)) {
					return;
				} elseif ($type == "integer" && is_int($this->data)) {
					return;
				} elseif ($type == "boolean" && is_bool($this->data)) {
					return;
				} elseif ($type == "null" && $this->data === NULL) {
					return;
				}
			}

			if ($this->coerce) {
				foreach ($types as $type) {
					if ($type == "number") {
						if (is_numeric($this->data)) {
							$this->data = (float) $this->data;
							return;
						} else if (is_bool($this->data)) {
							$this->data = $this->data ? 1 : 0;
							return;
						}
					} else if ($type == "integer") {
						if ((int) $this->data == $this->data) {
							$this->data = (int) $this->data;
							return;
						}
					} else if ($type == "string") {
						if (is_bool($this->data)) {
							$this->data = $this->data? "true" : "false";
							return;
						} else if (is_null($this->data)) {
							$this->data = "";
							return;
						} else if (is_scalar($this->data)) {
							$this->data = (string) $this->data;
							return;
						}
					} else if ($type == "boolean") {
						if (is_numeric($this->data)) {
							$this->data = ($this->data != "0");
							return;
						} else if ($this->data == "yes" || $this->data == "true") {
							$this->data = TRUE;
							return;
						} else if ($this->data == "no" || $this->data == "false") {
							$this->data = FALSE;
							return;
						} else if ($this->data == NULL) {
							$this->data = FALSE;
							return;
						}
					} else if ($type == "object") {
						if (is_array($this->data)) {
							$this->data = (object) $this->data;
							return;
						}
					} else if ($type == "array") {
						$this->data = (array) $this->data;
						return;
					}
				}
			}

			$type = gettype($this->data);
			if ($type == "double") {
				$type = ((int) $this->data == $this->data) ? "integer" : "number";
			} else if ($type == "NULL") {
				$type = "null";
			}
			$this->fail(self::INVALID_TYPE, "", "/type", "Invalid type: $type");
		}
	}


	private function checkEnum()
	{
		if (isset($this->schema->enum)) {
			foreach ($this->schema->enum as $option) {
				if (self::recursiveEqual($this->data, $option)) {
					return;
				}
			}
			$this->fail(self::ENUM_MISMATCH, "", "/enum", "Value must be one of the enum options");
		}
	}


	private function checkObject()
	{
		if (!is_object($this->data)) {
			return;
		}
		if (isset($this->schema->required)) {
			foreach ($this->schema->required as $index => $key) {
				if (!property_exists($this->data, $key)) {
					if ($this->coerce && $this->createValueForProperty($key)) {
						continue;
					}
					$this->fail(self::OBJECT_REQUIRED, "", "/required/{$index}", "Missing required property: {$key}");
				}
			}
		}
		$checkedProperties = [];
		if (isset($this->schema->properties)) {
			foreach ($this->schema->properties as $key => $subSchema) {
				$checkedProperties[$key] = TRUE;
				if (property_exists($this->data, $key)) {
					$subResult = $this->subResult($this->data->$key, $subSchema);
					$this->includeSubResult($subResult, self::pointerJoin(array($key)), self::pointerJoin(array("properties", $key)));
				}
			}
		}
		if (isset($this->schema->patternProperties)) {
			foreach ($this->schema->patternProperties as $pattern => $subSchema) {
				foreach ($this->data as $key => &$subValue) {
					if (preg_match("/" . str_replace("/", "\\/", $pattern) . "/", $key)) {
						$checkedProperties[$key] = TRUE;
						$subResult				 = $this->subResult($this->data->$key, $subSchema);
						$this->includeSubResult($subResult, self::pointerJoin(array($key)), self::pointerJoin(array("patternProperties", $pattern)));
					}
				}
			}
		}
		if (isset($this->schema->additionalProperties)) {
			$additionalProperties = $this->schema->additionalProperties;
			foreach ($this->data as $key => &$subValue) {
				if (isset($checkedProperties[$key])) {
					continue;
				}
				if (!$additionalProperties) {
					$this->fail(self::OBJECT_ADDITIONAL_PROPERTIES, self::pointerJoin(array($key)), "/additionalProperties", "Additional properties not allowed");
				} else if (is_object($additionalProperties)) {
					$subResult = $this->subResult($subValue, $additionalProperties);
					$this->includeSubResult($subResult, self::pointerJoin(array($key)), "/additionalProperties");
				}
			}
		}
		if (isset($this->schema->dependencies)) {
			foreach ($this->schema->dependencies as $key => $dep) {
				if (!isset($this->data->$key)) {
					continue;
				}
				if (is_object($dep)) {
					$subResult = $this->subResult($this->data, $dep);
					$this->includeSubResult($subResult, "", self::pointerJoin(array("dependencies", $key)));
				} else if (is_array($dep)) {
					foreach ($dep as $index => $depKey) {
						if (!isset($this->data->$depKey)) {
							$this->fail(self::OBJECT_DEPENDENCY_KEY, "", self::pointerJoin(array("dependencies", $key, $index)), "Property $key depends on $depKey");
						}
					}
				} else {
					if (!isset($this->data->$dep)) {
						$this->fail(self::OBJECT_DEPENDENCY_KEY, "", self::pointerJoin(array("dependencies", $key)), "Property $key depends on $dep");
					}
				}
			}
		}
		if (isset($this->schema->minProperties)) {
			if (count(get_object_vars($this->data)) < $this->schema->minProperties) {
				$this->fail(self::OBJECT_PROPERTIES_MINIMUM, "", "/minProperties", ($this->schema->minProperties == 1) ? "Object cannot be empty" : "Object must have at least {$this->schema->minProperties} defined properties");
			}
		}
		if (isset($this->schema->maxProperties)) {
			if (count(get_object_vars($this->data)) > $this->schema->maxProperties) {
				$this->fail(self::OBJECT_PROPERTIES_MAXIMUM, "", "/minProperties", ($this->schema->maxProperties == 1) ? "Object must have at most one defined property" : "Object must have at most {$this->schema->maxProperties} defined properties");
			}
		}
	}


	private function checkArray()
	{
		if (!is_array($this->data)) {
			return;
		}
		if (isset($this->schema->items)) {
			$items = $this->schema->items;
			if (is_array($items)) {
				foreach ($this->data as $index => &$subData) {
					if (!is_numeric($index)) {
						throw new Exception("Arrays must only be numerically-indexed");
					}
					if (isset($items[$index])) {
						$subResult = $this->subResult($subData, $items[$index]);
						$this->includeSubResult($subResult, "/{$index}", "/items/{$index}");
					} else if (isset($this->schema->additionalItems)) {
						$additionalItems = $this->schema->additionalItems;
						if (!$additionalItems) {
							$this->fail(self::ARRAY_ADDITIONAL_ITEMS, "/{$index}", "/additionalItems", "Additional items (index " . count($items) . " or more) are not allowed");
						} else if ($additionalItems !== TRUE) {
							$subResult = $this->subResult($subData, $additionalItems);
							$this->includeSubResult($subResult, "/{$index}", "/additionalItems");
						}
					}
				}
			} else {
				foreach ($this->data as $index => &$subData) {
					if (!is_numeric($index)) {
						throw new Exception("Arrays must only be numerically-indexed");
					}
					$subResult = $this->subResult($subData, $items);
					$this->includeSubResult($subResult, "/{$index}", "/items");
				}
			}
		}
		if (isset($this->schema->minItems)) {
			if (count($this->data) < $this->schema->minItems) {
				$this->fail(self::ARRAY_LENGTH_SHORT, "", "/minItems", "Array is too short (must have at least {$this->schema->minItems} items)");
			}
		}
		if (isset($this->schema->maxItems)) {
			if (count($this->data) > $this->schema->maxItems) {
				$this->fail(self::ARRAY_LENGTH_LONG, "", "/maxItems", "Array is too long (must have at most {$this->schema->maxItems} items)");
			}
		}
		if (isset($this->schema->uniqueItems)) {
			foreach ($this->data as $indexA => $itemA) {
				foreach ($this->data as $indexB => $itemB) {
					if ($indexA < $indexB) {
						if (self::recursiveEqual($itemA, $itemB)) {
							$this->fail(self::ARRAY_UNIQUE, "", "/uniqueItems", "Array items must be unique (items $indexA and $indexB)");
							break 2;
						}
					}
				}
			}
		}
	}


	private function checkString()
	{
		if (!is_string($this->data)) {
			return;
		}
		if (isset($this->schema->minLength)) {
			if (mb_strlen($this->data) < $this->schema->minLength) {
				$this->fail(self::STRING_LENGTH_SHORT, "", "/minLength", "String must be at least {$this->schema->minLength} characters long");
			}
		}
		if (isset($this->schema->maxLength)) {
			if (mb_strlen($this->data) > $this->schema->maxLength) {
				$this->fail(self::STRING_LENGTH_LONG, "", "/maxLength", "String must be at most {$this->schema->maxLength} characters long");
			}
		}
		if (isset($this->schema->pattern)) {
			$pattern		 = $this->schema->pattern;
			$patternFlags	 = isset($this->schema->patternFlags) ? $this->schema->patternFlags : '';
			$result			 = preg_match("/" . str_replace("/", "\\/", $pattern) . "/" . $patternFlags, $this->data);
			if ($result === 0) {
				$this->fail(self::STRING_PATTERN, "", "/pattern", "String does not match pattern: $pattern");
			}
		}
	}


	private function checkNumber()
	{
		if (is_string($this->data) || !is_numeric($this->data)) {
			return;
		}
		if (isset($this->schema->multipleOf)) {
			if (fmod($this->data / $this->schema->multipleOf, 1) != 0) {
				$this->fail(self::NUMBER_MULTIPLE_OF, "", "/multipleOf", "Number must be a multiple of {$this->schema->multipleOf}");
			}
		}
		if (isset($this->schema->minimum)) {
			$minimum = $this->schema->minimum;
			if (isset($this->schema->exclusiveMinimum) && $this->schema->exclusiveMinimum) {
				if ($this->data <= $minimum) {
					$this->fail(self::NUMBER_MINIMUM_EXCLUSIVE, "", "", "Number must be > $minimum");
				}
			} else {
				if ($this->data < $minimum) {
					$this->fail(self::NUMBER_MINIMUM, "", "/minimum", "Number must be >= $minimum");
				}
			}
		}
		if (isset($this->schema->maximum)) {
			$maximum = $this->schema->maximum;
			if (isset($this->schema->exclusiveMaximum) && $this->schema->exclusiveMaximum) {
				if ($this->data >= $maximum) {
					$this->fail(self::NUMBER_MAXIMUM_EXCLUSIVE, "", "", "Number must be < $maximum");
				}
			} else {
				if ($this->data > $maximum) {
					$this->fail(self::NUMBER_MAXIMUM, "", "/maximum", "Number must be <= $maximum");
				}
			}
		}
	}


	private function checkComposite()
	{
		if (isset($this->schema->allOf)) {
			foreach ($this->schema->allOf as $index => $subSchema) {
				$subResult = $this->subResult($this->data, $subSchema, FALSE);
				$this->includeSubResult($subResult, "", "/allOf/" . (int) $index);
			}
		}
		if (isset($this->schema->anyOf)) {
			$failResults = [];
			foreach ($this->schema->anyOf as $index => $subSchema) {
				$subResult = $this->subResult($this->data, $subSchema, FALSE);
				if ($subResult->valid) {
					return;
				}
				$failResults[] = $subResult;
			}
			$this->fail(self::ANY_OF_MISSING, "", "/anyOf", "Value must satisfy at least one of the options", $failResults);
		}
		if (isset($this->schema->oneOf)) {
			$failResults	 = [];
			$successIndex	 = NULL;
			foreach ($this->schema->oneOf as $index => $subSchema) {
				$subResult = $this->subResult($this->data, $subSchema, FALSE);
				if ($subResult->valid) {
					if ($successIndex === NULL) {
						$successIndex = $index;
					} else {
						$this->fail(self::ONE_OF_MULTIPLE, "", "/oneOf", "Value satisfies more than one of the options ($successIndex and $index)");
					}
					continue;
				}
				$failResults[] = $subResult;
			}
			if ($successIndex === NULL) {
				$this->fail(self::ONE_OF_MISSING, "", "/oneOf", "Value must satisfy one of the options", $failResults);
			}
		}
		if (isset($this->schema->not)) {
			$subResult = $this->subResult($this->data, $this->schema->not, FALSE);
			if ($subResult->valid) {
				$this->fail(self::NOT_PASSED, "", "/not", "Value satisfies prohibited schema");
			}
		}
	}


	private function createValueForProperty($key)
	{
		$schema = NULL;
		if (isset($this->schema->properties->$key)) {
			$schema = $this->schema->properties->$key;
		} else if (isset($this->schema->patternProperties)) {
			foreach ($this->schema->patternProperties as $pattern => $subSchema) {
				if (preg_match("/" . str_replace("/", "\\/", $pattern) . "/", $key)) {
					$schema = $subSchema;
					break;
				}
			}
		}
		if (!$schema && isset($this->schema->additionalProperties)) {
			$schema = $this->schema->additionalProperties;
		}
		if ($schema) {
			if (isset($schema->default)) {
				$this->data->$key = unserialize(serialize($schema->default));
				return TRUE;
			}
			if (isset($schema->type)) {
				$types = is_array($schema->type) ? $schema->type : array($schema->type);
				if (in_array("null", $types)) {
					$this->data->$key = NULL;
				} elseif (in_array("boolean", $types)) {
					$this->data->$key = TRUE;
				} elseif (in_array("integer", $types) || in_array("number", $types)) {
					$this->data->$key = 0;
				} elseif (in_array("string", $types)) {
					$this->data->$key = "";
				} elseif (in_array("object", $types)) {
					$this->data->$key = new \StdClass;
				} elseif (in_array("array", $types)) {
					$this->data->$key = [];
				} else {
					return FALSE;
				}
			}
			return TRUE;
		}
		return FALSE;
	}


}
