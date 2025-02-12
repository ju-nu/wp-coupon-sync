<?php
declare(strict_types=1);

namespace JUNU\Command;

use JUNU\Config;
use JUNU\Utils\Logger;
use Exception;

class WpCliClient
{
    private string $wpCliPath;
    private string $wpPath;

    public function __construct()
    {
        $this->wpCliPath = Config::getWpCliPath();
        $this->wpPath    = Config::getWpPath();
    }

    /**
     * Runs a WP‑CLI command and returns its output.
     *
     * @param string $command The WP‑CLI command (without the --path parameter).
     * @return string
     * @throws Exception
     */
    public function runCommand(string $command): string
    {
        $fullCommand = sprintf(
            '%s --path=%s %s',
            escapeshellcmd($this->wpCliPath),
            escapeshellarg($this->wpPath),
            $command
        );
        Logger::info("Running WP‑CLI command: {$fullCommand}");
        $output = [];
        $returnVar = 0;
        exec($fullCommand, $output, $returnVar);
        if ($returnVar !== 0) {
            throw new Exception("WP‑CLI command failed: {$fullCommand}. Output: " . implode("\n", $output));
        }
        return implode("\n", $output);
    }
}
