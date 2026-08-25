# QA & Verification Index — Kin Café Management System

Central index for test scripts, verification reports, and defense-day checklists.

---

## Quick Commands

```bat
cd c:\xampp\htdocs\Kin_Cafe

REM Full system snapshot
c:\xampp\php\php.exe tools\verify_system.php

REM Seed demo history for ARIMA/SARIMA + profiles (first time or reset)
c:\xampp\php\php.exe tools\seed_demo_data.php
c:\xampp\php\php.exe tools\seed_demo_data.php --force

REM Start forecast service (required for ARIMA/SARIMA labels)
start_forecast_service.bat
```

---

## Documentation

| Document | Purpose |
|----------|---------|
| [iso25010-matrix.md](./iso25010-matrix.md) | ISO/IEC 25010 quality characteristic mapping |
| [verification-report.md](./verification-report.md) | Post-remediation audit results |
| [performance-benchmark.md](./performance-benchmark.md) | Analytics query timing evidence |
| [demo-checklist.md](./demo-checklist.md) | Defense-day browser walkthrough |
| [../payment-policy.md](../payment-policy.md) | Cash-only POS policy |

---

## PHP Test Scripts (`includes/`)

| Script | Focus | Command |
|--------|-------|---------|
| `test_orders_history.php` | Order details API | `php includes/test_orders_history.php` |
| `test_menu_workflow.php` | Categories, menu items, availability | `php includes/test_menu_workflow.php` |
| `test_inventory.php` | Ingredient CRUD dates | `php includes/test_inventory.php` |
| `test_inventory_delete.php` | Ingredient deletion/archive | `php includes/test_inventory_delete.php` |
| `test_account_delete.php` | User deletion cleanup | `php includes/test_account_delete.php` |
| `test_refcounter.php` | Resource reference counter | `php includes/test_refcounter.php` |
| `test_advanced_analytics.php` | Advanced analytics API smoke | `php includes/test_advanced_analytics.php` |
| `benchmark_analytics.php` | Query performance timing | `php includes/benchmark_analytics.php` |

---

## Integration & Tools

| Script | Focus | Command |
|--------|-------|---------|
| `tools/seed_demo_data.php` | 90-day sales, customers, recipes | `php tools/seed_demo_data.php` |
| `python_forecast_service/test_api.py` | ARIMA/SARIMA API unit tests | `venv\Scripts\python.exe test_api.py` |
| `python_forecast_service/test_forecast_integration.php` | PHP ↔ Python bridge | `php python_forecast_service/test_forecast_integration.php` |

---

## ISO 25010 Mapping Summary

| Characteristic | Primary Evidence |
|----------------|------------------|
| Functional suitability | Module pages + `verify_system.php` |
| Performance efficiency | `benchmark_analytics.php` |
| Compatibility | Forecast integration tests |
| Usability | Manual demo checklist |
| Reliability | Forecast fallback + regression tests |
| Security | Login CSRF, audit logs, permission gates |
| Maintainability | Split AI services, method labels |
| Portability | XAMPP + README install steps |

---

## When to Re-run

- After any change to `includes/ai_services.php`, `includes/functions.php`, or forecast service
- Before capstone defense/demo day
- After seeding demo order history (≥14 days for ARIMA labels)
