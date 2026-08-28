<?php

function aiStandardDeviation(array $values): float {
    if (!$values) {
        return 0.0;
    }

    $mean = array_sum($values) / count($values);
    $variance = 0.0;
    foreach ($values as $value) {
        $variance += ($value - $mean) ** 2;
    }

    return sqrt($variance / count($values));
}

function aiFormatCustomerLabel(array $row): string {
    $name = trim((string) ($row['name'] ?? $row['customer_name'] ?? $row['customer_label'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $phone = trim((string) ($row['phone'] ?? ''));
    if ($phone !== '') {
        return $phone;
    }

    $customerId = (int) ($row['customer_id'] ?? $row['id'] ?? 0);
    return $customerId > 0 ? 'Customer #' . $customerId : 'Walk-in';
}

function aiFormatHourLabel(?int $hour): string {
    if ($hour === null || $hour < 0 || $hour > 23) {
        return 'No peak hour yet';
    }

    return sprintf('%02d:00-%02d:00', $hour, ($hour + 1) % 24);
}

function aiRuleBasedMethodLabel(string $context = 'analytics'): string {
    switch ($context) {
        case 'inventory_optimization':
            return aiInventoryOptimizationMethodLabel();
        case 'smart_reordering':
            return 'Powered by Consumption-Based Reorder Rules (14-day usage rate)';
        case 'recommendation_system':
            return aiRecommendationMethodLabel();
        default:
            return 'Powered by Rule-Based Analytics (SQL + heuristics)';
    }
}

function aiInventoryOptimizationMethodLabel(): string {
    return 'Powered by Consumption-Based Analytics (14-day usage rate)';
}

function aiRecommendationMethodLabel(): string {
    return 'Powered by Order-Pairing Analytics (SQL co-occurrence)';
}

function aiAnomalyMethodLabel(): string {
    return 'Powered by Z-Score & IQR Statistical Detection';
}

function aiBuildPosRecommendationCatalog(PDO $pdo): array {
    $pairRows = $pdo->query("SELECT
            LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_a,
            GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_b,
            COUNT(DISTINCT oi1.order_id) AS pair_count
        FROM order_items oi1
        JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi1.id < oi2.id
        JOIN orders o ON o.id = oi1.order_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot), GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot)
        ORDER BY pair_count DESC, item_a ASC, item_b ASC
        LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

    $pairs = [];
    foreach ($pairRows as $pairRow) {
        $itemA = trim((string) ($pairRow['item_a'] ?? ''));
        $itemB = trim((string) ($pairRow['item_b'] ?? ''));
        $pairCount = (int) ($pairRow['pair_count'] ?? 0);
        if ($itemA === '' || $itemB === '' || $pairCount <= 0) {
            continue;
        }

        $pairs[] = [
            'anchor_item' => $itemA,
            'recommended_item' => $itemB,
            'pair_count' => $pairCount,
            'reason' => 'Often ordered with ' . $itemA . ' (' . $pairCount . ' shared orders)',
        ];
        $pairs[] = [
            'anchor_item' => $itemB,
            'recommended_item' => $itemA,
            'pair_count' => $pairCount,
            'reason' => 'Often ordered with ' . $itemB . ' (' . $pairCount . ' shared orders)',
        ];
    }

    $popularRows = $pdo->query("SELECT
            COALESCE(mi.name, oi.item_name_snapshot) AS item_name,
            COALESCE(SUM(oi.quantity), 0) AS total_qty
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY COALESCE(mi.name, oi.item_name_snapshot)
        ORDER BY total_qty DESC, item_name ASC
        LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    $popular = [];
    foreach ($popularRows as $popularRow) {
        $itemName = trim((string) ($popularRow['item_name'] ?? ''));
        if ($itemName === '') {
            continue;
        }
        $popular[] = [
            'recommended_item' => $itemName,
            'total_qty' => (int) ($popularRow['total_qty'] ?? 0),
            'reason' => 'Top seller in the last 30 days',
        ];
    }

    return [
        'pairs' => $pairs,
        'popular' => $popular,
    ];
}

function aiEnrichPosRecommendations(array $catalog, array $payloadByName): array {
    $resolvePayload = static function (string $itemName) use ($payloadByName): ?array {
        $key = strtolower(trim($itemName));
        return $payloadByName[$key] ?? null;
    };

    $pairs = [];
    $seenPairKeys = [];
    foreach ($catalog['pairs'] ?? [] as $pairRow) {
        $recommendedName = trim((string) ($pairRow['recommended_item'] ?? ''));
        $payload = $resolvePayload($recommendedName);
        if (!$payload) {
            continue;
        }
        $pairKey = strtolower((string) ($pairRow['anchor_item'] ?? '')) . '|' . strtolower($recommendedName);
        if (isset($seenPairKeys[$pairKey])) {
            continue;
        }
        $seenPairKeys[$pairKey] = true;
        $pairs[] = [
            'anchor_item' => (string) ($pairRow['anchor_item'] ?? ''),
            'recommended_item' => $recommendedName,
            'pair_count' => (int) ($pairRow['pair_count'] ?? 0),
            'reason' => (string) ($pairRow['reason'] ?? ''),
            'item' => $payload,
        ];
    }

    $popular = [];
    $seenPopular = [];
    foreach ($catalog['popular'] ?? [] as $popularRow) {
        $recommendedName = trim((string) ($popularRow['recommended_item'] ?? ''));
        $key = strtolower($recommendedName);
        if (isset($seenPopular[$key])) {
            continue;
        }
        $payload = $resolvePayload($recommendedName);
        if (!$payload) {
            continue;
        }
        $seenPopular[$key] = true;
        $popular[] = [
            'recommended_item' => $recommendedName,
            'total_qty' => (int) ($popularRow['total_qty'] ?? 0),
            'reason' => (string) ($popularRow['reason'] ?? ''),
            'item' => $payload,
        ];
    }

    return [
        'pairs' => $pairs,
        'popular' => $popular,
        'method_label' => aiRecommendationMethodLabel(),
    ];
}

function getPosRecommendationData(PDO $pdo, array $availableItemPayloads): array {
    $payloadByName = [];
    foreach ($availableItemPayloads as $payload) {
        if (empty($payload['available'])) {
            continue;
        }
        $payloadByName[strtolower(trim((string) ($payload['name'] ?? '')))] = $payload;
    }

    return aiEnrichPosRecommendations(aiBuildPosRecommendationCatalog($pdo), $payloadByName);
}

function aiBuildForecastStockUpGuidance(PDO $pdo, float $forecastNextWeek, float $averageDailySales, array $demandPredictions, array $smartReordering): array {
    $baselineWeek = max(0.01, $averageDailySales * 7);
    $upliftFactor = $forecastNextWeek > 0 ? max(1.0, $forecastNextWeek / $baselineWeek) : 1.0;
    $upliftPercent = round(($upliftFactor - 1) * 100, 1);

    $guidance = [];
    $seenIngredients = [];

    foreach (array_slice($smartReordering, 0, 6) as $row) {
        $ingredientName = (string) ($row['name'] ?? '');
        if ($ingredientName === '' || isset($seenIngredients[$ingredientName])) {
            continue;
        }
        $seenIngredients[$ingredientName] = true;
        $baseOrder = (float) ($row['recommended_order'] ?? 0);
        $adjustedOrder = round(max($baseOrder, $baseOrder * $upliftFactor), 2);
        $reason = 'Below reorder point based on 14-day consumption';
        if ($upliftFactor > 1.05) {
            $reason .= '; sales forecast uplift +' . $upliftPercent . '%';
        }

        $guidance[] = [
            'ingredient' => $ingredientName,
            'unit' => (string) ($row['unit'] ?? ''),
            'current_stock' => (float) ($row['current_stock'] ?? 0),
            'recommended_order' => $adjustedOrder,
            'reason' => $reason,
            'source' => 'smart_reordering',
            'linked_menu_item' => null,
        ];
    }

    if (tableExists($pdo, 'menu_item_recipes')) {
        foreach (array_slice($demandPredictions, 0, 5) as $demandRow) {
            $menuItemId = (int) ($demandRow['id'] ?? 0);
            $menuItemName = (string) ($demandRow['name'] ?? '');
            $predictedUnits = (float) ($demandRow['predicted_week_units'] ?? 0);
            if ($menuItemId <= 0 || $menuItemName === '' || $predictedUnits <= 0) {
                continue;
            }

            $recipeStmt = $pdo->prepare("SELECT ing.id,
                    ing.name,
                    ing.unit,
                    ing.stock_quantity,
                    mir.quantity
                FROM menu_item_recipes mir
                JOIN ingredients ing ON ing.id = mir.ingredient_id
                WHERE mir.menu_item_id = ?
                  AND ing.deleted_at IS NULL");
            $recipeStmt->execute([$menuItemId]);
            foreach ($recipeStmt->fetchAll(PDO::FETCH_ASSOC) as $recipeRow) {
                $ingredientName = (string) ($recipeRow['name'] ?? '');
                if ($ingredientName === '' || isset($seenIngredients[$ingredientName])) {
                    continue;
                }

                $qtyPerUnit = (float) ($recipeRow['quantity'] ?? 0);
                $estimatedNeed = round($qtyPerUnit * $predictedUnits * $upliftFactor, 2);
                $currentStock = (float) ($recipeRow['stock_quantity'] ?? 0);
                $recommendedOrder = max(0.0, round($estimatedNeed - $currentStock, 2));
                if ($recommendedOrder <= 0) {
                    continue;
                }

                $seenIngredients[$ingredientName] = true;
                $guidance[] = [
                    'ingredient' => $ingredientName,
                    'unit' => (string) ($recipeRow['unit'] ?? ''),
                    'current_stock' => $currentStock,
                    'recommended_order' => $recommendedOrder,
                    'reason' => 'Forecast demand for ' . $menuItemName . ' (~' . (int) round($predictedUnits) . ' units/week)',
                    'source' => 'demand_forecast',
                    'linked_menu_item' => $menuItemName,
                ];
            }
        }
    }

    usort($guidance, static function (array $left, array $right): int {
        return ($right['recommended_order'] <=> $left['recommended_order'])
            ?: strcmp((string) ($left['ingredient'] ?? ''), (string) ($right['ingredient'] ?? ''));
    });

    return [
        'items' => array_slice($guidance, 0, 8),
        'uplift_factor' => round($upliftFactor, 2),
        'uplift_percent' => $upliftPercent,
        'forecast_next_week' => $forecastNextWeek,
        'baseline_week' => round($baselineWeek, 2),
    ];
}

function aiResolveForecastPeakWindow(array $salesForecast, string $historicalPeakDay, ?int $historicalPeakHour, array $demandPredictions): array {
    $peakDayLabel = $historicalPeakDay;
    $peakHourLabel = aiFormatHourLabel($historicalPeakHour);
    $peakDaySource = 'Historical baseline (60-day completed orders)';

    if (empty($salesForecast['insufficient_data']) && !empty($salesForecast['peak_day'])) {
        $peakDayLabel = (string) $salesForecast['peak_day'];
        $peakDaySource = 'ARIMA(1,1,1) 7-day forecast';
    }

    $demandPeakHours = [];
    foreach ($demandPredictions as $demandRow) {
        if (!empty($demandRow['insufficient_data'])) {
            continue;
        }
        if (!isset($demandRow['peak_service_window'])) {
            continue;
        }
        $hour = (int) $demandRow['peak_service_window'];
        if ($hour < 0 || $hour > 23) {
            continue;
        }
        $demandPeakHours[$hour] = ($demandPeakHours[$hour] ?? 0) + 1;
    }
    if ($demandPeakHours) {
        arsort($demandPeakHours);
        $peakHourLabel = aiFormatHourLabel((int) array_key_first($demandPeakHours));
    }

    return [
        'peak_day_label' => $peakDayLabel,
        'peak_hour_label' => $peakHourLabel,
        'peak_day_source' => $peakDaySource,
    ];
}

function aiDetectSalesAnomalies(array $dailySales, float $averageDailySales): array {
    if (count($dailySales) < 5 || $averageDailySales <= 0) {
        return [];
    }

    $values = array_map(static function (array $row): float {
        return (float) ($row['daily_sales'] ?? 0);
    }, $dailySales);
    $stdDev = aiStandardDeviation($values);
    if ($stdDev <= 0) {
        return [];
    }

    $valuesCopy = $values;
    sort($valuesCopy);
    $count = count($valuesCopy);
    $q1Index = (int) floor(($count - 1) * 0.25);
    $q3Index = (int) floor(($count - 1) * 0.75);
    $q1 = $valuesCopy[$q1Index];
    $q3 = $valuesCopy[$q3Index];
    $iqr = max(0.0, $q3 - $q1);
    $iqrLower = $q1 - (1.5 * $iqr);
    $iqrUpper = $q3 + (1.5 * $iqr);

    $anomalies = [];
    foreach ($dailySales as $salesRow) {
        $dailyValue = (float) ($salesRow['daily_sales'] ?? 0);
        $zScore = abs($dailyValue - $averageDailySales) / $stdDev;
        $isZScoreAnomaly = $zScore >= 2.0;
        $isIqrAnomaly = $dailyValue < $iqrLower || $dailyValue > $iqrUpper;
        if (!$isZScoreAnomaly && !$isIqrAnomaly) {
            continue;
        }

        $methods = [];
        if ($isZScoreAnomaly) {
            $methods[] = 'Z-score';
        }
        if ($isIqrAnomaly) {
            $methods[] = 'IQR';
        }

        $anomalies[] = [
            'sale_date' => (string) ($salesRow['sale_date'] ?? ''),
            'daily_sales' => $dailyValue,
            'type' => $dailyValue > $averageDailySales ? 'Spike' : 'Drop',
            'gap_amount' => round(abs($dailyValue - $averageDailySales), 2),
            'z_score' => round($zScore, 2),
            'detection_method' => implode(' + ', $methods),
        ];
    }

    return array_slice($anomalies, -5);
}

function aiGetCustomerProfiles(PDO $pdo, int $limit = 12): array {
    if (!tableExists($pdo, 'customers')) {
        return [];
    }

    $limit = max(1, $limit);
    $stmt = $pdo->query("SELECT c.id AS customer_id,
            COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(c.phone), ''), CONCAT('Customer #', c.id)) AS customer_label,
            COUNT(DISTINCT o.id) AS order_count,
            COALESCE(SUM(GREATEST(o.total_amount - o.refund_amount, 0)), 0) AS total_spend,
            MAX(o.created_at) AS last_order_at,
            (
                SELECT COALESCE(mi2.name, oi2.item_name_snapshot)
                FROM order_items oi2
                JOIN orders o2 ON o2.id = oi2.order_id
                LEFT JOIN menu_items mi2 ON mi2.id = oi2.menu_item_id
                WHERE o2.customer_id = c.id
                  AND o2.payment_status = 'completed'
                  AND o2.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY COALESCE(mi2.name, oi2.item_name_snapshot)
                ORDER BY SUM(oi2.quantity) DESC
                LIMIT 1
            ) AS favorite_item,
            (
                SELECT COALESCE(mc2.name, 'Uncategorized')
                FROM order_items oi3
                JOIN orders o3 ON o3.id = oi3.order_id
                LEFT JOIN menu_items mi3 ON mi3.id = oi3.menu_item_id
                LEFT JOIN menu_categories mc2 ON mc2.id = mi3.category_id
                WHERE o3.customer_id = c.id
                  AND o3.payment_status = 'completed'
                  AND o3.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY COALESCE(mc2.name, 'Uncategorized')
                ORDER BY SUM(oi3.quantity) DESC
                LIMIT 1
            ) AS favorite_category
        FROM customers c
        JOIN orders o ON o.customer_id = c.id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY c.id, c.name, c.phone
        HAVING order_count >= 2
        ORDER BY order_count DESC, total_spend DESC, customer_label ASC
        LIMIT " . (int) $limit);

    $profiles = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orderCount = (int) ($row['order_count'] ?? 0);
        $profiles[] = [
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_label' => (string) ($row['customer_label'] ?? 'Customer'),
            'order_count' => $orderCount,
            'total_spend' => round((float) ($row['total_spend'] ?? 0), 2),
            'last_order_at' => (string) ($row['last_order_at'] ?? ''),
            'favorite_item' => (string) ($row['favorite_item'] ?? 'Unknown item'),
            'favorite_category' => (string) ($row['favorite_category'] ?? 'Uncategorized'),
            'repeat_status' => $orderCount >= 5 ? 'Loyal repeat' : 'Repeat customer',
        ];
    }

    return $profiles;
}

function aiGetTopMenuItemPerformance(PDO $pdo, int $limit = 12): array {
    $limit = max(1, $limit);
    $stmt = $pdo->query("SELECT mi.id,
            mi.name,
            mi.price,
            mi.available,
            COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS total_qty,
            COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN GREATEST(oi.line_total, 0) ELSE 0 END), 0) AS total_sales,
            MAX(CASE WHEN o.id IS NOT NULL THEN o.created_at ELSE NULL END) AS last_sold_at
        FROM menu_items mi
        LEFT JOIN order_items oi ON oi.menu_item_id = mi.id
        LEFT JOIN orders o ON o.id = oi.order_id
            AND o.payment_status = 'completed'
            AND o.transaction_status <> 'refunded'
            AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY mi.id, mi.name, mi.price, mi.available
        ORDER BY total_qty DESC, mi.name ASC
        LIMIT " . (int) $limit);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function aiGetItemDailyDemandSeries(PDO $pdo, int $itemId, int $days = 90): array {
    $days = max(1, $days);
    $stmt = $pdo->prepare("SELECT DATE(o.created_at) AS sale_date,
            COALESCE(SUM(oi.quantity), 0) AS quantity
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.menu_item_id = ?
          AND o.payment_status = 'completed'
          AND o.transaction_status <> 'refunded'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(o.created_at)
        ORDER BY sale_date ASC");
    $stmt->execute([$itemId, $days]);

    $series = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $series[] = [
            'date' => (string) ($row['sale_date'] ?? ''),
            'quantity' => round((float) ($row['quantity'] ?? 0), 2),
        ];
    }

    return $series;
}

function aiGetItemHourlyDemandDistribution(PDO $pdo, int $itemId, int $days = 90): array {
    $days = max(1, $days);
    $stmt = $pdo->prepare("SELECT HOUR(o.created_at) AS sale_hour,
            COALESCE(SUM(oi.quantity), 0) AS quantity
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE oi.menu_item_id = ?
          AND o.payment_status = 'completed'
          AND o.transaction_status <> 'refunded'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY HOUR(o.created_at)
        ORDER BY sale_hour ASC");
    $stmt->execute([$itemId, $days]);

    $distribution = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $hour = (int) ($row['sale_hour'] ?? 0);
        $distribution[(string) $hour] = round((float) ($row['quantity'] ?? 0), 2);
    }

    return $distribution;
}

function aiBuildMovingAverageDemandPrediction(array $itemRow, string $peakDayLabel, ?int $peakHour): array {
    $totalQty = (float) ($itemRow['total_qty'] ?? 0);
    $dailyUnits = round($totalQty / 90, 2);
    $predictedWeekUnits = round($dailyUnits * 7, 1);
    $demandStrength = 'Moderate';
    if ($totalQty >= 60) {
        $demandStrength = 'High';
    } elseif ($totalQty <= 15) {
        $demandStrength = 'Low';
    }

    return [
        'item_id' => (int) ($itemRow['id'] ?? 0),
        'name' => (string) ($itemRow['name'] ?? ''),
        'total_qty' => $totalQty,
        'predicted_week_units' => $predictedWeekUnits,
        'next_day_forecast' => $dailyUnits,
        'daily_units' => $dailyUnits,
        'demand_strength' => $demandStrength,
        'service_window' => $peakDayLabel . ' / ' . aiFormatHourLabel($peakHour),
        'peak_service_window' => $peakHour,
        'method_used' => 'Moving Average',
        'method_label' => formatForecastMethodLabel('moving_average', true),
        'insufficient_data' => true,
    ];
}

function aiBuildDemandForecastItems(PDO $pdo, string $peakDayLabel, ?int $peakHour): array {
    $itemPerformance = aiGetTopMenuItemPerformance($pdo, 12);
    $topItems = array_values(array_filter($itemPerformance, static function (array $row): bool {
        return (float) ($row['total_qty'] ?? 0) > 0;
    }));
    $topItems = array_slice($topItems, 0, 8);

    if (!$topItems) {
        return [
            'items' => [],
            'method_used' => 'Moving Average',
            'method_label' => formatForecastMethodLabel('moving_average', true),
        ];
    }

    if (!function_exists('callForecastService')) {
        require_once __DIR__ . '/forecast_client.php';
    }

    $serviceItems = [];
    foreach ($topItems as $itemRow) {
        $itemId = (int) ($itemRow['id'] ?? 0);
        if ($itemId <= 0) {
            continue;
        }

        $serviceItems[] = [
            'item_id' => $itemId,
            'name' => (string) ($itemRow['name'] ?? ''),
            'daily_series' => aiGetItemDailyDemandSeries($pdo, $itemId, 90),
            'hourly_distribution' => aiGetItemHourlyDemandDistribution($pdo, $itemId, 90),
        ];
    }

    $serviceResponse = $serviceItems ? callForecastService('demand', ['items' => $serviceItems]) : null;
    $serviceItemsById = [];
    if (is_array($serviceResponse['items'] ?? null)) {
        foreach ($serviceResponse['items'] as $serviceItem) {
            $serviceItemsById[(int) ($serviceItem['item_id'] ?? 0)] = $serviceItem;
        }
    }

    $demandPredictions = [];
    $methodLabels = [];
    foreach ($topItems as $itemRow) {
        $itemId = (int) ($itemRow['id'] ?? 0);
        $fallbackPrediction = aiBuildMovingAverageDemandPrediction($itemRow, $peakDayLabel, $peakHour);
        $serviceItem = $serviceItemsById[$itemId] ?? null;

        if ($serviceResponse === null) {
            $fallbackPrediction['method_label'] = formatForecastMethodLabel('moving_average', false, true);
            $demandPredictions[] = $fallbackPrediction;
            $methodLabels[] = $fallbackPrediction['method_label'];
            continue;
        }

        if (!$serviceItem || !empty($serviceItem['insufficient_data'])) {
            $demandPredictions[] = $fallbackPrediction;
            $methodLabels[] = $fallbackPrediction['method_label'];
            continue;
        }

        $sevenDayForecast = [];
        foreach (($serviceItem['7day_forecast'] ?? []) as $value) {
            $sevenDayForecast[] = round((float) $value, 2);
        }
        $predictedWeekUnits = round((float) ($serviceItem['7day_forecast_total'] ?? array_sum($sevenDayForecast)), 1);
        $nextDayForecast = round((float) ($serviceItem['next_day_forecast'] ?? ($sevenDayForecast[0] ?? 0)), 2);
        $dailyUnits = round($predictedWeekUnits / 7, 2);
        $itemPeakHour = isset($serviceItem['peak_service_window']) ? (int) $serviceItem['peak_service_window'] : $peakHour;
        $demandStrength = 'Moderate';
        if ($predictedWeekUnits >= 20) {
            $demandStrength = 'High';
        } elseif ($predictedWeekUnits <= 5) {
            $demandStrength = 'Low';
        }

        $methodUsed = (string) ($serviceItem['model_used'] ?? ($serviceResponse['model_used'] ?? 'SARIMA(1,1,1)(1,1,1,7)'));
        $methodLabel = formatForecastMethodLabel($methodUsed);
        $demandPredictions[] = [
            'item_id' => $itemId,
            'name' => (string) ($itemRow['name'] ?? ''),
            'total_qty' => (float) ($itemRow['total_qty'] ?? 0),
            'predicted_week_units' => $predictedWeekUnits,
            'next_day_forecast' => $nextDayForecast,
            'daily_units' => $dailyUnits,
            'demand_strength' => $demandStrength,
            'service_window' => $peakDayLabel . ' / ' . aiFormatHourLabel($itemPeakHour),
            'peak_service_window' => $itemPeakHour,
            'forecast_7day' => $sevenDayForecast,
            'method_used' => $methodUsed,
            'method_label' => $methodLabel,
            'insufficient_data' => false,
        ];
        $methodLabels[] = $methodLabel;
    }

    $uniqueMethodLabels = array_values(array_unique($methodLabels));
    $overallMethodLabel = count($uniqueMethodLabels) === 1
        ? $uniqueMethodLabels[0]
        : 'Powered by Mixed Forecast Models';

    return [
        'items' => $demandPredictions,
        'method_used' => (string) ($serviceResponse['model_used'] ?? 'mixed'),
        'method_label' => $overallMethodLabel,
    ];
}

function aiGetConfigValue(PDO $pdo, string $envKey, string $settingKey, string $default = ''): string {
    $envValue = getenv($envKey);
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }

    return trim((string) getSetting($pdo, $settingKey, $default));
}

function aiGetBooleanConfigValue(PDO $pdo, string $envKey, string $settingKey, bool $default = false): bool {
    $envValue = getenv($envKey);
    if (is_string($envValue) && trim($envValue) !== '') {
        return in_array(strtolower(trim($envValue)), ['1', 'true', 'yes', 'on'], true);
    }

    return in_array(strtolower(trim((string) getSetting($pdo, $settingKey, $default ? '1' : '0'))), ['1', 'true', 'yes', 'on'], true);
}

function aiGetVirtualAssistantConfig(PDO $pdo): array {
    return [
        'enabled' => aiGetBooleanConfigValue($pdo, 'KIN_CAFE_AI_ENABLED', 'ai_assistant_enabled', false),
        'provider' => aiGetConfigValue($pdo, 'KIN_CAFE_AI_PROVIDER', 'ai_assistant_provider', 'openai-compatible'),
        'endpoint' => aiGetConfigValue($pdo, 'KIN_CAFE_AI_ENDPOINT', 'ai_assistant_endpoint', 'https://api.openai.com/v1/chat/completions'),
        'model' => aiGetConfigValue($pdo, 'KIN_CAFE_AI_MODEL', 'ai_assistant_model', 'gpt-4o-mini'),
        'api_key' => aiGetConfigValue($pdo, 'KIN_CAFE_AI_API_KEY', 'ai_assistant_api_key', ''),
        'system_prompt' => aiGetConfigValue(
            $pdo,
            'KIN_CAFE_AI_SYSTEM_PROMPT',
            'ai_assistant_system_prompt',
            'You are the Kin Cafe virtual assistant. Answer only with information grounded in the provided business context. If the answer is not supported by the context, say that the system does not currently have enough verified data.'
        ),
        'timeout_seconds' => max(5, (int) aiGetConfigValue($pdo, 'KIN_CAFE_AI_TIMEOUT_SECONDS', 'ai_assistant_timeout_seconds', '20')),
    ];
}

function aiBuildKitchenWorkflowOptimization(PDO $pdo): array {
    $rows = $pdo->query("SELECT o.id,
            o.receipt_number,
            o.created_at,
            o.total_amount,
            COALESCE(c.name, 'Walk-in') AS customer_name,
            COUNT(oi.id) AS item_count,
            COALESCE(SUM(oi.quantity), 0) AS total_units
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.payment_status = 'pending'
        GROUP BY o.id, o.receipt_number, o.created_at, o.total_amount, c.name
        ORDER BY o.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

    $queue = [];
    $waitSamples = [];

    foreach ($rows as $row) {
        $createdAt = strtotime((string) ($row['created_at'] ?? ''));
        $waitingMinutes = $createdAt !== false ? max(0, (int) floor((time() - $createdAt) / 60)) : 0;
        $itemCount = max(0, (int) ($row['item_count'] ?? 0));
        $totalUnits = max($itemCount, (int) ($row['total_units'] ?? 0));
        $totalAmount = (float) ($row['total_amount'] ?? 0);

        $complexityScore = min(20.0, ($itemCount * 3.0) + ($totalUnits * 1.5));
        $valueScore = min(12.0, round($totalAmount / 60, 1));
        $urgencyBoost = $waitingMinutes >= 30 ? 12.0 : ($waitingMinutes >= 15 ? 6.0 : 0.0);
        $priorityScore = round($waitingMinutes + $complexityScore + $valueScore + $urgencyBoost, 1);

        $reasons = [];
        if ($waitingMinutes >= 20) {
            $reasons[] = 'Longest waiting';
        }
        if ($itemCount >= 4 || $totalUnits >= 6) {
            $reasons[] = 'Large prep load';
        }
        if ($totalAmount >= 300) {
            $reasons[] = 'High-value ticket';
        }
        if (!$reasons) {
            $reasons[] = 'Fits current flow';
        }

        $priorityLabel = 'Ready soon';
        if ($priorityScore >= 45) {
            $priorityLabel = 'Rush first';
        } elseif ($priorityScore >= 28) {
            $priorityLabel = 'Prepare next';
        }

        $waitSamples[] = $waitingMinutes;
        $queue[] = [
            'id' => (int) ($row['id'] ?? 0),
            'receipt_number' => (string) ($row['receipt_number'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'total_amount' => $totalAmount,
            'customer_name' => (string) ($row['customer_name'] ?? 'Walk-in'),
            'item_count' => $itemCount,
            'total_units' => $totalUnits,
            'waiting_minutes' => $waitingMinutes,
            'priority_score' => $priorityScore,
            'priority_label' => $priorityLabel,
            'reasoning' => implode(' • ', $reasons),
        ];
    }

    usort($queue, static function (array $left, array $right): int {
        $scoreCompare = ($right['priority_score'] <=> $left['priority_score']);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        return strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
    });

    return [
        'queue' => array_slice($queue, 0, 5),
        'next_up' => $queue[0] ?? null,
        'pending_count' => count($rows),
        'high_priority_count' => count(array_filter($queue, static function (array $row): bool {
            return ($row['priority_label'] ?? '') === 'Rush first';
        })),
        'average_wait_minutes' => $waitSamples ? round(array_sum($waitSamples) / count($waitSamples), 1) : 0.0,
    ];
}

function aiBuildSignals(PDO $pdo): array {
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $today = getSalesSummary($pdo, date('Y-m-d'));
    $salesForecast = getSalesForecastData($pdo, 7);
    $forecastNextWeek = (float) ($salesForecast['forecast_total'] ?? forecastSalesMovingAverage($pdo, 7, 30));
    $pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn();
    $kitchenWorkflowOptimization = aiBuildKitchenWorkflowOptimization($pdo);

    $dailySales = $pdo->query("SELECT DATE(created_at) AS sale_date,
            COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS daily_sales
        FROM orders
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC")->fetchAll(PDO::FETCH_ASSOC);

    $dailySalesValues = array_map(static function (array $row): float {
        return (float) ($row['daily_sales'] ?? 0);
    }, $dailySales);
    $averageDailySales = $dailySalesValues ? array_sum($dailySalesValues) / count($dailySalesValues) : 0.0;
    $salesDeviation = aiStandardDeviation($dailySalesValues);

    $weeklyComparison = $pdo->query("SELECT
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END), 0) AS current_week_sales,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN GREATEST(total_amount - refund_amount, 0) ELSE 0 END), 0) AS previous_week_sales
        FROM orders
        WHERE payment_status = 'completed'")->fetch(PDO::FETCH_ASSOC) ?: [];

    $currentWeekSales = (float) ($weeklyComparison['current_week_sales'] ?? 0);
    $previousWeekSales = (float) ($weeklyComparison['previous_week_sales'] ?? 0);
    $trendLabel = 'Stable versus last week';
    if ($previousWeekSales > 0 || $currentWeekSales > 0) {
        $trend = buildPercentageTrend($currentWeekSales, $previousWeekSales);
        $trendLabel = $trend['value'] . ' versus last week';
    }

    $weekdayRows = $pdo->query("SELECT DAYOFWEEK(created_at) AS weekday_number,
            COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS sales_total,
            COUNT(*) AS order_count
        FROM orders
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY DAYOFWEEK(created_at)
        ORDER BY sales_total DESC, order_count DESC
        LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

    $weekdayNames = [1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'];
    $peakDayLabel = $weekdayRows ? ($weekdayNames[(int) $weekdayRows['weekday_number']] ?? 'Unknown day') : 'No sales pattern yet';

    $hourlyRows = $pdo->query("SELECT HOUR(created_at) AS sale_hour,
            COUNT(*) AS order_count,
            COALESCE(SUM(GREATEST(total_amount - refund_amount, 0)), 0) AS sales_total
        FROM orders
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY HOUR(created_at)
        ORDER BY order_count DESC, sales_total DESC
        LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    $peakHour = $hourlyRows ? (int) $hourlyRows['sale_hour'] : null;

    $demandForecast = aiBuildDemandForecastItems($pdo, $peakDayLabel, $peakHour);
    $demandPredictions = $demandForecast['items'];
    $forecastPeakWindow = aiResolveForecastPeakWindow($salesForecast, $peakDayLabel, $peakHour, $demandPredictions);

    $itemPerformance = $pdo->query("SELECT mi.id,
            mi.name,
            mi.price,
            mi.available,
            COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS total_qty,
            COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN GREATEST(oi.line_total, 0) ELSE 0 END), 0) AS total_sales,
            MAX(CASE WHEN o.id IS NOT NULL THEN o.created_at ELSE NULL END) AS last_sold_at
        FROM menu_items mi
        LEFT JOIN order_items oi ON oi.menu_item_id = mi.id
        LEFT JOIN orders o ON o.id = oi.order_id
            AND o.payment_status = 'completed'
            AND o.transaction_status <> 'refunded'
            AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY mi.id, mi.name, mi.price, mi.available
        ORDER BY total_qty DESC, mi.name ASC
        LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);

    $topItems = array_values(array_filter($itemPerformance, static function (array $row): bool {
        return (float) ($row['total_qty'] ?? 0) > 0;
    }));

    $lowStockIngredients = [];
    $expiringIngredients = [];
    $inventoryOptimization = [];
    $smartReordering = [];
    $wasteAlerts = [];
    $inventoryAnomalies = [];
    if (tableExists($pdo, 'ingredients')) {
        $lowStockIngredients = $pdo->query("SELECT name, stock_quantity, unit
            FROM ingredients
            WHERE deleted_at IS NULL AND stock_quantity < 10
            ORDER BY stock_quantity ASC, name ASC
            LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
        $expiringIngredients = getExpiringIngredients($pdo, 14);

        $inventoryRows = $pdo->query("SELECT ing.id,
                ing.name,
                ing.unit,
                ing.stock_quantity,
                ing.expiration_date,
                COALESCE(SUM(CASE WHEN il.timestamp >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND il.action IN ('remove', 'sale') THEN ABS(il.quantity) ELSE 0 END), 0) AS used_14_days,
                COALESCE(SUM(CASE WHEN il.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND il.action = 'add' THEN ABS(il.quantity) ELSE 0 END), 0) AS replenished_30_days
            FROM ingredients ing
            LEFT JOIN inventory_logs il ON il.ingredient_id = ing.id
            WHERE ing.deleted_at IS NULL
            GROUP BY ing.id, ing.name, ing.unit, ing.stock_quantity, ing.expiration_date
            ORDER BY ing.name ASC")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inventoryRows as $ingredientRow) {
            $dailyUsage = round(((float) $ingredientRow['used_14_days']) / 14, 2);
            $targetStock = max(5.0, ceil(($dailyUsage * 10) + 2));
            $reorderPoint = max(3.0, ceil(($dailyUsage * 4) + 1));
            $currentStock = (float) $ingredientRow['stock_quantity'];
            $recommendedOrder = max(0.0, round($targetStock - $currentStock, 2));
            $daysRemaining = $dailyUsage > 0 ? round($currentStock / $dailyUsage, 1) : null;
            $status = 'Balanced';
            if ($currentStock < $reorderPoint) {
                $status = 'Under target';
            } elseif ($currentStock > ($targetStock * 1.5)) {
                $status = 'Overstocked';
            }

            $stockPressure = $reorderPoint > 0
                ? round(max(0.0, ($reorderPoint - $currentStock) / $reorderPoint), 3)
                : 0.0;

            $optimizationRow = [
                'name' => (string) $ingredientRow['name'],
                'unit' => (string) $ingredientRow['unit'],
                'current_stock' => $currentStock,
                'daily_usage' => $dailyUsage,
                'target_stock' => $targetStock,
                'reorder_point' => $reorderPoint,
                'recommended_order' => $recommendedOrder,
                'days_remaining' => $daysRemaining,
                'status' => $status,
                'stock_pressure' => $stockPressure,
            ];
            $inventoryOptimization[] = $optimizationRow;
            if ($recommendedOrder > 0 || $currentStock < $reorderPoint) {
                $smartReordering[] = $optimizationRow;
            }
        }

        usort($inventoryOptimization, static function (array $left, array $right): int {
            return ($right['stock_pressure'] <=> $left['stock_pressure'])
                ?: strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });
        usort($smartReordering, static function (array $left, array $right): int {
            return ($right['recommended_order'] <=> $left['recommended_order']) ?: strcmp($left['name'], $right['name']);
        });

        $wasteAlerts = $pdo->query("SELECT ing.name,
                ABS(il.quantity) AS quantity,
                COALESCE(NULLIF(il.reason, ''), 'No reason provided') AS reason,
                il.timestamp
            FROM inventory_logs il
            JOIN ingredients ing ON ing.id = il.ingredient_id
            WHERE il.action = 'remove'
              AND il.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND (
                    LOWER(COALESCE(il.reason, '')) LIKE '%waste%'
                 OR LOWER(COALESCE(il.reason, '')) LIKE '%spoil%'
                 OR LOWER(COALESCE(il.reason, '')) LIKE '%expire%'
              )
            ORDER BY il.timestamp DESC
            LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

        $inventoryAnomalies = $pdo->query("SELECT ing.name,
                il.action,
                ABS(il.quantity) AS quantity,
                COALESCE(NULLIF(il.reason, ''), 'No reason provided') AS reason,
                il.timestamp
            FROM inventory_logs il
            JOIN ingredients ing ON ing.id = il.ingredient_id
            WHERE il.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND ((il.action = 'remove' AND ABS(il.quantity) >= 5) OR (il.action = 'add' AND ABS(il.quantity) >= 25))
            ORDER BY ABS(il.quantity) DESC, il.timestamp DESC
            LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }

    $topCategory = $pdo->query("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name,
            COALESCE(SUM(GREATEST(oi.line_total, 0)), 0) AS total_sales
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        LEFT JOIN menu_categories mc ON mc.id = mi.category_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY mc.id, mc.name
        ORDER BY total_sales DESC
        LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

    $customerPreferenceSummary = $pdo->query("SELECT
            COALESCE(mi.name, oi.item_name_snapshot) AS favorite_item,
            COALESCE(mc.name, 'Uncategorized') AS favorite_category,
            COALESCE(SUM(oi.quantity), 0) AS total_quantity,
            COUNT(DISTINCT o.id) AS order_count,
            COUNT(DISTINCT COALESCE(o.customer_id, CONCAT('guest-', o.id))) AS audience_size
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        LEFT JOIN menu_categories mc ON mc.id = mi.category_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY favorite_item, favorite_category
        ORDER BY total_quantity DESC, order_count DESC, favorite_item ASC
        LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $categoryPreferences = $pdo->query("SELECT COALESCE(mc.name, 'Uncategorized') AS category_name,
            COALESCE(SUM(oi.quantity), 0) AS qty
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        LEFT JOIN menu_categories mc ON mc.id = mi.category_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY mc.id, mc.name
        ORDER BY qty DESC
        LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $recommendationPairs = $pdo->query("SELECT
            LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_a,
            GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot) AS item_b,
            COUNT(DISTINCT oi1.order_id) AS pair_count
        FROM order_items oi1
        JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi1.id < oi2.id
        JOIN orders o ON o.id = oi1.order_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY LEAST(oi1.item_name_snapshot, oi2.item_name_snapshot), GREATEST(oi1.item_name_snapshot, oi2.item_name_snapshot)
        ORDER BY pair_count DESC, item_a ASC, item_b ASC
        LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

    $customerRecommendations = [];
    foreach ($customerPreferenceSummary as $customerRow) {
        foreach ($recommendationPairs as $pairRow) {
            if ($pairRow['item_a'] === $customerRow['favorite_item']) {
                $customerRecommendations[] = [
                    'customer_label' => 'Customers who ordered ' . $customerRow['favorite_item'],
                    'anchor_item' => $pairRow['item_a'],
                    'recommended_item' => $pairRow['item_b'],
                    'pair_count' => (int) $pairRow['pair_count'],
                ];
                break;
            }
            if ($pairRow['item_b'] === $customerRow['favorite_item']) {
                $customerRecommendations[] = [
                    'customer_label' => 'Customers who ordered ' . $customerRow['favorite_item'],
                    'anchor_item' => $pairRow['item_b'],
                    'recommended_item' => $pairRow['item_a'],
                    'pair_count' => (int) $pairRow['pair_count'],
                ];
                break;
            }
        }
        if (count($customerRecommendations) >= 5) {
            break;
        }
    }

    $salesAnomalies = aiDetectSalesAnomalies($dailySales, $averageDailySales);

    $customerProfiles = aiGetCustomerProfiles($pdo, 12);

    $slowMovingItems = $pdo->query("SELECT mi.name,
            COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS total_qty
        FROM menu_items mi
        LEFT JOIN order_items oi ON oi.menu_item_id = mi.id
        LEFT JOIN orders o ON o.id = oi.order_id
            AND o.payment_status = 'completed'
            AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY mi.id, mi.name
        HAVING total_qty <= 3
        ORDER BY total_qty ASC, mi.name ASC
        LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $todayTopSeller = $pdo->query("SELECT COALESCE(mi.name, oi.item_name_snapshot) AS item_name, COALESCE(SUM(oi.quantity), 0) AS total_sold
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        WHERE o.payment_status = 'completed' AND DATE(o.created_at) = CURDATE()
        GROUP BY mi.id, oi.item_name_snapshot, mi.name
        ORDER BY total_sold DESC
        LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

    $cache = [
        'today' => $today,
        'forecast_next_week' => $forecastNextWeek,
        'sales_forecast' => $salesForecast,
        'forecast_method_used' => (string) ($salesForecast['method_used'] ?? 'Moving Average'),
        'forecast_method_label' => (string) ($salesForecast['method_label'] ?? formatForecastMethodLabel('moving_average', true)),
        'demand_method_used' => (string) ($demandForecast['method_used'] ?? 'Moving Average'),
        'demand_method_label' => (string) ($demandForecast['method_label'] ?? formatForecastMethodLabel('moving_average', true)),
        'pending_orders' => $pendingOrders,
        'daily_sales' => $dailySales,
        'average_daily_sales' => round($averageDailySales, 2),
        'sales_deviation' => $salesDeviation,
        'trend_label' => $trendLabel,
        'peak_day_label' => $peakDayLabel,
        'peak_hour_label' => aiFormatHourLabel($peakHour),
        'forecast_peak_day_label' => $forecastPeakWindow['peak_day_label'],
        'forecast_peak_hour_label' => $forecastPeakWindow['peak_hour_label'],
        'forecast_peak_day_source' => $forecastPeakWindow['peak_day_source'],
        'top_items' => $topItems,
        'demand_predictions' => $demandPredictions,
        'low_stock_ingredients' => $lowStockIngredients,
        'expiring_ingredients' => $expiringIngredients,
        'inventory_optimization' => $inventoryOptimization,
        'smart_reordering' => $smartReordering,
        'top_category' => $topCategory,
        'customer_preferences' => $customerPreferenceSummary,
        'customer_profiles' => $customerProfiles,
        'category_preferences' => $categoryPreferences,
        'recommendation_pairs' => $recommendationPairs,
        'customer_recommendations' => $customerRecommendations,
        'sales_anomalies' => $salesAnomalies,
        'inventory_anomalies' => $inventoryAnomalies,
        'slow_moving_items' => $slowMovingItems,
        'today_top_seller' => $todayTopSeller,
        'waste_alerts' => $wasteAlerts,
        'kitchen_workflow_optimization' => $kitchenWorkflowOptimization,
    ];

    return $cache;
}

function aiBuildVirtualAssistantPromptLibrary(array $signals): array {
    $smartReordering = $signals['smart_reordering'];
    $demandPredictions = $signals['demand_predictions'];
    $customerPreferences = $signals['customer_preferences'];
    $salesAnomalies = $signals['sales_anomalies'];

    return [
        [
            'question' => 'What are forecasted sales for the next 7 days?',
            'answer' => 'Projected net sales for the next 7 days are ₱' . number_format((float) $signals['forecast_next_week'], 2) . '.',
        ],
        [
            'question' => 'Which ingredient needs reordering first?',
            'answer' => !empty($smartReordering) ? $smartReordering[0]['name'] . ' should be reordered next with about ' . rtrim(rtrim(number_format((float) $smartReordering[0]['recommended_order'], 2), '0'), '.') . ' ' . $smartReordering[0]['unit'] . '.' : 'No ingredient is currently below the reorder point.',
        ],
        [
            'question' => 'What menu item demand is strongest right now?',
            'answer' => !empty($demandPredictions) ? $demandPredictions[0]['name'] . ' shows the strongest demand with about ' . $demandPredictions[0]['predicted_week_units'] . ' units expected next week.' : 'There is not enough demand history yet.',
        ],
        [
            'question' => 'Which customer preference stands out?',
            'answer' => !empty($signals['customer_profiles'])
                ? $signals['customer_profiles'][0]['customer_label'] . ' repeats ' . $signals['customer_profiles'][0]['favorite_item'] . ' with ' . (int) $signals['customer_profiles'][0]['order_count'] . ' completed orders in the last 90 days.'
                : (!empty($customerPreferences) ? 'The strongest shared preference is ' . $customerPreferences[0]['favorite_item'] . ' from ' . $customerPreferences[0]['favorite_category'] . ', with ' . (int) $customerPreferences[0]['order_count'] . ' completed orders in the last 90 days.' : 'No customer preference pattern is available yet.'),
        ],
        [
            'question' => 'What anomaly needs review?',
            'answer' => !empty($salesAnomalies) ? $salesAnomalies[count($salesAnomalies) - 1]['type'] . ' detected on ' . $salesAnomalies[count($salesAnomalies) - 1]['sale_date'] . ' with a ₱' . number_format((float) $salesAnomalies[count($salesAnomalies) - 1]['gap_amount'], 2) . ' variance from the 30-day average.' : 'No major sales anomalies were detected from the last 30 days.',
        ],
    ];
}

function getAiOverviewData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'today' => $signals['today'],
        'pending_orders' => $signals['pending_orders'],
        'forecast_next_week' => $signals['forecast_next_week'],
        'trend_label' => $signals['trend_label'],
        'peak_day_label' => $signals['peak_day_label'],
        'peak_hour_label' => $signals['peak_hour_label'],
        'top_category' => $signals['top_category'],
        'today_top_seller' => $signals['today_top_seller'],
        'low_stock_ingredients' => $signals['low_stock_ingredients'],
        'expiring_ingredients' => $signals['expiring_ingredients'],
        'forecast_method_used' => $signals['forecast_method_used'],
        'forecast_method_label' => $signals['forecast_method_label'],
    ];
}

function getAiSalesForecastingData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);
    $usingForecastPeak = empty($signals['sales_forecast']['insufficient_data'] ?? true)
        && !empty($signals['sales_forecast']['peak_day'] ?? '');
    $stockUpGuidance = aiBuildForecastStockUpGuidance(
        $pdo,
        (float) $signals['forecast_next_week'],
        (float) $signals['average_daily_sales'],
        $signals['demand_predictions'],
        $signals['smart_reordering']
    );

    return [
        'forecast_next_week' => $signals['forecast_next_week'],
        'average_daily_sales' => $signals['average_daily_sales'],
        'trend_label' => $signals['trend_label'],
        'peak_day_label' => $signals['forecast_peak_day_label'],
        'peak_hour_label' => $signals['forecast_peak_hour_label'],
        'peak_day_source' => $signals['forecast_peak_day_source'],
        'historical_peak_day_label' => $signals['peak_day_label'],
        'historical_peak_hour_label' => $signals['peak_hour_label'],
        'daily_sales' => $signals['daily_sales'],
        'top_items' => array_slice($signals['top_items'], 0, 5),
        'forecast_7day' => $signals['sales_forecast']['forecast_7day'] ?? [],
        'forecast_peak_day' => $signals['sales_forecast']['peak_day'] ?? $signals['peak_day_label'],
        'method_used' => $signals['forecast_method_used'],
        'method_label' => $signals['forecast_method_label'],
        'confidence_interval' => $signals['sales_forecast']['confidence_interval'] ?? ['lower' => [], 'upper' => []],
        'using_forecast_peak' => $usingForecastPeak,
        'stock_up_guidance' => $stockUpGuidance,
    ];
}

function getAiInventoryHealthSnapshot(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);
    $optimization = $signals['inventory_optimization'];
    $lowStockCount = count($signals['low_stock_ingredients']);
    $expiringCount = count($signals['expiring_ingredients']);
    $underTargetCount = count(array_filter($optimization, static function (array $row): bool {
        return ($row['status'] ?? '') === 'Under target';
    }));
    $overstockedCount = count(array_filter($optimization, static function (array $row): bool {
        return ($row['status'] ?? '') === 'Overstocked';
    }));
    $balancedCount = count(array_filter($optimization, static function (array $row): bool {
        return ($row['status'] ?? '') === 'Balanced';
    }));
    $totalTracked = count($optimization);

    if ($totalTracked === 0) {
        return [
            'score' => 100,
            'label' => 'No inventory data',
            'low_stock_count' => $lowStockCount,
            'expiring_count' => $expiringCount,
            'under_target_count' => 0,
            'overstocked_count' => 0,
            'balanced_count' => 0,
            'top_pressure' => [],
        ];
    }

    $penalty = min(95, ($underTargetCount * 8) + ($lowStockCount * 5) + ($expiringCount * 4) + ($overstockedCount * 2));
    $score = max(0, 100 - $penalty);
    $label = 'At Risk';
    if ($score >= 80) {
        $label = 'Healthy';
    } elseif ($score >= 60) {
        $label = 'Watch';
    }

    $topPressure = array_values(array_filter($optimization, static function (array $row): bool {
        return (float) ($row['stock_pressure'] ?? 0) > 0;
    }));
    usort($topPressure, static function (array $left, array $right): int {
        return ($right['stock_pressure'] <=> $left['stock_pressure'])
            ?: strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return [
        'score' => $score,
        'label' => $label,
        'low_stock_count' => $lowStockCount,
        'expiring_count' => $expiringCount,
        'under_target_count' => $underTargetCount,
        'overstocked_count' => $overstockedCount,
        'balanced_count' => $balancedCount,
        'top_pressure' => array_slice($topPressure, 0, 5),
    ];
}

function getAiInventoryOptimizationData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'items' => array_slice($signals['inventory_optimization'], 0, 8),
        'balanced_count' => count(array_filter($signals['inventory_optimization'], static function (array $row): bool {
            return $row['status'] === 'Balanced';
        })),
        'method_label' => aiRuleBasedMethodLabel('inventory_optimization'),
    ];
}

function getAiDemandPredictionData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'items' => $signals['demand_predictions'],
        'peak_day_label' => $signals['peak_day_label'],
        'peak_hour_label' => $signals['peak_hour_label'],
        'method_used' => $signals['demand_method_used'],
        'method_label' => $signals['demand_method_label'],
    ];
}

function getAiSmartReorderingData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'items' => array_slice($signals['smart_reordering'], 0, 8),
        'method_label' => aiRuleBasedMethodLabel('smart_reordering'),
    ];
}

function aiGetRepeatBuyerCountsByItem(PDO $pdo): array {
    if (!tableExists($pdo, 'customers')) {
        return [];
    }

    $rows = $pdo->query("SELECT
            COALESCE(mi.name, oi.item_name_snapshot) AS item_name,
            COUNT(DISTINCT o.customer_id) AS repeat_buyer_count
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        JOIN (
            SELECT customer_id
            FROM orders
            WHERE payment_status = 'completed'
              AND customer_id IS NOT NULL
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY customer_id
            HAVING COUNT(*) >= 2
        ) repeat_customers ON repeat_customers.customer_id = o.customer_id
        WHERE o.payment_status = 'completed'
          AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY COALESCE(mi.name, oi.item_name_snapshot)")->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $key = strtolower(trim((string) ($row['item_name'] ?? '')));
        if ($key === '') {
            continue;
        }
        $map[$key] = (int) ($row['repeat_buyer_count'] ?? 0);
    }

    return $map;
}

function aiEnrichCustomerPreferenceSummary(array $summary, array $repeatBuyerCounts): array {
    foreach ($summary as &$row) {
        $key = strtolower(trim((string) ($row['favorite_item'] ?? '')));
        $repeatBuyers = (int) ($repeatBuyerCounts[$key] ?? 0);
        $audience = (int) ($row['audience_size'] ?? 0);
        $row['repeat_buyer_count'] = $repeatBuyers;
        $row['repeat_rate_percent'] = $audience > 0 ? round(($repeatBuyers / $audience) * 100, 1) : 0.0;
    }
    unset($row);

    return $summary;
}

function getAiCustomerPreferencesData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);
    $repeatBuyerCounts = aiGetRepeatBuyerCountsByItem($pdo);
    $customerTrends = aiEnrichCustomerPreferenceSummary($signals['customer_preferences'], $repeatBuyerCounts);
    $totalRepeatBuyers = count($signals['customer_profiles']);

    return [
        'customers' => $customerTrends,
        'profiles' => $signals['customer_profiles'],
        'profiled_customer_count' => count($signals['customer_profiles']),
        'repeat_customer_total' => $totalRepeatBuyers,
        'categories' => $signals['category_preferences'],
        'method_label' => aiRuleBasedMethodLabel('customer_preferences'),
    ];
}

function getAiRecommendationSystemData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'pairings' => $signals['recommendation_pairs'],
        'customer_recommendations' => $signals['customer_recommendations'],
        'method_label' => aiRecommendationMethodLabel(),
    ];
}

function getAiAnomalyDetectionData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'sales' => $signals['sales_anomalies'],
        'inventory' => $signals['inventory_anomalies'],
        'average_daily_sales' => $signals['average_daily_sales'],
        'method_label' => aiAnomalyMethodLabel(),
    ];
}

function getAiWasteReductionData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return [
        'expiring_ingredients' => $signals['expiring_ingredients'],
        'slow_moving_items' => $signals['slow_moving_items'],
        'waste_alerts' => $signals['waste_alerts'],
        'method_label' => aiRuleBasedMethodLabel('waste_reduction'),
    ];
}

function getAiVirtualAssistantData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);
    $config = aiGetVirtualAssistantConfig($pdo);
    $hasExternalConfig = $config['enabled'] && $config['api_key'] !== '' && $config['endpoint'] !== '' && $config['model'] !== '';

    return [
        'prompts' => aiBuildVirtualAssistantPromptLibrary($signals),
        'provider_ready' => $hasExternalConfig,
        'provider' => $config['provider'],
        'model' => $config['model'],
        'endpoint' => $config['endpoint'],
        'enabled' => $config['enabled'],
    ];
}

function getAiKitchenWorkflowOptimizationData(PDO $pdo): array {
    $signals = aiBuildSignals($pdo);

    return $signals['kitchen_workflow_optimization'];
}

function getAiFeatureSuite(PDO $pdo): array {
    return [
        'overview' => getAiOverviewData($pdo),
        'sales_forecasting' => getAiSalesForecastingData($pdo),
        'inventory_optimization' => getAiInventoryOptimizationData($pdo),
        'demand_prediction' => getAiDemandPredictionData($pdo),
        'customer_preferences' => getAiCustomerPreferencesData($pdo),
        'recommendation_system' => getAiRecommendationSystemData($pdo),
        'anomaly_detection' => getAiAnomalyDetectionData($pdo),
        'virtual_assistant' => getAiVirtualAssistantData($pdo),
        'waste_reduction' => getAiWasteReductionData($pdo),
        'kitchen_workflow_optimization' => getAiKitchenWorkflowOptimizationData($pdo),
        'inventory_health' => getAiInventoryHealthSnapshot($pdo),
    ];
}

function getAiDataSourceMap(): array {
    return [
        'ai_insights.php' => [
            'service' => 'getAiFeatureSuite',
            'helpers' => ['getAiOverviewData', 'getAiSalesForecastingData', 'getAiInventoryOptimizationData', 'getAiDemandPredictionData', 'getAiSmartReorderingData', 'getAiCustomerPreferencesData', 'getAiRecommendationSystemData', 'getAiAnomalyDetectionData', 'getAiVirtualAssistantData', 'getAiWasteReductionData', 'getAiKitchenWorkflowOptimizationData'],
            'tables' => ['orders', 'order_items', 'menu_items', 'menu_categories', 'ingredients', 'inventory_logs'],
        ],
        'ai_sales_forecasting.php' => [
            'service' => 'getAiSalesForecastingData',
            'helpers' => ['forecastSales', 'buildPercentageTrend'],
            'tables' => ['orders', 'order_items', 'menu_items'],
        ],
        'ai_inventory_optimization.php' => [
            'service' => 'getAiInventoryOptimizationData',
            'helpers' => [],
            'tables' => ['ingredients', 'inventory_logs'],
        ],
        'ai_demand_prediction.php' => [
            'service' => 'getAiDemandPredictionData',
            'helpers' => ['aiFormatHourLabel'],
            'tables' => ['orders', 'order_items', 'menu_items'],
        ],
        'ai_customer_preferences.php' => [
            'service' => 'getAiCustomerPreferencesData',
            'helpers' => ['aiFormatCustomerLabel'],
            'tables' => ['orders', 'order_items', 'menu_items', 'menu_categories', 'customers'],
        ],
        'ai_anomaly_detection.php' => [
            'service' => 'getAiAnomalyDetectionData',
            'helpers' => ['aiStandardDeviation'],
            'tables' => ['orders', 'inventory_logs', 'ingredients'],
        ],
        'ai_virtual_assistant.php' => [
            'service' => 'getAiVirtualAssistantData / askAiVirtualAssistant',
            'helpers' => ['aiBuildVirtualAssistantPromptLibrary', 'aiGetVirtualAssistantConfig'],
            'tables' => ['settings', 'orders', 'order_items', 'menu_items', 'menu_categories', 'ingredients', 'inventory_logs'],
        ],
        'ai_waste_reduction.php' => [
            'service' => 'getAiWasteReductionData',
            'helpers' => ['getExpiringIngredients'],
            'tables' => ['ingredients', 'inventory_logs', 'orders', 'order_items', 'menu_items'],
        ],
        'orders_history.php' => [
            'service' => 'getAiKitchenWorkflowOptimizationData',
            'helpers' => [],
            'tables' => ['orders', 'order_items', 'customers'],
        ],
    ];
}

function aiTokenizeQuestion(string $value): array {
    preg_match_all('/[a-z0-9]{3,}/i', strtolower($value), $matches);
    return array_values(array_unique($matches[0] ?? []));
}

function aiBuildLocalAssistantAnswer(PDO $pdo, string $question): string {
    $q = strtolower(trim($question));
    $overview = getAiOverviewData($pdo);

    // 1. TODAY'S SALES / REVENUE / NET SALES / ORDERS TODAY
    if (preg_match('/(today|make today|earned today|revenue|sales today|income today|total sales)/i', $q)) {
        $todayNet = (float) ($overview['today']['net_sales'] ?? 0);
        $todayCount = (int) ($overview['today']['order_count'] ?? 0);
        $avgVal = $todayCount > 0 ? $todayNet / $todayCount : 0;
        return "<strong>Today's Sales Performance:</strong><br>• <strong>Net Sales:</strong> ₱" . number_format($todayNet, 2) . "<br>• <strong>Completed Orders:</strong> " . $todayCount . "<br>• <strong>Average Order Value:</strong> ₱" . number_format($avgVal, 2);
    }

    // 2. FORECAST / NEXT 7 DAYS / PROJECTION
    if (preg_match('/(forecast|next 7 days|next week|predict|future sales|projected)/i', $q)) {
        $forecastVal = (float) ($overview['forecast_next_week'] ?? 0);
        $trend = (string) ($overview['trend_label'] ?? 'Stable Growth');
        return "<strong>7-Day Sales Projection (ARIMA Model):</strong><br>• <strong>Forecasted Revenue:</strong> ₱" . number_format($forecastVal, 2) . "<br>• <strong>Trend Direction:</strong> " . htmlspecialchars($trend) . "<br>• <strong>Insight:</strong> Keep inventory ready for peak afternoon service windows.";
    }

    // 3. LOW STOCK / INVENTORY / REORDER / SHORTAGE
    if (preg_match('/(low stock|reorder|out of stock|inventory|shortage|running low|ingredient|stock)/i', $q)) {
        $lowStock = $overview['low_stock_ingredients'] ?? [];
        if (!empty($lowStock)) {
            $items = array_map(static function ($item) {
                return '• <strong>' . htmlspecialchars($item['name']) . '</strong>: ' . rtrim(rtrim(number_format((float) $item['stock_quantity'], 2), '0'), '.') . ' ' . htmlspecialchars($item['unit']) . ' left';
            }, array_slice($lowStock, 0, 5));
            return "<strong>Inventory Reorder Guidance:</strong><br>" . implode("<br>", $items) . "<br><small><em>Action: Create a purchase order to avoid menu item unavailability.</em></small>";
        }
        return "<strong>Inventory Status:</strong> All ingredients are currently above minimum threshold levels.";
    }

    // 4. EXPIRING / EXPIRATION / SPOIL / WASTE
    if (preg_match('/(expiring|expiration|expiry|spoil|spoiled|waste)/i', $q)) {
        $expiring = $overview['expiring_ingredients'] ?? [];
        if (!empty($expiring)) {
            $items = array_map(static function ($item) {
                return '• <strong>' . htmlspecialchars($item['name']) . '</strong> (Expires: ' . htmlspecialchars($item['expiration_date']) . ')';
            }, array_slice($expiring, 0, 5));
            return "<strong>Waste Reduction Alert:</strong><br>" . implode("<br>", $items) . "<br><small><em>Action: Feature these in today's promos to minimize waste.</em></small>";
        }
        return "<strong>Waste & Expiration Status:</strong> No ingredients are currently approaching expiration.";
    }

    // 5. TOP SELLING / BEST SELLER / POPULAR / DEMAND / MOST SOLD
    if (preg_match('/(top|best seller|popular|most sold|demand|item|product|food)/i', $q)) {
        $todayTop = $overview['today_top_seller'] ?? null;
        $forecasting = getAiSalesForecastingData($pdo);
        $topItemOverall = $forecasting['top_items'][0] ?? null;

        $lines = ["<strong>Menu Item Demand & Bestsellers:</strong>"];
        if ($todayTop && !empty($todayTop['name'])) {
            $lines[] = "• <strong>Today's #1 Item:</strong> " . htmlspecialchars($todayTop['name']) . " (" . (int) $todayTop['qty'] . " units sold today)";
        }
        if ($topItemOverall && !empty($topItemOverall['name'])) {
            $lines[] = "• <strong>All-Time Bestseller:</strong> " . htmlspecialchars($topItemOverall['name']) . " (" . (int) ($topItemOverall['total_sold'] ?? $topItemOverall['sales'] ?? 0) . " total units)";
        }
        $lines[] = "• <strong>Top Category:</strong> " . htmlspecialchars($overview['top_category']['category_name'] ?? 'General Menu');
        return implode("<br>", $lines);
    }

    // 6. PENDING ORDERS / KITCHEN / QUEUE
    if (preg_match('/(pending|kitchen|queue|active order|waiting)/i', $q)) {
        $pending = (int) ($overview['pending_orders'] ?? 0);
        return "<strong>Kitchen Queue Status:</strong><br>• <strong>Active Pending Orders:</strong> " . $pending . "<br>• <strong>Recommendation:</strong> " . ($pending > 3 ? 'High kitchen volume! Prioritize checkout speed.' : 'Kitchen queue is running smoothly.');
    }

    // 7. CUSTOMER / PREFERENCES / LOYALTY
    if (preg_match('/(customer|preference|loyal|buyer|spender)/i', $q)) {
        $custPref = getAiCustomerPreferencesData($pdo);
        if (!empty($custPref['top_customers'])) {
            $topC = $custPref['top_customers'][0];
            return "<strong>Customer Preference Insights:</strong><br>• <strong>Top VIP Spender:</strong> " . htmlspecialchars($topC['name'] ?: 'Walk-in') . " (₱" . number_format((float) $topC['sales_total'], 2) . " spent)<br>• <strong>Preferred Category:</strong> " . htmlspecialchars($overview['top_category']['category_name'] ?? 'Beverages');
        }
        return "<strong>Customer Preference Analysis:</strong> Strong customer preference indicated for " . htmlspecialchars($overview['top_category']['category_name'] ?? 'Beverages') . ".";
    }

    // 8. POS / DISCOUNT / HELP / RECEIPT / CASH / CHECKOUT
    if (preg_match('/(pos|discount|receipt|cash|checkout|pwd|senior|how to|help)/i', $q)) {
        return "<strong>POS Operational Guide:</strong><br>• <strong>Checkout:</strong> Add items to cart, enter cash amount, click CASH button.<br>• <strong>Discounts:</strong> Check <em>PWD/Senior Discount (20%)</em> or enter a promo code before payment.<br>• <strong>Receipts:</strong> Click <em>Print Receipt</em> on order completion popup.";
    }

    // 9. FALLBACK TOKEN MATCHING AGAINST PROMPT LIBRARY
    $feature = getAiVirtualAssistantData($pdo);
    $questionTokens = aiTokenizeQuestion($question);
    $bestScore = 0;
    $bestAnswer = '';

    foreach ($feature['prompts'] as $prompt) {
        $promptTokens = aiTokenizeQuestion((string) ($prompt['question'] ?? ''));
        $score = count(array_intersect($questionTokens, $promptTokens));
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestAnswer = (string) ($prompt['answer'] ?? '');
        }
    }

    if ($bestScore > 0 && $bestAnswer !== '') {
        return $bestAnswer;
    }

    // 10. DYNAMIC PREDICTIVE OVERVIEW SUMMARY
    $todayNet = number_format((float) ($overview['today']['net_sales'] ?? 0), 2);
    $pending = (int) ($overview['pending_orders'] ?? 0);
    $forecastVal = number_format((float) ($overview['forecast_next_week'] ?? 0), 2);
    return "<strong>Kin Cafe Virtual Assistant:</strong><br>• <strong>Today's Sales:</strong> ₱" . $todayNet . "<br>• <strong>Pending Orders:</strong> " . $pending . "<br>• <strong>7-Day Forecast:</strong> ₱" . $forecastVal . "<br><small>Ask any specific question or click a prompt above!</small>";
}

function aiBuildAssistantContext(PDO $pdo): string {
    $overview = getAiOverviewData($pdo);
    $forecasting = getAiSalesForecastingData($pdo);
    $inventory = getAiInventoryOptimizationData($pdo);
    $reordering = getAiSmartReorderingData($pdo);
    $waste = getAiWasteReductionData($pdo);
    $anomalies = getAiAnomalyDetectionData($pdo);
    $customers = getAiCustomerPreferencesData($pdo);

    $lowStockNames = array_slice(array_map(static function (array $row): string {
        return (string) ($row['name'] ?? '') . ' (' . rtrim(rtrim(number_format((float) ($row['stock_quantity'] ?? 0), 2), '0'), '.') . ' ' . ($row['unit'] ?? '') . ')';
    }, $overview['low_stock_ingredients']), 0, 5);

    $expiringNames = array_slice(array_map(static function (array $row): string {
        return (string) ($row['name'] ?? '') . ' (Expires: ' . ($row['expiration_date'] ?? '') . ')';
    }, $overview['expiring_ingredients']), 0, 5);

    $topDrivers = array_slice(array_map(static function (array $row): string {
        return (string) ($row['name'] ?? '') . ' (' . (int) ($row['total_sold'] ?? $row['sales'] ?? 0) . ' sold)';
    }, $forecasting['top_items']), 0, 5);

    $topCustomerName = !empty($customers['top_customers'][0]) ? ($customers['top_customers'][0]['name'] ?: 'Walk-in') . ' (₱' . number_format((float) $customers['top_customers'][0]['sales_total'], 2) . ')' : 'None recorded';

    return implode("\n", [
        'Kin Cafe Comprehensive Business Intelligence & Live Context:',
        '--- SALES & FORECASTING ---',
        '- Today Net Sales: ₱' . number_format((float) ($overview['today']['net_sales'] ?? 0), 2),
        '- Today Completed Orders: ' . (int) ($overview['today']['order_count'] ?? 0),
        '- Pending Kitchen Queue Orders: ' . (int) $overview['pending_orders'],
        '- 7-Day Forecasted Revenue (ARIMA/SARIMA): ₱' . number_format((float) $overview['forecast_next_week'], 2),
        '- Average Daily Sales Velocity: ₱' . number_format((float) $forecasting['average_daily_sales'], 2),
        '- Forecast Trend Direction: ' . (string) $forecasting['trend_label'],
        '- Peak Service Window: Day = ' . (string) $forecasting['peak_day_label'] . ', Hour = ' . (string) $forecasting['peak_hour_label'],
        '- Top Forecast Revenue Drivers: ' . ($topDrivers ? implode(', ', $topDrivers) : 'No item demand history yet'),
        '--- INVENTORY & SUPPLY CHAIN ---',
        '- Critical Low Stock Ingredients: ' . ($lowStockNames ? implode(', ', $lowStockNames) : 'All above threshold'),
        '- Total Inventory Items Analyzed: ' . count($inventory['items']),
        '- Urgent Reorder Candidates: ' . count($reordering['items']),
        '- Ingredients Expiring Soon: ' . ($expiringNames ? implode(', ', $expiringNames) : 'None near expiration'),
        '- Waste Risk Alerts Flagged: ' . count($waste['waste_alerts']),
        '--- CUSTOMERS & ANOMALIES ---',
        '- Highest Value VIP Customer: ' . $topCustomerName,
        '- Preferred Product Category: ' . (string) ($overview['top_category']['category_name'] ?? 'General Menu'),
        '- Sales Anomalies ($z-score flags): ' . count($anomalies['sales']),
        '- Inventory Adjustment Audit Flags: ' . count($anomalies['inventory']),
    ]);
}

function aiHttpPostJson(string $url, array $headers, array $payload, int $timeoutSeconds): array {
    $body = json_encode($payload);
    if ($body === false) {
        return ['success' => false, 'status' => 0, 'body' => '', 'error' => 'Could not encode request payload.'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => $responseBody !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $responseBody !== false ? (string) $responseBody : '',
            'error' => $error !== '' ? $error : '',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $headerLine) {
        if (preg_match('/HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'success' => $responseBody !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $responseBody !== false ? (string) $responseBody : '',
        'error' => $responseBody === false ? 'HTTP request failed.' : '',
    ];
}

function aiExtractAssistantContent(array $decoded): string {
    $message = $decoded['choices'][0]['message']['content'] ?? '';
    if (is_string($message)) {
        return trim($message);
    }

    if (is_array($message)) {
        $parts = [];
        foreach ($message as $entry) {
            if (is_array($entry) && isset($entry['text']) && is_string($entry['text'])) {
                $parts[] = trim($entry['text']);
            }
        }
        return trim(implode("\n", array_filter($parts)));
    }

    return '';
}

function aiCallExternalVirtualAssistant(PDO $pdo, string $question): array {
    $config = aiGetVirtualAssistantConfig($pdo);
    if (!$config['enabled']) {
        return ['success' => false, 'message' => 'External AI is disabled.'];
    }

    if ($config['api_key'] === '' || $config['endpoint'] === '' || $config['model'] === '') {
        return ['success' => false, 'message' => 'External AI settings are incomplete.'];
    }

    $payload = [
        'model' => $config['model'],
        'temperature' => 0.2,
        'messages' => [
            [
                'role' => 'system',
                'content' => $config['system_prompt'] . "\n\n" . aiBuildAssistantContext($pdo),
            ],
            [
                'role' => 'user',
                'content' => $question,
            ],
        ],
    ];

    $response = aiHttpPostJson(
        $config['endpoint'],
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key'],
        ],
        $payload,
        (int) $config['timeout_seconds']
    );

    if (!$response['success']) {
        $message = 'External AI request failed.';
        if ($response['status'] > 0) {
            $message .= ' HTTP ' . $response['status'] . '.';
        }
        if ($response['error'] !== '') {
            $message .= ' ' . $response['error'];
        }
        return ['success' => false, 'message' => trim($message)];
    }

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'External AI response was not valid JSON.'];
    }

    $answer = aiExtractAssistantContent($decoded);
    if ($answer === '') {
        return ['success' => false, 'message' => 'External AI response did not include a reply.'];
    }

    return [
        'success' => true,
        'answer' => $answer,
        'source' => 'external',
        'provider' => $config['provider'],
        'model' => $config['model'],
    ];
}

function askAiVirtualAssistant(PDO $pdo, string $question): array {
    $question = trim($question);
    if ($question === '') {
        return ['success' => false, 'message' => 'Enter a question for the assistant.'];
    }

    $maxQuestionLength = 500;
    if (function_exists('mb_strlen') && mb_strlen($question) > $maxQuestionLength) {
        $question = mb_substr($question, 0, $maxQuestionLength);
    } elseif (strlen($question) > $maxQuestionLength) {
        $question = substr($question, 0, $maxQuestionLength);
    }

    $config = aiGetVirtualAssistantConfig($pdo);
    $external = aiCallExternalVirtualAssistant($pdo, $question);
    if (!empty($external['success'])) {
        return $external;
    }

    $notice = '';
    if ($config['enabled'] && !empty($config['api_key'])) {
        $notice = $external['message'] ?? 'External AI could not be reached, so a local fallback answer was used.';
    }

    return [
        'success' => true,
        'answer' => aiBuildLocalAssistantAnswer($pdo, $question),
        'source' => 'local',
        'provider' => 'internal prompt library',
        'model' => 'rules-based fallback',
        'notice' => $notice,
    ];
}
