# Lean Package Validator

A PHP CLI tool that validates whether a project's `.gitattributes` file is correctly configured to exclude development 
artifacts from release archives.

## Tech Stack

- **PHP 8.1+** with `declare(strict_types=1)` on all files
- **Symfony Console** for the CLI framework
- **PHPUnit 11** for testing
- **PHPStan level 8** for static analysis
- **PHP-CS-Fixer** (PSR-2/PSR-12) for code style

## Commands

```bash
# Run tests
composer lpv:test

# Run tests with coverage
composer lpv:test-with-coverage

# Fix code style
composer lpv:cs-fix

# Lint code style (check only)
composer lpv:cs-lint

# Static analysis
composer lpv:static-analyse

# All pre-commit checks
composer lpv:pre-commit-check

# Spell check
composer lpv:spell-check

# Dependency analysis
composer lpv:dependency-analyse
```

## Project Structure

- `bin/lean-package-validator` — CLI entry point
- `src/Commands/` — CLI command classes
- `src/Presets/` — Language presets (PHP, Python, Go, JavaScript, Rust)
- `src/` — Core classes (Analyser, Archive, Glob, Tree, etc.)
- `tests/` — PHPUnit test suite mirroring `src/` structure
- `resources/boost/skills/` — AI assistant skills

## Conventions

- Namespace root: `Stolt\LeanPackage\`
- PSR-4 autoloading, one class per file
- Full type hints on all method parameters and return types
- Tests use `Stolt\LeanPackage\Tests\` namespace
