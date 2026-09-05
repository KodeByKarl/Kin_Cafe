// Custom JavaScript for Kin Cafe

let cart = [];

function formatCurrency(value) {
    const n = Number(value || 0);
    if (!Number.isFinite(n)) {
        return '0.00';
    }
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatOptionLabel(value) {
    return String(value || '')
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function getItemOptionText(item) {
    const labels = [];
    if (item && item.variant && item.variant !== 'normal') {
        labels.push(formatOptionLabel(item.variant));
    }
    if (item && item.temperature) {
        labels.push(formatOptionLabel(item.temperature));
    }
    const sizeLabel = item ? (item.sizeLabel || item.size_label || '') : '';
    if (sizeLabel) {
        labels.push(String(sizeLabel));
    }
    return labels.join(', ');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getPosItemById(itemId) {
    const items = (window.POS_CONFIG && window.POS_CONFIG.searchItems) || [];
    return items.find((it) => Number(it.id) === Number(itemId)) || null;
}

function showPosMessage(message, type = 'info') {
    const container = document.getElementById('posMessage');
    if (container) {
        container.innerHTML = `<div class="alert alert-${type} mb-3">${escapeHtml(message)}</div>`;
    }
    if ((type === 'danger' || type === 'warning') && window.KinAlertModal) {
        const title = type === 'danger' ? 'Cannot continue' : 'Check this first';
        window.KinAlertModal.alert(String(message || 'Please review this action.'), title, 'OK');
    }
}

function clearPosMessage() {
    const container = document.getElementById('posMessage');
    if (container) {
        container.innerHTML = '';
    }
}

function getEstimatedPromotion(subtotal) {
    const promoInput = document.getElementById('promoCode');
    const promotions = (window.POS_CONFIG && window.POS_CONFIG.promotions) || [];
    const code = promoInput ? promoInput.value.trim().toUpperCase() : '';

    if (!code || subtotal <= 0) {
        return { promotion: null, discountAmount: 0 };
    }

    const promotion = promotions.find((item) => item.code.toUpperCase() === code && Number(item.minimum_order || 0) <= subtotal);
    if (!promotion) {
        return { promotion: null, discountAmount: 0 };
    }

    let discountAmount = 0;
    if (promotion.discount_type === 'percent') {
        discountAmount = subtotal * (Number(promotion.discount_value || 0) / 100);
    } else {
        discountAmount = Number(promotion.discount_value || 0);
    }

    return {
        promotion,
        discountAmount: Math.min(discountAmount, subtotal)
    };
}

function getPaymentEntries() {
    const amountEl = document.getElementById('paymentAmount1');
    if (!amountEl) {
        return [];
    }
    const parser = (window.KinCashChange && window.KinCashChange.parseMoneyInput) ? window.KinCashChange.parseMoneyInput : (v) => Number(v || 0);
    const round = (window.KinCashChange && window.KinCashChange.roundToCents) ? window.KinCashChange.roundToCents : (n) => Math.round((Number(n) || 0) * 100) / 100;
    const amount = round(parser(amountEl.value));
    const finite = (typeof amount === 'number') && isFinite(amount);
    if (!finite || amount <= 0) {
        return [];
    }
    return [{ method: 'cash', amount, reference: '' }];
}

function getCartSummary() {
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const round = (window.KinCashChange && window.KinCashChange.roundToCents) ? window.KinCashChange.roundToCents : (n) => Math.round((Number(n) || 0) * 100) / 100;
    
    const isPwdSeniorEl = document.getElementById('isPwdSenior');
    const isPwdSenior = isPwdSeniorEl ? isPwdSeniorEl.checked : false;
    const isStoreDiscountEl = document.getElementById('isStoreDiscount');
    const isStoreDiscount = isStoreDiscountEl ? isStoreDiscountEl.checked : false;

    let discount = 0;
    let total = subtotal;
    let promotionObj = null;
    let discountType = null;
    let discountLabel = 'Discount';

    if (isPwdSenior && subtotal > 0) {
        const vatExclusive = round(subtotal / 1.12);
        discount = round(vatExclusive * 0.20);
        total = round(Math.max(vatExclusive - discount, 0));
        discountType = 'pwd_senior';
        discountLabel = 'PWD/Senior (20% Off)';
    } else if (isStoreDiscount && subtotal > 0) {
        discount = round(subtotal * 0.10);
        total = round(Math.max(subtotal - discount, 0));
        discountType = 'store';
        discountLabel = 'Store Discount (10% Off)';
    } else {
        const promotion = getEstimatedPromotion(subtotal);
        discount = promotion.discountAmount;
        total = Math.max(subtotal - discount, 0);
        promotionObj = promotion.promotion;
        if (discount > 0) {
            discountType = 'promo';
            discountLabel = 'Promo Code Discount';
        }
    }

    const payments = getPaymentEntries();
    const paid = round(payments.reduce((sum, entry) => sum + Number(entry.amount || 0), 0));
    const changeFn = (window.KinCashChange && window.KinCashChange.computeChange) ? window.KinCashChange.computeChange : (t, p) => Math.max((Number(p) || 0) - (Number(t) || 0), 0);
    const change = round(changeFn(total, paid));

    return {
        subtotal: round(subtotal),
        discount: round(discount),
        discountType,
        discountLabel,
        tax: 0,
        total: round(total),
        paid,
        change,
        promotion: promotionObj,
        isPwdSenior,
        isStoreDiscount
    };
}

function addToCart(itemId, name, price, variant = 'normal', temperature = null, productCode = '', categoryName = '', sizeLabel = '', customizations = []) {
    const customizationKey = Array.isArray(customizations) && customizations.length ? getCustomizationKey(customizations) : 'default';
    const key = `${variant}-${temperature || 'default'}-${sizeLabel || 'default'}-${customizationKey}`;
    const existing = cart.find((item) => item.id === itemId && item.key === key);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            id: itemId,
            key,
            name,
            price: Number(price),
            variant,
            temperature,
            sizeLabel,
            productCode,
            categoryName,
            quantity: 1,
            customizations: Array.isArray(customizations) ? customizations : []
        });
    }

    clearPosMessage();
    updateCartDisplay();
}

async function removeFromCart(itemKey) {
    const ok = window.KinAlertModal
        ? await window.KinAlertModal.confirm('Remove this item from the cart?', 'Remove item', 'Remove', 'Keep')
        : window.confirm('Remove this item from the cart?');
    if (!ok) {
        return;
    }
    cart = cart.filter((item) => `${item.id}_${item.key}` !== itemKey);
    updateCartDisplay();
}

function updateQuantity(itemKey, quantity) {
    const item = cart.find((entry) => `${entry.id}_${entry.key}` === itemKey);
    if (!item) {
        return;
    }

    item.quantity = Math.max(1, Number(quantity) || 1);
    updateCartDisplay();
}

function updateCartDisplay() {
    const cartContainer = document.getElementById('cart-items');
    if (!cartContainer) {
        return;
    }

    cartContainer.innerHTML = '';

    if (!cart.length) {
        cartContainer.innerHTML = `
            <div class="pos-cart-empty">
                <div>
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="20" r="1.5" fill="currentColor"/><circle cx="17" cy="20" r="1.5" fill="currentColor"/><path d="M3 4h2.2l2.1 9a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H7.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <strong>Your cart is empty.</strong>
                    <span>Select items to start an order.</span>
                </div>
            </div>
        `;
    }

    let cartHasUnavailableItems = false;

    cart.forEach((item) => {
        const itemKey = `${item.id}_${item.key}`;
        const optionText = getItemOptionText(item);
        const customizationsText = getCustomizationText(item.customizations || []);
        const metaParts = [];
        if (item.categoryName) metaParts.push(item.categoryName);
        if (item.productCode) metaParts.push(item.productCode);
        if (optionText) metaParts.push(optionText);
        const metaText = metaParts.length ? `<div><small class="text-muted">${escapeHtml(metaParts.join(' | '))}</small></div>` : '';
        const currentPosItem = getPosItemById(item.id);
        const isUnavailable = currentPosItem && currentPosItem.available === false;
        const availabilityDetail = currentPosItem ? (currentPosItem.availability_detail || '') : '';

        if (isUnavailable) {
            cartHasUnavailableItems = true;
        }

        cartContainer.innerHTML += `
            <div class="cart-item${isUnavailable ? ' cart-item-unavailable' : ''}">
                <div class="cart-item-description">
                    <strong>${escapeHtml(item.name)}</strong>
                    ${metaText}
                    ${customizationsText ? `<div><small class="text-muted">${escapeHtml(customizationsText)}</small></div>` : ''}
                    <small>₱${formatCurrency(item.price)} each</small>
                    ${isUnavailable ? `<div class="cart-item-unavailable-text">${escapeHtml(availabilityDetail || 'This item is currently unavailable due to ingredient status.')}</div>` : ''}
                </div>
                <div class="cart-item-actions">
                    <div class="quantity-controls" aria-label="Quantity controls for ${escapeHtml(item.name)}">
                        <button class="cart-control-btn" aria-label="Decrease quantity for ${escapeHtml(item.name)}" onclick="updateQuantity('${itemKey}', ${item.quantity - 1})">-</button>
                        <span class="cart-control-value">${item.quantity}</span>
                        <button class="cart-control-btn" aria-label="Increase quantity for ${escapeHtml(item.name)}" onclick="updateQuantity('${itemKey}', ${item.quantity + 1})">+</button>
                        <button class="cart-control-btn is-remove" aria-label="Remove ${escapeHtml(item.name)} from cart" onclick="removeFromCart('${itemKey}')">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 4.5h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5.5 7h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8.5 7v9.2a1.3 1.3 0 0 0 1.3 1.3h4.4a1.3 1.3 0 0 0 1.3-1.3V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.5 10.2v4.5M13.5 10.2v4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    const summary = getCartSummary();
    const completeButton = document.querySelector('.pos-complete-btn');
    if (completeButton) {
        const cartReady = cart.length > 0 && !cartHasUnavailableItems;
        completeButton.disabled = !cartReady;
        completeButton.classList.toggle('is-ready', cartReady);
    }

    const subtotalEl = document.getElementById('cart-subtotal');
    const discountEl = document.getElementById('cart-discount');
    const discountRow = document.getElementById('cart-discount-row');
    const taxEl = document.getElementById('cart-tax');
    const totalEl = document.getElementById('cart-total');
    const paidEl = document.getElementById('cart-paid');
    const changeEl = document.getElementById('cart-change');

    if (subtotalEl) subtotalEl.textContent = formatCurrency(summary.subtotal);
    if (discountEl) discountEl.textContent = formatCurrency(summary.discount);
    const discountLabelEl = document.getElementById('cart-discount-label');
    if (discountLabelEl) discountLabelEl.textContent = summary.discountLabel || 'Discount';
    if (discountRow) discountRow.hidden = !(summary.discount > 0);
    if (taxEl) taxEl.textContent = formatCurrency(summary.tax);
    if (totalEl) totalEl.textContent = formatCurrency(summary.total);
    if (paidEl) paidEl.textContent = formatCurrency(summary.paid);
    if (changeEl) changeEl.textContent = formatCurrency(summary.change);

    if (typeof updatePosRecommendations === 'function') {
        updatePosRecommendations();
    }
}

function initMenuSearch() {
    const input = document.getElementById('menuSearchInput');
    const results = document.getElementById('menuSearchResults');
    const searchBtn = document.getElementById('menuSearchButton');
    if (!input || !results) {
        return;
    }

    const lookupByCode = (window.POS_CONFIG && window.POS_CONFIG.itemsByCode) || {};
    const items = (window.POS_CONFIG && window.POS_CONFIG.searchItems) || [];
    let lastRendered = [];
    let originalCardOrder = null;

    function getActiveGrid() {
        const searching = String(input.value || '').trim() !== '';
        if (searching) {
            return document.querySelector('#cat-all .pos-grid') || document.querySelector('.category-panel.active .pos-grid');
        }
        return document.querySelector('.category-panel.active .pos-grid') || document.querySelector('#cat-all .pos-grid');
    }

    function captureOriginalOrder(grid) {
        if (!grid || originalCardOrder) return;
        originalCardOrder = Array.from(grid.querySelectorAll('.food-card'));
    }

    function parseCardItem(card) {
        try {
            return JSON.parse(card.getAttribute('data-item') || '{}');
        } catch (e) {
            return {};
        }
    }

    function reorderPosGrid(matches) {
        const grid = getActiveGrid();
        if (!grid) return;
        captureOriginalOrder(grid);
        const cards = Array.from(grid.querySelectorAll('.food-card'));
        if (!matches || !matches.length) {
            cards.forEach((card) => {
                card.style.display = '';
                card.classList.remove('pos-search-hit');
            });
            if (originalCardOrder) {
                originalCardOrder.forEach((card) => grid.appendChild(card));
            }
            return;
        }

        const matchIds = new Set(matches.map((m) => Number(m.id)));
        const hitCards = [];
        const otherCards = [];
        cards.forEach((card) => {
            const data = parseCardItem(card);
            const id = Number(data.id || 0);
            if (matchIds.has(id)) {
                card.style.display = '';
                card.classList.add('pos-search-hit');
                hitCards.push(card);
            } else {
                card.style.display = 'none';
                card.classList.remove('pos-search-hit');
                otherCards.push(card);
            }
        });

        // Stable order by match ranking
        hitCards.sort((a, b) => {
            const aId = Number(parseCardItem(a).id || 0);
            const bId = Number(parseCardItem(b).id || 0);
            const aIdx = matches.findIndex((m) => Number(m.id) === aId);
            const bIdx = matches.findIndex((m) => Number(m.id) === bId);
            return aIdx - bIdx;
        });
        [...hitCards, ...otherCards].forEach((card) => grid.appendChild(card));
    }

    function hideResults() {
        results.style.display = 'none';
        results.innerHTML = '';
        lastRendered = [];
    }

    function render(term) {
        const q = String(term || '').trim();
        if (!q) {
            hideResults();
            reorderPosGrid([]);
            return;
        }

        const qUpper = q.toUpperCase();
        const qLower = q.toLowerCase();

        const matches = items.filter((it) => {
            const name = String(it.name || '').toLowerCase();
            const code = String(it.product_code || '').toUpperCase();
            return name.includes(qLower) || (code && code.includes(qUpper));
        }).slice(0, 20);

        lastRendered = matches;
        const allTab = document.querySelector('#categoryTabs button');
        if (allTab && typeof selectCategory === 'function' && !allTab.classList.contains('active')) {
            selectCategory('cat-all', allTab);
        }
        reorderPosGrid(matches);

        if (!matches.length) {
            results.innerHTML = '<div class="list-group-item text-muted">No matching menu items.</div>';
            results.style.display = 'block';
            return;
        }

        results.innerHTML = matches.map((it, idx) => {
            const code = it.product_code ? ` <small class="text-muted">${escapeHtml(it.product_code)}</small>` : '';
            const price = typeof it.price === 'number' ? it.price : Number(it.price || 0);
            const availabilityClass = it.available === false ? ' pos-search-result-unavailable' : '';
            const availabilityText = it.available === false
                ? `<small class="pos-search-result-meta text-danger">${escapeHtml(it.availability_detail || 'Currently unavailable')}</small>`
                : `<small class="pos-search-result-meta text-muted">Ready to order</small>`;
            const priceText = it.available === false ? 'Unavailable' : `₱${formatCurrency(price)}`;
            const categoryText = it.category_name ? `<small class="pos-search-result-meta text-muted">${escapeHtml(it.category_name)}</small>` : '';

            return `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center${availabilityClass}" data-idx="${idx}" ${it.available === false ? 'aria-disabled="true"' : ''}>
                <span class="pos-search-result-copy"><strong>${escapeHtml(it.name || '')}</strong>${code}${categoryText}${availabilityText}</span>
                <span class="text-muted">${priceText}</span>
            </button>`;
        }).join('');
        results.style.display = 'block';
    }

    results.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-idx]');
        if (!btn) return;
        const idx = Number(btn.getAttribute('data-idx'));
        const item = lastRendered[idx];
        if (!item) return;
        if (typeof addToCartSelect === 'function') {
            addToCartSelect(item);
            input.value = '';
            hideResults();
            reorderPosGrid([]);
        }
    });

    input.addEventListener('input', () => render(input.value));
    if (searchBtn) {
        searchBtn.addEventListener('click', () => render(input.value));
    }

    input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const value = input.value.trim();
        if (!value) return;

        const code = value.toUpperCase();
        const exact = lookupByCode[code];
        if (exact && typeof addToCartSelect === 'function') {
            addToCartSelect(exact);
            input.value = '';
            hideResults();
            reorderPosGrid([]);
            return;
        }

        if (lastRendered[0] && typeof addToCartSelect === 'function') {
            addToCartSelect(lastRendered[0]);
            input.value = '';
            hideResults();
            reorderPosGrid([]);
        } else {
            render(value);
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(hideResults, 200);
    });
}

function buildCheckoutPayload() {
    const isPwdSeniorEl = document.getElementById('isPwdSenior');
    const pwdSeniorIdEl = document.getElementById('pwdSeniorId');
    const isStoreDiscountEl = document.getElementById('isStoreDiscount');
    return {
        cart: cart.map((item) => ({
            id: item.id,
            quantity: item.quantity,
            variant: item.variant,
            temperature: item.temperature,
            sizeLabel: item.sizeLabel,
            productCode: item.productCode,
            categoryName: item.categoryName,
            customizations: item.customizations || [],
        })),
        payments: getPaymentEntries(),
        promoCode: document.getElementById('promoCode') ? document.getElementById('promoCode').value.trim() : '',
        is_pwd_senior: isPwdSeniorEl ? isPwdSeniorEl.checked : false,
        pwd_senior_id: pwdSeniorIdEl ? pwdSeniorIdEl.value.trim() : '',
        is_store_discount: isStoreDiscountEl ? isStoreDiscountEl.checked : false,
        notes: document.getElementById('orderNotes') ? document.getElementById('orderNotes').value.trim() : '',
        customer: {
            name: document.getElementById('customerName') ? document.getElementById('customerName').value.trim() : '',
            phone: document.getElementById('customerPhone') ? document.getElementById('customerPhone').value.trim() : '',
            email: document.getElementById('customerEmail') ? document.getElementById('customerEmail').value.trim() : '',
        }
    };
}

function completeCheckout() {
    if (cart.length === 0) {
        showPosMessage('Cart is empty. Add items before saving the order.', 'warning');
        return;
    }

    const payload = buildCheckoutPayload();
    const summary = getCartSummary();
    if (payload.is_pwd_senior && !payload.pwd_senior_id) {
        showPosMessage('Please enter the PWD / Senior Citizen ID number before completing checkout.', 'warning');
        const pwdInput = document.getElementById('pwdSeniorId');
        if (pwdInput) {
            pwdInput.focus();
        }
        return;
    }

    if (!payload.payments.length) {
        showPosMessage('Enter the cash amount received before saving the order.', 'warning');
        return;
    }

    if (summary.paid + 0.001 < summary.total) {
        showPosMessage(
            'Insufficient payment. Total is ₱' + formatCurrency(summary.total)
            + ' but payment is ₱' + formatCurrency(summary.paid)
            + '. Discount is ₱' + formatCurrency(summary.discount) + '.',
            'warning'
        );
        return;
    }

    clearPosMessage();

    fetch('process_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async (response) => {
        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('The server returned an unreadable response.');
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'The transaction could not be completed.');
        }

        cart = [];
        updateCartDisplay();
        renderReceipt(data.receipt);
        showPosMessage(data.message || 'Order saved as pending.', 'success');
        clearCheckoutForm(false);
    })
    .catch((error) => {
        showPosMessage(error.message || 'A network failure prevented checkout. Please try again.', 'danger');
    });
}

function renderReceipt(receipt) {
    const container = document.getElementById('receiptContent');
    if (!container || !receipt) {
        return;
    }

    const itemsHtml = (receipt.items || []).map((item) => {
        const categoryName = item.category_name || item.categoryName || '';
        const itemLabel = categoryName ? `${categoryName} - ${item.name}` : item.name;
        return `
            <tr>
                <td>${escapeHtml(itemLabel)}${getItemOptionText(item) ? ` (${escapeHtml(getItemOptionText(item))})` : ''}</td>
                <td class="text-center">${escapeHtml(item.quantity)}</td>
                <td class="text-right">₱${formatCurrency(item.unit_price)}</td>
                <td class="text-right">₱${formatCurrency(item.line_total)}</td>
            </tr>
        `;
    }).join('');

    const paymentsHtml = (receipt.payments || []).map((payment) => `
        <li>${escapeHtml(payment.method)}: ₱${formatCurrency(payment.amount)}${payment.reference ? ` (${escapeHtml(payment.reference)})` : ''}</li>
    `).join('');

    const subtotalVal = Number(receipt.subtotal || 0);
    const taxVal = Number(receipt.tax_amount || 0);
    const discountVal = Number(receipt.discount_amount || 0);
    const totalVal = Number(receipt.total_amount || 0);

    let discountLabel = 'Discount';
    if (receipt.discount_type === 'pwd_senior') {
        discountLabel = `PWD/Senior (20% Off${receipt.pwd_senior_id ? `, ID: ${escapeHtml(receipt.pwd_senior_id)}` : ''})`;
    } else if (receipt.discount_type === 'store') {
        discountLabel = 'Store Discount (10% Off)';
    } else if (receipt.discount_type === 'promo') {
        discountLabel = 'Promo Code Discount';
    }

    container.innerHTML = `
        <div id="printableReceipt">
            <div class="text-center mb-3">
                <h4>Kin Cafe</h4>
                <div>Receipt No. ${escapeHtml(receipt.receipt_number)}</div>
                <div>${escapeHtml(receipt.created_at)}</div>
            </div>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>${itemsHtml}</tbody>
            </table>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Customer:</strong> ${escapeHtml(receipt.customer && receipt.customer.name ? receipt.customer.name : 'Walk-in')}</p>
                    ${receipt.notes ? `<p><strong>Order Notes:</strong> ${escapeHtml(receipt.notes)}</p>` : ''}
                    <p><strong>Loyalty Points Earned:</strong> ${escapeHtml(receipt.loyalty_points_earned || 0)}</p>
                    <p><strong>Updated Balance:</strong> ${escapeHtml(receipt.customer && receipt.customer.loyalty_points ? receipt.customer.loyalty_points : 0)}</p>
                    ${receipt.promotion ? `<p><strong>Promotion:</strong> ${escapeHtml(receipt.promotion.code)} - ${escapeHtml(receipt.promotion.name)}</p>` : ''}
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between"><span>Subtotal</span><strong>₱${formatCurrency(subtotalVal)}</strong></div>
                    <div class="d-flex justify-content-between"><span>VAT (12% ${receipt.discount_type === 'pwd_senior' ? 'EXEMPT' : ''})</span><strong>₱${formatCurrency(taxVal)}</strong></div>
                    ${discountVal > 0 ? `<div class="d-flex justify-content-between text-danger"><span>${discountLabel}</span><strong>- ₱${formatCurrency(discountVal)}</strong></div>` : ''}
                    <div class="d-flex justify-content-between font-weight-bold h5 mt-2"><span>Total</span><strong>₱${formatCurrency(totalVal)}</strong></div>
                    <div class="d-flex justify-content-between"><span>Paid</span><strong>₱${formatCurrency(receipt.paid_amount)}</strong></div>
                    <div class="d-flex justify-content-between"><span>Change</span><strong>₱${formatCurrency(receipt.change_amount)}</strong></div>
                </div>
            </div>
            <hr>
            <p><strong>Payments</strong></p>
            <ul>${paymentsHtml}</ul>
            <p class="text-center mt-4">${escapeHtml(receipt.receipt_footer || 'Thank you for visiting Kin Cafe.')}</p>
        </div>
    `;

    if (window.jQuery) {
        window.jQuery('#receiptModal').modal('show');
    }
}

function printReceipt() {
    const printable = document.getElementById('printableReceipt');
    if (!printable) {
        return;
    }

    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
        showPosMessage('The receipt window could not be opened. Check your popup blocker.', 'warning');
        return;
    }

    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt</title>
            <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        </head>
        <body class="p-4">${printable.innerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function clearCheckoutForm(resetMessage = true) {
    cart = [];
    const ids = ['customerName', 'customerPhone', 'customerEmail', 'promoCode', 'orderNotes', 'paymentAmount1', 'menuSearchInput', 'productCodeInput', 'pwdSeniorId'];
    ids.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.value = '';
        }
    });

    const isPwdSeniorEl = document.getElementById('isPwdSenior');
    if (isPwdSeniorEl) {
        isPwdSeniorEl.checked = false;
    }
    const isStoreDiscountEl = document.getElementById('isStoreDiscount');
    if (isStoreDiscountEl) {
        isStoreDiscountEl.checked = false;
    }
    if (typeof togglePwdSeniorInput === 'function') {
        togglePwdSeniorInput();
    }

    const paymentMethod1 = document.getElementById('paymentMethod1');
    if (paymentMethod1) paymentMethod1.value = 'cash';

    if (resetMessage) {
        clearPosMessage();
    }

    updateCartDisplay();
}

if (document.getElementById('menuSearchInput')) {
    initMenuSearch();
}

document.addEventListener('DOMContentLoaded', () => {
    const amount = document.getElementById('paymentAmount1');
    if (amount) {
        const handler = () => {
            if (typeof updateCartDisplay === 'function') {
                updateCartDisplay();
            }
        };
        ['input', 'change', 'keyup', 'blur'].forEach((evt) => amount.addEventListener(evt, handler));
        handler();
    }

    const paymentMethod = document.getElementById('paymentMethod1');
    document.querySelectorAll('.pos-payment-pill[data-method]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.pos-payment-pill[data-method]').forEach((pill) => pill.classList.remove('active'));
            button.classList.add('active');
            if (paymentMethod) {
                paymentMethod.value = button.getAttribute('data-method') || 'cash';
            }
        });
    });
});

