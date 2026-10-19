<?php

declare(strict_types=1);

/**
 * Seo_Sitemap
 *
 * Builds the sitemap of the public site (spec/02, "Sitemap and robots.txt").
 *
 * The public site is the static design from ../gsredanupdate: four pages
 * (home, /about/, /rallye/, /legal/), each addressable in every routable
 * language. They are worth publishing, because the language-prefixed pages
 * are not reachable from each other by links the crawler can follow — the
 * switcher mirrors the current path, and no page links to the French page as
 * a page. Without hreflang, a search engine only ever discovers the one
 * variant that happened to be its Accept-Language, and treats the other two
 * as duplicates of nothing.
 *
 * So: one <url> per page per routable language, each one carrying the full
 * set of alternates (including itself and x-default), which is the shape
 * Google documents for a language-prefixed site.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Seo_Sitemap {
	/**
	 * Namespace of the Index module, the one that owns the homepage route
	 *
	 * @var string $index_module
	 */
	const INDEX_MODULE = '\\App\\Front\\Module\\Index';

	/**
	 * The public pages, as the path segment after the language prefix
	 *
	 * The homepage is the empty path: /, /fr, /nl. The others are the static
	 * page routes of app/front/config/routes.php. The rally page is listed
	 * only while the `rally_enabled` setting is on (an off page redirects
	 * home — publishing it would put a redirect in the sitemap).
	 *
	 * @access private
	 * @return array
	 */
	private static function get_pages(): array {
		$pages = [ '' ];

		if (\Setting::get_by_name('rally_enabled') === '1') {
			$pages[] = 'rallye';
		}

		$pages[] = 'about';
		$pages[] = 'legal';

		return $pages;
	}

	/**
	 * Build the sitemap document
	 *
	 * <changefreq> and <priority> are deliberately absent: Google has ignored
	 * both since 2015, and inventing a crawl frequency is a number that looks
	 * authoritative while meaning nothing. There is no <lastmod> either: the
	 * pages are static templates, so no database row tracks when they last
	 * changed, and a made-up date is worse than none.
	 *
	 * @access public
	 * @return string $xml
	 */
	public static function get_xml(): string {
		$base_url = Http_Base_Url::get();
		$languages = self::get_languages();

		$lines = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
			. ' xmlns:xhtml="http://www.w3.org/1999/xhtml">';

		foreach (self::get_pages() as $page) {
			$page_url = rtrim($base_url . '/' . $page, '/');

			foreach ($languages as $language) {
				$lines[] = "\t<url>";
				$lines[] = "\t\t<loc>" . self::escape(self::get_language_url($base_url, $page, $language)) . '</loc>';
				$lines[] = "\t\t" . self::get_alternate_link($page_url, 'x-default');

				foreach ($languages as $alternate) {
					$lines[] = "\t\t" . self::get_alternate_link(self::get_language_url($base_url, $page, $alternate), $alternate->name_short);
				}

				$lines[] = "\t</url>";
			}
		}

		$lines[] = '</urlset>';

		return implode("\n", $lines) . "\n";
	}

	/**
	 * Get the URL of one page in one language
	 *
	 * No trailing slash: the router drops it, so /fr/about is the canonical
	 * form of what the site links as /fr/about/.
	 *
	 * @access private
	 * @param string $base_url
	 * @param string $page
	 * @param \Language $language
	 * @return string $url
	 */
	private static function get_language_url(string $base_url, string $page, \Language $language): string {
		return rtrim($base_url . '/' . $language->name_short . '/' . $page, '/');
	}

	/**
	 * Build one hreflang alternate link
	 *
	 * @access private
	 * @param string $url
	 * @param string $hreflang
	 * @return string $link
	 */
	private static function get_alternate_link(string $url, string $hreflang): string {
		return '<xhtml:link rel="alternate" hreflang="' . self::escape($hreflang) . '"'
			. ' href="' . self::escape($url) . '"/>';
	}

	/**
	 * Get the languages the public pages are actually routable in
	 *
	 * The list is read out of the Index route in
	 * `app/front/config/routes.php` instead of being repeated here: a
	 * language row in the database whose prefix is not in the route list is
	 * not dispatchable, and listing it in a sitemap would publish a 404.
	 * Conversely, a language added to the route list is published without
	 * touching this file.
	 *
	 * @access private
	 * @return array<\Language>
	 */
	private static function get_languages(): array {
		$routes = \Skeleton\Core\Application::get()->config->routes;
		$index_routes = [];
		if (isset($routes[self::INDEX_MODULE]) === true && is_array($routes[self::INDEX_MODULE]) === true) {
			$index_routes = $routes[self::INDEX_MODULE];
		}

		$routable_codes = self::get_routable_codes($index_routes);

		$languages = [];
		foreach (\Language::get_all() as $language) {
			if (count($routable_codes) > 0 && in_array($language->name_short, $routable_codes, true) === false) {
				continue;
			}

			$languages[] = $language;
		}

		return $languages;
	}

	/**
	 * Extract the allowed values of the $language variable from route patterns
	 *
	 * @access private
	 * @param array $routes
	 * @return array $codes
	 */
	private static function get_routable_codes(array $routes): array {
		$codes = [];

		foreach ($routes as $route) {
			if (preg_match('/\$language\[(.*?)\]/', $route, $matches) !== 1) {
				continue;
			}

			$codes = explode(',', $matches[1]);
		}

		return $codes;
	}

	/**
	 * Escape a value for XML content and attributes
	 *
	 * @access private
	 * @param string $value
	 * @return string $escaped
	 */
	private static function escape(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
