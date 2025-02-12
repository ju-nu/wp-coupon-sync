# WP-Coupon-Sync

WP-Coupon-Sync is a standalone PHP CLI application that synchronizes coupons from affiliate networks into a WordPress installation. It uses WP‑CLI to interact with WordPress and loads configuration variables from an `.env` file.

## Features

- **Brand Retrieval:** Retrieves brand (coupon store) data from WordPress via WP‑CLI.
- **Affiliate Integration:** Integrates with affiliate networks (currently ADCELL is implemented).
- **Coupon Synchronization:** Creates and updates coupon posts in WordPress.
- **Modular Architecture:** Designed for easy extension to add additional affiliate network integrations.
- **Environment Configuration:** All sensitive and environment-specific configuration is managed through an `.env` file.

## Requirements

- PHP 8.3 or higher
- WP-CLI installed on your system
- Composer

## Installation

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/ju-nu/wp-coupon-sync.git
   ```
2. **Change to the Project Directory:**
   ```bash
   cd wp-coupon-sync
   ```
3. **Install Dependencies:**
   ```bash
   composer install
   ```
4. **Set Up Environment Variables:**
   - Copy the sample environment file:
     ```bash
     cp .env.example .env
     ```
   - Edit the `.env` file with the correct configuration values for your environment.
5. **Make the CLI Script Executable:**
   ```bash
   chmod +x bin/coupon-sync.php
   ```

## Usage

Run the CLI application manually:

```bash
./bin/coupon-sync.php
```

Or configure it with Supervisor. For example, create a Supervisor configuration similar to:

```ini
[program:wp-coupon-sync]
command=/usr/bin/php /path/to/wp-coupon-sync/bin/coupon-sync.php
autostart=true
autorestart=true
stderr_logfile=/var/log/wp-coupon-sync.err.log
stdout_logfile=/var/log/wp-coupon-sync.out.log
```

## License

This project is licensed under the MIT License.
