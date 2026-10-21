# 02: Create and display a sandbox QRIS payment

**What to build:** Let a cashier select QRIS for the full order total and show the customer a sandbox payment QR, order identity, amount, and 15-minute countdown on the existing cashier screen. This slice creates a safe pending payment; confirmation is delivered by ticket 03.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** Awaiting Approval — implementation and automated verification complete; manual sandbox and screen verification remain pending.

- [x] Reuse existing item, variant, option, fulfilment, server-price, recipe-snapshot, and stock-availability validation; invalid checkout leaves no partial order or reservation.
- [x] Persist one pending order, attempt identity, and correct stock reservation consistently before depending on the Midtrans response. Network calls do not hold database transactions open.
- [ ] Display a readable QR, order identity, full amount, pending state, and countdown based on the original server/provider deadline, using the existing POS visual conventions.
- [x] Generating a QR locks items and total server-side. Unpaid orders cannot enter the kitchen or deduct physical stock.
- [x] Retried or concurrent submissions reuse the same logical checkout/attempt rather than creating another payable transaction; preserve current duplicate-request safeguards.
- [x] A creation timeout shows Checking payment status, retains the attempt identity and reservation, and blocks replacement QR or cash collection. It is not reported as a definite failure.
- [x] QRIS stock holds remain effective in all availability and adjustment checks even after the displayed deadline while the provider outcome is unresolved; generic local expiry cannot release them. Existing guest cash expiry is preserved.
- [x] Closing or leaving the display does not cancel the attempt, reset its deadline, or free stock. Full shared reopening is added in ticket 06.
- [x] Until later tickets exist, unavailable confirmation/cancellation actions are honestly represented rather than simulated. This slice remains sandbox-only and is not production-ready.
- [x] Secrets remain server-side; sandbox and production settings are separated and production QRIS stays disabled. No dependency change is made without explicit approval.
- [ ] Focused feature tests cover valid/invalid checkout, pricing, stock conflicts, duplicate creation, elapsed-but-unresolved holds, and creation timeouts; verify the QR display at cashier screen sizes.

## Implementation evidence — 2026-10-21

- RED: first checkout test failed with 422 because the cash amount was required for QRIS. GREEN: the shared checkout now reserves and persists a pending QRIS attempt before one fixed-endpoint sandbox creation call.
- RED: component runtime test showed closing/reopening reset 13:00 to 15:00. GREEN: countdown retains server time plus monotonic elapsed time and hides the QR at zero without releasing stock.
- Persisted creation claim prevents repeated or overlapping provider calls. Unknown results retain pending state and effective stock holds; changed requests cannot bypass the checkout fingerprint or switch to cash.
- Reuse Laravel HTTP client, existing cashier recovery, dialog/buttons, and existing nullable-expiry stock semantics. No dependencies installed and no later-ticket payment confirmation, cancellation, or scheduled reconciliation implemented.
- Focused QRIS feature tests: 22 passed / 222 assertions. Checkout/guest-cash regressions were also exercised.
- Full PostgreSQL suite: `php artisan test --compact` — 362 passed / 4183 assertions.
- Component runtime: `node --test tests/QrisPaymentDialogTest.mjs` — 1 passed, covering deadline continuity, QR hiding at zero, unavailable actions, and close/navigation events.
- Follow-up review: both named payment buttons must have `disabled === true` using DOM boolean-attribute semantics. The test name claims rendered states and emitted events, not the absence of network requests. Component test and targeted lint/format checks pass; production code is unchanged.
- Vue typechecking, PHPStan, targeted frontend formatting/lint, production build, Pint, whitespace checks, and the design detector passed. Independent Standards and Spec reviews found no defects.
- Additive migration applied only to confirmed local development database `cafe_pos`; test migrations ran against guarded `cafe_pos_test`.
- No browser/app surface is available for visual inspection. No real merchant sandbox credentials or transactions were used; provider tests use HTTP fakes. Screen-size readability/scanning and actual Midtrans sandbox creation remain manual checks, not claimed passes.
- Sandbox is disabled by default. Local testing requires setting `MIDTRANS_QRIS_ENABLED=true`, `MIDTRANS_ENVIRONMENT=sandbox`, and a valid `MIDTRANS_SANDBOX_SERVER_KEY` privately in the environment. Live credentials and production charging remain disabled.
- Ticket 02 awaits human review, including the documented verification limitations. Tickets 03–12 are not started; GitHub publication remains pending access.
