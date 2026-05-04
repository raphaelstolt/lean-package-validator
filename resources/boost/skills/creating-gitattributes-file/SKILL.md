---
name: creating-gitattributes-file
description: Create a lean-package-validator and Git compatible .gitattributes file in projects; use when generating missing export-ignore entries or scaffolding a new .gitattributes file.
version: 1.0.0

tags:
- git
- gitattributes
- lean-package-validator
- php
---

# Create .gitattributes with lean-package-validator

Follow these steps from the repository root:

1. Run `./vendor/bin/lean-package-validator create` to generate a .gitattributes file with all expected export-ignore entries.
2. If you want aligned export-ignore columns, add `--align-export-ignores`.
3. If you do not want the generated header comment, add `--omit-header`.
4. If a `.lpv` file exists, it will be used automatically; otherwise the default PHP preset is used.
5. You can refresh an existing, stale `.lpv` file with `./vendor/bin/lean-package-validator refresh`.
