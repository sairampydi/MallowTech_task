# Store Order & Inventory Mini-System

A Laravel 12 application for a retail counter to maintain a product catalog, record customer orders, calculate tax, and keep inventory in sync. It includes a JSON API and a browser-based billing screen.

## Features

- Create products and add inventory with dedicated API endpoints.
- Create orders for existing or new customers, with one or more products.
- Calculate and save order and order-line subtotals, tax, and totals.
- Decrement inventory without allowing an order to oversell available stock.
- Look up a customer's paginated order history by email.
- See products below a configurable low-stock threshold.
- Queue a simulated order-confirmation email after the order transaction commits.
- Use the responsive billing screen to make orders, check totals and change, view low-stock alerts, and print a bill.

## Requirements

- PHP 8.2 or newer with PDO SQLite enabled (or extensions for your chosen database).
- Composer 2.
- Node.js and npm (to build the browser assets).

The project uses Laravel 12, SQLite by default, Vite for frontend assets, and the database queue driver by default.

## Installation and setup

Run these commands from the project directory.

### Windows PowerShell

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate --seed
npm ci
npm run build
```

If you already have a `.env` file, keep it and do not overwrite it with `.env.example`.

### macOS/Linux

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci
npm run build
```

The supplied `.env.example` selects SQLite and `database/database.sqlite`. Review `.env` before migrating. For another Laravel-supported database, set the corresponding `DB_CONNECTION` and connection settings first.

To load the sample catalog again after an existing seed, run:

```bash
php artisan db:seed
```

The sample seeder uses stable product codes and customer emails, so rerunning it updates those sample records rather than adding duplicates. It resets the seeded products' sample stock to their configured values. **Do not rerun it against a database whose stock must be preserved.**

## Run the application

Start the web application:

```bash
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) for the billing screen. API requests use the same host and port.

The queue driver is `database`. Run a worker in another terminal to process order confirmations:

```bash
php artisan queue:work
```

The job simulates an email by writing an entry to `storage/logs/laravel.log`; no SMTP service or real email is used. Without a queue worker, confirmations remain queued.

The committed production assets are built with `npm run build`. During frontend development, run `npm run dev` in a separate terminal while serving Laravel.

## Configuration

| Environment variable | Default | Purpose |
| --- | --- | --- |
| `DB_CONNECTION` | `sqlite` | Database connection. |
| `DB_BUSY_TIMEOUT` | `5000` | SQLite wait time in milliseconds when another writer holds the database. |
| `QUEUE_CONNECTION` | `database` | Queue backend for confirmation jobs. |
| `LOW_STOCK_THRESHOLD` | `5` | Default product stock level used by the low-stock endpoint and billing screen. |
| `MAIL_MAILER` | `log` | Mail configuration; the confirmation job currently logs a simulation instead. |

The low-stock endpoint also accepts a per-request `threshold` query parameter.

## API

All API routes are under `/api`. Send `Accept: application/json`; send `Content-Type: application/json` with JSON request bodies. Laravel returns validation failures as `422 Unprocessable Content`. The API does not require authentication in this mini-task; add appropriate authentication and authorization before exposing it beyond a trusted development environment.

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

Creates a product and returns `{ "data": { ... } }` with `201 Created`. The product code must be unique. Price must be non-negative with at most two decimal places. Tax rate must be between 0 and 100 percent, with at most two decimal places. Initial stock must be a non-negative integer.

### Increase product stock

`POST /api/products/{product_id}/restock`

```json
{
  "quantity": 10
}
```

Adds a positive integer quantity to an existing product's stock and returns the updated product with `200 OK`. The addition is a database-side atomic increment, so simultaneous restock requests are not lost. An unknown product returns `404`; an invalid quantity returns `422`.

### View products below the low-stock threshold

`GET /api/products/low-stock`

Returns `{ "threshold": 5, "data": [...] }` using the configured default threshold. A product is considered low stock when its stock is **strictly less than** the threshold.

Example override:

`GET /api/products/low-stock?threshold=10`

The threshold must be a non-negative integer.

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

Creates an order and returns it, including its customer and product lines, with `201 Created`. The email and name are required. Each item must reference an existing product and have a positive integer quantity. Insufficient stock returns `422` and leaves the order and all stock unchanged.

### View customer order history

`GET /api/customers/orders?email=alex@example.com`

Returns the customer's orders, newest first, with their order items and product details. Results are paginated 15 orders per page. An unknown email returns `404`.

## Billing screen

The root route (`/`) renders the billing screen. The navbar's **Add product** button opens a form for product code, name, price, tax rate, and initial stock; **Restock** opens a product selector and quantity form to increase an existing product's stock. Both actions use the product APIs above and update the order selectors and low-stock panel without a page reload.

Select a product and quantity to see line totals, subtotal, tax, and grand total update. The optional amount-received field shows a balance due or change to return. **Generate bill** submits the same `POST /api/orders` request used by API clients. On success, the screen displays a printable bill and a **New order** button; selecting it clears the completed form and re-enables it for the next order.

The amount received and balance/change are cashier-only display values. They are not stored as payments or included in the order records. The bill is a printable browser view, not a generated PDF or email attachment.

## Postman

Import [`postman/MallowTech-Store-Orders.postman_collection.json`](./postman/MallowTech-Store-Orders.postman_collection.json) and optionally [`postman/MallowTech-Local.postman_environment.json`](./postman/MallowTech-Local.postman_environment.json) into Postman. Select **MallowTech Local** and update `base_url` if the server is running at another address.

The collection contains requests for product creation and restocking, low-stock lookups, order creation, and customer order history, including validation/not-found examples. Update `product_id` to a product in your database before ordering or restocking. Each successful order deducts stock; repeated runs create additional orders and consume more inventory.

## Assumptions and design decisions

### Data model

- `products`, `customers`, `orders`, and `order_items` are separate tables. Orders belong to a customer; order items connect orders and products.
- Product code and customer email are unique identifiers.
- Product prices and tax rates are decimals, not binary floating-point values, and order calculations use integer minor currency units.
- An order item snapshots its unit price and tax rate when the order is placed. Historical order totals therefore do not change if the catalog is later updated.
- There is one tax rate per product. Tax is calculated for each product line from its extended subtotal and rounded half-up to cents; order tax and total are sums of the line amounts.
- Repeated product IDs in an order request are combined into one order line.
- Submitting an email already in the customer table updates that customer's name to the name in the latest order request.
- No payment records, shipping, discounts, product editing/deletion, or customer editing API were requested. The billing screen's amount-received value is not persisted.

### Inventory and concurrency

- A stock check alone is not safe against concurrent orders. Order creation instead performs a conditional database decrement (`stock >= requested quantity`) within the transaction that creates the order and its lines.
- Requested product rows are processed in ascending ID order to reduce deadlock risk. A failed stock decrement raises a validation error, and the transaction rolls back every decrement and order write.
- Databases with row-level locking serialize conflicting updates. SQLite serializes writers; the configured busy timeout and Laravel transaction retries help with concurrent write contention. For production multi-user workloads, use a database with robust row-level locking and test against that database engine.
- Restocking uses a separate atomic increment endpoint so concurrent additions are not lost.

### API and asynchronous work

- The brief does not prescribe endpoint URLs or response envelopes; this project uses the `/api` routes documented above and wraps individual created/updated resources in a `data` field.
- Missing customers in order-history lookup return `404`; invalid input and insufficient stock return Laravel's standard `422` JSON validation response.
- The low-stock comparison is strictly less than the threshold. The threshold is configurable globally and can be overridden per request.
- Confirmation work is dispatched after the order transaction commits. The job logs a simulated confirmation; it does not send SMTP mail.
- The mini-task does not specify authentication, so endpoints are unauthenticated in this implementation. Add authentication, authorization, rate limiting, and operational safeguards before production use.

## Tests and code style

Run the PHPUnit feature and unit test suites:

```bash
php artisan test
```

The tests cover successful order creation and totals, stock deduction, rollback on insufficient stock, request validation, product creation and unique codes, stock replenishment and validation, order history, low-stock filtering, and storefront rendering.

Check PHP formatting with:

```bash
vendor/bin/pint --test
```

Build frontend assets with:

```bash
npm run build
```

## Assignment submission notes

The assignment asks for screenshots of actual AI prompts when AI tools are used and a narrated 5–10 minute screen recording. Include genuine prompt screenshots in a `prompts/` directory or in this README and provide the narrated recording with the repository submission. No prompt screenshots or recording are fabricated or included here.
