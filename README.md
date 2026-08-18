# Tripromio — Backend

Travel Companion Discovery + Trip Planning Platform.

> See `PROJECT_CONTEXT.md` for full product context, business rules, and development roadmap.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.3 |
| Composer | 2.x |
| MySQL | 8.x |
| Node.js | 18+ |
| NPM | 9+ |

---

## Local Setup

### 1. Clone the repository

```bash
git clone <repository-url>
cd Tripromio
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node dependencies

```bash
npm install
```

### 4. Copy environment file

```bash
cp .env.example .env
```

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Configure the environment

Open `.env` and set:

```env
APP_NAME=Tripromio
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tripromio
DB_USERNAME=root
DB_PASSWORD=
```

> `.env` is never committed to version control.

### 7. Create the MySQL database

```sql
CREATE DATABASE tripromio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 8. Run migrations

```bash
php artisan migrate
```

---

## Running the Application

```bash
php artisan serve
```

The API will be available at:

```
http://localhost:8000
```

---

## Running Tests

The test suite uses an **in-memory SQLite database** (configured in `phpunit.xml`).  
No MySQL connection is needed to run tests.

```bash
php artisan test
```

---

## API Health Endpoint

Verify the API pipeline is operational:

```http
GET /api/health
```

Expected response:

```json
{
    "success": true,
    "message": "Tripromio API is running",
    "data": {
        "status": "ok"
    }
}
```

---

## Authentication API

All auth endpoints live under `/api/auth`. Tokens follow the Bearer scheme.

| Method | Endpoint | Auth Required | Description |
|---|---|---|---|
| `POST` | `/api/auth/register` | No | Register a new account |
| `POST` | `/api/auth/login` | No | Login and receive a token |
| `GET` | `/api/auth/me` | Yes | Get the authenticated user |
| `POST` | `/api/auth/logout` | Yes | Revoke the current token |
| `POST` | `/api/auth/forgot-password` | No | Request a password reset link |
| `POST` | `/api/auth/reset-password` | No | Reset password using the emailed token |
| `GET` | `/api/email/verify/{id}/{hash}` | Yes | Verify email address (signed URL) |
| `POST` | `/api/email/verification-notification` | Yes | Resend verification email |

**Authenticated requests** require:
```
Authorization: Bearer <token>
```

---

## Email Verification (Local Development)

The project uses `MAIL_MAILER=log` locally. Verification emails are written to `storage/logs/laravel.log` instead of being delivered.

To find the verification link during local testing:
```bash
# Search the log for the verification URL
findstr "verify" storage\logs\laravel.log
```

Alternatively, you can manually generate a signed verification URL in Tinker:
```bash
php artisan tinker
>>> $user = App\Models\User::first();
>>> URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
```

---

## License

Proprietary. All rights reserved.

