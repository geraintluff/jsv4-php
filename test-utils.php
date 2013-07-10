<?php

function recursiveEqual($a, $b) {
	if (is_object($a)) {
		if (!is_object($b)) {
			return FALSE;
		}
		foreach ($a as $key => $value) {
			if (!isset($b->$key)) {
				return FALSE;
			}
			if (!recursiveEqual($value, $b->$key)) {
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
			if (!recursiveEqual($value, $b[$key])) {
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

function pointerGet(&$value, $path="", $strict=FALSE) {
	if ($path == "") {
		return $value;
	} else if ($path[0] != "/") {
		throw new Exception("Invalid path: $path");
	}
	$parts = explode("/", $path);
	array_shift($parts);
	foreach ($parts as $part) {
		$part = str_replace("~1", "/", $part);
		$part = str_replace("~0", "~", $part);
		if (is_array($value) && is_numeric($part)) {
			$value =& $value[$part];
		} else if (is_object($value)) {
			if (isset($value->$part)) {
				$value =& $value->$part;
			} else if ($strict) {
				throw new Exception("Path does not exist: $path");
			} else {
				return NULL;
			}
		} else if ($strict) {
			throw new Exception("Path does not exist: $path");
		} else {
			return NULL;
		}
	}
	return $value;
}

function pointerJoin($parts) {
	$result = "";
	foreach ($parts as $part) {
		$part = str_replace("~", "~0", $part);
		$part = str_replace("/", "~1", $part);
		$result .= "/".$part;
	}
	return $result;
}

?>