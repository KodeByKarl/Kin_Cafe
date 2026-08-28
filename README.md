# Kin Cafe Cashier Management System

PHP-based cashier and back-office system for Kin Cafe with POS checkout, inventory tracking, live reporting, promotions, loyalty tracking, and role-based staff access.

## Core Features

- Secure login with hashed passwords and active-account checks.
- Role-based permissions for `supervisor` and `cashier` users.
- POS checkout with:
	- promo-code discount application
	- cash-only payments with automatic change due calculation
	- receipt generation and print view
	- customer capture and loyalty point accrual
	- product-code lookup and invalid-code handling
- Transaction logging and audit trail for checkout, inventory changes, backups, and user management.
- Inventory management for both menu items and ingredients with low-stock visibility and activity logs.
- Live analytics with auto-refreshing charts, daily reconciliation, top-selling items, and loyalty leaderboards.
- Promotion management with fixed-value and percentage discounts.
- Automatic application-level JSON backups stored under `backups/`.

## Installation & Running

1. Ensure XAMPP is installed and Apache/MySQL are running.
2. Place the project in `htdocs/Kin_Cafe/`.
3. Double-click **`run.bat`**.
   - In **1 single click**, it will:
     - Check & auto-install Python virtual environment and required libraries (`flask`, `pandas`, `numpy`, `statsmodels`).
     - Create required folders (`backups/`).
     - Check & auto-setup MySQL database connection and schema.
     - Launch the AI Forecast Microservice on `http://127.0.0.1:5000`.
     - Automatically open `http://localhost/Kin_Cafe/` in your web browser.




The app also performs idempotent schema upgrades automatically on startup through the shared PHP bootstrap, so older databases are migrated forward when possible.

## Shared Database Setup

If multiple devices should see the same order history, analytics, inventory, and transaction data, they must all point to the same MySQL database.

- Default local config lives in `includes/app_config.php` under the `database` section.
- Environment variables override that config when present: `KIN_CAFE_DB_HOST`, `KIN_CAFE_DB_PORT`, `KIN_CAFE_DB_NAME`, `KIN_CAFE_DB_USER`, `KIN_CAFE_DB_PASSWORD`.
- For multi-device use, set `KIN_CAFE_DB_HOST` or the `database.host` value to the IP or hostname of the machine running the shared MySQL server instead of `127.0.0.1`.
- Import `sql/schema.sql` into that shared database once, then point every device or deployment to the same database credentials.

## Default Login

- Username: `admin`
- Password: `admin123`

## Main Pages

- [dashboard.php](dashboard.php): summary metrics, recent transactions, refunds, discounts, and weekly sales.
- [pos.php](pos.php): customer checkout, cash-only payments, receipts, promo codes, loyalty capture, product-code lookup, recommendations, and embedded virtual assistant.
- [menu_management.php](menu_management.php): menu and category administration.
- [inventory.php](inventory.php): ingredient and menu stock adjustments with logs.
- [analytics.php](analytics.php): live reporting, promotion management, reconciliation, and backup actions.
- [user_settings.php](user_settings.php): profile/password updates and admin user-role management.

## Validation and Error Handling

- Checkout rejects empty carts, invalid product options, unsupported payment methods, insufficient payment, and unavailable items.
- Network failures during checkout surface a user-facing POS error instead of failing silently.
- Product-code lookup shows an explicit invalid-code message.
- Inventory adjustments reject zero-quantity changes and negative resulting stock.
- User management prevents deactivating the currently logged-in account.

## Backup Behavior

- Automatic backups are triggered by successful operational events such as completed sales.
- Manual backups can be run from [analytics.php](analytics.php).
- Backup files are written as JSON snapshots into the `backups/` folder.

## Testing Scenarios

### Checkout and Payments

See [docs/payment-policy.md](docs/payment-policy.md) for the current cash-only POS policy.

1. Add multiple items to cart, enter one cash payment greater than total, and verify change due is shown and stored.
2. Enter a total payment below order total and verify checkout is rejected with an insufficient payment error.
3. Disconnect the network or stop Apache during checkout and verify the POS shows a network failure message.

### Product Code and Validation

1. Enter a valid `ITM000x` product code in POS and verify the matching item is added.
2. Enter an invalid product code and verify the POS shows an invalid-code error.
3. Add an unavailable or deleted item in another session, then attempt checkout and verify the server rejects it.

### Promotions and Loyalty

1. Create a percentage promo in [analytics.php](analytics.php), apply it in POS, and verify discount and tax totals update correctly.
2. Create a fixed promo with a minimum order, test below-threshold and above-threshold carts, and verify only valid carts receive the discount.
3. Complete a sale with customer phone details and verify loyalty points are added.

### Inventory

1. Complete a sale and verify menu stock decreases with an inventory log entry.

### Permissions and Audit Trail

1. Create cashier and supervisor users in [user_settings.php](user_settings.php).
2. Verify a cashier can access POS but not inventory or analytics.
3. Verify a supervisor can access inventory, menu, analytics, and user management.

## Technologies

- PHP
- MySQL
- Bootstrap 4
- Chart.js
- PDO

## Notes

- This is still a local-business demo system and not a PCI-compliant production payment stack.
- Automatic backups are application-level snapshots, not a replacement for full database backup tooling.

## AI Business Suite

The app now exposes a 9-feature AI suite through the dedicated hub page and linked core modules.

### Feature Map

1. Sales Forecasting Feature
Location: `ai_sales_forecasting.php` and shortcut from `analytics.php`
Purpose: Forecast future sales trends from historical completed orders for inventory and staffing decisions.

2. Inventory Optimization Feature
Location: `ai_inventory_optimization.php` and shortcut from `inventory.php`
Purpose: Recommend stock targets from recent ingredient usage patterns to reduce overstocking and shortages.

3. Demand Prediction Feature
Location: `ai_demand_prediction.php` and shortcut from `analytics.php`
Purpose: Predict demand for specific menu items using item sales history and peak service windows.

4. Smart Reordering Feature
Location: `ai_smart_reordering.php` and shortcut from `inventory.php`
Purpose: Generate reorder guidance when ingredient stock drops near the recommended threshold.

5. Customer Preference Analysis Feature
Location: `ai_customer_preferences.php` and shortcut from `analytics.php`
Purpose: Analyze customer buying patterns, favorite items, and preferred categories.

6. Recommendation System Feature
Location: `ai_recommendation_system.php` and shortcut from `pos.php`
Purpose: Suggest menu items using past customer behavior and popular item pairings.

7. Sales and Inventory Anomaly Detection Feature
Location: `ai_anomaly_detection.php` and shortcut from `analytics.php`
Purpose: Detect unusual sales spikes, drops, and inventory adjustments that need review.

8. Chatbot / Virtual Assistant Feature
Location: `ai_virtual_assistant.php` and shortcut from `pos.php`
Purpose: Provide operational answers, menu help, and service guidance in a dedicated assistant module.

9. Waste Reduction and Inventory Efficiency Feature
Location: `ai_waste_reduction.php` and shortcut from `inventory.php`
Purpose: Highlight expiring stock, slow-moving menu items, and waste-related inventory activity.

### Hub Page

- `ai_insights.php` is the central AI Business Suite hub.
- The sidebar now exposes the suite as `AI Suite`.
- Analytics, Inventory, POS, and Dashboard each link into the relevant AI feature pages.

### Service Architecture

- Shared orchestration remains available through `includes/ai_modules.php`, which now acts as a compatibility wrapper.
- Dedicated per-module services now live in `includes/ai_services.php`.
- Each AI page calls its own service function directly instead of reading the whole suite and extracting one section.

### AI Page Map

- `ai_insights.php` -> `getAiFeatureSuite(PDO $pdo)`
	Data sources: `orders`, `order_items`, `menu_items`, `menu_categories`, `ingredients`, `inventory_logs`
- `ai_sales_forecasting.php` -> `getAiSalesForecastingData(PDO $pdo)`
	Helpers: `forecastSales()`, `buildPercentageTrend()`
	Data sources: `orders`, `order_items`, `menu_items`
- `ai_inventory_optimization.php` -> `getAiInventoryOptimizationData(PDO $pdo)`
	Data sources: `ingredients`, `inventory_logs`
- `ai_demand_prediction.php` -> `getAiDemandPredictionData(PDO $pdo)`
	Data sources: `orders`, `order_items`, `menu_items`
- `ai_smart_reordering.php` -> `getAiSmartReorderingData(PDO $pdo)`
	Data sources: `ingredients`, `inventory_logs`
- `ai_customer_preferences.php` -> `getAiCustomerPreferencesData(PDO $pdo)`
	Data sources: `orders`, `order_items`, `menu_items`, `menu_categories`, `customers`
- `ai_recommendation_system.php` -> `getAiRecommendationSystemData(PDO $pdo)`
	Data sources: `orders`, `order_items`
- `ai_anomaly_detection.php` -> `getAiAnomalyDetectionData(PDO $pdo)`
	Helpers: `aiStandardDeviation()`
	Data sources: `orders`, `inventory_logs`, `ingredients`
- `ai_virtual_assistant.php` -> `getAiVirtualAssistantData(PDO $pdo)` and `askAiVirtualAssistant(PDO $pdo, string $question)`
	Data sources: `settings`, `orders`, `order_items`, `menu_items`, `menu_categories`, `ingredients`, `inventory_logs`
- `ai_waste_reduction.php` -> `getAiWasteReductionData(PDO $pdo)`
	Helpers: `getExpiringIngredients()`
	Data sources: `ingredients`, `inventory_logs`, `orders`, `order_items`, `menu_items`

### Virtual Assistant Provider

- The Virtual Assistant module now supports a real external AI provider through an OpenAI-compatible chat completions endpoint.
- Configure it from the `General` tab in `user_settings.php` as a supervisor.
- Supported saved settings:
	- `ai_assistant_enabled`
	- `ai_assistant_provider`
	- `ai_assistant_endpoint`
	- `ai_assistant_model`
	- `ai_assistant_api_key`
	- `ai_assistant_timeout_seconds`
	- `ai_assistant_system_prompt`
- Supported environment-variable overrides:
	- `KIN_CAFE_AI_ENABLED`
	- `KIN_CAFE_AI_PROVIDER`
	- `KIN_CAFE_AI_ENDPOINT`
	- `KIN_CAFE_AI_MODEL`
	- `KIN_CAFE_AI_API_KEY`
	- `KIN_CAFE_AI_TIMEOUT_SECONDS`
	- `KIN_CAFE_AI_SYSTEM_PROMPT`
- If no valid external configuration is present, the Virtual Assistant falls back to the internal prompt library and business-rule answer generation.

	## Email Delivery

	- The project now includes an application mailer in `includes/mailer.php` that loads PHPMailer directly from `PHPMailer/PHPMailer-master/src/`.
	- Base mail defaults now live in `includes/app_config.php`, following a single config-file pattern similar to lightweight PHP apps.
	- No root-level Composer autoload is required for the current mail integration because the project uses the downloaded PHPMailer source tree already present in the repository.
	- Supervisors can configure SMTP delivery from the `General` tab in `user_settings.php` and send a live test email from the same screen.
	- Runtime precedence is: environment variables, then saved settings from the database, then defaults from `includes/app_config.php`.
	- The login page also sends a password-reset confirmation email after a successful forgot-password reset when SMTP delivery is enabled and configured.

	### Saved Mail Settings

	- `mail_enabled`
	- `mail_smtp_host`
	- `mail_smtp_port`
	- `mail_smtp_username`
	- `mail_smtp_password`
	- `mail_smtp_auth_enabled`
	- `mail_smtp_encryption`
	- `mail_from_email`
	- `mail_from_name`
	- `mail_reply_to_email`
	- `mail_reply_to_name`
	- `mail_smtp_timeout_seconds`

	### Mail Environment Overrides

	- `KIN_CAFE_MAIL_ENABLED`
	- `KIN_CAFE_SMTP_HOST`
	- `KIN_CAFE_SMTP_PORT`
	- `KIN_CAFE_SMTP_USERNAME`
	- `KIN_CAFE_SMTP_PASSWORD`
	- `KIN_CAFE_SMTP_AUTH`
	- `KIN_CAFE_SMTP_ENCRYPTION`
	- `KIN_CAFE_MAIL_FROM_EMAIL`
	- `KIN_CAFE_MAIL_FROM_NAME`
	- `KIN_CAFE_MAIL_REPLY_TO`
	- `KIN_CAFE_MAIL_REPLY_TO_NAME`
	- `KIN_CAFE_SMTP_TIMEOUT_SECONDS`

## Maintenance Notes

- The app bootstrap in includes/functions.php already performs the schema upgrades needed by menu management, so no separate category setup script is required.
- Before removing any page or helper, verify runtime and fetch references instead of relying only on string-count audits.
