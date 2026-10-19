<?php

declare(strict_types=1);

/**
 * Contact
 *
 * Receives the public contact form (POST /contact) and mails it to the
 * club through Contact_Mail. The form itself lives on the homepage
 * (#contact); this module is only the submit endpoint.
 *
 * Anti-spam: the form carries a honeypot field named `website`. It is
 * visually hidden and skipped by tab order, so a human never fills it —
 * a bot that fills every input does. A filled honeypot is answered with
 * the same silent redirect as a success (the bot must learn nothing),
 * but no mail is composed.
 *
 * Feedback travels through the sticky session (like the admin screens),
 * not the query string: index.twig shows the success or error alert that
 * belongs to this submission and forgets it on the next navigation.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module;

use Skeleton\Core\Http\Session;

class Contact extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = false;

	/**
	 * The topics the contact form offers; the select is validated against
	 * this list so nothing but a known label can reach the mail.
	 *
	 * @var array $topics
	 */
	const TOPICS = [ 'club', 'youth', 'rally', 'other' ];

	/**
	 * Process the form
	 *
	 * @access public
	 */
	public function display(): void {
		// The endpoint exists for the form only: a stray GET (opened
		// directly, crawler probe) goes back to the form with nothing sent.
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			Session::redirect('/#contact');
		}

		$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();

		// Honeypot: a filled trap field is bot traffic. Reply with the
		// plain redirect and compose nothing.
		if (($_POST['cf_website'] ?? '') !== '') {
			Session::redirect('/#contact');
		}

		// Time trap: the form renders its render timestamp; a submission
		// that arrives less than three seconds later (or claims to be from
		// the future) was not typed by a human. Same silent reply.
		$form_ts = (int)($_POST['form_ts'] ?? 0);

		if ($form_ts === 0 || (time() - $form_ts) < 3 || $form_ts > time() + 60) {
			Session::redirect('/#contact');
		}

		$name = trim((string)($_POST['name'] ?? ''));
		$email = trim((string)($_POST['email'] ?? ''));
		$topic = (string)($_POST['topic'] ?? '');
		$message = trim((string)($_POST['message'] ?? ''));
		$consent = ($_POST['consent'] ?? '') !== '';

		// Server-side mirror of the client rules in main.js, so nothing
		// unsendable reaches the mail layer even with JavaScript off.
		if (mb_strlen($name) < 2) {
			self::redirect_validation_error();
		}

		if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $email) !== 1) {
			self::redirect_validation_error();
		}

		if (in_array($topic, self::TOPICS, true) === false) {
			self::redirect_validation_error();
		}

		if (mb_strlen($message) < 10) {
			self::redirect_validation_error();
		}

		if ($consent === false) {
			self::redirect_validation_error();
		}

		try {
			\Contact_Mail::send($name, $email, $topic, $message);
		} catch (\Exception $e) {
			// Transport or configuration failure: tell the visitor it did
			// not go out instead of silently dropping the message.
			$sticky_session->contact_failed = true;
			\Util::log_request('Contact mail failed: ' . $e->getMessage());
			Session::redirect('/#contact');
		}

		$sticky_session->contact_sent = true;
		Session::redirect('/#contact');
	}

	/**
	 * Redirect back to the form with the validation-error flag set
	 *
	 * @access private
	 */
	private static function redirect_validation_error(): void {
		$sticky_session = \Skeleton\Core\Http\Session\Sticky::get();
		$sticky_session->contact_error = true;

		Session::redirect('/#contact');
	}
}
