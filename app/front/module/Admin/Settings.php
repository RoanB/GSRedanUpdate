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

class Settings extends \Skeleton\Application\Web\Module {
	/**
	 * Login required
	 *
	 * @var bool $login_required
	 */
	protected bool $login_required = true;

	/**
	 * Secure
	 *
	 * @access public
	 * @return bool
	 */
	public function secure(): bool {
		return isset($_SESSION['user']) && $_SESSION['user']->is_admin();
	}

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

			if (isset($_POST['download_password']) && $_POST['download_password'] !== '') {
				\Setting::set_value('download_password_hash', password_hash($_POST['download_password'], PASSWORD_DEFAULT));
			}

			\Skeleton\Core\Http\Session::redirect('/admin/settings?saved=1');
		}

		$template->assign('settings', \Setting::get_all());
	}
}