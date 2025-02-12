#!/usr/bin/env php
<?php
declare(strict_types=1);

use JUNU\Command\CouponSyncCommand;
use JUNU\Utils\Logger;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $command = new CouponSyncCommand();
    $command->run();
    Logger::info('Coupon sync completed successfully.');
} catch (Throwable $e) {
    Logger::error('Fatal error: ' . $e->getMessage());
    exit(1);
}
