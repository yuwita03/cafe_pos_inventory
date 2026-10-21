# Midtrans QRIS — Cashier Payment Specification

> **Date:** 2026-10-21.
>
> **Status:** Agreed product decisions documented; specification awaiting review. Implementation has not started.
>
> **Scope:** First Midtrans QRIS release for cashier checkout, tested in sandbox before production.
>
> **Related work:** Stage 08 / F05. Self-order QRIS remains a later slice of that stage.

## Problem Statement

Cashiers can currently complete cash sales, but QRIS payment processing is unavailable. They need to take a customer's order, select QRIS, and show a payment QR on the existing cashier screen. Customers must not need an account or a separate display.

Displaying a QR is only part of the problem. The application must prevent overselling while customers pay, establish payment success from Midtrans, keep uncertain transactions from producing duplicate payment attempts, and send only eligible paid orders to the kitchen. Cashiers must be able to serve other customers and hand orders over to another cashier without losing payment state.

## Solution

Add Midtrans QRIS to cashier checkout alongside cash. Each order is paid in full by one method. Selecting QRIS creates a pending order and stock reservation, locks its items and total, and displays the QR with the amount and a visible 15-minute countdown.

Verified server-side payment confirmation queues the order automatically, even when its QR display is closed. Payment confirmation keeps the stock reservation and removes its expiry. Physical stock is deducted only when the kitchen first moves the order from queued to preparing, matching existing cash behavior.

Closing the QR display leaves payment pending. Cancellation, editing, switching to cash, expiry, and uncertain provider outcomes are separate operations. The application must resolve the existing attempt before allowing a replacement payment. Late payments after stock has been released require owner review.

All cashiers share access to all orders, including existing cash orders. Routine order handling remains subject to role permissions; financial exceptions and refund recording remain owner-only.

## User Stories

1. As a cashier, I want to build an order using the existing products, variants, options, quantities, and fulfilment choices, so that QRIS fits the existing checkout.
2. As a cashier, I want to select cash or QRIS for the full total, so that I can use the customer's chosen payment method.
3. As a customer, I want to scan a payment QR on the cashier's existing screen, so that I can pay without an account or another device supplied by the café.
4. As a cashier, I want the QR display to identify the order and show its amount, so that I present the correct payment to the customer.
5. As a customer, I want a visible 15-minute countdown, so that I know the intended payment window.
6. As a cashier, I want QR creation to lock the order's items and total, so that the displayed payment matches the order.
7. As a cashier, I want confirmed cancellation before editing a QRIS order, so that an old QR cannot remain payable for a different total.
8. As a cashier, I want stock reserved while payment is pending, so that another sale cannot consume the same remaining ingredients.
9. As a cashier, I want to close the QR display without cancelling payment, so that I can serve another customer.
10. As a cashier, I want to find pending orders and reopen their existing QR, so that customers can continue paying without another attempt being created.
11. As a cashier, I want to see and handle orders created by other cashiers, so that a shift change or temporary absence does not block service.
12. As an owner, I want order creation and subsequent cashier actions attributed to their actors, so that shared access preserves accountability.
13. As a cashier, I want the application to establish payment success from Midtrans, so that a customer claim or screenshot cannot mark an order paid.
14. As a cashier, I want a Check payment status action when confirmation is delayed, so that I can request an authoritative update.
15. As a cashier, I want successful payments to identify the paid order clearly, so that I notice confirmations while handling another order.
16. As kitchen staff, I want eligible confirmed payments to queue automatically, so that preparation does not depend on the cashier keeping a QR screen open.
17. As an owner, I want paid orders to retain their stock reservations without expiry, so that kitchen delays cannot free ingredients already committed to customers.
18. As kitchen staff, I want stock deducted once when preparation starts, so that payment confirmation and repeated kitchen actions do not deduct it again.
19. As a cashier, I want cancellation and switching to cash blocked until the QRIS outcome is resolved, so that the customer is not charged twice.
20. As a cashier, I want a timed-out creation request to show Checking payment status, so that an uncertain result is not mistaken for a failed payment.
21. As a cashier, I want automatic reconciliation and a manual status check for uncertain attempts, so that an existing QR or successful payment can be recovered.
22. As a cashier, I want stock released only after an unpaid QRIS attempt is confirmed terminal, so that the countdown does not free stock while payment is still possible.
23. As a cashier, I want expired orders preserved as history, so that their payment attempts remain traceable.
24. As a cashier, I want Copy to checkout on an expired order, so that I can retry without entering every item again.
25. As a cashier, I want copied orders checked against current stock and prices, with unavailable items and price changes clearly shown, so that I confirm a valid new sale.
26. As an owner, I want late payments routed to payment review, so that released stock is not silently promised again.
27. As an owner, I want to fulfil a reviewed order only after stock is rechecked and reserved, while honouring the amount already paid, so that the customer receives the agreed purchase when fulfilment is possible.
28. As an owner, I want to record a refund arranged outside the application with a reference or explanation, so that financial exceptions have a recorded resolution.
29. As an owner, I want cashiers prevented from resolving financial exceptions, so that shared order handling does not grant refund authority.
30. As a developer, I want sandbox verification before real payments, so that important payment and stock failure paths are exercised safely.
31. As an owner, I want production QRIS disabled until release requirements pass, so that a successful sandbox demonstration is not mistaken for production readiness.

## Implementation Decisions

### Scope and role boundaries

- Midtrans is the selected provider. The first release handles cashier-created QRIS orders; self-order QRIS follows later.
- Use the existing cashier screen. A dedicated customer display and customer accounts are unnecessary for this flow.
- Each order is paid in full by cash or QRIS. Split payments are excluded.
- All cashiers can view and perform role-permitted handling of all orders, including other cashiers' cash orders and existing guest orders. Remove creator-based visibility restrictions for this shared order workflow.
- Shared access does not grant kitchen operations, owner management, or exception-resolution permissions. Existing role boundaries remain authoritative.
- Preserve the original creator and attribute subsequent cancellation, payment-method changes, and owner resolutions to the acting staff member. Attribution must not be overwritten by another cashier taking over.

### Checkout and payment display

- Reuse existing checkout price calculation, item and option validation, recipe snapshots, stock checks, and order history.
- Create the pending order, payment attempt identity, and stock reservation consistently before relying on a provider response. Retain sufficient identity to reconcile a timed-out request.
- QR generation locks the items and total. Changes require the current unpaid attempt to be confirmed cancelled before returning to editing; a revised order requires a new payment attempt and QR.
- Closing the display is a navigation action, not a cancellation. Reopening recovers the same pending attempt and its remaining payment window; it does not reset the countdown or create another charge.
- Show the order identity, amount, QR, visible countdown, current payment state, and relevant actions clearly. Distinguish Close, Cancel payment, and Check payment status.
- Payment success queues the order without a second cashier approval. Give the cashier a clear indication identifying the paid order, including when its QR display has been closed. Exact notification delivery mechanics remain an implementation detail.

### Payment and stock lifecycle

| Situation                                                                | Order handling                                                                 | Stock handling                                                                                         |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| QRIS creation or payment pending                                         | Await payment; exclude from kitchen                                            | Reserve required stock; do not deduct physical stock                                                   |
| Creation request times out or provider outcome is unknown                | Show Checking payment status; reconcile the same attempt                       | Keep the reservation effective until the outcome is resolved                                           |
| Verified full payment while the original reservation is intact and valid | Record paid and queue once                                                     | Keep the active reservation and remove its expiry                                                      |
| Countdown reaches zero                                                   | Check or terminate the provider attempt; do not infer payment failure locally  | Do not make reserved stock available solely because time elapsed                                       |
| Confirmed unpaid cancellation, expiry, or terminal failure               | Resolve the attempt; allow the appropriate edit, method switch, or expiry flow | Release a closed order's reservation, or maintain/revalidate it consistently for a continuing checkout |
| Verified payment after reservation release                               | Enter payment review; do not queue automatically                               | Do not deduct or silently reinstate released stock                                                     |
| Owner approves fulfilment from payment review                            | Honour the paid amount and queue only after a successful stock check           | Reserve required stock again; keep it until preparation                                                |
| Owner records an externally completed refund from payment review         | Record the resolution and refunded payment outcome; exclude from kitchen       | Leave the abandoned reservation released                                                               |
| Kitchen first advances queued to preparing                               | Use the existing kitchen transition                                            | Consume reservations and deduct physical stock once                                                    |
| Kitchen advances preparing to ready to completed                         | Use existing transitions                                                       | No further deduction                                                                                   |

- The earlier interview suggestion to deduct stock at payment confirmation was explicitly corrected. The agreed rule is deduction at the start of preparation.
- Paid reservations do not expire because preparation takes time.
- The intended customer payment window is 15 minutes, configured with the provider and reflected by an authoritative server deadline.
- A browser countdown is informational. Browser closure, refresh, or reaching zero cannot establish that a QR is unpayable.
- Before releasing a QRIS reservation, establish the authoritative unpaid terminal outcome, requesting cancellation or expiry where needed. If status remains unknown, keep the order unresolved and block a replacement payment.
- Every stock-availability calculation must respect this rule, not only the scheduled release operation. The current stock queries exclude timestamp-expired holds even before an explicit release; QRIS must not inherit that behavior while its provider outcome remains unresolved.
- Payment arriving after a local deadline must be reconciled against the actual reservation and provider outcome. A released hold always requires owner review; it must never be queued using ingredients already made available to other orders.
- Do not automatically restore physical stock after preparation has started. This specification does not add post-preparation cancellation or refund behavior.

### Confirmation, uncertainty, and cancellation

- Establish QRIS success through verified server-side Midtrans notifications or an authenticated server-side status lookup. A customer screenshot, browser request, or cashier assertion cannot mark QRIS paid.
- Validate provider authenticity, payment identity, order association, expected amount, and currency before applying a success result. Unexpected financial results require review rather than automatic preparation.
- Use the same reconciliation rules for notification processing, automatic checks, and the manual Check payment status action.
- Notification retries, duplicate actions, concurrent checks, and out-of-order results must not create additional paid payments, kitchen admissions, reservations, or stock deductions, or downgrade an established successful payment.
- A request timeout is an unknown outcome, not proof of failure. Reconcile the original attempt identity before issuing another creation request.
- If the original attempt exists and is pending, recover its QR. If it is paid, process the verified success under the reservation rules. If it is authoritatively unsuccessful and cannot still be paid, allow the appropriate retry or cash flow.
- An unresolved attempt blocks a new payable QR and switching to cash. The cashier can continue serving other customers while reconciliation proceeds.
- Confirm cancellation, expiry, or failure before collecting cash for the same order. Hiding the QR or requesting cancellation is insufficient.
- If payment wins a race with cancellation, handle it as payment success or payment review according to stock state; do not proceed to collect cash.
- Keep a durable attempt history and preserve existing checkout idempotency. Shared order access must not turn a changed checkout payload into a duplicate sale or overwrite the original creator.
- At most one active payable attempt should exist for an order. Preserve the database safeguard allowing at most one paid payment per order, while retaining enough evidence to review unexpected provider receipts rather than discarding them.

### Expired orders and Copy to checkout

- Preserve expired orders and payment history. Do not revive an expired order when the cashier wants another QR.
- Copy to checkout is a cashier action, available without customer login. It copies the expired order's item selections into a cart for review.
- Revalidate products, variants, options, quantities, prices, and current available stock. Show unavailable selections and price changes clearly before confirmation.
- A confirmed checkout creates a new order, new checkout identity, new reservation, and new payment attempt. Do not reuse the expired order's provider identity or assume that its former stock is still available.
- This is separate from cancellation followed by editing a still-current checkout. It does not make any prior uncertain payment safe to retry; that outcome must first be resolved.

### Owner payment review

- Cashiers can see the review state but cannot resolve it, manually declare a QRIS payment successful, or record a refund.
- The owner can choose fulfilment or record a manual refund.
- Fulfilment requires an authoritative payment record plus a current stock check and reservation. Honour the amount already paid; do not reprice a paid review order as a fresh sale.
- If required stock cannot be reserved, do not queue the order. Keep it in review for owner resolution.
- A refund is arranged outside this application. Record its reference or explanation, acting owner, and resolution time, then represent the payment as refunded and the order as resolved without kitchen admission.
- Recording a refund does not send money or prove that Midtrans's provider-side status changed. Preserve the provider transaction evidence separately from the local resolution.
- Do not offer automatic refunds in this release.

### Integration and persistence constraints

- Extend the existing checkout, order handling, payment attempts, stock reservation, kitchen transition, and staff authorization modules. Use a small Midtrans integration boundary rather than a generic payment gateway framework.
- Keep provider network requests outside database transactions. Use appropriate atomic transitions and locking when applying results to payments, orders, and stock.
- Existing storage already supports cash and QRIS methods, payment attempts, provider references, payment review, and stock reservations. Reuse these concepts.
- Durable provider-attempt identity, QR recovery information, authoritative payment deadlines, unresolved-outcome tracking, actor attribution, and owner resolution records must be representable. The exact schema changes and internal status representation have not been selected.
- Checking payment status and cancellation-in-progress are required visible behaviors, not approval for particular new database enum values.
- The integration must distinguish provider status from local order state and manual refund resolution. A cancelled provider transaction must not be mislabeled as a successful payment.
- Keep sandbox and production credentials and behavior separate. Secrets remain server-side and must not appear in frontend data, source control, logs, or this specification.
- No SDK installation, dependency change, endpoint contract, polling interval, queue infrastructure, or production hosting choice was approved during the interview. Prefer existing project capabilities and confirm version-specific APIs during implementation.
- Preserve existing guest cash behavior, including its current 30-minute unpaid window. The agreed 15-minute provider-backed lifecycle applies to cashier QRIS in this release.

### Rollout and production gate

- Implement and test against Midtrans sandbox first. Sandbox verification does not authorize live credentials, production activation, or real-money transactions.
- Production QRIS remains disabled until the following release requirements have evidence:
    1. Successful payment, cancellation, expiry, delayed confirmation, duplicate notifications, and timeout recovery have been verified.
    2. Stock reservation, release, paid retention, preparation deduction, and late-payment review work without overselling or double deduction.
    3. Provider notifications reach the deployed backend and are verified before mutating financial state.
    4. Scheduled reconciliation and expiry handling operate when no cashier screen is open.
    5. Production secrets are protected and sandbox settings cannot be mistaken for live configuration.
    6. The merchant account and QRIS payment capability are confirmed active for production.
    7. Existing project release requirements, including HTTPS and security review, are satisfied.
- Obtain explicit authorization before production activation and any real-money validation. Sandbox success alone is insufficient.

## Testing Decisions

The following is the proposed verification approach for implementing the agreed behavior. No tests were created or executed for this documentation task. Test boundaries and cases are implementation guidance; they do not claim that a detailed test plan was separately approved during the interview.

### Primary test boundary

Prefer existing application feature tests: exercise authenticated checkout and order actions, provider notification handling, scheduled reconciliation, and kitchen transitions; assert responses and persisted payment, order, reservation, and movement outcomes. Fake provider responses at the external HTTP boundary so these tests do not move money or depend on Midtrans availability.

Use the existing cash order flow and guest cash order suites as prior art for pricing, checkout idempotency, stock holds, confirmation, expiry, and one-time preparation deduction. Use the existing role access suite for authorization. Keep PostgreSQL-specific constraints and concurrency guarantees in relevant database verification; an SQLite-only run cannot prove them.

### Required behavioral coverage

1. QRIS checkout calculates the full total on the server, reserves the correct stock, remains unpaid, and is absent from the kitchen queue.
2. Invalid items, inactive or unavailable selections, changed prices, and insufficient ingredients fail without partial transactions or negative stock.
3. Repeated checkout submissions and timed-out provider creation recover the same attempt rather than creating another payable charge.
4. Order items and total cannot change while their QR is payable or its outcome remains unknown.
5. Closing, reopening, refreshing, and changing the active cashier preserve the pending attempt and its original deadline.
6. All cashiers can see and perform permitted actions on each other's orders, including cash orders. Preserve denial of owner-only resolutions and kitchen-only operations.
7. Verify actor attribution for creation, cancellation, payment-method changes, and owner resolutions.
8. Valid payment confirmation queues exactly once, clears the reservation expiry, and does not deduct physical stock.
9. Duplicate or reordered notifications, simultaneous manual checks, cancellation races, and repeated kitchen actions do not produce duplicate financial or inventory effects.
10. Forged notifications, mismatched amounts, currency or references, and browser-originated paid claims cannot produce paid kitchen orders.
11. The visible countdown reaching zero cannot release stock while provider status is pending or unknown. Test both scheduled handling and every availability calculation that could otherwise ignore an elapsed reservation timestamp.
12. Confirmed unpaid expiry or cancellation releases abandoned stock exactly once; the released amount becomes available to another checkout.
13. Unknown creation, status, cancellation, or expiry results keep the relevant reservation effective and block replacement QR or cash collection until reconciled.
14. Switching to cash succeeds only after the old QRIS attempt is confirmed no longer payable, and records one full payment with a consistent reservation.
15. Late payment after a released hold enters payment review and never automatically enters the kitchen.
16. Owner fulfilment rechecks and reserves stock at the already-paid amount; insufficient stock leaves the order in review.
17. Only the owner can record a completed manual refund with a reference or explanation. Repeating the resolution does not duplicate refunds or reservations, and recording it makes no provider refund request.
18. Copy to checkout preserves expired history, shows changed prices or unavailable items, and creates a new order and attempt only after cashier confirmation.
19. Queued-to-preparing consumes the reservation and deducts stock once. Later kitchen transitions do not deduct again, and paid holds do not expire while waiting for preparation.
20. Existing cash checkout, guest cash confirmation and 30-minute expiry, private guest order access, and role restrictions remain covered. Replace the obsolete expectation that cashiers see only their own orders with the approved shared-access expectation.
21. Production QRIS cannot be used while disabled, and environment separation prevents sandbox transactions or secrets from being treated as production success.

### UI and sandbox verification

- Verify QR readability on the actual cashier screen, order identity and amount, countdown continuity, locked editing, separate Close and Cancel actions, status feedback, and the paid-order indication while another order is open.
- Verify pending order reopening, cross-cashier handover, Copy to checkout, and owner review permissions through the UI.
- Exercise actual sandbox QR creation and payment outcomes, notifications, status lookup, cancellation, and expiry. Where sandbox cannot reproduce an outage or race, prove that behavior with controlled provider fakes and record the limitation honestly.
- During implementation, run focused regressions first, then relevant type, formatting, build, database, and release checks according to the project rules. Do not claim production readiness from a fake-provider test alone.

## Out of Scope

- Self-order or table-QR Midtrans payment integration in this first slice; existing guest cash checkout remains available.
- Customer accounts, a dedicated customer display, split payments, partial payments, and split bills.
- Automatic or integrated refunds, manual cashier overrides of QRIS success, and cashier resolution of financial exceptions.
- Editing an order while its QR remains payable, or reviving an expired order without fresh checkout validation.
- Post-preparation cancellation/refund features and automatic restoration of already-consumed ingredients.
- A generic multi-provider gateway platform, unrelated order or kitchen redesign, or speculative infrastructure.
- New tax, service-charge, or payment-fee rules. Existing server-side pricing remains the baseline.
- Dependency installation, live merchant setup, production activation, and real-money transactions as part of this documentation task.

## Further Notes

### Existing baseline and approved changes

Current cashier checkout creates a paid cash order with a nonexpiring stock reservation. Guest cash checkout already creates an awaiting-payment order with a 30-minute hold and queues it after cashier confirmation. Physical stock is deducted at the first kitchen preparation transition.

The existing generic unpaid-expiry path releases stock based on local reservation time; it cannot be applied unchanged to uncertain QRIS transactions. Current cashier order visibility also restricts cashier-created orders to their creator. Both behaviors need deliberate changes for the agreed QRIS lifecycle and shared order handling.

Earlier core documents describe the provider as undecided and contain historical statements that guest checkout is unavailable. This interview selected Midtrans and cashier-first delivery; repository code already implements guest cash checkout. This feature specification records those decisions without rewriting the protected core documents or claiming that the overall self-order QRIS milestone is complete.

### Provider references

- [Midtrans GoPay / QRIS POS integration](https://docs.midtrans.com/docs/gopay-qris-pos-integration): server-created QR display, backend notification and status verification, and expiry guidance. Midtrans advises against relying on automatic expiry below 15 minutes and notes that processing may be delayed even at 15 minutes. This supports checking the actual provider outcome before releasing stock.
- [Midtrans custom expiry object](https://docs.midtrans.com/reference/custom-expiry-object): provider expiry configuration. Align the displayed deadline with the provider attempt rather than restarting a browser timer on every open.
- [Midtrans QRIS reference](https://docs.midtrans.com/reference/qris): QRIS transaction creation reference for implementation research.

Recheck applicable provider behavior and installed package APIs during implementation. The exact API integration style, cancellation/status mappings, retry policy, data migrations, and notification transport remain technical design work within these agreed requirements. Merchant activation, credentials, deployment reachability, and provider-specific refund arrangements have not been verified.

### Authorization boundary

This document records agreed behavior and proposed verification. It does not authorize implementation, new dependencies, external issue publication, production configuration changes, or real-money operations. Implementation remains on hold until explicitly requested.
