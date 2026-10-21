# Midtrans QRIS ticket publication drafts

Source: the agreed Midtrans QRIS cashier specification, dated 2026-10-21.

These are individual publication drafts for GitHub Issues in
`yuwita03/cafe_pos_inventory`. GitHub remains the configured issue tracker.
No remote issues, labels, or blocking relationships have been created.
Publication is pending an authenticated GitHub CLI or another authorized
GitHub connection. The current environment has neither a GitHub connector
nor an available browser session.

Ticket numbers below are local planning identifiers, not GitHub issue numbers.
Replace dependency references with actual GitHub issue identifiers at publication
and prefer native blocking relationships. Apply `ready-for-agent` to each issue;
the label does not override unresolved blockers or authorize implementation.

All UI slices must inspect and reuse the existing POS components, typography,
colours, spacing, buttons, and dialog patterns. This adds payment flows within
the established design; it does not authorize a redesign. Each implementation
ticket owns its relevant tests and UI verification. Ticket 12 verifies the
assembled system rather than deferring all testing until the end.

| Ticket                                                            | Deliverable                                           | Blocked by |
| ----------------------------------------------------------------- | ----------------------------------------------------- | ---------- |
| [01](issues/01-share-orders-across-cashiers.md)                   | Share orders across cashiers                          | None       |
| [02](issues/02-create-and-display-sandbox-qris.md)                | Create and display a sandbox QRIS payment             | None       |
| [03](issues/03-check-and-recover-payment-status.md)               | Check and recover payment status                      | 02         |
| [04](issues/04-process-verified-midtrans-notifications.md)        | Process verified Midtrans notifications               | 03         |
| [05](issues/05-reconcile-and-expire-payments-safely.md)           | Reconcile abandoned payments and expire safely        | 03         |
| [06](issues/06-resume-pending-orders-and-show-payment-updates.md) | Resume pending orders and show payment updates        | 01, 04     |
| [07](issues/07-cancel-qris-and-return-to-editing.md)              | Cancel QRIS and return to editing                     | 03         |
| [08](issues/08-switch-resolved-qris-checkout-to-cash.md)          | Switch a resolved QRIS checkout to cash               | 07         |
| [09](issues/09-copy-expired-order-to-checkout.md)                 | Copy an expired order into checkout                   | 05         |
| [10](issues/10-fulfil-order-from-owner-payment-review.md)         | Fulfil an order from owner payment review             | 05         |
| [11](issues/11-record-manual-refund-from-owner-review.md)         | Record a manual refund from owner review              | 05         |
| [12](issues/12-verify-sandbox-and-enforce-production-gates.md)    | Verify sandbox flows and enforce production readiness | 01–11      |

Ticket 01 is Done, explicitly approved by the user on 2026-10-21. Ticket 02 was
subsequently authorized and is implemented with passing automated checks, awaiting
approval and manual sandbox/screen verification. Tickets 03–12 remain on hold
until explicitly requested. Only tickets 01 and 02 have implementation authorization.
Create issues in numeric order, check for existing matching issues first, and
record actual publication results here to avoid duplicating issues on retry.
No parent issue was supplied; do not invent, modify, or close a parent issue.
