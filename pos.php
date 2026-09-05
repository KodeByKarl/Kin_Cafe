<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

require 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/ai_services.php';

requirePermission($pdo, 'pos.access');

$title = 'Point of Sale';

try {
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY COALESCE(category_order, 0), name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = $pdo->query("SELECT * FROM menu_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

$categories = sortMenuCategories($categories);

$items = $pdo->query("SELECT mi.*, mc.name as category_name, mp.name as parent_category_name
    FROM menu_items mi
    LEFT JOIN menu_categories mc ON mi.category_id = mc.id
    LEFT JOIN menu_categories mp ON mc.parent_id = mp.id
    ORDER BY COALESCE(mc.category_order, 0), COALESCE(mp.name, mc.name), mi.name")->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as &$item) {
    applyMenuItemAvailabilityDetails($pdo, $item);
}
unset($item);

$items = sortMenuItemsByCategoryPriority($items);
$unavailableMenuSnapshot = getUnavailableMenuItemsSnapshot($pdo, 6);

$activePromotions = $pdo->query("SELECT code, name, discount_type, discount_value, minimum_order, start_at, end_at
    FROM promotions
    WHERE active = 1 AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW())
    ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$menu = [];
$itemsByCode = [];
$searchItems = [];

function buildPosAvailabilityDetail(array $item): string {
    if (!empty($item['available'])) {
        return 'Ready to order';
    }

    if (!empty($item['unavailable_reason'])) {
        return (string) $item['unavailable_reason'];
    }

    $description = trim((string) ($item['description'] ?? ''));
    if ($description !== '') {
        return 'Unavailable right now. ' . $description;
    }

    return 'Unavailable right now.';
}

function buildPosItemPayload(array $item): array {
    return [
        'id' => (int) $item['id'],
        'name' => $item['name'],
        'price' => getEffectiveMenuItemPrice($item),
        'price_solo' => $item['price_solo'] !== null ? (float) $item['price_solo'] : null,
        'price_sharing' => $item['price_sharing'] !== null ? (float) $item['price_sharing'] : null,
        'price_hot' => $item['price_hot'] !== null ? (float) $item['price_hot'] : null,
        'price_iced' => $item['price_iced'] !== null ? (float) $item['price_iced'] : null,
        'size_option_enabled' => !empty($item['size_option_enabled']),
        'size_label_1' => $item['size_label_1'],
        'size_label_2' => $item['size_label_2'],
        'price_size_1' => $item['price_size_1'] !== null ? (float) $item['price_size_1'] : null,
        'price_size_2' => $item['price_size_2'] !== null ? (float) $item['price_size_2'] : null,
        'category_name' => $item['category_name'],
        'parent_category_name' => $item['parent_category_name'],
        'product_code' => $item['product_code'],
        'image' => $item['image'],
        'requires_temperature' => menuItemRequiresTemperatureSelection($item),
        'requires_size' => menuItemRequiresSizeSelection($item),
        'available' => !empty($item['available']),
        'availability_label' => !empty($item['available']) ? 'AVAILABLE NOW' : 'CURRENTLY UNAVAILABLE',
        'availability_detail' => buildPosAvailabilityDetail($item),
    ];
}

function renderPosMenuCard(array $item): void {
    $payload = buildPosItemPayload($item);
    $isAvailable = !empty($item['available']);
    $availabilityDetail = buildPosAvailabilityDetail($item);
    ?>
    <div class="food-card pos-modern-card <?php echo $isAvailable ? 'is-available' : 'is-unavailable'; ?>" data-item="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>" onclick="addToCartSelectFromElement(this)" aria-disabled="<?php echo $isAvailable ? 'false' : 'true'; ?>">
        <div class="thumb">
            <?php if ($item['image']): ?>
                <img src="assets/images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            <?php else: ?>
                <span class="pos-thumb-fallback"><?php echo htmlspecialchars(substr($item['name'], 0, 1)); ?></span>
            <?php endif; ?>
            <?php if (!$isAvailable): ?>
                <span class="pos-modern-card-badge">Unavailable</span>
            <?php endif; ?>
        </div>
        <div class="info">
            <h6><?php echo htmlspecialchars($item['name']); ?></h6>
            <div class="pos-modern-card-code"><?php echo htmlspecialchars($item['product_code'] ?: 'No code'); ?></div>
            <div class="pos-modern-card-price">₱<?php echo number_format(getEffectiveMenuItemPrice($item), 2); ?></div>
            <div class="pos-modern-card-status <?php echo $isAvailable ? 'is-available' : 'is-unavailable'; ?>"><?php echo $isAvailable ? 'AVAILABLE NOW' : 'CURRENTLY UNAVAILABLE'; ?></div>
            <?php if (!$isAvailable && !empty($item['unavailable_reason'])): ?>
                <div class="pos-modern-card-reason"><?php echo htmlspecialchars($item['unavailable_reason']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

foreach ($items as $item) {
    $menu[$item['category_name']][] = $item;
    $searchItems[] = buildPosItemPayload($item);
    if (!empty($item['product_code'])) {
        $itemsByCode[$item['product_code']] = buildPosItemPayload($item);
    }
}

$taxRate = 0.0;
$posRecommendations = getPosRecommendationData($pdo, $searchItems);
$posAssistant = getAiVirtualAssistantData($pdo);
$posAssistantCsrf = csrfToken('pos_virtual_assistant');
?>

<?php include 'includes/header.php'; ?>

<div class="app-shell pos-modern" data-tax-rate="<?php echo htmlspecialchars((string) $taxRate); ?>">
    <main class="main-content">
        <div class="pos-container pos-modern-layout">
            <section class="pos-modern-left">
                <div class="pos-recommendations-overlay" id="posRecommendationsOverlay" hidden>
                    <button type="button" class="kc-page-back" id="posRecommendationsBack">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 5.5 8.5 12 15 18.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>Back to POS</span>
                    </button>
                    <h2 class="pos-recommendations-overlay-title">Recommended add-ons</h2>
                    <p class="pos-recommendations-overlay-copy">Pairings from completed orders. Tap an item to add it to the current cart.</p>
                    <div class="pos-recommendations-overlay-list" id="posRecommendationsAllList"></div>
                </div>
                <div class="pos-catalog-toolbar">
                    <div class="pos-modern-search">
                        <div class="pos-search-input-row">
                            <input type="text" id="menuSearchInput" class="form-control" placeholder="Search menu items..." autocomplete="off">
                            <button type="button" class="btn btn-primary" id="menuSearchButton">Search</button>
                        </div>
                        <div id="menuSearchResults" class="list-group pos-modern-search-results"></div>
                    </div>
                    <div class="pos-toolbar-actions ai-module-utility-links">
                        <button type="button" class="btn btn-outline-secondary ai-shortcut-btn" id="posAssistantToggle" aria-controls="posAssistantPanel" aria-expanded="false">
                            <span class="ai-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v6A2.5 2.5 0 0 1 16.5 15H10l-4 4v-4H7.5A2.5 2.5 0 0 1 5 12.5v-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>
                            <span>Virtual Assistant</span>
                        </button>
                        <a class="btn btn-outline-secondary ai-shortcut-btn" href="ai_virtual_assistant.php"><span>Full Assistant</span></a>
                    </div>
                    <div class="category-tabs pos-modern-cats" id="categoryTabs">
                        <button type="button" class="active" onclick="selectCategory('cat-all', this)">All</button>
                        <?php foreach ($categories as $idx => $cat): ?>
                            <button type="button" onclick="selectCategory('<?php echo 'cat-' . $cat['id']; ?>', this)"><?php echo htmlspecialchars($cat['name']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($unavailableMenuSnapshot['total'])): ?>
                    <div class="alert alert-warning pos-unavailable-alert" role="status">
                        <strong><?php echo (int) $unavailableMenuSnapshot['total']; ?> menu item<?php echo (int) $unavailableMenuSnapshot['total'] === 1 ? '' : 's'; ?> unavailable</strong>
                        <span class="d-block small mb-2">Cashiers are notified here so unavailable orders can be avoided. Open Inventory or Menu to restock/fix.</span>
                        <ul class="mb-0 pl-3">
                            <?php foreach ($unavailableMenuSnapshot['items'] as $blocked): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars((string) $blocked['name']); ?>:</strong>
                                    <?php echo htmlspecialchars((string) $blocked['reason']); ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if ((int) $unavailableMenuSnapshot['total'] > count($unavailableMenuSnapshot['items'])): ?>
                                <li class="text-muted">+<?php echo (int) $unavailableMenuSnapshot['total'] - count($unavailableMenuSnapshot['items']); ?> more unavailable items…</li>
                            <?php endif; ?>
                        </ul>
                        <div class="mt-2">
                            <?php if (hasPermission($pdo, 'inventory.manage')): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="inventory.php?panel=reordering&filter=low">Open Inventory</a>
                            <?php endif; ?>
                            <?php if (hasPermission($pdo, 'menu.manage')): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="menu_management.php">Open Menu</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="category-panel active" id="cat-all">
                    <div class="pos-grid pos-modern-grid">
                        <?php foreach ($items as $item): ?>
                            <?php renderPosMenuCard($item); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($categories as $idx => $cat): ?>
                    <div class="category-panel" id="cat-<?php echo $cat['id']; ?>">
                        <div class="pos-grid pos-modern-grid">
                            <?php if (isset($menu[$cat['name']])): ?>
                                <?php foreach ($menu[$cat['name']] as $item): ?>
                                    <?php renderPosMenuCard($item); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No items in this category yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <aside class="order-panel pos-modern-right">
                <div class="pos-modern-bills-header">
                    <div class="pos-modern-bills-title">
                        <span class="pos-title-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="19" r="1.6" fill="currentColor"/><circle cx="17" cy="19" r="1.6" fill="currentColor"/><path d="M3 4h2.1l2.2 9.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H7.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span>Current Order</span>
                    </div>
                    <button type="button" class="pos-cart-toggle" id="posCartToggle" aria-expanded="true" title="Hide cart">Hide cart</button>
                    <button type="button" class="pos-clear-btn" onclick="clearCheckoutForm()">Clear</button>
                </div>
                <div class="pos-modern-body">
                    <div id="posMessage"></div>
                    <div class="order-items pos-cart-items" id="cart-items"></div>

                    <details class="pos-recommendations-panel" id="posRecommendationsPanel">
                        <summary class="pos-recommendations-head">
                            <div>
                                <h3>Recommended Add-ons</h3>
                                <p class="pos-recommendations-subtitle"><?php echo htmlspecialchars((string) ($posRecommendations['method_label'] ?? aiRecommendationMethodLabel())); ?></p>
                            </div>
                            <button type="button" class="pos-recommendations-link" id="posRecommendationsViewAll">View all</button>
                        </summary>
                        <div class="pos-recommendations-list" id="posRecommendationsList">
                            <p class="pos-recommendations-empty">Add an item to the cart to see pairing suggestions.</p>
                        </div>
                    </details>

                    <div class="pos-order-form">
                        <div class="pos-order-payment-grid">
                            <div class="pos-field-group">
                                <label class="pos-field-label" for="customerName">Customer Name</label>
                                <input type="text" id="customerName" class="form-control" placeholder="Optional">
                            </div>
                            <div class="pos-field-group">
                                <label class="pos-field-label" for="paymentAmount1">Amount Paid</label>
                                <input type="text" inputmode="decimal" id="paymentAmount1" class="form-control" placeholder="0.00" oninput="updateCartDisplay()" autocomplete="off">
                            </div>
                        </div>

                        <div class="pos-field-group mb-2 p-2 rounded" style="background:#f8fafc; border:1px solid #cbd5e1;">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" id="isStoreDiscount" class="custom-control-input" onchange="toggleStoreDiscount(); updateCartDisplay();">
                                <label class="custom-control-label font-weight-bold text-dark small" for="isStoreDiscount">Store Discount (10% Off)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" id="isPwdSenior" class="custom-control-input" onchange="togglePwdSeniorInput(); updateCartDisplay();">
                                <label class="custom-control-label font-weight-bold text-dark small" for="isPwdSenior">PWD / Senior Citizen Discount (20% Off, VAT-Exempt)</label>
                            </div>
                            <div id="pwdSeniorIdGroup" class="mt-2" style="display:none;">
                                <label class="pos-field-label small" for="pwdSeniorId">PWD / Senior Citizen ID #</label>
                                <input type="text" id="pwdSeniorId" class="form-control form-control-sm" placeholder="Enter Booklet / ID Number">
                            </div>
                            <p class="mb-0 mt-2 small text-muted">Store discount is separate from PWD/Senior. Only one discount can apply per order.</p>
                        </div>

                        <div class="pos-field-group">
                            <label class="pos-field-label" for="orderNotes">Order Notes</label>
                            <textarea id="orderNotes" class="form-control pos-order-notes" rows="3" maxlength="300" placeholder="Optional notes like no onions, less ice, extra sauce..."></textarea>
                        </div>

                        <input type="hidden" id="paymentMethod1" value="cash">
                        <div class="pos-payment-methods" aria-label="Payment methods">
                            <button type="button" class="pos-payment-pill active" data-method="cash"><span class="pos-payment-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="6.5" width="17" height="11" rx="2.5" stroke="currentColor" stroke-width="1.8"/><path d="M7 12h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span><span>Cash</span></button>
                        </div>

                        <div class="pos-order-summary">
                            <div class="pos-order-summary-row"><span>Subtotal</span><strong>₱<span id="cart-subtotal">0.00</span></strong></div>
                            <div class="pos-order-summary-row" id="cart-discount-row" hidden><span id="cart-discount-label">Discount</span><strong>- ₱<span id="cart-discount">0.00</span></strong></div>
                            <div class="pos-order-summary-total"><span>Total</span><strong>₱<span id="cart-total">0.00</span></strong></div>
                            <div class="pos-order-summary-row pos-order-summary-meta"><span>Amount Paid</span><strong>₱<span id="cart-paid">0.00</span></strong></div>
                            <div class="pos-order-summary-row pos-order-summary-meta"><span>Change</span><strong>₱<span id="cart-change">0.00</span></strong></div>
                        </div>
                    </div>
                </div>
                <div class="pos-order-footer">
                    <button class="pay-btn btn btn-primary btn-block pos-complete-btn" onclick="completeCheckout()">Save Pending Order</button>
                </div>
            </aside>
        </div>
    </main>
</div>

<script>
window.POS_CONFIG = {
    taxRate: <?php echo json_encode($taxRate); ?>,
    promotions: <?php echo json_encode($activePromotions); ?>,
    itemsByCode: <?php echo json_encode($itemsByCode); ?>,
    searchItems: <?php echo json_encode($searchItems); ?>,
    recommendations: <?php echo json_encode($posRecommendations); ?>,
    assistantCsrf: <?php echo json_encode($posAssistantCsrf); ?>
};

function updatePosRecommendations() {
    const list = document.getElementById('posRecommendationsList');
    const config = window.POS_CONFIG && window.POS_CONFIG.recommendations;
    if (!list || !config) {
        return;
    }

    const cartNames = new Set((cart || []).map((entry) => String(entry.name || '').trim().toLowerCase()).filter(Boolean));
    const cartIds = new Set((cart || []).map((entry) => Number(entry.id)).filter((id) => id > 0));
    const seen = new Set();
    const suggestions = [];

    function pushSuggestion(row) {
        const item = row && row.item ? row.item : null;
        if (!item || item.available === false) {
            return;
        }
        const itemId = Number(item.id);
        const itemName = String(item.name || '').trim().toLowerCase();
        if (!itemId || cartIds.has(itemId) || seen.has(itemId)) {
            return;
        }
        seen.add(itemId);
        suggestions.push({
            item,
            reason: String(row.reason || 'Suggested for this order'),
        });
    }

    if (cartNames.size > 0 && Array.isArray(config.pairs)) {
        config.pairs.forEach((pairRow) => {
            const anchorName = String(pairRow.anchor_item || '').trim().toLowerCase();
            if (!anchorName || !cartNames.has(anchorName)) {
                return;
            }
            pushSuggestion(pairRow);
        });
    }

    if (suggestions.length < 4 && Array.isArray(config.popular)) {
        config.popular.forEach((popularRow) => pushSuggestion(popularRow));
    }

    if (!suggestions.length) {
        list.innerHTML = '<p class="pos-recommendations-empty">No recommendations available yet. Complete more orders to build pairing history.</p>';
        refreshPosRecommendationsOverlay();
        return;
    }

    list.innerHTML = suggestions.slice(0, 4).map((entry) => renderPosRecommendationCard(entry)).join('');
    bindPosRecommendationCards(list);
    refreshPosRecommendationsOverlay();
}

function renderPosRecommendationCard(entry) {
    const item = entry.item || {};
    const price = typeof item.price === 'number' ? item.price : Number(item.price || 0);
    const encodedItem = encodeURIComponent(JSON.stringify(item));
    return `
        <button type="button" class="pos-recommendation-card" data-item="${encodedItem}">
            <span class="pos-recommendation-copy">
                <strong>${escapeHtml(item.name || '')}</strong>
                <small>${escapeHtml(entry.reason || '')}</small>
            </span>
            <span class="pos-recommendation-price">₱${formatCurrency(price)}</span>
        </button>
    `;
}

function bindPosRecommendationCards(root) {
    if (!root) {
        return;
    }
    root.querySelectorAll('.pos-recommendation-card').forEach((button) => {
        button.addEventListener('click', () => {
            if (!button.dataset.item) {
                return;
            }
            try {
                addToCartSelect(JSON.parse(decodeURIComponent(button.dataset.item)));
            } catch (error) {
                console.error('Unable to add recommended item', error);
            }
        });
    });
}

function collectPosRecommendationEntries(limit) {
    const config = window.POS_CONFIG && window.POS_CONFIG.recommendations;
    const cartIds = new Set((cart || []).map((entry) => Number(entry.id)).filter((id) => id > 0));
    const seen = new Set();
    const suggestions = [];

    function pushSuggestion(row) {
        const item = row && row.item ? row.item : null;
        if (!item || item.available === false) {
            return;
        }
        const itemId = Number(item.id);
        if (!itemId || cartIds.has(itemId) || seen.has(itemId)) {
            return;
        }
        seen.add(itemId);
        suggestions.push({
            item,
            reason: String(row.reason || 'Suggested for this order'),
        });
    }

    if (config && Array.isArray(config.pairs)) {
        config.pairs.forEach(pushSuggestion);
    }
    if (config && Array.isArray(config.popular)) {
        config.popular.forEach(pushSuggestion);
    }

    if (typeof limit === 'number' && limit > 0) {
        return suggestions.slice(0, limit);
    }
    return suggestions;
}

function initPosAssistantPanel() {
    const panel = document.getElementById('posAssistantPanel');
    const backdrop = document.getElementById('posAssistantBackdrop');
    const toggle = document.getElementById('posAssistantToggle');
    const closeBtn = document.getElementById('posAssistantClose');
    const form = document.getElementById('posAssistantForm');
    const questionInput = document.getElementById('posAssistantQuestion');
    const chatFeed = document.getElementById('posChatFeed');
    const typingIndicator = document.getElementById('posTypingIndicator');
    const promptButtons = document.querySelectorAll('.pos-chip, .pos-assistant-prompt');

    if (!panel || !toggle || !form || !questionInput || !chatFeed) {
        return;
    }

    function scrollToBottom() {
        if (chatFeed) {
            chatFeed.scrollTop = chatFeed.scrollHeight;
        }
    }

    function setOpen(isOpen) {
        panel.hidden = !isOpen;
        panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (backdrop) backdrop.hidden = !isOpen;
        document.body.classList.toggle('pos-assistant-open', isOpen);
        if (isOpen) {
            setTimeout(() => {
                questionInput.focus();
                scrollToBottom();
            }, 120);
        }
    }

    toggle.addEventListener('click', () => setOpen(panel.hidden));
    if (closeBtn) closeBtn.addEventListener('click', () => setOpen(false));
    if (backdrop) backdrop.addEventListener('click', () => setOpen(false));

    function appendUserMessage(text) {
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div = document.createElement('div');
        div.className = 'chat-msg user';
        div.innerHTML = `
            <div class="chat-bubble">
                ${escapeHtml(text)}
                <div class="chat-time">${timeStr}</div>
            </div>
        `;
        chatFeed.appendChild(div);
        scrollToBottom();
    }

    function appendAssistantMessage(htmlContent, sourceText) {
        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div = document.createElement('div');
        div.className = 'chat-msg assistant';
        const sourceHtml = sourceText ? `<div class="chat-source">${escapeHtml(sourceText)}</div>` : '';
        div.innerHTML = `
            <div class="chat-bubble">
                ${htmlContent}
                ${sourceHtml}
                <div class="chat-time">${timeStr}</div>
            </div>
        `;
        chatFeed.appendChild(div);
        scrollToBottom();
    }

    function setTyping(isTyping) {
        if (!typingIndicator) return;
        typingIndicator.hidden = !isTyping;
        if (isTyping) {
            scrollToBottom();
        }
    }

    function askAssistant(question) {
        const q = String(question || '').trim();
        if (!q) return;

        appendUserMessage(q);
        questionInput.value = '';
        setTyping(true);

        const csrfInput = form.querySelector('input[name="csrf_token"]');
        const csrfToken = (csrfInput && csrfInput.value)
            || (window.POS_CONFIG && window.POS_CONFIG.assistantCsrf)
            || '';

        const startTime = Date.now();

        fetch('pos_assistant.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({
                question: q,
                csrf_token: csrfToken
            })
        })
            .then(async (response) => {
                const data = await response.json();
                const elapsedTime = Date.now() - startTime;
                const minDelay = 450; // Smooth realistic typing feel
                const remainingDelay = Math.max(0, minDelay - elapsedTime);

                setTimeout(() => {
                    setTyping(false);
                    if (!response.ok || !data.success) {
                        appendAssistantMessage(`<span class="text-danger">${escapeHtml(data.message || 'Unable to reach the assistant.')}</span>`);
                        return;
                    }
                    const noticeHtml = data.notice ? `<div class="text-warning small mb-1">${escapeHtml(data.notice)}</div>` : '';
                    const answerStr = String(data.answer || '');
                    const sourceText = data.provider || data.source || '';
                    appendAssistantMessage(noticeHtml + answerStr, sourceText);
                }, remainingDelay);
            })
            .catch((error) => {
                setTyping(false);
                appendAssistantMessage(`<span class="text-danger">${escapeHtml(error.message || 'Unable to reach the assistant.')}</span>`);
            });
    }

    promptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const q = button.getAttribute('data-question') || button.textContent.trim();
            askAssistant(q);
        });
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        askAssistant(questionInput.value);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    updatePosRecommendations();
    initPosAssistantPanel();
    initPosCartToggle();
    initPosRecommendationsViewAll();
});

function initPosCartToggle() {
    const layout = document.querySelector('.pos-container');
    const toggle = document.getElementById('posCartToggle');
    if (!layout || !toggle) {
        return;
    }
    const collapsed = sessionStorage.getItem('kc_pos_cart_collapsed') === '1';
    layout.classList.toggle('pos-cart-collapsed', collapsed);
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    toggle.textContent = collapsed ? 'Show cart' : 'Hide cart';
    toggle.addEventListener('click', function () {
        const next = !layout.classList.contains('pos-cart-collapsed');
        layout.classList.toggle('pos-cart-collapsed', next);
        toggle.setAttribute('aria-expanded', next ? 'false' : 'true');
        toggle.textContent = next ? 'Show cart' : 'Hide cart';
        sessionStorage.setItem('kc_pos_cart_collapsed', next ? '1' : '0');
    });
}

function refreshPosRecommendationsOverlay() {
    const overlay = document.getElementById('posRecommendationsOverlay');
    const allList = document.getElementById('posRecommendationsAllList');
    if (!overlay || overlay.hidden || !allList) {
        return;
    }
    const entries = collectPosRecommendationEntries(18);
    allList.innerHTML = entries.length
        ? entries.map((entry) => renderPosRecommendationCard(entry)).join('')
        : '<p class="pos-recommendations-empty">Add items to the cart to see pairing suggestions. Pairings come from completed-order patterns.</p>';
    bindPosRecommendationCards(allList);
}

function initPosRecommendationsViewAll() {
    const button = document.getElementById('posRecommendationsViewAll');
    const overlay = document.getElementById('posRecommendationsOverlay');
    const back = document.getElementById('posRecommendationsBack');
    const catalog = document.querySelector('.pos-modern-left');
    if (!button || !overlay) {
        return;
    }

    function setOpen(isOpen) {
        overlay.hidden = !isOpen;
        if (catalog) {
            catalog.classList.toggle('is-showing-recommendations', isOpen);
        }
        if (isOpen) {
            refreshPosRecommendationsOverlay();
        }
    }

    button.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        setOpen(true);
        const panel = document.getElementById('posRecommendationsPanel');
        if (panel && panel.tagName === 'DETAILS') {
            panel.open = true;
        }
    });
    if (back) {
        back.addEventListener('click', function () {
            setOpen(false);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            setOpen(false);
        }
    });
}

function togglePwdSeniorInput() {
    const isPwdSenior = document.getElementById('isPwdSenior');
    const isChecked = !!(isPwdSenior && isPwdSenior.checked);
    const group = document.getElementById('pwdSeniorIdGroup');
    if (group) {
        group.style.display = isChecked ? 'block' : 'none';
    }
    if (isChecked) {
        const storeDiscount = document.getElementById('isStoreDiscount');
        if (storeDiscount) {
            storeDiscount.checked = false;
        }
    }
}

function toggleStoreDiscount() {
    const storeDiscount = document.getElementById('isStoreDiscount');
    if (!(storeDiscount && storeDiscount.checked)) {
        return;
    }
    const isPwdSenior = document.getElementById('isPwdSenior');
    if (isPwdSenior) {
        isPwdSenior.checked = false;
    }
    togglePwdSeniorInput();
}

function selectCategory(panelId, button) {
    document.querySelectorAll('.category-panel').forEach(panel => panel.classList.remove('active'));
    document.querySelectorAll('.category-tabs button').forEach(btn => btn.classList.remove('active'));

    const panel = document.getElementById(panelId);
    if (panel) {
        panel.classList.add('active');
    }
    if (button) {
        button.classList.add('active');
    }
}

function addToCartSelectFromElement(element) {
    if (!element || !element.dataset.item) {
        return;
    }
    addToCartSelect(JSON.parse(element.dataset.item));
}

function addToCartSelect(item) {
    if (!item || item.available === false) {
        showPosMessage(item && item.availability_detail ? item.availability_detail : 'This item is currently unavailable.', 'warning');
        return;
    }

    const hasSolo = item.price_solo !== null && Number(item.price_solo) > 0;
    const hasSharing = item.price_sharing !== null && Number(item.price_sharing) > 0;
    const requiresTemperature = Boolean(item.requires_temperature);
    const requiresSize = Boolean(item.requires_size);
    const hasHot = item.price_hot !== null && Number(item.price_hot) > 0;
    const hasIced = item.price_iced !== null && Number(item.price_iced) > 0;
    const hasSize1 = item.size_label_1 && item.price_size_1 !== null && Number(item.price_size_1) > 0;
    const hasSize2 = item.size_label_2 && item.price_size_2 !== null && Number(item.price_size_2) > 0;
    const title = document.getElementById('snackChoiceTitle');
    const body = document.getElementById('snackChoiceBody');
    const primaryBtn = document.getElementById('snackSoloBtn');
    const secondaryBtn = document.getElementById('snackSharingBtn');

    function showSizeChoice() {
        if (title) title.textContent = `Choose size for ${item.name}`;
        if (body) body.innerHTML = `${renderPopupImage(item)}<p>Select a size for this item.</p>`;
        if (primaryBtn) {
            primaryBtn.textContent = item.size_label_1 || 'Size 1';
            primaryBtn.style.display = hasSize1 ? 'inline-block' : 'none';
            primaryBtn.onclick = function () {
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, Number(item.price_size_1), 'normal', null, item.product_code || '', item.category_name || '', item.size_label_1 || '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (secondaryBtn) {
            secondaryBtn.textContent = item.size_label_2 || 'Size 2';
            secondaryBtn.style.display = hasSize2 ? 'inline-block' : 'none';
            secondaryBtn.onclick = function () {
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, Number(item.price_size_2), 'normal', null, item.product_code || '', item.category_name || '', item.size_label_2 || '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (hasSize1 && !hasSize2 && primaryBtn) {
            primaryBtn.onclick();
            return;
        }
        showModal('snackChoiceModal');
    }

    if (requiresSize) {
        showSizeChoice();
        return;
    }

    function showTemperatureChoice() {
        if (title) title.textContent = `Choose temperature for ${item.name}`;
        if (body) body.innerHTML = `${renderPopupImage(item)}<p>Select Hot Drink or Iced Drink for this item.</p>`;
        if (primaryBtn) {
            primaryBtn.textContent = 'Hot Drink';
            primaryBtn.style.display = hasHot ? 'inline-block' : 'none';
            primaryBtn.onclick = function () {
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, Number(item.price_hot), 'normal', 'hot', item.product_code || '', item.category_name || '', '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (secondaryBtn) {
            secondaryBtn.textContent = 'Iced Drink';
            secondaryBtn.style.display = hasIced ? 'inline-block' : 'none';
            secondaryBtn.onclick = function () {
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, Number(item.price_iced), 'normal', 'iced', item.product_code || '', item.category_name || '', '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (hasHot && !hasIced && primaryBtn) {
            primaryBtn.onclick();
            return;
        }
        if (hasIced && !hasHot && secondaryBtn) {
            secondaryBtn.onclick();
            return;
        }
        showModal('snackChoiceModal');
    }

    if (requiresTemperature) {
        showTemperatureChoice();
        return;
    }

    if (hasSolo || hasSharing) {
        if (title) title.textContent = `Choose option for ${item.name}`;
        if (body) body.innerHTML = `${renderPopupImage(item)}<p>Select Solo or Sharing for this item.</p>`;
        if (primaryBtn) {
            primaryBtn.textContent = 'Solo';
            primaryBtn.style.display = hasSolo ? 'inline-block' : 'none';
            primaryBtn.onclick = function () {
                const selectedPrice = hasSolo ? Number(item.price_solo) : Number(item.price);
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, selectedPrice, 'solo', null, item.product_code || '', item.category_name || '', '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (secondaryBtn) {
            secondaryBtn.textContent = 'Sharing';
            secondaryBtn.style.display = hasSharing ? 'inline-block' : 'none';
            secondaryBtn.onclick = function () {
                const selectedPrice = hasSharing ? Number(item.price_sharing) : Number(item.price);
                const confirmed = function (customizations) {
                    addToCart(item.id, item.name, selectedPrice, 'sharing', null, item.product_code || '', item.category_name || '', '', customizations);
                    $('#snackChoiceModal').modal('hide');
                };
                maybeShowRecipeCustomization(item, confirmed);
            };
        }
        if (hasSolo && !hasSharing && primaryBtn) {
            primaryBtn.onclick();
            return;
        }
        if (hasSharing && !hasSolo && secondaryBtn) {
            secondaryBtn.onclick();
            return;
        }
        showModal('snackChoiceModal');
        return;
    }

    const confirmed = function (customizations) {
        addToCart(item.id, item.name, item.price, 'normal', null, item.product_code || '', item.category_name || '', '', customizations);
    };
    maybeShowRecipeCustomization(item, confirmed);
}

function getCustomizationKey(customizations) {
    if (!Array.isArray(customizations) || customizations.length === 0) {
        return 'default';
    }
    return customizations.map((entry) => `${entry.ingredientId}:${entry.include ? 1 : 0}:${Number(entry.quantity).toFixed(2)}`).join('|');
}

function getCustomizationText(customizations) {
    if (!Array.isArray(customizations) || customizations.length === 0) {
        return '';
    }
    const changes = customizations
        .filter((entry) => !entry.include || Number(entry.quantity) !== Number(entry.defaultQuantity))
        .map((entry) => {
            if (!entry.include) {
                return `No ${entry.ingredientName}`;
            }
            return `${entry.ingredientName} ${Number(entry.quantity).toFixed(2)} ${entry.unit}`;
        })
        .filter(Boolean);
    return changes.length ? `Custom: ${changes.join(', ')}` : '';
}

function renderPopupImage(item) {
    if (!item) {
        return '';
    }
    const imageHtml = item.image
        ? `<img src="assets/images/${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="popup-product-img"/>`
        : `<div class="popup-product-placeholder" aria-hidden="true"></div>`;
    const categoryText = item.category_name ? escapeHtml(item.category_name) : 'Menu';
    const codeText = item.product_code ? escapeHtml(item.product_code) : '';
    const priceText = item.price ? `₱${formatCurrency(item.price)}` : '₱0.00';
    const isAvailable = item.available !== false;
    const availabilityHtml = isAvailable
        ? `<span class="popup-status-badge is-available">Available</span>`
        : `<span class="popup-status-badge is-unavailable">Unavailable</span>`;
    const reasonHtml = (!isAvailable && item.availability_detail)
        ? `<p class="popup-product-reason">${escapeHtml(item.availability_detail)}</p>`
        : '';

    return `
        <div class="popup-product-card">
            <div class="popup-product-media">${imageHtml}</div>
            <div class="popup-product-info">
                <div class="popup-product-topline">
                    ${availabilityHtml}
                    ${codeText ? `<span class="popup-product-code">${codeText}</span>` : ''}
                </div>
                <h4 class="popup-product-title">${escapeHtml(item.name)}</h4>
                <div class="popup-product-price">${priceText}</div>
                <p class="popup-product-category">${categoryText}</p>
                ${reasonHtml}
            </div>
        </div>
    `;
}

function showModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return;
    }
    if (window.jQuery && typeof window.jQuery(modalElement).modal === 'function') {
        window.jQuery(modalElement).modal('show');
        return;
    }
    if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        const instance = window.bootstrap.Modal.getInstance(modalElement) || new window.bootstrap.Modal(modalElement);
        instance.show();
        return;
    }
    modalElement.classList.add('show');
    modalElement.style.display = 'block';
    modalElement.removeAttribute('aria-hidden');
}

function hideModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) {
        return;
    }
    if (window.jQuery && typeof window.jQuery(modalElement).modal === 'function') {
        window.jQuery(modalElement).modal('hide');
        return;
    }
    if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        const instance = window.bootstrap.Modal.getInstance(modalElement);
        if (instance) {
            instance.hide();
            return;
        }
    }
    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    modalElement.setAttribute('aria-hidden', 'true');
}

function showNoRecipePopup(item, onComplete) {
    const title = document.getElementById('noRecipeTitle');
    const body = document.getElementById('noRecipeBody');
    const button = document.getElementById('noRecipeAcknowledge');

    if (title) {
        title.textContent = 'Add to order';
    }
    if (body) {
        body.innerHTML = `
            ${renderPopupImage(item)}
            <p class="popup-notice-text">Ready to add — no customization needed.</p>
        `;
    }
    if (button) {
        button.textContent = 'Add item';
        button.onclick = function () {
            $('#noRecipeModal').modal('hide');
            onComplete([]);
        };
    }
    $('#noRecipeModal').modal('show');
}

function maybeShowRecipeCustomization(item, onComplete) {
    if (!item || !item.id) {
        onComplete([]);
        return;
    }
    fetch(`get_item.php?id=${encodeURIComponent(item.id)}`)
        .then((response) => response.json())
        .then((data) => {
            const recipe = (data && Array.isArray(data.recipe)) ? data.recipe : [];
            if (recipe.length === 0) {
                showNoRecipePopup(item, onComplete);
                return;
            }
            const title = document.getElementById('recipeCustomizationTitle');
            const info = document.getElementById('recipeCustomizationInfo');
            const body = document.getElementById('recipeCustomizationBody');
            const submitBtn = document.getElementById('recipeCustomizationSubmit');

            if (title) {
                title.textContent = `Customize Ingredients`;
            }
            if (body) {
                body.innerHTML = `
                    ${renderPopupImage(item)}
                    <p class="popup-notice-text">Uncheck ingredients to remove them from this order.</p>
                    <div class="table-responsive rounded-lg border">
                        <table class="table table-hover align-middle mb-0 custom-recipe-table">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center">Use</th>
                                    <th>Ingredient</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${recipe.map((row, idx) => `
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" id="rcUse${idx}" class="recipe-customize-include" data-idx="${idx}" data-quantity="${Number(row.quantity).toFixed(2)}" checked style="width:18px;height:18px;cursor:pointer;">
                                        </td>
                                        <td class="font-weight-bold text-dark">${escapeHtml(row.ingredient_name || '')}</td>
                                        <td><span class="badge badge-pill badge-light border px-2 py-1">${Number(row.quantity).toFixed(2)}</span></td>
                                        <td class="text-muted small">${escapeHtml(row.quantity_unit || row.ingredient_unit || '')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            if (info) {
                info.textContent = '';
            }

            if (submitBtn) {
                submitBtn.onclick = function () {
                    const customizations = [];
                    const includeInputs = document.querySelectorAll('.recipe-customize-include');
                    includeInputs.forEach((input) => {
                        const idx = Number(input.dataset.idx);
                        const include = input.checked;
                        const quantity = Number(input.dataset.quantity || 0);
                        const row = recipe[idx];
                        if (!row) {
                            return;
                        }
                        customizations.push({
                            ingredientId: row.ingredient_id,
                            ingredientName: row.ingredient_name || '',
                            quantity: quantity,
                            defaultQuantity: Number(row.quantity),
                            unit: row.quantity_unit || row.ingredient_unit || '',
                            include,
                        });
                    });
                    hideModal('recipeCustomizationModal');
                    onComplete(customizations);
                };
            }

            showModal('recipeCustomizationModal');
        })
        .catch(() => {
            onComplete([]);
        });
}
</script>

<div id="snackChoiceModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="snackChoiceTitle" class="modal-title">Choose Option</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="snackChoiceBody">Select Solo or Sharing for this item.</p>
            </div>
            <div class="modal-footer">
                <button id="snackSoloBtn" type="button" class="btn btn-primary">Solo</button>
                <button id="snackSharingBtn" type="button" class="btn btn-secondary">Sharing</button>
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div id="recipeCustomizationModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="recipeCustomizationTitle" class="modal-title">Customize Ingredients</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="recipeCustomizationInfo" class="mb-3 text-muted">Adjust ingredient usage before adding this item to the cart.</p>
                <div id="recipeCustomizationBody"></div>
            </div>
            <div class="modal-footer">
                <button id="recipeCustomizationSubmit" type="button" class="btn btn-primary">Add to cart</button>
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div id="noRecipeModal" class="modal fade pos-item-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content pos-item-modal-content">
            <div class="modal-header pos-item-modal-header">
                <h5 id="noRecipeTitle" class="modal-title">Add to order</h5>
                <button type="button" class="pos-item-modal-close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body pos-item-modal-body" id="noRecipeBody"></div>
            <div class="modal-footer pos-item-modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button id="noRecipeAcknowledge" type="button" class="btn btn-primary pos-item-modal-action">Add item</button>
            </div>
        </div>
    </div>
</div>

<div id="receiptModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="receiptContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="printReceipt()">Print</button>
                <a class="btn btn-outline-secondary" href="orders_history.php?status=pending">View Pending Orders</a>
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<aside class="pos-assistant-panel" id="posAssistantPanel" aria-hidden="true" hidden>
    <div class="pos-assistant-head">
        <div class="assistant-head-brand">
            <div>
                <h2>Kin AI Assistant</h2>
                <span class="assistant-status-online"><span class="status-dot"></span> Active & Ready</span>
            </div>
        </div>
        <button type="button" class="pos-assistant-close" id="posAssistantClose" aria-label="Close assistant">&times;</button>
    </div>

    <div class="pos-chat-feed" id="posChatFeed" aria-live="polite">
        <div class="chat-msg assistant">
            <div class="chat-bubble">
                Hello! I am your <strong>Kin Cafe Virtual Assistant</strong>. Ask me anything about sales forecasts, inventory stock, menu demand, customer trends, or POS operation guidance!
                <div class="chat-time">Just now</div>
            </div>
        </div>
    </div>

    <div class="typing-indicator" id="posTypingIndicator" hidden>
        <div class="typing-bubble">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="typing-text">Kin AI is typing...</span>
        </div>
    </div>

    <div class="pos-assistant-footer">
        <div class="pos-assistant-chips" id="posAssistantChips">
            <button type="button" class="pos-chip" data-question="What are forecasted sales for the next 7 days?">
                <span>7-Day Sales Forecast</span>
            </button>
            <button type="button" class="pos-chip" data-question="Which ingredient needs reordering first?">
                <span>Low Stock & Reorders</span>
            </button>
            <button type="button" class="pos-chip" data-question="What menu item demand is strongest right now?">
                <span>Top Selling Demand</span>
            </button>
            <button type="button" class="pos-chip" data-question="Which customer preference stands out?">
                <span>Customer Preferences</span>
            </button>
            <button type="button" class="pos-chip" data-question="Are there any expiring ingredients or waste alerts?">
                <span>Waste & Expiration Alerts</span>
            </button>
            <button type="button" class="pos-chip" data-question="How do I process discounts and receipts in POS?">
                <span>POS Quick Help</span>
            </button>
        </div>

        <form class="pos-assistant-chat-form" id="posAssistantForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($posAssistantCsrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="chat-input-row">
                <input type="text" id="posAssistantQuestion" class="form-control chat-input" placeholder="Ask a question or select a prompt..." autocomplete="off">
                <button type="submit" class="btn btn-primary send-btn" title="Send Message">Send</button>
            </div>
        </form>
    </div>
</aside>
<div class="pos-assistant-backdrop" id="posAssistantBackdrop" hidden></div>

<?php include 'includes/footer.php'; ?>
