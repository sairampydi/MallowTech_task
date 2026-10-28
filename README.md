# Store Order & Inventory API

A Laravel API for recording customer orders, calculating tax, and maintaining product stock without overselling inventory. This implements the supplied Mallow Technologies Laravel Developer Mini Task.

The root page (`/`) also provides a responsive billing screen with customer details, product selection, live totals and change, a low-stock panel, and a printable bill after order creation.

## Requirements

- PHP 8.2+
- Composer
- SQLite (the default) or another Laravel-supported database

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
```

On macOS/Linux, use `cp .env.example .env` instead of `copy`.

The starter project already includes a SQLite database configuration. To use it explicitly, set `DB_CONNECTION=sqlite` in `.env` and ensure `database/database.sqlite` exists. Start the API with:

```bash
php artisan serve
```

For asynchronous confirmation logging, run a queue worker in a second terminal:

```bash
php artisan queue:work
```

The default queue connection is the database. The queued job writes a simulated confirmation to `storage/logs/laravel.log`; it does not send real email. To exercise the API without a worker, the job remains in the queue until a worker is started.

## API

All responses are JSON. Validation errors use Laravel's standard `422` response format.

### Create an order

`POST /api/orders`

```json
{
  "customer": {
    "name": "Alex Morgan",
    "email": "alex@example.com"
  },
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

The request creates or updates the customer with the supplied email, combines repeated product IDs into one order line, calculates line and order totals, decrements stock, and returns the order with its customer, item, and product details (`201 Created`). Product IDs must exist and quantities must be positive integers. If any product has insufficient stock, the request returns `422` and the transaction leaves the entire order and all stock unchanged.

### Customer order history

`GET /api/customers/orders?email=alex@example.com`

Returns the customer's orders, newest first, with pagination (15 orders per page) and their items. Returns `404` when that email has no customer.

### Low-stock products

`GET /api/products/low-stock`

Returns products whose stock is below `LOW_STOCK_THRESHOLD` (default `5`). Override the threshold for a request with `?threshold=10`; the threshold is a non-negative integer, and the comparison is strictly less than the threshold.

### Create a product

`POST /api/products`

```json
{
  "name": "Counter Biscuit",
  "code": "BISCUIT-100",
  "price": "12.50",
  "tax_rate": "5.00",
  "stock": 20
}
```

Creates a product and returns it with `201 Created`. Product codes must be unique; price and tax rate allow up to two decimal places, tax rate must be between 0 and 100 percent, and initial stock must be a non-negative integer.

### Increase product stock

`POST /api/products/{product_id}/restock`

```json
{
  "quantity": 10
}
```

Adds the positive integer quantity to an existing product's current stock and returns the updated product. This uses a database-side atomic increment, so simultaneous restock requests are both applied. An unknown product returns `404`; an invalid quantity returns `422`.

## Postman collection

Import [`postman/MallowTech-Store-Orders.postman_collection.json`](./postman/MallowTech-Store-Orders.postman_collection.json) and optionally [`postman/MallowTech-Local.postman_environment.json`](./postman/MallowTech-Local.postman_environment.json) into Postman. Start the Laravel app with `php artisan serve`, select the **MallowTech Local** environment, then run **Low Stock Products** to inspect inventory before creating an order.

The collection's default `product_id` is `1`, matching the first sample product in a fresh seeded database. Update that variable if your product has a different ID. Successful order requests permanently deduct stock. The collection also includes insufficient-stock, invalid-threshold, and unknown-customer examples, plus saved variables for the created customer's email and order ID.

## Billing screen

Visit `/` to create an order through the browser. The screen loads the product catalog, calculates line and order totals as quantities change, and shows the remaining balance when an amount received is entered. Placing an order uses the same `POST /api/orders` endpoint as API clients. Once it succeeds, the screen displays a printable bill and refreshes the low-stock alert.

The amount received and change are shown for cashier convenience; only the order and its product totals are persisted. The confirmation job logs a simulated email and does not create or send a PDF attachment.

## Data and design decisions

- `products`, `customers`, `orders`, and `order_items` are separate normalized tables. Product codes and customer emails are unique.
- Order items snapshot the product unit price and tax rate at the time of sale, so later catalog changes do not rewrite order history.
- Tax is calculated for each order line from its extended subtotal and rounded half-up to cents. Order totals are the sum of those line totals.
- Submitting an existing customer's email updates the customer's name with the latest supplied name.
- Repeated product IDs in a request are combined, and one item is stored per order/product pair.
- Order history uses standard Laravel pagination to avoid unbounded responses.
- Inventory is decremented inside the same database transaction that creates the order. Each decrement is a conditional SQL update (`stock >= requested quantity`) and product rows are processed in ascending ID order. Row-locking databases serialize competing updates; the conditional update prevents a decrement below zero. SQLite uses a busy timeout and transaction retries for competing writers.
- A queued `SendOrderConfirmation` job is dispatched after the order transaction commits. The job logs a simulated confirmation rather than sending SMTP mail.

The low-stock threshold can be set in `.env`:

```dotenv
LOW_STOCK_THRESHOLD=5
```

## Tests

Run the feature and unit tests with:

```bash
php artisan test
```

The feature tests cover successful order creation and totals, stock deduction, insufficient-stock rollback, queued confirmation dispatch, customer order history, and low-stock threshold filtering.

## AI prompt log and walkthrough

The assignment requests screenshots of actual AI prompts if AI-assisted tools are used and a narrated 5–10 minute screen recording. Add genuine prompt screenshots to a `prompts/` directory and include the recording when preparing the submission; no fabricated screenshots or recording are included in this repository.
