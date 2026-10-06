<?php

declare(strict_types=1);

/**
 * Http_Base_Url
 *
 * The absolute base URL of the current request, for the links that are opened
 * somewhere else: a reset mail link, a members share link.
 *
 * It lives in lib/component instead of the admin base class because two
 * unrelated callers need it (Admin\Base for the share links, Login for the
 * self-service reset mail) and a second copy of the Host-header decision is
 * exactly the kind of thing that drifts apart.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

class Http_Base_Url {
	/**
	 * Build the base URL of this request
	 *
	 * The host comes from the configured application hostname, never from the
	 * Host header: a mail link is read by a human, and a spoofed header would
	 * send a live reset token to a host of the sender's choosing. The scheme
	 * does come from the request, because the proxy in front of the
	 * application is what decides that.
	 *
	 * @access public
	 * @return string $base_url
	 */
	public static function get(): string {
		$application = \Skeleton\Core\Application::get();

		if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
			$scheme = 'https';
		} else {
			$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		}

		return $scheme . '://' . $application->hostname;
	}
}
