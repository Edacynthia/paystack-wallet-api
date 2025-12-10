# paystack-wallet-api - Wallet & Authentication API

A Laravel backend API for user authentication, wallets, transactions, and API key access. Supports JWT, Google OAuth, Paystack payments, and API key-based service-to-service access.

---

## Features

* User signup/login with JWT
* Google OAuth authentication
* Wallet management:

  * Deposit via Paystack
  * Wallet-to-wallet transfers
  * Transaction history
* API Key management (Sanctum) for service-to-service access
* Middleware for JWT and API key authorization
* Postman/Bruno collection support for API testing

---

## Requirements

* PHP 8.x
* Laravel 10.x
* MySQL
* Composer
* Paystack account for payment integration
* Google API credentials for OAuth

---

## Installation

```bash
git clone <repository_url>
cd paystack-wallet-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

---

## Environment Setup

Update your `.env` with:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paystack_wallet_api
DB_USERNAME=root
DB_PASSWORD=

GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

PAYSTACK_SECRET=sk_test_xxx
PAYSTACK_BASE=https://api.paystack.co
PAYSTACK_WEBHOOK_SECRET=whsec_...
```

---

## Endpoints

### Authentication (JWT)

| Method | Endpoint                    | Description                    |
| ------ | --------------------------- | ------------------------------ |
| POST   | `/api/auth/signup`          | User registration, returns JWT |
| POST   | `/api/auth/login`           | User login, returns JWT        |
| POST   | `/api/auth/logout`          | Logout (JWT protected)         |
| GET    | `/auth/google`          | Redirect to Google OAuth       |
| GET    | `/auth/google/callback` | Google OAuth callback          |

### Wallet

| Method | Endpoint                                 | Description                      |
| ------ | ---------------------------------------- | -------------------------------- |
| POST   | `/api/wallet/deposit`                    | Initialize Paystack deposit      |
| GET    | `/api/wallet/deposit/{reference}/status` | Verify deposit status            |
| POST   | `/api/wallet/transfer`                   | Transfer funds to another wallet |
| GET    | `/api/wallet/balance`                    | Retrieve wallet balance          |
| GET    | `/api/wallet/transactions`               | List wallet transactions         |

### API Key Management (Sanctum)

| Method | Endpoint                     | Description                          |
| ------ | ---------------------------- | ------------------------------------ |
| POST   | `/api/keys/create`           | Generate new API key (JWT protected) |
| POST   | `/api/keys/rollover`         | Rollover expired key                 |
| DELETE | `/api/keys/{tokenId}/revoke` | Revoke API key                       |

### Service-to-Service Access

| Method | Endpoint                | Description                        |
| ------ | ----------------------- | ---------------------------------- |
| GET    | `/api/service-resource` | Access with API key only (Sanctum) |

---

## Authentication

* JWT authentication for user-related routes.
* Sanctum API keys for service-to-service access.
* Custom abilities can be assigned to API keys (`service:access`).

---

## Paystack Integration

* All amounts handled in **kobo**.
* Webhooks validate successful payments and update wallet balances.
* Ensure `PAYSTACK_SECRET` and `PAYSTACK_WEBHOOK_SECRET` are correct.

---

## API Key Management

* Max 5 active API keys per user.
* Keys can have expiration: 1H, 1D, 1M, 1Y.
* Rollover allows generating a new key with same permissions.

---

## Testing

* Use Postman or Bruno to test endpoints.
* Include `Authorization: Bearer <token>` for JWT-protected routes.
* Include `x-api-key` header for service-to-service routes.
* Deposit and transfer endpoints require actual wallet balance.

---

## Postman / Bruno Collection

* Export your Postman/Bruno collection as JSON.
* Share with your team or import to test the API live.
