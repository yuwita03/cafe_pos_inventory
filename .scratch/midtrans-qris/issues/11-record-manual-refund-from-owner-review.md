# 11: Record a manual refund from owner review

**What to build:** Let the owner record that a reviewed payment has been refunded outside the application, with a reference or explanation, without initiating a provider refund.

**Blocked by:** 05 — Reconcile abandoned payments and expire safely.

**Status:** ready-for-agent

- [ ] Give the owner a clearly labeled Record manual refund action for an eligible review order; state that recording does not send money.
- [ ] Require a nonblank refund reference or explanation and record the acting owner and resolution time.
- [ ] Represent the locally resolved/refunded outcome without admitting the order to the kitchen or reinstating its released stock reservation.
- [ ] Preserve original payment evidence and distinguish local refund recording from the provider's transaction status.
- [ ] The action makes no provider refund request and cannot be used by cashiers, kitchen staff, or guests, including direct server requests.
- [ ] Duplicate submissions, concurrent owner actions, or later provider notifications do not duplicate the resolution, overwrite completed fulfilment, or reopen a refunded order automatically.
- [ ] Reuse existing management form and confirmation patterns, with clear success and validation feedback.
- [ ] Feature tests cover required evidence, permissions, local/provider status separation, no refund HTTP call, no stock/kitchen effects, and repeated/conflicting resolutions.
