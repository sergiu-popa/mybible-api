# CLAUDE.md — Project Agent Configuration

## Project

- **Name:** <PROJECT_NAME>
- **Type:** Laravel (Beyond CRUD architecture)
- **PHP version:** 8.3+
- **Laravel version:** 11.x
- **Livewire version:** 4.x

## Story Location

All agent workflow stories live in `.agent-workflow/stories/` and are committed
to this repository.

## Story ID Prefix

Use `<PREFIX>` for story IDs in this project (e.g., `<PREFIX>-001-description`).

## Domain Map

<!-- List the existing domains so agents understand the codebase structure. -->

| Domain | Purpose |
|---|---|
| `Invoicing` | Invoice creation, editing, sending |
| `Shared` | Cross-domain value objects and enums |

## Project-Specific Rules

<!-- Add any rules that override or extend the global guidelines. -->

- ...

## Environment

```bash
# Run tests
php artisan test

# Run a specific test file
php artisan test --filter=CreateInvoiceActionTest

# Run Livewire tests
php artisan test --filter=InvoiceCreateTest

# Code style
./vendor/bin/pint
```
