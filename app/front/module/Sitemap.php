<?php

declare(strict_types=1);

/**
 * Sitemap
 *
 * Serves /sitemap.xml. Anonymous, because a crawler has no session: the
 * login gate would answer a search engine with a redirect to /login, which
 * reads as "the sitemap does not exist".
 *
 * The document itself is built by Seo_Sitemap; this module is only the
 * endpoint. robots.txt lives on the other side of the media detector and is
 * served by App\Front\Event\Media — see that file for why.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

class Sitemap extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the sitemap
	 *
	 * @access public
	 */
	public function display(): void {
		header('Content-Type: application/xml; charset=utf-8');
		// Deliberately not cached: the document is a handful of queries, and
		// a long cache would need to reason about the session cookie PHP
		// sends with it. Crawlers fetch this a few times a month.
		header('Cache-Control: no-store');

		echo \Seo_Sitemap::get_xml();
		exit;
	}
}
