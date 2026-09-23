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
	}

	/**
	 * Not found
	 *
	 * Renders a friendly 404 page instead of the default exception.
	 *
	 * @access public
	 */
	public function not_found(): void {
		// Non-exiting variant: the template below must still render.
		\Skeleton\Core\Http\Status::code_404('module', false);

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

		$template->render('error404.twig');
	}
}