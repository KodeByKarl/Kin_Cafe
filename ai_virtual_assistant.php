<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_modules.php';

requirePermission($pdo, 'pos.access');

$title = 'Virtual Assistant';
$canViewAiSuite = hasPermission($pdo, 'reports.view');
$canManageMenu = hasPermission($pdo, 'menu.manage');
$canManageInventory = hasPermission($pdo, 'inventory.manage');
$feature = getAiVirtualAssistantData($pdo);
$assistantQuestion = '';
$assistantResponse = null;
$assistantError = '';
$bulkImportMessage = '';
$bulkImportError = '';
$bulkInventoryMessage = '';
$bulkInventoryError = '';

function parseMenuImportIngredients(string $ingredientList): array {
    $result = [];
    $parts = preg_split('/[;|]/', $ingredientList);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^\s*(.+?)\s*:\s*([\d\.]+)\s*([^\d\s].*)?$/u', $part, $matches)) {
            $name = trim($matches[1]);
            $quantity = (float) $matches[2];
            $unit = isset($matches[3]) ? trim($matches[3]) : '';
            if ($name !== '' && $quantity > 0) {
                $result[] = [
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit' => $unit !== '' ? $unit : 'pcs',
                ];
            }
        }
    }
    return $result;
}

function persistMenuItemRecipe(PDO $pdo, int $menuItemId, array $ingredientIds, array $quantities, array $quantityUnits = []): void {
    $deleteStmt = $pdo->prepare('DELETE FROM menu_item_recipes WHERE menu_item_id = ?');
    $deleteStmt->execute([$menuItemId]);
    $insertRecipeStmt = $pdo->prepare('INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (?, ?, ?, ?)');
    foreach ($ingredientIds as $index => $ingredientId) {
        $ingredientId = (int) $ingredientId;
        $quantity = isset($quantities[$index]) ? trim((string) $quantities[$index]) : '';
        $quantityUnit = isset($quantityUnits[$index]) ? trim((string) $quantityUnits[$index]) : '';
        if ($ingredientId <= 0 || $quantity === '' || !is_numeric($quantity) || (float) $quantity <= 0) {
            continue;
        }
        $insertRecipeStmt->execute([$menuItemId, $ingredientId, round((float) $quantity, 2), $quantityUnit !== '' ? $quantityUnit : null]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'ask_virtual_assistant') {
    $assistantQuestion = trim((string) ($_POST['assistant_question'] ?? ''));
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!csrfValidate($token, 'ask_virtual_assistant')) {
        $assistantError = 'Security validation failed. Refresh the page and try again.';
    } else {
        $assistantResponse = askAiVirtualAssistant($pdo, $assistantQuestion);
        if (empty($assistantResponse['success'])) {
            $assistantError = (string) ($assistantResponse['message'] ?? 'The assistant could not process your request.');
            $assistantResponse = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'bulk_import_menu' && $canManageMenu) {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!csrfValidate($token, 'bulk_import_menu')) {
        $bulkImportError = 'Security validation failed. Refresh the page and try again.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $bulkImportError = 'Please select a valid CSV file.';
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            $bulkImportError = 'Failed to read the uploaded file.';
        } else {
            $pdo->beginTransaction();
            $imported = 0;
            $errors = [];
            $line = 1;
            $categories = [];
            $catStmt = $pdo->prepare("SELECT id, name FROM menu_categories");
            $catStmt->execute();
            foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
                $categories[strtolower($cat['name'])] = $cat['id'];
            }

            $ingredientMap = [];
            $ingredientStmt = $pdo->prepare('SELECT id, name, unit FROM ingredients');
            $ingredientStmt->execute();
            foreach ($ingredientStmt->fetchAll(PDO::FETCH_ASSOC) as $ingredient) {
                $ingredientMap[strtolower($ingredient['name'])] = [
                    'id' => $ingredient['id'],
                    'unit' => $ingredient['unit'],
                ];
            }
            $insertIngredientStmt = $pdo->prepare('INSERT INTO ingredients (name, unit, stock_quantity) VALUES (?, ?, 0)');

            $headerMap = null;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if ($line === 1) {
                    $normalizedHeader = array_map(function ($value) {
                        return strtolower(trim((string) $value));
                    }, $data);
                    if (in_array('name', $normalizedHeader, true) && in_array('category', $normalizedHeader, true) && in_array('price', $normalizedHeader, true) && in_array('available', $normalizedHeader, true)) {
                        $headerMap = [];
                        foreach ($normalizedHeader as $index => $columnName) {
                            $headerMap[$columnName] = $index;
                        }
                        $line++;
                        continue;
                    }
                }

                $name = trim($headerMap !== null ? ($data[$headerMap['name']] ?? '') : ($data[0] ?? ''));
                $categoryName = trim($headerMap !== null ? ($data[$headerMap['category']] ?? '') : ($data[1] ?? ''));
                $price = (float) ($headerMap !== null ? ($data[$headerMap['price']] ?? 0) : ($data[2] ?? 0));
                $available = strtolower(trim($headerMap !== null ? ($data[$headerMap['available']] ?? 'yes') : ($data[3] ?? 'yes'))) === 'yes' ? 1 : 0;
                $ingredientsRaw = '';
                if ($headerMap !== null && isset($headerMap['ingredients'])) {
                    $ingredientsRaw = trim($data[$headerMap['ingredients']] ?? '');
                } elseif (count($data) > 4) {
                    $ingredientsRaw = trim($data[4]);
                }

                if ($name === '' || $price <= 0) {
                    $errors[] = "Line $line: Invalid name or price.";
                    $line++;
                    continue;
                }

                $categoryId = null;
                if ($categoryName !== '') {
                    $catKey = strtolower($categoryName);
                    if (!isset($categories[$catKey])) {
                        $insertCat = $pdo->prepare("INSERT INTO menu_categories (name) VALUES (?)");
                        $insertCat->execute([$categoryName]);
                        $categoryId = $pdo->lastInsertId();
                        $categories[$catKey] = $categoryId;
                    } else {
                        $categoryId = $categories[$catKey];
                    }
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO menu_items (name, price, category_id, available) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $price, $categoryId, $available]);
                    $menuItemId = (int) $pdo->lastInsertId();

                    if ($ingredientsRaw !== '') {
                        $recipeEntries = parseMenuImportIngredients($ingredientsRaw);
                        $recipeIngredientIds = [];
                        $recipeQuantities = [];
                        $recipeQuantityUnits = [];
                        foreach ($recipeEntries as $entry) {
                            $ingredientKey = strtolower($entry['name']);
                            if (!isset($ingredientMap[$ingredientKey])) {
                                $insertIngredientStmt->execute([$entry['name'], $entry['unit']]);
                                $ingredientId = (int) $pdo->lastInsertId();
                                $ingredientMap[$ingredientKey] = ['id' => $ingredientId, 'unit' => $entry['unit']];
                            } else {
                                $ingredientId = $ingredientMap[$ingredientKey]['id'];
                            }
                            $recipeIngredientIds[] = $ingredientId;
                            $recipeQuantities[] = $entry['quantity'];
                            $recipeQuantityUnits[] = $entry['unit'];
                        }
                        if ($recipeIngredientIds) {
                            persistMenuItemRecipe($pdo, $menuItemId, $recipeIngredientIds, $recipeQuantities, $recipeQuantityUnits);
                        }
                    }

                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Line $line: " . $e->getMessage();
                }
                $line++;
            }
            fclose($handle);

            if (empty($errors)) {
                $pdo->commit();
                $bulkImportMessage = "Successfully imported $imported menu items.";
            } else {
                $pdo->rollBack();
                $bulkImportError = "Import failed. Errors: " . implode('; ', $errors);
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'bulk_import_inventory' && $canManageInventory) {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!csrfValidate($token, 'bulk_import_inventory')) {
        $bulkInventoryError = 'Security validation failed. Refresh the page and try again.';
    } elseif (!isset($_FILES['csv_file_inventory']) || $_FILES['csv_file_inventory']['error'] !== UPLOAD_ERR_OK) {
        $bulkInventoryError = 'Please select a valid CSV file.';
    } else {
        $file = $_FILES['csv_file_inventory']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            $bulkInventoryError = 'Failed to read the uploaded file.';
        } else {
            $pdo->beginTransaction();
            $imported = 0;
            $errors = [];
            $line = 1;

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if ($line === 1) { // Skip header
                    $line++;
                    continue;
                }

                $name = trim($data[0] ?? '');
                $secondColumn = trim((string) ($data[1] ?? ''));
                $thirdColumn = trim((string) ($data[2] ?? ''));
                $fourthColumn = trim((string) ($data[3] ?? ''));
                $fifthColumn = trim((string) ($data[4] ?? ''));

                $packageMode = false;
                $unit = '';
                $stockQuantity = 0.0;

                if (strtolower($secondColumn) === 'unit' || strtolower($secondColumn) === 'pcs' || strtolower($secondColumn) === 'grams' || strtolower($secondColumn) === 'liters' || strtolower($secondColumn) === 'kilograms' || strtolower($secondColumn) === 'milliliters') {
                    // simple import format: Name, Unit, Quantity, Manufacturing Date, Expiration Date
                    $unit = trim($secondColumn);
                    $quantity = trim($thirdColumn);
                    $manufacturingDate = $fourthColumn;
                    $expirationDate = $fifthColumn;

                    if ($unit === '') {
                        $unit = 'pcs';
                    }
                    if (!isValidIngredientUnit($unit)) {
                        $errors[] = "Line $line: Unit must be one of pcs, grams, kilograms, milliliters, or liters.";
                        $line++;
                        continue;
                    }
                    if (!is_numeric($quantity) || (float) $quantity < 0) {
                        $errors[] = "Line $line: Quantity must be a non-negative number.";
                        $line++;
                        continue;
                    }
                    $stockQuantity = normalizeIngredientQuantityAmount((float) $quantity, $unit);
                    $unit = normalizeIngredientUnit($unit);
                } else {
                    // package import format: Name, Package Size, Package Unit, Quantity on hand, Manufacturing Date, Expiration Date
                    $packageSize = $secondColumn;
                    $packageUnit = $thirdColumn;
                    $quantityOnHand = $fourthColumn;
                    $manufacturingDate = $fifthColumn;
                    $expirationDate = trim((string) ($data[5] ?? ''));

                    if ($packageSize === '' || !is_numeric($packageSize) || (float) $packageSize < 0) {
                        $errors[] = "Line $line: Package size must be a non-negative number.";
                        $line++;
                        continue;
                    }
                    if ($packageUnit === '') {
                        $packageUnit = 'pcs';
                    }
                    if (!isValidIngredientUnit($packageUnit)) {
                        $errors[] = "Line $line: Package unit must be one of pcs, grams, kilograms, milliliters, or liters.";
                        $line++;
                        continue;
                    }
                    if (!is_numeric($quantityOnHand) || (float) $quantityOnHand < 0) {
                        $errors[] = "Line $line: Quantity on hand must be a non-negative number.";
                        $line++;
                        continue;
                    }
                    $unit = normalizeIngredientUnit($packageUnit);
                    $stockQuantity = normalizeIngredientQuantityAmount((float) $packageSize, $packageUnit) * (float) $quantityOnHand;
                }

                $manufDate = null;
                if ($manufacturingDate !== '') {
                    $manufDate = date('Y-m-d', strtotime($manufacturingDate));
                    if ($manufDate === '1970-01-01') $manufDate = null;
                }
                $expDate = null;
                if ($expirationDate !== '') {
                    $expDate = date('Y-m-d', strtotime($expirationDate));
                    if ($expDate === '1970-01-01') $expDate = null;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO ingredients (name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $unit, $stockQuantity, $manufDate, $expDate]);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Line $line: " . $e->getMessage();
                }
                $line++;
            }
            fclose($handle);

            if (empty($errors)) {
                $pdo->commit();
                $bulkInventoryMessage = "Successfully imported $imported ingredients.";
            } else {
                $pdo->rollBack();
                $bulkInventoryError = "Import failed. Errors: " . implode('; ', $errors);
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content analytics-admin-page ai-module-page">
    <div class="page-hero analytics-hero">
        <div>
            <h1 class="page-title">Virtual Assistant Feature</h1>
            <p class="page-subtitle">A dedicated assistant surface for inquiries, menu help, operational answers, and order guidance.</p>
        </div>
        <div class="analytics-hero-actions">
            <span class="analytics-badge"><?php echo $feature['provider_ready'] ? htmlspecialchars($feature['provider'] . ' / ' . $feature['model']) : 'Local fallback active'; ?></span>
            <?php if ($canViewAiSuite): ?>
            <a class="btn btn-outline-secondary" href="ai_insights.php">AI Suite</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="pos.php">POS</a>
        </div>
    </div>

    <div class="ai-detail-grid">
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Ask the Assistant</h2><span class="analytics-badge"><?php echo $feature['provider_ready'] ? 'External AI' : 'Fallback mode'; ?></span></div>
            <div class="card-body ai-module-body">
                <p class="ai-module-summary">Ask about forecasts, low stock, demand, order pressure, or anomaly review. The assistant uses the configured provider when available and falls back to internal business rules when it is not.</p>
                <?php if ($assistantError): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($assistantError); ?></div>
                <?php endif; ?>
                <?php if ($assistantResponse && !empty($assistantResponse['notice'])): ?>
                    <div class="alert alert-warning mb-0"><?php echo htmlspecialchars((string) $assistantResponse['notice']); ?></div>
                <?php endif; ?>
                <form method="post" class="ai-assistant-form">
                    <input type="hidden" name="action" value="ask_virtual_assistant">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('ask_virtual_assistant')); ?>">
                    <div class="form-group mb-0">
                        <label for="assistant_question">Assistant Question</label>
                        <textarea class="form-control" id="assistant_question" name="assistant_question" rows="4" placeholder="Example: What needs immediate attention before the next rush?" required><?php echo htmlspecialchars($assistantQuestion); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Ask Assistant</button>
                </form>

                <?php if ($assistantResponse): ?>
                    <div class="ai-answer-card">
                        <span class="ai-answer-label">Latest Response</span>
                        <strong><?php echo htmlspecialchars((string) $assistantResponse['answer']); ?></strong>
                        <div class="text-muted mt-2">Source: <?php echo htmlspecialchars((string) $assistantResponse['provider']); ?> | Mode: <?php echo htmlspecialchars((string) $assistantResponse['source']); ?><?php if (!empty($assistantResponse['model'])): ?> | Model: <?php echo htmlspecialchars((string) $assistantResponse['model']); ?><?php endif; ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Assistant Prompt Library</h2><span class="analytics-badge">Service help</span></div>
            <div class="card-body ai-module-body">
                <div class="ai-answer-stack">
                    <?php foreach ($feature['prompts'] as $row): ?>
                        <div class="ai-answer-card"><span class="ai-answer-label"><?php echo htmlspecialchars((string) $row['question']); ?></span><strong><?php echo htmlspecialchars((string) $row['answer']); ?></strong></div>
                    <?php endforeach; ?>
                    <?php if (!$feature['prompts']): ?><div class="ai-answer-card"><span class="ai-answer-label">Assistant</span><strong>No assistant prompts are available yet.</strong></div><?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($canManageMenu): ?>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Bulk Import Menu Items</h2><span class="analytics-badge">CSV Upload</span></div>
            <div class="card-body ai-module-body">
                <p class="ai-module-summary">Upload a CSV file to bulk import menu items. CSV format: Name, Category, Price, Available (yes/no), Ingredients.</p>
                <p class="ai-module-summary">Ingredients should be entered as <code>IngredientName:Quantity Unit</code>, separated by semicolons, e.g. <code>Chicken:700 grams; Rice:200 grams; Sauce:50 ml</code>.</p>
                <?php if ($bulkImportError): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($bulkImportError); ?></div>
                <?php endif; ?>
                <?php if ($bulkImportMessage): ?>
                    <div class="alert alert-success mb-0"><?php echo htmlspecialchars($bulkImportMessage); ?></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="ai-assistant-form">
                    <input type="hidden" name="action" value="bulk_import_menu">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('bulk_import_menu')); ?>">
                    <div class="form-group mb-0">
                        <label for="csv_file">CSV File</label>
                        <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import Menu Items</button>
                </form>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($canManageInventory): ?>
        <section class="card ai-module-card">
            <div class="dashboard-card-head"><h2>Bulk Import Ingredients</h2><span class="analytics-badge">CSV Upload</span></div>
            <div class="card-body ai-module-body">
                <p class="ai-module-summary">Upload a CSV file using the following columns: Name, Package amount per unit, Package Unit of measure, Quantity on hand, Manufacturing Date, Expiration Date.</p>
                <p class="ai-module-summary">Use pcs, grams, kilograms, milliliters, or liters for package units. Package amount per unit is the amount inside each stock unit, e.g. 220 for a 220ml bottle or 500 for a 500g bag. Fields marked with * cannot be empty.</p>
                <?php if ($bulkInventoryError): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($bulkInventoryError); ?></div>
                <?php endif; ?>
                <?php if ($bulkInventoryMessage): ?>
                    <div class="alert alert-success mb-0"><?php echo htmlspecialchars($bulkInventoryMessage); ?></div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="ai-assistant-form">
                    <input type="hidden" name="action" value="bulk_import_inventory">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken('bulk_import_inventory')); ?>">
                    <div class="form-group mb-0">
                        <label for="csv_file_inventory">CSV File</label>
                        <input type="file" class="form-control-file" id="csv_file_inventory" name="csv_file_inventory" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import Ingredients</button>
                </form>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>