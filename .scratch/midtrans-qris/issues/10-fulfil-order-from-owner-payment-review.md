# 10: Fulfil an order from owner payment review

**What to build:** Let the owner fulfil a reviewed paid order only after required stock can be reserved again, honouring the amount already paid and keeping the resolution traceable.

**Blocked by:** 05 — Reconcile abandoned payments and expire safely.

**Status:** ready-for-agent

- [ ] Present reviewed orders and their verified provider payment evidence in an owner-accessible resolution flow using existing management design patterns.
- [ ] Require an authoritative payment record and a current ingredient/finished-item availability check before reserving stock and queueing.
- [ ] Honour the paid order amount and its recorded item/recipe commitments; do not silently reprice it or replace its items with current alternatives.
- [ ] If required stock cannot be reserved, leave the order in review with an actionable explanation and no partial reservation or kitchen admission.
- [ ] Successful resolution queues once, keeps the new reservation without expiry, records the acting owner and resolution time, and deducts nothing until preparation.
- [ ] Cashiers can see the review state but cannot invoke resolution; guests and kitchen staff cannot resolve it either.
- [ ] Concurrent or repeated resolution cannot reserve stock or queue twice, overwrite a completed refund resolution, or lose provider evidence.
- [ ] Feature tests cover owner-only access, available/insufficient stock, price changes after payment, repeat/concurrent resolution, and one-time preparation deduction.
