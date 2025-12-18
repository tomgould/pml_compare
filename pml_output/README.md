# PML Output Directory

Place your Drush PML JSON exports here.

## How to Generate

```bash
drush @yoursite pml --format=json > yoursite.json
```

Or if using --uri:

```bash
drush --uri=yoursite.com pml --format=json > yoursite.json
```

## File Naming

Name your files descriptively - the filename (minus .json) becomes the site name in the CSV output.

Examples:

- `production.json` → "production" in CSV
- `staging.json` → "staging" in CSV
- `client-site.json` → "client-site" in CSV
