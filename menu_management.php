<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'menu.manage');

// Ensure required columns exist
try {
    $result = $pdo->query("SHOW COLUMNS FROM menu_categories LIKE 'parent_id'")->fetch();
    if (!$result) {
        $pdo->exec("ALTER TABLE menu_categories ADD COLUMN parent_id INT NULL");
    }
} catch (Exception $e) {
    // Log and continue, fallback logic handles missing columns where needed
    error_log('menu_management schema check error: ' . $e->getMessage());
}

$title = 'Menu Management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $expectsJson = isset($_POST['add_item']) || isset($_POST['toggle_available']) || isset($_POST['update_item_image']);
    if ($expectsJson) {
        header('Content-Type: application/json');
    }
    try {
    function uploadImage($fieldName) {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!isset($_FILES[$fieldName]['size']) || (int) $_FILES[$fieldName]['size'] <= 0) {
            return null;
        }
        if ((int) $_FILES[$fieldName]['size'] > 3 * 1024 * 1024) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES[$fieldName]['tmp_name']);
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($map[$mime])) {
            return null;
        }
        $filename = uniqid('menu_', true) . '.' . $map[$mime];
        $dest = __DIR__ . '/assets/images/' . $filename;
        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dest)) {
            return $filename;
        }
        return null;
    }

    function parseMenuImportIngredients(string $ingredientList): array {
        $result = [];
        $parts = preg_split('/[;\|]/', $ingredientList);
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
        $pdo->prepare('DELETE FROM menu_item_recipes WHERE menu_item_id = ?')->execute([$menuItemId]);
        $insertRecipeStmt = $pdo->prepare('INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (?, ?, ?, ?)');
        foreach ($ingredientIds as $index => $ingredientId) {
            $ingredientId = (int) $ingredientId;
            $quantity = isset($quantities[$index]) ? trim((string) $quantities[$index]) : '';
            $quantityUnit = isset($quantityUnits[$index]) ? trim((string) $quantityUnits[$index]) : '';
            if ($ingredientId <= 0 || $quantity === '') {
                continue;
            }
            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                continue;
            }
            $insertRecipeStmt->execute([$menuItemId, $ingredientId, round((float) $quantity, 2), $quantityUnit !== '' ? $quantityUnit : null]);
        }
    }

    if (isset($_POST['add_category'])) {
        $name = trim((string) ($_POST['category_name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }
        $stmt = $pdo->prepare("INSERT INTO menu_categories (name, description, parent_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $_POST['category_description'] ?? null, null]);
    } elseif (isset($_POST['edit_category'])) {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['category_name'] ?? ''));
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Select a category to edit.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }
        $stmt = $pdo->prepare("UPDATE menu_categories SET name=?, description=?, parent_id=? WHERE id=?");
        $stmt->execute([$name, $_POST['category_description'] ?? null, null, $categoryId]);
    } elseif (isset($_POST['delete_category'])) {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Select a category to delete.');
        }
        $pdo->beginTransaction();
        $deleted = deleteMenuCategoryTree($pdo, $categoryId);
        $pdo->commit();
        if (!$expectsJson) {
            $_SESSION['menu_success'] = sprintf('Deleted %d categor%s and %d item%s.', $deleted['deleted_categories'], $deleted['deleted_categories'] === 1 ? 'y' : 'ies', $deleted['deleted_items'], $deleted['deleted_items'] === 1 ? '' : 's');
        }
    } elseif (isset($_POST['bulk_import_menu'])) {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Please select a valid CSV file.');
        }
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open uploaded CSV file.');
        }

        $pdo->beginTransaction();
        $imported = 0;
        $errors = [];
        $line = 1;
        $categories = [];
        $catStmt = $pdo->query('SELECT id, name FROM menu_categories');
        foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
            $categories[strtolower($cat['name'])] = $cat['id'];
        }

        $ingredientsIndex = [];
        $ingredientMap = [];
        $ingredientStmt = $pdo->query('SELECT id, name, unit FROM ingredients');
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
                $categoryKey = strtolower($categoryName);
                if (!isset($categories[$categoryKey])) {
                    $insertCat = $pdo->prepare('INSERT INTO menu_categories (name) VALUES (?)');
                    $insertCat->execute([$categoryName]);
                    $categoryId = $pdo->lastInsertId();
                    $categories[$categoryKey] = $categoryId;
                } else {
                    $categoryId = $categories[$categoryKey];
                }
            }

            try {
                $stmt = $pdo->prepare('INSERT INTO menu_items (name, description, price, price_solo, price_sharing, price_hot, price_iced, size_option_enabled, size_label_1, size_label_2, price_size_1, price_size_2, category_id, image, available, temperature_option_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, null, $price, null, null, null, null, 0, null, null, null, null, $categoryId, null, $available, 0]);
                $imported++;
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
            } catch (Exception $e) {
                $errors[] = "Line $line: " . $e->getMessage();
            }
            $line++;
        }
        fclose($handle);

        if ($errors) {
            $pdo->rollBack();
            $_SESSION['menu_errors'] = $errors;
        } else {
            $pdo->commit();
            $_SESSION['menu_success'] = "Imported $imported menu items.";
        }
    } elseif (isset($_POST['add_item'])) {
        $imageName = uploadImage('image');
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Item name is required.']);
            exit;
        }
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Category is required.']);
            exit;
        }
        $pricingModel = strtolower(trim((string) ($_POST['pricing_model'] ?? 'single')));
        $priceSolo = null;
        $priceSharing = null;
        $priceHot = null;
        $priceIced = null;
        $sizeOptionEnabled = 0;
        $sizeLabel1 = null;
        $sizeLabel2 = null;
        $priceSize1 = null;
        $priceSize2 = null;
        $price = 0.0;
        $temperatureOptionEnabled = 0;

        if ($pricingModel === 'size') {
            $sizeLabel1 = trim((string) ($_POST['size_label_1'] ?? ''));
            $sizeLabel2 = trim((string) ($_POST['size_label_2'] ?? ''));
            $priceSize1 = ($_POST['price_size_1'] ?? '') !== '' ? (float) $_POST['price_size_1'] : null;
            $priceSize2 = ($_POST['price_size_2'] ?? '') !== '' ? (float) $_POST['price_size_2'] : null;
            $hasFirst = $sizeLabel1 !== '' && $priceSize1 !== null && $priceSize1 > 0;
            $hasSecond = $sizeLabel2 !== '' && $priceSize2 !== null && $priceSize2 > 0;
            if (!$hasFirst && !$hasSecond) {
                echo json_encode(['success' => false, 'message' => 'Provide at least one valid size label and price.']);
                exit;
            }
            $price = (float) ($hasFirst ? $priceSize1 : $priceSize2);
            $sizeOptionEnabled = 1;
            $priceSolo = null;
            $priceSharing = null;
            $priceHot = null;
            $priceIced = null;
        } elseif ($pricingModel === 'temperature') {
            $priceHot = ($_POST['price_hot'] ?? '') !== '' ? (float) $_POST['price_hot'] : null;
            $priceIced = ($_POST['price_iced'] ?? '') !== '' ? (float) $_POST['price_iced'] : null;
            if (($priceHot === null || $priceHot <= 0) && ($priceIced === null || $priceIced <= 0)) {
                echo json_encode(['success' => false, 'message' => 'Provide at least one valid Hot or Iced price.']);
                exit;
            }
            $price = (float) (($priceHot && $priceHot > 0) ? $priceHot : ($priceIced ?? 0));
            $priceSolo = null;
            $priceSharing = null;
            $temperatureOptionEnabled = 1;
            $sizeOptionEnabled = 0;
        } elseif ($pricingModel === 'dual') {
            $priceSolo = ($_POST['price_solo'] ?? '') !== '' ? (float) $_POST['price_solo'] : null;
            $priceSharing = ($_POST['price_sharing'] ?? '') !== '' ? (float) $_POST['price_sharing'] : null;
            if (($priceSolo === null || $priceSolo <= 0) && ($priceSharing === null || $priceSharing <= 0)) {
                echo json_encode(['success' => false, 'message' => 'Provide at least one valid Solo or Sharing price.']);
                exit;
            }
            $price = (float) (($priceSolo && $priceSolo > 0) ? $priceSolo : ($priceSharing ?? 0));
        } else {
            $price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
            if ($price <= 0) {
                echo json_encode(['success' => false, 'message' => 'Price must be greater than zero.']);
                exit;
            }
            $priceSolo = null;
            $priceSharing = null;
            $priceHot = null;
            $priceIced = null;
            $sizeOptionEnabled = 0;
        }

        $adminCost = (float) ($_POST['admin_cost'] ?? 0.0);
        $markupPercent = (float) ($_POST['markup_percent'] ?? 30.0);
        $manualPriceOverride = !empty($_POST['manual_price_override']) ? 1 : 0;

        $availVal = parseAvailableValue($_POST['available'] ?? null, 1);
        $stmt = $pdo->prepare("INSERT INTO menu_items (name, description, price, price_solo, price_sharing, price_hot, price_iced, size_option_enabled, size_label_1, size_label_2, price_size_1, price_size_2, category_id, image, available, temperature_option_enabled, admin_cost, markup_percent, manual_price_override) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, null, $price, $priceSolo, $priceSharing, $priceHot, $priceIced, $sizeOptionEnabled, $sizeLabel1 ?: null, $sizeLabel2 ?: null, $priceSize1, $priceSize2, $categoryId, $imageName, $availVal, $temperatureOptionEnabled, $adminCost, $markupPercent, $manualPriceOverride]);
        $newItemId = $pdo->lastInsertId();
        persistMenuItemRecipe($pdo, (int) $newItemId, (array) ($_POST['recipe_ingredient_id'] ?? []), (array) ($_POST['recipe_quantity'] ?? []), (array) ($_POST['recipe_quantity_unit'] ?? []));
        
        // Compute recipe cost_price & calculated selling price
        calculateMenuItemPrice($pdo, (int) $newItemId);

        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$newItemId]);
        $newItem = $stmt->fetch(PDO::FETCH_ASSOC);
        logAuditEvent($pdo, 'menu_item_created', 'menu_item', (int) $newItemId, [
            'name' => $name,
            'category_id' => $categoryId,
            'price' => $price,
            'available' => $availVal,
        ]);
        echo json_encode(['success' => true, 'item' => $newItem]);
        exit;
    } elseif (isset($_POST['edit_item'])) {
        $imageName = uploadImage('image');
        $itemId = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($itemId <= 0) {
            throw new InvalidArgumentException('Invalid menu item.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Item name is required.');
        }
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            throw new InvalidArgumentException('Category is required.');
        }

        $pricingModel = strtolower(trim((string) ($_POST['pricing_model'] ?? 'single')));
        $priceSolo = null;
        $priceSharing = null;
        $priceHot = null;
        $priceIced = null;
        $sizeOptionEnabled = 0;
        $sizeLabel1 = null;
        $sizeLabel2 = null;
        $priceSize1 = null;
        $priceSize2 = null;
        $price = 0.0;
        $temperatureOptionEnabled = 0;
        $adminCost = (float) ($_POST['admin_cost'] ?? 0.0);
        $markupPercent = (float) ($_POST['markup_percent'] ?? 30.0);
        $manualPriceOverride = !empty($_POST['manual_price_override']) ? 1 : 0;

        if ($pricingModel === 'size') {
            $sizeLabel1 = trim((string) ($_POST['size_label_1'] ?? ''));
            $sizeLabel2 = trim((string) ($_POST['size_label_2'] ?? ''));
            $priceSize1 = ($_POST['price_size_1'] ?? '') !== '' ? (float) $_POST['price_size_1'] : null;
            $priceSize2 = ($_POST['price_size_2'] ?? '') !== '' ? (float) $_POST['price_size_2'] : null;
            $hasFirst = $sizeLabel1 !== '' && $priceSize1 !== null && $priceSize1 > 0;
            $hasSecond = $sizeLabel2 !== '' && $priceSize2 !== null && $priceSize2 > 0;
            if (!$hasFirst && !$hasSecond) {
                throw new InvalidArgumentException('Provide at least one valid size label and price.');
            }
            $price = (float) ($hasFirst ? $priceSize1 : $priceSize2);
            $sizeOptionEnabled = 1;
            $priceSolo = null;
            $priceSharing = null;
            $priceHot = null;
            $priceIced = null;
        } elseif ($pricingModel === 'temperature') {
            $priceHot = ($_POST['price_hot'] ?? '') !== '' ? (float) $_POST['price_hot'] : null;
            $priceIced = ($_POST['price_iced'] ?? '') !== '' ? (float) $_POST['price_iced'] : null;
            if (($priceHot === null || $priceHot <= 0) && ($priceIced === null || $priceIced <= 0)) {
                throw new InvalidArgumentException('Provide at least one valid Hot or Iced price.');
            }
            $price = (float) (($priceHot && $priceHot > 0) ? $priceHot : ($priceIced ?? 0));
            $priceSolo = null;
            $priceSharing = null;
            $temperatureOptionEnabled = 1;
            $sizeOptionEnabled = 0;
        } elseif ($pricingModel === 'dual') {
            $priceSolo = ($_POST['price_solo'] ?? '') !== '' ? (float) $_POST['price_solo'] : null;
            $priceSharing = ($_POST['price_sharing'] ?? '') !== '' ? (float) $_POST['price_sharing'] : null;
            if (($priceSolo === null || $priceSolo <= 0) && ($priceSharing === null || $priceSharing <= 0)) {
                throw new InvalidArgumentException('Provide at least one valid Solo or Sharing price.');
            }
            $price = (float) (($priceSolo && $priceSolo > 0) ? $priceSolo : ($priceSharing ?? 0));
        } else {
            $price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
            $priceSolo = null;
            $priceSharing = null;
            $priceHot = null;
            $priceIced = null;
            $sizeOptionEnabled = 0;
        }

        if ($imageName) {
            $availVal = parseAvailableValue($_POST['available'] ?? null, 1);
            $stmt = $pdo->prepare("UPDATE menu_items SET name=?, description=?, price=?, price_solo=?, price_sharing=?, price_hot=?, price_iced=?, size_option_enabled=?, size_label_1=?, size_label_2=?, price_size_1=?, price_size_2=?, category_id=?, available=?, image=?, temperature_option_enabled=?, admin_cost=?, markup_percent=?, manual_price_override=? WHERE id=?");
            $stmt->execute([$name, null, $price, $priceSolo, $priceSharing, $priceHot, $priceIced, $sizeOptionEnabled, $sizeLabel1 ?: null, $sizeLabel2 ?: null, $priceSize1, $priceSize2, $categoryId, $availVal, $imageName, $temperatureOptionEnabled, $adminCost, $markupPercent, $manualPriceOverride, $itemId]);
        } else {
            $availVal = parseAvailableValue($_POST['available'] ?? null, 1);
            $stmt = $pdo->prepare("UPDATE menu_items SET name=?, description=?, price=?, price_solo=?, price_sharing=?, price_hot=?, price_iced=?, size_option_enabled=?, size_label_1=?, size_label_2=?, price_size_1=?, price_size_2=?, category_id=?, available=?, temperature_option_enabled=?, admin_cost=?, markup_percent=?, manual_price_override=? WHERE id=?");
            $stmt->execute([$name, null, $price, $priceSolo, $priceSharing, $priceHot, $priceIced, $sizeOptionEnabled, $sizeLabel1 ?: null, $sizeLabel2 ?: null, $priceSize1, $priceSize2, $categoryId, $availVal, $temperatureOptionEnabled, $adminCost, $markupPercent, $manualPriceOverride, $itemId]);
        }
        persistMenuItemRecipe($pdo, $itemId, (array) ($_POST['recipe_ingredient_id'] ?? []), (array) ($_POST['recipe_quantity'] ?? []), (array) ($_POST['recipe_quantity_unit'] ?? []));
        
        // Recalculate price dynamically
        calculateMenuItemPrice($pdo, $itemId);
        logAuditEvent($pdo, 'menu_item_updated', 'menu_item', $itemId, [
            'name' => $name,
            'category_id' => $categoryId,
            'price' => $price,
            'available' => $availVal,
        ]);
        $_SESSION['menu_success'] = 'Menu item updated successfully.';
        header('Location: menu_management.php');
        exit;
    } elseif (isset($_POST['update_item_image'])) {
        $itemId = (int) ($_POST['id'] ?? 0);
        if ($itemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid menu item.']);
            exit;
        }
        $imageName = uploadImage('image');
        if (!$imageName) {
            echo json_encode(['success' => false, 'message' => 'Invalid image. Use JPG, PNG, or WebP up to 3MB.']);
            exit;
        }
        $stmt = $pdo->prepare('SELECT image FROM menu_items WHERE id = ? LIMIT 1');
        $stmt->execute([$itemId]);
        $old = $stmt->fetchColumn();
        if ($old === false) {
            echo json_encode(['success' => false, 'message' => 'Item not found.']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE menu_items SET image = ? WHERE id = ?');
        $stmt->execute([$imageName, $itemId]);
        if ($old) {
            $path = __DIR__ . '/assets/images/' . (string) $old;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        logAuditEvent($pdo, 'menu_item_image_updated', 'menu_item', $itemId, [
            'name' => null,
            'image' => $imageName,
        ]);
        echo json_encode(['success' => true, 'image' => $imageName, 'url' => 'assets/images/' . rawurlencode($imageName)]);
        exit;
    } elseif (isset($_POST['toggle_available'])) {
        $itemId = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT name, available FROM menu_items WHERE id = ? LIMIT 1');
        $stmt->execute([$itemId]);
        $currentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentRow) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            exit;
        }
        $new = !empty($currentRow['available']) ? 0 : 1;
        $pdo->prepare('UPDATE menu_items SET available = ? WHERE id = ?')->execute([$new, $itemId]);
        logAuditEvent($pdo, 'menu_item_availability_toggled', 'menu_item', $itemId, [
            'name' => (string) ($currentRow['name'] ?? ''),
            'available' => (bool) $new,
        ]);
        echo json_encode(['success' => true, 'available' => (bool) $new]);
        exit;
    } elseif (isset($_POST['delete_item'])) {
        $deleteItemId = (int) ($_POST['id'] ?? 0);
        $nameStmt = $pdo->prepare('SELECT name FROM menu_items WHERE id = ? LIMIT 1');
        $nameStmt->execute([$deleteItemId]);
        $deletedName = (string) ($nameStmt->fetchColumn() ?: '');

        // Remove dependent order items first to satisfy foreign key constraints
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE menu_item_id = ?");
        $stmt->execute([$deleteItemId]);

        // Remove associated inventory logs for this menu item
        $stmt = $pdo->prepare("DELETE FROM inventory_logs WHERE menu_item_id = ?");
        $stmt->execute([$deleteItemId]);

        // Then remove the menu item
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id=?");
        $stmt->execute([$deleteItemId]);
        logAuditEvent($pdo, 'menu_item_deleted', 'menu_item', $deleteItemId, [
            'name' => $deletedName !== '' ? $deletedName : ('#' . $deleteItemId),
        ]);
    } elseif (isset($_POST['clear_menu'])) {
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM menu_item_recipes');
        $pdo->exec('DELETE FROM order_items');
        $pdo->exec('DELETE FROM inventory_logs WHERE menu_item_id IS NOT NULL');
        $pdo->exec('DELETE FROM menu_items');
        $pdo->exec('DELETE FROM menu_categories');
        $pdo->commit();
        $_SESSION['menu_success'] = 'All menu items, categories, and recipe links have been removed.';
    } elseif (isset($_POST['reorder_categories'])) {
        // Handle category reordering via AJAX
        $categoryOrder = json_decode($_POST['reorder_categories'], true);
        try {
            foreach ($categoryOrder as $order => $categoryId) {
                $stmt = $pdo->prepare("UPDATE menu_categories SET category_order = ? WHERE id = ?");
                $stmt->execute([$order, $categoryId]);
            }
        } catch (Exception $e) {
            // If column doesn't exist, silently fail (drag order won't persist, but no errors)
            error_log("Category order update failed: " . $e->getMessage());
        }
        exit;
    }
    if (!$expectsJson) {
        if (empty($_SESSION['menu_success'])) {
            $_SESSION['menu_success'] = 'Saved.';
        }
        header('Location: menu_management.php');
        exit;
    }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($expectsJson) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $_SESSION['menu_errors'] = [$e->getMessage()];
        header('Location: menu_management.php');
        exit;
    }
}

// Get categories - check if category_order column exists first
try {
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY COALESCE(category_order, 0), name")->fetchAll();
} catch (Exception $e) {
    // If category_order column doesn't exist, fall back to simple ordering
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll();
}

$categories = sortMenuCategories($categories);

$ingredients = $pdo->query('SELECT id, name, unit FROM ingredients WHERE deleted_at IS NULL ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$ingredientsById = array_column($ingredients, null, 'id');

// Get items grouped by category
$items = $pdo->query("SELECT mi.*, mc.name as category_name FROM menu_items mi LEFT JOIN menu_categories mc ON mi.category_id = mc.id ORDER BY mc.name, mi.name")->fetchAll();

foreach ($items as &$item) {
    applyMenuItemAvailabilityDetails($pdo, $item);
}
unset($item);

$menuEditHistory = getRecentMenuAndStockHistory($pdo, 25);

// Initialize all categories first
$itemsByCategory = [];
foreach ($categories as $cat) {
    $itemsByCategory[$cat['id']] = ['name' => $cat['name'], 'items' => []];
}

// Add items to their categories
foreach ($items as $item) {
    if ($item['category_id'] && isset($itemsByCategory[$item['category_id']])) {
        $itemsByCategory[$item['category_id']]['items'][] = $item;
    } elseif (!$item['category_id']) {
        // Handle uncategorized items
        if (!isset($itemsByCategory[0])) {
            $itemsByCategory[0] = ['name' => 'Uncategorized', 'items' => []];
        }
        $itemsByCategory[0]['items'][] = $item;
    }
}

$itemCount = count($items);
$availableItemCount = count(array_filter($items, static function ($item) {
    return !empty($item['available']);
}));
?>

<?php include 'includes/header.php'; ?>

<div class="main-content menu-admin-page">
    <div class="page-hero menu-admin-hero">
        <div>
            <?php renderPageBackButton('dashboard.php', 'Back to Dashboard'); ?>
            <h1 class="page-title">Menu Management</h1>
            <p class="page-subtitle">Configure your cafe's offerings and pricing.</p>
        </div>
        <div class="menu-admin-actions">
            <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#manageCategoriesModal">Manage Categories</button>
            <button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#bulkImportModal">Import Menu Items</button>
            <form method="post" style="display:inline; margin:0;">
                <button type="submit" name="clear_menu" class="btn btn-outline-danger" data-confirm="Remove all menu items, categories, and recipes? This cannot be undone.">Clear Menu</button>
            </form>
            <button class="btn btn-primary" type="button" onclick="openAddItemModal()">Add New Item</button>
        </div>
    </div>

<?php if (!empty($_SESSION['menu_success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars((string) $_SESSION['menu_success']); ?></div>
    <?php unset($_SESSION['menu_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['menu_errors']) && is_array($_SESSION['menu_errors'])): ?>
    <div class="alert alert-danger">
        <?php foreach ($_SESSION['menu_errors'] as $err): ?>
            <div><?php echo htmlspecialchars((string) $err); ?></div>
        <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['menu_errors']); ?>
<?php endif; ?>

<?php
$menuUnavailableCount = count(array_filter($items, static function ($item) {
    return empty($item['available']);
}));
?>
<?php if ($menuUnavailableCount > 0): ?>
    <div class="alert alert-warning" role="status">
        <strong><?php echo (int) $menuUnavailableCount; ?> menu item<?php echo $menuUnavailableCount === 1 ? '' : 's'; ?> currently unavailable.</strong>
        Reasons are shown under each item (manual disable, expired ingredients, or insufficient stock) so staff can restock or fix recipes immediately.
        <a class="alert-link" href="inventory.php?panel=reordering&filter=low">Open low-stock inventory</a>
    </div>
<?php endif; ?>

<div class="menu-admin-toolbar">
    <div class="menu-admin-search-wrap">
        <input type="text" id="searchInput" class="form-control" placeholder="Search menu items..." data-live-search-target="#categoryList .menu-item-card" data-live-search-filter="off" autocomplete="off">
    </div>
    <div class="menu-admin-filter-pills" id="menuCategoryFilters">
        <button type="button" class="active" onclick="filterMenuSections('all', this)">All</button>
        <?php foreach ($categories as $cat): ?>
            <button type="button" onclick="filterMenuSections('<?php echo (int) $cat['id']; ?>', this)"><?php echo htmlspecialchars($cat['name']); ?></button>
        <?php endforeach; ?>
    </div>
</div>

<div class="menu-admin-summary">
    <span><?php echo (int) $itemCount; ?> total items</span>
    <span><?php echo (int) $availableItemCount; ?> available</span>
    <span><?php echo (int) count($categories); ?> categories</span>
</div>

<div class="card mb-4 kc-history-disclosure-card" id="menuEditHistory">
    <details class="kc-history-disclosure">
        <summary>Menu &amp; Stock Edit History</summary>
        <div class="card-body table-responsive">
        <?php if (empty($menuEditHistory)): ?>
            <p class="text-muted mb-0">No menu or stock edits logged yet. Changes will appear here after staff update menu items or reduce/add ingredient stock.</p>
        <?php else: ?>
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>Action</th>
                        <th>Item</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menuEditHistory as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $entry['created_at']); ?></td>
                            <td><?php echo htmlspecialchars((string) $entry['username']); ?></td>
                            <td><?php echo htmlspecialchars((string) $entry['label']); ?></td>
                            <td><?php echo htmlspecialchars((string) $entry['summary']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    </details>
</div>

<!-- Manage Categories Modal with Tabs -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Categories</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#addCatTab">Add</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#editCatTab">Edit</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#deleteCatTab">Delete</a>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <!-- Add Tab -->
                    <div id="addCatTab" class="tab-pane fade show active">
                        <form method="post">
                            <div class="form-group">
                                <label>Category Name</label>
                                <input type="text" name="category_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="category_description" class="form-control"></textarea>
                            </div>
                            <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                        </form>
                    </div>

                    <!-- Edit Tab -->
                    <div id="editCatTab" class="tab-pane fade">
                        <form method="post">
                            <div class="form-group">
                                <label>Select Category to Edit</label>
                                <select name="category_id" id="editCategorySelect" class="form-control" required onchange="loadCategoryData()">
                                    <option value="">-- Choose category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Category Name</label>
                                <input type="text" name="category_name" id="editCategoryName" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="category_description" id="editCategoryDesc" class="form-control"></textarea>
                            </div>
                            <button type="submit" name="edit_category" class="btn btn-warning">Update Category</button>
                        </form>
                    </div>

                    <!-- Delete Tab -->
                    <div id="deleteCatTab" class="tab-pane fade">
                        <form method="post" data-confirm="Delete selected category, all child categories, and all their items? This action cannot be undone.">
                            <div class="form-group">
                                <label>Select Category to Delete</label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">-- Choose category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="text-danger"><strong>Warning:</strong> This will delete the category, any child categories under it, and all related items with their order or inventory logs.</p>
                            <button type="submit" name="delete_category" class="btn btn-danger">Delete Category</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Import Menu Items</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Upload a CSV file to import menu items in bulk. Required columns: Name, Category, Price, Available (yes/no), Ingredients.</p>
                    <p>Ingredients should be formatted as <code>IngredientName:Quantity Unit</code> and separated by semicolons.</p>
                    <div class="form-group">
                        <label>Example CSV</label>
                        <pre class="bg-light p-2" style="white-space: pre-wrap;">Name,Category,Price,Available,Ingredients
Chicken Poppers Chaofan,Main Course,250,yes,Chicken:700 grams; Rice:200 grams; Sauce:50 ml
Beef Burger,Main Course,180,yes,Beef Patty:150 grams; Bun:1 pcs; Lettuce:20 grams; Sauce:30 ml
Chocolate Milkshake,Beverages,120,yes,Milk:200 ml; Chocolate Syrup:30 ml; Ice Cream:100 grams</pre>
                    </div>
                    <div class="form-group">
                        <label for="csv_file">CSV File</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" class="form-control-file" required>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="downloadMenuTemplate()">Download sample menu CSV</button>
                        <small class="form-text text-muted mt-2">Categories and Ingredients are imported automatically if the headers match.</small>
                    </div>
                    <div class="form-group">
                        <small class="form-text text-muted">Categories will be created automatically if they do not already exist.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_import_menu" class="btn btn-primary">Import Menu Items</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="category-sections menu-admin-sections" id="categoryList">
    <?php foreach ($itemsByCategory as $catId => $category): ?>
    <section class="card menu-admin-section" draggable="true" data-category-id="<?php echo $catId; ?>" data-category-name="<?php echo htmlspecialchars(strtolower((string) $category['name'])); ?>">
        <div class="menu-admin-section-head">
            <div class="menu-admin-section-copy">
                <span class="menu-section-grip" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="8" cy="6" r="1.4" fill="currentColor"/><circle cx="8" cy="12" r="1.4" fill="currentColor"/><circle cx="8" cy="18" r="1.4" fill="currentColor"/><circle cx="16" cy="6" r="1.4" fill="currentColor"/><circle cx="16" cy="12" r="1.4" fill="currentColor"/><circle cx="16" cy="18" r="1.4" fill="currentColor"/></svg>
                </span>
                <div>
                    <h2><?php echo htmlspecialchars($category['name']); ?></h2>
                    <span><?php echo count($category['items']); ?> items</span>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="openAddItemModal(<?php echo $catId; ?>)">Add Item</button>
        </div>
        <div id="cat-<?php echo $catId; ?>" class="menu-admin-section-body">
            <div class="card-body px-0 pb-0">
                <?php if (count($category['items']) > 0): ?>
                <div class="menu-items-grid" id="items-grid-<?php echo (int) $catId; ?>">
                    <?php foreach ($category['items'] as $item): ?>
                        <?php
                            $imgFile = isset($item['image']) ? (string) $item['image'] : '';
                            $imgUrl = $imgFile !== '' ? ('assets/images/' . rawurlencode($imgFile)) : '';
                        ?>
                        <div class="menu-item-card" data-name="<?php echo htmlspecialchars(strtolower((string) $item['name'])); ?>" data-category-id="<?php echo (int) $catId; ?>" data-item-id="<?php echo (int) $item['id']; ?>" data-image="<?php echo htmlspecialchars($imgFile); ?>">
                            <div class="menu-item-thumb" role="img" aria-label="Item image">
                                <?php if ($imgUrl !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="menu-item-thumb-placeholder"><?php echo htmlspecialchars(substr((string) $item['name'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <button type="button" class="menu-item-image-edit-btn" aria-label="Edit image for <?php echo htmlspecialchars($item['name']); ?>" onclick="openImageEditor(<?php echo (int) $item['id']; ?>)">✎</button>
                            </div>
                            <div class="menu-item-main">
                                <div class="menu-item-title-row">
                                    <div>
                                        <div class="menu-item-title"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="menu-item-category"><?php echo htmlspecialchars($category['name']); ?></div>
                                    </div>
                                </div>
                                <div class="menu-item-sub">₱<?php echo number_format((float) $item['price'], 2); ?></div>
                                <div class="menu-item-status <?php echo $item['available'] ? 'is-available' : 'is-unavailable'; ?>"><?php echo $item['available'] ? 'Available' : 'Unavailable'; ?></div>
                                <?php if (empty($item['available']) && !empty($item['unavailable_reason'])): ?>
                                    <div class="menu-item-unavailable-reason"><?php echo htmlspecialchars($item['unavailable_reason']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="menu-item-actions">
                                <button class="btn btn-sm <?php echo $item['available'] ? 'btn-success' : 'btn-secondary'; ?>" id="avail-btn-<?php echo (int) $item['id']; ?>" onclick="toggleAvailability(<?php echo (int) $item['id']; ?>)"><?php echo $item['available'] ? 'Available' : 'Unavailable'; ?></button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="editItem(<?php echo (int) $item['id']; ?>)" aria-label="Edit <?php echo htmlspecialchars($item['name']); ?>">Edit</button>
                                <form method="post" class="d-inline" data-confirm="Delete this item?">
                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger" aria-label="Delete <?php echo htmlspecialchars($item['name']); ?>" data-confirm="Delete this item?">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0 menu-admin-empty">No items in this category yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="addItemForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="addItemMessage"></div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" name="image" accept="image/*" class="form-control-file">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="addCategorySelect" class="form-control">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipe Ingredients</label>
                        <div id="addRecipeRows" class="recipe-rows"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRecipeRow()">Add ingredient</button>
                        <small class="form-text text-muted">Add ingredients that make up this menu item. Leave blank for simple items.</small>
                    </div>
                    <div id="priceInputContainer">
                        <div class="form-group">
                            <label>Pricing Model</label>
                            <div class="btn-group btn-group-toggle d-flex menu-pricing-model-group" data-toggle="buttons" style="width:100%;">
                                <label class="btn btn-outline-primary menu-pricing-model-option active" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="single" autocomplete="off" checked onchange="togglePricingModel('single')"> Single Price
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="dual" autocomplete="off" onchange="togglePricingModel('dual')"> Solo/Sharing
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="temperature" autocomplete="off" onchange="togglePricingModel('temperature')"> Hot/Iced
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="size" autocomplete="off" onchange="togglePricingModel('size')"> Sizes
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="itemPriceField">
                            <label>Price</label>
                            <input id="addItemPriceInput" type="number" step="0.01" name="price" class="form-control" required>
                        </div>
                        <div id="dualPricingFields" style="display:none;">
                            <div class="form-group">
                                <label>Solo Price</label>
                                <input id="addItemPriceSoloInput" type="number" step="0.01" name="price_solo" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Sharing Price</label>
                                <input id="addItemPriceSharingInput" type="number" step="0.01" name="price_sharing" class="form-control">
                            </div>
                            <small class="form-text text-muted">Provide at least one of Solo or Sharing price.</small>
                        </div>
                        <div id="addTemperaturePricingFields" style="display:none;">
                            <div class="form-group">
                                <label>Hot Price</label>
                                <input id="addItemPriceHotInput" type="number" step="0.01" name="price_hot" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Iced Price</label>
                                <input id="addItemPriceIcedInput" type="number" step="0.01" name="price_iced" class="form-control">
                            </div>
                            <small class="form-text text-muted">Provide at least one of Hot or Iced price.</small>
                        </div>
                        <div id="addSizePricingFields" style="display:none;">
                            <div class="form-group">
                                <label>Size 1 Label</label>
                                <input id="addItemSizeLabel1Input" type="text" name="size_label_1" class="form-control" placeholder="12oz">
                            </div>
                            <div class="form-group">
                                <label>Size 1 Price</label>
                                <input id="addItemPriceSize1Input" type="number" step="0.01" name="price_size_1" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Size 2 Label</label>
                                <input id="addItemSizeLabel2Input" type="text" name="size_label_2" class="form-control" placeholder="16oz">
                            </div>
                            <div class="form-group">
                                <label>Size 2 Price</label>
                                <input id="addItemPriceSize2Input" type="number" step="0.01" name="price_size_2" class="form-control">
                            </div>
                            <small class="form-text text-muted">Provide at least one size label and price. Labels are editable.</small>
                        </div>
                        <div class="btn-group btn-group-toggle d-flex mt-2" data-toggle="buttons" style="width:100%;">
                            <label class="btn btn-outline-success active" style="flex:1;">
                                <input type="radio" name="available" value="1" autocomplete="off" checked> Available
                            </label>
                            <label class="btn btn-outline-secondary" style="flex:1;">
                                <input type="radio" name="available" value="0" autocomplete="off"> Unavailable
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="submitAddItemForm()">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal (will be populated by JS) -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="editModalBody">
                    <!-- Populated by JS -->
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_item" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
// Initialize Sortable for category reordering
let activeMenuFilter = 'all';
let pendingAddItemCategoryId = null;

const categoryList = document.getElementById('categoryList');
if (categoryList) {
    new Sortable(categoryList, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        handle: '.menu-section-grip',
        onEnd: function(e) {
            // Save new order to server
            const categories = Array.from(document.querySelectorAll('#categoryList .menu-admin-section')).map(card => card.dataset.categoryId);
            
            fetch('menu_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'reorder_categories=' + JSON.stringify(categories)
            })
            .then(response => console.log('Categories reordered'))
            .catch(err => console.error('Error saving category order:', err));
        }
    });
}

const categoryData = <?php echo json_encode(array_column($categories, null, 'id')); ?>;
const ingredientOptions = <?php echo json_encode($ingredients); ?>;

function downloadMenuTemplate() {
    const csv = [
        ['Name', 'Category', 'Price', 'Available', 'Ingredients'],
        ['Chicken Poppers Chaofan', 'Main Course', '250', 'yes', 'Chicken:700 grams; Rice:200 grams; Sauce:50 ml'],
        ['Beef Burger', 'Main Course', '180', 'yes', 'Beef Patty:150 grams; Bun:1 pcs; Lettuce:20 grams; Sauce:30 ml'],
        ['Chocolate Milkshake', 'Beverages', '120', 'yes', 'Milk:200 ml; Chocolate Syrup:30 ml; Ice Cream:100 grams'],
    ];
    const content = csv.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'menu_template.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function getIngredientOptionMarkup(selectedId) {
    return ingredientOptions.map((ingredient) => {
        const selected = String(ingredient.id) === String(selectedId) ? ' selected' : '';
        return `<option value="${ingredient.id}"${selected}>${ingredient.name} (${ingredient.unit})</option>`;
    }).join('');
}

function getIngredientLabelById(id) {
    const ingredient = ingredientOptions.find((ingredient) => String(ingredient.id) === String(id));
    return ingredient ? `${ingredient.name} (${ingredient.unit})` : '';
}

function normalizeRecipeUnit(unit) {
    const normalized = String(unit || '').trim().toLowerCase();
    if (normalized === 'ml') return 'milliliters';
    if (normalized === 'l' || normalized === 'lt' || normalized === 'liter') return 'liters';
    if (normalized === 'kg') return 'kilograms';
    if (normalized === 'g') return 'grams';
    if (normalized === 'pcs' || normalized === 'piece' || normalized === 'pieces') return 'pcs';
    if (normalized === 'millilitres') return 'milliliters';
    return normalized;
}

function createRecipeRow(containerId, ingredientId = '', quantity = '', quantityUnit = '', ingredientName = '') {
    const row = document.createElement('div');
    row.className = 'recipe-row';
    row.innerHTML = `
        <div class="form-group recipe-ingredient-wrap">
            <label>Ingredient</label>
            <select name="recipe_ingredient_id[]" class="form-control" required>
                <option value="">-- Select ingredient --</option>
                ${getIngredientOptionMarkup(ingredientId)}
            </select>
            <div class="selected-ingredient-text text-muted small mt-1"></div>
        </div>
        <div class="form-group recipe-qty-wrap">
            <label>Quantity</label>
            <input type="number" step="0.01" min="0" name="recipe_quantity[]" class="form-control" value="${quantity !== '' ? String(quantity) : ''}" placeholder="0.00" required>
        </div>
        <div class="form-group recipe-unit-wrap">
            <label>Unit</label>
            <select name="recipe_quantity_unit[]" class="form-control">
                <option value=""${quantityUnit === '' ? ' selected' : ''}>Default</option>
                <option value="pcs"${quantityUnit === 'pcs' ? ' selected' : ''}>pcs</option>
                <option value="grams"${quantityUnit === 'grams' ? ' selected' : ''}>grams</option>
                <option value="kilograms"${quantityUnit === 'kilograms' ? ' selected' : ''}>kilograms</option>
                <option value="milliliters"${quantityUnit === 'milliliters' ? ' selected' : ''}>milliliters</option>
                <option value="liters"${quantityUnit === 'liters' ? ' selected' : ''}>liters</option>
            </select>
        </div>
        <div class="form-group recipe-remove-wrap">
            <label>&nbsp;</label>
            <button type="button" class="btn btn-outline-danger" onclick="removeRecipeRow(this)">Remove</button>
        </div>
    `;
    const container = document.getElementById(containerId);
    if (container) {
        container.appendChild(row);
        const ingredientSelect = row.querySelector('select[name="recipe_ingredient_id[]"]');
        if (ingredientSelect) {
            if (ingredientId !== '') {
                ingredientSelect.value = String(ingredientId);
            }
            if (ingredientSelect.selectedIndex <= 0 && ingredientName !== '') {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = ingredientId !== '' ? String(ingredientId) : '';
                fallbackOption.textContent = ingredientName;
                fallbackOption.selected = true;
                ingredientSelect.appendChild(fallbackOption);
            }
        }
        const ingredientLabelDiv = row.querySelector('.selected-ingredient-text');
        if (ingredientLabelDiv) {
            ingredientLabelDiv.textContent = getIngredientLabelById(ingredientId) || ingredientName || 'Select an ingredient';
            if (ingredientSelect) {
                ingredientSelect.addEventListener('change', function () {
                    ingredientLabelDiv.textContent = getIngredientLabelById(this.value) || (this.value ? '' : 'Select an ingredient');
                });
            }
        }
        const unitSelect = row.querySelector('select[name="recipe_quantity_unit[]"]');
        if (unitSelect && quantityUnit !== '') {
            const normalizedUnit = normalizeRecipeUnit(quantityUnit);
            unitSelect.value = normalizedUnit;
            if (unitSelect.selectedIndex <= 0) {
                const fallbackOption = document.createElement('option');
                fallbackOption.value = String(quantityUnit);
                fallbackOption.textContent = String(quantityUnit);
                fallbackOption.selected = true;
                unitSelect.appendChild(fallbackOption);
            }
        }
    }
}

function addRecipeRow(ingredientId = '', quantity = '', quantityUnit = '', ingredientName = '') {
    createRecipeRow('addRecipeRows', ingredientId, quantity, quantityUnit, ingredientName);
}

function addEditRecipeRow(ingredientId = '', quantity = '', quantityUnit = '', ingredientName = '') {
    createRecipeRow('editRecipeRows', ingredientId, quantity, quantityUnit, ingredientName);
}

function removeRecipeRow(button) {
    const row = button.closest('.recipe-row');
    if (row) {
        row.remove();
    }
}

function renderRecipeRows(recipe = []) {
    const container = document.getElementById('addRecipeRows');
    if (!container) return;
    container.innerHTML = '';
    if (!Array.isArray(recipe) || recipe.length === 0) {
        addRecipeRow();
        return;
    }
    recipe.forEach((entry) => {
        addRecipeRow(entry.ingredient_id, entry.quantity, entry.quantity_unit || '', entry.ingredient_name || '');
    });
}

function renderEditRecipeRows(recipe = []) {
    const container = document.getElementById('editRecipeRows');
    if (!container) return;
    container.innerHTML = '';
    if (!Array.isArray(recipe) || recipe.length === 0) {
        addEditRecipeRow();
        return;
    }
    recipe.forEach((entry) => {
        addEditRecipeRow(entry.ingredient_id, entry.quantity, entry.quantity_unit || '', entry.ingredient_name || '');
    });
}

function syncPricingModelAvailability(formSelector, selectId, hintId) {
    const form = document.querySelector(formSelector);
    if (!form) {
        return;
    }

    const temperatureInput = form.querySelector('input[name="pricing_model"][value="temperature"]');
    if (!temperatureInput) {
        return;
    }

    temperatureInput.disabled = false;
    const temperatureLabel = temperatureInput.closest('label');
    if (temperatureLabel) {
        temperatureLabel.classList.remove('disabled');
        temperatureLabel.style.opacity = '1';
    }
    const hint = document.getElementById(hintId);
    if (hint) {
        hint.style.display = 'none';
    }
}

function setPricingModelInputsState(formSelector, model) {
    const form = document.querySelector(formSelector);
    if (!form) {
        return;
    }

    form.querySelectorAll('input[name="pricing_model"]').forEach((input) => {
        const isActive = input.value === model;
        input.checked = isActive;
        const label = input.closest('label');
        if (label) {
            label.classList.toggle('active', isActive);
        }
    });
}

function syncAddItemPricingState() {
    const priceField = document.getElementById('itemPriceField');
    const dualPricingFields = document.getElementById('dualPricingFields');
    const temperaturePricingFields = document.getElementById('addTemperaturePricingFields');
    const sizePricingFields = document.getElementById('addSizePricingFields');
    const priceInput = document.getElementById('addItemPriceInput');
    const soloInput = document.getElementById('addItemPriceSoloInput');
    const sharingInput = document.getElementById('addItemPriceSharingInput');
    const hotInput = document.getElementById('addItemPriceHotInput');
    const icedInput = document.getElementById('addItemPriceIcedInput');
    const sizeLabel1Input = document.getElementById('addItemSizeLabel1Input');
    const sizeLabel2Input = document.getElementById('addItemSizeLabel2Input');
    const sizePrice1Input = document.getElementById('addItemPriceSize1Input');
    const sizePrice2Input = document.getElementById('addItemPriceSize2Input');
    const model = (document.querySelector('#addItemForm input[name="pricing_model"]:checked') || {}).value || 'single';

    if (model === 'temperature') {
        if (priceField) priceField.style.display = 'none';
        if (dualPricingFields) dualPricingFields.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'block';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = false;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
        if (hotInput) hotInput.required = false;
        if (icedInput) icedInput.required = false;
        return;
    }

    if (model === 'size') {
        if (priceField) priceField.style.display = 'none';
        if (dualPricingFields) dualPricingFields.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'block';
        if (priceInput) priceInput.required = false;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
        if (hotInput) hotInput.required = false;
        if (icedInput) icedInput.required = false;
        if (sizeLabel1Input) sizeLabel1Input.required = false;
        if (sizeLabel2Input) sizeLabel2Input.required = false;
        if (sizePrice1Input) sizePrice1Input.required = false;
        if (sizePrice2Input) sizePrice2Input.required = false;
        return;
    }

    if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
    if (sizePricingFields) sizePricingFields.style.display = 'none';
    if (hotInput) hotInput.required = false;
    if (icedInput) icedInput.required = false;
    togglePricingModel(model);
}

function togglePricingModel(model) {
    const priceField = document.getElementById('itemPriceField');
    const dualPricingFields = document.getElementById('dualPricingFields');
    const temperaturePricingFields = document.getElementById('addTemperaturePricingFields');
    const sizePricingFields = document.getElementById('addSizePricingFields');
    const priceInput = document.getElementById('addItemPriceInput');
    const soloInput = document.getElementById('addItemPriceSoloInput');
    const sharingInput = document.getElementById('addItemPriceSharingInput');

    if (!priceField || !dualPricingFields) return;

    if (model === 'dual') {
        priceField.style.display = 'none';
        dualPricingFields.style.display = 'block';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = false;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
    } else if (model === 'temperature') {
        priceField.style.display = 'none';
        dualPricingFields.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'block';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = false;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
    } else if (model === 'size') {
        priceField.style.display = 'none';
        dualPricingFields.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'block';
        if (priceInput) priceInput.required = false;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
    } else {
        priceField.style.display = 'block';
        dualPricingFields.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = true;
        if (soloInput) soloInput.required = false;
        if (sharingInput) sharingInput.required = false;
    }
}

function submitAddItemForm() {
    const form = document.getElementById('addItemForm');
    const messageDiv = document.getElementById('addItemMessage');
    const formData = new FormData(form);
    formData.append('add_item', '1');

    fetch('menu_management.php', {
        method: 'POST',
        body: formData
    })
    .then(async (response) => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text ? text.slice(0, 200) : 'The server returned an invalid response.');
        }
    })
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<div class="alert alert-success">Item added successfully!</div>';
            const newItem = data.item;
            const section = document.querySelector('.menu-admin-section[data-category-id="' + newItem.category_id + '"]');
            let grid = document.getElementById('items-grid-' + newItem.category_id);
            if (!grid && section) {
                const body = section.querySelector('.menu-admin-section-body .card-body');
                const empty = section.querySelector('.menu-admin-empty');
                if (empty) {
                    empty.remove();
                }
                if (body) {
                    grid = document.createElement('div');
                    grid.className = 'menu-items-grid';
                    grid.id = 'items-grid-' + newItem.category_id;
                    body.prepend(grid);
                }
            }
            if (grid) {
                const card = document.createElement('div');
                card.className = 'menu-item-card';
                card.setAttribute('data-name', String(newItem.name || '').toLowerCase());
                card.setAttribute('data-category-id', String(newItem.category_id || ''));
                card.setAttribute('data-item-id', String(newItem.id));
                card.setAttribute('data-image', String(newItem.image || ''));
                const initial = String(newItem.name || '').trim().slice(0, 1) || '?';
                const imgUrl = newItem.image ? ('assets/images/' + encodeURIComponent(newItem.image)) : '';
                const categorySelect = document.getElementById('addCategorySelect');
                const categoryLabel = categorySelect && categorySelect.selectedOptions[0] ? categorySelect.selectedOptions[0].textContent : 'Category';
                card.innerHTML = `
                    <div class="menu-item-thumb" role="img" aria-label="Item image">
                        ${imgUrl ? `<img src="${imgUrl}" alt="${newItem.name}">` : `<div class="menu-item-thumb-placeholder">${initial}</div>`}
                        <button type="button" class="menu-item-image-edit-btn" aria-label="Edit image for ${newItem.name}" onclick="openImageEditor(${newItem.id})">✎</button>
                    </div>
                    <div class="menu-item-main">
                        <div class="menu-item-title-row">
                            <div>
                                <div class="menu-item-title">${newItem.name}</div>
                                <div class="menu-item-category">${categoryLabel}</div>
                            </div>
                        </div>
                        <div class="menu-item-sub">₱${parseFloat(newItem.price).toFixed(2)}</div>
                        <div class="menu-item-status ${newItem.available ? 'is-available' : 'is-unavailable'}">${newItem.available ? 'Available' : 'Unavailable'}</div>
                    </div>
                    <div class="menu-item-actions">
                        <button class="btn btn-sm ${newItem.available ? 'btn-success' : 'btn-secondary'}" id="avail-btn-${newItem.id}" onclick="toggleAvailability(${newItem.id})">${newItem.available ? 'Available' : 'Unavailable'}</button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editItem(${newItem.id})" aria-label="Edit ${newItem.name}">Edit</button>
                        <form method="post" class="d-inline" data-confirm="Delete this item?">
                            <input type="hidden" name="id" value="${newItem.id}">
                            <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger" aria-label="Delete ${newItem.name}" data-confirm="Delete this item?">Delete</button>
                        </form>
                    </div>
                `;
                grid.prepend(card);
            }
            applyMenuFilters();
            setTimeout(() => {
                $('#addItemModal').modal('hide');
            }, 800);
        } else {
            messageDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    })
    .catch(err => {
        messageDiv.innerHTML = `<div class="alert alert-danger">${err.message || 'Error adding item. Please try again.'}</div>`;
        console.error('Error:', err);
    });
}

function toggleAvailability(id) {
    fetch('menu_management.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'toggle_available=1&id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) throw new Error(res.message || 'Toggle failed');
        const btn = document.getElementById('avail-btn-' + id);
        const status = document.querySelector('.menu-item-card[data-item-id="' + id + '"] .menu-item-status');
        if (btn) {
            btn.textContent = res.available ? 'Available' : 'Unavailable';
            btn.className = 'btn btn-sm ' + (res.available ? 'btn-success' : 'btn-secondary');
        }
        if (status) {
            status.textContent = res.available ? 'Available' : 'Unavailable';
            status.className = 'menu-item-status ' + (res.available ? 'is-available' : 'is-unavailable');
        }
    })
    .catch(err => {
        window.KinAlertModal.alert('Failed to update availability: ' + err.message, 'Error', 'OK');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', applyMenuFilters);
    }

    const addCategorySelect = document.getElementById('addCategorySelect');
    if (addCategorySelect) {
        addCategorySelect.addEventListener('change', function () {
            syncPricingModelAvailability('#addItemForm', 'addCategorySelect', 'addTemperatureModelHint');
            syncAddItemPricingState();
        });
    }
    
    // Reinitialize when Add Item modal is shown
    $('#addItemModal').on('show.bs.modal', function() {
        // Reset form
        document.querySelector('#addItemForm').reset();
        // Clear message
        document.getElementById('addItemMessage').innerHTML = '';
        const categorySelect = document.getElementById('addCategorySelect');
        const preferredCategoryId = pendingAddItemCategoryId !== null
            ? String(pendingAddItemCategoryId)
            : (activeMenuFilter !== 'all' ? String(activeMenuFilter) : '');
        if (categorySelect && preferredCategoryId !== '') {
            categorySelect.value = preferredCategoryId;
        }
        togglePricingModel('single');
        syncPricingModelAvailability('#addItemForm', 'addCategorySelect', 'addTemperatureModelHint');
        syncAddItemPricingState();
        renderRecipeRows([]);
    });

    $('#addItemModal').on('hidden.bs.modal', function() {
        pendingAddItemCategoryId = null;
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form || !form.getAttribute) return;
        if (form.dataset.confirmBypassed === '1') {
            delete form.dataset.confirmBypassed;
            return;
        }
        const submitter = e.submitter;
        const msg = form.getAttribute('data-confirm') || (submitter && submitter.getAttribute ? submitter.getAttribute('data-confirm') : null);
        if (!msg) return;
        if (window.KinAlertModal && typeof window.KinAlertModal.confirm === 'function') {
            e.preventDefault();
            window.KinAlertModal.confirm(msg, 'Confirm', 'OK', 'Cancel').then((ok) => {
                if (!ok) {
                    return;
                }

                form.dataset.confirmBypassed = '1';
                if (submitter && typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                    return;
                }

                if (submitter && submitter.name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitter.name;
                    hidden.value = submitter.value || '1';
                    form.appendChild(hidden);
                }
                form.submit();
            });
        }
    }, true);

    applyMenuFilters();
    syncPricingModelAvailability('#addItemForm', 'addCategorySelect', 'addTemperatureModelHint');
    syncAddItemPricingState();
});

function filterMenuSections(filter, button) {
    activeMenuFilter = String(filter || 'all');
    document.querySelectorAll('#menuCategoryFilters button').forEach((pill) => pill.classList.remove('active'));
    if (button) {
        button.classList.add('active');
    }
    applyMenuFilters();
}

function applyMenuFilters() {
    const query = String((document.getElementById('searchInput') || {}).value || '').trim().toLowerCase();
    const categoryCards = document.querySelectorAll('#categoryList .menu-admin-section');

    categoryCards.forEach((card) => {
        const categoryId = String(card.getAttribute('data-category-id') || '');
        const categoryName = String(card.getAttribute('data-category-name') || '');
        const matchesFilter = activeMenuFilter === 'all' || activeMenuFilter === categoryId;
        const itemCards = card.querySelectorAll('.menu-item-card');
        let hasVisibleItems = false;

        itemCards.forEach((itemCard) => {
            const itemName = String(itemCard.getAttribute('data-name') || itemCard.textContent || '').toLowerCase();
            const matchesQuery = !query || itemName.includes(query) || categoryName.includes(query);
            const shouldShow = matchesFilter && matchesQuery;
            itemCard.style.display = shouldShow ? '' : 'none';
            if (shouldShow) {
                hasVisibleItems = true;
            }
        });

        const empty = card.querySelector('.menu-admin-empty');
        if (empty) {
            empty.style.display = (!itemCards.length && matchesFilter && (!query || categoryName.includes(query))) ? '' : 'none';
        }

        card.style.display = (matchesFilter && (hasVisibleItems || (empty && empty.style.display !== 'none'))) ? '' : 'none';
    });
}

function editItem(id) {
    // Fetch item data and populate modal
    fetch('get_item.php?id=' + id)
    .then(async (response) => {
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error(text ? text.slice(0, 200) : 'The server returned an invalid response.');
        }
        if (!response.ok || (data && data.success === false)) {
            throw new Error((data && data.message) ? data.message : 'Unable to load item.');
        }
        return data;
    })
    .then(item => {
        const pricingModel = Number(item.size_option_enabled || 0) ? 'size' : (Number(item.temperature_option_enabled || 0) ? 'temperature' : ((item.price_solo !== null || item.price_sharing !== null) ? 'dual' : 'single'));
        document.getElementById('editModalBody').innerHTML = `
            <input type="hidden" name="id" value="${item.id}">
            <div class="edit-form-grid">
                <!-- LEFT PANEL: Image & Category / Status -->
                <div class="edit-form-panel left-panel">
                    <div class="panel-card">
                        <h6 class="panel-title">Media & Settings</h6>
                        <div class="form-group text-center mb-3">
                            <div class="edit-image-preview-wrapper mb-2" id="editItemImagePreview">
                                ${item.image ? `<img src="assets/images/${encodeURIComponent(item.image)}" alt="${item.name} image" class="edit-preview-img"/>` : '<div class="text-muted p-4">No image uploaded</div>'}
                            </div>
                            <input type="file" name="image" accept="image/*" class="form-control-file form-control-sm">
                            <small class="form-text text-muted">Upload JPG, PNG, or WebP image.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Category</label>
                            <select name="category_id" id="editItemCategorySelect" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" ${item.category_id == <?php echo $cat['id']; ?> ? 'selected' : ''}>${'<?php echo htmlspecialchars($cat['name']); ?>'}</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold">Status</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons" style="width:100%;">
                                <label class="btn btn-outline-success ${item.available ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="available" value="1" autocomplete="off" ${item.available ? 'checked' : ''}> Available
                                </label>
                                <label class="btn btn-outline-secondary ${!item.available ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="available" value="0" autocomplete="off" ${!item.available ? 'checked' : ''}> Unavailable
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Name, Pricing & Recipe -->
                <div class="edit-form-panel right-panel">
                    <div class="panel-card">
                        <h6 class="panel-title">Item Info & Pricing</h6>
                        
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Item Name</label>
                            <input type="text" name="name" class="form-control" value="${item.name}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Pricing Model</label>
                            <div class="btn-group btn-group-toggle d-flex menu-pricing-model-group mb-2" data-toggle="buttons" style="width:100%;">
                                <label class="btn btn-outline-primary menu-pricing-model-option ${pricingModel === 'single' ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="single" autocomplete="off" ${pricingModel === 'single' ? 'checked' : ''} onchange="toggleEditPricingModel('single')"> Single Price
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option ${pricingModel === 'dual' ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="dual" autocomplete="off" ${pricingModel === 'dual' ? 'checked' : ''} onchange="toggleEditPricingModel('dual')"> Solo/Sharing
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option ${pricingModel === 'temperature' ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="temperature" autocomplete="off" ${pricingModel === 'temperature' ? 'checked' : ''} onchange="toggleEditPricingModel('temperature')"> Hot/Iced
                                </label>
                                <label class="btn btn-outline-primary menu-pricing-model-option ${pricingModel === 'size' ? 'active' : ''}" style="flex:1;">
                                    <input type="radio" name="pricing_model" value="size" autocomplete="off" ${pricingModel === 'size' ? 'checked' : ''} onchange="toggleEditPricingModel('size')"> Sizes
                                </label>
                            </div>
                        </div>

                        <div class="p-3 mb-3" style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px;">
                            <h6 class="font-weight-bold text-primary mb-2" style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.04em;">Dynamic Costing & Pricing</h6>
                            <div class="form-row">
                                <div class="form-group col-6 mb-2">
                                    <label class="small text-muted font-weight-bold">Ingredient Cost (₱)</label>
                                    <input type="text" class="form-control form-control-sm" value="₱${parseFloat(item.cost_price || 0).toFixed(2)}" readonly style="background:#e2e8f0 !important; font-weight:800; color:#1e293b !important;">
                                </div>
                                <div class="form-group col-6 mb-2">
                                    <label class="small text-muted font-weight-bold">Admin Cost (₱)</label>
                                    <input type="number" step="0.01" name="admin_cost" class="form-control form-control-sm" value="${item.admin_cost || '0.00'}">
                                </div>
                                <div class="form-group col-6 mb-0">
                                    <label class="small text-muted font-weight-bold">Markup (%)</label>
                                    <input type="number" step="0.01" name="markup_percent" class="form-control form-control-sm" value="${item.markup_percent || '30.00'}">
                                </div>
                                <div class="form-group col-6 mb-0 d-flex align-items-center pt-3">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="manual_price_override" id="editManualOverride" class="custom-control-input" value="1" ${Number(item.manual_price_override) === 1 ? 'checked' : ''}>
                                        <label class="custom-control-label small font-weight-bold text-dark" for="editManualOverride">Manual Price Override</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3" id="editPriceField">
                            <label class="font-weight-bold">Price (₱) <small class="text-muted">(Auto-computed if override is off)</small></label>
                            <input type="number" step="0.01" name="price" class="form-control" value="${item.price}" required>
                        </div>

                        <div id="editDualPricingFields" style="display:none;" class="form-row">
                            <div class="form-group col-6">
                                <label>Solo Price</label>
                                <input type="number" step="0.01" name="price_solo" class="form-control" value="${item.price_solo || ''}">
                            </div>
                            <div class="form-group col-6">
                                <label>Sharing Price</label>
                                <input type="number" step="0.01" name="price_sharing" class="form-control" value="${item.price_sharing || ''}">
                            </div>
                        </div>

                        <div id="editTemperaturePricingFields" style="display:none;" class="form-row">
                            <div class="form-group col-6">
                                <label>Hot Price</label>
                                <input type="number" step="0.01" name="price_hot" class="form-control" value="${item.price_hot || ''}">
                            </div>
                            <div class="form-group col-6">
                                <label>Iced Price</label>
                                <input type="number" step="0.01" name="price_iced" class="form-control" value="${item.price_iced || ''}">
                            </div>
                        </div>

                        <div id="editSizePricingFields" style="display:none;" class="form-row">
                            <div class="form-group col-6 mb-2">
                                <label>Size 1 Label</label>
                                <input type="text" name="size_label_1" class="form-control" value="${item.size_label_1 || ''}" placeholder="12oz">
                            </div>
                            <div class="form-group col-6 mb-2">
                                <label>Size 1 Price</label>
                                <input type="number" step="0.01" name="price_size_1" class="form-control" value="${item.price_size_1 || ''}">
                            </div>
                            <div class="form-group col-6 mb-2">
                                <label>Size 2 Label</label>
                                <input type="text" name="size_label_2" class="form-control" value="${item.size_label_2 || ''}" placeholder="16oz">
                            </div>
                            <div class="form-group col-6 mb-2">
                                <label>Size 2 Price</label>
                                <input type="number" step="0.01" name="price_size_2" class="form-control" value="${item.price_size_2 || ''}">
                            </div>
                        </div>

                        <div class="form-group mb-0 mt-3">
                            <label class="font-weight-bold">Recipe Ingredients</label>
                            <div id="editRecipeRows" class="mb-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEditRecipeRow()">+ Add Ingredient</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#editItemModal').modal('show');
        renderEditRecipeRows(item.recipe || []);
        toggleEditPricingModel(pricingModel);
        syncEditItemPricingState();
        const editCategorySelect = document.getElementById('editItemCategorySelect');
        if (editCategorySelect) {
            editCategorySelect.addEventListener('change', function () {
                syncEditItemPricingState();
            });
        }
    })
    .catch(err => {
        const msg = 'Edit failed: ' + (err.message || 'Unable to open edit form.');
        window.KinAlertModal.alert(msg, 'Error', 'OK');
    });
}

function toggleEditPricingModel(model) {
    const priceField = document.getElementById('editPriceField');
    const dual = document.getElementById('editDualPricingFields');
    const temperaturePricingFields = document.getElementById('editTemperaturePricingFields');
    const sizePricingFields = document.getElementById('editSizePricingFields');
    if (!priceField || !dual) return;
    const priceInput = priceField.querySelector('input[name="price"]');

    if (model === 'dual') {
        priceField.style.display = 'none';
        dual.style.display = 'block';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = false;
    } else if (model === 'temperature') {
        priceField.style.display = 'none';
        dual.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'block';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = false;
    } else if (model === 'size') {
        priceField.style.display = 'none';
        dual.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'block';
        if (priceInput) priceInput.required = false;
    } else {
        priceField.style.display = 'block';
        dual.style.display = 'none';
        if (temperaturePricingFields) temperaturePricingFields.style.display = 'none';
        if (sizePricingFields) sizePricingFields.style.display = 'none';
        if (priceInput) priceInput.required = true;
    }
}

function syncEditItemPricingState() {
    const model = (document.querySelector('#editModalBody input[name="pricing_model"]:checked') || {}).value || 'single';
    toggleEditPricingModel(model);
}

function loadCategoryData() {
    const categoryId = document.getElementById('editCategorySelect').value;
    
    if (categoryId) {
        const data = categoryData[categoryId];
        if (data) {
            document.getElementById('editCategoryName').value = data.name || '';
            document.getElementById('editCategoryDesc').value = data.description || '';
            return;
        }

        fetch('get_category.php?id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editCategoryName').value = data.name || '';
                document.getElementById('editCategoryDesc').value = data.description || '';
            })
            .catch(err => console.log('Category data fetch error:', err));
    }
}

function openAddItemModal(categoryId) {
    pendingAddItemCategoryId = categoryId || (activeMenuFilter !== 'all' ? activeMenuFilter : null);
    // Set the category select to this category
    const categorySelect = document.querySelector('#addItemModal select[name="category_id"]');
    if (categorySelect && pendingAddItemCategoryId !== null) {
        categorySelect.value = String(pendingAddItemCategoryId);
    }
    // Open the modal
    $('#addItemModal').modal('show');
}

let kcImageEdit = {
    itemId: null,
    img: null,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    dragging: false,
    lastX: 0,
    lastY: 0,
    fileSelected: false,
};

function openImageEditor(itemId) {
    kcImageEdit.itemId = itemId;
    kcImageEdit.img = null;
    kcImageEdit.zoom = 1;
    kcImageEdit.offsetX = 0;
    kcImageEdit.offsetY = 0;
    kcImageEdit.dragging = false;
    kcImageEdit.fileSelected = false;

    const fileInput = document.getElementById('editImageFile');
    const zoomInput = document.getElementById('editImageZoom');
    const saveBtn = document.getElementById('editImageSaveBtn');
    if (fileInput) fileInput.value = '';
    if (zoomInput) zoomInput.value = '1';
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Save Image';
    }

    renderImagePreview();
    $('#editImageModal').modal('show');
}

function renderImagePreview() {
    const canvas = document.getElementById('editImageCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.fillStyle = 'rgba(176, 156, 141, 0.15)';
    ctx.fillRect(0, 0, w, h);

    if (!kcImageEdit.img) {
        ctx.fillStyle = 'rgba(78, 56, 41, 0.65)';
        ctx.font = '700 14px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Choose an image to preview', w / 2, h / 2);
        return;
    }

    const img = kcImageEdit.img;
    const baseScale = Math.max(w / img.width, h / img.height);
    const scale = baseScale * kcImageEdit.zoom;
    const drawW = img.width * scale;
    const drawH = img.height * scale;
    const cx = w / 2 + kcImageEdit.offsetX;
    const cy = h / 2 + kcImageEdit.offsetY;
    const x = cx - drawW / 2;
    const y = cy - drawH / 2;

    ctx.drawImage(img, x, y, drawW, drawH);
}

function buildCroppedBlob() {
    return new Promise((resolve, reject) => {
        if (!kcImageEdit.img) {
            reject(new Error('No image selected.'));
            return;
        }
        const outSize = 512;
        const canvas = document.createElement('canvas');
        canvas.width = outSize;
        canvas.height = outSize;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            reject(new Error('Unable to process image.'));
            return;
        }

        const img = kcImageEdit.img;
        const baseScale = Math.max(outSize / img.width, outSize / img.height);
        const scale = baseScale * kcImageEdit.zoom;
        const drawW = img.width * scale;
        const drawH = img.height * scale;
        const cx = outSize / 2 + kcImageEdit.offsetX * (outSize / 320);
        const cy = outSize / 2 + kcImageEdit.offsetY * (outSize / 320);
        const x = cx - drawW / 2;
        const y = cy - drawH / 2;

        ctx.drawImage(img, x, y, drawW, drawH);
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('Unable to process image.'));
                return;
            }
            resolve(blob);
        }, 'image/webp', 0.9);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('editImageFile');
    const zoomInput = document.getElementById('editImageZoom');
    const canvas = document.getElementById('editImageCanvas');
    const saveBtn = document.getElementById('editImageSaveBtn');
    let objectUrl = null;

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                window.KinAlertModal.alert('Invalid file type. Use JPG, PNG, or WebP.', 'Invalid Image', 'OK');
                fileInput.value = '';
                return;
            }
            if (file.size > 3 * 1024 * 1024) {
                window.KinAlertModal.alert('Image is too large. Max size is 3MB.', 'Invalid Image', 'OK');
                fileInput.value = '';
                return;
            }
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            const url = URL.createObjectURL(file);
            objectUrl = url;
            const img = new Image();
            img.onload = () => {
                kcImageEdit.img = img;
                kcImageEdit.zoom = 1;
                kcImageEdit.offsetX = 0;
                kcImageEdit.offsetY = 0;
                kcImageEdit.fileSelected = true;
                if (saveBtn) saveBtn.disabled = false;
                renderImagePreview();
            };
            img.onerror = () => {
                window.KinAlertModal.alert('Unable to load image preview.', 'Error', 'OK');
            };
            img.src = url;
        });
    }

    if (zoomInput) {
        zoomInput.addEventListener('input', () => {
            kcImageEdit.zoom = Math.min(3, Math.max(1, parseFloat(zoomInput.value || '1')));
            renderImagePreview();
        });
    }

    if (canvas) {
        canvas.addEventListener('mousedown', (e) => {
            kcImageEdit.dragging = true;
            kcImageEdit.lastX = e.clientX;
            kcImageEdit.lastY = e.clientY;
        });
        window.addEventListener('mouseup', () => {
            kcImageEdit.dragging = false;
        });
        window.addEventListener('mousemove', (e) => {
            if (!kcImageEdit.dragging) return;
            const dx = e.clientX - kcImageEdit.lastX;
            const dy = e.clientY - kcImageEdit.lastY;
            kcImageEdit.lastX = e.clientX;
            kcImageEdit.lastY = e.clientY;
            kcImageEdit.offsetX += dx;
            kcImageEdit.offsetY += dy;
            renderImagePreview();
        });
        canvas.addEventListener('touchstart', (e) => {
            if (!e.touches || !e.touches[0]) return;
            kcImageEdit.dragging = true;
            kcImageEdit.lastX = e.touches[0].clientX;
            kcImageEdit.lastY = e.touches[0].clientY;
        }, { passive: true });
        window.addEventListener('touchend', () => {
            kcImageEdit.dragging = false;
        }, { passive: true });
        window.addEventListener('touchmove', (e) => {
            if (!kcImageEdit.dragging || !e.touches || !e.touches[0]) return;
            const dx = e.touches[0].clientX - kcImageEdit.lastX;
            const dy = e.touches[0].clientY - kcImageEdit.lastY;
            kcImageEdit.lastX = e.touches[0].clientX;
            kcImageEdit.lastY = e.touches[0].clientY;
            kcImageEdit.offsetX += dx;
            kcImageEdit.offsetY += dy;
            renderImagePreview();
        }, { passive: true });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', async () => {
            if (!kcImageEdit.itemId) return;
            if (!kcImageEdit.fileSelected) {
                window.KinAlertModal.alert('Choose an image first.', 'Missing Image', 'OK');
                return;
            }
            const ok = await window.KinAlertModal.confirm('Replace the current image for this item?', 'Confirm Replace', 'Replace', 'Cancel');
            if (!ok) return;

            saveBtn.disabled = true;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Uploading...';
            try {
                const blob = await buildCroppedBlob();
                if (blob.size > 3 * 1024 * 1024) {
                    throw new Error('Processed image is too large. Try a smaller image.');
                }
                const file = new File([blob], 'menu.webp', { type: 'image/webp' });
                const fd = new FormData();
                fd.append('update_item_image', '1');
                fd.append('id', String(kcImageEdit.itemId));
                fd.append('image', file);
                const res = await fetch('menu_management.php', { method: 'POST', body: fd });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error(text ? text.slice(0, 200) : 'Invalid response'); }
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Upload failed.');
                }
                const card = document.querySelector('.menu-item-card[data-item-id="' + kcImageEdit.itemId + '"]');
                if (card) {
                    card.setAttribute('data-image', data.image || '');
                    const img = card.querySelector('.menu-item-thumb img');
                    if (img) {
                        img.src = data.url + '?v=' + Date.now();
                    } else {
                        const thumb = card.querySelector('.menu-item-thumb');
                        if (thumb) {
                            thumb.innerHTML = `<img src="${data.url}?v=${Date.now()}" alt="Item image">` + thumb.innerHTML;
                        }
                    }
                }
                $('#editImageModal').modal('hide');
            } catch (err) {
                const msg = (err && err.message) ? err.message : 'Upload failed.';
                window.KinAlertModal.alert(msg, 'Error', 'OK');
            } finally {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            }
        });
    }
});
</script>

    </div>

<div class="modal fade" id="editImageModal" tabindex="-1" role="dialog" aria-labelledby="editImageTitle" aria-modal="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editImageTitle">Edit Item Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Image (JPG/PNG/WebP, max 3MB)</label>
                    <input type="file" id="editImageFile" class="form-control-file" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="form-group">
                    <label>Zoom</label>
                    <input type="range" id="editImageZoom" class="custom-range" min="1" max="3" step="0.01" value="1">
                </div>
                <div class="text-muted mb-2">Drag the image to reposition.</div>
                <div class="d-flex justify-content-center">
                    <canvas id="editImageCanvas" width="320" height="320" style="border-radius:12px; border:1px solid var(--border); max-width:100%; height:auto;"></canvas>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editImageSaveBtn" disabled>Save Image</button>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
