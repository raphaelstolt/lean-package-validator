# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [v2.0.1] - 2019-09-04
- Upgraded development dependencies
- Fixed appearing static analysis errors

## [v2.0.0] - 2019-01-02
### Removed
- Removed support for PHP `5.6` and `7.0.`. Closes [#29](https://github.com/raphaelstolt/lean-package-validator/issues/29).

## [v1.9.0] - 2018-11-03
### Added
- Enabled distribution via PHAR. Closes [#27](https://github.com/raphaelstolt/lean-package-validator/issues/27).

## [v1.8.1] - 2017-10-18
### Fixed
- Matched directories e.g. `tests` or `specs` are export-ignored only once. Closes [#24](https://github.com/raphaelstolt/lean-package-validator/issues/24).

## [v1.8.0] - 2017-10-12
### Added
- Additional `--align-export-ignores|-a` option to align the export-ignores which improves readability. Closes [#23](https://github.com/raphaelstolt/lean-package-validator/issues/23).
- Additional `--enforce-alignment` option to enforce that all export-ignores are aligned.

## [v1.7.3] - 2017-10-02
### Fixed
- Fix dist file pattern to also match `*.dist` files.

## [v1.7.2] - 2017-05-08
### Fixed
- Fix non existent export ignored artifacts are excluded from validation. Fixes [#22](https://github.com/raphaelstolt/lean-package-validator/issues/22).

## [v1.7.1] - 2017-05-06
### Fixed
- Fix gitignored files with a pre- and postfixed directory separator are excluded from validation. Fixes [#21](https://github.com/raphaelstolt/lean-package-validator/issues/21).

## [v1.7.0] - 2017-03-31
### Added
- Additional artifacts glob pattern expansion to match AppVeyor configuration files.

## [v1.6.0] - 2016-10-08
### Added
- New `init` command to create a `.lpv` file with the default glob patterns. Closes [#18](https://github.com/raphaelstolt/lean-package-validator/issues/18).

## [v1.5.2] - 2016-10-08
### Added
- Internal Composer scripts have a namespace.

### Fixed
- Fix gitignored files are excluded from validation. Fixes [#17](https://github.com/raphaelstolt/lean-package-validator/issues/17).


## [v1.5.1] - 2016-10-05
### Fixed
- Fix missing export-ignore patterns on existing `.gitattributes` file with no export-ignore entries. Fixes [#16](https://github.com/raphaelstolt/lean-package-validator/issues/16).

## [v1.5.0] - 2016-10-04
### Added
- New `--keep-license` option to allow license files in releases. Closes [#15](https://github.com/raphaelstolt/lean-package-validator/issues/15).

## [v1.4.0] - 2016-10-04
### Added
- Additional artifacts glob pattern expansion to match CaptainHook configuration files. Closes [#14](https://github.com/raphaelstolt/lean-package-validator/issues/14).

### Fixed
- Fix missing `.gitattributes export-ignore` in suggested and generated `.gitattributes` file content. Closes [#13](https://github.com/raphaelstolt/lean-package-validator/issues/13).

## [v1.3.1] - 2016-10-04
### Fixed
- Fix dependency constraint.

## [v1.3.0] - 2016-09-30
### Added
- Leading slashes in export-ignore patterns are considered as a smell and raise a warning. Closes [#4](https://github.com/raphaelstolt/lean-package-validator/issues/4).
- A missing text auto configuration is considered as a smell and raises a warning. Closes [#12](https://github.com/raphaelstolt/lean-package-validator/issues/12).

## [v1.2.0] - 2016-09-22
### Added
- New `--glob-pattern-file` option to load custom glob patterns from a file. Closes [#9](https://github.com/raphaelstolt/lean-package-validator/issues/9).

## [v1.1.0] - 2016-09-18
### Added
- Additional artifacts glob pattern expansion to match Phulp files.
- New `--enforce-strict-order` option to enforce a strict order comparison of export-ignores in the .gitattributes file. Closes [#6](https://github.com/raphaelstolt/lean-package-validator/issues/6).

## [v1.0.6] - 2016-09-11
### Fixed
- Fix present, invalid `.gitattributes` files are overwritable. Closes [#8](https://github.com/raphaelstolt/lean-package-validator/issues/8).

## [v1.0.5] - 2016-09-09
### Fixed
- Fix expected and actual `export-ignores` comparison. Related to [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

## [v1.0.4] - 2016-09-09
### Added
- Additional artifacts glob pattern expansion to match Vagrant and Box files.

### Fixed
- Fix present `.gitattributes` files are really validated. Closes [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

## [v1.0.3] - 2016-09-05
### Fixed
- Fix `directory` argument usage and validation.

## [v1.0.2] - 2016-09-05
### Added
- Additional validation of glob patterns injected via the `--glob-pattern` option. Closes [#2](https://github.com/raphaelstolt/lean-package-validator/issues/2).

## [v1.0.1] - 2016-09-04
### Fixed
- Fix for autoloading in global installations.

## v1.0.0 - 2016-09-04
- Initial release.

[Unreleased]: https://github.com/raphaelstolt/lean-package-validator/compare/v2.0.0...HEAD
[v2.0.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.9.0...v2.0.0
[v1.9.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.8.1...v1.9.0
[v1.8.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.8.0...v1.8.1
[v1.8.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.3...v1.8.0
[v1.7.3]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.2...v1.7.3
[v1.7.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.1...v1.7.2
[v1.7.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.0...v1.7.1
[v1.7.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.6.0...v1.7.0
[v1.6.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.2...v1.6.0
[v1.5.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.1...v1.5.2
[v1.5.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.0...v1.5.1
[v1.5.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.4.0...v1.5.0
[v1.4.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.3.1...v1.4.0
[v1.3.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.6...v1.1.0
[v1.0.6]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.5...v1.0.6
[v1.0.5]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.4...v1.0.5
[v1.0.4]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.3...v1.0.4
[v1.0.3]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.2...v1.0.3
[v1.0.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.1...v1.0.2
[v1.0.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.0...v1.0.1
