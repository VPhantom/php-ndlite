<?php

	// Turn on debugging
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 'stdout');
	ini_set('display_startup_errors', true);
	ini_set('log_errors', false);
	ini_set('ignore_repeated_errors', true);
	ini_set('html_errors', true);
	ini_set('docref_root', 'http://ca2.php.net/manual/en/');

	require_once 'NDlite.php';

	// Path to your source files
	define('NDLITE_DOC_PATH', '/home/lis/public_html/devel/');

	// Base URL for cross-file links
	define('NDLITE_BASE_URL', '/devel/php-utils/samples/ndlite-pathinfo.php/');

	// NOTHING TO MODIFY BELOW THIS POINT

	// Browser-friendly URL: use PATH_INFO, preferrably append '-doc'
	$path = $_SERVER['PATH_INFO'];
	$pathlength = strlen($path);
	$pathstart = 0;
	if (strpos($path, '-doc') > 0) $pathlength -= 4;
	if (!empty($path) && $path[0] == '/') $pathstart = 1;
	$path = substr($path, $pathstart, ($pathlength - $pathstart));

	// The program
	header('Content-Type: text/html');
	$doc = new NDlite(NDLITE_DOC_PATH, NDLITE_BASE_URL);
	$doc->parseFile(NDLITE_DOC_PATH . $path);
	$title = $doc->guessTitle($path);
?>
<html>
	<head>
		<title><?=$title?></title>
<style>

/* Example NDlite styling, loosely based on NaturalDocs 1.x.
 *
 * WARNING: Tested only with Firefox 2.0. I'm not sure MSIE will like the
 * display:table-cell I used in the summary and definition lists.
 */

/* Make links less intrusive in text flow */
.ndlite_doc a {
	text-decoration: none;
}
.ndlite_doc a:hover {
	text-decoration: underline;
}

/* Titles */
.ndlite_doc h2 {
	border-bottom: 2px solid black;
	font-variant: small-caps;
	padding-left: 1em;
}
.ndlite_doc h3 {
	border-bottom: 1px solid #808080;
	padding-left: 2em;
}

/* Code blocks */
.ndlite_doc pre {
	margin: 0 1em 0 2em;
	padding: 1em;
	border: 1px solid #c0c0c0;
	border-left: 6px solid #c0c0c0;
	color: #606060;
}

/* Definition lists */
.ndlite_doc dt {
	color: #606060;
	font-family: monospace;
}
.ndlite_doc dd {
	margin-bottom: 1em;
}

/* Prototype code */
.ndlite_doc code {
	display: block;
	font-family: monospace;
	border: 1px solid #c0c0c0;
	background-color: #f8f8f8;
	margin: 0.5em 4em 1em 4em;
	padding: 0.5em 1em 0.5em 1em;
}

/* Summary */
.ndlite_doc .NDlite_Summary {
	padding: 1em 1em 0.5em 2em;
	margin: 0 4em 0 4em;
	background-color: #f8f8f8;
	border: 1px solid #c0c0c0;
}
.ndlite_doc .NDlite_Summary ul {
	margin-left: 0;
	padding-left: 2em;
}
.ndlite_doc .NDlite_Summary li {
	list-style-type: none;
	margin-left: 0;
	padding-left: 0;
	clear: both;
}
.ndlite_doc .NDlite_Summary li.NDlite_Group ul {
	margin: 0.5em 0 1em 0;
}
.ndlite_doc .NDlite_Summary a {
	display: block;
	float: left;
	width: 20em;
}
.ndlite_doc .NDlite_Summary .NDlite_Abstract a {
	width: auto;
	display: inline;
	float: none;
}
.ndlite_doc .NDlite_Summary li.NDlite_Group a {
	font-weight: bold;
	font-variant: small-caps;
	width: 18em;
}
.ndlite_doc .NDlite_Summary li.NDlite_Group li a {
	font-weight: normal;
	font-variant: normal;
	width: 16em;
}
.ndlite_doc .NDlite_Summary li.odd {
	background-color: #eaeaea;
}

</style>
	</head>
	<body>
		<div class="ndlite_doc">
			<h1><?=$title?></h1>
<?php echo $doc->toXHTML(array('private' => true)); ?>
		</div>
	</body>
</html>
