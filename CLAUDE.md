# Lean Package Validator

A PHP CLI tool that validates whether a project's `.gitattributes` file is correctly configured to exclude development 
artifacts from release archives.

## Tech Stack

- **PHP 8.2+** with `declare(strict_types=1)` on all files
- **Symfony Console** for the CLI framework
- **PHPUnit 11** for testing
- **PHPStan level 8** for static analysis
- **Mago** (PSR-2/PSR-12) for code style

## Contribution commands

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

## Available CLI commands
create      Create a new .gitattributes file for a project/micro-package repository
init        Create a default .lpv file in a given project/micro-package repository
refresh     Refresh a present .lpv file
tree        Display the source structure of a given project/micro-package repository or it's dist package
update      Update an existing .gitattributes file for a project/micro-package repository
validate    Validate the .gitattributes file of a given project/micro-package repository

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
- Integration tests use `zenstruck/console-test`
