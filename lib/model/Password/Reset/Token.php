<?php

declare(strict_types=1);

/**
 * Password_Reset_Token
 *
 * A single password reset token issued for a user. The raw token only ever
 * travels in the reset mail; this table stores its SHA-256 hash, so a leaked
 * database dump cannot be replayed against the login screen.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Skeleton\Database\Database;

class Password_Reset_Token {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Uuid;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Class configuration
	 *
	 * The table is named user_password_reset_token, while the autoloader
	 * derives password_reset_token from the class name.
	 *
	 * @access protected
	 * @var array $class_configuration
	 */
	protected static $class_configuration = [
		'database_table' => 'user_password_reset_token',
	];

	/**
	 * Number of seconds a token stays valid
	 *
	 * @var int $validity_seconds
	 */
	const VALIDITY_SECONDS = 86400;

	/**
	 * Seconds before the same account may be sent another link
	 *
	 * The self-service reset form on the login screen is unauthenticated, so it
	 * is a mail relay for whoever knows an admin address. This per-account
	 * cooldown is what keeps that bounded: one mail per account per ten
	 * minutes, whatever the request rate is. There is no rate-limiter package
	 * in the project, and the token table already holds everything needed.
	 *
	 * @var int $resend_cooldown_seconds
	 */
	const RESEND_COOLDOWN_SECONDS = 600;

	/**
	 * Was a link mailed to this account very recently?
	 *
	 * Counts the account's own live tokens, so it survives a cleared cookie and
	 * cannot be side-stepped by opening a private window.
	 *
	 * @access public
	 * @param \User $user
	 * @return bool $cooling_down
	 */
	public static function is_in_resend_cooldown(\User $user): bool {
		$count = Database::get()->get_one(
			'SELECT COUNT(*) FROM `user_password_reset_token`
			WHERE `user_id` = ?
			AND `used` = 0
			AND `archived` IS NULL
			AND `expires_at` > NOW()
			AND `created` > DATE_SUB(NOW(), INTERVAL ' . self::RESEND_COOLDOWN_SECONDS . ' SECOND)',
			[ $user->id ]
		);

		return (int)$count > 0;
	}

	/**
	 * Create a new reset token for a user
	 *
	 * The raw token is handed back to the caller for the reset mail, only its
	 * hash is stored. Any token the user already had is marked used first, so
	 * a mailbox full of reset mails never leaves more than one live link.
	 *
	 * @access public
	 * @param \User $user
	 * @param string $raw_token
	 * @return Password_Reset_Token $token
	 */
	public static function create_for_user(\User $user, string $raw_token): Password_Reset_Token {
		self::invalidate_all_for_user($user);

		$token = new self();
		$token->user_id = $user->id;
		$token->token = self::hash_token($raw_token);
		$token->expires_at = date('Y-m-d H:i:s', time() + self::VALIDITY_SECONDS);
		$token->save();

		return $token;
	}

	/**
	 * Get a still-valid token by its raw value
	 *
	 * Returns null when the token does not exist, is used, expired or
	 * archived.
	 *
	 * @access public
	 * @param string $raw_token
	 * @return ?Password_Reset_Token $token
	 */
	public static function get_by_raw_token(string $raw_token): ?Password_Reset_Token {
		$id = Database::get()->get_one(
			'SELECT `id` FROM `user_password_reset_token`
			WHERE `token` = ?
			AND `used` = 0
			AND `archived` IS NULL
			AND `expires_at` > NOW()',
			[ self::hash_token($raw_token) ]
		);

		if ($id === null) {
			return null;
		}

		return self::get_by_id((int)$id);
	}

	/**
	 * Spend every live token of a user
	 *
	 * @access public
	 * @param \User $user
	 */
	public static function invalidate_all_for_user(\User $user): void {
		Database::get()->query(
			'UPDATE `user_password_reset_token` SET `used` = 1, `updated` = NOW()
			WHERE `user_id` = ? AND `used` = 0 AND `archived` IS NULL',
			[ $user->id ]
		);
	}

	/**
	 * Get the user this token belongs to
	 *
	 * @access public
	 * @return \User $user
	 */
	public function get_user(): \User {
		return \User::get_by_id((int)$this->user_id);
	}

	/**
	 * Mark the token as used
	 *
	 * @access public
	 */
	public function mark_used(): void {
		$this->used = 1;
		$this->save(false);
	}

	/**
	 * Is this token still usable?
	 *
	 * @access public
	 * @return bool $valid
	 */
	public function is_valid(): bool {
		if ((int)$this->used === 1) {
			return false;
		}

		if ($this->archived !== null && (string)$this->archived !== '') {
			return false;
		}

		return (string)$this->expires_at > date('Y-m-d H:i:s');
	}

	/**
	 * Hash a raw token value
	 *
	 * @access private
	 * @param string $raw_token
	 * @return string $hash
	 */
	private static function hash_token(string $raw_token): string {
		return hash('sha256', $raw_token);
	}
}
