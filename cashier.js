'use strict';
const pos = document.querySelector('#pos-form');
if (pos) {
    const quantities = [...pos.querySelectorAll('[data-unit-cents]')];
    const money = (n) => (n / 100).toFixed(2);
    const update = () => {
        let subtotal = 0;
        const summary = document.querySelector('#order-summary');
        summary.replaceChildren();
        for (const input of quantities) {
            const qty = Math.max(0, Math.min(99, Math.floor(Number(input.value) || 0)));
            if (input.disabled || qty === 0) continue;
            const line = qty * Number(input.dataset.unitCents);
            subtotal += line;
            const p = document.createElement('p');
            p.textContent = `${qty} × ${input.dataset.name} — ${money(line)}`;
            summary.append(p);
        }
        if (!summary.children.length) summary.textContent = 'Select quantities from the menu.';
        const tax = Math.round(subtotal * Number(pos.dataset.taxBps) / 10000);
        const total = subtotal + tax;
        document.querySelector('#pos-subtotal').textContent = money(subtotal);
        document.querySelector('#pos-tax').textContent = money(tax);
        document.querySelector('#pos-total').textContent = money(total);
        const cash = document.querySelector('#payment-method').value === 'cash';
        const tendered = document.querySelector('#tendered');
        tendered.required = cash; tendered.disabled = !cash;
        document.querySelector('#payment-reference').required = !cash;
        document.querySelector('#pos-change').textContent = cash ? money(Math.max(0, Math.round(Number(tendered.value || 0) * 100) - total)) : '0.00';
    };
    pos.addEventListener('input', update);
    document.querySelector('#menu-search').addEventListener('input', (event) => {
        const q = event.target.value.toLowerCase().trim();
        document.querySelectorAll('[data-menu-text]').forEach((item) => { item.hidden = !item.dataset.menuText.includes(q); });
    });
    update();
}
document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
