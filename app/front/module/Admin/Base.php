<?php

declare(strict_types=1);

/**
 * Base
 *
 * Base class of every /admin module. It holds the one thing all admin
 * screens have in common: a session with an admin user in it. The module
 * bootstrap event calls secure() on every module that defines it, so the
 * check lives here once instead of being copied into each admin module.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

abstract class Base extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = true;

	/**
	 * Secure
	 *
	 * @access public
	 * @return bool
	 */
	public function secure(): bool {
		return isset($_SESSION['user']) && $_SESSION['user']->is_admin();
	}

	/**
	 * The admin performing the current request
	 *
	 * @access protected
	 * @return \User $user
	 */
	protected function get_current_user(): \User {
		return $_SESSION['user'];
	}

	/**
	 * Set a sticky session value
	 *
	 * Admin feedback (Saved, Card deleted, ...) travels through the session
	 * instead of the query string. The templates read these back as
	 * sticky_session.<key>, so a message shows up once on the page it was
	 * meant for instead of sticking to the URL when the admin refreshes or
	 * copies the link.
	 *
	 * @access protected
	 * @param string $key
	 * @param mixed $value
	 */
	protected function set_sticky(string $key, mixed $value): void {
		$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();
		$sticky_session->$key = $value;
	}

	/**
	 * Redirect with a sticky message and a clean URL
	 *
	 * @access protected
	 * @param string $url
	 * @param string $key
	 * @param mixed $value
	 */
	protected function redirect_with_message(string $url, string $key, mixed $value = true): void {
		$this->set_sticky($key, $value);

		\Skeleton\Core\Http\Session::redirect($url);
	}

	/**
	 * The absolute base URL of the current request
	 *
	 * Used for the links the admin has to open in another place (a reset mail
	 * link, a share link). Kept here so every module builds it the same way;
	 * the host/scheme decision itself lives in Http_Base_Url, which the login
	 * module needs too for the self-service reset mail.
	 *
	 * @access protected
	 * @return string $base_url
	 */
	protected function get_base_url(): string {
		return \Http_Base_Url::get();
	}
}
