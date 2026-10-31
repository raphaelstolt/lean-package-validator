# Lean Package Validator GitHub Action

Validates the `.gitattributes` file using the `validate` command from [lean-package-validator](https://github.com/stolt/lean-package-validator).

This action performs self-validation, checking the project's own `.gitattributes` file to ensure it's correctly 
formatted and functional.

## Usage

```yaml
- name: Validate .gitattributes
  uses: ./.github/actions/validate
```

### With custom PHP version

```yaml
- name: Validate .gitattributes
  uses: ./.github/actions/validate
  with:
    php-version: '8.4'
```

### With additional validate options

```yaml
- name: Validate .gitattributes
  uses: ./.github/actions/validate
  with:
    options: '--enforce-strict-order --enforce-alignment --diff'
```

## Inputs

| Input | Description | Required | Default |
|-------|-------------|----------|---------|
| `php-version` | PHP version to use | No | `8.3` |
| `options` | Additional options/flags passed to the validate command | No | `` |

## Example workflow

```yaml
name: validate

on:
  push:
    paths:
      - '.gitattributes'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Validate .gitattributes
        uses: ./.github/actions/validate
```

## Using the GitHub Action from another repository

### Reference the action directly

Reference the action directly from this repository using the full GitHub path. This keeps you up to date without copying
files.

```yaml
- name: Validate .gitattributes
  uses: stolt/lean-package-validator/.github/actions/validate@main
  with:
    php-version: '8.4'
    options: '--enforce-strict-order --enforce-alignment'
```

You can pin to a specific tag (e.g., `@v6.4.0`) for stability. The action runs inside your repository's context, so ensure
you've checked out your code first with `actions/checkout`.
