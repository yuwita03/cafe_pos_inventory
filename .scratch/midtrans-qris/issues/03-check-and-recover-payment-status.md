# 03: Check and recover payment status

**What to build:** Give the cashier a Check payment status action that looks up the original Midtrans attempt, recovers its QR when pending, and processes an authoritative paid outcome without another cashier approval.

**Blocked by:** 02 — Create and display a sandbox QRIS payment.

**Status:** ready-for-agent

- [ ] Check the original persisted attempt through an authenticated server-side provider lookup; a pending attempt recovers its existing QR and deadline without a new charge.
- [ ] Validate attempt/order association, amount, currency, and the provider outcome before accepting payment. Screenshots and browser-originated paid claims cannot mark an order paid.
- [ ] A valid full payment with an intact reservation records one paid result, queues once, clears reservation expiry, and leaves physical stock unchanged until preparation.
- [ ] Preserve one-time stock deduction through queued-to-preparing and ensure later kitchen transitions do not deduct again.
- [ ] Unknown or temporarily unavailable status retains the hold and blocks replacement payment. Enable safe continuation only when the old attempt is authoritatively resolved.
- [ ] A verified payment after stock release or an unexpected financial result enters visible payment review and stays out of the kitchen; review resolution is delivered later.
- [ ] Repeated or concurrent checks are idempotent and cannot downgrade paid state or duplicate financial, kitchen, or stock effects. Preserve unexpected provider evidence for owner review.
- [ ] The cashier sees pending, checking, paid, terminal unpaid, and review outcomes clearly using existing UI patterns; QRIS has no manual paid override.
- [ ] Feature tests fake only the external provider boundary and assert persisted outcomes for recovery, success, timeouts, mismatches, late payment, and repeated checks.
