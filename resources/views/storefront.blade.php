<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>New order · {{ config('app.name', 'Store Billing') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">M</span>
                <span>Store Billing</span>
            </div>
            <div class="topbar-actions">
                <span class="topbar-note">Order desk <span aria-hidden="true">·</span> Inventory connected</span>
                <button class="button button-nav" id="open-add-product" type="button">＋ Add product</button>
                <button class="button button-nav button-nav-secondary" id="open-restock" type="button">↗ Restock</button>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Point of sale</p>
                <h1>New order</h1>
                <p class="page-subtitle">Create a customer bill and keep your inventory in sync.</p>
            </div>
            <span class="date-chip">{{ now()->format('D, M j, Y') }}</span>
        </div>

        <form id="order-form" novalidate>
            <section class="card customer-card" aria-labelledby="customer-heading">
                <div class="card-heading">
                    <div class="section-title">
                        <span class="section-icon" aria-hidden="true">♙</span>
                        <div>
                            <h2 id="customer-heading">Customer</h2>
                            <span class="muted">Add contact details for the receipt</span>
                        </div>
                    </div>
                    <span class="section-count">01</span>
                </div>
                <div class="customer-fields">
                    <div class="field">
                        <label for="customer-email">Email address</label>
                        <input id="customer-email" name="customer.email" type="email" placeholder="e.g. alex@example.com" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="customer-name">Full name</label>
                        <input id="customer-name" name="customer.name" type="text" placeholder="Customer name" autocomplete="name" maxlength="255" required>
                    </div>
                </div>
            </section>

            <div class="workspace-grid">
                <div class="main-column">
                    <section class="card products-card" aria-labelledby="products-heading">
                        <div class="card-heading">
                            <div class="section-title">
                                <span class="section-icon" aria-hidden="true">▤</span>
                                <div>
                                    <h2 id="products-heading">Products</h2>
                                    <span class="muted">Choose items and quantities for this order</span>
                                </div>
                            </div>
                            <span class="section-count" id="line-count">0 items</span>
                        </div>

                        <div class="product-table-wrap">
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Product</th>
                                        <th scope="col">Quantity</th>
                                        <th scope="col">Unit price</th>
                                        <th scope="col">Line total</th>
                                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody id="product-rows"></tbody>
                            </table>
                        </div>

                        <div class="product-actions">
                            <span class="helper-text">Line totals include product tax.</span>
                            <button class="button button-secondary" id="add-product" type="button"><span aria-hidden="true">＋</span> Add product</button>
                        </div>
                    </section>

                    <section class="card payment-card" aria-labelledby="payment-heading">
                        <div class="card-heading">
                            <div class="section-title">
                                <span class="section-icon" aria-hidden="true">₹</span>
                                <div>
                                    <h2 id="payment-heading">Payment</h2>
                                    <span class="muted">Review the bill before completing the order</span>
                                </div>
                            </div>
                        </div>
                        <div class="summary-lines" aria-live="polite">
                            <div class="summary-line"><span>Subtotal</span><strong id="subtotal">₹0.00</strong></div>
                            <div class="summary-line"><span>Tax</span><strong id="tax-total">₹0.00</strong></div>
                            <div class="summary-line total"><span>Grand total</span><strong id="grand-total">₹0.00</strong></div>
                        </div>
                        <div class="amount-field">
                            <label for="amount-given">Amount given by customer <span class="muted">(optional)</span></label>
                            <input id="amount-given" type="number" min="0" step="0.01" inputmode="decimal" placeholder="Enter cash received">
                        </div>
                        <div class="change-line">
                            <span>Balance to return</span>
                            <strong id="balance">—</strong>
                        </div>
                    </section>
                </div>

                <aside class="sidebar" aria-label="Order status and actions">
                    <section class="card stock-card" aria-labelledby="stock-heading">
                        <div class="stock-heading">
                            <span class="section-icon" aria-hidden="true">!</span>
                            <h2 id="stock-heading">Low stock alert</h2>
                        </div>
                        <div class="stock-content">
                            <p class="stock-intro" id="stock-description">Products with fewer than {{ $lowStockThreshold }} units remaining</p>
                            <ul class="stock-list" id="stock-list" aria-live="polite">
                                <li class="stock-empty">Checking inventory…</li>
                            </ul>
                        </div>
                    </section>

                    <div class="payment-action">
                        <button class="button button-primary" id="submit-order" type="submit">
                            <span aria-hidden="true">✓</span> Generate bill
                        </button>
                        <p>Stock is reserved when the order is placed. A confirmation email is queued for the customer.</p>
                    </div>
                </aside>
            </div>

            <div class="notice" id="order-error" role="alert" tabindex="-1"></div>
        </form>

        <section class="bill-card" id="bill-card" aria-labelledby="bill-heading" aria-live="polite">
            <div class="bill-header">
                <div>
                    <div class="bill-status"><span aria-hidden="true">✓</span> Order placed successfully</div>
                    <h2 class="bill-title" id="bill-heading">Customer bill</h2>
                    <p class="bill-meta" id="bill-meta"></p>
                </div>
            </div>
            <div class="bill-items" id="bill-items"></div>
            <div class="bill-totals" id="bill-totals"></div>
            <div class="bill-actions">
                <button class="button button-secondary" id="print-bill" type="button">Print bill</button>
                <button class="button button-primary" id="new-order" type="button">New order</button>
            </div>
        </section>
    </main>

    <dialog class="modal" id="add-product-dialog" aria-labelledby="add-product-heading">
        <form class="modal-form" id="create-product-form">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Inventory</p>
                    <h2 id="add-product-heading">Add product</h2>
                    <p class="modal-description">Create a new item in your product catalog.</p>
                </div>
                <button class="modal-close" type="button" data-close-dialog aria-label="Close dialog">×</button>
            </div>
            <div class="modal-fields">
                <div class="field">
                    <label for="new-product-code">Product code</label>
                    <input id="new-product-code" name="code" type="text" maxlength="255" autocomplete="off" placeholder="e.g. BISCUIT-100" required>
                </div>
                <div class="field">
                    <label for="new-product-name">Product name</label>
                    <input id="new-product-name" name="name" type="text" maxlength="255" placeholder="e.g. Counter Biscuit" required>
                </div>
                <div class="modal-fields-row">
                    <div class="field">
                        <label for="new-product-price">Price (₹)</label>
                        <input id="new-product-price" name="price" type="number" min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0.00" required>
                    </div>
                    <div class="field">
                        <label for="new-product-tax-rate">Tax rate (%)</label>
                        <input id="new-product-tax-rate" name="tax_rate" type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0.00" required>
                    </div>
                </div>
                <div class="field">
                    <label for="new-product-stock">Initial stock</label>
                    <input id="new-product-stock" name="stock" type="number" min="0" step="1" inputmode="numeric" placeholder="0" required>
                </div>
            </div>
            <p class="modal-error" id="create-product-error" role="alert"></p>
            <div class="modal-actions">
                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                <button class="button button-primary" id="create-product-submit" type="submit">Save product</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="restock-dialog" aria-labelledby="restock-heading">
        <form class="modal-form" id="restock-form">
            <div class="modal-header">
                <div>
                    <p class="eyebrow">Inventory</p>
                    <h2 id="restock-heading">Restock product</h2>
                    <p class="modal-description">Add units to a product's current stock.</p>
                </div>
                <button class="modal-close" type="button" data-close-dialog aria-label="Close dialog">×</button>
            </div>
            <div class="modal-fields">
                <div class="field">
                    <label for="restock-product">Product</label>
                    <select class="product-select" id="restock-product" name="product_id" required></select>
                </div>
                <div class="field">
                    <label for="restock-quantity">Quantity to add</label>
                    <input id="restock-quantity" name="quantity" type="number" min="1" step="1" inputmode="numeric" placeholder="Enter quantity" required>
                </div>
            </div>
            <p class="modal-error" id="restock-error" role="alert"></p>
            <div class="modal-actions">
                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                <button class="button button-primary" id="restock-submit" type="submit">Add stock</button>
            </div>
        </form>
    </dialog>

    <template id="product-row-template">
        <tr>
            <td><select class="product-select" aria-label="Product" required></select></td>
            <td><input class="quantity-input" type="number" min="1" step="1" value="1" aria-label="Quantity" required></td>
            <td class="line-price muted">—</td>
            <td class="line-total">—</td>
            <td><button class="remove-row" type="button" aria-label="Remove product">×</button></td>
        </tr>
    </template>

    <script>
        window.storefrontConfig = {{ Illuminate\Support\Js::from([
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $product->price,
                'tax_rate' => $product->tax_rate,
                'stock' => $product->stock,
            ])->values(),
            'lowStockThreshold' => $lowStockThreshold,
        ]) }};
    </script>
</body>
</html>
