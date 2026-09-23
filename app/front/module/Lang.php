<?php

namespace App\Front\Module;

/**
 * Lang
 *
 * Handles the language switcher at /lang/switch. Sets the active language in
 * the session and redirects back. Redirect targets are restricted to
 * relative URLs to avoid open-redirect abuse.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use Skeleton\Core\Http\Session;

class Lang extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Process the language switch
	 *
	 * @access public
	 */
	public function display(): void {
		$language_short = $_POST['language'] ?? 'fr';

		try {
			$language = \Language::get_by_name_short($language_short);
		} catch (\Exception $e) {
			$language = \Language::get_default();
		}

		$_SESSION['language'] = $language;

		$redirect_uri = $_POST['url'] ?? '/';
		if (is_string($redirect_uri) && isset($redirect_uri[0]) && $redirect_uri[0] === '/' && substr($redirect_uri, 0, 2) !== '//') {
			Session::redirect($redirect_uri);
		}

		Session::redirect('/');
	}
}