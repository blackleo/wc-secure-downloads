# WC Secure Downloads

Defence-in-depth for paid digital downloads on WooCommerce. Answers the question
*"how do you stop a guessed link from reaching a paid file?"* with four layers:

1. **Files are not in a public path.** Protected files live in `uploads/woocommerce_uploads/`, blocked at the web server (`server-config/`). No URL to the file itself works.
2. **WooCommerce's order-bound download link.** Each link needs the order key, the buyer's email (or login), and a per-file key. None are guessable. Set per-product **download limit** and **expiry days**.
3. **Rate limiting (this plugin).** After N attempts per IP per 10 minutes, the endpoint returns `429`, so brute-forcing is impractical. Raw IPs are never stored (HMAC hash).
4. **No indexing / no caching.** Download URLs are disallowed in robots.txt, sent `noindex`, `no-store`, `nosniff`.

## Setup
1. Copy `wc-secure-downloads.php` into `wp-content/plugins/wc-secure-downloads/` (or `mu-plugins/`) and activate.
2. Apply `server-config/nginx.conf` **or** `apache.htaccess`.
3. WooCommerce → Settings → Products → Downloadable products: set *Download method* to **X-Accel-Redirect/X-Sendfile** if your host supports it (otherwise *Force downloads*), and tick *Downloads require login* if you want account-only access.
4. Run `tests/guess-test.sh https://yoursite.com some-file.pdf` and keep the output with the security report.

## Tunables
`wcsd_max_attempts` and `wcsd_window_secs` filters.

## License
GPL-2.0-or-later
