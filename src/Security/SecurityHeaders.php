<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Config;

/**
 * Emits the HTTP response headers that harden a page against common attacks.
 *
 * The previous deployment relied entirely on `.htaccess` for headers, which
 * meant anything served outside Apache (or with a rewritten path) lost them.
 * Sending them from PHP keeps them tied to the response itself.
 */
final class SecurityHeaders
{
    /**
     * @param Config $config Application configuration.
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Send every configured security header.
     *
     * @param bool $isHtml Whether the response body is an HTML document.
     * @return void
     */
    public function apply(bool $isHtml = true): void
    {
        if (headers_sent()) {
            return;
        }

        // Stop the browser from guessing a content type it was not told about,
        // which is what turns an uploaded file into executable script.
        header('X-Content-Type-Options: nosniff');

        // Deny framing outright: the site has no legitimate embed use case, and
        // this removes clickjacking as an attack class.
        header('X-Frame-Options: DENY');

        // Do not leak the full URL of outbound navigation to third parties.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Disable browser features the site never uses.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

        if ($this->config->get('session.cookie_secure', false)) {
            // Only meaningful over HTTPS; sending it on plain HTTP is ignored.
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if ($isHtml) {
            $csp = $this->config->get('security.csp');

            if (is_string($csp) && $csp !== '') {
                // Blocks inline script, which is what an injected <script> needs
                // in order to run. Styles still allow inline because the site
                // uses a few inline style blocks.
                header('Content-Security-Policy: ' . $csp);
            }
        } else {
            // Never let a non-document response be interpreted as markup.
            header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        }
    }
}
