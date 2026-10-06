<?php
/**
 * Initialize the application
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */
require_once 'lib/base/Bootstrap.php';
Bootstrap::boot();

/**
 * Drop a trailing slash from the requested path
 *
 * The router matches whole path segments and does not ignore empty ones:
 * Handler::Run() rebuilds the path it routes on by joining the REQUEST_URI
 * segments between two slashes, so `/en/` arrives as the segments `en` and
 * ``, matches no route, and has no module to fall back on either
 * (\App\Front\Module\En does not exist). Every language-prefixed URL with a
 * trailing slash is therefore a 404, which also kills the whole `/xx/...`
 * tree (`/en/members/` included).
 *
 * The slash is not something the browser only adds by accident: Apache adds
 * it by itself (DirectorySlash) the moment the webroot holds a directory for
 * that path, and a hand-typed URL does it just as easily. Normalising it here,
 * before the framework reads REQUEST_URI, is what makes every route
 * slash-tolerant. Templates read the same REQUEST_URI to build the language
 * switcher, so they build their links from the normalised path as well.
 *
 * The root path keeps its slash, and the query string is carried over
 * untouched.
 */
$request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$request_path = (string)parse_url($request_uri, PHP_URL_PATH);

if ($request_path !== '' && $request_path !== '/' && substr($request_path, -1) === '/') {
	$request_query = (string)parse_url($request_uri, PHP_URL_QUERY);
	$_SERVER['REQUEST_URI'] = rtrim($request_path, '/') . ($request_query === '' ? '' : '?' . $request_query);
}

\Skeleton\Core\Http\Handler::Run();
