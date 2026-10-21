# 04: Process verified Midtrans notifications

**What to build:** Update an order from Midtrans's verified server notification even when its QR display or browser is closed, using the same payment reconciliation rules as a manual check.

**Blocked by:** 03 — Check and recover payment status.

**Status:** ready-for-agent

- [ ] Verify notification authenticity and expected transaction identity, order, amount, and currency before changing financial or kitchen state.
- [ ] Route verified outcomes through the existing reconciliation behavior; a valid paid order with intact stock queues once without a cashier action.
- [ ] Paid reservations lose their expiry without physical stock deduction; released-stock or financial exceptions enter payment review.
- [ ] Replayed, duplicated, reordered, or concurrently checked notifications cannot create duplicate paid payments, kitchen admissions, reservations, or stock movements, or revert paid state.
- [ ] Invalid notifications and customer/browser claims do not mark orders paid. Expected retry handling does not discard valid provider evidence.
- [ ] Reopening the existing order UI shows the persisted outcome even if no browser was active during confirmation; live cashier notifications are added in ticket 06.
- [ ] Feature tests cover genuine and forged payloads, mismatches, unknown attempts, duplicate delivery, ordering, and concurrency where database guarantees matter.
