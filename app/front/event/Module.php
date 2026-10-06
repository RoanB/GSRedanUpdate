<?php

declare(strict_types=1);

/**
 * Event
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Event;

use Skeleton\Core\Http\Session;

class Module extends \Skeleton\Application\Web\Event\Module {
	/**
	 * Bootstrap the module
	 *
	 * @access public
	 * @param \Skeleton\Application\Web\Module $module
	 */
	public function bootstrap(\Skeleton\Application\Web\Module $module): void {
		$template = \Skeleton\Application\Web\Template::get();

		if (isset($_SESSION['user'])) {
			$template->assign('user', $_SESSION['user']);
		}
		// Language switch via GET (?language=xx) — the nav menu renders plain
		// anchor links mirroring ../redan; the switch is a redirect-ish query.
		if (isset($_GET['language']) === true && is_string($_GET['language']) === true) {
			try {
				$switch_language = \Language::get_by_name_short($_GET['language']);
				$_SESSION['language'] = $switch_language;
			} catch (\Exception $e) {
				// Unknown tag: keep the current language.
			}
		}

		\Language::set($_SESSION['language']);

		if (is_callable([ $module, 'secure' ])) {
			$allowed = $module->secure();

			if ($allowed === false) {
				Session::redirect('/login');
			}
		}

		if (isset($_GET['action']) === true) {
			$template->assign('action', $_GET['action']);
		}

		$template->assign('languages', \Language::get_all());

		// Assign the sticky session object to our template
		$sticky_session = new \Skeleton\Core\Http\Session\Sticky();
		$template->add_environment('sticky_session', $sticky_session);

		$template->assign('environment', \Skeleton\Core\Config::get()->environment);

		// Homepage menu: visible blocks with a non-empty anchor AND title
		$language = \Language::get();
		$nav_items = [];
		$blocks = \Block::get_visible_ordered();
		foreach ($blocks as $block) {
			if (empty($block->anchor)) {
				continue;
			}

			try {
				$translation = $block->get_translation($language);
			} catch (\Exception $e) {
				$translation = null;
			}

			if ($translation === null || $translation->title === '') {
				continue;
			}

			$nav_items[] = [
				'anchor' => $block->anchor,
				'title' => $translation->title,
			];
		}
		$template->assign('nav_items', $nav_items);
		$template->assign('settings', \Setting::get_all());
		$this->assign_masthead_image();
	}

	/**
	 * Not found
	 *
	 * Renders a friendly 404 page instead of the default exception.
	 *
	 * @access public
	 */
	public function not_found(): void {
		// Status::code_404() cannot be used here, not even in its non-exiting
		// variant. That variant only skips the exit: it still echoes
		// "404 Not Found (module)" into the response body, which lands in
		// front of the HTML the template renders further down and turns the
		// friendly page into stray text followed by markup. The response body
		// belongs to the template, so set the status code directly and let
		// the template own everything else. The "(module)" suffix only ever
		// reached the status line, which browsers do not display, so nothing
		// useful is lost.
		http_response_code(404);

		$template = \Skeleton\Application\Web\Template::get();
		$template->assign('title', 'Not found');
		$template->assign('languages', \Language::get_all());

		if (isset($_GET['language']) === true && is_string($_GET['language']) === true) {
			try {
				$switch_language = \Language::get_by_name_short($_GET['language']);
				$_SESSION['language'] = $switch_language;
			} catch (\Exception $e) {
				// Unknown tag: keep the current language.
			}
		}

		\Language::set($_SESSION['language']);
		$language = \Language::get();

		$nav_items = [];
		$blocks = \Block::get_visible_ordered();
		foreach ($blocks as $block) {
			if (empty($block->anchor)) {
				continue;
			}

			try {
				$translation = $block->get_translation($language);
			} catch (\Exception $e) {
				$translation = null;
			}

			if ($translation === null || $translation->title === '') {
				continue;
			}

			$nav_items[] = [
				'anchor' => $block->anchor,
				'title' => $translation->title,
			];
		}
		$template->assign('nav_items', $nav_items);
		$template->assign('settings', \Setting::get_all());

		// The error pages carry no photo, so the masthead photo is not resolved
		// here. Two consequences, both wanted: the 404 does not spend the three
		// extra /picture requests the homepage makes, and hero_image.twig skips
		// its <link rel="preload"> block, which would otherwise pull a caving
		// banner nobody paints. The same flag puts `error-page` on <body> in
		// layout.public.twig, which is what forces the navbar into its solid
		// state: the pages are light, and the navbar's own default is white
		// text meant for a dark photo behind it.
		$template->assign('error_page', true);

		// display(), not render(): render() returns the HTML as a string and
		// this is the only place in the app that calls it directly, so the
		// return value was thrown away and the response went out with an empty
		// body (content-length: 0). display() is what the framework itself
		// uses for every normal page, via Module::handle_request().
		$template->display('error404.twig');
	}

	/**
	 * Assign the masthead hero image to the template
	 *
	 * Not every page carries the masthead block (the members area and the
	 * error pages have their own hero markup), but the photo behind the hero
	 * is the same on all of them. Resolving it here keeps one copy of that
	 * decision: the layout hands the three variants to styles.css, and
	 * og:image points at the largest one.
	 *
	 * Called from not_found() as well, because the 404 page goes through the
	 * public layout without ever reaching bootstrap().
	 *
	 * @access private
	 */
	private function assign_masthead_image(): void {
		$template = \Skeleton\Application\Web\Template::get();

		$masthead_image = \Masthead_Image::get_urls();

		$template->assign('masthead_image', $masthead_image);
		$template->assign('masthead_image_share', \Masthead_Image::get_absolute_url($masthead_image));
	}
}