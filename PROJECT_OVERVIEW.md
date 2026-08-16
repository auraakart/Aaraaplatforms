# Aaraa Platforms — Technical Project Overview & Architecture

## 1. Executive Summary

**Aaraa Platforms** (also operating as **Aaraakart**) is an enterprise, multi-tenant SaaS e-commerce and subscription delivery platform built on WordPress and WooCommerce. The architecture is engineered to support multi-vendor e-commerce marketplaces, recurring subscription services (such as daily milk and grocery delivery), custom wallet-based pre-paid transactions, localized payment gateways (CCAvenue, Paytm), and automated customer notifications via WhatsApp and Firebase.

### Core Technology Stack
- **Base Framework:** WordPress 6.5+ (PHP 8.0+ minimum requirement)
- **E-Commerce & Subscriptions:** WooCommerce (HPOS compliant), WooCommerce Subscriptions, WooCommerce All Products for Subscriptions (WCS ATT)
- **Multi-Tenancy:** Custom Host-based Dynamic PDO Database Router with temp file caching
- **Multi-Vendor Engine:** WCFM (WooCommerce Frontend Manager) Suite & Marketplace REST APIs
- **Custom SaaS Plugins:** `aaraa-white-label-admin`, `milk-delivery-schedule`
- **Payment & Wallet Systems:** `wallet-system-for-woocommerce`, `wallet-system-for-woocommerce-pro`, `ccavanue-woocommerce-payment-getway`, `paytm-payments`
- **Integrations:** WhatsApp Business API, Firebase Push Notifications, LiteSpeed Caching

---

## 2. Multi-Tenant Architecture & Infrastructure

### 2.1 Dynamic Database Routing
Aaraa Platforms implements host-level dynamic database selection inside `wp-config.php` to isolate database access per vendor subdomain without needing separate physical WordPress installations.

```
                  ┌───────────────────────────────┐
                  │      Incoming HTTP Request    │
                  └──────────────┬────────────────┘
                                 │
                   Host: store1.aaraakart.com
                                 │
                                 ▼
                    ┌───────────────────────────┐
                    │     wp-config.php         │
                    └────────────┬──────────────┘
                                 │
            ┌────────────────────┴────────────────────┐
            │ Subdomain extracted ("store1")          │
            └────────────────────┬────────────────────┘
                                 │
                   ┌─────────────┴─────────────┐
                   ▼                           ▼
        Check Local Cache              Query Master DB
     (/tmp/vdb_[md5].php)           (u293817202_akartmaster_db)
     [TTL: 5 Minutes]               SELECT db_* FROM vendors
                   │                WHERE subdomain = 'store1'
                   └─────────────┬─────────────┘
                                 │
                                 ▼
                   Define DB_HOST, DB_NAME,
                   DB_USER, DB_PASSWORD
                                 │
                                 ▼
                     Boot WordPress Instance
```

- **Master Database (`u293817202_akartmaster_db`):** Maintains the `vendors` master routing table.
- **Routing Engine (`aaraakart_get_vendor_db`):** Resolves incoming host subdomains (excluding `www` and `api`) to active vendor database credentials using a fast PDO fallback connection.
- **Performance Caching:** Vendor database config vectors are cached on disk (`/tmp/vdb_[md5].php`) with a 300-second TTL to eliminate database routing overhead on subsequent HTTP requests.

---

## 3. Core Custom Modules & Extensions

### 3.1 `aaraa-white-label-admin` (SaaS Control Engine)
Location: [`wp-content/plugins/aaraa-white-label-admin`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin)

This plugin serves as the central administrative engine for Aaraa Platforms, transforming standard WordPress into a fully branded SaaS control panel.

#### Key Architectural Components:
1. **White-Labeling & Security:**
   - [`class-admin-theme.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-admin-theme.php): Custom SaaS styling for WordPress admin dashboard.
   - [`class-admin-url-rewriter.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-admin-url-rewriter.php): Custom login and admin slug rewriter for security and white-labeling.
   - [`class-roles.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-roles.php): Custom Role-Based Access Control (RBAC) for vendor staff, delivery drivers, and admins.
   - [`class-security.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-security.php): Hardening features, restricting standard WP admin vectors.

2. **Subscriptions & Delivery Management:**
   - [`class-subscription-delivery.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-delivery.php) & [`class-subscription-api.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-api.php): Custom REST endpoints and business logic for subscription scheduling.
   - [`class-subscription-pause-report.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-pause-report.php) & [`class-subscription-resume-report.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-resume-report.php): Auditing subscription pause/resume histories.
   - [`class-delivery-admin.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-delivery-admin.php), [`class-delivery-report.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-delivery-report.php), & [`class-delivery-slots-api.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-delivery-slots-api.php): Slot configuration, route planning, and daily dispatch sheets for delivery agents.

3. **Wallet & Renewal Systems:**
   - [`class-wallet-admin.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-wallet-admin.php) & [`class-renewal-wallet.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-renewal-wallet.php): Administrative wallet top-up management, auto-deduction handling for subscriptions, balance checks, and renewal validation.

4. **Integrations:**
   - [`class-whatsapp-admin.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-whatsapp-admin.php): Automated WhatsApp messaging engine for transactional alerts and order status.
   - [`class-firebase-admin.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-firebase-admin.php): Push notification triggers for mobile applications (customer & delivery boy apps).

5. **Logging & Monitoring:**
   - [`class-audit-logs.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-audit-logs.php) & [`class-application-log.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-application-log.php): Tracks administrative mutations, user actions, system exceptions, and API transactions.

---

### 3.2 `milk-delivery-schedule` (Delivery Logistics Engine)
Location: [`wp-content/plugins/milk-delivery-schedule`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/milk-delivery-schedule)

Custom plugin designed specifically for subscription products (e.g., daily milk, fresh produce delivery).

#### Delivery Frequencies Supported:
- **Every Day** (`everyday` -> `daily`)
- **Alternate Day** (`alternate`)
- **Weekend** (`weekend` -> Saturday & Sunday)
- **Custom Days** (`custom` -> Selection of specific weekdays 0-6)

#### Metadata Dual-Writing & HPOS Compatibility:
When an order or subscription is placed, `milk-delivery-schedule` normalizes selected delivery days and delivery slots (`_aaraa_delivery_slot`). It performs dual-writes to both the WC Subscription CRUD object and postmeta (`_wcfm_delivery_schedule`, `_wcfm_delivery_days`, `_aaraa_delivery_slot`) to ensure compatibility with standard legacy postmeta queries and WooCommerce High-Performance Order Storage (HPOS).

---

### 3.3 Payment Gateway & Wallet Infrastructure

1. **WooCommerce Wallet System:**
   - Extensions: `wallet-system-for-woocommerce` & `wallet-system-for-woocommerce-pro`.
   - Allows users to maintain a pre-paid balance. Subscriptions dynamically auto-renew using available wallet funds.
2. **CCAvenue Gateway:**
   - [`ccavanue-woocommerce-payment-getway`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/ccavanue-woocommerce-payment-getway) + root [`/ccavenue`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/ccavenue) script set (Crypto JS/PHP encryption wrappers, request/response handlers).
3. **Paytm Payments Gateway:**
   - [`paytm-payments`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/paytm-payments) + root [`/paytm`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/paytm) standalone checksum utilities (`PaytmChecksum.php`, transaction initiation scripts).

---

### 3.4 Multi-Vendor & Marketplace System

Aaraa Platforms relies on the **WCFM (WooCommerce Frontend Manager)** ecosystem:
- **`wc-multivendor-marketplace`:** Vendor storefronts, store pages, Commission structures, and payout rules.
- **`wc-frontend-manager-delivery`:** Vendor delivery driver assignment and order dispatch handling.
- **`wc-frontend-manager-ultimate` & `wcfm-marketplace-rest-api`:** Advanced vendor dashboard modules and REST API surface consumed by vendor mobile applications.

---

## 4. Key Directory Structure

```
Aaraaplatforms/
├── wp-config.php                         # Dynamic Multi-Tenant DB router & core constants
├── wp-config-aaraa.php                   # Alternate/backup platform configuration
├── ccavenue/                             # Root CCAvenue gateway execution scripts
├── paytm/                                # Root Paytm gateway execution scripts
├── wp-content/
│   ├── plugins/
│   │   ├── aaraa-white-label-admin/      # SaaS admin, RBAC, Subscriptions & Delivery API, WhatsApp, Firebase
│   │   ├── milk-delivery-schedule/       # Product delivery schedule picker & subscription meta link
│   │   ├── wallet-system-for-woocommerce/# Core wallet engine
│   │   ├── wallet-system-for-woocommerce-pro/ # Advanced wallet features (QR codes, email notifications)
│   │   ├── ccavanue-woocommerce-payment-getway/ # CCAvenue WooCommerce integration
│   │   ├── paytm-payments/               # Paytm WooCommerce integration
│   │   ├── wc-multivendor-marketplace/   # WCFM Multi-vendor store engine
│   │   ├── wc-frontend-manager-*/        # WCFM Delivery, Groups, Analytics, Ultimate modules
│   │   └── woocommerce-subscriptions/    # Subscription billing engine
│   └── themes/
│       └── econis/                       # Primary e-commerce theme (Dokan, WCFM, WC templates)
```

---

## 5. Operations & Developer Guidelines

### 5.1 Debugging & Logs
- **WordPress Debug Log:** Enabled via `wp-config.php` at [`wp-content/debug.log`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/debug.log).
- **Application Log System:** Inspect [`class-application-log.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-application-log.php) for structured system logging in the database/admin table.
- **Audit Trails:** Managed via [`class-audit-logs.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-audit-logs.php) for tracking admin user activity.

### 5.2 Vendor Database Provisioning
To onboard a new vendor/tenant:
1. Provision a new MySQL database instance.
2. Insert a record into the `vendors` table in the master database (`u293817202_akartmaster_db`):
   ```sql
   INSERT INTO vendors (subdomain, db_host, db_port, db_name, db_user, db_pass, status)
   VALUES ('tenantname', 'localhost', 3306, 'tenant_db_name', 'tenant_db_user', 'password', 'active');
   ```
3. HTTP requests to `tenantname.aaraakart.com` will automatically target the new vendor database.

---
*Report generated for Aaraa Platforms Codebase Review.*
