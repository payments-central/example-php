# Payments Central — PHP Demo

A minimal PHP 8 application that demonstrates the core Payments Central API workflows: charging cards, listing and fetching transactions, issuing refunds, and launching a hosted checkout session. No framework — just Composer autoloading and plain cURL.

---

## Prerequisites

| Requirement | Version |
|---|---|
| PHP | 8.0 or higher |
| Composer | any recent version |
| PHP extension | `curl` (enabled by default in most distros) |

---

## Setup

```bash
# 1. Clone or copy this directory
cd pc-example-php

# 2. Install autoloader (no third-party packages)
composer install

# 3. Configure credentials
cp .env.example .env
# Open .env and set your API key and merchant ID

# 4. Start the built-in PHP server
php -S localhost:8000 -t public/
```

Then open **http://localhost:8000** in your browser.

---

## Credentials

| Variable | Description |
|---|---|
| `PAYMENTS_CENTRAL_API_KEY` | Bearer token — starts with `sk_sandbox_` |
| `PAYMENTS_CENTRAL_MERCHANT_ID` | Your merchant account ID |
| `PAYMENTS_CENTRAL_BASE_URL` | Override the API base URL (optional) |

---

## Demo Routes

| Route | Method | What it does |
|---|---|---|
| `/` | GET | Home page with links to all demo actions |
| `/demo/charge` | POST | Charges $10.00 USD and displays the transaction JSON |
| `/demo/transactions` | GET | Lists the last 10 transactions in an HTML table |
| `/demo/transaction?id=xxx` | GET | Fetches and displays a single transaction by ID |
| `/demo/refund` | POST | Issues a full refund on the given transaction ID |
| `/demo/checkout` | POST | Creates a hosted checkout session and redirects to it |
| `/demo/success` | GET | Landing page after a successful checkout |
| `/demo/cancel` | GET | Landing page after a cancelled checkout |

---

## Project Layout

```
pc-example-php/
├── composer.json          # PSR-4 autoload, no dependencies
├── .env.example           # Copy to .env and fill in credentials
├── .gitignore
├── src/
│   └── Client.php         # PaymentsCentral\Client — all API calls
└── public/
    └── index.php          # Front controller — routing + HTML rendering
```

---

## Further Reading

Full API reference and authentication guide: **https://developer.payments-central.com**
