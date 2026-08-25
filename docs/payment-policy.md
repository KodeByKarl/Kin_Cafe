# Payment Policy — Kin Café Management System

**Effective scope:** Point of Sale (POS) and order processing  
**Last updated:** August 23, 2026

---

## Cash-Only POS Design

The Kin Café Management System is configured for **cash-only checkout** at the POS by design. This matches the current café operating workflow and capstone implementation scope.

### What the system supports today

| Capability | Status |
|------------|--------|
| Cash payment capture | **Active** — amount paid, change calculation |
| Card payment at POS | **Not enabled** — UI shows cash pill only |
| Digital wallet payment at POS | **Not enabled** |
| Payment method stored on order | **Yes** — `payment_method = 'cash'` |
| Refunds / transaction status | **Supported** in orders history |

### Code references

- `pos.php` — payment method hidden input set to `cash`; cash pill only in UI
- `process_order.php` — accepts and stores cash payments for completed/pending orders

### Why cash-only

1. Matches the physical café counter workflow during capstone deployment.
2. Reduces PCI/compliance scope for the academic prototype.
3. Keeps checkout fast for staff during peak service windows.

### Future extension (not in current scope)

The database schema supports multiple payment methods via `order_payments`. To enable card/digital methods later:

1. Re-enable additional payment pills in `pos.php`.
2. Update `process_order.php` validation to accept non-cash methods.
3. Update receipt/report labels and analytics payment-mix views.

### Paper / defense wording

> "The POS module currently implements a cash-only payment flow aligned with the café's counter-service operation. The order and payment schema supports future multi-method expansion, but card and digital channels were intentionally excluded from the prototype scope."

---

## Related Documentation

- ISO 25010 matrix: `docs/qa/iso25010-matrix.md`
- Verification report: `docs/qa/verification-report.md`
