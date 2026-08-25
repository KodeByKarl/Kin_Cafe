(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.KinCashChange = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    function roundToCents(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) return 0;
        return Math.round((n + Number.EPSILON) * 100) / 100;
    }

    function parseMoneyInput(value) {
        if (value === null || value === undefined) return 0;
        if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
        const s = String(value).trim();
        if (!s) return 0;
        const cleaned = s.replace(/[^\d.,-]/g, '');
        const hasComma = cleaned.indexOf(',') !== -1;
        const hasDot = cleaned.indexOf('.') !== -1;
        let normalized = cleaned;
        if (hasComma && hasDot) {
            normalized = normalized.replace(/,/g, '');
        } else if (hasComma && !hasDot) {
            normalized = normalized.replace(/,/g, '.');
        }
        normalized = normalized.replace(/[^\d.-]/g, '');
        const n = Number(normalized);
        return Number.isFinite(n) ? n : 0;
    }

    function computeChange(total, paid) {
        const t = roundToCents(Math.max(0, parseMoneyInput(total)));
        const p = roundToCents(parseMoneyInput(paid));
        const safePaid = p < 0 ? 0 : p;
        return roundToCents(Math.max(safePaid - t, 0));
    }

    function computeDenominationBreakdown(change, denominations) {
        const denoms = Array.isArray(denominations) && denominations.length
            ? denominations.slice()
            : [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.25, 0.1, 0.05, 0.01];
        let remaining = roundToCents(Math.max(0, parseMoneyInput(change)));
        const result = [];
        denoms.forEach((d) => {
            const denom = roundToCents(d);
            if (!denom || denom <= 0) return;
            const count = Math.floor((remaining + 1e-9) / denom);
            if (count > 0) {
                result.push({ denom, count });
                remaining = roundToCents(remaining - (count * denom));
            }
        });
        return result;
    }

    return {
        roundToCents,
        parseMoneyInput,
        computeChange,
        computeDenominationBreakdown,
    };
}));
