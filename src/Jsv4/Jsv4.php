<?php

namespace Jsv4;

class Jsv4
{
    const JSV4_INVALID_TYPE = 0;
    const JSV4_ENUM_MISMATCH = 1;
    const JSV4_ANY_OF_MISSING = 10;
    const JSV4_ONE_OF_MISSING = 11;
    const JSV4_ONE_OF_MULTIPLE = 12;
    const JSV4_NOT_PASSED = 13;
    // Numeric errors
    const JSV4_NUMBER_MULTIPLE_OF = 100;
    const JSV4_NUMBER_MINIMUM = 101;
    const JSV4_NUMBER_MINIMUM_EXCLUSIVE = 102;
    const JSV4_NUMBER_MAXIMUM = 103;
    const JSV4_NUMBER_MAXIMUM_EXCLUSIVE = 104;
    // String errors
    const JSV4_STRING_LENGTH_SHORT = 200;
    const JSV4_STRING_LENGTH_LONG = 201;
    const JSV4_STRING_PATTERN = 202;
    // Object errors
    const JSV4_OBJECT_PROPERTIES_MINIMUM = 300;
    const JSV4_OBJECT_PROPERTIES_MAXIMUM = 301;
    const JSV4_OBJECT_REQUIRED = 302;
    const JSV4_OBJECT_ADDITIONAL_PROPERTIES = 303;
    const JSV4_OBJECT_DEPENDENCY_KEY = 304;
    // Array errors
    const JSV4_ARRAY_LENGTH_SHORT = 400;
    const JSV4_ARRAY_LENGTH_LONG = 401;
    const JSV4_ARRAY_UNIQUE = 402;
    const JSV4_ARRAY_ADDITIONAL_ITEMS = 403;
    
    private $data;
    private $schema;
    private $firstErrorOnly;
    private $coerce;
    
    public $valid;
    public $errors;
    
    public static function validate($data, $schema)
    {
        return new Jsv4($data, $schema);
    }

    public static function isValid($data, $schema)
    {
        $result = new Jsv4($data, $schema, true);
        return $result->valid;
    }

    public static function coerce($data, $schema)
    {
        if (is_object($data) || is_array($data)) {
            $data = unserialize(serialize($data));
        }
        $result = new Jsv4($data, $schema, false, true);
        if ($result->valid) {
            $result->value = $result->data;
        }
        return $result;
    }

    public static function pointerJoin($parts)
    {
        $result = "";
        foreach ($parts as $part) {
            $part = str_replace("~", "~0", $part);
            $part = str_replace("/", "~1", $part);
            $result .= "/" . $part;
        }
        return $result;
    }

    public static function recursiveEqual($a, $b)
    {
        if (is_object($a)) {
            if (!is_object($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!isset($b->$key)) {
                    return false;
                }
                if (!self::recursiveEqual($value, $b->$key)) {
                    return false;
                }
            }
            foreach ($b as $key => $value) {
                if (!isset($a->$key)) {
                    return false;
                }
            }
            return true;
        }
        if (is_array($a)) {
            if (!is_array($b)) {
                return false;
            }
            foreach ($a as $key => $value) {
                if (!isset($b[$key])) {
                    return false;
                }
                if (!self::recursiveEqual($value, $b[$key])) {
                    return false;
                }
            }
            foreach ($b as $key => $value) {
                if (!isset($a[$key])) {
                    return false;
                }
            }
            return true;
        }
        return $a === $b;
    }

    private function __construct(&$data, $schema, $firstErrorOnly = false, $coerce = false)
    {
        $this->data = & $data;
        $this->schema = & $schema;
        $this->firstErrorOnly = $firstErrorOnly;
        $this->coerce = $coerce;
        $this->valid = true;
        $this->errors = array();

        try {
            $this->checkTypes();
            $this->checkEnum();
            $this->checkObject();
            $this->checkArray();
            $this->checkString();
            $this->checkNumber();
            $this->checkComposite();
        } catch (Jsv4Error $e) {
        }
    }

    private function fail($code, $dataPath, $schemaPath, $errorMessage, $subErrors = null)
    {
        $this->valid = false;
        $error = new Jsv4Error($code, $dataPath, $schemaPath, $errorMessage, $subErrors);
        $this->errors[] = $error;
        if ($this->firstErrorOnly) {
            throw $error;
        }
    }

    private function subResult(&$data, $schema, $allowCoercion = true)
    {
        return new Jsv4($data, $schema, $this->firstErrorOnly, $allowCoercion && $this->coerce);
    }

    private function includeSubResult($subResult, $dataPrefix, $schemaPrefix)
    {
        if (!$subResult->valid) {
            $this->valid = false;
            foreach ($subResult->errors as $error) {
                $this->errors[] = $error->prefix($dataPrefix, $schemaPrefix);
            }
        }
    }

    private function checkTypes()
    {
        if (isset($this->schema->type)) {
            $types = $this->schema->type;
            if (!is_array($types)) {
                $types = array($types);
            }
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
                } elseif ($type == "null" && $this->data === null) {
                    return;
                }
            }

            if ($this->coerce) {
                foreach ($types as $type) {
                    if ($type == "number") {
                        if (is_numeric($this->data)) {
                            $this->data = (float) $this->data;
                            return;
                        } elseif (is_bool($this->data)) {
                            $this->data = $this->data ? 1 : 0;
                            return;
                        }
                    } elseif ($type == "integer") {
                        if ((int) $this->data == $this->data) {
                            $this->data = (int) $this->data;
                            return;
                        }
                    } elseif ($type == "string") {
                        if (is_numeric($this->data)) {
                            $this->data = "" . $this->data;
                            return;
                        } elseif (is_bool($this->data)) {
                            $this->data = ($this->data) ? "true" : "false";
                            return;
                        } elseif (is_null($this->data)) {
                            $this->data = "";
                            return;
                        }
                    } elseif ($type == "boolean") {
                        if (is_numeric($this->data)) {
                            $this->data = ($this->data != "0");
                            return;
                        } elseif ($this->data == "yes" || $this->data == "true") {
                            $this->data = true;
                            return;
                        } elseif ($this->data == "no" || $this->data == "false") {
                            $this->data = false;
                            return;
                        } elseif ($this->data == null) {
                            $this->data = false;
                            return;
                        }
                    }
                }
            }

            $type = strtolower(gettype($this->data));
            if ($type == "double") {
                $type = ((int) $this->data == $this->data) ? "integer" : "number";
            } elseif ($type == "null") {
                $type = "null";
            }
            $this->fail(self::JSV4_INVALID_TYPE, "", "/type", "Invalid type: $type");
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
            $this->fail(self::JSV4_ENUM_MISMATCH, "", "/enum", "Value must be one of the enum options");
        }
    }

    private function checkObject()
    {
        if (!is_object($this->data)) {
            return;
        }
        if (isset($this->schema->required)) {
            foreach ($this->schema->required as $index => $key) {
                if (!array_key_exists($key, (array) $this->data)) {
                    if ($this->coerce && $this->createValueForProperty($key)) {
                        continue;
                    }
                    $this->fail(self::JSV4_OBJECT_REQUIRED, "", "/required/{$index}", "Missing required property: {$key}");
                }
            }
        }
        $checkedProperties = array();
        if (isset($this->schema->properties)) {
            foreach ($this->schema->properties as $key => $subSchema) {
                $checkedProperties[$key] = true;
                if (array_key_exists($key, (array) $this->data)) {
                    $subResult = $this->subResult($this->data->$key, $subSchema);
                    $this->includeSubResult(
                        $subResult,
                        self::pointerJoin(array($key)),
                        self::pointerJoin(array("properties", $key))
                    );
                }
            }
        }
        if (isset($this->schema->patternProperties)) {
            foreach ($this->schema->patternProperties as $pattern => $subSchema) {
                foreach ($this->data as $key => &$subValue) {
                    if (preg_match("/" . str_replace("/", "\\/", $pattern) . "/", $key)) {
                        $checkedProperties[$key] = true;
                        $subResult = $this->subResult($this->data->$key, $subSchema);
                        $this->includeSubResult(
                            $subResult,
                            self::pointerJoin(array($key)),
                            self::pointerJoin(array("patternProperties", $pattern))
                        );
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
                    $this->fail(self::JSV4_OBJECT_ADDITIONAL_PROPERTIES, self::pointerJoin(array($key)), "/additionalProperties", "Additional properties not allowed");
                } elseif (is_object($additionalProperties)) {
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
                } elseif (is_array($dep)) {
                    foreach ($dep as $index => $depKey) {
                        if (!isset($this->data->$depKey)) {
                            $this->fail(self::JSV4_OBJECT_DEPENDENCY_KEY, "", self::pointerJoin(array("dependencies", $key, $index)), "Property $key depends on $depKey");
                        }
                    }
                } else {
                    if (!isset($this->data->$dep)) {
                        $this->fail(self::JSV4_OBJECT_DEPENDENCY_KEY, "", self::pointerJoin(array("dependencies", $key)), "Property $key depends on $dep");
                    }
                }
            }
        }
        if (isset($this->schema->minProperties)) {
            if (count(get_object_vars($this->data)) < $this->schema->minProperties) {
                $this->fail(self::JSV4_OBJECT_PROPERTIES_MINIMUM, "", "/minProperties", ($this->schema->minProperties == 1) ? "Object cannot be empty" : "Object must have at least {$this->schema->minProperties} defined properties");
            }
        }
        if (isset($this->schema->maxProperties)) {
            if (count(get_object_vars($this->data)) > $this->schema->maxProperties) {
                $this->fail(self::JSV4_OBJECT_PROPERTIES_MAXIMUM, "", "/minProperties", ($this->schema->maxProperties == 1) ? "Object must have at most one defined property" : "Object must have at most {$this->schema->maxProperties} defined properties");
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
                    } elseif (isset($this->schema->additionalItems)) {
                        $additionalItems = $this->schema->additionalItems;
                        if (!$additionalItems) {
                            $this->fail(self::JSV4_ARRAY_ADDITIONAL_ITEMS, "/{$index}", "/additionalItems", "Additional items (index " . count($items) . " or more) are not allowed");
                        } elseif ($additionalItems !== true) {
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
                $this->fail(self::JSV4_ARRAY_LENGTH_SHORT, "", "/minItems", "Array is too short (must have at least {$this->schema->minItems} items)");
            }
        }
        if (isset($this->schema->maxItems)) {
            if (count($this->data) > $this->schema->maxItems) {
                $this->fail(self::JSV4_ARRAY_LENGTH_LONG, "", "/maxItems", "Array is too long (must have at most {$this->schema->maxItems} items)");
            }
        }
        if (isset($this->schema->uniqueItems)) {
            foreach ($this->data as $indexA => $itemA) {
                foreach ($this->data as $indexB => $itemB) {
                    if ($indexA < $indexB) {
                        if (self::recursiveEqual($itemA, $itemB)) {
                            $this->fail(self::JSV4_ARRAY_UNIQUE, "", "/uniqueItems", "Array items must be unique (items $indexA and $indexB)");
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
            if (strlen($this->data) < $this->schema->minLength) {
                $this->fail(self::JSV4_STRING_LENGTH_SHORT, "", "/minLength", "String must be at least {$this->schema->minLength} characters long");
            }
        }
        if (isset($this->schema->maxLength)) {
            if (strlen($this->data) > $this->schema->maxLength) {
                $this->fail(self::JSV4_STRING_LENGTH_LONG, "", "/maxLength", "String must be at most {$this->schema->maxLength} characters long");
            }
        }
        if (isset($this->schema->pattern)) {
            $pattern = $this->schema->pattern;
            $patternFlags = isset($this->schema->patternFlags) ? $this->schema->patternFlags : '';
            $result = preg_match("/" . str_replace("/", "\\/", $pattern) . "/" . $patternFlags, $this->data);
            if ($result === 0) {
                $this->fail(self::JSV4_STRING_PATTERN, "", "/pattern", "String does not match pattern: $pattern");
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
                $this->fail(self::JSV4_NUMBER_MULTIPLE_OF, "", "/multipleOf", "Number must be a multiple of {$this->schema->multipleOf}");
            }
        }
        if (isset($this->schema->minimum)) {
            $minimum = $this->schema->minimum;
            if (isset($this->schema->exclusiveMinimum) && $this->schema->exclusiveMinimum) {
                if ($this->data <= $minimum) {
                    $this->fail(self::JSV4_NUMBER_MINIMUM_EXCLUSIVE, "", "", "Number must be > $minimum");
                }
            } else {
                if ($this->data < $minimum) {
                    $this->fail(self::JSV4_NUMBER_MINIMUM, "", "/minimum", "Number must be >= $minimum");
                }
            }
        }
        if (isset($this->schema->maximum)) {
            $maximum = $this->schema->maximum;
            if (isset($this->schema->exclusiveMaximum) && $this->schema->exclusiveMaximum) {
                if ($this->data >= $maximum) {
                    $this->fail(self::JSV4_NUMBER_MAXIMUM_EXCLUSIVE, "", "", "Number must be < $maximum");
                }
            } else {
                if ($this->data > $maximum) {
                    $this->fail(self::JSV4_NUMBER_MAXIMUM, "", "/maximum", "Number must be <= $maximum");
                }
            }
        }
    }

    private function checkComposite()
    {
        if (isset($this->schema->allOf)) {
            foreach ($this->schema->allOf as $index => $subSchema) {
                $subResult = $this->subResult($this->data, $subSchema, false);
                $this->includeSubResult($subResult, "", "/allOf/" . (int) $index);
            }
        }
        if (isset($this->schema->anyOf)) {
            $failResults = array();
            foreach ($this->schema->anyOf as $index => $subSchema) {
                $subResult = $this->subResult($this->data, $subSchema, false);
                if ($subResult->valid) {
                    return;
                }
                $failResults[] = $subResult;
            }
            $this->fail(self::JSV4_ANY_OF_MISSING, "", "/anyOf", "Value must satisfy at least one of the options", $failResults);
        }
        if (isset($this->schema->oneOf)) {
            $failResults = array();
            $successIndex = null;
            foreach ($this->schema->oneOf as $index => $subSchema) {
                $subResult = $this->subResult($this->data, $subSchema, false);
                if ($subResult->valid) {
                    if ($successIndex === null) {
                        $successIndex = $index;
                    } else {
                        $this->fail(self::JSV4_ONE_OF_MULTIPLE, "", "/oneOf", "Value satisfies more than one of the options ($successIndex and $index)");
                    }
                    continue;
                }
                $failResults[] = $subResult;
            }
            if ($successIndex === null) {
                $this->fail(self::JSV4_ONE_OF_MISSING, "", "/oneOf", "Value must satisfy one of the options", $failResults);
            }
        }
        if (isset($this->schema->not)) {
            $subResult = $this->subResult($this->data, $this->schema->not, false);
            if ($subResult->valid) {
                $this->fail(self::JSV4_NOT_PASSED, "", "/not", "Value satisfies prohibited schema");
            }
        }
    }

    private function createValueForProperty($key)
    {
        $schema = null;
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
                return true;
            }
            if (isset($schema->type)) {
                $types = is_array($schema->type) ? $schema->type : array($schema->type);
                if (in_array("null", $types)) {
                    $this->data->$key = null;
                } elseif (in_array("boolean", $types)) {
                    $this->data->$key = true;
                } elseif (in_array("integer", $types) || in_array("number", $types)) {
                    $this->data->$key = 0;
                } elseif (in_array("string", $types)) {
                    $this->data->$key = "";
                } elseif (in_array("object", $types)) {
                    $this->data->$key = new \StdClass;
                } elseif (in_array("array", $types)) {
                    $this->data->$key = array();
                } else {
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}
