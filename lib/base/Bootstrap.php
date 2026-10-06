<?php
/**
 * Bootstrap Class
 *
 * Initializes the Skeleton framework
 *
 * @author Roan Buysse <roan@buysse.be>
 */

class Bootstrap {

	/**
	 * Bootstrap
	 *
	 * @access public
	 */
	public static function boot() {
		/**
		 * Set the root path
		 */
		$root_path = realpath(dirname(__FILE__) . '/../..');

		/**
		 * Register the autoloader from Composer
		 */
		require_once $root_path . '/lib/external/packages/autoload.php';

		/**
		 * Get the config
		 */
		\Skeleton\Core\Config::include_directory($root_path . '/config');
		$config = \Skeleton\Core\Config::get();

		/**
		 * Register the autoloader
		 */
		$autoloader = new \Skeleton\Core\Autoloader();
		$autoloader->add_include_path($root_path . '/lib/model/');
		$autoloader->add_include_path($root_path . '/lib/base/');
		$autoloader->add_include_path($root_path . '/lib/component/');
		$autoloader->register();

		/**
		 * Initialize the database
		 */
		\Skeleton\Database\Config::$auto_null = false;
		\Skeleton\Database\Config::$auto_trim = false;
		\Skeleton\Database\Config::$auto_discard = false;
		$database = \Skeleton\Database\Database::get($config->database, true);

		/**
		 * Initialize migration
		 */
		\Skeleton\Database\Migration\Config::$migration_directory = $root_path . '/migration/';
		\Skeleton\Database\Migration\Config::$version_storage  = 'database';
		\Skeleton\Database\Migration\Config::$database_table  = 'db_version';

		/**
		 * Transaction daemon
		 */
		\Skeleton\Transaction\Config::$monitor_file = $root_path . '/tmp/skeleton.status';
		\Skeleton\Transaction\Config::$monitor_authentication = 'thie7Eej7poh7Aic';

		/**
		 * Initialize the error handler
		 *
		 * E_DEPRECATED is excluded: the server runs PHP 8.5 while the vendored
		 * skeleton packages predate it, so deprecations such as "using null as
		 * an array offset" (Rewrite event) would otherwise be escalated to
		 * ErrorExceptions and take down every page. Real errors still throw.
		 */
		\Skeleton\Error\Config::$debug = $config->debug;
		\Skeleton\Error\Config::$environment = $config->environment;
		\Skeleton\Error\Config::$sentry_dsn = $config->sentry_dsn;
		\Skeleton\Error\Config::$error_reporting = E_ALL & ~E_DEPRECATED;
		\Skeleton\Error\Handler::enable();

		/**
		 * Initialize the template
		 */
		\Skeleton\Template\Twig\Config::$cache_directory = $config->tmp_path . 'twig/';
		\Skeleton\Template\Twig\Config::$debug = $config->debug;

		/**
		 * Initialize the translations
		 */
		\Skeleton\I18n\Config::$language_interface = '\Language';
		\Skeleton\I18n\Translator\Storage\Po::set_default_configuration(['storage_path' => $root_path . '/po/']);
		\Skeleton\I18n\Config::$cache_path = $root_path . '/tmp/languages/';

		/**
		 * Emails
		 *
		 * The template path is fixed: store/email/template/{type}/{html,text,
		 * subject}.twig. The transport comes from the (gitignored) environment
		 * config, so no credentials end up in version control. Without an
		 * explicit 'email_transport' the local sendmail binary is used, which
		 * is what the host provides; 'email_transport' => 'smtp' then expects
		 * 'email_smtp' => [ host, port, encryption, username, password ].
		 */
		\Skeleton\Email\Config::$email_path = $root_path . '/store/email/';

		if (($config->email_transport ?? '') === 'smtp') {
			\Skeleton\Email\Config::$transport_type = 'smtp';
			\Skeleton\Email\Config::$transport_smtp_config = $config->email_smtp;
		}

		if (($config->email_sendmail_command ?? '') !== '') {
			\Skeleton\Email\Config::$transport_sendmail_command = $config->email_sendmail_command;
		}

		/**
		 * Skeleton-File
		 */
		\Skeleton\File\Config::$file_path = $config->file_path;
		\Skeleton\File\Config::$file_interface = 'File';

		/**
		 * Skeleton-File-picture
		 */
		\Skeleton\File\Picture\Config::$tmp_path = $config->tmp_path . '/picture/';
		\Skeleton\File\Picture\Config::add_resize_configuration('160x160', 160, 160);
		\Skeleton\File\Picture\Config::add_resize_configuration('88x88', 88, 88);
		\Skeleton\File\Picture\Config::add_resize_configuration('50x50', 88, 88);
		\Skeleton\File\Picture\Config::add_resize_configuration('25x25', 25, 25);

		// Card image sizes (activity hero images, gallery pictures)
		\Skeleton\File\Picture\Config::add_resize_configuration('1600x1200', 1600, 1200, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('1200x900', 1200, 900, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('800x600', 800, 600, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('400x300', 400, 300, 'auto');

		// Masthead hero sizes: one full-width banner variant per breakpoint
		// (Masthead_Image::SIZES). Separate from the card sizes above,
		// because the hero is a full-bleed banner and not a card in a column.
		//
		// The variant is the viewport range it serves, and the masthead is
		// `width: 100%` with `background-size: cover`, so every one of these
		// has to be at least as wide as the widest viewport in its range or
		// the browser upscales the hero. That is why the steps are 640/1280/
		// 1920/2560 and not Bootstrap's 768/992/1200/1400: the breakpoints in
		// styles.css have to be these numbers. Keep the two in step — the
		// matching preloads live in _default/hero_image.twig.
		\Skeleton\File\Picture\Config::add_resize_configuration('2560x1280', 2560, 1280, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('1920x960', 1920, 960, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('1280x640', 1280, 640, 'auto');
		\Skeleton\File\Picture\Config::add_resize_configuration('640x320', 640, 320, 'auto');

		/**
		 * Initialize the pager
		 */
		\Skeleton\Pager\Config::$sticky_pager = true;
		\Skeleton\Pager\Config::$jump_to = false;
		\Skeleton\Pager\Config::$links_template = '@skeleton-pager\bootstrap5-tabler\links.twig';
		\Skeleton\Pager\Config::$header_template = '@skeleton-pager\bootstrap5-tabler\header.twig';
		\Skeleton\Pager\Config::$per_page_template = '@skeleton-pager\bootstrap5-tabler\per_page.twig';
	}
}
