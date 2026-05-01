# Sigma SMS A2P OTP Panel

A production-ready, multi-tenant PHP/MySQL web application for managing virtual phone numbers, receiving real OTP messages from a live API, tracking profit per SMS, and providing a hierarchical user management system with a REST API.

---

## Features

- **Real OTP ingestion** from `https://tempnum.net/api/public/otps`
- **Multi-tenant hierarchy**: Admin → Manager → Reseller → Sub-Reseller
- **Profit tracking** per SMS per assigned number
- **Beautiful animated UI** — Bootstrap 5, ApexCharts, DataTables, Select2
- **REST API** with token authentication for programmatic OTP retrieval
- **Railway-ready** Docker deployment for PHP panel
- **FastAPI migration scaffold** for phased backend cutover

---

## Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `curl`, `json`, `mbstring`
- MySQL 5.7+ or MariaDB 10.3+

---

## Deploy on Railway

### Step 1 — Add a MySQL database

In your Railway project, click **+ New** → **Database** → **MySQL**. Railway will provision it and inject these env vars automatically:
- `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`

### Step 2 — Deploy the app

Connect your GitHub repo (or drag-and-drop the folder) to a new Railway service. Railway detects the root `Dockerfile` and builds automatically.

The root `Dockerfile` uses **PHP 8.2 + Apache** with `pdo_mysql` pre-installed.

### Optional: FastAPI service (phase migration)

Deploy a second Railway service from `backend/`:
- Build command: `pip install -r requirements.txt`
- Start command: `uvicorn app.main:app --host 0.0.0.0 --port $PORT`
- Health check path: `/health`

Set these vars on the PHP service to enable staged cutover for dashboard endpoints:
- `USE_FASTAPI_AJAX=1`
- `FASTAPI_BASE_URL=https://<your-fastapi-service>.railway.app`

### Step 3 — Install the application

Once deployed, visit:
```
https://your-app.railway.app/install.php
```

The DB credentials are **auto-filled** from Railway's environment variables. Just set:
- **App URL**: `https://your-app.railway.app`
- **Admin username + password**

Click **Install Now**.

### Step 4 — Done

Delete `install.php` (or Railway will warn you). Your panel is live at:
```
https://your-app.railway.app/
```

---

## Local Development

```bash
# Clone / extract the project
cd sigma_sms

# Start PHP built-in server
php -S localhost:8080

# Visit http://localhost:8080/install.php
```

FastAPI local (optional):
```bash
cd backend
python -m venv .venv
# Windows:
.venv\\Scripts\\activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8000
```

---

## Quick Install (Manual)

1. Create MySQL database: `sigma_sms_a2p`
2. Import schema: `mysql -u root -p sigma_sms_a2p < schema.sql`
3. Edit `config.php` with your credentials
4. Default login: **admin** / **password** — change immediately!

---

## Directory Structure

```
sigma_sms/
├── ajax/                    # AJAX endpoints (server-side DataTables, actions)
│   ├── cron_fetch.php       # OTP ingestion from external API
│   ├── dashboard_stats.php  # Dashboard statistics
│   ├── dashboard_charts.php # Chart data
│   ├── dt_sms_reports.php   # SMS reports DataTable
│   ├── dt_profit_reports.php
│   ├── dt_numbers.php
│   ├── dt_users.php
│   ├── aj_numbers.php       # Number CRUD actions
│   ├── aj_users.php         # User CRUD actions
│   ├── aj_services.php      # Service autocomplete
│   └── aj_countries.php     # Country list
├── api/
│   └── otps.php             # Public REST API
├── assets/
│   ├── css/app.css          # Custom styles + animations
│   └── js/app.js            # App JavaScript
├── includes/
│   ├── header.php           # Shared HTML head + sidebar + topbar
│   └── footer.php           # Shared scripts + closing tags
├── config.php               # Database + app configuration
├── functions.php            # All helper functions
├── schema.sql               # Database schema (+ performance indexes)
├── Dockerfile               # Railway PHP deploy image
├── railway.json             # Railway deploy config
├── .env.example             # Environment variables template
├── backend/                 # FastAPI parity backend (phase migration)
├── install.php              # Web installer (DELETE after use)
├── index.php                # Redirect to dashboard
├── login.php                # Login page
├── logout.php
├── dashboard.php            # Main dashboard
├── sms_reports.php          # SMS reports with filters
├── profit_stats.php         # Profit breakdown
├── numbers.php              # Number management (admin/manager)
├── my_numbers.php           # Assigned numbers (reseller)
├── users.php                # User management
├── profile.php              # Profile + API token
├── notifications.php
├── news_master.php          # Announcements (admin/manager)
├── credit_notes.php
├── payment_requests.php
├── bank_accounts.php
└── statements.php
```

---

## User Roles

| Role           | Capabilities |
|----------------|-------------|
| `admin`        | Full system control, all users, all numbers, all reports |
| `manager`      | Manage own resellers, own numbers, trigger OTP fetch |
| `reseller`     | View assigned numbers, create sub-resellers, assign numbers |
| `sub_reseller` | View only assigned numbers and own profit |

---

## OTP Fetching

OTPs are fetched from the live endpoint:
```
GET https://tempnum.net/api/public/otps
```

- **Manual**: Click "Fetch OTPs Now" on the dashboard (admin/manager)
- **Cron**: `* * * * * php /path/to/sigma_sms/ajax/cron_fetch.php`
- Minimum **60-second** interval enforced between fetches

---

## REST API

```
GET /api/otps.php?token=YOUR_TOKEN
```

**Parameters:**
| Param    | Description                        |
|----------|------------------------------------|
| `token`  | Your API token (required)          |
| `from`   | Start date YYYY-MM-DD              |
| `to`     | End date YYYY-MM-DD                |
| `service`| Filter by service (e.g. `viber`)   |
| `country`| Filter by country code (e.g. `MM`) |
| `number` | Filter by phone number             |
| `page`   | Page number (default: 1)           |
| `limit`  | Records per page (max: 500)        |

**Example response:**
```json
{
  "status": "success",
  "total": 42,
  "page": 1,
  "limit": 100,
  "total_pages": 1,
  "data": [
    {
      "number": "+959661902830",
      "service": "viber",
      "country": "MM",
      "otp": "685102",
      "message": "Your viber verification code is: 685102",
      "received_at": "2026-04-27 12:36:57",
      "rate": "0.005500",
      "profit": "0.005500"
    }
  ]
}
```

Generate your API token at: **Profile & API Token** page.

---

## Security

- All SQL queries use **PDO prepared statements** — no SQL injection
- Passwords hashed with `password_hash()` (bcrypt)
- CSRF token protection on all forms
- Session-based auth with role checks on every page
- API tokens: 64-char cryptographically random hex strings
- **Change the default admin password immediately after install**
- **Delete `install.php` after installation**
- Use HTTPS in production

---

## Default Credentials

| Username | Password   |
|----------|------------|
| `admin`  | `password` |

⚠️ **Change immediately after first login.**

---

## License

MIT — Free to use and modify.
