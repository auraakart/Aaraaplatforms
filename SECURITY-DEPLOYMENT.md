# AaraaKart security deployment checklist

## Deployment warning
The feature-testbyganesh branch contains breaking security hardening. Do **not** deploy until environment secrets have been injected, rotated, and the payment integrations have passed staging checks.

## Required environment variables
- MAIN_DB_HOST
- MAIN_DB_NAME
- MAIN_DB_USER
- MAIN_DB_PASSWORD
- WP_AUTH_KEY
- WP_SECURE_AUTH_KEY
- WP_LOGGED_IN_KEY
- WP_NONCE_KEY
- WP_AUTH_SALT
- WP_SECURE_AUTH_SALT
- WP_LOGGED_IN_SALT
- WP_NONCE_SALT
- PAYTM_MID
- PAYTM_MERCHANT_KEY
- PAYTM_WEBSITE_NAME
- CCA_MERCHANT_ID
- CCA_ACCESS_CODE
- CCA_WORKING_KEY

Generate **new**, unique WordPress salts and rotate all database/payment credentials because historical Git commits remain exposed. Update the active runtime configuration in the hosting environment. Never store production credentials in the Git repository or a web-accessible directory.

## Required manual operations
1. Rotate all exposed database users/passwords (including vendor DB accounts where applicable), merchant keys, access codes, and WordPress salts.
2. Audit historical commits and published forks/clones for credentials; plan Git history removal with repository owners after coordinated credential rotation.
3. Inspect previously tracked debug logs for any sensitive information, and remove copies from deployed webroots and storage.
4. Configure access restrictions, logging, and protections for payment callback endpoints.
5. Verify production HTTPS, scheduled jobs, and available WooCommerce gateway plugins.
6. Run PHPCS, PHP lint, dependency audits and integration tests prior to promoting the feature branch.

## Compatibility and testing
- Paytm initiation now requires a logged-in WooCommerce customer (or WooCommerce manager), a WordPress REST nonce in the X-WP-Nonce header (or _wpnonce), and an existing unpaid INR order. The amount is read only from WooCommerce.
- Paytm callback checks the submitted checksum, fetches transaction status from Paytm and checks merchant, order and amount before marking payment paid or failed.
- The standalone CCAvenue response endpoint is intentionally non-finalizing and returns a pending message. Use a validated WooCommerce payment gateway for order completion; do not treat the standalone callback as payment confirmation.
- Delivery calendar API requires authentication and customer ownership or list_users capability. Confirm the mobile app supplies the correct credentials.
- Run success, failure, pending, duplicate callback, amount mismatch, signature mismatch, guest checkout, and cross-customer access tests.
- Never merge to main without explicit external user approval.
