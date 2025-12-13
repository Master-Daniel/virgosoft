# Trading Platform - Backend (Laravel)

This is the backend API for the cryptocurrency trading platform, built with Laravel 12.

## 📋 Prerequisites

Before you begin, ensure you have the following installed on your system:

- **PHP 8.2 or higher** - [Download PHP](https://www.php.net/downloads.php)
- **Composer** - PHP dependency manager ([Download Composer](https://getcomposer.org/download/))
- **Database** - MySQL, PostgreSQL, or SQLite (SQLite is recommended for quick setup)
- **Pusher Account** - For real-time features ([Sign up for free](https://pusher.com/))

### Verify Installation

Open your terminal/command prompt and verify:

```bash
php -v          # Should show PHP 8.2 or higher
composer -V     # Should show Composer version
```

## 🚀 Setup Instructions

### Step 1: Navigate to Backend Directory

```bash
cd backend
```

### Step 2: Install PHP Dependencies

Install all required PHP packages using Composer:

```bash
composer install
```

This will download all Laravel framework files and dependencies into the `vendor` directory.

### Step 3: Configure Environment File

1. **Copy the example environment file:**

```bash
# On Windows (Git Bash or Command Prompt)
copy .env.example .env

# On Mac/Linux
cp .env.example .env
```

2. **Open the `.env` file** in a text editor and configure the following:

#### Basic Application Settings

```env
APP_NAME="Trading Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
```

#### Database Configuration

**Option A: SQLite (Easiest for beginners - no database server needed)**

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

**Option B: MySQL**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trading_platform
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

**Option C: PostgreSQL**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=trading_platform
DB_USERNAME=your_postgres_username
DB_PASSWORD=your_postgres_password
```

#### Pusher Configuration (Required for Real-time Features)

1. Go to [Pusher Dashboard](https://dashboard.pusher.com/)
2. Create a new app (or use an existing one)
3. Go to "App Keys" tab
4. Copy your credentials and paste them in `.env`:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_key
PUSHER_APP_SECRET=your_pusher_secret
PUSHER_APP_CLUSTER=mt1
```

**Note:** The cluster value (e.g., `mt1`, `us2`, `eu`) can be found in your Pusher dashboard.

#### CORS Configuration

Add your frontend URL to allow cross-origin requests:

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

### Step 4: Generate Application Key

Laravel requires an encryption key for security:

```bash
php artisan key:generate
```

This will automatically add the `APP_KEY` to your `.env` file.

### Step 5: Create Database (If using SQLite)

If you're using SQLite, create the database file:

```bash
# On Windows (Git Bash)
touch database/database.sqlite

# On Windows (Command Prompt)
type nul > database\database.sqlite

# On Mac/Linux
touch database/database.sqlite
```

### Step 6: Run Database Migrations

Create all necessary database tables:

```bash
php artisan migrate
```

This will create tables for:
- Users
- Assets (BTC, ETH balances)
- Orders
- Trades
- Personal access tokens (for authentication)

### Step 7: Clear Configuration Cache

Ensure Laravel reads your latest `.env` settings:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### Step 8: Start the Development Server

Start the Laravel server on port 8080:

```bash
php artisan serve --port=8080
```

You should see:
```
INFO  Server running on [http://127.0.0.1:8080].
```

**Important:** Keep this terminal window open while developing. The server will automatically reload when you make code changes.

### Step 9: Verify Backend is Running

Open your browser and visit:
- `http://localhost:8080` - Should show Laravel welcome page
- `http://localhost:8080/api` - Should return JSON (may show error, but confirms API is accessible)

## ✅ Verification Checklist

- [ ] PHP 8.2+ installed
- [ ] Composer installed
- [ ] Dependencies installed (`composer install`)
- [ ] `.env` file created and configured
- [ ] Application key generated (`php artisan key:generate`)
- [ ] Database configured (SQLite/MySQL/PostgreSQL)
- [ ] Migrations run successfully (`php artisan migrate`)
- [ ] Pusher credentials added to `.env`
- [ ] Server running on port 8080
- [ ] Can access `http://localhost:8080`

## 🔧 Common Issues & Solutions

### Issue: "Class not found" errors
**Solution:** Run `composer install` again and ensure you're in the `backend` directory.

### Issue: Database connection error
**Solution:** 
- For SQLite: Ensure `database/database.sqlite` file exists
- For MySQL/PostgreSQL: Verify credentials in `.env` and ensure database server is running

### Issue: "APP_KEY is not set"
**Solution:** Run `php artisan key:generate`

### Issue: Port 8080 already in use
**Solution:** 
- Find and close the application using port 8080, OR
- Use a different port: `php artisan serve --port=8081` (then update frontend `.env` accordingly)

### Issue: Pusher connection errors
**Solution:** 
- Verify Pusher credentials in `.env` match your Pusher dashboard
- Ensure `BROADCAST_CONNECTION=pusher` is set
- Check that cluster value matches your Pusher app settings

## 📡 API Endpoints

Once the server is running, the following endpoints are available:

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user (requires authentication)

### Profile
- `GET /api/profile` - Get user balance and assets (requires authentication)

### Orders
- `GET /api/orders?symbol=BTC&orderbook=true` - Get orders (requires authentication)
- `POST /api/orders` - Create a limit order (requires authentication)
- `POST /api/orders/{id}/cancel` - Cancel an order (requires authentication)

### Broadcasting
- `POST /api/broadcasting/auth` - Pusher channel authentication (requires authentication)

## 🗄️ Database Schema

The application uses the following main tables:

- **users** - User accounts with USD balance
- **assets** - User crypto assets (BTC, ETH) with available and locked amounts
- **orders** - Buy/sell orders with status tracking
- **trades** - Completed trades with commission records
- **personal_access_tokens** - API authentication tokens

## 📝 Notes

- The backend runs on **port 8080** by default
- New users start with **$10,000 USD** and initial crypto assets (0.5 BTC, 5 ETH)
- Real-time features require Pusher to be properly configured
- All API endpoints (except register/login) require authentication via Bearer token

## 🔗 Next Steps

After setting up the backend:
1. Set up the frontend (see `../frontend/README.md`)
2. Ensure frontend `.env` points to `http://localhost:8080/api`
3. Test the application by registering a user and placing orders

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. For more information, visit [laravel.com](https://laravel.com).
