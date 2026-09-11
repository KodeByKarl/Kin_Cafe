<?php
session_start();
require 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$title = 'Purchase Orders & Procurement';
$currentNav = 'purchase_orders';
include 'includes/header.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action === 'create_po') {
                $supplierId = (int) ($_POST['supplier_id'] ?? 0);
                $ingredientId = (int) ($_POST['ingredient_id'] ?? 0);
                $qty = (float) ($_POST['quantity'] ?? 0);
                $unitCost = (float) ($_POST['unit_cost'] ?? 0);

                if ($supplierId <= 0 || $ingredientId <= 0 || $qty <= 0) {
                    throw new InvalidArgumentException('Please select a valid supplier, ingredient, and quantity.');
                }

                $lineTotal = round($qty * $unitCost, 2);
                $adminId = $_SESSION['admin_id'] ?? null;

                $pdo->prepare("INSERT INTO purchase_orders (supplier_id, status, total_amount, created_by, notes) VALUES (?, 'draft', ?, ?, 'Manual PO Creation')")
                    ->execute([$supplierId, $lineTotal, $adminId]);
                $poId = (int) $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, ingredient_id, quantity_ordered, unit_cost, line_total) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$poId, $ingredientId, $qty, $unitCost, $lineTotal]);

                if (!empty($_POST['send_immediately'])) {
                    sendPurchaseOrderEmail($pdo, $poId);
                    $successMessage = 'Purchase order created and email sent to supplier.';
                } else {
                    $successMessage = 'Draft purchase order created successfully.';
                }
            } elseif ($action === 'send_po') {
                $poId = (int) ($_POST['po_id'] ?? 0);
                if ($poId <= 0) throw new InvalidArgumentException('Invalid purchase order.');

                $sent = sendPurchaseOrderEmail($pdo, $poId);
                if ($sent) {
                    $successMessage = 'Purchase Order #PO-' . $poId . ' has been emailed to supplier.';
                } else {
                    $errorMessage = 'Unable to send email (check supplier email or mail settings). Status updated to sent.';
                    $pdo->prepare("UPDATE purchase_orders SET status = 'sent' WHERE id = ?")->execute([$poId]);
                }
            } elseif ($action === 'receive_po') {
                $poId = (int) ($_POST['po_id'] ?? 0);
                if ($poId <= 0) throw new InvalidArgumentException('Invalid purchase order.');

                $items = $pdo->prepare("SELECT poi.*, ing.name AS ingredient_name FROM purchase_order_items poi JOIN ingredients ing ON ing.id = poi.ingredient_id WHERE poi.purchase_order_id = ?");
                $items->execute([$poId]);
                $rows = $items->fetchAll(PDO::FETCH_ASSOC);

                $adminId = $_SESSION['admin_id'] ?? null;
                foreach ($rows as $row) {
                    $ingId = (int) $row['ingredient_id'];
                    $qty = (float) $row['quantity_ordered'];
                    $unitCost = (float) $row['unit_cost'];

                    $pdo->prepare("UPDATE ingredients SET stock_quantity = stock_quantity + ?, unit_cost = CASE WHEN ? > 0 THEN ? ELSE unit_cost END WHERE id = ?")
                        ->execute([$qty, $unitCost, $unitCost, $ingId]);
                    
                    $pdo->prepare("UPDATE purchase_order_items SET quantity_received = ? WHERE id = ?")
                        ->execute([$qty, (int) $row['id']]);

                    $pdo->prepare("INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by) VALUES (?, 'purchase', ?, ?, ?)")
                        ->execute([$ingId, $qty, 'Stock received via PO #PO-' . $poId, $adminId]);

                    recalculateMenuItemsUsingIngredient($pdo, $ingId);
                }

                $pdo->prepare("UPDATE purchase_orders SET status = 'received', received_at = NOW() WHERE id = ?")->execute([$poId]);
                logAuditEvent($pdo, 'purchase_order_received', 'purchase_order', $poId, [
                    'items_received' => count($rows),
                    'ingredients' => array_values(array_map(static function (array $row): string {
                        return (string) ($row['ingredient_name'] ?? '');
                    }, $rows)),
                ]);
                $successMessage = 'Stock received and inventory quantities updated successfully!';
            } elseif ($action === 'cancel_po') {
                $poId = (int) ($_POST['po_id'] ?? 0);
                $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?")->execute([$poId]);
                $successMessage = 'Purchase Order #PO-' . $poId . ' cancelled.';
            } elseif ($action === 'add_supplier') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = trim((string) ($_POST['contact_email'] ?? ''));
                $phone = trim((string) ($_POST['contact_phone'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));

                if ($name === '') throw new InvalidArgumentException('Supplier name is required.');

                $pdo->prepare("INSERT INTO suppliers (name, contact_email, contact_phone, address) VALUES (?, ?, ?, ?)")
                    ->execute([$name, $email ?: null, $phone ?: null, $address ?: null]);
                $successMessage = 'Supplier added successfully.';
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Ensure PO & Supplier schema exists in case tables were unmigrated
if (function_exists('ensureSystemSchema')) {
    ensureSystemSchema($pdo);
}

// Fetch PO Data
$draftPos = [];
$activePos = [];
$allPos = [];
$suppliers = [];
$ingredients = [];
$poItemsMap = [];

try {
    $draftPos = $pdo->query("SELECT po.*, s.name AS supplier_name, s.contact_email 
        FROM purchase_orders po 
        JOIN suppliers s ON s.id = po.supplier_id 
        WHERE po.status = 'draft' 
        ORDER BY po.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $activePos = $pdo->query("SELECT po.*, s.name AS supplier_name, s.contact_email 
        FROM purchase_orders po 
        JOIN suppliers s ON s.id = po.supplier_id 
        WHERE po.status IN ('sent') 
        ORDER BY po.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $allPos = $pdo->query("SELECT po.*, s.name AS supplier_name, s.contact_email 
        FROM purchase_orders po 
        JOIN suppliers s ON s.id = po.supplier_id 
        ORDER BY po.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

    $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $ingredients = $pdo->query("SELECT * FROM ingredients WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Map items for each PO
    $poItemsQuery = $pdo->query("SELECT poi.*, ing.name AS ingredient_name, ing.unit FROM purchase_order_items poi JOIN ingredients ing ON ing.id = poi.ingredient_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($poItemsQuery as $item) {
        $poItemsMap[$item['purchase_order_id']][] = $item;
    }
} catch (Exception $e) {
    if (empty($errorMessage)) {
        $errorMessage = 'Unable to load purchase order data: ' . $e->getMessage();
    }
}
?>

<div class="main-content purchase-orders-page">
    <div class="page-hero">
        <div>
            <h1 class="page-title">Purchase Orders & Procurement</h1>
            <p class="page-subtitle">Automated stock replenishment, supplier ranking, and purchase order tracking.</p>
        </div>
        <div class="orders-hero-actions">
            <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#addSupplierModal">+ Add Supplier</button>
            <button class="btn btn-primary" data-toggle="modal" data-target="#createPoModal">+ Create Purchase Order</button>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Success!</strong> <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error!</strong> <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics Cards -->
    <div class="dashboard-stats-grid mb-4">
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Pending Draft POs</div>
            <div class="dashboard-stat-value"><?php echo count($draftPos); ?></div>
            <div class="dashboard-stat-sub text-muted">Awaiting confirmation</div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Sent to Suppliers</div>
            <div class="dashboard-stat-value"><?php echo count($activePos); ?></div>
            <div class="dashboard-stat-sub text-muted">Awaiting delivery</div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Registered Suppliers</div>
            <div class="dashboard-stat-value"><?php echo count($suppliers); ?></div>
            <div class="dashboard-stat-sub text-muted">Vendor accounts</div>
        </article>
        <article class="dashboard-stat-card">
            <div class="dashboard-stat-label">Total PO Count</div>
            <div class="dashboard-stat-value"><?php echo count($allPos); ?></div>
            <div class="dashboard-stat-sub text-muted">Procurement history</div>
        </article>
    </div>

    <!-- PO Tabs -->
    <div class="card ai-module-card analytics-feature-tabs-card mb-4">
        <div class="card-body">
            <ul class="nav nav-tabs analytics-feature-tabs" id="poTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="drafts-tab" data-toggle="tab" href="#drafts" role="tab" aria-controls="drafts" aria-selected="true">
                        Draft POs <?php if (count($draftPos) > 0): ?><span class="badge badge-warning ml-1"><?php echo count($draftPos); ?></span><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="sent-tab" data-toggle="tab" href="#sent" role="tab" aria-controls="sent" aria-selected="false">
                        Sent Orders <?php if (count($activePos) > 0): ?><span class="badge badge-info ml-1"><?php echo count($activePos); ?></span><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab" aria-controls="history" aria-selected="false">All PO History</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="suppliers-tab" data-toggle="tab" href="#suppliersTab" role="tab" aria-controls="suppliersTab" aria-selected="false">Suppliers Directory</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="poTabsContent">
        <!-- DRAFT POs TAB -->
        <div class="tab-pane fade show active" id="drafts" role="tabpanel" aria-labelledby="drafts-tab">
            <?php if (empty($draftPos)): ?>
                <div class="po-empty-state">
                    <div class="po-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" stroke="currentColor" stroke-width="1.8"/><rect x="8" y="2" width="8" height="4" rx="1" stroke="currentColor" stroke-width="1.8"/><path d="M9 12h6M9 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </div>
                    <h5>No pending draft purchase orders</h5>
                    <p>Auto-generated POs will appear here when inventory stock drops below critical levels.</p>
                </div>
            <?php else: ?>
                <div class="card po-table-card">
                    <div class="card-body p-0 table-responsive">
                        <table class="table po-table">
                            <thead>
                                <tr>
                                    <th>PO #</th>
                                    <th>Supplier</th>
                                    <th>Items Ordered</th>
                                    <th>Total Amount</th>
                                    <th>Created At</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($draftPos as $po): ?>
                                <tr>
                                    <td><span class="po-number-badge">#PO-<?php echo $po['id']; ?></span></td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($po['supplier_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($po['contact_email'] ?: 'No email set'); ?></small>
                                    </td>
                                    <td>
                                        <ul class="po-item-list">
                                        <?php foreach ($poItemsMap[$po['id']] ?? [] as $item): ?>
                                            <li>• <?php echo htmlspecialchars($item['ingredient_name']); ?> (<strong><?php echo number_format($item['quantity_ordered'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></strong> @ ₱<?php echo number_format($item['unit_cost'], 2); ?>)</li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td><strong class="text-primary">₱<?php echo number_format((float) $po['total_amount'], 2); ?></strong></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($po['created_at'])); ?></td>
                                    <td class="text-right">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="send_po">
                                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success font-weight-bold mr-1">Confirm & Send Email</button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Cancel this PO?');">
                                            <input type="hidden" name="action" value="cancel_po">
                                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- SENT POs TAB -->
        <div class="tab-pane fade" id="sent" role="tabpanel" aria-labelledby="sent-tab">
            <?php if (empty($activePos)): ?>
                <div class="po-empty-state">
                    <div class="po-empty-icon">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M22 2 11 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="m22 2-7 20-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h5>No active sent purchase orders</h5>
                    <p>Orders sent to suppliers awaiting stock receipt will appear here.</p>
                </div>
            <?php else: ?>
                <div class="card po-table-card">
                    <div class="card-body p-0 table-responsive">
                        <table class="table po-table">
                            <thead>
                                <tr>
                                    <th>PO #</th>
                                    <th>Supplier</th>
                                    <th>Items Ordered</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activePos as $po): ?>
                                <tr>
                                    <td><span class="po-number-badge">#PO-<?php echo $po['id']; ?></span></td>
                                    <td>
                                        <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($po['supplier_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($po['contact_email']); ?></small>
                                    </td>
                                    <td>
                                        <ul class="po-item-list">
                                        <?php foreach ($poItemsMap[$po['id']] ?? [] as $item): ?>
                                            <li>• <?php echo htmlspecialchars($item['ingredient_name']); ?> (<strong><?php echo number_format($item['quantity_ordered'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></strong>)</li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td><strong class="text-primary">₱<?php echo number_format((float) $po['total_amount'], 2); ?></strong></td>
                                    <td><span class="badge badge-info">SENT TO SUPPLIER</span></td>
                                    <td class="text-right">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Receive this shipment and add stock to inventory?');">
                                            <input type="hidden" name="action" value="receive_po">
                                            <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary font-weight-bold">Mark Stock Received</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- HISTORY TAB -->
        <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
            <div class="card po-table-card">
                <div class="card-body p-0 table-responsive">
                    <table class="table po-table">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Supplier</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date Created</th>
                                <th>Received Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allPos as $po): ?>
                            <tr>
                                <td><span class="po-number-badge">#PO-<?php echo $po['id']; ?></span></td>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($po['supplier_name']); ?></strong></td>
                                <td>
                                    <ul class="po-item-list">
                                    <?php foreach ($poItemsMap[$po['id']] ?? [] as $item): ?>
                                        <li>• <?php echo htmlspecialchars($item['ingredient_name']); ?> (<?php echo number_format($item['quantity_ordered'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>)</li>
                                    <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td><strong>₱<?php echo number_format((float) $po['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php
                                        $st = $po['status'];
                                        $badgeClass = $st === 'received' ? 'badge-success' : ($st === 'sent' ? 'badge-info' : ($st === 'draft' ? 'badge-warning' : 'badge-secondary'));
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($st); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($po['created_at'])); ?></td>
                                <td><?php echo $po['received_at'] ? date('M d, Y', strtotime($po['received_at'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$allPos): ?>
                                <tr><td colspan="7" class="text-muted text-center py-4">No purchase orders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SUPPLIERS DIRECTORY TAB -->
        <div class="tab-pane fade" id="suppliersTab" role="tabpanel" aria-labelledby="suppliers-tab">
            <div class="card po-table-card">
                <div class="card-body p-0 table-responsive">
                    <table class="table po-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Supplier Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td><strong>#<?php echo $sup['id']; ?></strong></td>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($sup['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sup['contact_email'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($sup['contact_phone'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($sup['address'] ?: '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$suppliers): ?>
                                <tr><td colspan="5" class="text-muted text-center py-4">No suppliers registered yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create PO -->
<div class="modal fade" id="createPoModal" tabindex="-1" role="dialog" aria-labelledby="createPoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create_po">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="createPoModalLabel">Create Purchase Order</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Select Supplier</label>
                        <select name="supplier_id" class="form-control" required>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Select Ingredient</label>
                        <select name="ingredient_id" class="form-control" required>
                            <?php foreach ($ingredients as $ing): ?>
                                <option value="<?php echo $ing['id']; ?>"><?php echo htmlspecialchars($ing['name']); ?> (Stock: <?php echo $ing['stock_quantity']; ?> <?php echo htmlspecialchars($ing['unit']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label class="font-weight-bold">Quantity</label>
                            <input type="number" step="0.01" name="quantity" class="form-control" value="10" min="0.1" required>
                        </div>
                        <div class="form-group col-6">
                            <label class="font-weight-bold">Unit Cost (₱)</label>
                            <input type="number" step="0.01" name="unit_cost" class="form-control" value="0.00" min="0" required>
                        </div>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="send_immediately" id="sendImmediately" class="custom-control-input" value="1">
                        <label class="custom-control-label font-weight-bold" for="sendImmediately">Send email to supplier immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Create PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Supplier -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_supplier">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold" id="addSupplierModalLabel">Add New Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Supplier Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Acme Coffee Beans Corp.">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" placeholder="supplier@company.com">
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" placeholder="+63 917 123 4567">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Supplier physical address or office location"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
