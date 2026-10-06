<?php

declare(strict_types=1);

/**
 * Event
 *
 * Serves /robots.txt from the application instead of from the media folder.
 *
 * Why this is here: the media detector runs before the router
 * (Application\Web::run) and claims every request whose extension it knows.
 * "txt" is a known extension (the "doc" filetype), so /robots.txt is looked
 * up as app/front/media/doc/robots.txt and, when that file does not exist,
 * the core Media event throws Not\Found, which Application\Web turns into a
 * 404 before routing ever happens. A module route cannot serve a .txt path.
 *
 * So the miss is intercepted here, and robots.txt is generated from
 * Seo_Robots. If a real app/front/media/doc/robots.txt is ever committed, the
 * media detector finds it and serves it before this event is reached — the
 * static file wins, which is the obvious outcome.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Event;

class Media extends \Skeleton\Core\Application\Event\Media {
	/**
	 * Media not found
	 *
	 * @access public
	 */
	public function not_found(): void {
		$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

		if (strtolower($path) === '/robots.txt') {
			header('Content-Type: text/plain; charset=utf-8');
			header('Cache-Control: no-store');

			echo \Seo_Robots::get_txt();
			exit;
		}

		parent::not_found();
	}
}
