# 06: Resume pending orders and show payment updates

**What to build:** Let any cashier close a QR, serve another customer, and reopen the same pending payment, while clearly identifying orders that become paid in the meantime.

**Blocked by:** 01 — Share orders across cashiers; 04 — Process verified Midtrans notifications.

**Status:** ready-for-agent

- [ ] Cashiers can locate shared pending orders and reopen their QR, amount, payment state, and remaining countdown using persisted attempt data.
- [ ] Closing is distinct from cancelling: navigation does not create a new QR, release stock, restart the deadline, or unlock an order.
- [ ] Another cashier can resume the order without changing its creator or bypassing role permissions.
- [ ] Confirmed payment updates the order without a second approval and produces a clear indication identifying the paid order while the cashier is handling another one.
- [ ] Refreshing/reopening after an unattended confirmation shows the paid state rather than a stale payable QR. Repeated updates do not create repeat order side effects.
- [ ] Preserve any cart currently being prepared for another customer when processing a background payment update.
- [ ] Reuse current POS/orders typography, colours, controls, spacing, and dialog conventions; verify readability, loading/error feedback, and keyboard access.
- [ ] Feature and relevant UI checks cover closing/reopening, cross-cashier handover, deadline continuity, background success, and state restoration.
