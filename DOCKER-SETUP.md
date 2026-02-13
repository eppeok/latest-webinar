# Docker Development Environment — Windows 11 Setup

## Prerequisites

### 1. Install Docker Desktop for Windows

1. Download from: https://www.docker.com/products/docker-desktop/
2. Run the installer
3. When prompted, ensure **"Use WSL 2 instead of Hyper-V"** is checked (recommended)
4. Restart your PC when prompted
5. Open Docker Desktop and wait for it to say **"Docker Desktop is running"** in the system tray

### 2. Verify Docker is Working

Open **PowerShell** or **Command Prompt** and run:

```powershell
docker --version
docker compose version
```

Both should return version numbers. If not, restart Docker Desktop.

---

## Quick Start

### 1. Clone the Repository

```powershell
git clone https://github.com/Creative813/webinar-plugin.git
cd webinar-plugin
```

### 2. Start the Environment

```powershell
docker compose up -d
```

**First run** takes ~1-2 minutes (downloads images, sets up the database, installs WordPress + WooCommerce).

### 3. Open Your Test Site

| URL | Purpose |
|---|---|
| http://localhost:8080 | WordPress site (frontend) |
| http://localhost:8080/wp-admin | WordPress admin panel |
| http://localhost:8081 | phpMyAdmin (database browser) |

**Admin credentials:** `admin` / `admin`

---

## What Gets Installed Automatically

The setup script runs once and:

- Installs WordPress with test site configuration
- Installs and activates WooCommerce
- Activates the Review Raffles plugin
- Configures store defaults (USD, US address, permalinks)
- Creates WooCommerce pages (shop, cart, checkout, my account)

---

## Daily Development Workflow

### Making Code Changes

1. Edit any file in the `review-raffles/` folder using your editor (VS Code, etc.)
2. Refresh the browser
3. The change is live immediately — no restart needed

PHP is interpreted, so the WordPress container reads your files directly from disk on every page load.

### Starting and Stopping

```powershell
# Stop (preserves everything — database, uploads, settings)
docker compose stop

# Resume (takes ~3 seconds)
docker compose start

# View container logs (useful for debugging)
docker compose logs -f wordpress

# View just the setup script output
docker compose logs wpcli
```

### Full Reset (Fresh Database)

```powershell
docker compose down -v
docker compose up -d
```

This destroys the database and WordPress files, then rebuilds everything from scratch. Your plugin code in `review-raffles/` is never affected — it lives on your disk, not in the container.

---

## Services Included

| Service | Port | Purpose |
|---|---|---|
| **wordpress** | 8080 | WordPress 6.7 + PHP 8.2 + Apache |
| **db** | 3306 | MySQL 8.0 database |
| **phpmyadmin** | 8081 | Database browser (root / rootpass) |
| **wpcli** | — | One-shot setup container (exits after setup) |

---

## Troubleshooting

### "Port 8080 is already in use"

Another application is using port 8080. Either stop that application, or change the port in `docker-compose.yml`:

```yaml
    ports:
      - "9090:80"    # Change 8080 to any free port
```

Then access the site at `http://localhost:9090`.

### "Docker Desktop is not running"

Open Docker Desktop from the Start menu and wait for it to fully start (green icon in system tray).

### Plugin not showing in WordPress admin

Check that the `review-raffles/` folder is in the right location — it should be a direct child of the repo root, next to `docker-compose.yml`:

```
webinar-plugin/
  docker-compose.yml
  setup.sh
  review-raffles/
    review-raffles.php
    ...
```

### Setup script didn't run / WooCommerce not installed

Run the setup manually:

```powershell
docker compose run --rm wpcli sh /setup.sh
```

### Viewing PHP error logs

```powershell
docker compose exec wordpress cat /var/www/html/wp-content/debug.log
```

Or tail them live:

```powershell
docker compose exec wordpress tail -f /var/www/html/wp-content/debug.log
```

### Database access

- **phpMyAdmin:** http://localhost:8081 (no login required)
- **Direct MySQL connection:** `localhost:3306`, user `root`, password `rootpass`

### Running WP-CLI Commands

```powershell
# List all plugins
docker compose run --rm wpcli wp plugin list

# Deactivate/reactivate the plugin
docker compose run --rm wpcli wp plugin deactivate review-raffles
docker compose run --rm wpcli wp plugin activate review-raffles

# Check WordPress version
docker compose run --rm wpcli wp core version

# Export the database
docker compose run --rm wpcli wp db export /var/www/html/backup.sql
```

---

## Adding Test Data

After the environment is running, you can create test products with seat configurations through the WordPress admin at http://localhost:8080/wp-admin:

1. Go to **Products > Add New**
2. Create a **Variable product** with a "Seat" attribute
3. Set stock quantities and the max seats field
4. Publish and test the seat booking flow on the frontend
