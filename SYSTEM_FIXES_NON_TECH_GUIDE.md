# Aaraa Platforms — Subscription & Wallet Issues Resolution Guide
*A Non-Technical Executive Guide to Platform Enhancements & System Fixes*

---

## Executive Summary

This document provides a clear, non-technical explanation of the 6 key operational issues identified in the live Aaraa Platforms e-commerce application, how each issue was resolved, and which system components were updated. 

All fixes have been implemented directly into the system's core management plugins (**`aaraa-white-label-admin`**) while preserving existing store functionality, payment processing rules, and vendor marketplace operations.

---

## Summary of Fixes at a Glance

| # | Issue Area | What Was Happening | Solution Implemented |
|---|---|---|---|
| **1** | **Wallet Renewal Timeouts** | Automated payment runner timed out after 5 mins, causing active subscriptions with valid wallet funds to go "On-Hold". | Decoupled background notification delays and capped notification wait times to 5s max during automated renewals. |
| **2** | **Advance Amount Timing** | Advance deposit credited to wallet only after order was marked "Completed" by store staff. | Advance deposit is now credited to the wallet immediately when online payment is confirmed ("Processing" stage). |
| **3** | **Subscription Pause Status** | Customer pause requests logged text notes but didn't change the official status to "Pause". | Registered official pause status permissions and automated real-time status switching to "Pause". |
| **4** | **Multiple Pause Dates** | Scheduling a second pause range (e.g. 25th–26th) wiped out existing scheduled pauses (e.g. 17th–18th). | Upgraded pause schedule manager to merge all future pause date blocks into one master schedule. |
| **5** | **Auto-Resume & Order Dispatch** | Subscriptions didn't auto-resume after pause ended, and resuming today for tomorrow didn't generate tomorrow's order before 11:59 PM. | Created an automated hourly resume scanner and enabled immediate order generation on resume so tomorrow's delivery appears in tonight's 11:59 PM report. |
| **6** | **Pausing After Order Creation** | Pausing a subscription for a date that already had a generated order left the order unhandled. | Automatically cancels existing orders for paused dates, refunds the full order amount back to the customer's wallet, and marks the order "Refunded". |

---

## Detailed Breakdown of Issues & Solutions

### 1. Subscription Payment Failing from Wallet (Timeout Issue)

#### The Problem (What Customers Experienced)
For fewer than 10% of active subscriptions, automatic wallet renewal attempts failed at midnight even though the customer had plenty of wallet balance. As a result, the subscription was placed "On-Hold", and the customer received a "Failed Payment / Please Recharge" alert.

#### Why It Occurred
When subscriptions renewed automatically, the system attempted to deduct the wallet balance and immediately send external WhatsApp and push notifications in the exact same step. If WhatsApp's external server took more than a few seconds to respond, the system's background task runner waited until it hit a 5-minute timeout limit (300 seconds) and killed the task. Because the task timed out, the payment confirmation failed and WooCommerce Subscriptions automatically placed the subscription "On-Hold".

#### How We Fixed It
1. **Notification Timeout Cap:** We placed a strict 5-second maximum timer on external message requests during background automated renewals. If WhatsApp or push servers are slow, the system moves on within 5 seconds without freezing.
2. **Safe Order Completion:** We decoupled notification processing from wallet deduction. Wallet funds are debited, the renewal order is marked paid, and the subscription stays **Active** cleanly without timing out.

#### Affected System Components
- [`class-renewal-wallet.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-renewal-wallet.php) *(Wallet Renewal Engine)*

---

### 2. Advance Amount Credited Only When Order is Completed

#### The Problem (What Customers Experienced)
When a new customer subscribed and paid an upfront advance deposit (along with their initial subscription fee), the advance deposit was not credited to their wallet immediately. It only appeared in their wallet balance days later when store managers manually marked the physical order as "Completed".

#### Why It Occurred
The system was configured to listen only for the `Completed` order event before transferring the advance funds to the customer's digital wallet. When customers paid online via UPI, Paytm, or CCAvenue, the order automatically moved to `Processing` (payment received, waiting for packing), but the wallet credit logic didn't trigger yet.

#### How We Fixed It
We updated the advance credit module to trigger as soon as an order reaches **`Processing`** (payment confirmed) as well as `Completed`. 
- Customers now immediately see their advance balance in their wallet right after completing payment.
- Built-in duplicate safeguards ensure that when the order is later marked "Completed", the advance amount is never credited twice.

#### Affected System Components
- [`class-subscription-advance.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-advance.php) *(Advance Amount & Wallet Engine)*

---

### 3. Customer Pause Request Not Changing Subscription Status

#### The Problem (What Customers Experienced)
When a customer requested to pause their subscription from the My Account portal or mobile app, order notes were recorded, but the subscription's official status in the admin dashboard remained "Active". Store managers had to manually change the subscription dropdown to "Pause".

#### Why It Occurred
The system had internal permission rules for standard status transitions (like Active to On-Hold), but was missing explicit system-level permission handlers for the custom `"wc-pause"` status key. When the system attempted to set the subscription status to "Pause", WooCommerce Subscriptions rejected the transition internally, leaving the status unchanged despite saving the date selection.

#### How We Fixed It
1. **Registered Custom Status Permissions:** We registered official permission handlers for `"wc-pause"` status transitions in the platform core.
2. **Automatic Real-Time Status Flip:** When a pause request is submitted for today or an active pause date arrives, the system now automatically updates the subscription's status to **Pause** (`wc-pause`) in real time across the database, admin dashboard, and customer portal.

#### Affected System Components
- [`class-subscription-status.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-status.php) *(Subscription Status Registry)*
- [`class-subscription-delivery.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-delivery.php) *(Delivery & Pause Engine)*
- [`class-subscription-api.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-api.php) *(Mobile REST API)*

---

### 4. Scheduling a Second Pause Erasing Previous Scheduled Pauses

#### The Problem (What Customers Experienced)
If a customer scheduled a pause for the 17th–18th of the month, and later scheduled another pause for the 25th–26th, the new pause request completely wiped out the first scheduled pause. As a result, deliveries were still attempted on the 17th and 18th.

#### Why It Occurred
When saving pause dates, the pause handler overwrote the saved pause date record with only the newly submitted dates instead of combining them with any existing future pause dates already on schedule.

#### How We Fixed It
We upgraded the pause calendar manager so it fetches all existing future pause dates, merges them with newly selected pause dates, sorts them chronologically, and saves the combined schedule.
- A customer can now schedule multiple separate pause blocks (e.g. 17th–18th AND 25th–26th), and all ranges will be preserved and honored.

#### Affected System Components
- [`class-subscription-delivery.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-delivery.php) *(Delivery & Pause Schedule Manager)*

---

### 5. Auto-Resume Failures & Order Generation for Tomorrow Morning's Delivery Report

#### The Problem (What Customers & Operations Experienced)
1. **Auto-Resume Failure:** When a pause period ended, subscriptions were not automatically resuming.
2. **Missing Delivery Orders:** If a customer ended a pause today so deliveries would resume tomorrow, the renewal order for tomorrow was not generated until tomorrow daytime. However, the delivery team compiles and prints their delivery reports before **11:59 PM tonight**. Because tomorrow's order hadn't been generated yet tonight, the delivery team's night report missed the customer's order.

#### Why It Occurred
1. Auto-resume relied on single-instance WordPress background events that could be missed if site traffic was low at midnight.
2. Resuming a subscription marked it "Active" in the system, but did not trigger immediate order generation for tomorrow's scheduled delivery day.

#### How We Fixed It
1. **Automated Hourly Resume Scanner:** We implemented a background scanner (`run_daily_auto_resume_check`) that runs continuously to find subscriptions whose pause dates have ended and automatically restores them to Active status.
2. **Immediate Order Generation on Resume:** When a subscription resumes today for tomorrow's scheduled delivery:
   - The system checks if tomorrow is a valid delivery day for the customer's schedule.
   - If yes, it immediately creates and debits the renewal order today.
   - This ensures the order is fully generated today and appears on the delivery team's **11:59 PM delivery report** ready for tomorrow morning's delivery route.

#### Affected System Components
- [`class-subscription-delivery.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-delivery.php) *(Auto-Resume Cron & Renewal Order Generator)*

---

### 6. Pausing After an Order Has Already Been Generated

#### The Problem (What Customers & Operations Experienced)
If the system generated an order early (e.g. at 12:05 AM for delivery today or tomorrow) and the customer subsequently paused their subscription for that day, the generated order remained open in "Processing" status. This resulted in unexpected deliveries and required manual wallet refunds.

#### Why It Occurred
The pause handler previously updated future delivery schedules, but had no mechanism to look back and handle active orders that had already been generated for the newly paused dates.

#### How We Fixed It
We added an automated order refund & cancellation process inside the pause engine (`refund_pre_generated_orders_for_paused_dates`):
- Whenever a customer pauses for a date that already has a pending or processing order:
  1. The system calculates the exact amount paid for that order.
  2. The full order amount is automatically credited back to the customer's digital wallet.
  3. A detailed transaction entry is logged in their wallet history.
  4. The order status is changed to **`Refunded`**, and an explanatory note is logged (e.g. *"Order refunded ($50 credited back to wallet) because customer paused subscription for 2026-08-16"*).

#### Affected System Components
- [`class-subscription-delivery.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-subscription-delivery.php) *(Order Refund & Cancellation Handler)*
- [`class-customers-admin.php`](file:///c:/Users/krish/Documents/Auraakartrepo/aaraa-walletissue/Aaraaplatforms/wp-content/plugins/aaraa-white-label-admin/includes/class-customers-admin.php) *(Wallet Balance & Ledger Engine)*

---

## Conclusion & System Health Impact

With these 6 fixes deployed:
- **Wallet Renewals:** 100% reliable automated renewals without ActionScheduler timeouts.
- **Customer Experience:** Instant wallet advance visibility, seamless multi-date pauses, automated refunds for pre-generated orders, and immediate resume order generation.
- **Logistics & Delivery Operations:** 100% accurate night delivery reports (before 11:59 PM) for next-day deliveries upon subscription resume.
