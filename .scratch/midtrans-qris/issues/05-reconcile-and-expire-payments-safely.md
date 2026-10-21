# 05: Reconcile abandoned payments and expire safely

**What to build:** Resolve pending, uncertain, and abandoned QRIS attempts in scheduled processing without an open cashier screen, release stock only after authoritative unpaid termination, and surface late-paid exceptions for owner review.

**Blocked by:** 03 — Check and recover payment status.

**Status:** ready-for-agent

- [ ] Scheduled reconciliation finds unresolved creation/payment attempts and reuses the same provider identity and reconciliation rules as manual status checks.
- [ ] The 15-minute customer deadline triggers authoritative checking and provider expiry/cancellation as appropriate; elapsed browser or database time alone does not prove an attempt is unpayable.
- [ ] If the outcome remains unknown, retain an effective hold across checkout, catalog availability, and inventory adjustment checks and keep replacement QR/cash blocked.
- [ ] Confirmed unpaid terminal outcomes close abandoned orders and release stock once, making it available to subsequent sales without changing physical stock.
- [ ] Paid orders keep nonexpiring reservations; scheduler runs cannot release them because the kitchen is slow.
- [ ] Late payment after release enters visible payment review without automatic queueing, stock reinstatement, or deduction. Persist sufficient evidence for owner resolution.
- [ ] Failures are retryable without duplicate provider attempts or duplicate local effects; provider calls remain outside database transactions.
- [ ] Existing guest cash's 30-minute expiry and cash order behavior remain intact.
- [ ] Order displays reflect pending/checking/expired/review outcomes honestly and consistently with current UI styling.
- [ ] Time-controlled feature tests cover unattended reconciliation, unknown outcomes past the deadline, confirmed release, repeat scheduler runs, late payment, paid holds, and concurrent stock use.
