# ionCube Encoding Guide for Review Raffles

## Overview

ionCube encodes PHP source files into bytecode that cannot be read by humans or AI. Encoded files require the **ionCube Loader** (a free PHP extension) on the customer's server to execute.

```
Development (plaintext PHP)
        │
        ▼
  ionCube Encoder ──► Encoded bytecode (.php files that look like binary)
        │
        ▼
  Distribution ZIP ──► Customer installs on their WordPress site
        │
        ▼
  ionCube Loader (on customer's server) ──► Executes the bytecode
```

**You always develop with plaintext PHP.** Encoding only happens when you build a release for distribution.

---

## Step 1: Purchase ionCube Encoder

Go to: https://www.ioncube.com/encoder_eval_download.php

| Edition | Price | What You Need |
|---|---|---|
| **Cerberus** (basic) | $199/yr | Sufficient for this plugin |
| **Pro** | $399/yr | Adds license key embedding, expiry dates |
| **Business** | $599/yr | Adds dynamic key generation |

**Recommendation:** Start with **Pro** — it lets you embed license expiry dates directly into encoded files, which pairs well with your WHMCS licensing.

### Installation (Windows)

1. Download the Windows encoder from your ionCube account
2. Extract to a folder like `C:\ioncube`
3. Add to your system PATH:
   - Search "Environment Variables" in Windows Start menu
   - Edit `Path` under System variables
   - Add `C:\ioncube`
4. Restart PowerShell and verify:

```powershell
ioncube_encoder --version
```

### Installation (WSL / Linux)

```bash
# Download (check ioncube.com for latest link)
wget https://downloads.ioncube.com/loader_downloads/ioncube_encoder_evaluation.tar.gz
tar xzf ioncube_encoder_evaluation.tar.gz
sudo mv ioncube_encoder /usr/local/bin/
ioncube_encoder --version
```

---

## Step 2: Understand the File Strategy

Not all files need encoding. Template files with HTML output should stay plaintext (themes may need to override them). Core logic and licensing get encoded.

### Files to Encode (7 files — core logic)

| File | Why Encode |
|---|---|
| `review-raffles.php` | Main plugin — seat booking, winner selection, all hooks |
| `twwt-admin-settings.php` | Licensing logic, WHMCS validation, secret key |
| `twwt-product-notification.php` | Email/SMS/Zoom notification sending |
| `twwt-order-csv.php` | CSV customer data export |
| `twwt-admin-metabox.php` | Admin product metabox |
| `twwt-admin-add-webinar-simple.php` | Webinar creation admin page |
| `twwt-livestrom.php` | Livestorm API integration |

### Files Left as Plaintext (4 files — templates)

| File | Why Plaintext |
|---|---|
| `ticket-layout.php` | Frontend HTML template (seat grid) |
| `ticket-participant.php` | Frontend HTML template (participant display) |
| `my-account-webinars.php` | My Account page template |
| `twwt-myaccount-videos.php` | My Account video endpoint |

### Directories Copied As-Is

| Directory | Why |
|---|---|
| `asset/` | CSS, JS, images — not PHP |
| `vendor/` | Third-party Twilio SDK — encoding would break autoloading |

---

## Step 3: Build an Encoded Release

### Option A: Using the Build Script

From the repo root (in PowerShell, WSL, or terminal):

```bash
./build-encoded.sh --version 2.2
```

This creates: `review-raffles-v2.2-encoded.zip`

### Option B: Using the ionCube Project File

```bash
ioncube_encoder --project-file ioncube.cfg
cd dist && zip -r ../review-raffles-encoded.zip review-raffles/
```

### Option C: Manual (Single File)

```bash
ioncube_encoder --php 8.2 --optimize max \
  -o dist/review-raffles.php \
  review-raffles/review-raffles.php
```

---

## Step 4: Verify the Encoded Build

### Check that files are encoded

Open an encoded file in a text editor. You should see something like:

```
<?php //0046a
// ... binary content ...
HR+cPxBfSAiELFxBfEBCAJ0cHA3cJfOABIqLDIiKiw...
```

If you can still read the PHP code, encoding failed.

### Test in Docker

Update the `docker-compose.yml` WordPress service to install the ionCube Loader:

```yaml
  wordpress:
    image: wordpress:6.7-php8.2-apache
    volumes:
      - wp_data:/var/www/html
      - ./dist/review-raffles:/var/www/html/wp-content/plugins/review-raffles
```

Note: The default WordPress Docker image does NOT include the ionCube Loader. For testing encoded builds, you have two options:

**Option 1:** Create a custom Dockerfile:

```dockerfile
FROM wordpress:6.7-php8.2-apache

# Install ionCube Loader
ADD https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz /tmp/
RUN tar xzf /tmp/ioncube_loaders_lin_x86-64.tar.gz -C /tmp/ \
    && cp /tmp/ioncube/ioncube_loader_lin_8.2.so $(php -r 'echo ini_get("extension_dir");')/ \
    && echo "zend_extension=ioncube_loader_lin_8.2.so" > /usr/local/etc/php/conf.d/00-ioncube.ini \
    && rm -rf /tmp/ioncube*
```

**Option 2:** Test on your actual WordPress hosting (most hosts already have the loader).

---

## Step 5: Customer Server Requirements

### Most Hosts Already Have It

ionCube Loader is pre-installed on the majority of WordPress hosting providers:

- SiteGround
- Bluehost
- HostGator
- A2 Hosting
- GoDaddy (managed WordPress)
- WP Engine
- Cloudways
- InMotion Hosting

### If a Customer's Host Doesn't Have It

The plugin includes `ioncube-loader-check.php` which shows a friendly admin notice:

> **Review Raffles:** This plugin requires the ionCube Loader PHP extension.
> Most WordPress hosting providers include it by default —
> please contact your hosting provider to enable it.

### Customer Self-Install (cPanel)

Most cPanel hosts have a "Select PHP Version" tool:
1. Log into cPanel
2. Go to "Select PHP Version" or "MultiPHP Manager"
3. Click "Extensions"
4. Check "ionCube Loader"
5. Save

---

## Development Workflow

```
┌─────────────────────────────────────────────────────────┐
│                    DEVELOPMENT                          │
│                                                         │
│  Edit plaintext PHP files in review-raffles/            │
│  Test locally with Docker (no encoding needed)          │
│  Commit and push to git                                 │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                    RELEASE                              │
│                                                         │
│  1. Run: ./build-encoded.sh --version X.X               │
│  2. Output: review-raffles-vX.X-encoded.zip             │
│  3. Upload ZIP to your distribution platform            │
│     (WooCommerce store, EDD, WHMCS, etc.)               │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                    CUSTOMER                             │
│                                                         │
│  Downloads encoded ZIP from your store                  │
│  Installs in WordPress (Plugins > Add New > Upload)     │
│  ionCube Loader on their server decodes at runtime      │
│  Source code is never visible to the customer            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Key point:** You never commit encoded files to git. Git always has the plaintext source. Encoding is a build step that happens before distribution, just like compiling a binary.

---

## Files in This Repository

| File | Purpose |
|---|---|
| `build-encoded.sh` | Shell script to build an encoded distribution ZIP |
| `ioncube.cfg` | ionCube Encoder project configuration file |
| `review-raffles/ioncube-loader-check.php` | Friendly admin notice if loader is missing |
| `IONCUBE-GUIDE.md` | This guide |

---

## Optional: ionCube Pro Features

If you purchase the **Pro** edition, you can also:

### Embed License Expiry

```bash
ioncube_encoder --php 8.2 --expire 2027-01-01 \
  --expire-message "Your license has expired. Please renew at reviewraffles.com" \
  review-raffles/review-raffles.php
```

The encoded file will stop executing after the expiry date — enforced at the bytecode level, impossible to remove without the encoder.

### Per-Customer Encoding

Build a unique ZIP per customer with their license key baked in:

```bash
ioncube_encoder --php 8.2 \
  --passphrase "CUSTOMER-LICENSE-KEY-HERE" \
  --property "license=WR-2026-XXXX" \
  review-raffles/review-raffles.php
```

This lets you watermark builds and trace leaked copies.

### Domain Locking (Strongest Protection)

```bash
ioncube_encoder --php 8.2 \
  --allowed-server "SERVER_NAME=customerdomain.com" \
  review-raffles/review-raffles.php
```

The encoded file will only execute on the specified domain. Hardcoded at the bytecode level.
