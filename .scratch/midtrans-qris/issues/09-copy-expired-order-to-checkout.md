# 09: Copy an expired order into checkout

**What to build:** Give the cashier a Copy to checkout action on an expired order so its selections can be reviewed and purchased again without reviving the old transaction.

**Blocked by:** 05 — Reconcile abandoned payments and expire safely.

**Status:** ready-for-agent

- [ ] Show Copy to checkout for an eligible expired order after the original provider outcome is resolved; an unknown attempt is not eligible for a replacement sale.
- [ ] Copy item, variant, option, and quantity selections into a reviewable cashier cart without requiring a customer account.
- [ ] Preserve the expired order and its payment history unchanged; copying alone creates no order, charge, or reservation.
- [ ] Recheck current product/variant/option validity, prices, and available stock. Clearly show unavailable selections and price changes before cashier confirmation.
- [ ] Final confirmation creates a new order, checkout identity, reservation, and provider attempt using normal checkout safeguards; do not reuse the old payment identity.
- [ ] Repeated confirmation cannot create duplicate sales, and a newly discovered exception on the old attempt remains visible for owner review.
- [ ] Use existing cart and order-detail design conventions rather than introducing a separate customer flow.
- [ ] Feature and UI checks cover unchanged history, missing/inactive selections, price changes, stock sold after expiry, explicit confirmation, and duplicate submission.
