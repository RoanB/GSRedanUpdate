<?php

declare(strict_types=1);

/**
 * Admin User
 *
 * Lists the admin accounts, creates and edits them, archives them, and mails
 * a password reset link. There is no public "I forgot my password" page: a
 * reset always starts here, by an admin who knows the account exists. The
 * user picks the new password on the login screen with the token from the
 * mail (Login::display_reset_password()).
 *
 * Every account created here is an admin, because the project has no other
 * kind of user: the members area is password protected, not account based.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

class User extends Base {
	/**
	 * List the users
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/user.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$current_user = $this->get_current_user();
		$language_names = $this->get_language_names();

		$users = [];
		foreach (\User::get_all_ordered() as $user) {
			$language_id = (int)$user->language_id;

			$users[] = [
				'id' => (int)$user->id,
				'name' => $user->get_name(),
				'email' => (string)$user->email,
				'language' => $language_names[$language_id] ?? $language_names[0],
				'is_current' => (int)$user->id === (int)$current_user->id,
			];
		}

		$template->assign('users', $users);
	}

	/**
	 * Create a user
	 *
	 * @access public
	 */
	public function display_add(): void {
		$this->template = 'admin/user_edit.twig';

		$template = \Skeleton\Application\Web\Template::get();

		$template->assign('user', $this->get_empty_user_data());
		$template->assign('languages', $this->get_languages());
		$template->assign('errors', []);

		if (isset($_POST['email']) === false) {
			return;
		}

		$user = new \User();
		$user->admin = 1;
		$this->read_user_form($user);

		$password = (string)($_POST['password'] ?? '');
		$user->password = $password;

		// User::validate() empties $errors first, so the password rule has to
		// be added after it ran, not before.
		$errors = [];
		$user->validate($errors);

		$password_error = \User::validate_password_strength($password);
		if ($password_error !== null) {
			$errors['password'] = $password_error;
		}

		if (count($errors) === 0) {
			$user->save();

			$this->redirect_with_message('/admin/user', 'saved');
		}

		$template->assign('user', $this->get_user_data($user));
		$template->assign('errors', $errors);
	}

	/**
	 * Edit a user
	 *
	 * The password is not part of this form: an admin who does not know the
	 * password resets it by mail instead (display_reset), so the stored hash is
	 * never replaced with a value the typing admin picked.
	 *
	 * @access public
	 */
	public function display_edit(): void {
		$this->template = 'admin/user_edit.twig';

		$template = \Skeleton\Application\Web\Template::get();
		$languages = $this->get_languages();

		$user = $this->get_user_by_id((int)($_GET['id'] ?? $_POST['id'] ?? 0));

		if (isset($_POST['email']) === false) {
			$template->assign('user', $this->get_user_data($user));
			$template->assign('languages', $languages);
			$template->assign('errors', []);

			return;
		}

		$this->read_user_form($user);

		// No admin checkbox: in this project every user is an admin.
		$user->admin = 1;

		$errors = [];
		if ($user->validate($errors) === true) {
			$user->save();

			$this->redirect_with_message('/admin/user', 'saved');
		}

		$template->assign('user', $this->get_user_data($user));
		$template->assign('languages', $languages);
		$template->assign('errors', $errors);
	}

	/**
	 * Archive a user
	 *
	 * Archiving hides the account from the list, from the login, and from the
	 * reset flow. It is `archived` and not `delete()`: the row stays, so the
	 * email address remains taken and the reset tokens keep their foreign key.
	 * A hard delete would fail on that foreign key for any account that ever
	 * requested a reset.
	 *
	 * @access public
	 */
	public function display_archive(): void {
		$this->template = 'admin/user.twig';

		$user = $this->get_user_by_id((int)($_GET['id'] ?? $_POST['id'] ?? 0));

		// An admin who archives their own account locks themselves out on the
		// next request, with no way back in through this screen.
		if ((int)$user->id === (int)$this->get_current_user()->id) {
			$this->redirect_with_message('/admin/user', 'self_archive');
		}

		// No validate: archiving must not depend on the account data being
		// complete enough to pass User::validate().
		$user->archive(false);

		// A reset link handed out before the archiving is worthless now, and
		// should not survive it.
		\Password_Reset_Token::invalidate_all_for_user($user);

		$this->redirect_with_message('/admin/user', 'archived');
	}

	/**
	 * Mail a password reset link
	 *
	 * @access public
	 */
	public function display_reset(): void {
		$this->template = 'admin/user.twig';

		$user = $this->get_user_by_id((int)($_GET['id'] ?? $_POST['id'] ?? 0));

		$raw_token = $user->issue_password_reset_token();

		$reset_url = $this->get_base_url() . '/login?action=reset_password&token=' . rawurlencode($raw_token);

		try {
			\Password_Reset_Mail::send($user, $reset_url);
		} catch (\Exception $e) {
			$this->redirect_with_message('/admin/user', 'reset_failed');
		}

		$this->redirect_with_message('/admin/user', 'reset_sent');
	}

	/**
	 * Fill a user from the posted form
	 *
	 * @access private
	 * @param \User $user
	 */
	private function read_user_form(\User $user): void {
		$user->email = trim((string)($_POST['email'] ?? ''));
		$user->firstname = trim((string)($_POST['firstname'] ?? ''));
		$user->lastname = trim((string)($_POST['lastname'] ?? ''));
		$user->language_id = (int)($_POST['language_id'] ?? 0);
	}

	/**
	 * Get a user, or send the admin back to the list
	 *
	 * Archived users are refused as well as missing ones: skeleton's
	 * get_by_id() selects by id without an archived filter, so a stale
	 * /admin/user?action=edit&id=… link would otherwise keep working on an
	 * account that is out of the list.
	 *
	 * @access private
	 * @param int $user_id
	 * @return \User $user
	 */
	private function get_user_by_id(int $user_id): \User {
		try {
			$user = \User::get_by_id($user_id);
		} catch (\Exception $e) {
			\Skeleton\Core\Http\Session::redirect('/admin/user');
		}

		if (isset($user->archived) && (string)$user->archived !== '') {
			\Skeleton\Core\Http\Session::redirect('/admin/user');
		}

		return $user;
	}

	/**
	 * The template view of a new user
	 *
	 * @access private
	 * @return array $data
	 */
	private function get_empty_user_data(): array {
		return [
			'id' => 0,
			'email' => '',
			'firstname' => '',
			'lastname' => '',
			'language_id' => 0,
		];
	}

	/**
	 * The template view of a user
	 *
	 * @access private
	 * @param \User $user
	 * @return array $data
	 */
	private function get_user_data(\User $user): array {
		return [
			'id' => (int)$user->id,
			'email' => (string)$user->email,
			'firstname' => (string)$user->firstname,
			'lastname' => (string)$user->lastname,
			'language_id' => (int)$user->language_id,
		];
	}

	/**
	 * The language select options
	 *
	 * The admin screens are in English, so the options use the English name
	 * (Dutch, French) rather than the local one.
	 *
	 * @access private
	 * @return array $options
	 */
	private function get_languages(): array {
		$options = [];
		foreach (\Language::get_all() as $language) {
			$options[] = [
				'id' => (int)$language->id,
				'name' => (string)$language->name,
			];
		}

		return $options;
	}

	/**
	 * The language names, by language id
	 *
	 * Used for the list column. A user on a language that no longer exists
	 * falls back to the site default, the same rule the reset mail follows.
	 *
	 * @access private
	 * @return array $names
	 */
	private function get_language_names(): array {
		$names = [];
		foreach (\Language::get_all() as $language) {
			$names[(int)$language->id] = (string)$language->name;
		}

		$default_language = \Language::get_default();
		$names[0] = (string)$default_language->name;

		return $names;
	}
}
