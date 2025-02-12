# WP-Coupon-Sync

WP-Coupon-Sync is a standalone PHP CLI application that synchronizes coupons from affiliate networks into a WordPress installation. It is designed to be run via Supervisor (or cron) and uses WP‑CLI to interact with WordPress.

## Features

- Retrieves brand (coupon store) data from WordPress via WP‑CLI.
- Supports affiliate networks (currently ADCELL is implemented).
- Creates/updates coupon posts in WordPress.
- Uses a modular design to allow easy extension with new affiliate network integrations.

## Requirements

- PHP 8.3 or higher
- WP-CLI installed on your system
- Composer

## Installation

1. Clone this repository.
2. Run `composer install` to install dependencies.
3. Configure your settings in `src/Config.php`.
4. Make the CLI script executable:

   ```bash
   chmod +x bin/coupon-sync.php
