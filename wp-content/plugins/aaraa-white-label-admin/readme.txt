=== Aaraa White Label Admin ===
Contributors: aaraaplatforms
Tags: white label, admin theme, hide login, custom admin url, woocommerce
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

White-labels the WordPress admin: custom admin & login URLs, an Aaraa SaaS admin
theme, login branding, dashboard widgets, and security hardening. WooCommerce and
HPOS compatible.

== Description ==

Aaraa White Label Admin turns the standard WordPress admin into a branded Aaraa
control panel.

Features:

* Custom admin URL — admin links are rewritten to `/aaraa-admin/` (configurable).
* Hidden login — `/wp-login.php` is served from the branded slug instead.
* Login branding — logo, background colour/image, and button colours.
* Aaraa admin theme — SaaS palette (primary #10B7D4, sidebar #0F172A), rounded
  dashboard cards.
* WordPress branding removal — WP logo, footer credit, version string, Help tab,
  and (optionally) Screen Options.
* Branded admin bar node linking to the dashboard.
* "Aaraa Dashboard" rename plus a welcome widget with WooCommerce metrics
  (orders, revenue, customers, products) — HPOS-safe via `wc_get_orders()`.
* Tabbed settings page (General, Branding, Admin URL, Login, Dashboard, Advanced)
  using the WordPress Settings pattern; options stored in `aaraa_settings`.
* Security — hide WP version, disable XML-RPC, disable the file editor.
* Multisite aware, including network activation and a network settings screen.

== Installation ==

1. Upload the `aaraa-white-label-admin` folder to `/wp-content/plugins/`.
2. Activate the plugin (or Network Activate on multisite).
3. Go to **Aaraa Settings** to configure the slug, branding and security options.
4. Save the **Admin URL** tab once so rewrite rules and the .htaccess alias are
   written.

== Admin URL & lock-out safety ==

Important, please read.

WordPress serves `/wp-admin/*.php` as real files through your webserver, not
through WordPress routing. Because of that, a plugin cannot *transparently* move
the whole admin to a new path using PHP alone without risking a lock-out. This
plugin takes the safe approach:

1. **Login relocation is done fully in PHP** and is robust. `/wp-login.php` is
   hidden and the login form is served from your slug (default `/aaraa-admin/`).
   Password reset, logout, and registration keep working.

2. **Admin links are rewritten** so the UI never displays `/wp-admin/`.

3. **Transparent admin sub-pages** (`/aaraa-admin/plugins.php`) require a small
   webserver alias:
   * Apache — written automatically to `.htaccess` on activation / slug change.
   * nginx — add this to your server block (sub-paths only; the base is left to
     WordPress so the login form can be served there):
     `location ~ ^/aaraa-admin/(.+)$ { rewrite ^/aaraa-admin/(.+)$ /wp-admin/$1 last; }`

4. **If no alias is present**, requests to `/aaraa-admin/<file>.php` fall back to
   a safe 302 redirect to the real `/wp-admin/<file>.php`. Everything keeps
   working — the URL simply is not hidden for that hop. This is graceful
   degradation, never a lock-out.

**Emergency recovery.** If you are ever unable to reach the admin, add this to
`wp-config.php` above the "That's all, stop editing" line:

`define( 'AARAA_DISABLE_URL_REWRITE', true );`

This instantly restores `/wp-admin/` and `/wp-login.php`. Remove it once you have
fixed the slug in **Aaraa Settings → Admin URL**.

The slug cannot be set to reserved values (`wp-admin`, `wp-login`, `admin`,
`login`, etc.); those are rejected and the previous value is kept.

== WooCommerce / HPOS ==

The plugin declares compatibility with `custom_order_tables` (HPOS) and reads
order data through `wc_get_orders()`, so it works whether HPOS is on or off.

== Multisite ==

Network Activate to manage settings once for the whole network (stored with
`get_site_option`). Activated per-site, each site keeps its own `aaraa_settings`.

== Frequently Asked Questions ==

= Does the login form still work if I hide wp-login.php? =
Yes. Login, logout, lost password, reset and registration all continue to work
from the branded slug. Utility actions on `wp-login.php` (postpass, logout,
password reset, register, confirmaction) are allowed through so nothing breaks.

= How do I change the slug back? =
Aaraa Settings → Admin URL. Saving flushes rewrite rules and rewrites the
.htaccess alias automatically.

== Changelog ==

= 1.0.0 =
* Initial release.
