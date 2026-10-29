import './bootstrap';

const config = window.storefrontConfig;

if (config) {
    const productCatalog = new Map(config.products.map((product) => [product.id, product]));
    const productRows = document.querySelector('#product-rows');
    const rowTemplate = document.querySelector('#product-row-template');
    const orderForm = document.querySelector('#order-form');
    const amountGivenInput = document.querySelector('#amount-given');
    const submitButton = document.querySelector('#submit-order');
    const errorNotice = document.querySelector('#order-error');
    const billCard = document.querySelector('#bill-card');
    const addProductDialog = document.querySelector('#add-product-dialog');
    const restockDialog = document.querySelector('#restock-dialog');
    const createProductForm = document.querySelector('#create-product-form');
    const restockForm = document.querySelector('#restock-form');
    const restockProductSelect = document.querySelector('#restock-product');
    let orderSubmitted = false;
    let orderTotals = { grandTotal: 0 };

    const formatMoney = (cents) => new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(cents / 100);

    const toCents = (amount) => Math.round(Number(amount) * 100);

    function populateProductSelect(select, selectedValue = '') {
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = productCatalog.size > 0 ? 'Choose a product' : 'No products available';
        select.replaceChildren(placeholder);

        for (const product of productCatalog.values()) {
            const option = document.createElement('option');
            option.value = product.id;
            option.disabled = product.stock < 1;
            option.textContent = `${product.name} · ${product.code} · ${product.stock} in stock`;
            select.append(option);
        }

        select.value = String(selectedValue);
    }

    function refreshProductSelects() {
        productRows.querySelectorAll('.product-select').forEach((select) => {
            populateProductSelect(select, select.value);
        });
        populateProductSelect(restockProductSelect, restockProductSelect.value);
    }

    function showModalError(element, message) {
        element.textContent = message;
        element.classList.add('is-visible');
    }

    function clearModalError(element) {
        element.textContent = '';
        element.classList.remove('is-visible');
    }

    async function readApiResponse(response) {
        const payload = await response.json();

        if (!response.ok) {
            const messages = Object.values(payload.errors || {}).flat();
            throw new Error(messages.join(' ') || payload.message || `Request failed (${response.status}).`);
        }

        return payload;
    }

    function updateSummary() {
        let subtotalCents = 0;
        let taxCents = 0;
        let itemCount = 0;

        productRows.querySelectorAll('tr').forEach((row) => {
            const productId = Number(row.querySelector('.product-select').value);
            const product = productCatalog.get(productId);
            const quantityInput = row.querySelector('.quantity-input');
            const quantity = Math.max(0, Number.parseInt(quantityInput.value, 10) || 0);
            const unitPrice = product ? toCents(product.price) : 0;
            const taxRateBasisPoints = product ? Math.round(Number(product.tax_rate) * 100) : 0;
            const lineSubtotal = unitPrice * quantity;
            const lineTax = Math.round((lineSubtotal * taxRateBasisPoints) / 10000);

            row.querySelector('.line-price').textContent = product
                ? `${formatMoney(unitPrice)} · ${Number(product.tax_rate)}% tax`
                : '—';
            row.querySelector('.line-total').textContent = product
                ? formatMoney(lineSubtotal + lineTax)
                : '—';

            if (product && quantity > 0) {
                subtotalCents += lineSubtotal;
                taxCents += lineTax;
                itemCount += quantity;
            }
        });

        orderTotals = { subtotal: subtotalCents, tax: taxCents, grandTotal: subtotalCents + taxCents };
        document.querySelector('#subtotal').textContent = formatMoney(subtotalCents);
        document.querySelector('#tax-total').textContent = formatMoney(taxCents);
        document.querySelector('#grand-total').textContent = formatMoney(subtotalCents + taxCents);
        document.querySelector('#line-count').textContent = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;
        updateBalance();
    }

    function updateBalance() {
        const amountGiven = amountGivenInput.value === '' ? null : toCents(amountGivenInput.value);
        const balance = document.querySelector('#balance');
        const needsPayment = amountGiven !== null && amountGiven < orderTotals.grandTotal;

        if (amountGiven === null) {
            balance.textContent = '—';
        } else if (needsPayment) {
            balance.textContent = `${formatMoney(orderTotals.grandTotal - amountGiven)} due`;
        } else {
            balance.textContent = formatMoney(amountGiven - orderTotals.grandTotal);
        }

        balance.style.color = needsPayment ? '#bd3f45' : '';
    }

    function addProductRow() {
        const row = rowTemplate.content.firstElementChild.cloneNode(true);
        productRows.append(row);
        populateProductSelect(row.querySelector('.product-select'));
        updateSummary();
    }

    function showError(message) {
        errorNotice.textContent = message;
        errorNotice.classList.add('is-visible');
        errorNotice.focus?.();
    }

    function renderLowStock(products) {
        const list = document.querySelector('#stock-list');
        list.replaceChildren();

        if (products.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'stock-empty';
            empty.textContent = 'All products are above the low-stock threshold.';
            list.append(empty);
            return;
        }

        for (const product of products) {
            const item = document.createElement('li');
            item.className = 'stock-item';
            const name = document.createElement('span');
            name.textContent = product.name;
            const quantity = document.createElement('span');
            quantity.className = 'stock-badge';
            quantity.textContent = `${product.stock} left`;
            item.append(name, quantity);
            list.append(item);
        }
    }

    async function refreshLowStock() {
        const list = document.querySelector('#stock-list');
        const url = `/api/products/low-stock?threshold=${encodeURIComponent(config.lowStockThreshold)}`;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                throw new Error(`Inventory request failed (${response.status}).`);
            }

            const payload = await response.json();
            renderLowStock(payload.data);
        } catch (error) {
            list.replaceChildren();
            const item = document.createElement('li');
            item.className = 'stock-empty';
            item.textContent = 'Unable to load current stock. Refresh the page to try again.';
            list.append(item);
            console.error(error);
        }
    }

    function renderBill(order) {
        const billItems = document.querySelector('#bill-items');
        const billTotals = document.querySelector('#bill-totals');
        const createdAt = new Date(order.created_at);

        document.querySelector('#bill-meta').textContent =
            `Order #${order.id} · ${createdAt.toLocaleString()} · ${order.customer.name} · ${order.customer.email}`;
        billItems.replaceChildren();
        billTotals.replaceChildren();

        for (const item of order.items) {
            const line = document.createElement('div');
            line.className = 'bill-item';
            const description = document.createElement('span');
            description.textContent = `${item.product.name} × ${item.quantity} at ${formatMoney(toCents(item.unit_price))}`;
            const total = document.createElement('strong');
            total.textContent = formatMoney(toCents(item.total));
            line.append(description, total);
            billItems.append(line);
        }

        for (const [label, value, isTotal] of [
            ['Subtotal', order.subtotal, false],
            ['Tax', order.tax_amount, false],
            ['Grand total', order.total, true],
        ]) {
            const line = document.createElement('div');
            line.className = isTotal ? 'summary-line total' : 'summary-line';
            const title = document.createElement('span');
            title.textContent = label;
            const amount = document.createElement('strong');
            amount.textContent = formatMoney(toCents(value));
            line.append(title, amount);
            billTotals.append(line);
        }

        billCard.classList.add('is-visible');
        billCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function startNewOrder() {
        orderSubmitted = false;
        orderForm.reset();
        billCard.classList.remove('is-visible');
        errorNotice.classList.remove('is-visible');
        errorNotice.textContent = '';
        amountGivenInput.disabled = false;
        submitButton.disabled = false;
        submitButton.innerHTML = '<span aria-hidden="true">✓</span> Generate bill';
        document.querySelector('#add-product').disabled = false;
        productRows.replaceChildren();

        addProductRow();
        updateSummary();
        refreshLowStock();
        orderForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelector('#customer-email').focus({ preventScroll: true });
    }

    document.querySelector('#add-product').addEventListener('click', addProductRow);
    amountGivenInput.addEventListener('input', updateBalance);

    document.querySelector('#open-add-product').addEventListener('click', () => {
        clearModalError(document.querySelector('#create-product-error'));
        addProductDialog.showModal();
        document.querySelector('#new-product-code').focus();
    });

    document.querySelector('#open-restock').addEventListener('click', () => {
        clearModalError(document.querySelector('#restock-error'));
        refreshProductSelects();
        restockDialog.showModal();
        restockProductSelect.focus();
    });

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog').close());
    });

    addProductDialog.addEventListener('click', (event) => {
        if (event.target === addProductDialog) {
            addProductDialog.close();
        }
    });

    restockDialog.addEventListener('click', (event) => {
        if (event.target === restockDialog) {
            restockDialog.close();
        }
    });

    createProductForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorElement = document.querySelector('#create-product-error');
        const saveButton = document.querySelector('#create-product-submit');
        clearModalError(errorElement);

        if (!createProductForm.reportValidity()) {
            return;
        }

        const formData = new FormData(createProductForm);
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';

        try {
            const response = await fetch('/api/products', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    code: formData.get('code').trim(),
                    name: formData.get('name').trim(),
                    price: formData.get('price'),
                    tax_rate: formData.get('tax_rate'),
                    stock: Number(formData.get('stock')),
                }),
            });
            const payload = await readApiResponse(response);
            const product = payload.data;

            productCatalog.set(product.id, product);
            createProductForm.reset();
            addProductDialog.close();
            refreshProductSelects();
            await refreshLowStock();
        } catch (error) {
            showModalError(errorElement, error.message);
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save product';
        }
    });

    restockForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorElement = document.querySelector('#restock-error');
        const restockButton = document.querySelector('#restock-submit');
        clearModalError(errorElement);

        if (!restockForm.reportValidity()) {
            return;
        }

        const productId = Number(restockProductSelect.value);
        const quantity = Number(document.querySelector('#restock-quantity').value);
        restockButton.disabled = true;
        restockButton.textContent = 'Adding stock…';

        try {
            const response = await fetch(`/api/products/${productId}/restock`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ quantity }),
            });
            const payload = await readApiResponse(response);

            productCatalog.set(payload.data.id, payload.data);
            restockForm.reset();
            restockDialog.close();
            refreshProductSelects();
            updateSummary();
            await refreshLowStock();
        } catch (error) {
            showModalError(errorElement, error.message);
        } finally {
            restockButton.disabled = false;
            restockButton.textContent = 'Add stock';
        }
    });

    productRows.addEventListener('input', (event) => {
        if (event.target.matches('.quantity-input')) {
            updateSummary();
        }
    });

    productRows.addEventListener('change', (event) => {
        if (event.target.matches('.product-select')) {
            updateSummary();
        }
    });

    productRows.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-row');

        if (!removeButton) {
            return;
        }

        if (productRows.children.length === 1) {
            const row = removeButton.closest('tr');
            row.querySelector('.product-select').value = '';
            row.querySelector('.quantity-input').value = '1';
        } else {
            removeButton.closest('tr').remove();
        }

        updateSummary();
    });

    document.querySelector('#print-bill').addEventListener('click', () => window.print());
    document.querySelector('#new-order').addEventListener('click', startNewOrder);

    orderForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorNotice.classList.remove('is-visible');
        errorNotice.textContent = '';

        if (orderSubmitted) {
            return;
        }

        const customerName = document.querySelector('#customer-name');
        const customerEmail = document.querySelector('#customer-email');
        const items = [...productRows.querySelectorAll('tr')].map((row) => ({
            product_id: Number(row.querySelector('.product-select').value),
            quantity: Number(row.querySelector('.quantity-input').value),
        }));

        if (!orderForm.reportValidity()) {
            return;
        }

        if (items.some((item) => !item.product_id || !Number.isInteger(item.quantity) || item.quantity < 1)) {
            showError('Choose a product and a valid quantity for every row.');
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Placing order…';

        try {
            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    customer: {
                        name: customerName.value.trim(),
                        email: customerEmail.value.trim(),
                    },
                    items,
                }),
            });
            const payload = await readApiResponse(response);

            orderSubmitted = true;
            submitButton.textContent = 'Order placed';
            productRows.querySelectorAll('select, input, button').forEach((control) => {
                control.disabled = true;
            });
            document.querySelector('#add-product').disabled = true;
            amountGivenInput.disabled = true;

            for (const item of payload.data.items) {
                if (item.product) {
                    productCatalog.set(item.product_id, item.product);
                }
            }
            refreshProductSelects();

            document.querySelector('#stock-description').textContent =
                `Products with fewer than ${config.lowStockThreshold} units remaining`;
            await refreshLowStock();
            renderBill(payload.data);
        } catch (error) {
            showError(error.message);
            submitButton.disabled = false;
            submitButton.innerHTML = '<span aria-hidden="true">✓</span> Generate bill';
        }
    });

    addProductRow();
    refreshLowStock();
}
