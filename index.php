<?php
set_time_limit(0);

// replaces non-alphanumeric characters with optional non-alphanumeric match, and inserts optional non-alphanumeric match between numbers and letters
function prepareRegex($str) {
	return preg_replace('/([a-z])(\d)/i', '$1[^a-z\d]*$2',
				 preg_replace('/(\d)([a-z])/i', '$1[^a-z\d]*$2',
				 preg_replace('/[^a-z\d]+/i', '[^a-z\d]*',
				 $str)));
}

// make regex string with word boundaries
function makeRegex($str) {
	return '/\b'.prepareRegex($str).'\b/i';
}

// make regex string without word boundaries
function makeRegexNoWord($str) {
	return '/'.prepareRegex($str).'/i';
}

// initialize output
$output = array();
define('OUTPUT_FILE', 'results.txt');
file_put_contents(OUTPUT_FILE, '');

// read in listings
$listings = file_get_contents('listings.txt');
$listings = explode("\n", $listings);
foreach($listings as &$l) $l = json_decode($l, true);// decode json string for each lisitng

// read in products
$products = file_get_contents('products.txt');
$products = explode("\n", $products);
foreach($products as $j => &$p) {
	$p = json_decode($p, true);// decode json string
	if (is_null($p)) {
		unset($products[$j]);// remove product if json decode failed
		continue;
	}

	// initialize product in output
	$output[$p['product_name']] = array(
		'product_name' => $p['product_name'],
		'listings' => array()
	);

	// search for ideal match first - manufacturer + family + full model
	if (empty($p['manufacturer']) || empty($p['model']) || empty($p['family'])) continue;

	$manufacturer = makeRegex($p['manufacturer']);
	$model = makeRegex($p['model']);
	$family = makeRegex($p['family']);

	foreach($listings as $i => $l) {
		if (!preg_match($manufacturer, $l['manufacturer']) || !preg_match($model, $l['title']) || !preg_match($family, $l['title'])) continue;

		$output[$p['product_name']]['listings'][] = $l;// add match to output
		unset($listings[$i]);// remove matched listing from future searches
	}
}

// 2nd pass - look for manufacturer + full model from listings that haven't been matched yet
foreach($products as $p) {
	if (empty($p['manufacturer']) || empty($p['model'])) continue;

	$manufacturer = makeRegex($p['manufacturer']);
	$model = makeRegex($p['model']);

	foreach($listings as $i => $l) {
		if (!preg_match($manufacturer, $l['manufacturer']) || !preg_match($model, $l['title'])) continue;

		$output[$p['product_name']]['listings'][] = $l;
		unset($listings[$i]);
	}
}

// 3rd pass - look for partial models - e.g. model is DSC123 but listing has DSC123S
// skipped if model is only numbers or only letters (too general - many false positives)
foreach($products as $p) {
	if (empty($p['manufacturer']) || empty($p['model']) || is_numeric($p['model']) || !preg_match('/\d/', $p['model'])) continue;

	$manufacturer = makeRegex($p['manufacturer']);
	$model = makeRegexNoWord($p['model']);

	foreach($listings as $i => $l) {
		if (!preg_match($manufacturer, $l['manufacturer']) || !preg_match($model, $l['title'])) continue;

		$output[$p['product_name']]['listings'][] = $l;
		unset($listings[$i]);
	}
}

// write output
foreach($output as $line) file_put_contents(OUTPUT_FILE, json_encode($line)."\n", FILE_APPEND);

exit(0);
?>