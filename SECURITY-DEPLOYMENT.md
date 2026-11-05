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

**Critical remaining blocker:** a feature-branch fix now rejects direct card-entry POSTs and restricts `_post_data` persistence to a safe allowlist, but the installed CCAvenue WooCommerce gateway still has a legacy card-entry interface and handles decrypted callbacks without checking response merchant, order amount and currency against the server-side order. Do **not** deploy or re-enable direct card entry until it is replaced or comprehensively remediated and validated with provider support. Historically stored PAN/CVV values may remain in existing database order metadata; review and securely purge prohibited verification data under an approved PCI data-remediation plan. Never dump these values into logs.

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
| WooCommerce CCAvenue gateway | Direct card entry must be rejected; block release until callback validation and sandbox checks are completed |

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

## Installed payment gateway hardening in feature branch
- The installed Paytm WooCommerce plugin's `saveTxnResponse` persistence now uses `$wpdb->insert/update` with sanitized/allowlisted status fields instead of concatenating callback values into SQL. The legacy two-argument call signature is supported.
- The installed Paytm callback no longer substitutes unverified incoming POST data when the gateway status lookup fails. Its success path checks the provider's order ID, merchant ID and paid amount against the WooCommerce order, and avoids duplicate stock decrements.
- The installed CCAvenue WooCommerce callback checks gateway, response merchant, currency and amount, but now **always returns HTTP 202 without changing order payment status**. Decryption and matching fields alone are not authoritative verification. The standalone callback likewise does not finalize orders. This is an intentional fail-closed change that prevents automated CCAvenue settlement until a supported gateway or independently verified provider status flow is installed. Direct card entry remains rejected, with card-number/CVV fields removed from outgoing requests.
- These changes are code-level safeguards, **not proof of successful or authenticated end-to-end payment**. The legacy CCAvenue encrypted response does not itself prove provider settlement or authenticate against a fresh provider-side order-status API. The CCAvenue plugin must be replaced with a current supported PCI-compliant gateway or independently verified in a controlled provider sandbox before enabling it in production.
- The installed Paytm plugin may use a legacy status API whose response fields differ from current provider versions; the new fail-closed checks may reject otherwise legitimate payments until the compatibility matrix is validated. Never weaken amount/merchant verification to work around a failed test.

## Release readiness and approval boundaries
- Only `feature-testbyganesh` is authorized for ongoing code remediation.
- Draft PR #1 targets `develop`; do not merge it without review and staging checks.
- `staging` is a separate branch and has not been promoted.
- `main` must remain unchanged without explicit external user approval.
- External operations still pending: provider credentials rotation, host environment variable deployment, prior-version secret history handling, live data cleanup, payment gateway sandbox verification, WordPress functional tests, and staging deployment.

## Follow-up verification status
- Existing Paytm WooCommerce callback now guards already-paid orders against failed/pending status transitions, handles missing provider status safely, and requires a nonempty provider transaction ID before completing a payment.
- Legacy CCAvenue callback does not currently complete payment or mark failure. **Operations must not enable this gateway for customer checkout until a tested authoritative verification integration is provided.**
- Test harness checks these fail-closed code conditions and PHP syntax, not real gateway transactions.
- Required external access to proceed with sandbox and release: isolated WordPress/WooCommerce staging host, nonproduction database, new nonproduction Paytm and CCAvenue credentials, and authorized payment provider verification documentation. Do not place any credentials in GitHub issues, commits, or chats.

## Code-only security remediation completed in follow-up
- Tenant routing now requires `AARAA_BASE_DOMAIN` (for example `aaraakart.com`) and rejects unexpected Host values or additional subdomain levels. The helper is separately tested with spoofed and malformed hostnames. **Configure this environment variable before deploying**, or all requests are rejected.
- Vendor database credentials are no longer serialized into executable PHP temporary cache files. The master routing database is queried per request, which may affect latency until a properly isolated secret-safe cache is introduced.
- Paytm transaction-status AJAX is restricted to users with `manage_woocommerce`, an administrator nonce, matching WooCommerce Paytm order and stored transaction record, and verified provider identifiers. No raw status response is sent to the browser.
- A provider outage no longer marks an unpaid Paytm order failed.
- The old non-settling CCAvenue WooCommerce gateway now returns unavailable at checkout and refuses payment initiation. It must remain disabled until replaced with a verified provider integration.
- The legacy CCAvenue class no longer captures card numbers, expiration fields or CVV into properties. Existing stored card data is a separate operations problem.
- The source-security workflow includes `tests/tenant-routing.php` in addition to PHP lint and the existing authorization tests.

## Engineering follow-up after security gate
- Run deployment-specific end-to-end tests for product purchase, wallet debit, subscription renewal, delivery scheduling, refunds, and duplicate payment callbacks. These cannot be fully simulated by the current source-only CI.
- Assess plugin `-old` files against active plugin registration before deletion. Avoid deleting active or externally referenced code without verification.
- Review compatibility with WooCommerce HPOS and vendor-specific database configurations.
