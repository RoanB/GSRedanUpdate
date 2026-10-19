<?php

declare(strict_types=1);

/**
 * Admin Settings
 *
 * Edits the key/value settings (contact info, socials, masthead title,
 * download password). `blank` is a convenience flag so the template only
 * renders a password field when one was submitted (the stored hash must
 * never leak back into the form).
 *
 * @author Roan Buysse <roan@tigron.be>
 */

namespace App\Front\Module\Admin;

class Settings extends Base {
	/**
	 * Display and process the settings form
	 *
	 * @access public
	 */
	public function display(): void {
		$this->template = 'admin/settings.twig';

		$template = \Skeleton\Application\Web\Template::get();

		if (isset($_POST['contact_email'])) {
			\Setting::set_value('contact_email', $_POST['contact_email']);
			\Setting::set_value('address_local_street', $_POST['address_local_street']);
			\Setting::set_value('address_local_zip', $_POST['address_local_zip']);
			\Setting::set_value('address_local_city', $_POST['address_local_city']);
			\Setting::set_value('address_local_maps', $_POST['address_local_maps']);
			\Setting::set_value('address_club_street', $_POST['address_club_street']);
			\Setting::set_value('address_club_zip', $_POST['address_club_zip']);
			\Setting::set_value('address_club_city', $_POST['address_club_city']);
			\Setting::set_value('address_club_maps', $_POST['address_club_maps']);
			\Setting::set_value('social_instagram', $_POST['social_instagram']);
			\Setting::set_value('social_facebook', $_POST['social_facebook']);
			\Setting::set_value('footer_company_number', $_POST['footer_company_number']);
			\Setting::set_value('footer_legal_entity', $_POST['footer_legal_entity']);
			\Setting::set_value('masthead_title', $_POST['masthead_title']);

			// Rally: the checkbox posts nothing when unchecked, so the
			// switch is on only when the request carries it. The dates are
			// datetimes: datetime-local posts YYYY-MM-DDTHH(:MM(:SS)),
			// normalised to the Y-m-d H:i(:s) storage format. The
			// countdown guards an unparsable value in JS.
			\Setting::set_value('rally_enabled', isset($_POST['rally_enabled']) ? '1' : '0');
			\Setting::set_value('rally_start_date', self::normalize_rally_datetime($_POST['rally_start_date']));
			\Setting::set_value('rally_end_date', self::normalize_rally_datetime($_POST['rally_end_date']));

			if (isset($_POST['download_password']) && $_POST['download_password'] !== '') {
				$download_password = (string)$_POST['download_password'];
				\Setting::set_value('download_password_hash', password_hash($download_password, PASSWORD_DEFAULT));
				// Plaintext mirror for the admin share links (spec/07):
				// the bcrypt hash cannot be read back, but the share URL
				// embeds the password to skip the gate.
				\Setting::set_value('download_password', $download_password);
			}

			$this->redirect_with_message('/admin/settings', 'saved');
		}

		$template->assign('settings', \Setting::get_all());
	}

	/**
	 * Normalize a datetime-local value to the storage format
	 *
	 * datetime-local posts `YYYY-MM-DDTHH:MM` (or with `:SS` depending on
	 * the browser); the setting keeps the DB format `Y-m-d H:i(:s)`.
	 * Empty stays empty.
	 *
	 * @access public
	 * @param ?string $value
	 * @return string
	 */
	public static function normalize_rally_datetime(?string $value): string {
		$value = (string)str_replace('T', ' ', (string)$value);

		if ($value === '') {
			return '';
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1) {
			return $value . ':00';
		}

		return $value;
	}
}