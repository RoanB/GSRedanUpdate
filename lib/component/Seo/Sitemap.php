<?php

declare(strict_types=1);

/**
 * Seo_Sitemap
 *
 * Builds the sitemap of the public site (spec/02, "Sitemap and robots.txt").
 *
 * The public site is a single page: the visible blocks are sections of the
 * homepage, reachable as fragments (#rallye, #salle, ...), not as separate
 * URLs. A fragment is not a URL, so there is exactly one addressable page per
 * language: /, /en, /fr, /nl. That is still worth publishing, because the
 * language-prefixed pages are not reachable from each other by links the
 * crawler can follow — the switcher is a session/language selector, and no
 * page links to the French homepage as a page. Without hreflang, a search
 * engine only ever discovers the one variant that happened to be its
 * Accept-Language, and treats the other two as duplicates of nothing.
 *
 * So: one <url> per routable language, each one carrying the full set of
 * alternates (including itself and x-default), which is the shape Google
 * documents for a language-prefixed site.
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
	 * Build the sitemap document
	 *
	 * <changefreq> and <priority> are deliberately absent: Google has ignored
	 * both since 2015, and inventing a crawl frequency is a number that looks
	 * authoritative while meaning nothing. <lastmod> is kept, and it is a real
	 * timestamp rather than a constant: the newest of the visible blocks and
	 * of the visible upcoming calendar events (the Monday-openings card is fed
	 * by the calendar), which is everything the homepage renders from.
	 *
	 * @access public
	 * @return string $xml
	 */
	public static function get_xml(): string {
		$base_url = Http_Base_Url::get();
		$languages = self::get_languages();
		$lastmod = self::get_lastmod();

		$lines = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
			. ' xmlns:xhtml="http://www.w3.org/1999/xhtml">';

		foreach ($languages as $language) {
			$lines[] = "\t<url>";
			$lines[] = "\t\t<loc>" . self::escape(self::get_language_url($base_url, $language)) . '</loc>';

			if ($lastmod !== null) {
				$lines[] = "\t\t<lastmod>" . $lastmod . '</lastmod>';
			}

			$lines[] = "\t\t" . self::get_alternate_link($base_url . '/', 'x-default');

			foreach ($languages as $alternate) {
				$lines[] = "\t\t" . self::get_alternate_link(self::get_language_url($base_url, $alternate), $alternate->name_short);
			}

			$lines[] = "\t</url>";
		}

		$lines[] = '</urlset>';

		return implode("\n", $lines) . "\n";
	}

	/**
	 * Get the URL of the homepage in one language
	 *
	 * No trailing slash, the way the site itself links: the rewrite of
	 * /{language} produces /fr, not /fr/.
	 *
	 * @access private
	 * @param string $base_url
	 * @param \Language $language
	 * @return string $url
	 */
	private static function get_language_url(string $base_url, \Language $language): string {
		return $base_url . '/' . $language->name_short;
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
	 * Get the languages the homepage is actually routable in
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
	 * Get the date the homepage content last changed
	 *
	 * A date, not a timestamp: the columns are local datetimes and the site
	 * publishes no timezone, so claiming a time would be a guess. A date is
	 * a valid, useful <lastmod>.
	 *
	 * @access private
	 * @return string|null $lastmod
	 */
	private static function get_lastmod(): ?string {
		$timestamps = [];

		foreach (\Block::get_visible_ordered() as $block) {
			$timestamps[] = (string)$block->updated;
		}

		// The Monday-openings card renders upcoming events, so an event that
		// was edited or created changes the page too.
		foreach (\Calendar_Event::get_visible_upcoming(24) as $event) {
			$timestamps[] = (string)$event->updated;
		}

		$known = [];
		foreach ($timestamps as $timestamp) {
			if ($timestamp === '') {
				continue;
			}

			$known[] = $timestamp;
		}

		if (count($known) === 0) {
			return null;
		}

		// The column is a 'Y-m-d H:i:s' string, so the newest is the largest.
		return substr(max($known), 0, 10);
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
