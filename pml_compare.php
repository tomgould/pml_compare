<?php

declare(strict_types=1);

/**
 * @file
 * Compare drush pml JSON output from multiple Drupal sites.
 *
 * This script auto-discovers JSON files in the pml_output directory,
 * parses module data, and generates a CSV comparison report with
 * analysis to help identify:
 * - Modules safe to remove (disabled on all sites)
 * - Version mismatches requiring upgrades
 * - Partially used modules
 *
 * Usage:
 *   1. Export module lists from each site:
 *      drush pml --format=json > sitename.json
 *
 *   2. Place JSON files in the pml_output directory
 *
 *   3. Run: php pml_compare.php
 *
 *   4. Open csv/module_differences.csv
 */
class PmlCompare
{
    private const INPUT_DIR = 'pml_output';
    private const OUTPUT_DIR = 'csv';
    private const OUTPUT_FILE = 'module_differences.csv';

    private const STATUS_ENABLED = 'Enabled';
    private const STATUS_DISABLED = 'Disabled';

    private const ANALYSIS_REMOVABLE = 'REMOVABLE';
    private const ANALYSIS_VERSION_MISMATCH = 'VERSION_MISMATCH';
    private const ANALYSIS_PARTIAL = 'PARTIAL_USE';
    private const ANALYSIS_CONSISTENT = 'CONSISTENT';

    /** @var array<string, array<string, array{status: string, version: string}>> */
    private array $sites = [];

    /** @var array<string, array<string, mixed>> */
    private array $allModules = [];

    public function run(): void
    {
        $this->discoverAndParseSites();

        if (empty($this->sites)) {
            $this->error("No JSON files found in " . self::INPUT_DIR . "/");
            $this->info("Export module lists using: drush pml --format=json > sitename.json");
            exit(1);
        }

        $this->info("Found " . count($this->sites) . " site(s): " . implode(', ', array_keys($this->sites)));

        $this->buildModuleIndex();
        $this->generateCsv();

        $this->info("CSV written to " . self::OUTPUT_DIR . "/" . self::OUTPUT_FILE);
        $this->printSummary();
    }

    /**
     * Auto-discover and parse JSON files from the input directory.
     */
    private function discoverAndParseSites(): void
    {
        $pattern = self::INPUT_DIR . '/*.json';
        $files = glob($pattern);

        if ($files === false || empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $siteName = $this->extractSiteName($file);
            $modules = $this->parseJsonFile($file);

            if ($modules !== null) {
                $this->sites[$siteName] = $modules;
                $this->info("Parsed: {$siteName} (" . count($modules) . " modules)");
            }
        }

        // Sort sites alphabetically for consistent output
        ksort($this->sites);
    }

    /**
     * Extract a clean site name from the file path.
     */
    private function extractSiteName(string $filePath): string
    {
        $filename = basename($filePath, '.json');
        // Remove common suffixes
        $filename = preg_replace('/\.(pml|modules)$/i', '', $filename) ?? $filename;
        return $filename;
    }

    /**
     * Parse a JSON file and return normalized module data.
     *
     * @return array<string, array{status: string, version: string}>|null
     */
    private function parseJsonFile(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->warning("Could not read file: {$filePath}");
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warning("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return null;
        }

        $modules = [];
        foreach ($data as $machineName => $moduleData) {
            $modules[$machineName] = [
                'status' => $moduleData['status'] ?? self::STATUS_DISABLED,
                'version' => $this->normalizeVersion($moduleData['version'] ?? ''),
                'package' => $moduleData['package'] ?? '',
                'display_name' => $moduleData['display_name'] ?? $machineName,
            ];
        }

        return $modules;
    }

    private function warning(string $message): void
    {
        echo "[WARNING] {$message}\n";
    }

    /**
     * Normalize version string for consistent comparison.
     */
    private function normalizeVersion(mixed $version): string
    {
        if ($version === null || $version === '') {
            return 'dev';
        }
        return (string)$version;
    }

    private function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    private function error(string $message): void
    {
        echo "[ERROR] {$message}\n";
    }

    /**
     * Build a consolidated index of all modules across all sites.
     */
    private function buildModuleIndex(): void
    {
        foreach ($this->sites as $siteName => $modules) {
            foreach ($modules as $machineName => $moduleData) {
                if (!isset($this->allModules[$machineName])) {
                    $this->allModules[$machineName] = [
                        'display_name' => $moduleData['display_name'],
                        'package' => $moduleData['package'],
                        'sites' => [],
                    ];
                }
                $this->allModules[$machineName]['sites'][$siteName] = [
                    'status' => $moduleData['status'],
                    'version' => $moduleData['version'],
                ];
            }
        }

        // Sort modules alphabetically
        ksort($this->allModules);

        // Add analysis to each module
        foreach ($this->allModules as $machineName => &$moduleData) {
            $moduleData['analysis'] = $this->analyzeModule($moduleData['sites']);
        }
        unset($moduleData);
    }

    /**
     * Analyze a module's status across sites.
     *
     * @param array<string, array{status: string, version: string}> $siteData
     */
    private function analyzeModule(array $siteData): string
    {
        $statuses = array_column($siteData, 'status');
        $versions = array_unique(array_column($siteData, 'version'));

        $enabledCount = count(array_filter($statuses, fn($s) => $s === self::STATUS_ENABLED));
        $totalCount = count($statuses);

        // All disabled = safe to remove
        if ($enabledCount === 0) {
            return self::ANALYSIS_REMOVABLE;
        }

        // Enabled on some, disabled on others
        if ($enabledCount < $totalCount) {
            return self::ANALYSIS_PARTIAL;
        }

        // All enabled but different versions
        if (count($versions) > 1) {
            return self::ANALYSIS_VERSION_MISMATCH;
        }

        // All enabled, same version
        return self::ANALYSIS_CONSISTENT;
    }

    /**
     * Generate the CSV output file.
     */
    private function generateCsv(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }

        $fp = fopen(self::OUTPUT_DIR . '/' . self::OUTPUT_FILE, 'w');
        if ($fp === false) {
            $this->error("Could not open output file for writing");
            exit(1);
        }

        // Build header row
        $headers = ['Module', 'Machine Name', 'Package', 'Analysis'];
        foreach (array_keys($this->sites) as $siteName) {
            $headers[] = "{$siteName} Status";
            $headers[] = "{$siteName} Version";
        }
        fputcsv($fp, $headers);

        // Write module rows
        foreach ($this->allModules as $machineName => $moduleData) {
            $row = [
                $moduleData['display_name'],
                $machineName,
                $moduleData['package'],
                $moduleData['analysis'],
            ];

            foreach (array_keys($this->sites) as $siteName) {
                if (isset($moduleData['sites'][$siteName])) {
                    $row[] = $moduleData['sites'][$siteName]['status'];
                    $row[] = $moduleData['sites'][$siteName]['version'];
                } else {
                    $row[] = 'Not Present';
                    $row[] = '';
                }
            }

            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    /**
     * Print a summary of the analysis to the console.
     */
    private function printSummary(): void
    {
        $counts = [
            self::ANALYSIS_REMOVABLE => 0,
            self::ANALYSIS_VERSION_MISMATCH => 0,
            self::ANALYSIS_PARTIAL => 0,
            self::ANALYSIS_CONSISTENT => 0,
        ];

        $removable = [];
        $versionMismatch = [];

        foreach ($this->allModules as $machineName => $moduleData) {
            $analysis = $moduleData['analysis'];
            $counts[$analysis]++;

            if ($analysis === self::ANALYSIS_REMOVABLE) {
                $removable[] = $machineName;
            } elseif ($analysis === self::ANALYSIS_VERSION_MISMATCH) {
                $versionMismatch[] = $machineName;
            }
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "ANALYSIS SUMMARY\n";
        echo str_repeat('=', 60) . "\n\n";

        echo "Total modules found: " . count($this->allModules) . "\n\n";

        echo "  REMOVABLE (disabled on all sites):    {$counts[self::ANALYSIS_REMOVABLE]}\n";
        echo "  VERSION_MISMATCH (needs upgrade):     {$counts[self::ANALYSIS_VERSION_MISMATCH]}\n";
        echo "  PARTIAL_USE (review needed):          {$counts[self::ANALYSIS_PARTIAL]}\n";
        echo "  CONSISTENT (no action needed):        {$counts[self::ANALYSIS_CONSISTENT]}\n";

        if (!empty($removable)) {
            echo "\n" . str_repeat('-', 60) . "\n";
            echo "MODULES SAFE TO REMOVE FROM COMPOSER:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($removable as $module) {
                echo "  - {$module}\n";
            }
        }

        if (!empty($versionMismatch)) {
            echo "\n" . str_repeat('-', 60) . "\n";
            echo "MODULES WITH VERSION MISMATCHES:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($versionMismatch as $module) {
                $versions = [];
                foreach ($this->allModules[$module]['sites'] as $site => $data) {
                    $versions[] = "{$site}: {$data['version']}";
                }
                echo "  - {$module}\n";
                echo "    " . implode(', ', $versions) . "\n";
            }
        }

        echo "\n";
    }
}

// Run the comparison
$compare = new PmlCompare();
$compare->run();
