<?php

declare(strict_types=1);

/**
 * Login
 *
 * Admin login screen. Runs at /login, accepts email + password, stores the
 * User model in the session and redirects to /admin. Uses its own login
 * layout (_default/layout.login.twig).
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
}