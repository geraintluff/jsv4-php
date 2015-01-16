<?php

function recursiveEqual($a, $b)
{
    if (is_object($a)) {
        if (!is_object($b)) {
            return false;
        }
        foreach ($a as $key => $value) {
            if (!isset($b->$key)) {
                return false;
            }
            if (!recursiveEqual($value, $b->$key)) {
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
            if (!recursiveEqual($value, $b[$key])) {
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

function pointerGet(&$value, $path = "", $strict = false)
{
    if ($path == "") {
        return $value;
    } elseif ($path[0] != "/") {
        throw new Exception("Invalid path: $path");
    }
    $parts = explode("/", $path);
    array_shift($parts);
    foreach ($parts as $part) {
        $part = str_replace("~1", "/", $part);
        $part = str_replace("~0", "~", $part);
        if (is_array($value) && is_numeric($part)) {
            $value = & $value[$part];
        } elseif (is_object($value)) {
            if (isset($value->$part)) {
                $value = & $value->$part;
            } elseif ($strict) {
                throw new Exception("Path does not exist: $path");
            } else {
                return null;
            }
        } elseif ($strict) {
            throw new Exception("Path does not exist: $path");
        } else {
            return null;
        }
    }
    return $value;
}

function pointerJoin($parts)
{
    $result = "";
    foreach ($parts as $part) {
        $part = str_replace("~", "~0", $part);
        $part = str_replace("/", "~1", $part);
        $result .= "/" . $part;
    }
    return $result;
}
