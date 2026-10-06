<?php

declare(strict_types=1);

/**
 * Password_Reset_Mail
 *
 * Sends the password reset mail for an admin user.
 *
 * The mail is written in the language of the recipient (user.language_id),
 * not in the language of the admin who triggered the reset: the link is meant
 * for the account owner, who may be using another language in the admin.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Password_Reset_Mail {
	/**
	 * The email type, matching store/email/template/password_reset
	 *
	 * @var string $type
	 */
	const TYPE = 'password_reset';

	/**
	 * Send the password reset mail
	 *
	 * @access public
	 * @param \User $user
	 * @param string $reset_url
	 * @throws \Skeleton\Email\Exception\Validation when the sender or the recipient address is invalid
	 */
	public static function send(\User $user, string $reset_url): void {
		$sender_email = \Setting::get_by_name('contact_email');

		if ($sender_email === null || $sender_email === '') {
			throw new \Exception('Cannot send the password reset mail: no sender address configured (setting contact_email).');
		}

		$email = new \Skeleton\Email\Email(self::TYPE);
		$email->set_sender($sender_email, self::get_sender_name());
		$email->add_to((string)$user->email, $user->get_name());
		$email->assign('reset_url', $reset_url);
		$email->set_translation(self::get_translation($user));
		$email->send();
	}

	/**
	 * The sender name shown in the mail client
	 *
	 * @access private
	 * @return string $name
	 */
	private static function get_sender_name(): string {
		$sender_name = \Setting::get_by_name('masthead_title');

		if ($sender_name === null || $sender_name === '') {
			return 'GS Redan';
		}

		return $sender_name;
	}

	/**
	 * The catalog of the recipient's own language
	 *
	 * @access private
	 * @param \User $user
	 * @return \Skeleton\I18n\Translation $translation
	 */
	private static function get_translation(\User $user): \Skeleton\I18n\Translation {
		$language = self::get_language($user);

		return \Skeleton\I18n\Translation::get($language, 'front');
	}

	/**
	 * The language of a user, defaulting to the site default
	 *
	 * @access private
	 * @param \User $user
	 * @return \Language $language
	 */
	private static function get_language(\User $user): \Language {
		if ($user->language_id !== null && (int)$user->language_id > 0) {
			try {
				return \Language::get_by_id((int)$user->language_id);
			} catch (\Exception $e) {
				// Language removed since the user was created.
			}
		}

		return \Language::get_default();
	}
}
