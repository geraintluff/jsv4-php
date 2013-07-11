<?php

$store = new SchemaStore();

$urlBase = "http://example.com/";
$url = $urlBase."test-schema";
$schema = json_decode('{
	"title": "Test schema",
	"properties": {
		"foo": {
			"id": "#foo"
		},
		"bar": {
			"id": "/bar"
		}
	}
}');

$store->add($url, $schema);

if (!recursiveEqual($store->get($url."#foo"), $schema->properties->foo)) {
	throw new Exception("#foo not found");
}

if ($store->get($urlBase."bar")) {
	throw new Exception("/bar should not be indexed, as it should not be trusted");
}

$store->add($url, $schema, TRUE);

if (!recursiveEqual($store->get($urlBase."bar"), $schema->properties->bar)) {
	throw new Exception("/bar not found");
}

?>