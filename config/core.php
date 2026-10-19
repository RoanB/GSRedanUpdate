<?php
/**
 * Skeleton configuration
 */

return [
	// Where skeleton can find various parts of your application
	'application_path' => dirname(__FILE__) . '/../app/',
	'asset_paths' => [
		dirname(__FILE__) . '/../lib/external/packages/bower-asset/',
		dirname(__FILE__) . '/../lib/external/packages/npm-asset/',
		dirname(__FILE__) . '/../lib/external/packages',
	],
	'tmp_path' => dirname(__FILE__) . '/../tmp/',
	'tmp_dir' => dirname(__FILE__) . '/../tmp/',
	'lib_dir' => dirname(__FILE__) . '/../lib/',
	'file_path' => dirname(__FILE__) . '/../store/file/',

	// The From address for mail the site sends on its own behalf (the
	// contact form's copies). Not a secret: it lives here (versioned) and
	// can be overridden per environment in the gitignored environment.php.
	// The contact mail falls back to the contact_email setting when this
	// is empty.
	'email_noreply' => 'noreply@gsredan.be',
];