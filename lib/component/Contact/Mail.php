<?php

declare(strict_types=1);

/**
 * Contact_Mail
 *
 * Delivers the public contact form to the club.
 *
 * The club is the recipient, so the mail goes to the address in the
 * `contact_email` setting, sent from that same address (it owns the
 * mailbox). The visitor's address travels as Reply-To: hitting "reply"
 * in the mail client answers the person who wrote in, not the club
 * itself.
 *
 * The mail is written in the language the visitor was browsing in
 * (session language), not in the site default: the submitted text is
 * already in that language, and the club is Brussels-multilingual.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Contact_Mail {
	/**
	 * The email type, matching store/email/template/contact
	 *
	 * @var string $type
	 */
	const TYPE = 'contact';

	/**
	 * Send the contact mail
	 *
	 * Two mails leave per submission: the club's copy (Reply-To the
	 * visitor, so hitting reply answers them) and the visitor's copy
	 * (Reply-To the club mailbox). The visible sender is the noreply
	 * address from the config files (`email_noreply` in config/core.php,
	 * overridable per environment); without it the club mailbox itself
	 * is the sender.
	 *
	 * @access public
	 * @param string $name the visitor's name
	 * @param string $email the visitor's reply-to address
	 * @param string $topic the raw topic key (club, youth, rally, other)
	 * @param string $message the visitor's message
	 * @throws \Skeleton\Email\Exception\Validation when the sender or the recipient address is invalid
	 */
	public static function send(string $name, string $email, string $topic, string $message): void {
		$club_email = \Setting::get_by_name('contact_email');

		if ($club_email === null || $club_email === '') {
			throw new \Exception('Cannot send the contact mail: no recipient configured (setting contact_email).');
		}

		$noreply = \Skeleton\Core\Config::get()->email_noreply ?? null;

		if ($noreply === null || $noreply === '') {
			$noreply = $club_email;
		}

		// The club's copy
		$mail = new \Skeleton\Email\Email(self::TYPE);
		$mail->set_sender($noreply, self::get_sender_name());
		$mail->add_to($club_email, self::get_sender_name());
		$mail->add_reply_to($email, $name);
		$mail->assign('name', $name);
		$mail->assign('email', $email);
		$mail->assign('topic', $topic);
		$mail->assign('message', $message);
		$mail->assign('copy', false);
		$mail->assign('year', self::get_rally_year());
		$mail->set_translation(self::get_translation());
		$mail->send();

		// The visitor's copy
		$copy = new \Skeleton\Email\Email(self::TYPE);
		$copy->set_sender($noreply, self::get_sender_name());
		$copy->add_to($email, $name);
		$copy->add_reply_to($club_email, self::get_sender_name());
		$copy->assign('name', $name);
		$copy->assign('email', $email);
		$copy->assign('topic', $topic);
		$copy->assign('message', $message);
		$copy->assign('copy', true);
		$copy->assign('year', self::get_rally_year());
		$copy->set_translation(self::get_translation());
		$copy->send();
	}

	/**
	 * The rally year, for the topic label in the mail
	 *
	 * The year of the start date in the rally settings; empty when unset.
	 *
	 * @access private
	 * @return string
	 */
	private static function get_rally_year(): string {
		try {
			return (new \DateTime((string)\Setting::get_by_name('rally_start_date'), new \DateTimeZone(\Calendar_Event::DISPLAY_TIMEZONE)))->format('Y');
		} catch (\Exception $e) {
			return '';
		}
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
	 * The catalog of the visitor's session language
	 *
	 * @access private
	 * @return \Skeleton\I18n\Translation $translation
	 */
	private static function get_translation(): \Skeleton\I18n\Translation {
		return \Skeleton\I18n\Translation::get(\Language::get(), 'front');
	}
}
