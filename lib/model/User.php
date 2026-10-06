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
	 * Minimum length of a new password, in bytes
	 *
	 * @var int $minimum_password_length
	 */
	const MINIMUM_PASSWORD_LENGTH = 8;

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
				$errors[$mandatory] = 'required';
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
	 * All active users, by name
	 *
	 * Archived users are left out: the admin user list works on the accounts
	 * that can still be used.
	 *
	 * @access public
	 * @return array User
	 */
	public static function get_all_ordered(): array {
		$ids = Database::get()->get_column(
			'SELECT id FROM user
			WHERE archived IS NULL
			ORDER BY lastname ASC, firstname ASC, email ASC'
		);

		return self::get_by_ids($ids);
	}

	/**
	 * Number of active users (admin dashboard counter)
	 *
	 * @access public
	 * @return int $count
	 */
	public static function count_all(): int {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM user WHERE archived IS NULL'
		);

		return (int)$count;
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
	 * Get an active (not archived) user by email
	 *
	 * Login uses this one: an archived account has to stop working the moment
	 * the admin archives it, while get_by_email() still serves lookups that
	 * have to see archived rows (the duplicate email check on save).
	 *
	 * @access public
	 * @param string $email
	 * @return User
	 * @throws \Exception if not found or archived
	 */
	public static function get_active_by_email(string $email): self {
		$db = Database::get();
		$id = $db->get_one(
			'SELECT id FROM user WHERE email = ? AND archived IS NULL',
			[ $email ]
		);
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
	 * Check a new password against the account rules
	 *
	 * @access public
	 * @param string $password
	 * @return ?string $error error key, or null when the password is acceptable
	 */
	public static function validate_password_strength(string $password): ?string {
		if (strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
			return 'too_short';
		}

		return null;
	}

	/**
	 * Authenticate an admin user by email + password
	 *
	 * Archived accounts are rejected before the password check: the admin
	 * cannot log in with the credentials of a user they archived.
	 *
	 * @access public
	 * @param string $email
	 * @param string $password
	 * @return User
	 * @throws \Exception if authentication fails
	 */
	public static function authenticate(string $email, string $password): self {
		$user = self::get_active_by_email($email);
		if (!$user->validate_password($password)) {
			throw new \Exception('Authentication failed');
		}
		return $user;
	}

	/**
	 * Issue a password reset token for this user
	 *
	 * Returns the raw token, which goes straight into the reset mail and is
	 * never stored: Password_Reset_Token keeps its SHA-256 hash. Issuing a
	 * token invalidates the ones handed out earlier.
	 *
	 * @access public
	 * @return string $raw_token
	 */
	public function issue_password_reset_token(): string {
		$raw_token = bin2hex(random_bytes(32));

		\Password_Reset_Token::create_for_user($this, $raw_token);

		return $raw_token;
	}

	/**
	 * Set a new password and close every open reset token
	 *
	 * @access public
	 * @param string $password
	 */
	public function set_new_password(string $password): void {
		$this->set_password($password);
		$this->save();

		\Password_Reset_Token::invalidate_all_for_user($this);
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

	/**
	 * The name of this user, for the admin screens
	 *
	 * @access public
	 * @return string $name
	 */
	public function get_name(): string {
		$name = trim((string)$this->firstname . ' ' . (string)$this->lastname);

		if ($name !== '') {
			return $name;
		}

		return (string)$this->email;
	}
}