<?php

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/forecast_client.php';

function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function formatAppDateTime(?string $value, string $empty = ''): string {
    $value = trim((string) $value);
    if ($value === '') {
        return $empty;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('M d, Y g:i A', $timestamp);
}

function formatMoney($amount): string {
    return number_format((float) $amount, 2);
}

function getBackupDirectory(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
}

function renderPageBackButton(string $href = 'dashboard.php', string $label = 'Back'): void {
    echo '<a class="kc-page-back" href="' . htmlspecialchars($href) . '">'
        . '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5.5 8.5 12 15 18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . '<span>' . htmlspecialchars($label) . '</span></a>';
}

function syncAutomatedStockCeilings(PDO $pdo): void {
    if (!tableExists($pdo, 'ingredients') || !columnExists($pdo, 'ingredients', 'max_stock')) {
        return;
    }
    try {
        $pdo->exec("UPDATE ingredients
            SET max_stock = stock_quantity
            WHERE deleted_at IS NULL
              AND stock_quantity > GREATEST(COALESCE(max_stock, 0), 0)");
    } catch (Throwable $e) {
        // Schema differences should not block inventory.
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tableName, $columnName]);
    return (bool) $stmt->fetchColumn();
}

function ensureColumn(PDO $pdo, string $tableName, string $columnName, string $definition): void {
    if (!columnExists($pdo, $tableName, $columnName)) {
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$definition}");
    }
}

function normalizeIngredientUnit(string $unit): string {
    $unit = strtolower(trim($unit));
    $unitAliases = [
        'pcs' => 'pcs', 'piece' => 'pcs', 'pieces' => 'pcs',
        'g' => 'grams', 'gram' => 'grams', 'grams' => 'grams', 'kg' => 'grams', 'kilogram' => 'grams', 'kilograms' => 'grams',
        'l' => 'liters', 'liter' => 'liters', 'liters' => 'liters', 'ml' => 'liters', 'milliliter' => 'liters', 'milliliters' => 'liters',
    ];
    return $unitAliases[$unit] ?? 'pcs';
}

function isValidIngredientUnit(string $unit): bool {
    $normalized = normalizeIngredientUnit($unit);
    return in_array($normalized, ['pcs', 'grams', 'liters'], true);
}

function getIngredientUnitDimension(string $unit): string {
    $normalized = normalizeIngredientUnit($unit);
    if ($normalized === 'grams') {
        return 'weight';
    }
    if ($normalized === 'liters') {
        return 'volume';
    }
    return 'count';
}

function normalizeIngredientQuantityAmount(float $quantity, string $unit): float {
    $unit = strtolower(trim($unit));
    if (in_array($unit, ['kg', 'kilogram', 'kilograms'], true)) {
        return $quantity * 1000.0;
    }
    if (in_array($unit, ['g', 'gram', 'grams'], true)) {
        return $quantity;
    }
    if (in_array($unit, ['l', 'liter', 'liters'], true)) {
        return $quantity;
    }
    if (in_array($unit, ['ml', 'milliliter', 'milliliters'], true)) {
        return $quantity / 1000.0;
    }
    return $quantity;
}

function convertIngredientQuantityToStorage(float $quantity, ?string $recipeUnit, string $ingredientUnit): float {
    $ingredientUnitNormalized = normalizeIngredientUnit($ingredientUnit);
    $recipeUnitNormalized = $recipeUnit !== null ? strtolower(trim($recipeUnit)) : '';
    if ($recipeUnitNormalized === '') {
        if ($ingredientUnitNormalized === 'grams') {
            $recipeUnitNormalized = 'grams';
        } elseif ($ingredientUnitNormalized === 'liters') {
            $recipeUnitNormalized = 'milliliters';
        } else {
            $recipeUnitNormalized = 'pcs';
        }
    }
    if (getIngredientUnitDimension($recipeUnitNormalized) !== getIngredientUnitDimension($ingredientUnitNormalized)) {
        throw new InvalidArgumentException(sprintf('Cannot convert recipe unit "%s" to ingredient unit "%s".', $recipeUnitNormalized, $ingredientUnitNormalized));
    }
    return normalizeIngredientQuantityAmount($quantity, $recipeUnitNormalized);
}

function ensureDefaultUser(PDO $pdo, string $username, string $passwordHash, string $email, string $role): void {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, is_active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE id = id");
    $stmt->execute([$username, $passwordHash, $email, $role]);
}

function ensureSystemSchema(PDO $pdo): void {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'supervisor',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        email VARCHAR(100) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_password_reset_user_created (user_id, created_at),
        KEY idx_password_reset_expires (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        succeeded TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL,
        KEY idx_login_attempts_lookup (username, ip_address, attempted_at)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        parent_id INT NULL,
        category_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES menu_categories(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ingredients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        unit VARCHAR(50) DEFAULT 'pcs',
        stock_quantity DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(100) NULL,
        loyalty_points INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL,
        price_solo DECIMAL(10,2) NULL,
        price_sharing DECIMAL(10,2) NULL,
        price_hot DECIMAL(10,2) NULL,
        price_iced DECIMAL(10,2) NULL,
        size_option_enabled TINYINT(1) NOT NULL DEFAULT 0,
        size_label_1 VARCHAR(30) NULL,
        size_label_2 VARCHAR(30) NULL,
        price_size_1 DECIMAL(10,2) NULL,
        price_size_2 DECIMAL(10,2) NULL,
        temperature VARCHAR(10) NULL,
        product_code VARCHAR(50) NULL,
        temperature_option_enabled TINYINT(1) NOT NULL DEFAULT 0,
        promo_price DECIMAL(10,2) NULL,
        promo_start DATETIME NULL,
        promo_end DATETIME NULL,
        tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
        category_id INT NULL,
        image VARCHAR(255) NULL,
        available TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total_amount DECIMAL(10,2) NOT NULL,
        subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        change_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        cash_received_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        cash_received_denominations_json TEXT NULL,
        cash_change_denominations_json TEXT NULL,
        payment_method ENUM('cash') NOT NULL DEFAULT 'cash',
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        receipt_number VARCHAR(40) NULL,
        customer_id INT NULL,
        transaction_status VARCHAR(30) NOT NULL DEFAULT 'completed',
        refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_by INT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        menu_item_id INT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        item_name_snapshot VARCHAR(255) NULL,
        product_code_snapshot VARCHAR(50) NULL,
        customizations TEXT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_item_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_item_id INT NOT NULL,
        ingredient_id INT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
        quantity_unit VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
        FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(100) NOT NULL,
        discount_type ENUM('fixed', 'percent') NOT NULL,
        discount_value DECIMAL(10,2) NOT NULL,
        minimum_order DECIMAL(10,2) NOT NULL DEFAULT 0,
        start_at DATETIME NULL,
        end_at DATETIME NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        usage_count INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        payment_method ENUM('cash', 'card', 'digital') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reference_number VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_discounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        promotion_id INT NULL,
        discount_code VARCHAR(50) NULL,
        discount_amount DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        reference_number VARCHAR(100) NULL,
        event_type VARCHAR(50) NOT NULL,
        status VARCHAR(30) NOT NULL,
        message TEXT NULL,
        payload_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_key VARCHAR(100) NOT NULL,
        dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_notif (user_id, notification_key)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NULL,
        details_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS backups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_name VARCHAR(255) NULL,
        file_path VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL,
        reason VARCHAR(50) NULL,
        message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_reconciliations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        business_date DATE NOT NULL,
        gross_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        refund_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        net_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
        cash_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        order_count INT NOT NULL DEFAULT 0,
        reconciled_by INT NULL,
        notes TEXT NULL,
        reconciled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_business_date (business_date)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_tab_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        php_session_id VARCHAR(128) NOT NULL,
        tab_id VARCHAR(64) NOT NULL,
        user_id INT NOT NULL,
        user_role VARCHAR(20) NOT NULL,
        ip_address VARCHAR(64) NOT NULL,
        user_agent_hash CHAR(64) NOT NULL,
        idle_timeout_seconds INT NOT NULL DEFAULT 1800,
        expires_at DATETIME NOT NULL,
        last_seen_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_php_tab (php_session_id, tab_id),
        KEY idx_user_id (user_id),
        KEY idx_last_seen (last_seen_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pref_key VARCHAR(100) NOT NULL,
        pref_value LONGTEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_pref (user_id, pref_key),
        KEY idx_pref_key (pref_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resource_refcounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resource_type VARCHAR(60) NOT NULL,
        resource_key VARCHAR(128) NOT NULL,
        ref_count INT NOT NULL DEFAULT 0,
        last_event_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_resource (resource_type, resource_key),
        KEY idx_ref_count (ref_count),
        KEY idx_last_event (last_event_at)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resource_ref_edges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_type VARCHAR(60) NOT NULL,
        from_key VARCHAR(128) NOT NULL,
        to_type VARCHAR(60) NOT NULL,
        to_key VARCHAR(128) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_edge (from_type, from_key, to_type, to_key),
        KEY idx_from (from_type, from_key),
        KEY idx_to (to_type, to_key)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS resource_ref_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        resource_type VARCHAR(60) NOT NULL,
        resource_key VARCHAR(128) NOT NULL,
        delta INT NOT NULL,
        new_count INT NOT NULL,
        event_type VARCHAR(20) NOT NULL,
        ref_tag VARCHAR(80) NULL,
        by_user_id INT NULL,
        meta_json TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_resource_time (resource_type, resource_key, created_at),
        KEY idx_created_at (created_at),
        KEY idx_by_user (by_user_id),
        FOREIGN KEY (by_user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    ensureColumn($pdo, 'users', 'role', "VARCHAR(20) NOT NULL DEFAULT 'supervisor'");
    ensureColumn($pdo, 'users', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");

    ensureColumn($pdo, 'menu_categories', 'parent_id', 'INT NULL');
    ensureColumn($pdo, 'menu_categories', 'category_order', 'INT NOT NULL DEFAULT 0');

    ensureColumn($pdo, 'menu_items', 'price_solo', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'price_sharing', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'price_hot', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'price_iced', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'size_option_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'menu_items', 'size_label_1', 'VARCHAR(30) NULL');
    ensureColumn($pdo, 'menu_items', 'size_label_2', 'VARCHAR(30) NULL');
    ensureColumn($pdo, 'menu_items', 'price_size_1', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'price_size_2', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'temperature', 'VARCHAR(10) NULL');
    ensureColumn($pdo, 'menu_items', 'product_code', 'VARCHAR(50) NULL');
    ensureColumn($pdo, 'menu_items', 'temperature_option_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'menu_items', 'promo_price', 'DECIMAL(10,2) NULL');
    ensureColumn($pdo, 'menu_items', 'promo_start', 'DATETIME NULL');
    ensureColumn($pdo, 'menu_items', 'promo_end', 'DATETIME NULL');
    ensureColumn($pdo, 'menu_items', 'tax_exempt', 'TINYINT(1) NOT NULL DEFAULT 0');
    if (tableExists($pdo, 'menu_item_recipes')) {
        ensureColumn($pdo, 'menu_item_recipes', 'quantity_unit', 'VARCHAR(20) NULL');
    }

    ensureColumn($pdo, 'orders', 'subtotal_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'tax_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'discount_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'paid_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'change_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'receipt_number', 'VARCHAR(40) NULL');
    ensureColumn($pdo, 'orders', 'customer_id', 'INT NULL');
    ensureColumn($pdo, 'orders', 'transaction_status', "VARCHAR(30) NOT NULL DEFAULT 'completed'");
    ensureColumn($pdo, 'orders', 'refund_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'created_by', 'INT NULL');
    ensureColumn($pdo, 'orders', 'notes', 'TEXT NULL');
    ensureColumn($pdo, 'orders', 'cash_received_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'orders', 'cash_received_denominations_json', 'TEXT NULL');
    ensureColumn($pdo, 'orders', 'cash_change_denominations_json', 'TEXT NULL');

    if (tableExists($pdo, 'order_payments')) {
        ensureColumn($pdo, 'order_payments', 'denominations_received_json', 'TEXT NULL');
        ensureColumn($pdo, 'order_payments', 'change_denominations_json', 'TEXT NULL');
    }

    ensureColumn($pdo, 'order_items', 'line_total', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'order_items', 'item_name_snapshot', 'VARCHAR(255) NULL');
    ensureColumn($pdo, 'order_items', 'product_code_snapshot', 'VARCHAR(50) NULL');
    ensureColumn($pdo, 'order_items', 'customizations', 'TEXT NULL');

    ensureColumn($pdo, 'inventory_logs', 'performed_by', 'INT NULL');
    ensureColumn($pdo, 'users', 'failed_login_attempts', 'INT NOT NULL DEFAULT 0');
    ensureColumn($pdo, 'users', 'locked_until', 'DATETIME NULL');
    try {
        $pdo->exec("ALTER TABLE inventory_logs MODIFY COLUMN action ENUM('add', 'remove', 'sale', 'purchase', 'adjust') NOT NULL");
    } catch (Throwable $e) {
        // Ignore when enum already includes these values or table is unavailable.
    }

    if (tableExists($pdo, 'ingredients')) {
        ensureColumn($pdo, 'ingredients', 'unit_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        ensureColumn($pdo, 'ingredients', 'manufacturing_date', 'DATE NULL');
        ensureColumn($pdo, 'ingredients', 'expiration_date', 'DATE NULL');
        ensureColumn($pdo, 'ingredients', 'deleted_at', 'DATETIME NULL');
        ensureColumn($pdo, 'ingredients', 'deleted_by', 'INT NULL');
        ensureColumn($pdo, 'ingredients', 'max_stock', 'DECIMAL(10,2) DEFAULT 100');
    }

    if (tableExists($pdo, 'menu_items')) {
        ensureColumn($pdo, 'menu_items', 'cost_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        ensureColumn($pdo, 'menu_items', 'admin_cost', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        ensureColumn($pdo, 'menu_items', 'markup_percent', 'DECIMAL(10,2) NOT NULL DEFAULT 30.00');
        ensureColumn($pdo, 'menu_items', 'manual_price_override', 'TINYINT(1) NOT NULL DEFAULT 0');
    }

    $mailDefaults = getProjectMailDefaults();

    $defaults = [
        'tax_rate' => '0.00',
        'loyalty_points_per_currency' => '0.05',
        'backup_enabled' => '1',
        'receipt_footer' => 'Thank you for visiting Kin Cafe.',
        'multi_account_enabled' => '1',
        'session_ttl_seconds_supervisor' => (string) (8 * 3600),
        'session_ttl_seconds_cashier' => (string) (4 * 3600),
        'session_idle_seconds_supervisor' => (string) (60 * 60),
        'session_idle_seconds_cashier' => (string) (30 * 60),
        'ai_assistant_enabled' => '0',
        'ai_assistant_provider' => 'openai-compatible',
        'ai_assistant_endpoint' => 'https://api.openai.com/v1/chat/completions',
        'ai_assistant_model' => 'gpt-4o-mini',
        'ai_assistant_api_key' => '',
        'ai_assistant_timeout_seconds' => '20',
        'ai_assistant_system_prompt' => 'You are Kin Cafe\'s Lead AI Operations Strategist & Business Intelligence Analyst. Provide deep, data-driven, highly precise, and predictive analysis based on live cafe transactions and inventory data. Always include quantitative metrics, clear trend interpretations, specific operational risk flags, and step-by-step optimization recommendations.',
        'mail_enabled' => $mailDefaults['enabled'] ? '1' : '0',
        'mail_smtp_host' => $mailDefaults['host'],
        'mail_smtp_port' => (string) $mailDefaults['port'],
        'mail_smtp_username' => $mailDefaults['username'],
        'mail_smtp_password' => $mailDefaults['password'],
        'mail_smtp_auth_enabled' => $mailDefaults['auth_enabled'] ? '1' : '0',
        'mail_smtp_encryption' => $mailDefaults['encryption'],
        'mail_from_email' => $mailDefaults['from_email'],
        'mail_from_name' => $mailDefaults['from_name'],
        'mail_reply_to_email' => $mailDefaults['reply_to_email'],
        'mail_reply_to_name' => $mailDefaults['reply_to_name'],
        'mail_smtp_timeout_seconds' => (string) $mailDefaults['timeout_seconds'],
    ];

    $insertSetting = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = setting_value");
    foreach ($defaults as $key => $value) {
        $insertSetting->execute([$key, $value]);
    }

    ensureDefaultUser(
        $pdo,
        'admin',
        '$2y$10$qrK17ZfHRHZ59qPlWFzrG.Eio9phNq/UzpxCm1rn64A2ptNtFg2FC',
        'admin@kincafe.com',
        'supervisor'
    );
    ensureDefaultUser(
        $pdo,
        'cashier',
        '$2y$10$npf4pLTAWNaqxWluba9KzuyUYrjD9OIrmz6HgVWZTIoDe5TvKMZ92',
        'cashier@kincafe.com',
        'cashier'
    );

    $itemsMissingCode = $pdo->query("SELECT id FROM menu_items WHERE product_code IS NULL OR product_code = ''")->fetchAll(PDO::FETCH_COLUMN);
    if ($itemsMissingCode) {
        $updateCodeStmt = $pdo->prepare("UPDATE menu_items SET product_code = ? WHERE id = ?");
        foreach ($itemsMissingCode as $itemId) {
            $updateCodeStmt->execute([sprintf('ITM%04d', $itemId), $itemId]);
        }
    }

    $pdo->exec("UPDATE users SET role = 'supervisor' WHERE role IS NULL OR role = ''");
    $pdo->exec("UPDATE users SET role = 'supervisor' WHERE role IN ('admin', 'manager')");

    try { $pdo->exec("CREATE INDEX idx_orders_created_at ON orders (created_at)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_orders_payment_status_created_at ON orders (payment_status, created_at)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_order_items_order_id ON order_items (order_id)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_order_payments_order_id ON order_payments (order_id)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_order_payments_method ON order_payments (payment_method)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)"); } catch (Throwable $e) {}
    try { $pdo->exec("CREATE INDEX idx_refcounts_type_count ON resource_refcounts (resource_type, ref_count)"); } catch (Throwable $e) {}

    $initialized = true;
}

function authIsSessionTabId(string $tab): bool {
    if ($tab === 'default') {
        return true;
    }
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tab)) {
        return true;
    }
    return (bool) preg_match('/^[0-9a-f]{20,}$/i', $tab);
}

function authTabId(): string {
    $fromGet = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['tab'] ?? ''));
    $fromSession = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_SESSION['kc_tab'] ?? 'default'));
    if ($fromGet !== '' && authIsSessionTabId($fromGet)) {
        return substr($fromGet, 0, 64);
    }
    if ($fromSession !== '') {
        return substr($fromSession, 0, 64);
    }
    return 'default';
}

function authMultiAccountEnabled(PDO $pdo): bool {
    $value = (string) getSetting($pdo, 'multi_account_enabled', '1');
    return $value === '1';
}

function authClientIp(): string {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function authClientAgentHash(): string {
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function authSessionPolicy(PDO $pdo, string $role): array {
    $defaults = [
        'supervisor' => ['ttl' => 8 * 3600, 'idle' => 60 * 60],
        'cashier' => ['ttl' => 4 * 3600, 'idle' => 30 * 60],
    ];

    $role = normalizeRole($role);
    $policy = $defaults[$role] ?? ['ttl' => 4 * 3600, 'idle' => 30 * 60];

    $ttlKey = "session_ttl_seconds_{$role}";
    $idleKey = "session_idle_seconds_{$role}";

    $ttlDefault = (string) $policy['ttl'];
    $idleDefault = (string) $policy['idle'];
    if ($role === 'supervisor') {
        $ttlDefault = (string) (getSetting($pdo, 'session_ttl_seconds_admin', getSetting($pdo, 'session_ttl_seconds_manager', $ttlDefault)));
        $idleDefault = (string) (getSetting($pdo, 'session_idle_seconds_admin', getSetting($pdo, 'session_idle_seconds_manager', $idleDefault)));
    }

    $ttlOverride = (int) getSetting($pdo, $ttlKey, $ttlDefault);
    $idleOverride = (int) getSetting($pdo, $idleKey, $idleDefault);
    return ['ttl' => max(300, $ttlOverride), 'idle' => max(60, $idleOverride)];
}

function authUpsertActiveSession(PDO $pdo, string $tabId, int $userId, string $role, int $idleTimeoutSeconds, string $expiresAt): void {
    $phpSessionId = session_id();
    $ip = authClientIp();
    $uaHash = authClientAgentHash();
    $stmt = $pdo->prepare("INSERT INTO auth_tab_sessions (php_session_id, tab_id, user_id, user_role, ip_address, user_agent_hash, idle_timeout_seconds, expires_at, last_seen_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), user_role = VALUES(user_role), ip_address = VALUES(ip_address), user_agent_hash = VALUES(user_agent_hash),
        idle_timeout_seconds = VALUES(idle_timeout_seconds), expires_at = VALUES(expires_at), last_seen_at = CURRENT_TIMESTAMP");
    $stmt->execute([$phpSessionId, $tabId, $userId, $role, $ip, $uaHash, $idleTimeoutSeconds, $expiresAt]);
}

function authRemoveActiveSession(PDO $pdo, string $tabId): void {
    $phpSessionId = session_id();
    $stmt = $pdo->prepare('DELETE FROM auth_tab_sessions WHERE php_session_id = ? AND tab_id = ?');
    $stmt->execute([$phpSessionId, $tabId]);
}

function authRemoveAllActiveSessions(PDO $pdo): void {
    $phpSessionId = session_id();
    $stmt = $pdo->prepare('DELETE FROM auth_tab_sessions WHERE php_session_id = ?');
    $stmt->execute([$phpSessionId]);
}

function authLoginToTab(PDO $pdo, string $tabId, array $user): void {
    $role = normalizeRole((string) ($user['role'] ?? ''));
    $policy = authSessionPolicy($pdo, $role);
    $now = time();
    $expiresAt = $now + $policy['ttl'];
    $_SESSION['tab_auth'][$tabId] = [
        'user_id' => (int) $user['id'],
        'role' => $role,
        'ip' => authClientIp(),
        'ua' => authClientAgentHash(),
        'last_seen' => $now,
        'idle' => $policy['idle'],
        'expires' => $expiresAt,
    ];
    authUpsertActiveSession($pdo, $tabId, (int) $user['id'], $role, (int) $policy['idle'], date('Y-m-d H:i:s', $expiresAt));
}

function authLogoutTab(PDO $pdo, string $tabId): void {
    if (isset($_SESSION['tab_auth'][$tabId])) {
        unset($_SESSION['tab_auth'][$tabId]);
    }
    authRemoveActiveSession($pdo, $tabId);
}

function authLogoutAll(PDO $pdo): void {
    unset($_SESSION['tab_auth']);
    authRemoveAllActiveSessions($pdo);
}

function authBootstrap(PDO $pdo): void {
    $tabId = authTabId();

    if (!authMultiAccountEnabled($pdo)) {
        unset($_SESSION['tab_auth'], $_SESSION['kc_tab']);
        return;
    }

    $_SESSION['kc_tab'] = $tabId;
    $record = $_SESSION['tab_auth'][$tabId] ?? null;
    if (!$record) {
        unset($_SESSION['admin'], $_SESSION['role']);
        return;
    }

    $now = time();
    if (($record['expires'] ?? 0) < $now) {
        authLogoutTab($pdo, $tabId);
        unset($_SESSION['admin'], $_SESSION['role']);
        return;
    }

    if (($record['last_seen'] ?? 0) > 0 && ($record['idle'] ?? 0) > 0 && ($now - (int) $record['last_seen']) > (int) $record['idle']) {
        authLogoutTab($pdo, $tabId);
        unset($_SESSION['admin'], $_SESSION['role']);
        return;
    }

    if (($record['ip'] ?? '') !== authClientIp() || ($record['ua'] ?? '') !== authClientAgentHash()) {
        authLogoutTab($pdo, $tabId);
        unset($_SESSION['admin'], $_SESSION['role']);
        return;
    }

    $record['last_seen'] = $now;
    $_SESSION['tab_auth'][$tabId] = $record;
    authUpsertActiveSession($pdo, $tabId, (int) $record['user_id'], (string) $record['role'], (int) $record['idle'], date('Y-m-d H:i:s', (int) $record['expires']));

    $_SESSION['admin'] = (int) $record['user_id'];
    $_SESSION['role'] = (string) $record['role'];
}

function getSetting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function setSetting(PDO $pdo, string $key, ?string $value): void {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$key, $value]);
}

function generatePasswordResetOtp(int $length = 6): string {
    $length = max(4, min(10, $length));
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= (string) random_int(0, 9);
    }
    return $otp;
}

function cleanupPasswordResetOtps(PDO $pdo): void {
    if (!tableExists($pdo, 'password_reset_otps')) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM password_reset_otps WHERE (consumed_at IS NOT NULL AND consumed_at < DATE_SUB(NOW(), INTERVAL 1 DAY)) OR (expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
    $stmt->execute();
}

function clearPasswordResetOtps(PDO $pdo, int $userId, string $email): void {
    if ($userId <= 0 || !tableExists($pdo, 'password_reset_otps')) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE password_reset_otps SET consumed_at = NOW() WHERE user_id = ? AND email = ? AND consumed_at IS NULL');
    $stmt->execute([$userId, $email]);
}

function createPasswordResetOtp(PDO $pdo, int $userId, string $email, string $otp, int $ttlSeconds = 600): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid user id for password reset OTP.');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid email is required for password reset OTP.');
    }
    if ($otp === '') {
        throw new InvalidArgumentException('OTP value is required.');
    }

    cleanupPasswordResetOtps($pdo);
    clearPasswordResetOtps($pdo, $userId, $email);

    $ttlSeconds = max(300, min(1800, $ttlSeconds));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO password_reset_otps (user_id, email, otp_hash, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $email, $otpHash, $expiresAt]);

    return [
        'expires_at' => $expiresAt,
        'ttl_seconds' => $ttlSeconds,
    ];
}

function verifyPasswordResetOtp(PDO $pdo, int $userId, string $email, string $otp, int $maxAttempts = 5): array {
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Invalid password reset request.'];
    }
    if ($email === '' || $otp === '') {
        return ['success' => false, 'message' => 'OTP verification requires an email and code.'];
    }
    if (!tableExists($pdo, 'password_reset_otps')) {
        return ['success' => false, 'message' => 'Password reset storage is not available.'];
    }

    cleanupPasswordResetOtps($pdo);

    $maxAttempts = max(1, min(10, $maxAttempts));
    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, otp_hash, attempts, expires_at FROM password_reset_otps WHERE user_id = ? AND email = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId, $email]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['success' => false, 'message' => 'No active OTP request was found. Request a new OTP.'];
        }

        if (strtotime((string) $request['expires_at']) < time()) {
            $expireStmt = $pdo->prepare('UPDATE password_reset_otps SET consumed_at = NOW() WHERE id = ?');
            $expireStmt->execute([(int) $request['id']]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['success' => false, 'message' => 'The OTP has expired. Request a new one.'];
        }

        $attempts = (int) ($request['attempts'] ?? 0);
        if ($attempts >= $maxAttempts) {
            $lockStmt = $pdo->prepare('UPDATE password_reset_otps SET consumed_at = NOW() WHERE id = ?');
            $lockStmt->execute([(int) $request['id']]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['success' => false, 'message' => 'Too many invalid OTP attempts. Request a new OTP.'];
        }

        if (!password_verify($otp, (string) $request['otp_hash'])) {
            $nextAttempts = $attempts + 1;
            $invalidStmt = $pdo->prepare('UPDATE password_reset_otps SET attempts = ?, consumed_at = CASE WHEN ? >= ? THEN NOW() ELSE consumed_at END WHERE id = ?');
            $invalidStmt->execute([$nextAttempts, $nextAttempts, $maxAttempts, (int) $request['id']]);
            if ($startedTransaction) {
                $pdo->commit();
            }

            return [
                'success' => false,
                'message' => $nextAttempts >= $maxAttempts
                    ? 'Too many invalid OTP attempts. Request a new OTP.'
                    : 'The OTP you entered is invalid.',
            ];
        }

        $successStmt = $pdo->prepare('UPDATE password_reset_otps SET consumed_at = NOW() WHERE id = ?');
        $successStmt->execute([(int) $request['id']]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function csrfToken(string $scope = 'default'): string {
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    if (!isset($_SESSION['csrf_tokens'][$scope]) || !is_string($_SESSION['csrf_tokens'][$scope]) || $_SESSION['csrf_tokens'][$scope] === '') {
        $_SESSION['csrf_tokens'][$scope] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_tokens'][$scope];
}

function csrfValidate(string $token, string $scope = 'default'): bool {
    if (!isset($_SESSION['csrf_tokens'][$scope])) {
        return false;
    }
    $expected = (string) $_SESSION['csrf_tokens'][$scope];
    if ($expected === '' || $token === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

function loginMaxAttempts(): int {
    return 3;
}

function loginCooldownSeconds(): int {
    return 300;
}

function getLoginLockoutStatus(PDO $pdo, string $username, ?string $ipAddress = null): array {
    $username = trim($username);
    $ipAddress = $ipAddress !== null && $ipAddress !== '' ? $ipAddress : authClientIp();
    $maxAttempts = loginMaxAttempts();
    $cooldownSeconds = loginCooldownSeconds();
    $remainingSeconds = 0;
    $failedAttempts = 0;

    if ($username !== '' && tableExists($pdo, 'users') && columnExists($pdo, 'users', 'locked_until')) {
        $stmt = $pdo->prepare('SELECT id, failed_login_attempts, locked_until FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $failedAttempts = (int) ($user['failed_login_attempts'] ?? 0);
            $lockedUntil = (string) ($user['locked_until'] ?? '');
            if ($lockedUntil !== '') {
                $lockedTs = strtotime($lockedUntil);
                if ($lockedTs !== false && $lockedTs > time()) {
                    $remainingSeconds = max($remainingSeconds, $lockedTs - time());
                } elseif ($lockedTs !== false && $lockedTs <= time()) {
                    $clearStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
                    $clearStmt->execute([(int) $user['id']]);
                    $failedAttempts = 0;
                }
            }
        }
    }

    if (tableExists($pdo, 'login_attempts')) {
        $windowStart = date('Y-m-d H:i:s', time() - $cooldownSeconds);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts
            WHERE username = ? AND ip_address = ? AND succeeded = 0 AND attempted_at >= ?');
        $stmt->execute([$username !== '' ? $username : '(blank)', $ipAddress, $windowStart]);
        $windowFails = (int) $stmt->fetchColumn();
        $failedAttempts = max($failedAttempts, $windowFails);
        if ($windowFails >= $maxAttempts) {
            $oldestStmt = $pdo->prepare('SELECT attempted_at FROM login_attempts
                WHERE username = ? AND ip_address = ? AND succeeded = 0 AND attempted_at >= ?
                ORDER BY attempted_at ASC LIMIT 1');
            $oldestStmt->execute([$username !== '' ? $username : '(blank)', $ipAddress, $windowStart]);
            $oldest = (string) ($oldestStmt->fetchColumn() ?: '');
            if ($oldest !== '') {
                $unlockAt = strtotime($oldest) + $cooldownSeconds;
                if ($unlockAt > time()) {
                    $remainingSeconds = max($remainingSeconds, $unlockAt - time());
                }
            }
        }
    }

    return [
        'locked' => $remainingSeconds > 0,
        'remaining_seconds' => $remainingSeconds,
        'failed_attempts' => $failedAttempts,
        'max_attempts' => $maxAttempts,
        'cooldown_seconds' => $cooldownSeconds,
        'attempts_remaining' => max(0, $maxAttempts - $failedAttempts),
    ];
}

function formatLoginCooldownMessage(int $remainingSeconds): string {
    $remainingSeconds = max(1, $remainingSeconds);
    $minutes = (int) floor($remainingSeconds / 60);
    $seconds = $remainingSeconds % 60;
    if ($minutes > 0) {
        return sprintf(
            'Too many failed login attempts. Try again in %d minute%s%s.',
            $minutes,
            $minutes === 1 ? '' : 's',
            $seconds > 0 ? sprintf(' and %d second%s', $seconds, $seconds === 1 ? '' : 's') : ''
        );
    }

    return sprintf('Too many failed login attempts. Try again in %d second%s.', $seconds, $seconds === 1 ? '' : 's');
}

function recordFailedLoginAttempt(PDO $pdo, string $username, ?string $ipAddress = null): array {
    $username = trim($username);
    $ipAddress = $ipAddress !== null && $ipAddress !== '' ? $ipAddress : authClientIp();
    $maxAttempts = loginMaxAttempts();
    $cooldownSeconds = loginCooldownSeconds();

    if (tableExists($pdo, 'login_attempts')) {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, succeeded, attempted_at) VALUES (?, ?, 0, NOW())');
        $stmt->execute([$username !== '' ? $username : '(blank)', $ipAddress]);
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    if ($username !== '' && tableExists($pdo, 'users') && columnExists($pdo, 'users', 'failed_login_attempts')) {
        $stmt = $pdo->prepare('SELECT id, failed_login_attempts FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $attempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;
            if ($attempts >= $maxAttempts) {
                $lockStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?');
                $lockStmt->execute([$attempts, $cooldownSeconds, (int) $user['id']]);
            } else {
                $updateStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = NULL WHERE id = ?');
                $updateStmt->execute([$attempts, (int) $user['id']]);
            }
            logAuditEvent($pdo, 'login_failed', 'user', (int) $user['id'], [
                'username' => $username,
                'attempts' => $attempts,
                'ip' => $ipAddress,
            ]);
        } else {
            logAuditEvent($pdo, 'login_failed', 'user', null, [
                'username' => $username,
                'ip' => $ipAddress,
            ]);
        }
    }

    return getLoginLockoutStatus($pdo, $username, $ipAddress);
}

function clearFailedLoginAttempts(PDO $pdo, string $username, ?string $ipAddress = null): void {
    $username = trim($username);
    $ipAddress = $ipAddress !== null && $ipAddress !== '' ? $ipAddress : authClientIp();

    if ($username !== '' && tableExists($pdo, 'users') && columnExists($pdo, 'users', 'failed_login_attempts')) {
        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = ?');
        $stmt->execute([$username]);
    }

    if (tableExists($pdo, 'login_attempts')) {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, succeeded, attempted_at) VALUES (?, ?, 1, NOW())');
        $stmt->execute([$username !== '' ? $username : '(blank)', $ipAddress]);
    }
}

function getCurrentUser(PDO $pdo): ?array {
    static $cachedUser = null;

    if (!isset($_SESSION['admin'])) {
        return null;
    }

    if ($cachedUser && (int) $cachedUser['id'] === (int) $_SESSION['admin']) {
        return $cachedUser;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin']]);
    $cachedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $cachedUser;
}

function getCurrentUserRole(PDO $pdo): string {
    $user = getCurrentUser($pdo);
    $role = $user['role'] ?? 'guest';
    return normalizeRole((string) $role);
}

function hasPermission(PDO $pdo, string $permission): bool {
    $role = getCurrentUserRole($pdo);
    $permissions = [
        'supervisor' => ['*'],
        'cashier' => ['dashboard.view', 'pos.access', 'pos.checkout', 'orders.view'],
        'guest' => [],
    ];

    if (!isset($permissions[$role])) {
        return false;
    }

    return in_array('*', $permissions[$role], true) || in_array($permission, $permissions[$role], true);
}

function normalizeRole(string $role): string {
    $role = strtolower(trim($role));
    if ($role === 'admin' || $role === 'manager') {
        return 'supervisor';
    }
    if ($role === '') {
        return 'guest';
    }
    return $role;
}

function activeSupervisorCount(PDO $pdo): int {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor' AND is_active = 1");
    return (int) $stmt->fetchColumn();
}

function deleteUserAccount(PDO $pdo, int $userId): void {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Invalid user id.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new InvalidArgumentException('User not found.');
        }

        $role = normalizeRole((string) ($user['role'] ?? ''));
        if ($role === 'supervisor' && activeSupervisorCount($pdo) <= 1) {
            throw new InvalidArgumentException('Cannot delete the last active supervisor account.');
        }

        if (tableExists($pdo, 'auth_tab_sessions')) {
            $stmt = $pdo->prepare('DELETE FROM auth_tab_sessions WHERE user_id = ?');
            $stmt->execute([$userId]);
        }
        if (tableExists($pdo, 'user_preferences')) {
            $stmt = $pdo->prepare('DELETE FROM user_preferences WHERE user_id = ?');
            $stmt->execute([$userId]);
        }

        if (tableExists($pdo, 'audit_logs')) {
            $stmt = $pdo->prepare('UPDATE audit_logs SET user_id = NULL WHERE user_id = ?');
            $stmt->execute([$userId]);
        }
        if (tableExists($pdo, 'orders') && columnExists($pdo, 'orders', 'created_by')) {
            $stmt = $pdo->prepare('UPDATE orders SET created_by = NULL WHERE created_by = ?');
            $stmt->execute([$userId]);
        }
        if (tableExists($pdo, 'inventory_logs') && columnExists($pdo, 'inventory_logs', 'performed_by')) {
            $stmt = $pdo->prepare('UPDATE inventory_logs SET performed_by = NULL WHERE performed_by = ?');
            $stmt->execute([$userId]);
        }
        if (tableExists($pdo, 'daily_reconciliations') && columnExists($pdo, 'daily_reconciliations', 'reconciled_by')) {
            $stmt = $pdo->prepare('UPDATE daily_reconciliations SET reconciled_by = NULL WHERE reconciled_by = ?');
            $stmt->execute([$userId]);
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        logAuditEvent($pdo, 'account_deleted', 'user', $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function parseAvailableValue($value, int $default = 1): int {
    if ($value === null) {
        return $default ? 1 : 0;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $str = strtolower(trim((string) $value));
    if ($str === '1' || $str === 'true' || $str === 'yes' || $str === 'on') {
        return 1;
    }
    if ($str === '0' || $str === 'false' || $str === 'no' || $str === 'off' || $str === '') {
        return 0;
    }
    return $default ? 1 : 0;
}

function clampPercentageChange(float $change, int $limit = 100): float {
    $limit = max(0, $limit);
    return max(-$limit, min($limit, $change));
}

function getDashboardSalesSeries(PDO $pdo, int $days = 7): array {
    $days = in_array($days, [7, 14, 30], true) ? $days : 7;
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS sale_date, COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS sales
        FROM orders
        WHERE payment_status = 'completed'
          AND DATE(created_at) >= ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC");
    $stmt->execute([$startDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $salesByDate = [];
    foreach ($rows as $row) {
        $salesByDate[(string) ($row['sale_date'] ?? '')] = (float) ($row['sales'] ?? 0);
    }

    $labels = [];
    $values = [];
    for ($offset = $days - 1; $offset >= 0; $offset--) {
        $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
        $labels[] = date('M j', strtotime($date));
        $values[] = $salesByDate[$date] ?? 0.0;
    }

    return [
        'days' => $days,
        'labels' => $labels,
        'values' => $values,
    ];
}

function getAnalyticsKpiTrends(PDO $pdo): array {
    $today = getSalesSummary($pdo, date('Y-m-d'));
    $yesterday = getSalesSummary($pdo, date('Y-m-d', strtotime('-1 day')));

    $todayNetSales = (float) ($today['net_sales'] ?? 0);
    $yesterdayNetSales = (float) ($yesterday['net_sales'] ?? 0);
    $todayOrders = (float) ($today['order_count'] ?? 0);
    $yesterdayOrders = (float) ($yesterday['order_count'] ?? 0);
    $todayAov = $todayOrders > 0 ? ($todayNetSales / $todayOrders) : 0.0;
    $yesterdayAov = $yesterdayOrders > 0 ? ($yesterdayNetSales / $yesterdayOrders) : 0.0;

    $newCustomersToday = (float) $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $newCustomersYesterday = (float) $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

    return [
        'net_sales' => buildPercentageTrend($todayNetSales, $yesterdayNetSales),
        'orders' => buildPercentageTrend($todayOrders, $yesterdayOrders),
        'average_order_value' => buildPercentageTrend($todayAov, $yesterdayAov),
        'new_customers' => buildPercentageTrend($newCustomersToday, $newCustomersYesterday),
    ];
}

function getDashboardActiveUserTrend(PDO $pdo): array {
    $activeNow = 0;
    if (tableExists($pdo, 'auth_tab_sessions')) {
        $activeNow = (int) $pdo->query("SELECT COUNT(DISTINCT user_id)
            FROM auth_tab_sessions
            WHERE expires_at > NOW()
              AND TIMESTAMPADD(SECOND, idle_timeout_seconds, COALESCE(last_seen_at, created_at)) > NOW()")->fetchColumn();
    }

    $periods = ['current_week_users' => 0, 'previous_week_users' => 0];
    if (tableExists($pdo, 'auth_tab_sessions')) {
        $periods = $pdo->query("SELECT
                COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 6 DAY) THEN user_id END) AS current_week_users,
                COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 13 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 6 DAY) THEN user_id END) AS previous_week_users
            FROM auth_tab_sessions")->fetch(PDO::FETCH_ASSOC) ?: $periods;
    }

    if (((int) ($periods['current_week_users'] ?? 0)) === 0 && ((int) ($periods['previous_week_users'] ?? 0)) === 0) {
        $periods = $pdo->query("SELECT
                COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN created_by END) AS current_week_users,
                COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN created_by END) AS previous_week_users
            FROM orders
            WHERE payment_status = 'completed'
              AND created_by IS NOT NULL")->fetch(PDO::FETCH_ASSOC) ?: $periods;
    }

    $trend = buildPercentageTrend(
        (float) ($periods['current_week_users'] ?? 0),
        (float) ($periods['previous_week_users'] ?? 0)
    );

    return [
        'active_now' => $activeNow,
        'trend' => [
            'value' => $trend['value'],
            'positive' => $trend['positive'],
        ],
    ];
}

function buildPercentageTrend(float $current, float $previous, int $limit = 100): array {
    if ($previous <= 0.0) {
        if ($current <= 0.0) {
            return ['value' => '0%', 'positive' => true, 'change' => 0.0];
        }

        return ['value' => sprintf('+%d%%', max(0, $limit)), 'positive' => true, 'change' => (float) max(0, $limit)];
    }

    $change = (($current - $previous) / $previous) * 100;
    $change = clampPercentageChange($change, $limit);
    $rounded = (int) round($change);

    return [
        'value' => sprintf('%s%d%%', $rounded > 0 ? '+' : '', $rounded),
        'positive' => $change >= 0,
        'change' => $change,
    ];
}

function normalizeMenuCategoryName(string $name): string {
    $normalized = strtolower(trim($name));
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);
    return $normalized ?? '';
}

function getPreferredMenuCategoryOrderMap(): array {
    return [
        'chaofanricemeals' => 0,
        'pasta' => 1,
        'pizza' => 2,
        'burger' => 3,
        'burgers' => 3,
        'snacks' => 4,
        'sandwitches' => 5,
        'sandwiches' => 5,
        'desserts' => 6,
        'espressobased' => 7,
        'milkbased' => 8,
        'matchaseries' => 9,
        'sodabased' => 10,
        'addons' => 11,
        'add-ons' => 11,
    ];
}

function getMenuCategorySortPriority(?string $name): ?int {
    if ($name === null) {
        return null;
    }

    $orderMap = getPreferredMenuCategoryOrderMap();
    $normalized = normalizeMenuCategoryName($name);
    return $orderMap[$normalized] ?? null;
}

function sortMenuCategories(array $categories): array {
    usort($categories, static function (array $left, array $right): int {
        $leftPriority = getMenuCategorySortPriority($left['name'] ?? null);
        $rightPriority = getMenuCategorySortPriority($right['name'] ?? null);

        if ($leftPriority !== null && $rightPriority !== null && $leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }
        if ($leftPriority !== null && $rightPriority === null) {
            return -1;
        }
        if ($leftPriority === null && $rightPriority !== null) {
            return 1;
        }

        $leftOrder = (int) ($left['category_order'] ?? 0);
        $rightOrder = (int) ($right['category_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $categories;
}

function sortMenuItemsByCategoryPriority(array $items): array {
    usort($items, static function (array $left, array $right): int {
        $leftPriority = getMenuCategorySortPriority($left['parent_category_name'] ?: $left['category_name'] ?? null);
        $rightPriority = getMenuCategorySortPriority($right['parent_category_name'] ?: $right['category_name'] ?? null);

        if ($leftPriority !== null && $rightPriority !== null && $leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }
        if ($leftPriority !== null && $rightPriority === null) {
            return -1;
        }
        if ($leftPriority === null && $rightPriority !== null) {
            return 1;
        }

        $leftCategory = (string) ($left['parent_category_name'] ?: $left['category_name'] ?? '');
        $rightCategory = (string) ($right['parent_category_name'] ?: $right['category_name'] ?? '');
        $categoryCompare = strcasecmp($leftCategory, $rightCategory);
        if ($categoryCompare !== 0) {
            return $categoryCompare;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return $items;
}

function assertValidCategoryParent(PDO $pdo, int $categoryId, ?int $parentId): void {
    if ($parentId === null || $parentId === 0) {
        return;
    }
    if ($parentId === $categoryId) {
        throw new InvalidArgumentException('A category cannot be its own parent.');
    }

    $seen = [];
    $cursor = $parentId;
    for ($i = 0; $i < 100; $i++) {
        if ($cursor === null || $cursor === 0) {
            return;
        }
        if (isset($seen[$cursor])) {
            throw new InvalidArgumentException('Category hierarchy is invalid (cycle detected).');
        }
        $seen[$cursor] = true;
        if ($cursor === $categoryId) {
            throw new InvalidArgumentException('Circular category parent reference detected.');
        }
        $stmt = $pdo->prepare('SELECT parent_id FROM menu_categories WHERE id = ?');
        $stmt->execute([$cursor]);
        $next = $stmt->fetchColumn();
        if ($next === false) {
            throw new InvalidArgumentException('Parent category does not exist.');
        }
        $cursor = $next !== null ? (int) $next : null;
    }

    throw new InvalidArgumentException('Category hierarchy is too deep.');
}

function getDescendantCategoryIds(PDO $pdo, int $categoryId): array {
    if ($categoryId <= 0) {
        return [];
    }

    $stmt = $pdo->query('SELECT id, parent_id FROM menu_categories');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $childrenByParent = [];
    $exists = false;

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
        if ($id === $categoryId) {
            $exists = true;
        }
        if ($parentId > 0) {
            $childrenByParent[$parentId][] = $id;
        }
    }

    if (!$exists) {
        throw new InvalidArgumentException('Selected category was not found.');
    }

    $result = [];
    $stack = [$categoryId];
    while ($stack) {
        $current = array_pop($stack);
        if (isset($result[$current])) {
            continue;
        }
        $result[$current] = true;
        foreach ($childrenByParent[$current] ?? [] as $childId) {
            $stack[] = (int) $childId;
        }
    }

    return array_map('intval', array_keys($result));
}

function deleteMenuCategoryTree(PDO $pdo, int $categoryId): array {
    $categoryIds = getDescendantCategoryIds($pdo, $categoryId);
    if (!$categoryIds) {
        return ['deleted_categories' => 0, 'deleted_items' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE category_id IN ($placeholders)");
    $stmt->execute($categoryIds);
    $menuItems = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($menuItems) {
        $itemPlaceholders = implode(',', array_fill(0, count($menuItems), '?'));
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE menu_item_id IN ($itemPlaceholders)");
        $stmt->execute($menuItems);
        $stmt = $pdo->prepare("DELETE FROM inventory_logs WHERE menu_item_id IN ($itemPlaceholders)");
        $stmt->execute($menuItems);
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id IN ($itemPlaceholders)");
        $stmt->execute($menuItems);
    }

    $stmt = $pdo->prepare("UPDATE menu_categories SET parent_id = NULL WHERE parent_id IN ($placeholders)");
    $stmt->execute($categoryIds);
    $stmt = $pdo->prepare("DELETE FROM menu_categories WHERE id IN ($placeholders)");
    $stmt->execute($categoryIds);

    return [
        'deleted_categories' => count($categoryIds),
        'deleted_items' => count($menuItems),
    ];
}

function getUserPreference(PDO $pdo, int $userId, string $key, $default = null) {
    $stmt = $pdo->prepare('SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = ?');
    $stmt->execute([$userId, $key]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function setUserPreference(PDO $pdo, int $userId, string $key, ?string $value): void {
    $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)");
    $stmt->execute([$userId, $key, $value]);
}

function analyticsNormalizeDate(?string $date): ?string {
    if ($date === null || trim($date) === '') return null;
    $d = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    return $d;
}

function analyticsDateRange(array $query): array {
    $end = analyticsNormalizeDate($query['end'] ?? null) ?? date('Y-m-d');
    $start = analyticsNormalizeDate($query['start'] ?? null) ?? date('Y-m-d', strtotime('-30 days'));
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }
    return [$start, $end];
}

function analyticsFilters(array $query): array {
    $createdBy = isset($query['created_by']) ? (int) $query['created_by'] : 0;
    $module = isset($query['module']) ? strtolower(trim((string) $query['module'])) : '';
    return ['created_by' => $createdBy > 0 ? $createdBy : null, 'module' => $module !== '' ? $module : null];
}

function analyticsKpis(PDO $pdo, string $startDate, string $endDate, array $filters = []): array {
    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }

    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS order_count,
            COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS net_sales,
            COALESCE(SUM(o.refund_amount), 0) AS refund_total,
            COALESCE(SUM(o.discount_amount), 0) AS discount_total,
            COALESCE(AVG(o.total_amount), 0) AS avg_order
        FROM orders o
        WHERE {$where}");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'order_count' => (int) ($row['order_count'] ?? 0),
        'net_sales' => (float) ($row['net_sales'] ?? 0),
        'refund_total' => (float) ($row['refund_total'] ?? 0),
        'discount_total' => (float) ($row['discount_total'] ?? 0),
        'avg_order' => (float) ($row['avg_order'] ?? 0),
    ];
}

function analyticsSalesSeries(PDO $pdo, string $startDate, string $endDate, array $filters = []): array {
    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }
    $stmt = $pdo->prepare("SELECT DATE(o.created_at) AS day, COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS sales
        FROM orders o
        WHERE {$where}
        GROUP BY DATE(o.created_at)
        ORDER BY DATE(o.created_at)");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function analyticsPaymentMix(PDO $pdo, string $startDate, string $endDate, array $filters = []): array {
    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }
    $stmt = $pdo->prepare("SELECT op.payment_method, COALESCE(SUM(op.amount), 0) AS total
        FROM order_payments op
        JOIN orders o ON o.id = op.order_id
        WHERE {$where}
        GROUP BY op.payment_method
        ORDER BY total DESC");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function analyticsTopItems(PDO $pdo, string $startDate, string $endDate, int $limit = 10, array $filters = []): array {
    $limit = max(1, min(100, $limit));
    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }
    $stmt = $pdo->prepare("SELECT oi.item_name_snapshot AS name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE {$where}
        GROUP BY oi.item_name_snapshot
        ORDER BY revenue DESC
        LIMIT {$limit}");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function analyticsHeatmap(PDO $pdo, string $startDate, string $endDate, array $filters = []): array {
    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }

    $stmt = $pdo->prepare("SELECT DAYOFWEEK(o.created_at) AS dow, HOUR(o.created_at) AS hr, COUNT(*) AS cnt
        FROM orders o
        WHERE {$where}
        GROUP BY DAYOFWEEK(o.created_at), HOUR(o.created_at)");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $grid = [];
    foreach ($rows as $r) {
        $dow = (int) $r['dow'];
        $hr = (int) $r['hr'];
        $grid[$dow][$hr] = (int) $r['cnt'];
    }
    return $grid;
}

function analyticsOrders(PDO $pdo, string $startDate, string $endDate, int $limit = 50, int $offset = 0, array $filters = []): array {
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $params = [$startDate, $endDate];
    $where = "o.payment_status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?";
    if (!empty($filters['created_by'])) {
        $where .= " AND o.created_by = ?";
        $params[] = (int) $filters['created_by'];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT o.id, o.receipt_number, o.total_amount, o.discount_amount, o.refund_amount, o.payment_method, o.created_at,
            COALESCE(c.name, 'Walk-in') AS customer_name,
            COALESCE(u.username, 'System') AS created_by_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN users u ON u.id = o.created_by
        WHERE {$where}
        ORDER BY o.created_at DESC
        LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    return ['total' => $total, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function getOrderDetails(PDO $pdo, int $orderId): array {
    if ($orderId <= 0) {
        throw new InvalidArgumentException('Invalid order id.');
    }

    $stmt = $pdo->prepare("SELECT o.*, COALESCE(c.name, 'Walk-in') AS customer_name, COALESCE(u.username, 'System') AS created_by_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN users u ON u.id = o.created_by
        WHERE o.id = ?
        LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }

    $stmt = $pdo->prepare("SELECT quantity, price, line_total, item_name_snapshot, product_code_snapshot
        FROM order_items
        WHERE order_id = ?
        ORDER BY id ASC");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT payment_method, amount, created_at
        FROM order_payments
        WHERE order_id = ?
        ORDER BY id ASC");
    $stmt->execute([$orderId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT event_type, status, message, payload_json, created_at
        FROM transaction_logs
        WHERE order_id = ?
        ORDER BY id ASC");
    $stmt->execute([$orderId]);
    $tx = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.action, a.details_json
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE a.entity_type = 'order' AND a.entity_id = ?
        ORDER BY a.id ASC");
    $stmt->execute([$orderId]);
    $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'order' => $order,
        'items' => $items,
        'payments' => $payments,
        'transaction_logs' => $tx,
        'audit_logs' => $audit,
    ];
}

function completePendingOrder(PDO $pdo, int $orderId, ?int $completedBy = null): array {
    if ($orderId <= 0) {
        throw new InvalidArgumentException('Invalid order id.');
    }

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, receipt_number, customer_id, total_amount, payment_status, transaction_status
            FROM orders
            WHERE id = ?
            LIMIT 1
            FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new InvalidArgumentException('Order not found.');
        }

        if ((string) $order['payment_status'] === 'completed') {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'order_id' => (int) $order['id'],
                'receipt_number' => (string) $order['receipt_number'],
                'status' => 'completed',
                'already_completed' => true,
                'loyalty_points_earned' => 0,
            ];
        }

        if ((string) $order['payment_status'] !== 'pending') {
            throw new RuntimeException('Only pending orders can be completed.');
        }

        $pdo->prepare("UPDATE orders SET payment_status = 'completed', transaction_status = 'completed' WHERE id = ?")
            ->execute([$orderId]);

        $orderItemStmt = $pdo->prepare('SELECT menu_item_id, quantity, customizations FROM order_items WHERE order_id = ?');
        $orderItemStmt->execute([$orderId]);
        $orderItems = $orderItemStmt->fetchAll(PDO::FETCH_ASSOC);

        $recipeStmt = $pdo->prepare('SELECT ingredient_id, quantity, quantity_unit FROM menu_item_recipes WHERE menu_item_id = ?');
        $ingredientStmt = $pdo->prepare('SELECT id, name, stock_quantity, unit FROM ingredients WHERE id = ? LIMIT 1 FOR UPDATE');
        $updateIngredientStmt = $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE id = ?');
        $logStmt = $pdo->prepare('INSERT INTO inventory_logs (menu_item_id, ingredient_id, action, quantity, reason, performed_by) VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($orderItems as $orderItem) {
            $menuItemId = (int) $orderItem['menu_item_id'];
            $quantity = (float) $orderItem['quantity'];
            $customizations = [];
            if (!empty($orderItem['customizations'])) {
                $decoded = json_decode($orderItem['customizations'], true);
                if (is_array($decoded)) {
                    $customizations = $decoded;
                }
            }
            if ($menuItemId <= 0 || $quantity <= 0) {
                continue;
            }

            $recipeStmt->execute([$menuItemId]);
            $recipeRows = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($recipeRows as $recipeRow) {
                $ingredientId = (int) $recipeRow['ingredient_id'];
                $ingredientQuantity = (float) $recipeRow['quantity'];
                $recipeUnit = isset($recipeRow['quantity_unit']) ? (string) $recipeRow['quantity_unit'] : null;
                if ($ingredientId <= 0 || $ingredientQuantity <= 0) {
                    continue;
                }

                $useIngredient = true;
                foreach ($customizations as $custom) {
                    if (!is_array($custom) || !isset($custom['ingredientId'])) {
                        continue;
                    }
                    if ((int) $custom['ingredientId'] === $ingredientId) {
                        if (empty($custom['include'])) {
                            $useIngredient = false;
                        }
                        break;
                    }
                }

                if (!$useIngredient) {
                    continue;
                }

                $ingredientStmt->execute([$ingredientId]);
                $ingredient = $ingredientStmt->fetch(PDO::FETCH_ASSOC);
                if (!$ingredient) {
                    throw new RuntimeException('Ingredient record not found when completing order.');
                }

                $requiredPerItem = convertIngredientQuantityToStorage($ingredientQuantity, $recipeUnit, (string) $ingredient['unit']);
                $requiredQuantity = round($requiredPerItem * $quantity, 2);
                if ($requiredQuantity <= 0) {
                    continue;
                }
                if ((float) $ingredient['stock_quantity'] + 0.0001 < $requiredQuantity) {
                    throw new RuntimeException('Insufficient stock for ingredient: ' . $ingredient['name'] . '.');
                }

                $updateIngredientStmt->execute([$requiredQuantity, $ingredientId]);
                archiveIngredientIfOutOfStock($pdo, $ingredientId, $completedBy);
                $logStmt->execute([
                    $menuItemId,
                    $ingredientId,
                    'sale',
                    $requiredQuantity,
                    'Order #' . $orderId . ' completed',
                    $completedBy,
                ]);

                // Check if stock is now at/below critical level and auto-create PO
                checkAndGeneratePurchaseOrder($pdo, $ingredientId, $completedBy);
            }
        }

        $discountStmt = $pdo->prepare('SELECT promotion_id FROM order_discounts WHERE order_id = ? AND promotion_id IS NOT NULL');
        $discountStmt->execute([$orderId]);
        $promotionIds = $discountStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($promotionIds) {
            $promoUsageStmt = $pdo->prepare('UPDATE promotions SET usage_count = usage_count + 1 WHERE id = ?');
            foreach ($promotionIds as $promotionId) {
                $promoUsageStmt->execute([(int) $promotionId]);
            }
        }

        $loyaltyPointsEarned = calculateLoyaltyPoints($pdo, (float) $order['total_amount']);
        awardLoyaltyPoints($pdo, isset($order['customer_id']) ? (int) $order['customer_id'] : null, $loyaltyPointsEarned);

        logTransactionEvent($pdo, 'order_completed', 'success', (int) $order['id'], (string) $order['receipt_number'], 'Order marked completed', [
            'completed_by' => $completedBy,
            'loyalty_points_earned' => $loyaltyPointsEarned,
        ]);
        logAuditEvent($pdo, 'order_completed', 'order', (int) $order['id'], [
            'receipt_number' => (string) $order['receipt_number'],
            'completed_by' => $completedBy,
        ]);
        runAutomaticBackup($pdo, 'auto');

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'order_id' => (int) $order['id'],
            'receipt_number' => (string) $order['receipt_number'],
            'status' => 'completed',
            'already_completed' => false,
            'loyalty_points_earned' => $loyaltyPointsEarned,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function cancelPendingOrder(PDO $pdo, int $orderId, ?int $cancelledBy = null): array {
    if ($orderId <= 0) {
        throw new InvalidArgumentException('Invalid order id.');
    }

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, receipt_number, payment_status, transaction_status
            FROM orders
            WHERE id = ?
            LIMIT 1
            FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new InvalidArgumentException('Order not found.');
        }

        if ((string) $order['transaction_status'] === 'cancelled') {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'order_id' => (int) $order['id'],
                'receipt_number' => (string) $order['receipt_number'],
                'status' => 'cancelled',
                'already_cancelled' => true,
            ];
        }

        if ((string) $order['payment_status'] !== 'pending') {
            throw new RuntimeException('Only pending orders can be cancelled.');
        }

        $pdo->prepare("UPDATE orders SET payment_status = 'failed', transaction_status = 'cancelled' WHERE id = ?")
            ->execute([$orderId]);

        logTransactionEvent($pdo, 'order_cancelled', 'success', (int) $order['id'], (string) $order['receipt_number'], 'Order cancelled', [
            'cancelled_by' => $cancelledBy,
        ]);
        logAuditEvent($pdo, 'order_cancelled', 'order', (int) $order['id'], [
            'receipt_number' => (string) $order['receipt_number'],
            'cancelled_by' => $cancelledBy,
        ]);
        runAutomaticBackup($pdo, 'auto');

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'order_id' => (int) $order['id'],
            'receipt_number' => (string) $order['receipt_number'],
            'status' => 'cancelled',
            'already_cancelled' => false,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function requirePermission(PDO $pdo, string $permission, bool $jsonResponse = false): void {
    if (hasPermission($pdo, $permission)) {
        return;
    }

    if ($jsonResponse) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    } else {
        http_response_code(403);
        echo 'Access denied';
    }
    exit;
}

function logAuditEvent(PDO $pdo, string $action, string $entityType, ?int $entityId = null, array $details = []): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $_SESSION['admin'] ?? null,
        $action,
        $entityType,
        $entityId,
        $details ? json_encode($details) : null,
    ]);
}

function logTransactionEvent(PDO $pdo, string $eventType, string $status, ?int $orderId = null, ?string $referenceNumber = null, string $message = '', array $payload = []): void {
    $stmt = $pdo->prepare('INSERT INTO transaction_logs (order_id, reference_number, event_type, status, message, payload_json) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $orderId,
        $referenceNumber,
        $eventType,
        $status,
        $message,
        $payload ? json_encode($payload) : null,
    ]);
}

function getEffectiveMenuItemPrice(array $item): float {
    $basePrice = (float) ($item['price'] ?? 0);
    if (!empty($item['promo_price'])) {
        $now = time();
        $promoStart = !empty($item['promo_start']) ? strtotime($item['promo_start']) : null;
        $promoEnd = !empty($item['promo_end']) ? strtotime($item['promo_end']) : null;
        $startValid = $promoStart === null || $promoStart <= $now;
        $endValid = $promoEnd === null || $promoEnd >= $now;
        if ($startValid && $endValid) {
            return (float) $item['promo_price'];
        }
    }
    return $basePrice;
}

function menuItemHasExpiredIngredient(PDO $pdo, int $menuItemId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_item_recipes mr\n        JOIN ingredients ing ON ing.id = mr.ingredient_id\n        WHERE mr.menu_item_id = ?\n          AND ing.deleted_at IS NULL\n          AND ing.expiration_date IS NOT NULL\n          AND ing.expiration_date < CURDATE()\n    ");
    $stmt->execute([$menuItemId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getMenuItemIdsWithExpiredIngredients(PDO $pdo, array $menuItemIds): array {
    $menuItemIds = array_values(array_filter(array_map('intval', $menuItemIds), static fn($id) => $id > 0));
    if (empty($menuItemIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($menuItemIds), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT mr.menu_item_id FROM menu_item_recipes mr\n        JOIN ingredients ing ON ing.id = mr.ingredient_id\n        WHERE mr.menu_item_id IN ($placeholders)\n          AND ing.deleted_at IS NULL\n          AND ing.expiration_date IS NOT NULL\n          AND ing.expiration_date < CURDATE()\n    ");
    $stmt->execute($menuItemIds);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
}

function menuItemHasInsufficientStock(PDO $pdo, int $menuItemId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id = ?
          AND ing.deleted_at IS NULL
          AND ing.stock_quantity < mr.quantity
          AND (mr.quantity_unit IS NULL OR mr.quantity_unit = '' OR mr.quantity_unit = ing.unit)
    ");
    $stmt->execute([$menuItemId]);
    return (int) $stmt->fetchColumn() > 0;
}

function getMenuItemIdsWithInsufficientStock(PDO $pdo, array $menuItemIds): array {
    $menuItemIds = array_values(array_filter(array_map('intval', $menuItemIds), static fn($id) => $id > 0));
    if (empty($menuItemIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($menuItemIds), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT mr.menu_item_id FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id IN ($placeholders)
          AND ing.deleted_at IS NULL
          AND ing.stock_quantity < mr.quantity
          AND (mr.quantity_unit IS NULL OR mr.quantity_unit = '' OR mr.quantity_unit = ing.unit)
    ");
    $stmt->execute($menuItemIds);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
}

function getMenuItemIdsWithUnavailableIngredients(PDO $pdo, array $menuItemIds): array {
    return array_values(array_unique(array_merge(
        getMenuItemIdsWithExpiredIngredients($pdo, $menuItemIds),
        getMenuItemIdsWithInsufficientStock($pdo, $menuItemIds)
    )));
}

function getMenuItemBlockingIngredients(PDO $pdo, int $menuItemId): array {
    if ($menuItemId <= 0) {
        return ['expired' => [], 'insufficient' => []];
    }

    $expiredStmt = $pdo->prepare("SELECT ing.name
        FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id = ?
          AND ing.deleted_at IS NULL
          AND ing.expiration_date IS NOT NULL
          AND ing.expiration_date < CURDATE()
        ORDER BY ing.name");
    $expiredStmt->execute([$menuItemId]);
    $expired = array_values(array_filter(array_map('strval', $expiredStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));

    $stockStmt = $pdo->prepare("SELECT ing.name, ing.stock_quantity, ing.unit, mr.quantity AS required_quantity, COALESCE(NULLIF(mr.quantity_unit, ''), ing.unit) AS required_unit
        FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id = ?
          AND ing.deleted_at IS NULL
          AND ing.stock_quantity < mr.quantity
          AND (mr.quantity_unit IS NULL OR mr.quantity_unit = '' OR mr.quantity_unit = ing.unit)
        ORDER BY ing.name");
    $stockStmt->execute([$menuItemId]);
    $insufficientRows = $stockStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $insufficient = [];
    foreach ($insufficientRows as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $insufficient[] = sprintf(
            '%s (need %s %s, have %s %s)',
            $name,
            rtrim(rtrim(number_format((float) ($row['required_quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0',
            (string) ($row['required_unit'] ?? ''),
            rtrim(rtrim(number_format((float) ($row['stock_quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0',
            (string) ($row['unit'] ?? '')
        );
    }

    return [
        'expired' => $expired,
        'insufficient' => $insufficient,
    ];
}

function getMenuItemUnavailableDetails(PDO $pdo, array $item): array {
    $itemId = (int) ($item['id'] ?? 0);
    $itemName = trim((string) ($item['name'] ?? 'This item'));
    $manuallyDisabled = empty($item['available']);
    $blockers = getMenuItemBlockingIngredients($pdo, $itemId);
    $expired = $blockers['expired'];
    $insufficient = $blockers['insufficient'];
    $available = !$manuallyDisabled && empty($expired) && empty($insufficient);

    $reasons = [];
    if ($manuallyDisabled) {
        $reasons[] = 'Marked unavailable in Menu Management.';
    }
    if ($expired) {
        $reasons[] = 'Expired ingredient(s): ' . implode(', ', $expired) . '.';
    }
    if ($insufficient) {
        $reasons[] = 'Insufficient stock: ' . implode('; ', $insufficient) . '.';
    }

    $reason = $reasons ? implode(' ', $reasons) : '';
    if (!$available && $reason === '') {
        $reason = 'Unavailable right now.';
    }

    return [
        'available' => $available,
        'reason' => $reason,
        'reasons' => $reasons,
        'expired_ingredients' => $expired,
        'insufficient_ingredients' => $insufficient,
        'item_name' => $itemName,
        'manually_disabled' => $manuallyDisabled,
    ];
}

function applyMenuItemAvailabilityDetails(PDO $pdo, array &$item): array {
    $details = getMenuItemUnavailableDetails($pdo, $item);
    if (!$details['available']) {
        $item['available'] = 0;
        $item['unavailable_reason'] = $details['reason'];
        $item['unavailable_details'] = $details;
    } else {
        $item['available'] = 1;
        unset($item['unavailable_reason'], $item['unavailable_details']);
    }
    return $details;
}

function getUnavailableMenuItemsSnapshot(PDO $pdo, int $limit = 8): array {
    $items = $pdo->query("SELECT id, name, available FROM menu_items ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $unavailable = [];
    foreach ($items as $item) {
        $details = getMenuItemUnavailableDetails($pdo, $item);
        if ($details['available']) {
            continue;
        }
        $unavailable[] = [
            'id' => (int) $item['id'],
            'name' => (string) $item['name'],
            'reason' => $details['reason'],
            'details' => $details,
        ];
    }

    return [
        'total' => count($unavailable),
        'items' => array_slice($unavailable, 0, max(1, $limit)),
    ];
}

function dismissStaffNotification(PDO $pdo, int $userId, string $notificationKey): bool {
    $notificationKey = trim($notificationKey);
    if ($userId <= 0 || $notificationKey === '' || !tableExists($pdo, 'notification_reads')) {
        return false;
    }

    $stmt = $pdo->prepare("INSERT INTO notification_reads (user_id, notification_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE dismissed_at = NOW()");
    $stmt->execute([$userId, $notificationKey]);
    return true;
}

function getStaffNotificationFeed(PDO $pdo, ?int $userId = null, int $limit = 16): array {
    $userId = $userId ?? (isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : 0);
    $role = getCurrentUserRole($pdo);
    $isSupervisor = $role === 'supervisor';
    $limit = max(1, min(30, $limit));
    $items = [];

    $dismissedKeys = [];
    if ($userId > 0 && tableExists($pdo, 'notification_reads')) {
        $stmt = $pdo->prepare('SELECT notification_key FROM notification_reads WHERE user_id = ?');
        $stmt->execute([$userId]);
        $dismissedKeys = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    $prefs = [
        'pending_orders' => true,
        'low_stock' => true,
        'expiring_ingredients' => $isSupervisor,
        'unavailable_items' => true,
        'backup_health' => $isSupervisor,
    ];
    if ($userId > 0) {
        foreach ($prefs as $prefKey => $defaultOn) {
            $saved = getUserPreference($pdo, $userId, 'notifications.' . $prefKey, $defaultOn ? '1' : '0');
            $prefs[$prefKey] = $saved === '1';
        }
        if (!$isSupervisor) {
            $prefs['expiring_ingredients'] = false;
            $prefs['backup_health'] = false;
        }
    }

    try {
        $orderRows = $pdo->query("SELECT o.id, o.receipt_number, o.created_at, o.total_amount, o.payment_status,
                COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in') AS customer_name,
                GROUP_CONCAT(CONCAT(oi.quantity, 'x ', COALESCE(NULLIF(oi.item_name_snapshot, ''), 'Item')) ORDER BY oi.id SEPARATOR ', ') AS items_summary
            FROM orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            GROUP BY o.id, o.receipt_number, o.created_at, o.total_amount, o.payment_status, c.name
            ORDER BY o.created_at DESC
            LIMIT 8")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orderIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $orderRows);
        $orderIds = array_values(array_filter($orderIds));
        $ingredientsByOrder = [];
        if ($orderIds && tableExists($pdo, 'menu_item_recipes')) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $ingStmt = $pdo->prepare("SELECT oi.order_id, ing.name
                FROM order_items oi
                JOIN menu_item_recipes mr ON mr.menu_item_id = oi.menu_item_id
                JOIN ingredients ing ON ing.id = mr.ingredient_id AND ing.deleted_at IS NULL
                WHERE oi.order_id IN ({$placeholders})
                ORDER BY ing.name");
            $ingStmt->execute($orderIds);
            foreach ($ingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ingRow) {
                $oid = (int) ($ingRow['order_id'] ?? 0);
                $name = trim((string) ($ingRow['name'] ?? ''));
                if ($oid <= 0 || $name === '') {
                    continue;
                }
                $ingredientsByOrder[$oid][$name] = $name;
            }
        }

        foreach ($orderRows as $order) {
            $status = (string) ($order['payment_status'] ?? '');
            if (!$prefs['pending_orders'] && $status === 'pending') {
                continue;
            }
            $key = 'order_' . (int) $order['id'];
            if (in_array($key, $dismissedKeys, true)) {
                continue;
            }
            $customer = (string) ($order['customer_name'] ?? 'Walk-in');
            $ordered = (string) ($order['items_summary'] ?: 'No items listed');
            $ingredientNames = array_values($ingredientsByOrder[(int) $order['id']] ?? []);
            $ingredientText = $ingredientNames ? implode(', ', $ingredientNames) : 'No recipe ingredients recorded';
            $receiptNo = trim((string) ($order['receipt_number'] ?? 'POS'));
            $totalText = '₱' . formatMoney((float) ($order['total_amount'] ?? 0));
            $statusLabel = $status === 'pending' ? 'Pending' : ucfirst($status);
            $details = "Receipt: {$receiptNo}\nCustomer: {$customer}\nStatus: {$statusLabel}\nItems: {$ordered}\nIngredients: {$ingredientText}\nTotal: {$totalText}\nTime: " . formatAppDateTime((string) ($order['created_at'] ?? ''));
            $items[] = [
                'key' => $key,
                'kind' => 'order',
                'severity' => $status === 'pending' ? 'warning' : 'info',
                'title' => $status === 'pending'
                    ? ($customer . ' has a pending order')
                    : ($customer . ' placed an order'),
                'preview' => 'Ordered: ' . $ordered . "\nIngredients: " . $ingredientText . "\n" . $totalText,
                'details' => $details,
                'meta' => $receiptNo,
                'time' => (string) ($order['created_at'] ?? ''),
                'href' => 'orders_history.php?focus=' . (int) $order['id'],
            ];
        }
    } catch (Throwable $e) {
        // Ignore if schema differs.
    }

    $lowStockHref = hasPermission($pdo, 'inventory.manage') ? 'inventory.php?panel=reordering&filter=low' : 'pos.php';
    $expiringHref = hasPermission($pdo, 'inventory.manage') ? 'inventory.php?panel=waste&filter=expiring' : 'pos.php';

    if (!empty($prefs['low_stock']) && tableExists($pdo, 'ingredients')) {
        $hasMaxStock = columnExists($pdo, 'ingredients', 'max_stock');
        $lowSql = $hasMaxStock
            ? "SELECT id, name, stock_quantity, unit, COALESCE(NULLIF(max_stock, 0), 100) AS max_stock FROM ingredients
            WHERE deleted_at IS NULL AND (
                stock_quantity < CASE
                    WHEN unit IN ('liters', 'liter') THEN 1
                    WHEN unit IN ('grams', 'gram') THEN 20
                    ELSE 5
                END
                OR stock_quantity <= (GREATEST(COALESCE(NULLIF(max_stock, 0), 100), 1) * 0.05)
            )
            ORDER BY stock_quantity ASC
            LIMIT 8"
            : "SELECT id, name, stock_quantity, unit, 100 AS max_stock FROM ingredients
            WHERE deleted_at IS NULL AND stock_quantity < CASE
                WHEN unit IN ('liters', 'liter') THEN 1
                WHEN unit IN ('grams', 'gram') THEN 20
                ELSE 5
            END
            ORDER BY stock_quantity ASC
            LIMIT 8";
        $lowRows = $pdo->query($lowSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($lowRows as $row) {
            $key = 'low_stock_item_' . (int) $row['id'];
            if (in_array($key, $dismissedKeys, true) || in_array('low_stock', $dismissedKeys, true)) {
                continue;
            }
            $qty = rtrim(rtrim(number_format((float) ($row['stock_quantity'] ?? 0), 2, '.', ''), '0'), '.') ?: '0';
            $maxStock = (float) ($row['max_stock'] ?? 100);
            $level = $maxStock > 0 ? round(((float) ($row['stock_quantity'] ?? 0) / $maxStock) * 100, 0) : 0;
            $preview = sprintf('Only %s %s left (level %s%% of ceiling). This can make related menu items unavailable.', $qty, (string) ($row['unit'] ?? ''), $level);
            $items[] = [
                'key' => $key,
                'kind' => 'low_stock',
                'severity' => 'danger',
                'title' => 'Low stock: ' . (string) ($row['name'] ?? 'Ingredient'),
                'preview' => $preview,
                'details' => "Ingredient: " . (string) ($row['name'] ?? 'Ingredient') . "\nOn hand: {$qty} " . (string) ($row['unit'] ?? '') . "\nCeiling: " . rtrim(rtrim(number_format($maxStock, 2, '.', ''), '0'), '.') . "\nLevel: {$level}%\nAlert: at or below 5% of ceiling, or below the unit threshold.",
                'meta' => 'Inventory',
                'time' => date('Y-m-d H:i:s'),
                'href' => $lowStockHref,
            ];
        }
    }

    if (!empty($prefs['expiring_ingredients']) && tableExists($pdo, 'ingredients')) {
        $expiringRows = $pdo->query("SELECT id, name, expiration_date, DATEDIFF(expiration_date, CURDATE()) AS days_left
            FROM ingredients
            WHERE deleted_at IS NULL
              AND expiration_date IS NOT NULL
              AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY expiration_date ASC
            LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($expiringRows as $row) {
            $key = 'expiring_item_' . (int) $row['id'];
            if (in_array($key, $dismissedKeys, true) || in_array('expiring_ingredients', $dismissedKeys, true)) {
                continue;
            }
            $daysLeft = (int) ($row['days_left'] ?? 0);
            $items[] = [
                'key' => $key,
                'kind' => 'expiring',
                'severity' => $daysLeft < 0 ? 'danger' : 'warning',
                'title' => ($daysLeft < 0 ? 'Expired: ' : 'Expiring soon: ') . (string) ($row['name'] ?? 'Ingredient'),
                'preview' => $daysLeft < 0
                    ? ('Expired on ' . (string) ($row['expiration_date'] ?? ''))
                    : sprintf('Expires in %d day%s (%s). Not the same as low stock.', $daysLeft, $daysLeft === 1 ? '' : 's', (string) ($row['expiration_date'] ?? '')),
                'meta' => 'Waste / Expiry',
                'time' => date('Y-m-d H:i:s'),
                'href' => $expiringHref,
            ];
        }
    }

    if (!empty($prefs['unavailable_items'])) {
        $unavailable = getUnavailableMenuItemsSnapshot($pdo, 6);
        foreach ($unavailable['items'] as $row) {
            $key = 'unavailable_item_' . (int) ($row['id'] ?? 0);
            if (in_array($key, $dismissedKeys, true) || in_array('unavailable_items', $dismissedKeys, true)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'kind' => 'unavailable',
                'severity' => 'danger',
                'title' => 'Cannot sell: ' . (string) ($row['name'] ?? 'Menu item'),
                'preview' => (string) ($row['reason'] ?? 'Cannot be ordered right now.'),
                'meta' => 'POS / Menu',
                'time' => date('Y-m-d H:i:s'),
                'href' => hasPermission($pdo, 'menu.manage') ? 'menu_management.php' : 'pos.php',
            ];
        }
    }

    if (!empty($prefs['backup_health']) && tableExists($pdo, 'backups') && !in_array('backup_health', $dismissedKeys, true)) {
        $latestBackup = $pdo->query("SELECT status, reason, created_at FROM backups ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
        $latestSuccessfulBackup = $pdo->query("SELECT created_at FROM backups WHERE status = 'success' ORDER BY created_at DESC LIMIT 1")->fetchColumn();
        $needsBackupAttention = false;
        $backupTitle = '';
        $backupBody = '';

        if ($latestBackup && (string) ($latestBackup['status'] ?? '') !== 'success') {
            $needsBackupAttention = true;
            $backupTitle = 'Recent backup needs attention.';
            $backupBody = 'The latest backup did not complete successfully.';
        } elseif (!$latestSuccessfulBackup) {
            $needsBackupAttention = true;
            $backupTitle = 'No successful backup recorded yet.';
            $backupBody = 'Create a backup to protect current system data.';
        } elseif (strtotime((string) $latestSuccessfulBackup) < strtotime('-7 days')) {
            $needsBackupAttention = true;
            $backupTitle = 'Backup is older than 7 days.';
            $backupBody = 'Run a new backup to keep recovery points current.';
        }

        if ($needsBackupAttention) {
            $items[] = [
                'key' => 'backup_health',
                'kind' => 'backup',
                'severity' => 'info',
                'title' => $backupTitle,
                'preview' => $backupBody,
                'meta' => 'Database',
                'time' => (string) (($latestBackup['created_at'] ?? '') ?: date('Y-m-d H:i:s')),
                'href' => 'user_settings.php?panel=database',
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });

    $items = array_slice($items, 0, $limit);
    foreach ($items as &$item) {
        if (empty($item['details'])) {
            $item['details'] = trim((string) ($item['title'] ?? '') . "\n\n" . (string) ($item['preview'] ?? ''));
        }
        $item['time'] = formatAppDateTime((string) ($item['time'] ?? ''), (string) ($item['time'] ?? ''));
    }
    unset($item);
    return [
        'count' => count($items),
        'items' => $items,
    ];
}

function getRecentMenuAndStockHistory(PDO $pdo, int $limit = 30): array {
    $limit = max(1, min(100, $limit));
    if (!tableExists($pdo, 'audit_logs')) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT a.id, a.action, a.entity_type, a.entity_id, a.details_json, a.created_at,
            COALESCE(u.username, 'System') AS username
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE (a.entity_type = 'menu_item' AND a.action IN ('menu_item_created', 'menu_item_updated', 'menu_item_deleted', 'menu_item_availability_toggled', 'menu_item_image_updated'))
           OR (a.entity_type = 'ingredient' AND a.action IN ('ingredient_stock_adjusted', 'ingredient_updated', 'ingredient_created', 'ingredient_deleted'))
           OR (a.entity_type = 'order' AND a.action IN ('order_created', 'order_completed', 'order_cancelled'))
        ORDER BY a.id DESC
        LIMIT {$limit}");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $history = [];
    foreach ($rows as $row) {
        $details = [];
        if (!empty($row['details_json'])) {
            $decoded = json_decode((string) $row['details_json'], true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }

        $summary = (string) ($details['name'] ?? ('#' . (string) ($row['entity_id'] ?? '')));
        $action = (string) ($row['action'] ?? '');
        $label = $action;
        if ($action === 'menu_item_created') {
            $label = 'Created menu item';
        } elseif ($action === 'menu_item_updated') {
            $label = 'Edited menu item';
        } elseif ($action === 'menu_item_deleted') {
            $label = 'Deleted menu item';
        } elseif ($action === 'menu_item_availability_toggled') {
            $label = !empty($details['available']) ? 'Marked menu item available' : 'Marked menu item unavailable';
        } elseif ($action === 'menu_item_image_updated') {
            $label = 'Updated menu item image';
        } elseif ($action === 'ingredient_stock_adjusted') {
            $delta = (float) ($details['delta'] ?? 0);
            $label = $delta < 0 ? 'Reduced ingredient stock' : 'Increased ingredient stock';
            $summary .= sprintf(
                ' (%s → %s, %+s)',
                (string) ($details['previous_stock'] ?? '?'),
                (string) ($details['new_stock'] ?? '?'),
                (string) ($details['delta'] ?? '0')
            );
        } elseif ($action === 'ingredient_updated') {
            $label = 'Updated ingredient';
        } elseif ($action === 'ingredient_created') {
            $label = 'Added ingredient';
        } elseif ($action === 'ingredient_deleted') {
            $label = 'Archived/deleted ingredient';
        } elseif ($action === 'order_created') {
            $label = 'Saved pending order';
            $summary = (string) ($details['receipt_number'] ?? $summary);
        } elseif ($action === 'order_completed') {
            $label = 'Completed order';
            $summary = (string) ($details['receipt_number'] ?? $summary);
        } elseif ($action === 'order_cancelled') {
            $label = 'Cancelled order';
            $summary = (string) ($details['receipt_number'] ?? $summary);
        }

        $history[] = [
            'id' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? 'System'),
            'action' => $action,
            'label' => $label,
            'summary' => $summary,
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'created_at' => formatAppDateTime((string) ($row['created_at'] ?? '')),
            'details' => $details,
        ];
    }

    return $history;
}

function logIngredientStockChange(PDO $pdo, int $ingredientId, float $previousStock, float $newStock, string $ingredientName = '', ?int $performedBy = null): void {
    $delta = round($newStock - $previousStock, 2);
    if (abs($delta) < 0.0001) {
        return;
    }

    $performedBy = $performedBy ?? (isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null);
    $action = $delta < 0 ? 'remove' : 'add';
    $quantity = abs($delta);
    $reason = sprintf('Manual stock adjustment (%s → %s)', $previousStock, $newStock);

    if (tableExists($pdo, 'inventory_logs')) {
        $stmt = $pdo->prepare('INSERT INTO inventory_logs (menu_item_id, ingredient_id, action, quantity, reason, performed_by) VALUES (NULL, ?, ?, ?, ?, ?)');
        $stmt->execute([$ingredientId, $action, $quantity, $reason, $performedBy]);
    }

    logAuditEvent($pdo, 'ingredient_stock_adjusted', 'ingredient', $ingredientId, [
        'name' => $ingredientName,
        'previous_stock' => $previousStock,
        'new_stock' => $newStock,
        'delta' => $delta,
    ]);
}

function calculateMenuItemPrice(PDO $pdo, int $menuItemId): float {
    if ($menuItemId <= 0) {
        return 0.0;
    }

    $stmt = $pdo->prepare('SELECT cost_price, admin_cost, markup_percent, manual_price_override, tax_exempt, price FROM menu_items WHERE id = ? LIMIT 1');
    $stmt->execute([$menuItemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return 0.0;
    }

    $recipeStmt = $pdo->prepare('SELECT mr.quantity, mr.quantity_unit, ing.unit_cost, ing.unit AS ing_unit 
        FROM menu_item_recipes mr
        JOIN ingredients ing ON ing.id = mr.ingredient_id
        WHERE mr.menu_item_id = ? AND ing.deleted_at IS NULL');
    $recipeStmt->execute([$menuItemId]);
    $recipeRows = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);

    $computedCostPrice = 0.0;
    foreach ($recipeRows as $row) {
        $qty = (float) ($row['quantity'] ?? 0);
        $recipeUnit = (string) ($row['quantity_unit'] ?? '');
        $ingUnit = (string) ($row['ing_unit'] ?? '');
        $unitCost = (float) ($row['unit_cost'] ?? 0);

        $storageQty = convertIngredientQuantityToStorage($qty, $recipeUnit, $ingUnit);
        $computedCostPrice += round($storageQty * $unitCost, 4);
    }
    $computedCostPrice = round($computedCostPrice, 2);

    $adminCost = (float) ($item['admin_cost'] ?? 0.0);
    $markupPercent = (float) ($item['markup_percent'] ?? 30.0);
    $taxExempt = !empty($item['tax_exempt']);

    $systemTaxRate = 0.0;
    if (!$taxExempt) {
        $taxSetting = getSetting($pdo, 'tax_rate', '0.00');
        $systemTaxRate = (float) $taxSetting;
    }

    // Formula: price = (cost_price + admin_cost) * (1 + tax_rate) * (1 + markup_percent/100)
    $baseWithAdmin = $computedCostPrice + $adminCost;
    $withTax = $baseWithAdmin * (1 + $systemTaxRate);
    $finalComputedPrice = round($withTax * (1 + ($markupPercent / 100)), 2);

    $updateSql = 'UPDATE menu_items SET cost_price = ?';
    $params = [$computedCostPrice];

    if (empty($item['manual_price_override'])) {
        $updateSql .= ', price = ?';
        $params[] = $finalComputedPrice;
    }
    $updateSql .= ' WHERE id = ?';
    $params[] = $menuItemId;

    $pdo->prepare($updateSql)->execute($params);

    return empty($item['manual_price_override']) ? $finalComputedPrice : (float) $item['price'];
}

function recalculateMenuItemsUsingIngredient(PDO $pdo, int $ingredientId): int {
    if ($ingredientId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT DISTINCT menu_item_id FROM menu_item_recipes WHERE ingredient_id = ?');
    $stmt->execute([$ingredientId]);
    $menuItemIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

    $count = 0;
    foreach ($menuItemIds as $menuItemId) {
        calculateMenuItemPrice($pdo, (int) $menuItemId);
        $count++;
    }
    return $count;
}

function getBestSupplierForIngredient(PDO $pdo, int $ingredientId): ?array {
    if ($ingredientId <= 0) {
        return null;
    }

    // 1. Check ingredient_suppliers for lowest cost_per_unit
    $stmt = $pdo->prepare("SELECT s.id AS supplier_id, s.name AS supplier_name, s.contact_email, s.contact_phone, ins.cost_per_unit
        FROM ingredient_suppliers ins
        JOIN suppliers s ON s.id = ins.supplier_id
        WHERE ins.ingredient_id = ?
        ORDER BY ins.cost_per_unit ASC, ins.is_default DESC
        LIMIT 1");
    $stmt->execute([$ingredientId]);
    $best = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($best) {
        return $best;
    }

    // 2. Fallback to supplier_id column on ingredients table
    $stmt = $pdo->prepare("SELECT s.id AS supplier_id, s.name AS supplier_name, s.contact_email, s.contact_phone, ing.unit_cost AS cost_per_unit
        FROM ingredients ing
        JOIN suppliers s ON s.id = ing.supplier_id
        WHERE ing.id = ? LIMIT 1");
    $stmt->execute([$ingredientId]);
    $direct = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($direct) {
        return $direct;
    }

    // 3. Ultimate fallback: return first active supplier
    $stmt = $pdo->query("SELECT id AS supplier_id, name AS supplier_name, contact_email, contact_phone, 0.00 AS cost_per_unit FROM suppliers ORDER BY id ASC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function checkAndGeneratePurchaseOrder(PDO $pdo, int $ingredientId, ?int $userId = null): ?int {
    if ($ingredientId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, name, unit, stock_quantity, max_stock, critical_level, reorder_point, unit_cost FROM ingredients WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$ingredientId]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ingredient) {
        return null;
    }

    $stock = (float) $ingredient['stock_quantity'];
    $critical = (float) ($ingredient['critical_level'] ?? 5.0);

    if ($stock > $critical) {
        return null; // Stock is healthy
    }

    // Prevent duplicate draft/sent POs for the same ingredient
    $dupCheck = $pdo->prepare("SELECT poi.purchase_order_id 
        FROM purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.purchase_order_id
        WHERE poi.ingredient_id = ? AND po.status IN ('draft', 'sent')
        LIMIT 1");
    $dupCheck->execute([$ingredientId]);
    if ($dupCheck->fetchColumn()) {
        return null; // Active PO already exists
    }

    $maxStock = (float) ($ingredient['max_stock'] ?? 100.0);
    $reorderQty = max(1.0, round($maxStock - $stock, 2));

    $supplier = getBestSupplierForIngredient($pdo, $ingredientId);
    if (!$supplier) {
        return null;
    }

    $unitCost = (float) ($supplier['cost_per_unit'] > 0 ? $supplier['cost_per_unit'] : $ingredient['unit_cost']);
    $lineTotal = round($reorderQty * $unitCost, 2);

    $pdo->prepare("INSERT INTO purchase_orders (supplier_id, status, total_amount, created_by, notes) VALUES (?, 'draft', ?, ?, ?)")
        ->execute([$supplier['supplier_id'], $lineTotal, $userId, 'Auto-generated PO: Stock reached critical level (' . $stock . ' ' . $ingredient['unit'] . ')']);
    
    $poId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, ingredient_id, quantity_ordered, unit_cost, line_total) VALUES (?, ?, ?, ?, ?)")
        ->execute([$poId, $ingredientId, $reorderQty, $unitCost, $lineTotal]);

    return $poId;
}

function sendPurchaseOrderEmail(PDO $pdo, int $poId): bool {
    require_once __DIR__ . '/mailer.php';

    $stmt = $pdo->prepare("SELECT po.*, s.name AS supplier_name, s.contact_email, s.contact_phone
        FROM purchase_orders po
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE po.id = ? LIMIT 1");
    $stmt->execute([$poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po || empty($po['contact_email'])) {
        return false;
    }

    $itemsStmt = $pdo->prepare("SELECT poi.*, ing.name AS ingredient_name, ing.unit
        FROM purchase_order_items poi
        JOIN ingredients ing ON ing.id = poi.ingredient_id
        WHERE poi.purchase_order_id = ?");
    $itemsStmt->execute([$poId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= sprintf(
            "<tr><td style='padding:8px;border:1px solid #cbd5e1;'>%s</td><td style='padding:8px;border:1px solid #cbd5e1;text-align:center;'>%s %s</td><td style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>₱%s</td><td style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>₱%s</td></tr>",
            htmlspecialchars((string) $item['ingredient_name']),
            number_format((float) $item['quantity_ordered'], 2),
            htmlspecialchars((string) $item['unit']),
            number_format((float) $item['unit_cost'], 2),
            number_format((float) $item['line_total'], 2)
        );
    }

    $htmlBody = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e2e8f0;padding:24px;border-radius:12px;'>
            <h2 style='color:#2563eb;margin-top:0;'>Kin Café - Purchase Order #PO-{$po['id']}</h2>
            <p>Dear <strong>" . htmlspecialchars((string) $po['supplier_name']) . "</strong>,</p>
            <p>Please accept this official Purchase Order from Kin Café for the following inventory items:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                <thead>
                    <tr style='background:#f8fafc;'>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:left;'>Item</th>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:center;'>Qty</th>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>Unit Cost</th>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>Total</th>
                    </tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
            </table>
            <p style='text-align:right;font-size:1.1rem;'><strong>Total Amount: ₱" . number_format((float) $po['total_amount'], 2) . "</strong></p>
            <hr style='border:none;border-top:1px solid #e2e8f0;'>
            <p style='color:#64748b;font-size:0.85rem;'>Thank you,<br>Kin Café Procurement Management</p>
        </div>";

    $plainBody = "Kin Cafe - Purchase Order #PO-{$po['id']}\nSupplier: {$po['supplier_name']}\nTotal: ₱{$po['total_amount']}\n";

    $subject = "Purchase Order #PO-{$po['id']} - Kin Cafe";
    $result = sendProjectEmail($pdo, [
        'to_email' => (string) $po['contact_email'],
        'to_name' => (string) $po['supplier_name'],
        'subject' => $subject,
        'html_body' => $htmlBody,
        'text_body' => $plainBody,
    ]);

    $sent = !empty($result['success']);

    if ($sent) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'sent' WHERE id = ?")->execute([$poId]);
    }
    return $sent;
}

function isMenuItemCurrentlyAvailable(PDO $pdo, array $item): bool {
    return !empty(getMenuItemUnavailableDetails($pdo, $item)['available']);
}

function menuItemRequiresTemperatureSelection(array $item): bool {
    return !empty($item['temperature_option_enabled']);
}

function menuItemRequiresSizeSelection(array $item): bool {
    return !empty($item['size_option_enabled']);
}

function findCustomerByPhone(PDO $pdo, string $phone): ?array {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findOrCreateCustomer(PDO $pdo, string $name = '', string $phone = '', string $email = ''): ?array {
    $name = trim($name);
    $phone = trim($phone);
    $email = trim($email);

    if ($name === '' && $phone === '' && $email === '') {
        return null;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Customer email is invalid.');
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        throw new InvalidArgumentException('Customer phone number is invalid.');
    }

    if ($phone !== '') {
        $existing = findCustomerByPhone($pdo, $phone);
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE customers SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([
                $name !== '' ? $name : $existing['name'],
                $email !== '' ? $email : $existing['email'],
                $existing['id'],
            ]);
            return findCustomerByPhone($pdo, $phone);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)');
    $stmt->execute([
        $name !== '' ? $name : null,
        $phone !== '' ? $phone : null,
        $email !== '' ? $email : null,
    ]);

    $customerId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getActivePromotion(PDO $pdo, string $code, float $subtotal): ?array {
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE UPPER(code) = ? AND active = 1 AND minimum_order <= ? AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$code, $subtotal]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function calculateLoyaltyPoints(PDO $pdo, float $netAmount): int {
    $rate = (float) getSetting($pdo, 'loyalty_points_per_currency', '0.05');
    return (int) floor($netAmount * $rate);
}

function awardLoyaltyPoints(PDO $pdo, ?int $customerId, int $points): void {
    if (!$customerId || $points <= 0) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?');
    $stmt->execute([$points, $customerId]);
}

function generateReceiptNumber(): string {
    return 'RCPT-' . date('YmdHis') . '-' . random_int(100, 999);
}

function softDeleteIngredient(PDO $pdo, int $ingredientId, ?int $userId = null): void {
    if ($ingredientId <= 0) {
        throw new InvalidArgumentException('Invalid ingredient.');
    }
    if (!tableExists($pdo, 'ingredients')) {
        throw new RuntimeException('Ingredients table is missing.');
    }
    if (!columnExists($pdo, 'ingredients', 'deleted_at')) {
        $pdo->exec("ALTER TABLE ingredients ADD COLUMN deleted_at DATETIME NULL");
    }
    if (!columnExists($pdo, 'ingredients', 'deleted_by')) {
        $pdo->exec("ALTER TABLE ingredients ADD COLUMN deleted_by INT NULL");
    }

    $stmt = $pdo->prepare('SELECT id, deleted_at FROM ingredients WHERE id = ? LIMIT 1');
    $stmt->execute([$ingredientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new InvalidArgumentException('Ingredient not found.');
    }
    if (!empty($row['deleted_at'])) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE ingredients SET deleted_at = NOW(), deleted_by = ? WHERE id = ?');
    $stmt->execute([$userId, $ingredientId]);
    logAuditEvent($pdo, 'ingredient_deleted', 'ingredient', $ingredientId);
}

function archiveIngredientIfOutOfStock(PDO $pdo, int $ingredientId, ?int $userId = null): void {
    if ($ingredientId <= 0) {
        return;
    }
    $stmt = $pdo->prepare('SELECT stock_quantity FROM ingredients WHERE id = ? LIMIT 1');
    $stmt->execute([$ingredientId]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ingredient) {
        return;
    }
    if ((float) $ingredient['stock_quantity'] <= 0) {
        softDeleteIngredient($pdo, $ingredientId, $userId);
    }
}

function getTotalSales(PDO $pdo, ?string $startDate = null, ?string $endDate = null): float {
    $query = "SELECT COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) as total FROM orders WHERE payment_status = 'completed'";
    $params = [];
    if ($startDate) {
        $query .= ' AND DATE(created_at) >= ?';
        $params[] = $startDate;
    }
    if ($endDate) {
        $query .= ' AND DATE(created_at) <= ?';
        $params[] = $endDate;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return (float) ($stmt->fetchColumn() ?: 0);
}

function getTopSellingItems(PDO $pdo, int $limit = 10): array {
    $query = "
        SELECT mi.name, COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.payment_status = 'completed' AND o.transaction_status <> 'refunded'
        GROUP BY mi.id, mi.name
        ORDER BY total_sold DESC
        LIMIT " . (int) $limit;
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getDailySalesHistory(PDO $pdo, int $days = 90): array {
    $days = max(1, $days);
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS sale_date,
            COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS daily_sales
        FROM orders
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC");
    $stmt->execute([$days]);

    $history = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $history[] = [
            'date' => (string) ($row['sale_date'] ?? ''),
            'amount' => round((float) ($row['daily_sales'] ?? 0), 2),
        ];
    }

    return $history;
}

function forecastSalesMovingAverage(PDO $pdo, int $days = 7, int $historyDays = 30): float {
    $historyDays = max(7, $historyDays);
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS sale_date,
            SUM(GREATEST(total_amount - refund_amount, 0)) AS daily_sales
        FROM orders
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date DESC");
    $stmt->execute([$historyDays]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($sales) < 7) {
        return 0.0;
    }

    $total = array_sum(array_map(static function ($row) {
        return (float) $row['daily_sales'];
    }, $sales));

    return round(($total / count($sales)) * $days, 2);
}

function getSalesForecastData(PDO $pdo, int $days = 7): array {
    $days = max(1, $days);
    $history = getDailySalesHistory($pdo, 90);
    $fallbackTotal = forecastSalesMovingAverage($pdo, $days, 30);
    $fallbackDaily = $days > 0 ? round($fallbackTotal / $days, 2) : 0.0;

    $fallback = [
        'forecast_total' => $fallbackTotal,
        'forecast_7day' => array_fill(0, $days, $fallbackDaily),
        'peak_day' => null,
        'confidence_interval' => ['lower' => [], 'upper' => []],
        'method_used' => 'Moving Average',
        'method_label' => formatForecastMethodLabel('moving_average', true),
        'insufficient_data' => true,
        'service_available' => false,
    ];

    if (count($history) < 14) {
        return $fallback;
    }

    if (!function_exists('callForecastService')) {
        require_once __DIR__ . '/forecast_client.php';
    }

    $serviceResponse = callForecastService('sales', $history);
    if ($serviceResponse === null) {
        $fallback['method_label'] = formatForecastMethodLabel('moving_average', false, true);
        return $fallback;
    }

    if (!empty($serviceResponse['insufficient_data'])) {
        return $fallback;
    }

    $forecastValues = [];
    foreach (($serviceResponse['forecast_7day'] ?? []) as $value) {
        $forecastValues[] = round((float) $value, 2);
    }

    if (!$forecastValues) {
        return $fallback;
    }

    if (count($forecastValues) > $days) {
        $forecastValues = array_slice($forecastValues, 0, $days);
    }

    $forecastTotal = round((float) ($serviceResponse['forecast_7day_total'] ?? array_sum($forecastValues)), 2);
    $confidenceInterval = $serviceResponse['confidence_interval'] ?? ['lower' => [], 'upper' => []];

    return [
        'forecast_total' => $forecastTotal,
        'forecast_7day' => $forecastValues,
        'peak_day' => isset($serviceResponse['peak_day']) ? (string) $serviceResponse['peak_day'] : null,
        'confidence_interval' => [
            'lower' => array_map(static function ($value) {
                return round((float) $value, 2);
            }, $confidenceInterval['lower'] ?? []),
            'upper' => array_map(static function ($value) {
                return round((float) $value, 2);
            }, $confidenceInterval['upper'] ?? []),
        ],
        'method_used' => (string) ($serviceResponse['model_used'] ?? 'ARIMA(1,1,1)'),
        'method_label' => formatForecastMethodLabel((string) ($serviceResponse['model_used'] ?? 'ARIMA(1,1,1)')),
        'insufficient_data' => false,
        'service_available' => true,
        'aic' => isset($serviceResponse['aic']) ? (float) $serviceResponse['aic'] : null,
        'days_used' => isset($serviceResponse['days_used']) ? (int) $serviceResponse['days_used'] : count($history),
    ];
}

function forecastSales(PDO $pdo, int $days = 7): float {
    $forecast = getSalesForecastData($pdo, $days);

    return (float) ($forecast['forecast_total'] ?? 0.0);
}

function getSalesSummary(PDO $pdo, string $businessDate): array {
        $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(subtotal_amount), 0) AS gross_sales,
            COALESCE(SUM(discount_amount), 0) AS discount_total,
            COALESCE(SUM(tax_amount), 0) AS tax_total,
            COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS net_sales,
            COALESCE(SUM(refund_amount), 0) AS refund_total,
            COUNT(*) AS order_count
        FROM orders
        WHERE DATE(created_at) = ? AND payment_status = 'completed'");
    $stmt->execute([$businessDate]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $paymentTotal = 0.0;
    if (tableExists($pdo, 'order_payments')) {
        $paymentStmt = $pdo->prepare("SELECT COALESCE(SUM(op.amount), 0) AS total
            FROM order_payments op
            JOIN orders o ON o.id = op.order_id
            WHERE DATE(o.created_at) = ? AND o.payment_status = 'completed'");
        $paymentStmt->execute([$businessDate]);
        $paymentTotal = (float) $paymentStmt->fetchColumn();
    }

    return [
        'gross_sales' => (float) ($summary['gross_sales'] ?? 0),
        'discount_total' => (float) ($summary['discount_total'] ?? 0),
        'tax_total' => (float) ($summary['tax_total'] ?? 0),
        'net_sales' => (float) ($summary['net_sales'] ?? 0),
        'refund_total' => (float) ($summary['refund_total'] ?? 0),
        'order_count' => (int) ($summary['order_count'] ?? 0),
        'cash_total' => $paymentTotal,
    ];
}

function reconcileDailySales(PDO $pdo, string $businessDate, ?int $userId, string $notes = ''): array {
    $summary = getSalesSummary($pdo, $businessDate);
    $stmt = $pdo->prepare("INSERT INTO daily_reconciliations (
            business_date, gross_sales, discount_total, tax_total, refund_total, net_sales,
            cash_total, order_count, reconciled_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            gross_sales = VALUES(gross_sales),
            discount_total = VALUES(discount_total),
            tax_total = VALUES(tax_total),
            refund_total = VALUES(refund_total),
            net_sales = VALUES(net_sales),
            cash_total = VALUES(cash_total),
            order_count = VALUES(order_count),
            reconciled_by = VALUES(reconciled_by),
            notes = VALUES(notes),
            reconciled_at = CURRENT_TIMESTAMP");
    $stmt->execute([
        $businessDate,
        $summary['gross_sales'],
        $summary['discount_total'],
        $summary['tax_total'],
        $summary['refund_total'],
        $summary['net_sales'],
        $summary['cash_total'],
        $summary['order_count'],
        $userId,
        $notes !== '' ? $notes : null,
    ]);
    return $summary;
}

function runAutomaticBackup(PDO $pdo, string $reason = 'auto', bool $force = false): ?array {
    if ((string) getSetting($pdo, 'backup_enabled', '1') !== '1') {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM backups WHERE status = 'success' AND reason = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$reason]);
    $lastBackup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$force && $lastBackup && date('Y-m-d', strtotime($lastBackup['created_at'])) === date('Y-m-d')) {
        return $lastBackup;
    }

    $backupDir = getBackupDirectory();
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Backup directory could not be created.');
    }

    $tables = ['users', 'menu_categories', 'menu_items', 'menu_item_recipes', 'ingredients', 'inventory_logs', 'customers', 'promotions', 'orders', 'order_items', 'order_payments', 'order_discounts', 'suppliers', 'purchase_orders', 'purchase_order_items', 'backups', 'daily_reconciliations', 'settings'];
    $payload = ['generated_at' => date('c'), 'reason' => $reason, 'tables' => []];

    foreach ($tables as $table) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $payload['tables'][$table] = $pdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
    }

    $fileName = 'backup-' . date('Ymd-His') . '.json';
    $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;
    $written = file_put_contents($filePath, json_encode($payload, JSON_PRETTY_PRINT));
    if ($written === false) {
        $stmt = $pdo->prepare('INSERT INTO backups (file_name, file_path, status, reason, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$fileName, $filePath, 'failed', $reason, 'Unable to write backup file.']);
        throw new RuntimeException('Unable to write backup file.');
    }

    $stmt = $pdo->prepare('INSERT INTO backups (file_name, file_path, status, reason, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$fileName, $filePath, 'success', $reason, 'Backup created successfully.']);

    return [
        'file_name' => $fileName,
        'file_path' => $filePath,
        'status' => 'success',
        'reason' => $reason,
    ];
}

function restoreDatabaseFromBackup(PDO $pdo, string $filePath): array {
    if (!file_exists($filePath)) {
        return ['success' => false, 'message' => 'Backup file does not exist.'];
    }

    $raw = @file_get_contents($filePath);
    if (!$raw) {
        return ['success' => false, 'message' => 'Unable to read backup file.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['tables'])) {
        return ['success' => false, 'message' => 'Invalid backup JSON payload.'];
    }

    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

        foreach ($data['tables'] as $table => $rows) {
            if (!tableExists($pdo, $table) || !is_array($rows)) {
                continue;
            }

            $pdo->exec("DELETE FROM `{$table}`");

            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                $escapedCols = array_map(static fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES ({$placeholders})";
                $stmt = $pdo->prepare($sql);

                foreach ($rows as $row) {
                    $stmt->execute(array_values($row));
                }
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        return ['success' => true, 'message' => 'Database successfully restored from backup snapshot.'];
    } catch (Throwable $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        return ['success' => false, 'message' => 'Database restore failed: ' . $e->getMessage()];
    }
}

function getExpiringIngredients(PDO $pdo, int $days = 30): array {
    $stmt = $pdo->prepare("SELECT *, DATEDIFF(expiration_date, CURDATE()) AS days_to_expiry FROM ingredients WHERE deleted_at IS NULL AND expiration_date IS NOT NULL AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY expiration_date ASC");
    $stmt->execute([$days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function checkAndRunScheduledBackup(PDO $pdo): ?array {
    if ((string) getSetting($pdo, 'auto_backup_enabled', '1') !== '1') {
        return null;
    }

    $scheduledTime = getSetting($pdo, 'auto_backup_time', '17:00');
    $currentTime = date('H:i');
    $today = date('Y-m-d');

    if ($currentTime < $scheduledTime) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM backups WHERE reason IN ('scheduled_5pm', 'scheduled_daily') AND DATE(created_at) = ? LIMIT 1");
    $stmt->execute([$today]);
    if ($stmt->fetchColumn()) {
        return null;
    }

    // Avoid retrying a heavy dump on every page navigation when the previous attempt failed.
    $lastAttemptRaw = (string) getSetting($pdo, 'auto_backup_last_attempt', '');
    $lastAttemptTs = $lastAttemptRaw !== '' ? strtotime($lastAttemptRaw) : false;
    if ($lastAttemptTs !== false && (time() - $lastAttemptTs) < 3600) {
        return null;
    }

    $lockPath = getBackupDirectory() . DIRECTORY_SEPARATOR . '.scheduled-backup.lock';
    $lockDir = dirname($lockPath);
    if (!is_dir($lockDir) && !@mkdir($lockDir, 0777, true) && !is_dir($lockDir)) {
        return null;
    }

    $lockHandle = @fopen($lockPath, 'c+');
    if ($lockHandle === false) {
        return null;
    }
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        return null;
    }

    try {
        setSetting($pdo, 'auto_backup_last_attempt', date('c'));
        // Re-check under lock in case another request finished the backup.
        $stmt->execute([$today]);
        if ($stmt->fetchColumn()) {
            return null;
        }
        return runAutomaticBackup($pdo, 'scheduled_5pm', true);
    } catch (Throwable $e) {
        return null;
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function generateSqlDatabaseDump(PDO $pdo): string {
    $tables = ['users', 'menu_categories', 'menu_items', 'menu_item_recipes', 'ingredients', 'inventory_logs', 'customers', 'promotions', 'orders', 'order_items', 'order_payments', 'order_discounts', 'suppliers', 'purchase_orders', 'purchase_order_items', 'backups', 'daily_reconciliations', 'settings'];
    
    $out = "-- Kin Cafe Database Backup Dump\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Host: " . getSetting($pdo, 'database.host', '127.0.0.1') . "\n\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $out .= "-- --------------------------------------------------------\n";
        $out .= "-- Table structure for table `{$table}`\n";
        $out .= "-- --------------------------------------------------------\n";
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
        
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $createSql = $createStmt['Create Table'] ?? '';
        if ($createSql) {
            $out .= $createSql . ";\n\n";
        }

        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $out .= "-- Dumping data for table `{$table}`\n";
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $escapedCols = array_map(static fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);
                $escapedVals = array_map(static function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));

                $out .= "INSERT INTO `" . $table . "` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
            }
            $out .= "\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}
?>
