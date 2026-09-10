#!/usr/bin/env python3
"""Generate pre-oral SQL with dummy operational data for Doc review."""

from __future__ import annotations

import random
from datetime import datetime, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCHEMA = ROOT / "sql" / "schema.sql"
OUT = ROOT / "sql" / "kin_cafe_preoral_2026-09-10.sql"

random.seed(912)  # stable demo numbers for defense

MENU = [
    (5, "Caramel Latte", 119.0, "ITM0005"),
    (7, "Spanish Latte", 119.0, "ITM0007"),
    (14, "Latte", 119.0, "ITM0014"),
    (6, "Tapa Chaofan", 109.0, "ITM0006"),
    (12, "Sisig Chaofan", 159.0, "ITM0012"),
    (8, "Chicken Poppers Chaofan", 129.0, "ITM0008"),
    (30, "Pepperoni Pizza", 249.0, "ITM0030"),
    (24, "Creamy Carbonara", 149.0, "ITM0024"),
    (39, "French Fries", 69.0, "ITM0039"),
    (46, "Beef Burger", 109.0, "ITM0046"),
    (49, "Blueberry Soda", 49.0, "ITM0049"),
    (18, "Ham & Cheese Waffle", 49.0, "ITM0018"),
]

CUSTOMERS = [
    ("Maria Santos", "09171234501", 120),
    ("Juan Dela Cruz", "09181234502", 85),
    ("Ana Reyes", "09191234503", 210),
    ("Carlo Mendoza", "09201234504", 64),
    ("Bea Fernandez", "09211234505", 140),
    ("Miguel Torres", "09221234506", 55),
    ("Sofia Ramos", "09231234507", 98),
    ("Ethan Villanueva", "09241234508", 175),
    ("Walk-in Regular", "09251234509", 40),
    ("Team Alpha", "09261234510", 33),
]

INGREDIENTS = [
    # id, name, unit, stock, mfg, exp
    (1, "Espresso Beans", "grams", 120.00, "2026-07-01", "2027-01-15"),
    (2, "Fresh Milk", "liters", 4.00, "2026-09-01", "2026-09-18"),
    (3, "Jasmine Rice", "grams", 12000.00, "2026-06-01", "2027-06-01"),
    (4, "Chicken Fillet", "grams", 3500.00, "2026-08-20", "2026-09-25"),
    (5, "Cooking Oil", "liters", 40.00, "2026-05-01", "2027-05-01"),
    (6, "Sugar Syrup", "liters", 8.50, "2026-07-10", "2026-12-10"),
    (7, "Matcha Powder", "grams", 900.00, "2026-06-15", "2027-03-15"),
    (8, "Burger Bun", "pcs", 180.00, "2026-09-05", "2026-09-20"),
]


def sql_str(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def main() -> None:
    schema = SCHEMA.read_text(encoding="utf-8-sig")
    # Drop the package-reference line if present; we'll rewrite a full header.
    if schema.startswith("-- Database schema"):
        # keep body after first blank line following header comments
        pass

    lines: list[str] = []
    lines.append("-- Kin Cafe SQL package for pre-oral (prepared 2026-09-10)")
    lines.append("-- Includes schema + DUMMY operational data for Doc review.")
    lines.append("-- Default logins: admin/admin123 , cashier/cashier123")
    lines.append("-- Contents: users, menu, customers, ingredients, recipes,")
    lines.append("--           ~30 days completed orders, inventory logs, anomaly spike day")
    lines.append("-- Import tip: phpMyAdmin > Import this file (fresh DB).")
    lines.append("-- If kin_cafe already exists, this script drops and recreates it.")
    lines.append("")

    schema_body = schema
    # Replace soft create with clean recreate so dummy package is one-click importable.
    schema_body = schema_body.replace(
        "CREATE DATABASE IF NOT EXISTS kin_cafe;\nUSE kin_cafe;",
        "DROP DATABASE IF EXISTS kin_cafe;\nCREATE DATABASE kin_cafe;\nUSE kin_cafe;",
        1,
    )

    lines.append(schema_body.rstrip() + "\n")

    lines.append("\n-- ============================================================")
    lines.append("-- DUMMY DATA (pre-oral demo)")
    lines.append("-- ============================================================\n")

    lines.append("SET FOREIGN_KEY_CHECKS=0;\n")

    # Customers
    lines.append("-- Customers")
    for i, (name, phone, pts) in enumerate(CUSTOMERS, start=1):
        created = (datetime(2026, 5, 1) + timedelta(days=i * 4)).strftime("%Y-%m-%d %H:%M:%S")
        lines.append(
            "INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES "
            f"({i}, {sql_str(name)}, {sql_str(phone)}, {pts}, {sql_str(created)});"
        )

    # Ingredients
    lines.append("\n-- Ingredients (includes low-stock Fresh Milk / Espresso Beans)")
    for row in INGREDIENTS:
        iid, name, unit, stock, mfg, exp = row
        lines.append(
            "INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES "
            f"({iid}, {sql_str(name)}, {sql_str(unit)}, {stock:.2f}, {sql_str(mfg)}, {sql_str(exp)});"
        )

    # Recipes
    lines.append("\n-- Sample recipes")
    recipes = [
        (14, 1, 18, "grams"),      # Latte -> Espresso
        (14, 2, 0.18, "liters"),   # Latte -> Milk
        (5, 1, 18, "grams"),
        (5, 2, 0.18, "liters"),
        (6, 3, 220, "grams"),      # Tapa Chaofan
        (6, 5, 0.015, "liters"),
        (12, 3, 220, "grams"),
        (12, 4, 90, "grams"),
        (8, 3, 220, "grams"),
        (8, 4, 80, "grams"),
        (46, 8, 1, "pcs"),
        (46, 4, 100, "grams"),
    ]
    for menu_id, ing_id, qty, unit in recipes:
        lines.append(
            "INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES "
            f"({menu_id}, {ing_id}, {qty}, {sql_str(unit)});"
        )

    # Inventory logs (large removals for Stock Review anomalies)
    lines.append("\n-- Inventory anomaly-friendly logs")
    inv_rows = [
        (2, "remove", 6.00, "Spoilage / waste demo", "2026-09-03 10:15:00"),
        (1, "remove", 8.00, "Bulk kitchen prep", "2026-09-04 09:40:00"),
        (4, "remove", 7.50, "Expired trim waste", "2026-09-06 16:20:00"),
        (2, "add", 30.00, "Supplier delivery", "2026-09-07 08:00:00"),
        (3, "add", 40.00, "Rice restock", "2026-09-08 08:30:00"),
        (1, "remove", 5.50, "Demo consumption", "2026-09-09 14:00:00"),
    ]
    for ing_id, action, qty, reason, ts in inv_rows:
        lines.append(
            "INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES "
            f"({ing_id}, {sql_str(action)}, {qty:.2f}, {sql_str(reason)}, 1, {sql_str(ts)});"
        )

    # Daily consumption-ish small removes
    today = datetime(2026, 9, 10)
    for day in range(14):
        d = today - timedelta(days=day)
        for ing_id in (1, 2, 3, 4, 5):
            qty = round(random.uniform(0.5, 2.5), 2)
            ts = d.replace(hour=15, minute=0, second=0).strftime("%Y-%m-%d %H:%M:%S")
            lines.append(
                "INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES "
                f"({ing_id}, 'remove', {qty:.2f}, 'Daily usage seed', 1, {sql_str(ts)});"
            )

    # Orders across 30 days
    lines.append("\n-- Completed demo orders (~30 days) for analytics / anomaly / forecast")
    order_id = 1
    item_id = 1
    payment_id = 1
    pair_sets = [
        [MENU[2], MENU[3]],  # Latte + Tapa
        [MENU[2], MENU[4]],  # Latte + Sisig
        [MENU[0], MENU[5]],  # Caramel + Chicken Poppers Chaofan
        [MENU[3], MENU[4]],
        [MENU[6], MENU[8]],  # Pizza + Fries
        [MENU[9], MENU[10]], # Burger + Soda
        [MENU[7], MENU[11]], # Pasta + Waffle
    ]

    for day_offset in range(29, -1, -1):
        day = today - timedelta(days=day_offset)
        weekday = day.weekday()  # 0=Mon
        is_weekend = weekday >= 5
        # Create an intentional spike day for anomaly (~4k+ variance potential)
        if day_offset == 3:  # 2026-09-07
            orders_today = 28
            spike = True
        elif day_offset == 10:  # quieter drop day
            orders_today = 4
            spike = False
        else:
            base = 10 if not is_weekend else 14
            orders_today = base + random.randint(0, 4)
            spike = False

        for i in range(orders_today):
            hour = random.choice([11, 12, 13, 14, 17, 18, 19])
            minute = random.randint(0, 59)
            created = day.replace(hour=hour, minute=minute, second=0)
            created_s = created.strftime("%Y-%m-%d %H:%M:%S")
            cust_id = random.randint(1, len(CUSTOMERS)) if random.random() < 0.72 else "NULL"
            template = pair_sets[random.randint(0, len(pair_sets) - 1)]
            items = []
            for m in template:
                qty = 2 if spike and random.random() < 0.55 else random.randint(1, 2)
                items.append((m[0], m[1], m[2], m[3], qty))
            if random.random() < 0.25:
                extra = MENU[random.randint(0, len(MENU) - 1)]
                items.append((extra[0], extra[1], extra[2], extra[3], 1))

            total = round(sum(price * qty for _, _, price, _, qty in items), 2)
            paid = round(total + random.choice([0, 10, 20, 50]), 2)
            if paid < total:
                paid = total
            change = round(paid - total, 2)
            receipt = f"RCPT-DEMO-{day.strftime('%Y%m%d')}-{order_id:04d}"

            lines.append(
                "INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, "
                "paid_amount, change_amount, cash_received_amount, payment_method, payment_status, "
                "receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES "
                f"({order_id}, {total:.2f}, {total:.2f}, 0.00, 0.00, {paid:.2f}, {change:.2f}, {paid:.2f}, "
                f"'cash', 'completed', {sql_str(receipt)}, {cust_id}, 'completed', 0.00, 1, {sql_str(created_s)});"
            )

            for menu_item_id, name, price, code, qty in items:
                line_total = round(price * qty, 2)
                lines.append(
                    "INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, "
                    "item_name_snapshot, product_code_snapshot) VALUES "
                    f"({item_id}, {order_id}, {menu_item_id}, {qty}, {price:.2f}, {line_total:.2f}, "
                    f"{sql_str(name)}, {sql_str(code)});"
                )
                item_id += 1

            lines.append(
                "INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES "
                f"({payment_id}, {order_id}, 'cash', {paid:.2f}, {sql_str(created_s)});"
            )
            payment_id += 1
            order_id += 1

    # Supplier
    lines.append("\n-- Supplier sample")
    lines.append(
        "INSERT INTO suppliers (id, name, contact, address) VALUES "
        "(1, 'Metro Cafe Supply', '09170001111', 'Quezon City');"
    )

    lines.append("\nSET FOREIGN_KEY_CHECKS=1;")
    lines.append("\n-- End of pre-oral dummy package")

    OUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)")
    print(f"Orders inserted: {order_id - 1}")
    print(f"Order items: {item_id - 1}")
    print(f"Customers: {len(CUSTOMERS)}")
    print(f"Ingredients: {len(INGREDIENTS)}")


if __name__ == "__main__":
    main()
