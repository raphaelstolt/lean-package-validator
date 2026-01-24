---
name: updating-gitattributes-file
description: Update .gitattributes files for Laravel Boost projects using lean-package-validator; use when adding missing export-ignore entries or refreshing existing content.
---

# Update .gitattributes with lean-package-validator

Use lean-package-validator to refresh an existing .gitattributes file:

1. Run `./vendor/bin/lean-package-validator update` to replace missing export-ignore entries.
2. Add `--align-export-ignores` if you want aligned columns.
3. Add `--omit-header` to avoid modifying the header comment.
4. If you need a preview first, run `./vendor/bin/lean-package-validator validate --diff` and then re-run with the `update` command.

Keep any non-export-ignore rules intact; lean-package-validator appends export-ignores below them.
