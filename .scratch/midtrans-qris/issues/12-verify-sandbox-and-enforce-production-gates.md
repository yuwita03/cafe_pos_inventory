# 12: Verify sandbox flows and enforce production readiness

**What to build:** Demonstrate the assembled cashier QRIS flow in Midtrans sandbox and record release evidence while keeping real payments disabled until every production requirement and explicit activation authorization is satisfied.

**Blocked by:** 01–11 — all preceding Midtrans QRIS tickets. Each delivers behavior required by the complete release, even when another ticket also blocks it transitively.

**Status:** ready-for-agent

- [ ] Verify actual sandbox QR creation, payment, notification delivery, authenticated status lookup, cancellation, and expiry through the cashier experience; report missing sandbox capabilities honestly.
- [ ] Exercise closing/reopening, shared cashier handover, background success indication, expired-order copying, owner fulfilment, and manual refund recording using existing POS styling.
- [ ] Record controlled-provider test evidence for creation/cancellation/status timeouts, duplicate/reordered notifications, late payment, stock conflicts, and payment/cancellation races that cannot reliably be reproduced in sandbox.
- [ ] Verify scheduled reconciliation works without an open cashier browser and no availability path frees unresolved QRIS holds merely because a timestamp passed.
- [ ] Verify paid reservations persist, preparation deducts once, expired unpaid reservations release once, and owner review prevents automatic fulfilment after stock release.
- [ ] Run relevant regressions, type/style/build checks, and PostgreSQL-specific constraint/concurrency verification. Cover existing cash and guest cash behavior as well as QRIS.
- [ ] Keep production QRIS disabled by default and verify server-side environment separation, protected credentials, authentic notifications, and absence of secrets in client data or logs.
- [ ] Record production prerequisites and their actual verified/unverified status: reachable HTTPS notifications, scheduled processing, active live merchant/QRIS capability, protected live credentials, and existing project security/release requirements.
- [ ] An unmet production prerequisite leaves activation blocked. Sandbox success does not authorize a live payment, live credential use, dependency installation, deployment, or activation; obtain explicit authorization before those actions.
- [ ] Summarize real checks, limitations, and remaining release blockers. Keep the overall self-order QRIS milestone incomplete because self-order integration is outside this cashier slice.
