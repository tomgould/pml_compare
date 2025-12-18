# PML Compare

Compare Drupal module lists across multiple sites to identify:

- **Removable modules** - Disabled on all sites, safe to remove from composer
- **Version mismatches** - Same module with different versions (upgrade candidates)
- **Partially used modules** - Enabled on some sites, disabled on others

## Requirements

- PHP 7.4 or higher
- Drupal 8, 9, or 10 sites
- Drush 9+ (for JSON export)

## Quick Start

### 1. Export Module Lists

On each Drupal site, export the module list as JSON:

```bash
# Using drush alias
drush @mysite pml --format=json > mysite.json

# Or using --uri
drush --uri=mysite.com pml --format=json > mysite.json

# Or from within the Drupal root
cd /var/www/mysite
drush pml --format=json > mysite.json
```

### 2. Collect JSON Files

Place all exported JSON files in the `pml_output/` directory:

```
pml_compare/
├── pml_output/
│   ├── site1.json
│   ├── site2.json
│   ├── site3.json
│   └── production.json
├── csv/
├── pml_compare.php
└── module_updates.php
```

### 3. Run Comparison

```bash
cd /path/to/pml_compare
php pml_compare.php
```

### 4. Review Results

- **Console output** shows a summary with actionable items
- **CSV file** (`csv/module_differences.csv`) contains the full comparison

## CSV Output Columns

| Column         | Description                            |
|----------------|----------------------------------------|
| Module         | Human-readable module name             |
| Machine Name   | Drupal machine name (used in composer) |
| Package        | Module package/category                |
| Analysis       | Action indicator (see below)           |
| {Site} Status  | Enabled/Disabled/Not Present per site  |
| {Site} Version | Version installed on that site         |

## Analysis Values

| Value              | Meaning                              | Action                    |
|--------------------|--------------------------------------|---------------------------|
| `REMOVABLE`        | Disabled on ALL sites                | Safe to `composer remove` |
| `VERSION_MISMATCH` | Different versions across sites      | Review and upgrade        |
| `PARTIAL_USE`      | Enabled on some sites only           | Review if intentional     |
| `CONSISTENT`       | Same version, same status everywhere | No action needed          |

## Module Updates Script

The `module_updates.php` script helps generate update commands from drush output.

### Usage

1. Get your update list:
   ```bash
   # Security updates
   drush pm:security

   # Or all outdated packages
   composer outdated "drupal/*"
   ```

2. Edit `module_updates.php` and paste the output into `$up_output`

3. Run:
   ```bash
   php module_updates.php
   ```

4. Copy the generated composer commands

## Example Workflow

```bash
# 1. Export from all sites
drush @site1 pml --format=json > pml_output/site1.json
drush @site2 pml --format=json > pml_output/site2.json
drush @site3 pml --format=json > pml_output/site3.json

# 2. Run comparison
php pml_compare.php

# 3. Review removable modules
# The console output lists modules safe to remove

# 4. Remove unused modules (example)
composer remove drupal/unused_module

# 5. Update mismatched versions
composer update drupal/ctools --with-all-dependencies
```

## Tips

### Filtering by Package

The CSV includes a "Package" column. Use spreadsheet filtering to focus on specific module types (e.g., "Core", "
Contrib", your custom package names).

### Handling Custom Modules

Custom modules without versions will show as `dev` in the version column. This is normal and helps distinguish them from
contrib modules.

### Large Sites

For sites with many modules, the JSON export might take a moment. The comparison script handles files of any size
efficiently.

## Migrating from Old Format

If you have old Drupal 6/7 PML text files, you'll need to re-export using the JSON format. The text format is no longer
supported.

## License

This project is provided as-is for Drupal site management.
