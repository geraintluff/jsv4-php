<?php

header('Content-Type: text/plain');

require_once 'jsv4.php';
require_once 'test-utils.php';
require_once 'schema-store.php';

$totalTestCount = 0;
$failedTests = array();

function runJsonTest($key, $test) {
	global $totalTestCount;
	global $failedTests;
	$totalTestCount++;
	
	try {
		if ($test->method == "validate") {
			$result = Jsv4::validate($test->data, $test->schema);
		} else if ($test->method == "isValid") {
			$result = Jsv4::isValid($test->data, $test->schema);
		} else if ($test->method == "coerce") {
			$result = Jsv4::coerce($test->data, $test->schema);
		} else {
			$failedTests[$key][] = ("Unknown method: {$test->method}");
			return;
		}
		if (is_object($test->result)) {
			foreach ($test->result as $path => $expectedValue) {
				$actualValue = pointerGet($result, $path, TRUE);
				if (!recursiveEqual($actualValue, $expectedValue)) {
					$failedTests[$key][] = "$path does not match - should be:\n    ".json_encode($expectedValue)."\nwas:\n    ".json_encode($actualValue);
				}
			}
		} else {
			if (!recursiveEqual($test->result, $result)) {
				$failedTests[$key][] = "$path does not match - should be:\n    ".json_encode($test->result)."\nwas:\n    ".json_encode($result);
			}
		}
	} catch (Exception $e) {
		$failedTests[$key][] = $e->getMessage();
		$failedTests[$key][] .= "    ".str_replace("\n", "\n    ", $e->getTraceAsString());
	}
}

function runPhpTest($key, $filename) {
	global $totalTestCount;
	global $failedTests;
	$totalTestCount++;
	
	try {
		include_once $filename;
	} catch (Exception $e) {
		$failedTests[$key][] = $e->getMessage();
		$failedTests[$key][] .= "    ".str_replace("\n", "\n    ", $e->getTraceAsString());
	}
}

function runTests($directory, $indent="") {
	global $failedTests;
	if ($directory[strlen($directory) - 1] != "/") {
		$directory .= "/";
	}
	$baseName = basename($directory);
	
	$testCount = 0;
	$testFileCount = 0;
	
	$entries = scandir($directory);
	foreach ($entries as $entry) {
		$filename = $directory.$entry;
		if (stripos($entry, '.php') && is_file($filename)) {
			$key = substr($filename, 0, strlen($filename) - 4);
			runPhpTest($key, $filename);
		} else if (stripos($entry, '.json') && is_file($filename)) {
			$testFileCount++;
			$tests = json_decode(file_get_contents($filename));
			if ($tests == NULL) {
				$testCount++;
				$failedTests[$filename] = "Error parsing JSON";
				continue;
			}
			if (!is_array($tests)) {
				$tests = array($tests);
			}
			foreach ($tests as $index => $test) {
				$key = substr($filename, 0, strlen($filename) - 5);
				if (isset($test->title)) {
					$key .= ": {$test->title}";
				} else {
					$key .= ": #{$index}";
				}
				runJsonTest($key, $test);
				$testCount++;
			}
		}
	}
	if ($testCount) {
		echo "{$indent}{$baseName}/   \t({$testCount} tests in {$testFileCount} files)\n";
	} else {
		echo "{$indent}{$baseName}/\n";
	}
	foreach ($entries as $entry) {
		$filename = $directory.$entry;
		if (strpos($entry, '.') === FALSE && is_dir($filename)) {
			runTests($filename, $indent.str_repeat(" ", strlen($baseName) + 1));
		}
	}
}

runTests("tests/");

echo "\n\n";
if (count($failedTests) == 0) {
	echo "Passed all {$totalTestCount} tests\n";
} else {
	echo "Failed ".count($failedTests)."/{$totalTestCount} tests\n";
	foreach ($failedTests as $key => $failedTest) {
		if (is_array($failedTest)) {
			$failedTest = implode("\n", $failedTest);
		}
		echo "\n";
		echo "FAILED $key:\n";
		echo str_repeat("-", strlen($key) + 10)."\n";
		echo " |  ".str_replace("\n", "\n |  ", $failedTest)."\n";
	}
}

?>