# Changelog

#### v1.3.0 `2016-09-30`
- Leading slashes in export-ignore patterns are considered as a smell and raise a warning. Closes [#4](https://github.com/raphaelstolt/lean-package-validator/issues/4).
- A missing text auto configuration is considered as a smell and raises a warning. Closes [#12](https://github.com/raphaelstolt/lean-package-validator/issues/12).

#### v1.2.0 `2016-09-22`
- New `--glob-pattern-file` option to load custom glob patterns from a file. Closes [#9](https://github.com/raphaelstolt/lean-package-validator/issues/9).

#### v1.1.0 `2016-09-18`
- Additional artifacts glob pattern expansion to match Phulp files.
- New `--enforce-strict-order` option to enforce a strict order comparison of export-ignores in the .gitattributes file. Closes [#6](https://github.com/raphaelstolt/lean-package-validator/issues/6).

#### v1.0.6 `2016-09-11`
- Fix present, invalid `.gitattributes` files are overwritable. Closes [#8](https://github.com/raphaelstolt/lean-package-validator/issues/8).

#### v1.0.5 `2016-09-09`
- Fix expected and actual `export-ignores` comparison. Related to [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

#### v1.0.4 `2016-09-09`
- Additional artifacts glob pattern expansion to match Vagrant and Box files.
- Fix present `.gitattributes` files are really validated. Closes [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

#### v1.0.3 `2016-09-05`
- Fix `directory` argument usage and validation.

#### v1.0.2 `2016-09-05`
- Additional validation of glob patterns injected via the `--glob-pattern` option. Closes [#2](https://github.com/raphaelstolt/lean-package-validator/issues/2).

#### v1.0.1 `2016-09-04`
- Fix for autoloading in global installations.

#### v1.0.0 `2016-09-04`
- Initial release.
