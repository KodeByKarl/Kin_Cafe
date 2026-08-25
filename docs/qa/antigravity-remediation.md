# AntiGravity QA Findings — Remediation Log

**Source report:** AntiGravity bug hunt (August 24, 2026)  
**Status:** Addressed in codebase

---

## BUG-001 — Cashier RBAC too permissive (High) — FIXED

**Issue:** Cashier role included `menu.manage` and `inventory.manage`.  
**Fix:** Removed those permissions in `includes/functions.php`. Cashiers now have: `dashboard.view`, `pos.access`, `pos.checkout`, `orders.view` only.

**Verify:** Log in as cashier → `inventory.php` and `menu_management.php` should show access denied.

---

## BUG-002 — Virtual Assistant CSRF (Medium) — FIXED

**Issue:** Slide-out POST could fail CSRF validation when token not passed consistently.  
**Fix:**
- Hidden `csrf_token` field added to POS assistant form (`pos.php`)
- Fetch sends token in JSON body and `X-CSRF-Token` header
- `pos_assistant.php` accepts header fallback and wraps handler in try/catch

**Verify:** Open POS → Virtual Assistant → submit question → receive answer (no 403).

---

## BUG-003 — Oversized assistant prompts (Medium/Low) — FIXED

**Issue:** 10,000+ character prompts could cause unhandled errors.  
**Fix:** `askAiVirtualAssistant()` truncates input to 500 characters before processing; `pos_assistant.php` returns JSON error on unexpected exceptions.

---

## BUG-004 — Active Users trend badge (Low) — FIXED

**Issue:** Trend showed static `0%` when session history was sparse.  
**Fix:** Added `getDashboardActiveUserTrend()` in `includes/functions.php`:
- Live count from `auth_tab_sessions`
- Week-over-week trend from session history
- Fallback to distinct `orders.created_by` when session history is empty

**Note:** Re-run `tools/seed_demo_data.php --force` to backfill `created_by` on demo orders for richer trend data.

---

## BUG-005 — Zero-quantity inventory noise (Low) — FIXED

**Issue:** Adding/editing with no effective change created pointless audit noise.  
**Fix:**
- **Add ingredient:** rejects `stock_quantity <= 0` with validation message
- **Edit ingredient:** rejects no-op saves with `"No stock or ingredient changes were made."`

---

## Re-test commands

```bat
cd c:\xampp\htdocs\Kin_Cafe
c:\xampp\php\php.exe tools\verify_system.php
tools\run_all_tests.bat
c:\xampp\php\php.exe tools\seed_demo_data.php --force
```

## Updated demo readiness

**Verdict:** Ready for defense (pending quick re-test of cashier login + POS assistant).
