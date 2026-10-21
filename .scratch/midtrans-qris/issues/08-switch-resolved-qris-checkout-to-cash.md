# 08: Switch a resolved QRIS checkout to cash

**What to build:** Let a cashier complete the same checkout with cash after the previous QRIS attempt is confirmed cancelled, expired, or terminally failed, while preserving its payment history.

**Blocked by:** 07 — Cancel QRIS and return to editing.

**Status:** ready-for-agent

- [ ] Make switching to cash available only when the old QRIS attempt is authoritatively unpaid and no longer payable; hiding the QR or requesting cancellation is insufficient.
- [ ] Pending, unknown, paid, or owner-review attempts cannot be bypassed through a cash request, including direct server requests.
- [ ] Reuse full-total cash validation, received amount and change calculation, and current checkout validation; do not introduce partial or split payment.
- [ ] Retain the QRIS history and original order creator, attribute the method change and cash collection, and record one successful payment.
- [ ] Keep or revalidate stock consistently, retain a nonexpiring reservation on paid/queued, and leave deduction to kitchen preparation.
- [ ] Repeated or concurrent cash submissions cannot duplicate payments or kitchen admission; a discovered QRIS payment blocks cash collection and follows reconciliation rules.
- [ ] Reuse existing cash UI patterns and make the prerequisite cancellation/status state clear before staff collect cash.
- [ ] Feature tests cover confirmed switching, insufficient cash, unresolved/paid denial, actor attribution, duplicate requests, and payment races.
