<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication is required.']);
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';

requirePermission($pdo, 'pos.checkout', true);

function truncateOrderItemSnapshot(string $snapshot, int $maxLength = 150): string {
    if (mb_strlen($snapshot) <= $maxLength) {
        return $snapshot;
    }
    return mb_substr($snapshot, 0, $maxLength - 3) . '...';
}

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid checkout payload.']);
    exit;
}

$cart = $data['cart'] ?? [];
$payments = $data['payments'] ?? [];
$customerPayload = $data['customer'] ?? [];
$promoCode = trim((string) ($data['promoCode'] ?? ''));
$notes = trim((string) ($data['notes'] ?? ''));
if ($notes !== '') {
    $notes = substr($notes, 0, 300);
}

if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

if (!is_array($payments) || empty($payments)) {
    echo json_encode(['success' => false, 'message' => 'At least one payment entry is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $validatedItems = [];
    $subtotal = 0.0;

    foreach ($cart as $cartItem) {
        $menuItemId = isset($cartItem['id']) ? (int) $cartItem['id'] : 0;
        $quantity = isset($cartItem['quantity']) ? (int) $cartItem['quantity'] : 0;
        $variant = strtolower(trim((string) ($cartItem['variant'] ?? 'normal')));
        $temperature = strtolower(trim((string) ($cartItem['temperature'] ?? '')));
        $temperature = $temperature !== '' ? $temperature : null;
        $sizeLabel = trim((string) ($cartItem['sizeLabel'] ?? ''));
        $sizeLabel = $sizeLabel !== '' ? $sizeLabel : null;
        $categoryName = trim((string) ($cartItem['categoryName'] ?? ''));

        if ($menuItemId <= 0 || $quantity <= 0) {
            throw new InvalidArgumentException('The cart contains an invalid item or quantity.');
        }

        $stmt = $pdo->prepare('SELECT mi.*, mc.name AS category_name, mp.name AS parent_category_name
            FROM menu_items mi
            LEFT JOIN menu_categories mc ON mc.id = mi.category_id
            LEFT JOIN menu_categories mp ON mp.id = mc.parent_id
            WHERE mi.id = ?
            LIMIT 1');
        $stmt->execute([$menuItemId]);
        $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$menuItem) {
            throw new InvalidArgumentException('One of the selected products no longer exists.');
        }

        $availability = getMenuItemUnavailableDetails($pdo, $menuItem);
        if (empty($availability['available'])) {
            $itemLabel = (string) ($menuItem['name'] ?? 'Selected product');
            throw new InvalidArgumentException($itemLabel . ' is unavailable. ' . (string) $availability['reason']);
        }

        if (!in_array($variant, ['normal', 'solo', 'sharing'], true)) {
            throw new InvalidArgumentException('An invalid product option was submitted.');
        }

        if ($temperature !== null && !in_array($temperature, ['hot', 'iced'], true)) {
            throw new InvalidArgumentException('An invalid drink temperature was submitted.');
        }

        $requiresTemperature = menuItemRequiresTemperatureSelection($menuItem);
        $requiresSize = menuItemRequiresSizeSelection($menuItem);
        if ($requiresTemperature && $temperature === null) {
            throw new InvalidArgumentException('Select Hot Drink or Iced Drink for beverage items.');
        }
        if (!$requiresTemperature && $temperature !== null) {
            throw new InvalidArgumentException('Temperature selection is only allowed for drink items.');
        }
        if ($requiresTemperature && $variant !== 'normal') {
            throw new InvalidArgumentException('Hot or iced drink pricing cannot be combined with Solo or Sharing.');
        }
        if ($requiresSize && $sizeLabel === null) {
            throw new InvalidArgumentException('Select a size for this item.');
        }
        if (!$requiresSize && $sizeLabel !== null) {
            throw new InvalidArgumentException('Size selection is not allowed for this item.');
        }
        if ($requiresSize && ($variant !== 'normal' || $temperature !== null)) {
            throw new InvalidArgumentException('Size pricing cannot be combined with Solo, Sharing, or Hot/Iced.');
        }

        $unitPrice = getEffectiveMenuItemPrice($menuItem);
        if ($requiresSize) {
            $sizeLabel1 = trim((string) ($menuItem['size_label_1'] ?? ''));
            $sizeLabel2 = trim((string) ($menuItem['size_label_2'] ?? ''));
            if ($sizeLabel1 !== '' && strcasecmp($sizeLabel1, (string) $sizeLabel) === 0) {
                if ((float) ($menuItem['price_size_1'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('The selected size is not priced correctly.');
                }
                $sizeLabel = $sizeLabel1;
                $unitPrice = (float) $menuItem['price_size_1'];
            } elseif ($sizeLabel2 !== '' && strcasecmp($sizeLabel2, (string) $sizeLabel) === 0) {
                if ((float) ($menuItem['price_size_2'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('The selected size is not priced correctly.');
                }
                $sizeLabel = $sizeLabel2;
                $unitPrice = (float) $menuItem['price_size_2'];
            } else {
                throw new InvalidArgumentException('An invalid size was submitted.');
            }
        } elseif ($requiresTemperature) {
            if ($temperature === 'hot') {
                if ((float) ($menuItem['price_hot'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('Hot drink pricing is not available for the selected item.');
                }
                $unitPrice = (float) $menuItem['price_hot'];
            } elseif ($temperature === 'iced') {
                if ((float) ($menuItem['price_iced'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('Iced drink pricing is not available for the selected item.');
                }
                $unitPrice = (float) $menuItem['price_iced'];
            }
        } elseif ($variant === 'solo') {
            $unitPrice = (float) ($menuItem['price_solo'] ?? $unitPrice);
        } elseif ($variant === 'sharing') {
            $unitPrice = (float) ($menuItem['price_sharing'] ?? $unitPrice);
        }

        $lineTotal = round($unitPrice * $quantity, 2);
        $subtotal += $lineTotal;

        $customizations = [];
        if (!empty($cartItem['customizations']) && is_array($cartItem['customizations'])) {
            $customizations = $cartItem['customizations'];
        }

        $customLabelParts = [];
        foreach ($customizations as $custom) {
            if (!is_array($custom)) {
                continue;
            }
            if (empty($custom['include'])) {
                $customLabelParts[] = 'No ' . trim((string) ($custom['ingredientName'] ?? ''));
                continue;
            }
            $ingredientName = trim((string) ($custom['ingredientName'] ?? ''));
            $quantityLabel = number_format((float) ($custom['quantity'] ?? 0), 2);
            $unitLabel = trim((string) ($custom['unit'] ?? ''));
            if ($ingredientName !== '') {
                $customLabelParts[] = trim($ingredientName . ' ' . $quantityLabel . ($unitLabel !== '' ? ' ' . $unitLabel : ''));
            }
        }

        $itemNameSnapshot = $menuItem['name'];
        if ($customLabelParts) {
            $itemNameSnapshot .= ' [' . implode(', ', $customLabelParts) . ']';
        }
        $itemNameSnapshot = truncateOrderItemSnapshot($itemNameSnapshot);

        $validatedItems[] = [
            'menu_item_id' => $menuItemId,
            'name' => $menuItem['name'],
            'product_code' => $menuItem['product_code'],
            'quantity' => $quantity,
            'variant' => $variant,
            'temperature' => $temperature,
            'size_label' => $sizeLabel,
            'category_name' => $categoryName,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'customizations' => $customizations,
            'item_name_snapshot' => $itemNameSnapshot,
        ];
    }

    $ingredientRequirements = [];
    $recipeStmt = $pdo->prepare('SELECT ingredient_id, quantity, quantity_unit FROM menu_item_recipes WHERE menu_item_id = ?');
    foreach ($validatedItems as $validatedItem) {
        $recipeStmt->execute([(int) $validatedItem['menu_item_id']]);
        $recipeRows = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recipeRows as $recipeRow) {
            $ingredientId = (int) ($recipeRow['ingredient_id'] ?? 0);
            $ingredientQuantity = (float) ($recipeRow['quantity'] ?? 0);
            $recipeUnit = isset($recipeRow['quantity_unit']) ? (string) $recipeRow['quantity_unit'] : null;
            if ($ingredientId <= 0 || $ingredientQuantity <= 0) {
                continue;
            }

            $useIngredient = true;
            foreach ($validatedItem['customizations'] as $custom) {
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

            $ingredientRequirements[] = [
                'ingredient_id' => $ingredientId,
                'recipe_quantity' => $ingredientQuantity,
                'recipe_unit' => $recipeUnit,
                'quantity' => (float) $validatedItem['quantity'],
            ];
        }
    }

    if ($ingredientRequirements) {
        $ingredientIds = array_values(array_unique(array_map(static fn(array $req) => $req['ingredient_id'], $ingredientRequirements)));
        $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
        $ingredientStmt = $pdo->prepare("SELECT id, name, stock_quantity, unit FROM ingredients WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $ingredientStmt->execute($ingredientIds);
        $ingredientMap = [];
        foreach ($ingredientStmt->fetchAll(PDO::FETCH_ASSOC) as $ingredientRow) {
            $ingredientMap[(int) $ingredientRow['id']] = $ingredientRow;
        }

        $ingredientTotals = [];
        foreach ($ingredientRequirements as $requirement) {
            $ingredient = $ingredientMap[$requirement['ingredient_id']] ?? null;
            if (!$ingredient) {
                throw new InvalidArgumentException('One of the selected products requires an unavailable ingredient.');
            }

            $requiredPerItem = convertIngredientQuantityToStorage(
                (float) $requirement['recipe_quantity'], 
                $requirement['recipe_unit'], 
                (string) $ingredient['unit']
            );
            $requiredQuantity = round($requiredPerItem * (float) $requirement['quantity'], 2);
            $ingredientTotals[$requirement['ingredient_id']] = round((float) ($ingredientTotals[$requirement['ingredient_id']] ?? 0) + $requiredQuantity, 2);
        }

        foreach ($ingredientTotals as $ingredientId => $requiredTotal) {
            $ingredient = $ingredientMap[$ingredientId];
            if ((float) $ingredient['stock_quantity'] + 0.0001 < $requiredTotal) {
                throw new InvalidArgumentException('Insufficient stock for ingredient: ' . $ingredient['name'] . '.');
            }
        }
    }

    $isPwdSenior = !empty($payload['is_pwd_senior']);
    $pwdSeniorId = trim((string) ($payload['pwd_senior_id'] ?? ''));

    $promotion = null;
    $discountAmount = 0.0;
    $discountType = null;
    $taxAmount = 0.0;

    if ($isPwdSenior) {
        if ($pwdSeniorId === '') {
            throw new InvalidArgumentException('PWD / Senior Citizen ID number is required when applying statutory discount.');
        }
        $discountType = 'pwd_senior';
        $vatExclusiveSubtotal = round((float) $subtotal / 1.12, 2);
        $discountAmount = round($vatExclusiveSubtotal * 0.20, 2);
        $taxAmount = 0.00;
        $totalAmount = round(max($vatExclusiveSubtotal - $discountAmount, 0), 2);
    } elseif ($promoCode !== '') {
        $promotion = getActivePromotion($pdo, $promoCode, (float) $subtotal);
        if (!$promotion) {
            throw new InvalidArgumentException('Invalid or ineligible promo code.');
        }
        $discountType = 'promo';
        if ($promotion['discount_type'] === 'percent') {
            $discountAmount = (float) $subtotal * ((float) $promotion['discount_value'] / 100);
        } else {
            $discountAmount = (float) $promotion['discount_value'];
        }
        $discountAmount = round(min(max($discountAmount, 0), (float) $subtotal), 2);
        $totalAmount = round(max((float) $subtotal - $discountAmount, 0), 2);
        $taxAmount = round($totalAmount * 0.12, 2);
    } else {
        $totalAmount = round((float) $subtotal, 2);
        $taxAmount = round($totalAmount * 0.12, 2);
    }

    if ($totalAmount < 0) {
        throw new InvalidArgumentException('The calculated order total is invalid.');
    }

    $validatedPayments = [];
    $paidAmount = 0.0;

    $firstPayment = null;
    foreach ($payments as $payment) {
        $method = strtolower(trim((string) ($payment['method'] ?? '')));
        $amount = round((float) ($payment['amount'] ?? 0), 2);
        if ($method === '' || $amount <= 0) {
            continue;
        }
        $firstPayment = ['method' => $method, 'amount' => $amount];
        break;
    }

    if (!$firstPayment) {
        throw new InvalidArgumentException('Enter a cash amount before checkout.');
    }

    if ($firstPayment['method'] !== 'cash') {
        throw new InvalidArgumentException('This system is configured for cash-only transactions.');
    }

    $paidAmount = (float) $firstPayment['amount'];
    $validatedPayments[] = [
        'method' => 'cash',
        'amount' => $paidAmount,
        'reference' => null,
    ];

    if ($paidAmount + 0.001 < $totalAmount) {
        throw new InvalidArgumentException('Insufficient payment. Additional funds are required to complete the transaction.');
    }

    $changeAmount = round(max($paidAmount - $totalAmount, 0), 2);

    $customer = findOrCreateCustomer(
        $pdo,
        (string) ($customerPayload['name'] ?? '')
    );

    $receiptNumber = generateReceiptNumber();
    $primaryPaymentMethod = $validatedPayments[0]['method'];
    $stmt = $pdo->prepare("INSERT INTO orders (
            total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount,
            cash_received_amount, discount_type, pwd_senior_id,
            payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount,
            created_by, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 0, ?, ?)");
    $stmt->execute([
        $totalAmount,
        $subtotal,
        $taxAmount,
        $discountAmount,
        $paidAmount,
        $changeAmount,
        $paidAmount,
        $discountType,
        $pwdSeniorId ?: null,
        $primaryPaymentMethod,
        $receiptNumber,
        $customer['id'] ?? null,
        $_SESSION['admin_id'] ?? null,
        $notes ?: null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $orderItemStmt = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot, customizations) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($validatedItems as $item) {
        $labels = [];
        if ($item['variant'] !== 'normal') {
            $labels[] = ucfirst($item['variant']);
        }
        if (!empty($item['temperature'])) {
            $labels[] = ucfirst($item['temperature']);
        }
        if (!empty($item['size_label'])) {
            $labels[] = $item['size_label'];
        }
        $suffix = $labels ? ' (' . implode(', ', $labels) . ')' : '';
        $orderItemStmt->execute([
            $orderId,
            $item['menu_item_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['line_total'],
            $item['item_name_snapshot'] ?? ($item['name'] . $suffix),
            $item['product_code'],
            json_encode($item['customizations'] ?? []),
        ]);
    }

    $paymentStmt = $pdo->prepare('INSERT INTO order_payments (order_id, payment_method, amount) VALUES (?, ?, ?)');
    foreach ($validatedPayments as $payment) {
        $paymentStmt->execute([$orderId, $payment['method'], $payment['amount']]);
    }

    if ($promotion) {
        $discountStmt = $pdo->prepare('INSERT INTO order_discounts (order_id, promotion_id, discount_code, discount_amount) VALUES (?, ?, ?, ?)');
        $discountStmt->execute([$orderId, $promotion['id'], $promotion['code'], $discountAmount]);
    }

    $loyaltyPointsEarned = calculateLoyaltyPoints($pdo, $totalAmount);

    logTransactionEvent($pdo, 'checkout', 'success', $orderId, $receiptNumber, 'Order placed as pending', [
        'subtotal' => $subtotal,
        'tax_amount' => 0,
        'discount_amount' => $discountAmount,
        'total_amount' => $totalAmount,
        'payment_status' => 'pending',
    ]);
    logAuditEvent($pdo, 'order_created', 'order', $orderId, ['receipt_number' => $receiptNumber]);
    runAutomaticBackup($pdo, 'auto');

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order saved as pending. Staff can mark it complete from Orders.',
        'receipt' => [
            'order_id' => $orderId,
            'receipt_number' => $receiptNumber,
            'payment_status' => 'pending',
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'discount_type' => $discountType,
            'pwd_senior_id' => $pwdSeniorId,
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'change_amount' => round($changeAmount, 2),
            'items' => $validatedItems,
            'payments' => $validatedPayments,
            'customer' => $customer,
            'notes' => $notes,
            'promotion' => $promotion ? ['code' => $promotion['code'], 'name' => $promotion['name']] : null,
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'receipt_footer' => getSetting($pdo, 'receipt_footer', 'Thank you for visiting Kin Cafe.'),
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logTransactionEvent($pdo, 'checkout', 'failed', null, null, $exception->getMessage(), [
        'cart_count' => is_array($cart) ? count($cart) : 0,
        'promo_code' => $promoCode,
    ]);

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
}
?>
