<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'inventory.manage');

$title = 'Inventory Management';
$errors = [];
$success = '';
$bulkImportMessage = '';
$bulkImportError = '';
$allowedUnits = ['pcs', 'grams', 'liters', 'gram', 'liter', 'kilograms', 'kilogram', 'milliliters', 'milliliter', 'kg', 'ml'];

function normalizeCsvDate(?string $value): ?string {
    if ($value === null) {
        return null;
    }
    $date = trim((string) $value);
    if ($date === '') {
        return null;
    }

    if (preg_match('/^\d{1,4}[\/\-.]\d{1,2}[\/\-.]\d{1,4}$/', $date)) {
        if (strpos($date, '/') !== false) {
            list($first, $second, $year) = explode('/', $date);
            if ((int) $first > 12) {
                $formats = ['j/n/Y', 'j-n-Y', 'j.n.Y'];
            } else {
                $formats = ['n/j/Y', 'n-j-Y', 'n.j.Y', 'j/n/Y', 'j-n-Y', 'j.n.Y'];
            }
        } elseif (strpos($date, '-') !== false) {
            $formats = ['Y-m-d', 'n-j-Y', 'j-n-Y', 'Y-n-j'];
        } elseif (strpos($date, '.') !== false) {
            $formats = ['Y.n.j', 'n.j.Y', 'j.n.Y'];
        } else {
            $formats = ['Y-m-d', 'n/j/Y', 'j/n/Y'];
        }
    } else {
        $formats = ['Y-m-d', 'n/j/Y', 'j/n/Y', 'n-j-Y', 'j-n-Y', 'Y/m/d', 'Y.n.j'];
    }

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $date);
        if ($dt && $dt->format($format) === $date) {
            return $dt->format('Y-m-d');
        }
    }

    $timestamp = strtotime($date);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
}

function isDateInFuture(?string $date): bool {
    if ($date === null || trim((string) $date) === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt) {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }
        $dt = (new DateTime())->setTimestamp($timestamp);
    }
    return $dt->format('Y-m-d') > date('Y-m-d');
}

if (isset($_SESSION['inventory_success'])) {
    $success = $_SESSION['inventory_success'];
    unset($_SESSION['inventory_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_ingredient'])) {
            $name = trim((string) ($_POST['ingredient_name'] ?? ''));
            $unitRaw = trim((string) ($_POST['ingredient_unit'] ?? 'pcs'));
            $stockQuantity = trim((string) ($_POST['stock_quantity'] ?? '0'));
            $maxStock = trim((string) ($_POST['max_stock'] ?? '100'));
            $manufacturingDate = $_POST['manufacturing_date'] ?? null;
            $expirationDate = $_POST['expiration_date'] ?? null;

            if ($name === '') {
                throw new InvalidArgumentException('Ingredient name is required.');
            }
            if ($unitRaw === '') {
                $unitRaw = 'pcs';
            }
            if (!isValidIngredientUnit($unitRaw)) {
                throw new InvalidArgumentException('Unit must be one of pcs, grams, kilograms, milliliters, or liters.');
            }
            $unit = normalizeIngredientUnit($unitRaw);
            if (!is_numeric($stockQuantity) || (float) $stockQuantity < 0) {
                throw new InvalidArgumentException('Quantity must be a valid non-negative number.');
            }
            $stockQuantity = normalizeIngredientQuantityAmount((float) $stockQuantity, $unitRaw);
            $stockQuantity = round($stockQuantity, 2);
            if ($stockQuantity <= 0) {
                throw new InvalidArgumentException('Initial stock quantity must be greater than zero.');
            }

            if (!is_numeric($maxStock) || (float) $maxStock <= 0) {
                throw new InvalidArgumentException('Max stock must be a valid positive number.');
            }
            $maxStock = round((float) $maxStock, 2);

            if (isDateInFuture($manufacturingDate)) {
                throw new InvalidArgumentException('Manufacturing date cannot be in the future.');
            }
            if ($manufacturingDate && $expirationDate && strtotime($expirationDate) <= strtotime($manufacturingDate)) {
                throw new InvalidArgumentException('Expiration date must be after the manufacturing date.');
            }

            $unitCost = trim((string) ($_POST['unit_cost'] ?? '0.00'));
            if (!is_numeric($unitCost) || (float) $unitCost < 0) {
                $unitCost = '0.00';
            }
            $unitCost = round((float) $unitCost, 2);

            $stmt = $pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity, max_stock, unit_cost, manufacturing_date, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $unit !== '' ? $unit : 'pcs', $stockQuantity, $maxStock, $unitCost, $manufacturingDate, $expirationDate]);
            $newIngredientId = (int) $pdo->lastInsertId();
            if ($stockQuantity <= 0) {
                archiveIngredientIfOutOfStock($pdo, $newIngredientId, $_SESSION['admin'] ?? null);
            }
            logAuditEvent($pdo, 'ingredient_created', 'ingredient', $newIngredientId, ['name' => $name, 'stock_quantity' => $stockQuantity, 'max_stock' => $maxStock, 'unit_cost' => $unitCost]);
            $_SESSION['inventory_success'] = 'Ingredient added successfully.';
            header('Location: inventory.php');
            exit;
        } elseif (isset($_POST['bulk_import_ingredients'])) {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Please select a valid CSV file.');
            }

            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if (!$handle) {
                throw new RuntimeException('Unable to open uploaded CSV file.');
            }

            $header = fgetcsv($handle, 1000, ',');
            if (!$header) {
                fclose($handle);
                throw new InvalidArgumentException('CSV file is empty or malformed.');
            }

            $headerMap = [];
            foreach ($header as $index => $column) {
                $headerMap[strtolower(trim((string) $column))] = $index;
            }

            $headerMap = [];
            foreach ($header as $index => $column) {
                $key = strtolower(trim((string) $column));
                $key = preg_replace('/[\s_]+/', ' ', $key);
                $headerMap[$key] = $index;
            }

            $quantityColumn = null;
            if (array_key_exists('quantity', $headerMap)) {
                $quantityColumn = 'quantity';
            } elseif (array_key_exists('quantity on hand', $headerMap)) {
                $quantityColumn = 'quantity on hand';
            }

            $unitColumn = array_key_exists('unit', $headerMap) ? 'unit' : null;
            $packageSizeColumn = array_key_exists('package size', $headerMap) ? 'package size' : (array_key_exists('package amount per unit', $headerMap) ? 'package amount per unit' : null);
            $packageUnitColumn = array_key_exists('package unit', $headerMap) ? 'package unit' : (array_key_exists('package unit of measure', $headerMap) ? 'package unit of measure' : null);

            if (!array_key_exists('name', $headerMap)) {
                fclose($handle);
                throw new InvalidArgumentException('CSV is missing required column: name');
            }
            if ($quantityColumn === null) {
                fclose($handle);
                throw new InvalidArgumentException('CSV is missing required column: quantity or quantity on hand');
            }
            if ($unitColumn === null && $packageSizeColumn === null) {
                fclose($handle);
                throw new InvalidArgumentException('CSV must include either Unit or Package Size / Package Unit columns.');
            }
            if ($packageSizeColumn !== null && $packageUnitColumn === null) {
                fclose($handle);
                throw new InvalidArgumentException('CSV is missing required column: package unit or package unit of measure');
            }

            $pdo->beginTransaction();
            $imported = 0;
            $line = 2;
            $importErrors = [];

            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $name = trim($row[$headerMap['name']] ?? '');
                $quantity = trim($row[$headerMap[$quantityColumn]] ?? '');
                $manufacturingDate = normalizeCsvDate(trim($row[$headerMap['manufacturing date']] ?? ''));
                $expirationDate = normalizeCsvDate(trim($row[$headerMap['expiration date']] ?? ''));

                if ($name === '') {
                    $importErrors[] = "Line $line: Name is required.";
                    $line++;
                    continue;
                }

                if (!is_numeric($quantity) || (float) $quantity < 0) {
                    $importErrors[] = "Line $line: Quantity on hand must be a non-negative number.";
                    $line++;
                    continue;
                }

                if ($packageSizeColumn !== null) {
                    $packageSize = trim($row[$headerMap[$packageSizeColumn]] ?? '');
                    $packageUnit = trim(strtolower((string) ($row[$headerMap[$packageUnitColumn]] ?? '')));

                    if ($packageSize === '' || !is_numeric($packageSize) || (float) $packageSize < 0) {
                        $importErrors[] = "Line $line: Package size must be a non-negative number.";
                        $line++;
                        continue;
                    }
                    if ($packageUnit === '') {
                        $packageUnit = 'pcs';
                    }
                    if (!isValidIngredientUnit($packageUnit)) {
                        $importErrors[] = "Line $line: Package unit must be one of pcs, grams, kilograms, milliliters, or liters.";
                        $line++;
                        continue;
                    }

                    $normalizedPackageUnit = normalizeIngredientUnit($packageUnit);
                    $stockQuantity = normalizeIngredientQuantityAmount((float) $packageSize, $packageUnit) * (float) $quantity;
                    $stockQuantity = round($stockQuantity, 2);
                    $unit = $normalizedPackageUnit;
                } else {
                    $unit = trim(strtolower((string) ($row[$headerMap['unit']] ?? '')));
                    if ($unit === '') {
                        $unit = 'pcs';
                    }
                    if (!isValidIngredientUnit($unit)) {
                        $importErrors[] = "Line $line: Unit must be one of pcs, grams, kilograms, milliliters, or liters.";
                        $line++;
                        continue;
                    }
                    $stockQuantity = normalizeIngredientQuantityAmount((float) $quantity, $unit);
                    $stockQuantity = round($stockQuantity, 2);
                    $unit = normalizeIngredientUnit($unit);
                }

                if ($manufacturingDate !== null && $manufacturingDate !== '' && strtotime($manufacturingDate) === false) {
                    $importErrors[] = "Line $line: Manufacturing date is invalid.";
                    $line++;
                    continue;
                }
                if ($expirationDate !== null && $expirationDate !== '' && strtotime($expirationDate) === false) {
                    $importErrors[] = "Line $line: Expiration date is invalid.";
                    $line++;
                    continue;
                }
                if ($manufacturingDate !== null && $manufacturingDate !== '' && isDateInFuture($manufacturingDate)) {
                    $importErrors[] = "Line $line: Manufacturing date cannot be in the future.";
                    $line++;
                    continue;
                }
                if ($manufacturingDate !== null && $expirationDate !== null && $manufacturingDate !== '' && $expirationDate !== '' && strtotime($expirationDate) <= strtotime($manufacturingDate)) {
                    $importErrors[] = "Line $line: Expiration date must be after manufacturing date.";
                    $line++;
                    continue;
                }

                try {
                    $stmt = $pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $unit, $stockQuantity, $manufacturingDate ?: null, $expirationDate ?: null]);
                    $imported++;
                    if ($stockQuantity <= 0) {
                        archiveIngredientIfOutOfStock($pdo, (int) $pdo->lastInsertId(), $_SESSION['admin'] ?? null);
                    }
                } catch (Throwable $e) {
                    $importErrors[] = "Line $line: " . $e->getMessage();
                }
                $line++;
            }

            fclose($handle);

            if ($importErrors) {
                $pdo->rollBack();
                foreach ($importErrors as $errorLine) {
                    $errors[] = $errorLine;
                }
                throw new InvalidArgumentException('Import completed with errors. See details below.');
            }

            $pdo->commit();
            $_SESSION['inventory_success'] = "Successfully imported $imported ingredients.";
            header('Location: inventory.php');
            exit;
        } elseif (isset($_POST['edit_ingredient'])) {
            $ingredientId = (int) ($_POST['ingredient_id'] ?? 0);
            $name = trim((string) ($_POST['ingredient_name'] ?? ''));
            $unitRaw = trim((string) ($_POST['ingredient_unit'] ?? 'pcs'));
            $stockQuantity = trim((string) ($_POST['stock_quantity'] ?? '0'));
            $maxStock = trim((string) ($_POST['max_stock'] ?? '100'));
            $manufacturingDate = $_POST['manufacturing_date'] ?? null;
            $expirationDate = $_POST['expiration_date'] ?? null;

            if ($ingredientId <= 0) {
                throw new InvalidArgumentException('Invalid ingredient.');
            }
            $existingIngredientStmt = $pdo->prepare('SELECT name, unit, stock_quantity, max_stock, unit_cost, manufacturing_date, expiration_date FROM ingredients WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $existingIngredientStmt->execute([$ingredientId]);
            $existingIngredient = $existingIngredientStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingIngredient) {
                throw new InvalidArgumentException('Ingredient not found.');
            }
            $previousStockQuantity = (float) ($existingIngredient['stock_quantity'] ?? 0);
            if ($name === '') {
                throw new InvalidArgumentException('Ingredient name is required.');
            }
            if ($unitRaw === '') {
                throw new InvalidArgumentException('Unit is required.');
            }
            if (!isValidIngredientUnit($unitRaw)) {
                throw new InvalidArgumentException('Unit must be one of pcs, grams, kilograms, milliliters, or liters.');
            }
            $unit = normalizeIngredientUnit($unitRaw);
            if (!is_numeric($stockQuantity) || (float) $stockQuantity < 0) {
                throw new InvalidArgumentException('Quantity must be a valid non-negative number.');
            }
            $stockQuantity = normalizeIngredientQuantityAmount((float) $stockQuantity, $unitRaw);
            $stockQuantity = round($stockQuantity, 2);

            if (!is_numeric($maxStock) || (float) $maxStock <= 0) {
                throw new InvalidArgumentException('Max stock must be a valid positive number.');
            }
            $maxStock = round((float) $maxStock, 2);

            if (isDateInFuture($manufacturingDate)) {
                throw new InvalidArgumentException('Manufacturing date cannot be in the future.');
            }
            if ($manufacturingDate && $expirationDate && strtotime($expirationDate) <= strtotime($manufacturingDate)) {
                throw new InvalidArgumentException('Expiration date must be after the manufacturing date.');
            }

            $unitCost = trim((string) ($_POST['unit_cost'] ?? '0.00'));
            if (!is_numeric($unitCost) || (float) $unitCost < 0) {
                $unitCost = '0.00';
            }
            $unitCost = round((float) $unitCost, 2);

            $normalizedManufacturingDate = $manufacturingDate ?: null;
            $normalizedExpirationDate = $expirationDate ?: null;
            $existingManufacturingDate = $existingIngredient['manufacturing_date'] ?? null;
            $existingExpirationDate = $existingIngredient['expiration_date'] ?? null;
            $noChanges = trim($name) === trim((string) ($existingIngredient['name'] ?? ''))
                && $unit === normalizeIngredientUnit((string) ($existingIngredient['unit'] ?? 'pcs'))
                && abs($stockQuantity - $previousStockQuantity) < 0.0001
                && abs($maxStock - (float) ($existingIngredient['max_stock'] ?? 0)) < 0.0001
                && abs($unitCost - (float) ($existingIngredient['unit_cost'] ?? 0)) < 0.0001
                && (string) ($normalizedManufacturingDate ?? '') === (string) ($existingManufacturingDate ?? '')
                && (string) ($normalizedExpirationDate ?? '') === (string) ($existingExpirationDate ?? '');
            if ($noChanges) {
                throw new InvalidArgumentException('No stock or ingredient changes were made.');
            }

            $stmt = $pdo->prepare('SELECT id FROM ingredients WHERE id = ? LIMIT 1');
            $stmt->execute([$ingredientId]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Ingredient not found.');
            }

            $stmt = $pdo->prepare('UPDATE ingredients SET name = ?, unit = ?, stock_quantity = ?, max_stock = ?, unit_cost = ?, manufacturing_date = ?, expiration_date = ? WHERE id = ?');
            $stmt->execute([$name, $unit, $stockQuantity, $maxStock, $unitCost, $manufacturingDate ?: null, $expirationDate ?: null, $ingredientId]);
            
            // Recalculate prices for all menu items using this ingredient
            recalculateMenuItemsUsingIngredient($pdo, $ingredientId);

            // Trigger automated PO generation if stock is at/below critical level
            checkAndGeneratePurchaseOrder($pdo, $ingredientId, $_SESSION['admin_id'] ?? null);

            archiveIngredientIfOutOfStock($pdo, $ingredientId, $_SESSION['admin'] ?? null);
            logAuditEvent($pdo, 'ingredient_updated', 'ingredient', $ingredientId, ['name' => $name, 'stock_quantity' => $stockQuantity, 'max_stock' => $maxStock, 'unit_cost' => $unitCost]);
            if (abs($previousStockQuantity - $stockQuantity) > 0.0001) {
                logIngredientStockChange(
                    $pdo,
                    $ingredientId,
                    $previousStockQuantity,
                    $stockQuantity,
                    $name,
                    isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null
                );
            }
            $_SESSION['inventory_success'] = 'Ingredient updated successfully.';
            header('Location: inventory.php');
            exit;
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$lowStockIngredients = $pdo->query("SELECT *, DATEDIFF(expiration_date, CURDATE()) AS days_to_expiry FROM ingredients WHERE deleted_at IS NULL AND stock_quantity < CASE
        WHEN unit IN ('liters', 'liter') THEN 1
        WHEN unit IN ('grams', 'gram') THEN 20
        ELSE 5
    END ORDER BY stock_quantity ASC")->fetchAll(PDO::FETCH_ASSOC);
$expiringIngredients = getExpiringIngredients($pdo, 30);

$logs = $pdo->query("SELECT il.*, mi.name AS item_name, ing.name AS ingredient_name, u.username AS performed_by_name
    FROM inventory_logs il
    LEFT JOIN menu_items mi ON il.menu_item_id = mi.id
    LEFT JOIN ingredients ing ON il.ingredient_id = ing.id
    LEFT JOIN users u ON il.performed_by = u.id
    ORDER BY il.timestamp DESC
    LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

syncAutomatedStockCeilings($pdo);

$ingredients = $pdo->query('SELECT id, name, stock_quantity FROM ingredients WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$allIngredients = $pdo->query('SELECT *, DATEDIFF(expiration_date, CURDATE()) AS days_to_expiry FROM ingredients WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$archivedIngredients = $pdo->query('SELECT *, DATEDIFF(expiration_date, CURDATE()) AS days_to_expiry FROM ingredients WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC')->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/ai_modules.php';
$inventoryOptimization = getAiInventoryOptimizationData($pdo);
$smartReordering = getAiSmartReorderingData($pdo);
$wasteReduction = getAiWasteReductionData($pdo);

function formatStockQuantity($quantity) {
    return rtrim(rtrim(number_format((float) $quantity, 2, '.', ''), '0'), '.');
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content inventory-admin-page">
    <div class="page-hero inventory-hero">
        <div>
            <?php renderPageBackButton('dashboard.php', 'Back to Dashboard'); ?>
            <h1 class="page-title">Inventory Management</h1>
            <p class="page-subtitle">Track ingredients, stock levels, and expiration dates.</p>
        </div>
        <div class="inventory-hero-actions">
            <a class="btn btn-outline-secondary" href="#inventoryActivity">View Activity</a>
            <a class="btn btn-outline-secondary" href="orders_history.php?status=pending">Pending Orders</a>
            <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#selectIngredientModal"<?php echo $allIngredients ? '' : ' disabled'; ?>>Edit Ingredient</button>
            <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#importIngredientsModal">Import Ingredients</button>
            <button type="button" class="btn btn-outline-danger" onclick="clearInventory()"<?php echo $allIngredients ? '' : ' disabled'; ?>>Clear Inventory</button>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addIngredientModal">Add Ingredient</button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="inventory-stat-grid">
        <article class="inventory-stat inventory-stat-danger">
            <div class="inventory-stat-icon">!</div>
            <div>
                <div class="inventory-stat-label">Low Stock</div>
                <div class="inventory-stat-value"><?php echo count($lowStockIngredients); ?> Item<?php echo count($lowStockIngredients) === 1 ? '' : 's'; ?></div>
            </div>
        </article>
        <article class="inventory-stat inventory-stat-warning">
            <div class="inventory-stat-icon">◔</div>
            <div>
                <div class="inventory-stat-label">Expiring Soon</div>
                <div class="inventory-stat-value"><?php echo count($expiringIngredients); ?> Item<?php echo count($expiringIngredients) === 1 ? '' : 's'; ?></div>
            </div>
        </article>
        <article class="inventory-stat inventory-stat-success">
            <div class="inventory-stat-icon">□</div>
            <div>
                <div class="inventory-stat-label">Total Items</div>
                <div class="inventory-stat-value"><?php echo count($allIngredients); ?></div>
            </div>
        </article>
        <article class="inventory-stat inventory-stat-muted">
            <div class="inventory-stat-icon">▾</div>
            <div>
                <div class="inventory-stat-label">Archived</div>
                <div class="inventory-stat-value"><?php echo count($archivedIngredients); ?> Item<?php echo count($archivedIngredients) === 1 ? '' : 's'; ?></div>
            </div>
        </article>
    </div>

    <div class="card inventory-tabs-card">
        <div class="card-body">
            <ul class="nav nav-tabs inventory-feature-tabs" id="inventoryFeatureTabs" role="tablist">
                <li class="nav-item"><button type="button" class="nav-link active" data-inventory-tab="stock" role="tab" aria-selected="true">Stock Management</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-inventory-tab="optimization" role="tab" aria-selected="false">Inventory Optimization</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-inventory-tab="reordering" role="tab" aria-selected="false">Smart Reordering</button></li>
                <li class="nav-item"><button type="button" class="nav-link" data-inventory-tab="waste" role="tab" aria-selected="false">Waste Reduction</button></li>
            </ul>
            <button type="button" class="btn btn-sm btn-outline-secondary kc-inventory-tab-back" id="inventoryTabBack" hidden>Back to Stock</button>
        </div>
    </div>

    <div class="inventory-tab-panel active" data-inventory-panel="stock">

    <div class="card inventory-table-card">
        <div class="inventory-toolbar">
            <div class="inventory-search-wrap">
                <input type="text" id="inventorySearchInput" class="form-control" placeholder="Search ingredients..." data-live-search-target="#inventoryTable tbody tr" data-live-search-filter="off" autocomplete="off">
            </div>
            <div class="inventory-filter-pills">
                <button type="button" class="active" onclick="filterInventoryTable('all', this)">All</button>
                <button type="button" onclick="filterInventoryTable('healthy', this)">Healthy</button>
                <button type="button" onclick="filterInventoryTable('low', this)">Low Stock</button>
                <button type="button" onclick="filterInventoryTable('expiring', this)">Expiring Soon</button>
                <button type="button" onclick="filterInventoryTable('expired', this)">Expired</button>
                <?php if (!empty($archivedIngredients)): ?>
                <button type="button" onclick="toggleArchivedSection()">View Archived (<?php echo count($archivedIngredients); ?>)</button>
                <?php endif; ?>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="analytics_export.php?type=inventory&amp;format=excel">Export Excel</a>
        </div>
        <div class="card-body table-responsive">
            <table class="table inventory-table mb-0" id="inventoryTable">
                <thead>
                    <tr>
                        <th>Ingredient Name</th>
                        <th>Physical Stock</th>
                        <th>Volume / Mass</th>
                        <th>Mfg. Date</th>
                        <th>Exp. Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allIngredients as $ingredient): ?>
                        <?php
                            $daysToExpiry = $ingredient['expiration_date'] ? (int) $ingredient['days_to_expiry'] : null;
                            $stockQuantityNumber = (float) $ingredient['stock_quantity'];
                            $unit = strtolower((string) $ingredient['unit']);
                            $lowStockThreshold = $unit === 'liters' || $unit === 'liter' ? 1 : ($unit === 'grams' || $unit === 'gram' ? 20 : 5);
                            $maxStock = (float) ($ingredient['max_stock'] ?? 100);
                            if ($maxStock <= 0) {
                                $maxStock = 100;
                            }
                            $healthPercent = min(100, max(0, ($stockQuantityNumber / $maxStock) * 100));
                            $statusLabel = 'Healthy';
                            $statusClass = 'healthy';
                            if ($stockQuantityNumber < $lowStockThreshold || $healthPercent <= 5) {
                                $statusLabel = 'Low Stock';
                                $statusClass = 'low';
                            } elseif ($daysToExpiry !== null && $daysToExpiry < 0) {
                                $statusLabel = 'Expired';
                                $statusClass = 'expired';
                            } elseif ($daysToExpiry !== null && $daysToExpiry <= 30) {
                                $statusLabel = 'Expiring';
                                $statusClass = 'expiring';
                            }
                            $rowPulseClass = $statusClass === 'low' ? 'is-low-stock' : ($statusClass === 'expiring' ? 'is-expiring-stock' : ($statusClass === 'expired' ? 'is-expired-stock' : ''));
                        ?>
                        <tr data-status="<?php echo htmlspecialchars(strtolower($statusLabel)); ?>" class="<?php echo $rowPulseClass; ?>">
                            <td data-name="<?php echo htmlspecialchars(strtolower((string) $ingredient['name'])); ?>">
                                <div class="inventory-name-cell">
                                    <span class="inventory-avatar"><?php echo htmlspecialchars(strtoupper(substr((string) $ingredient['name'], 0, 1))); ?></span>
                                    <strong><?php echo htmlspecialchars($ingredient['name']); ?></strong>
                                </div>
                            </td>
                            <td data-stock="<?php echo (float) $ingredient['stock_quantity']; ?>" data-unit="<?php echo htmlspecialchars($ingredient['unit']); ?>">
                                <strong<?php echo $stockQuantityNumber < $lowStockThreshold ? ' class="inventory-stock-low"' : ''; ?>><?php echo htmlspecialchars(formatStockQuantity($ingredient['stock_quantity'])); ?> <?php echo htmlspecialchars($ingredient['unit']); ?></strong>
                            </td>
                            <td>
                                <?php
                                    $maxStock = (float) ($ingredient['max_stock'] ?? 100);
                                    if ($maxStock <= 0) {
                                        $maxStock = 100;
                                    }
                                    $healthPercent = min(100, max(0, ($stockQuantityNumber / $maxStock) * 100));
                                    $healthColor = 'health-green';
                                    if ($healthPercent < 25) {
                                        $healthColor = 'health-red';
                                    } elseif ($healthPercent < 50) {
                                        $healthColor = 'health-orange';
                                    } elseif ($healthPercent < 75) {
                                        $healthColor = 'health-yellow';
                                    }
                                ?>
                                <div class="inventory-volume-summary">
                                    <div class="inventory-volume-value"><?php echo htmlspecialchars(formatStockQuantity($ingredient['stock_quantity'])); ?> <?php echo htmlspecialchars($ingredient['unit']); ?></div>
                                    <div class="inventory-progress">
                                        <div class="inventory-progress-bar <?php echo $healthColor; ?>" style="width: <?php echo $healthPercent; ?>%;"></div>
                                    </div>
                                    <div class="inventory-progress-label">LEVEL <?php echo htmlspecialchars(number_format($healthPercent, 0)); ?>%</div>
                                </div>
                            </td>
                            <td><?php echo $ingredient['manufacturing_date'] ? htmlspecialchars($ingredient['manufacturing_date']) : 'N/A'; ?></td>
                            <td data-expiry="<?php echo $daysToExpiry !== null ? $daysToExpiry : 9999; ?>"><?php echo $ingredient['expiration_date'] ? htmlspecialchars($ingredient['expiration_date']) : 'N/A'; ?></td>
                            <td>
                                <span class="inventory-status-pill is-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                <div class="inventory-status-bar" title="Status strength: <?php echo htmlspecialchars(number_format($healthPercent, 0)); ?>%">
                                    <div class="inventory-status-bar-fill is-<?php echo $statusClass; ?>" style="width: <?php echo $healthPercent; ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <div class="inventory-action-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary inventory-edit-btn" onclick="openEditIngredient(<?php echo (int) $ingredient['id']; ?>)" aria-label="Edit ingredient <?php echo htmlspecialchars($ingredient['name']); ?>">Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" aria-label="Delete ingredient <?php echo htmlspecialchars($ingredient['name']); ?>" onclick="deleteIngredient(<?php echo (int) $ingredient['id']; ?>, this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($archivedIngredients)): ?>
    <div class="card inventory-table-card archived-inventory-card" id="archivedInventorySection" style="display: none;">
        <div class="card-header">
            <h2>Archived Ingredients</h2>
            <p class="text-muted mb-0">Ingredients with no stock remaining are automatically archived.</p>
        </div>
        <div class="card-body table-responsive">
            <table class="table inventory-table mb-0" id="archivedInventoryTable">
                <thead>
                    <tr>
                        <th>Ingredient Name</th>
                        <th>Last Stock</th>
                        <th>Mfg. Date</th>
                        <th>Exp. Date</th>
                        <th>Archived At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archivedIngredients as $ingredient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ingredient['name']); ?></td>
                            <td><?php echo htmlspecialchars(formatStockQuantity($ingredient['stock_quantity'])); ?> <?php echo htmlspecialchars($ingredient['unit']); ?></td>
                            <td><?php echo $ingredient['manufacturing_date'] ? htmlspecialchars($ingredient['manufacturing_date']) : 'N/A'; ?></td>
                            <td><?php echo $ingredient['expiration_date'] ? htmlspecialchars($ingredient['expiration_date']) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($ingredient['deleted_at'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card inventory-activity-card" id="inventoryActivity">
        <div class="dashboard-card-head">
            <h2>Recent Inventory Activity</h2>
            <span class="text-muted small">Shows who added/removed stock (sales, purchases, and manual adjustments)</span>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Action</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>By</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['item_name']): ?>
                                    <?php echo htmlspecialchars($log['item_name']); ?> (Menu)
                                <?php else: ?>
                                    <?php echo htmlspecialchars($log['ingredient_name'] ?: 'Unknown'); ?> (Ingredient)
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($log['reason']); ?></td>
                            <td><?php echo htmlspecialchars($log['performed_by_name'] ?: 'System'); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <div class="inventory-tab-panel" data-inventory-panel="optimization" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Inventory Optimization</h2><a class="btn btn-sm btn-outline-secondary" href="ai_inventory_optimization.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($inventoryOptimization['method_label'] ?? '')); ?></span>
                <ul class="ai-module-list">
                    <?php foreach ($inventoryOptimization['items'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?>: <?php echo htmlspecialchars((string) $row['status']); ?></span><strong><?php echo rtrim(rtrim(number_format((float) $row['target_stock'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?> target</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$inventoryOptimization['items']): ?><li><span>No ingredient optimization data yet.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>

    <div class="inventory-tab-panel" data-inventory-panel="reordering" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Smart Reordering</h2><a class="btn btn-sm btn-outline-secondary" href="ai_inventory_optimization.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($smartReordering['method_label'] ?? '')); ?></span>
                <ul class="ai-module-list">
                    <?php foreach ($smartReordering['items'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?></span><strong><?php echo rtrim(rtrim(number_format((float) $row['recommended_order'], 2), '0'), '.'); ?> <?php echo htmlspecialchars((string) $row['unit']); ?></strong></li>
                    <?php endforeach; ?>
                    <?php if (!$smartReordering['items']): ?><li><span>No reorder candidates right now.</span><strong>--</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>

    <div class="inventory-tab-panel" data-inventory-panel="waste" hidden>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Waste Reduction</h2><a class="btn btn-sm btn-outline-secondary" href="ai_waste_reduction.php">Open module</a></div>
            <div class="card-body ai-module-body">
                <span class="forecast-model-label"><?php echo htmlspecialchars((string) ($wasteReduction['method_label'] ?? '')); ?></span>
                <ul class="ai-module-list">
                    <?php foreach ($wasteReduction['expiring_ingredients'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?> expiring soon</span><strong><?php echo htmlspecialchars((string) ($row['expiration_date'] ?? '')); ?></strong></li>
                    <?php endforeach; ?>
                    <?php foreach ($wasteReduction['slow_moving_items'] as $row): ?>
                        <li><span><?php echo htmlspecialchars((string) $row['name']); ?> slow mover</span><strong><?php echo (int) ($row['total_qty'] ?? 0); ?> sold</strong></li>
                    <?php endforeach; ?>
                    <?php if (!$wasteReduction['expiring_ingredients'] && !$wasteReduction['slow_moving_items']): ?><li><span>No immediate waste risks found.</span><strong>OK</strong></li><?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="selectIngredientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if ($allIngredients): ?>
                    <div class="form-group mb-0">
                        <label for="ingredientPickerSelect">Choose Ingredient</label>
                        <select id="ingredientPickerSelect" class="form-control">
                            <option value="">Select an ingredient</option>
                            <?php foreach ($allIngredients as $ingredient): ?>
                                <option value="<?php echo (int) $ingredient['id']; ?>"><?php echo htmlspecialchars($ingredient['name']); ?><?php echo $ingredient['stock_quantity'] !== null ? ' - ' . htmlspecialchars(formatStockQuantity($ingredient['stock_quantity'])) . ' ' . htmlspecialchars((string) $ingredient['unit']) : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <p class="mb-0 text-muted">No ingredients are available to edit yet.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="openSelectedIngredientEditor()"<?php echo $allIngredients ? '' : ' disabled'; ?>>Open Editor</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addIngredientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add Ingredient</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="ingredient_name" class="form-control" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label>Unit <span class="text-danger">*</span></label>
                        <select name="ingredient_unit" class="form-control" required>
                            <option value="pcs">pcs</option>
                            <option value="grams">grams</option>
                            <option value="kilograms">kilograms</option>
                            <option value="liters">liters</option>
                            <option value="milliliters">milliliters</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="stock_quantity" class="form-control" step="0.01" min="0.01" value="1" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Stock ceiling</label>
                        <input type="number" name="max_stock" class="form-control" step="0.01" min="0.01" value="100" placeholder="Auto" title="Automatically raised when received stock exceeds this ceiling">
                        <small class="form-text text-muted">Raised automatically when stock goes above this level.</small>
                    </div>
                    <div class="form-group">
                        <label>Unit Cost (₱ per unit)</label>
                        <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" value="0.00" placeholder="0.00">
                    </div>
                    <p class="text-muted"><small>Fields marked with * cannot be empty.</small></p>
                    <div class="form-group">
                        <label>Manufacturing Date</label>
                        <input type="date" name="manufacturing_date" class="form-control">
                    </div>
                    <div class="form-group mb-0">
                        <label>Expiration Date</label>
                        <input type="date" name="expiration_date" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_ingredient" class="btn btn-primary">Add Ingredient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="importIngredientsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Import Ingredients</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Upload a CSV file using the following columns:</p>
                    <ul>
                        <li><strong>Name *</strong></li>
                        <li><strong>Package amount per unit *</strong> (Enter the amount inside each stock unit, for example 220 for a 220ml bottle or 500 for a 500g bag.)</li>
                        <li><strong>Package Unit of measure *</strong> (pcs, grams, kilograms, milliliters, or liters)</li>
                        <li><strong>Quantity on hand *</strong> (Number of stock units currently available)</li>
                        <li><strong>Manufacturing Date</strong></li>
                        <li><strong>Expiration Date</strong></li>
                    </ul>
                    <p class="text-muted"><small>Fields marked with * cannot be empty.</small></p>
                    <div class="form-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="downloadIngredientTemplate()">Download sample ingredient CSV</button>
                        <small class="form-text text-muted mt-2">Use the sample format to import units, stock, and dates consistently.</small>
                    </div>
                    <div class="form-group">
                        <label for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" class="form-control-file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_import_ingredients" class="btn btn-primary">Import Ingredients</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editIngredientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" id="editIngredientForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Ingredient</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="editIngredientMessage"></div>
                    <input type="hidden" name="ingredient_id" id="editIngredientId">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="ingredient_name" id="editIngredientName" class="form-control" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="ingredient_unit" id="editIngredientUnit" class="form-control" required>
                            <option value="pcs">pcs</option>
                            <option value="grams">grams</option>
                            <option value="kilograms">kilograms</option>
                            <option value="liters">liters</option>
                            <option value="milliliters">milliliters</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="stock_quantity" id="editIngredientStockQuantity" class="form-control" step="0.01" min="0" value="0" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Stock ceiling</label>
                        <input type="number" name="max_stock" id="editIngredientMaxStock" class="form-control" step="0.01" min="0.01" value="100" placeholder="Auto" title="Automatically raised when received stock exceeds this ceiling">
                        <small class="form-text text-muted">Raised automatically when stock goes above this level.</small>
                    </div>
                    <div class="form-group">
                        <label>Unit Cost (₱ per unit)</label>
                        <input type="number" name="unit_cost" id="editIngredientUnitCost" class="form-control" step="0.01" min="0" value="0.00" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Manufacturing Date</label>
                        <input type="date" name="manufacturing_date" id="editIngredientMfg" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Expiration Date</label>
                        <input type="date" name="expiration_date" id="editIngredientExp" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_ingredient" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSelectedIngredientEditor() {
    const select = document.getElementById('ingredientPickerSelect');
    const ingredientId = Number(select ? select.value : 0);
    if (!ingredientId) {
        window.KinAlertModal.alert('Select an ingredient to edit.', 'Edit Ingredient', 'OK');
        return;
    }

    $('#selectIngredientModal').one('hidden.bs.modal', function () {
        openEditIngredient(ingredientId);
    });
    $('#selectIngredientModal').modal('hide');
}

function openEditIngredient(id) {
    const msg = document.getElementById('editIngredientMessage');
    if (msg) msg.innerHTML = '';
    fetch('get_ingredient.php?id=' + encodeURIComponent(id))
        .then(async (response) => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error(text ? text.slice(0, 200) : 'The server returned an invalid response.');
            }
            if (!response.ok || (data && data.success === false)) {
                throw new Error((data && data.message) ? data.message : 'Unable to load ingredient.');
            }
            return data;
        })
        .then((ingredient) => {
            document.getElementById('editIngredientId').value = ingredient.id;
            document.getElementById('editIngredientName').value = ingredient.name || '';
            const editUnitSelect = document.getElementById('editIngredientUnit');
            if (editUnitSelect) {
                const selectedUnit = ingredient.unit || 'pcs';
                if (![...editUnitSelect.options].some((option) => option.value === selectedUnit)) {
                    const customOption = document.createElement('option');
                    customOption.value = selectedUnit;
                    customOption.textContent = selectedUnit;
                    editUnitSelect.appendChild(customOption);
                }
                editUnitSelect.value = selectedUnit;
            }
            const editQuantityInput = document.getElementById('editIngredientStockQuantity');
            if (editQuantityInput) {
                editQuantityInput.value = ingredient.stock_quantity ?? '0';
            }
            const editMaxStockInput = document.getElementById('editIngredientMaxStock');
            if (editMaxStockInput) {
                editMaxStockInput.value = ingredient.max_stock ?? '100';
            }
            const editUnitCostInput = document.getElementById('editIngredientUnitCost');
            if (editUnitCostInput) {
                editUnitCostInput.value = ingredient.unit_cost ?? '0.00';
            }
            document.getElementById('editIngredientMfg').value = ingredient.manufacturing_date || '';
            document.getElementById('editIngredientExp').value = ingredient.expiration_date || '';
            $('#editIngredientModal').modal('show');
        })
        .catch((err) => {
            const msg = 'Edit failed: ' + (err.message || 'Unable to open edit form.');
            window.KinAlertModal.alert(msg, 'Error', 'OK');
        });
}

function deleteIngredient(id, btn) {
    const proceed = () => {
        const originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Deleting...';
        }
        const fd = new FormData();
        fd.append('id', String(id));
        fetch('delete_ingredient.php', { method: 'POST', body: fd })
            .then(async (response) => {
                const text = await response.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error(text ? text.slice(0, 200) : 'Invalid response'); }
                if (!response.ok || (data && data.success === false)) throw new Error((data && data.message) ? data.message : 'Delete failed.');
                return data;
            })
            .then(() => {
                location.reload();
            })
            .catch((err) => {
                const msg = 'Delete failed: ' + (err.message || 'Unable to delete ingredient.');
                window.KinAlertModal.alert(msg, 'Error', 'OK');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText || 'Delete';
                }
            });
    };

    window.KinAlertModal.confirm('Delete this ingredient?', 'Confirm Delete', 'Delete', 'Cancel').then((ok) => {
        if (ok) proceed();
    });
}

function clearInventory() {
    window.KinAlertModal.confirm(
        'Clear all inventory items? This will archive every active ingredient and cannot be undone.',
        'Clear Inventory',
        'Clear',
        'Cancel'
    ).then((ok) => {
        if (!ok) {
            return;
        }

        const btn = document.activeElement;
        const originalText = btn ? btn.textContent : '';
        if (btn && btn.tagName === 'BUTTON') {
            btn.disabled = true;
            btn.textContent = 'Clearing...';
        }

        const fd = new FormData();
        fetch('clear_inventory.php', { method: 'POST', body: fd })
            .then(async (response) => {
                const text = await response.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error(text ? text.slice(0, 200) : 'Invalid response'); }
                if (!response.ok || (data && data.success === false)) throw new Error((data && data.message) ? data.message : 'Clear inventory failed.');
                return data;
            })
            .then(() => {
                location.reload();
            })
            .catch((err) => {
                const msg = 'Clear inventory failed: ' + (err.message || 'Unable to clear inventory.');
                window.KinAlertModal.alert(msg, 'Error', 'OK');
                if (btn && btn.tagName === 'BUTTON') {
                    btn.disabled = false;
                    btn.textContent = originalText || 'Clear Inventory';
                }
            });
    });
}

let activeInventoryFilter = 'all';

function filterInventoryTable(filter, button) {
    activeInventoryFilter = String(filter || 'all');
    document.querySelectorAll('.inventory-filter-pills button').forEach((pill) => pill.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    }
    applyInventoryFilters();
}

function applyInventoryFilters() {
    const search = String((document.getElementById('inventorySearchInput') || {}).value || '').trim().toLowerCase();
    document.querySelectorAll('#inventoryTable tbody tr').forEach((row) => {
        const name = String((row.querySelector('[data-name]') || {}).getAttribute?.('data-name') || row.textContent || '').toLowerCase();
        const stock = Number((row.querySelector('[data-stock]') || {}).getAttribute?.('data-stock') || 0);
        const unit = String((row.querySelector('[data-unit]') || {}).getAttribute?.('data-unit') || '').toLowerCase();
        const expiry = Number((row.querySelector('[data-expiry]') || {}).getAttribute?.('data-expiry') || 9999);
        const lowThreshold = unit === 'liters' || unit === 'liter' ? 1 : (unit === 'grams' || unit === 'gram' ? 20 : 5);
        const searchMatch = !search || name.includes(search);
        const status = String((row.getAttribute('data-status') || '')).toLowerCase();
        const filterMatch = activeInventoryFilter === 'all'
            || (activeInventoryFilter === 'healthy' && status === 'healthy')
            || (activeInventoryFilter === 'low' && status === 'low stock')
            || (activeInventoryFilter === 'expiring' && status === 'expiring')
            || (activeInventoryFilter === 'expired' && status === 'expired');
        row.style.display = searchMatch && filterMatch ? '' : 'none';
    });
}

function downloadIngredientTemplate() {
    const csv = [
        ['Name', 'Package amount per unit', 'Package Unit of measure', 'Quantity on hand', 'Manufacturing Date', 'Expiration Date'],
        ['Chicken Stock', '220', 'milliliters', '10', '2026-04-01', '2026-07-01'],
        ['Rice', '1000', 'grams', '20', '2026-04-10', '2027-04-10'],
        ['Oil Bottle', '500', 'milliliters', '15', '2026-03-05', '2027-03-05'],
    ];
    const content = csv.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'ingredient_template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function toggleArchivedSection() {
    const archivedSection = document.getElementById('archivedInventorySection');
    if (archivedSection) {
        const isHidden = archivedSection.style.display === 'none' || archivedSection.style.display === '';
        archivedSection.style.display = isHidden ? 'block' : 'none';
    }
}

function initInventoryFeatureTabs() {
    const tabButtons = document.querySelectorAll('[data-inventory-tab]');
    const panels = document.querySelectorAll('[data-inventory-panel]');

    function activateInventoryTab(target) {
        if (!target) {
            return;
        }
        tabButtons.forEach((item) => {
            const isActive = item.getAttribute('data-inventory-tab') === target;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-inventory-panel') === target;
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });
        const back = document.getElementById('inventoryTabBack');
        if (back) {
            back.hidden = target === 'stock';
        }
    }

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activateInventoryTab(button.getAttribute('data-inventory-tab'));
        });
    });

    const params = new URLSearchParams(window.location.search);
    const requestedTab = params.get('panel') || params.get('inv') || params.get('tab');
    const tabAliasMap = {
        'smart-reordering': 'reordering',
        'inventory-optimization': 'optimization',
        'waste-reduction': 'waste',
    };
    const resolvedTab = tabAliasMap[requestedTab] || requestedTab;
    const knownTabs = ['stock', 'optimization', 'reordering', 'waste'];
    if (knownTabs.indexOf(resolvedTab) !== -1) {
        activateInventoryTab(resolvedTab);
    }

    const back = document.getElementById('inventoryTabBack');
    if (back) {
        back.addEventListener('click', function () {
            activateInventoryTab('stock');
        });
    }

    const requestedFilter = params.get('filter');
    if (requestedFilter) {
        const filterButton = document.querySelector(`.inventory-filter-pills button[onclick*="'${requestedFilter}'"]`);
        if (typeof filterInventoryTable === 'function') {
            filterInventoryTable(requestedFilter, filterButton || null);
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initInventoryFeatureTabs();
    const input = document.getElementById('inventorySearchInput');
    if (input) {
        input.addEventListener('input', applyInventoryFilters);
    }
    applyInventoryFilters();
});
</script>

<?php include 'includes/footer.php'; ?>
