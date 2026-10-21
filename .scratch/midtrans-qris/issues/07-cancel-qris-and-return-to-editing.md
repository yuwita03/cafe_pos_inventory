# 07: Cancel QRIS and return to editing

**What to build:** Let the cashier cancel a pending QRIS attempt and return to order editing only when Midtrans confirms that the original payment can no longer be made.

**Blocked by:** 03 — Check and recover payment status.

**Status:** ready-for-agent

- [ ] Show a distinct Cancel payment action using existing confirmation and form patterns; closing the QR does not trigger it.
- [ ] Record the requesting actor and durable cancellation outcome while retaining the original creator and payment history.
- [ ] A request in progress, timeout, or unknown result keeps items/total locked, stock reserved, and replacement payment blocked. A later check can reconcile it.
- [ ] Only authoritative unpaid termination permits returning to editing. Maintain or revalidate the continuing checkout's stock reservation consistently; release stock if the order is abandoned.
- [ ] Confirmed cancellation allows current item/price/availability validation before issuing a new attempt and QR. Never reuse a still-payable provider identity or lose attempt history.
- [ ] If payment wins a cancellation race, process verified success or owner review according to stock state and keep the paid order out of editing.
- [ ] Repeated actions and concurrent cancellation/check results cannot duplicate attempts, release holds twice, or revert paid state.
- [ ] Server authorization and feature tests cover permitted actors, locked editing, success, timeout recovery, changed prices/stock, and payment races; verify the cashier-facing feedback.
