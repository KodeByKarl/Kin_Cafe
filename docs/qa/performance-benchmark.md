# Performance Benchmark Summary — Kin Café Management System

**Date:** August 23, 2026  
**Environment:** Local XAMPP / MySQL  
**Script:** `includes/benchmark_analytics.php`  
**Range tested:** Last 30 days of completed orders

---

## Results

| Query / Function | Time (ms) | ISO 25010 Evidence |
|------------------|-----------|----------------------|
| `analyticsKpis()` | 0.29 | Time behaviour — KPI aggregation |
| `analyticsSalesSeries()` | 0.20 | Time behaviour — chart data |
| `analyticsPaymentMix()` | 0.23 | Time behaviour — payment breakdown |
| `analyticsTopItems()` | 0.19 | Time behaviour — top sellers |
| `analyticsOrders()` (50 rows) | 0.28 | Time behaviour — drill-down |
| `analyticsHeatmap()` | 0.17 | Time behaviour — heatmap grid |

**All queries completed in under 1 ms** on the test database.

---

## Interpretation

These timings support the **Performance Efficiency → Time Behaviour** claim for the analytics module on local deployment. The dataset was small at test time (minimal order history); re-run after seeding production-like volume for defense annex evidence.

---

## Re-run Command

```bat
c:\xampp\php\php.exe c:\xampp\htdocs\Kin_Cafe\includes\benchmark_analytics.php
```

Save terminal output and attach to capstone ISO evidence folder.

---

## Related

- [test-index.md](./test-index.md)
- [iso25010-matrix.md](./iso25010-matrix.md)
