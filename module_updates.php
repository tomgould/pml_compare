<?php

declare(strict_types=1);

/**
 * @file
 * Parse drush pm:security / composer outdated output and generate update commands.
 *
 * For Drupal 8/9/10, the recommended approach is:
 *   - drush pm:security (for security updates)
 *   - composer outdated drupal/* (for all Drupal updates)
 *
 * Usage:
 *   1. Set your $site variable below (drush alias or path)
 *   2. Paste your "drush pm:security" or "composer outdated" output into $up_output
 *   3. Run: php module_updates.php
 *   4. Copy/paste the generated commands
 */
class ModuleUpdates
{
    private string $site;
    private string $upOutput;

    /** @var array<string, array{current: string, available: string, type: string}> */
    private array $updates = [];

    public function __construct(string $site, string $upOutput)
    {
        $this->site = $site;
        $this->upOutput = $upOutput;
    }

    public function run(): void
    {
        $this->parseOutput();

        if (empty($this->updates)) {
            echo "No updates found in the provided output.\n";
            return;
        }

        $this->printUpdateCommands();
    }

    /**
     * Parse the drush/composer output to extract update information.
     */
    private function parseOutput(): void
    {
        $lines = explode("\n", $this->upOutput);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Try to parse as drush pm:security output
            if ($this->parseDrushSecurityLine($line)) {
                continue;
            }

            // Try to parse as composer outdated output
            if ($this->parseComposerOutdatedLine($line)) {
                continue;
            }

            // Try to parse legacy drush up output (for older installs)
            $this->parseLegacyDrushLine($line);
        }
    }

    /**
     * Parse drush pm:security output format.
     * Example: "drupal/core 9.5.10 9.5.11 SECURITY UPDATE available"
     */
    private function parseDrushSecurityLine(string $line): bool
    {
        // Match: package current_version recommended_version [status]
        if (preg_match('/^(drupal\/[\w\-]+|[\w\-]+)\s+([\d\.\-\w]+)\s+([\d\.\-\w]+)\s+(.+)$/i', $line, $matches)) {
            $package = $matches[1];
            $current = $matches[2];
            $available = $matches[3];
            $status = strtoupper(trim($matches[4]));

            $type = str_contains($status, 'SECURITY') ? 'security' : 'update';

            $this->updates[$package] = [
                'current' => $current,
                'available' => $available,
                'type' => $type,
            ];
            return true;
        }
        return false;
    }

    /**
     * Parse composer outdated output format.
     * Example: "drupal/core 9.5.10 9.5.11"
     * Or with color codes stripped: "! drupal/core 9.5.10 9.5.11"
     */
    private function parseComposerOutdatedLine(string $line): bool
    {
        // Remove ANSI color codes if present
        $line = preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?? $line;

        // Remove leading symbols (!, ~, etc.)
        $line = preg_replace('/^[!~*]\s*/', '', $line) ?? $line;

        // Match: package current_version available_version
        if (preg_match('/^(drupal\/[\w\-]+|[\w\-]+\/[\w\-]+)\s+([\d\.\-\w]+)\s+([\d\.\-\w]+)/', $line, $matches)) {
            $package = $matches[1];
            $current = $matches[2];
            $available = $matches[3];

            // Only add Drupal packages
            if (str_starts_with($package, 'drupal/')) {
                $this->updates[$package] = [
                    'current' => $current,
                    'available' => $available,
                    'type' => 'update',
                ];
                return true;
            }
        }
        return false;
    }

    /**
     * Parse legacy drush up -n output format (Drupal 7 style).
     * Example: "Administration menu (admin_menu) 6.x-1.8 6.x-1.9 Update available"
     */
    private function parseLegacyDrushLine(string $line): bool
    {
        // Skip header lines and locked modules
        if (str_contains($line, 'Installed version') || str_contains($line, 'Locked via drush')) {
            return false;
        }

        // Check for update indicators
        $isSecurityUpdate = str_contains($line, 'SECURITY UPDATE');
        $isUpdate = str_contains($line, 'Update available') || $isSecurityUpdate;

        if (!$isUpdate) {
            return false;
        }

        // Extract module name from brackets: "Name (machine_name)"
        $machineName = $this->extractBracketContent($line);

        // If no brackets, try to get first word (for core: "Drupal 9.5.10 9.5.11")
        if ($machineName === null) {
            $parts = preg_split('/\s+/', $line);
            if (!empty($parts[0]) && strtolower($parts[0]) === 'drupal') {
                $machineName = 'drupal/core';
            }
        }

        if ($machineName !== null) {
            $this->updates[$machineName] = [
                'current' => '',
                'available' => '',
                'type' => $isSecurityUpdate ? 'security' : 'update',
            ];
            return true;
        }

        return false;
    }

    /**
     * Extract content from within brackets.
     */
    private function extractBracketContent(string $str): ?string
    {
        // Find the last occurrence of brackets (handles nested cases)
        $lastOpen = strrpos($str, '(');
        $lastClose = strrpos($str, ')');

        if ($lastOpen !== false && $lastClose !== false && $lastClose > $lastOpen) {
            return trim(substr($str, $lastOpen + 1, $lastClose - $lastOpen - 1));
        }

        return null;
    }

    /**
     * Print the update commands grouped by type.
     */
    private function printUpdateCommands(): void
    {
        $security = [];
        $regular = [];

        foreach ($this->updates as $package => $info) {
            if ($info['type'] === 'security') {
                $security[$package] = $info;
            } else {
                $regular[$package] = $info;
            }
        }

        echo str_repeat('=', 60) . "\n";
        echo "UPDATE COMMANDS\n";
        echo str_repeat('=', 60) . "\n\n";

        if (!empty($security)) {
            echo "SECURITY UPDATES (do these first!):\n";
            echo str_repeat('-', 40) . "\n";
            foreach ($security as $package => $info) {
                $this->printCommand($package, $info);
            }
            echo "\n";
        }

        if (!empty($regular)) {
            echo "REGULAR UPDATES:\n";
            echo str_repeat('-', 40) . "\n";
            foreach ($regular as $package => $info) {
                $this->printCommand($package, $info);
            }
            echo "\n";
        }

        // Print batch commands
        echo str_repeat('=', 60) . "\n";
        echo "BATCH COMMANDS (use with caution):\n";
        echo str_repeat('=', 60) . "\n\n";

        if (!empty($security)) {
            $packages = implode(' ', array_keys($security));
            echo "# All security updates:\n";
            echo "composer update {$packages} --with-all-dependencies\n\n";
        }

        if (!empty($regular)) {
            $packages = implode(' ', array_keys($regular));
            echo "# All regular updates:\n";
            echo "composer update {$packages} --with-all-dependencies\n\n";
        }
    }

    /**
     * Print a single update command.
     *
     * @param array{current: string, available: string, type: string} $info
     */
    private function printCommand(string $package, array $info): void
    {
        $versionInfo = '';
        if (!empty($info['current']) && !empty($info['available'])) {
            $versionInfo = " # {$info['current']} -> {$info['available']}";
        }

        // For Drupal modules, use composer
        if (str_starts_with($package, 'drupal/')) {
            echo "composer update {$package} --with-all-dependencies{$versionInfo}\n";
        } else {
            // Legacy: might be a module machine name, try both approaches
            echo "# Option 1 (if using composer):\n";
            echo "composer update drupal/{$package} --with-all-dependencies{$versionInfo}\n";
            echo "# Option 2 (legacy drush):\n";
            echo "{$this->site} up {$package}{$versionInfo}\n";
        }
    }
}

// =============================================================================
// CONFIGURATION
// =============================================================================

// How you target your site with Drush (used for legacy commands)
$site = 'drush @mysite';

// Paste your "drush pm:security" or "composer outdated drupal/*" output here
$up_output = <<<'OUTPUT'
 ------------------- ---------- ---------- ---------------------
  Name                Installed  Proposed   Status
 ------------------- ---------- ---------- ---------------------
  drupal/core         9.5.10     9.5.11     SECURITY UPDATE
  drupal/ctools       4.0.4      4.1.0      Update available
  drupal/pathauto     1.11       1.12       Update available
 ------------------- ---------- ---------- ---------------------
OUTPUT;

// =============================================================================
// RUN
// =============================================================================

$updater = new ModuleUpdates($site, $up_output);
$updater->run();
