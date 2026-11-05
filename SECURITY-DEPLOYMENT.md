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


## Checkout and gateway blockers discovered during follow-up
The repository contains two WooCommerce gateways:
- `wp-content/plugins/paytm-payments/` is an existing Paytm gateway with its own callback/order-completion flow.
- `wp-content/plugins/ccavanue-woocommerce-payment-getway/` is an existing CCAvenue gateway with its own checkout and callback flow.

The standalone `paytm/*.php` and `ccavenue/*.php` scripts are **not** replacements for these WooCommerce gateway integrations. Confirm which integration the mobile app and storefront actually call, and ensure duplicate payment routes cannot mark the same order paid twice.

**Critical remaining blocker:** the existing CCAvenue WooCommerce plugin stores unsanitized complete checkout `$_POST` in order metadata as `_post_data` (including fields named `card_number` and `cvv_number`). It also populates `card_number` and `cvv_number` in its gateway request arguments and handles a decrypted callback without verifying the response merchant, order amount and currency against the server-side order. Do **not** enable direct card-entry mode or deploy that integration until replaced or remediated and validated by the payment provider's supported integration. Review and securely purge already stored prohibited card verification values in accordance with the provider's PCI guidance; do not dump these values into support logs. Existing stored values require a controlled data-remediation task by the database owner.

**Blocking pre-release requirement:** inspect and audit the installed Paytm WooCommerce gateway independently. Its callback implementation has legacy conditional payment completion logic and response persistence. The custom Paytm standalone callbacks have been hardened but this does not automatically harden the installed gateway.

## Sandbox payment test matrix (run before deployment)
| Scenario | Expected result |
| --- | --- |
| Guest or unauthenticated direct Paytm initiation | Rejected, no transaction |
| Invalid or missing WordPress nonce | Rejected |
| Customer requests another customer's order | Rejected |
| Browser-supplied altered amount | Ignored; WooCommerce order total used |
| Missing merchant environment variables | Clear service-unavailable response, no charge |
| Provider returns invalid signature | Never mark order paid |
| Provider returns wrong MID/order/amount | Never mark order paid |
| Verified success response | Mark WooCommerce order paid once, save transaction ID |
| Callback repeated | No duplicate order completion |
| Verified failure | Mark failed only if order was not previously paid |
| Pending or unavailable provider status | Leave order unpaid/pending |
| Delivery calendar unauthenticated or cross-customer | 401/403, no order details |
| CCAvenue standalone callback | No customer payment data echoed; no false paid status |
| WooCommerce CCAvenue gateway | Block release until card handling and response validation are addressed |

## Credential rotation and history cleanup
These are **operational tasks, not completed by repository commits**. The repository was public, so treat old credentials as exposed even when deleted from the current branch.
1. Generate and securely store replacement main/vendor database credentials, WordPress salts, Paytm MID/key and CCAvenue merchant/access/working keys; rotate with each payment provider and hosting operator.
2. Test updated application credentials in a nonproduction environment before production cutover.
3. Revoke exposed old credentials and monitor payment/database access logs for abnormal usage.
4. Coordinate history cleanup with all repository contributors, forks, CI owners and backup administrators. Git history rewrite requires a separate explicit approval, force-push plan, and user coordination. Never rewrite `main` without external approval.
5. Recheck exposed artifacts in pull requests, GitHub Actions logs, releases and forks after history cleanup.
6. Store only example secret **names**, not secret values, in the repository.

## CI scope
`.github/workflows/feature-security-checks.yml` runs syntax lint for touched PHP and a basic static security contract test. This does not exercise live WordPress/WooCommerce databases, webhook signatures end-to-end, gateway sandbox payments, or website functionality. Do not promote until a staging instance passes the full test matrix.
