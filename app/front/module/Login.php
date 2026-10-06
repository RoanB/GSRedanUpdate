<?php

declare(strict_types=1);

/**
 * Login
 *
 * Admin login screen. Runs at /login, accepts email + password, stores the
 * User model in the session and redirects to /admin. Uses its own login
 * layout (_default/layout.login.twig).
 *
 * Also serves the password reset form (display_reset_password): the link from
 * the reset mail points here, the user sets a new password, and the token is
 * spent in the process. There is no page to request a reset mail from, the
 * admin screen (/admin/user) is the only place that sends one.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use Skeleton\Core\Http\Session;

class Login extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * Display the login form and process submissions
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'login.twig';

		$template = \Skeleton\Application\Web\Template::get();

		if (isset($_POST['login'])) {
			try {
				$user = \User::authenticate($_POST['login'], $_POST['password']);
				if ($user->is_admin() === false) {
					throw new \Exception('Authentication failed');
				}

				$_SESSION['user'] = $user;
				Session::redirect('/admin');
			} catch (\Exception $e) {
				$template->assign('login_failed', true);
			}
		}
	}

	/**
	 * Request a password reset link (self-service)
	 *
	 * Reached from the login screen: "Forgot your password?" opens a small form
	 * that mails a link to the address that belongs to an admin account. The
	 * receiving half already exists (display_reset_password), so this only has
	 * to issue a token and send the mail.
	 *
	 * The reply is the same sentence whether or not the address belongs to an
	 * account. That is the whole security design of this screen: the login page
	 * is unauthenticated, so a different message for a known and an unknown
	 * address would turn it into a way to find out who administers the site.
	 * For the same reason nothing is logged and the reset is throttled per
	 * account (Password_Reset_Token::is_in_resend_cooldown()) instead of
	 * being an open relay.
	 *
	 * @access public
	 */
	public function display_reset_request(): void {
		$this->template = 'login.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$template->assign('show_reset_request', true);

		if (isset($_POST['email']) === false) {
			return;
		}

		$this->send_reset_link((string)$_POST['email']);

		$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();
		$sticky_session->reset_request_sent = true;

		Session::redirect('/login');
	}

	/**
	 * Mail a reset link when the address belongs to an active admin account
	 *
	 * Silent by design: no exception on a missing address, a mail failure, or
	 * a throttled account, because the caller shows one neutral sentence
	 * regardless. An admin whose mail server is broken should not be able to
	 * tell the difference from a wrong address, and the login screen must not
	 * become an error oracle.
	 *
	 * @access private
	 * @param string $email
	 */
	private function send_reset_link(string $email): void {
		try {
			$user = \User::get_active_by_email($email);
		} catch (\Exception $e) {
			return;
		}

		// Archived users are already out (get_active_by_email filters them),
		// and a non-admin row could not log in afterwards either.
		if ($user->is_admin() === false) {
			return;
		}

		if (\Password_Reset_Token::is_in_resend_cooldown($user)) {
			return;
		}

		try {
			$raw_token = $user->issue_password_reset_token();

			$reset_url = \Http_Base_Url::get() . '/login?action=reset_password&token=' . rawurlencode($raw_token);

			\Password_Reset_Mail::send($user, $reset_url);
		} catch (\Exception $e) {
			// Swallowed on purpose, see the docblock.
		}
	}

	/**
	 * Log out
	 *
	 * Both the admin menu (layout.base.twig) and the members navbar
	 * (layout.members.twig) point at /login?action=logout. The action used to
	 * be missing: skeleton's handle_request() falls back to display() when no
	 * display_<action> method exists, so the link showed the login form and
	 * left the session untouched.
	 *
	 * Only the user key is dropped. The session itself stays alive for the
	 * language, and the members gate is a separate shared password with its own
	 * logout (/members?action=logout) — logging out of the admin area is not a
	 * reason to lock the club board out.
	 *
	 * @access public
	 */
	public function display_logout(): void {
		unset($_SESSION['user']);

		$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();
		$sticky_session->logged_out = true;

		Session::redirect('/login');
	}

	/**
	 * Set a new password with the token from the reset mail
	 *
	 * The token travels in the query string of the link, so it is read from
	 * both $_GET (opening the link) and $_POST (sending the form back). It is
	 * single use and expires: get_by_raw_token() only returns a token that is
	 * unused, not archived, and not past its expiry, so a replayed or stale
	 * link lands on the "link is not valid" screen with nothing to change.
	 *
	 * @access public
	 */
	public function display_reset_password(): void {
		$this->template = 'password_reset.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$raw_token = $this->get_raw_token();
		$token = \Password_Reset_Token::get_by_raw_token($raw_token);

		if ($token === null) {
			$template->assign('token_valid', false);

			return;
		}

		$user = $token->get_user();

		$template->assign('token_valid', true);
		$template->assign('token', $raw_token);
		$template->assign('user_name', $user->get_name());

		if (isset($_POST['password']) === false) {
			return;
		}

		$password = (string)$_POST['password'];
		$password_repeat = (string)($_POST['password_repeat'] ?? '');

		$errors = [];
		$password_error = \User::validate_password_strength($password);

		if ($password_error !== null) {
			$errors['password'] = $password_error;
		}

		if ($password !== $password_repeat) {
			$errors['password_repeat'] = 'mismatch';
		}

		if (count($errors) === 0) {
			// Also spends the token: set_new_password() closes every open
			// reset token of the user, this one included.
			$user->set_new_password($password);

			$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();
			$sticky_session->password_changed = true;

			Session::redirect('/login');
		}

		$template->assign('errors', $errors);
	}

	/**
	 * The raw token of the current reset request
	 *
	 * @access private
	 * @return string $raw_token
	 */
	private function get_raw_token(): string {
		if (isset($_GET['token'])) {
			return trim((string)$_GET['token']);
		}

		if (isset($_POST['token'])) {
			return trim((string)$_POST['token']);
		}

		return '';
	}
}