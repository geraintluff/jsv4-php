<?php

class SchemaStore {
	private static function pointerGet(&$value, $path="", $strict=FALSE) {
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
	
	private static function isNumericArray($array) {
		$count = count($array);
		for ($i = 0; $i < $count; $i++) {
			if (!isset($array[$i])) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	private static function resolveUrl($base, $relative) {
		if (parse_url($relative, PHP_URL_SCHEME) != '') {
			// It's already absolute
			return $relative;
		}
		$baseParts = parse_url($base);
		if ($relative[0] == "?") {
			$baseParts['query'] = substr($relative, 1);
			unset($baseParts['fragment']);
		} else if ($relative[0] == "#") {
			$baseParts['fragment'] = substr($relative, 1);
		} else if ($relative[0] == "/") {
			if ($relative[1] == "/") {
				return $baseParts['scheme'].$relative;
			}
			$baseParts['path'] = $relative;
			unset($baseParts['query']);
			unset($baseParts['fragment']);
		} else {
			$basePathParts = explode("/", $baseParts['path']);
			$relativePathParts = explode("/", $relative);
			array_pop($basePathParts);
			while (count($relativePathParts)) {
				if ($relativePathParts[0] == "..") {
					array_shift($relativePathParts);
					if (count($basePathParts)) {
						array_pop($basePathParts);
					}
				} else if ($relativePathParts[0] == ".") {
					array_shift($relativePathParts);
				} else {
					array_push($basePathParts, array_shift($relativePathParts));
				}
			}
			$baseParts['path'] = implode("/", $basePathParts);
			if ($baseParts['path'][0] != '/') {
				$baseParts['path'] = "/".$baseParts['path'];
			}
		}
		
		$result = "";
		if ($baseParts['scheme']) {
			$result .= $baseParts['scheme']."://";
			if ($baseParts['user']) {
				$result .= ":".$baseParts['user'];
				if ($baseParts['pass']) {
					$result .= ":".$baseParts['pass'];
				}
				$result .= "@";
			}
			$result .= $baseParts['host'];
			if ($baseParts['port']) {
				$result .= ":".$baseParts['port'];
			}
		}
		$result .= $baseParts["path"];
		if ($baseParts['query']) {
			$result .= "?".$baseParts['query'];
		}
		if ($baseParts['fragment']) {
			$result .= "#".$baseParts['fragment'];
		}
		return $result;
	}

	private $schemas = array();
	private $refs = array();
	
	public function missing() {
		return array_keys($this->refs);
	}
	
	public function add($url, $schema, $trusted=FALSE) {
		$urlParts = explode("#", $url);
		$baseUrl = array_shift($urlParts);
		$fragment = urldecode(implode("#", $urlParts));

		$trustBase = explode("?", $baseUrl);
		$trustBase = $trustBase[0];

		$this->schemas[$url] =& $schema;
		$this->normaliseSchema($url, $schema, $trusted ? TRUE : $trustBase);
		if ($fragment == "") {
			$this->schemas[$baseUrl] = $schema;
		}
		if (isset($this->refs[$baseUrl])) {
			foreach ($this->refs[$baseUrl] as $fullUrl => &$refSchema) {
				$refSchema = $this->get($fullUrl);
				unset($this->refs[$baseUrl][$fullUrl]);
			}
			if (count($this->refs[$baseUrl]) == 0) {
				unset($this->refs[$baseUrl]);
			}
		}
	}
	
	private function normaliseSchema($url, &$schema, $trustPrefix) {
		if (is_array($schema) && !self::isNumericArray($schema)) {
			$schema = (object)$schema;
		}
		if (is_object($schema)) {
			if (isset($schema->{'$ref'})) {
				$refUrl = $schema->{'$ref'} = self::resolveUrl($url, $schema->{'$ref'});
				if ($refSchema = $this->get($refUrl)) {
					$schema = $refSchema;
					return;
				} else {
					$urlParts = explode("#", $refUrl);
					$baseUrl = array_shift($urlParts);
					$fragment = urldecode(implode("#", $urlParts));
					$this->refs[$baseUrl][$refUrl] =& $schema;
				}
			} else if (isset($schema->id)) {
				$schema->id = $url = self::resolveUrl($url, $schema->id);
				if ($trustPrefix === TRUE
						|| (substr($schema->id, 0, strlen($trustPrefix)) == $trustPrefix
							&& ($trustPrefix[strlen($trustPrefix) - 1] == "/"
								|| $schema->id[strlen($trustPrefix)] == "#"
								|| $schema->id[strlen($trustPrefix)] == "?")
						)) {
					if (!isset($this->schemas[$schema->id])) {
						$this->add($schema->id, $schema);
					}
				}
			}
			foreach ($schema as $key => &$value) {
				if ($key != "enum") {
					self::normaliseSchema($url, $value, $trustPrefix);
				}
			}
		} else if (is_array($schema)) {
			foreach ($schema as &$value) {
				self::normaliseSchema($value);
			}
		}
	}
	
	public function get($url) {
		if (isset($this->schemas[$url])) {
			return $this->schemas[$url];
		}
		$urlParts = explode("#", $url);
		$baseUrl = array_shift($urlParts);
		$fragment = urldecode(implode("#", $urlParts));
		if (isset($this->schemas[$baseUrl])) {
			$schema = $this->schemas[$baseUrl];
			if ($schema && $fragment == "" || $fragment[0] == "/") {
				$schema = self::pointerGet($schema, $fragment);
				$this->add($url, $schema);
				return $schema;
			}
		}
	}
}

?>