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

## Useful Artisan Commands

```bash
# Check Laravel version
php artisan --version

# List all registered routes
php artisan route:list

# Check migration status
php artisan migrate:status

# Run tests
php artisan test
```

---

## Architecture

```text
Route → Controller → Form Request / Validation → Service → Model / Query → Database
```

- Controllers are thin.
- Business logic lives in service classes.
- API responses use the `App\Traits\ApiResponse` trait.
- Authentication uses Laravel Sanctum (token-based).

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 |
| Database | MySQL 8 |
| Authentication | Laravel Sanctum |
| API | REST JSON |
| Mobile Client | Flutter (future) |

---

## License

Proprietary. All rights reserved.

