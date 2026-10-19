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

		// Site settings (contact email, addresses, socials, footer lines,
		// the rally dates): the layout footer and the 404 page render them
		// on every request.
		$template->assign('settings', \Setting::get_all());

		// The rally date line (home rally band + rallye hero): rendered
		// from the rally settings so the club changes the dates in one
		// place. Empty string when the dates are not set; the templates
		// then hide the date line.
		$template->assign('rally_date_label', \Date_Label::format_range(
			(string)\Setting::get_by_name('rally_start_date'),
			(string)\Setting::get_by_name('rally_end_date'),
			\Language::get()->name_short
		));

		// The rally year, for the labels that carry it (nav, footer, hero
		// CTA, countdown label, contact topic): the year of the start date.
		// Empty when the setting is unset or unparsable — the year simply
		// disappears from the labels instead of printing garbage.
		try {
			$rally_year = (new \DateTime((string)\Setting::get_by_name('rally_start_date'), new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE)))->format('Y');
		} catch (\Exception $e) {
			$rally_year = '';
		}
		$template->assign('rally_year', $rally_year);
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
		$template->assign('settings', \Setting::get_all());

		if (isset($_GET['language']) === true && is_string($_GET['language']) === true) {
			try {
				$switch_language = \Language::get_by_name_short($_GET['language']);
				$_SESSION['language'] = $switch_language;
			} catch (\Exception $e) {
				// Unknown tag: keep the current language.
			}
		}

		\Language::set($_SESSION['language']);

		// The error page carries no navbar and no photo (it is the e404 page
		// of the static design). The flag skips both in layout.public.twig.
		$template->assign('error_page', true);

		// display(), not render(): render() returns the HTML as a string and
		// this is the only place in the app that calls it directly, so the
		// return value was thrown away and the response went out with an empty
		// body (content-length: 0). display() is what the framework itself
		// uses for every normal page, via Module::handle_request().
		$template->display('error404.twig');
	}
}