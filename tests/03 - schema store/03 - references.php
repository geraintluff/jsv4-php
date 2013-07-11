<?php

$store = new SchemaStore();

$urlBase = "http://example.com/";

// Add internal $ref, and make sure it's resolved
$url = $urlBase."test-schema";
$schema = json_decode('{
	"title": "Test schema",
	"properties": {
		"foo": {
			"$ref": "#/definitions/foo"
		}
	},
	"definitions": {
		"foo": {
			"title": "foo"
		}
	}
}');
$store->add($url, $schema);
$schema = $store->get($url);
if ($schema->properties->foo != $schema->definitions->foo) {
	throw new Exception('$ref was not resolved');
}

// Add external $ref, and don't resolve it
// While we're at it, use an array, not an object
$schema = array(
	"title" => "Test schema 2",
	"properties" => array(
		"foo" => array('$ref' => "somewhere-else")
	)
);
$store->add($urlBase."test-schema-2", $schema);
$schema = $store->get($urlBase."test-schema-2");
if (!$schema->properties->foo->{'$ref'}) {
	throw new Exception('$ref should still exist');
}
if (!recursiveEqual($store->missing(), array($urlBase."somewhere-else"))) {
	throw new Exception('$store->missing() is not correct: '.json_encode($store->missing()).' is not '.json_encode(array($urlBase."somewhere-else")));
}

$otherSchema = json_decode('{
	"title": "Somewhere else",
	"items": {"$ref": "'.$urlBase."test-schema-2".'"}
}');
$store->add($urlBase."somewhere-else", $otherSchema);
$fooSchema = $schema->properties->foo;
if ($fooSchema->{'$ref'}) {
	throw new Exception('$ref should have been resolved');
}
if ($fooSchema->title != "Somewhere else") {
	throw new Exception('$ref does not point to correct place');
}
if ($fooSchema->items->title != "Test schema 2") {
	throw new Exception('$ref in somewhere-else was not resolved');
}
if (count($store->missing())) {
	throw new Exception('There should be no more missing schemas');
}

?>