<?php

/**
 * User
 *
 * Admin account. The seed migration strips the skeleton-core user table to
 * email/password/firstname/lastname/admin/verified/language_id; there are no
 * address or organisation fields in this project.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class User {
	use \Skeleton\Object\Model {
		__set as trait_set;
	}
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Set
	 *
	 * Intercept password writes to hash them.
	 *
	 * @access public
	 * @param string $key
	 * @param mixed $value
	 */
	public function __set($key, $value): void {
		if ($key === 'password') {
			$this->set_password($value);
		} else {
			$this->trait_set($key, $value);
		}
	}

	/**
	 * Validate before save
	 *
	 * @access public
	 * @param array &$errors
	 * @return bool
	 */
	public function validate(array &$errors = []): bool {
		$errors = [];
		$mandatories = [ 'email', 'firstname', 'lastname' ];

		if (isset($this->admin) && $this->admin) {
			$mandatories[] = 'password';
		}

		foreach ($mandatories as $mandatory) {
			if (empty($this->$mandatory) === true) {
				$errors[$mandatory] = 'mandatory';
			}
		}

		if (isset($this->email)) {
			try {
				$user = self::get_by_email($this->email);
				if (!isset($this->id) || $this->id !== $user->id) {
					$errors['email'] = 'duplicate';
				}
			} catch (\Exception $e) {
				// no existing user with this email
			}
		}

		return count($errors) === 0;
	}

	/**
	 * Get by email
	 *
	 * @access public
	 * @param string $email
	 * @return User
	 * @throws \Exception if not found
	 */
	public static function get_by_email(string $email): self {
		$db = Database::get();
		$id = $db->get_one('SELECT id FROM user WHERE email = ?', [ $email ]);
		if ($id === null) {
			throw new \Exception('Not found');
		}
		return self::get_by_id((int)$id);
	}

	/**
	 * Set password (hashes it)
	 *
	 * @access public
	 * @param string $password
	 */
	public function set_password(string $password): void {
		$this->details['password'] = password_hash($password, PASSWORD_DEFAULT);
	}

	/**
	 * Verify a password against the stored hash
	 *
	 * @access public
	 * @param string $password
	 * @return bool
	 */
	public function validate_password(string $password): bool {
		return password_verify($password, (string)$this->password);
	}

	/**
	 * Authenticate an admin user by email + password
	 *
	 * @access public
	 * @param string $email
	 * @param string $password
	 * @return User
	 * @throws \Exception if authentication fails
	 */
	public static function authenticate(string $email, string $password): self {
		$user = self::get_by_email($email);
		if (!$user->validate_password($password)) {
			throw new \Exception('Authentication failed');
		}
		return $user;
	}

	/**
	 * Is this user an admin?
	 *
	 * @access public
	 * @return bool
	 */
	public function is_admin(): bool {
		return (bool)$this->admin;
	}
}