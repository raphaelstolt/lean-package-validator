# Lean package validator

![Test Status](https://github.com/raphaelstolt/lean-package-validator/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/lean-package-validator.svg?style=flat)](https://packagist.org/packages/stolt/lean-package-validator)
![PHP Version](https://img.shields.io/badge/php-8.1+-ff69b4.svg)
[![composer.lock available](https://poser.pugx.org/stolt/lean-package-validator/composerlock)](https://packagist.org/packages/stolt/lean-package-validator)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)

<p align="center">
    <img src="lpv-logo.png" 
         alt="Lean package validator logo">
</p>

The lean package validator is a utility tool that validates a project/micro-package
for its `leanness`. A project/micro-package is considered `lean` when its common
repository artifacts won't be included in release assets.

## Installation

The lean package validator CLI can be installed globally through Composer.

``` bash
composer global require stolt/lean-package-validator
```

Make sure that the path to your global vendor binaries directory is in your `$PATH`.
You can determine the location of your global vendor binaries directory via
`composer global config bin-dir --absolute`. This way the `lean-package-validator`
executable can be located.

Since the default name of the CLI is quite a mouthful, an alias which can be placed
in `~/.aliases`, `~/.zshrc` or the like might come in handy. The alias shown next
assumes that `$COMPOSER_HOME` is `~/.config/composer` and not `~/.composer`.

``` bash
alias lpv='~/.config/composer/vendor/bin/lean-package-validator $@'
```

The lean package validator also can be installed locally to a project which allows
further utilisation via [Composer scripts](https://getcomposer.org/doc/articles/scripts.md).

``` bash
composer require --dev stolt/lean-package-validator
```

> [!TIP] 
> As of release `v1.9.0` it's also possible to install and use the lean package validator
> via a PHAR [file](https://github.com/raphaelstolt/lean-package-validator/releases/tag/v1.9.0).

Therefor download a released version e.g. v3.3.1 and move it to `/usr/local/bin` as shown next.

``` bash
wget --quiet https://github.com/raphaelstolt/lean-package-validator/releases/download/v3.3.1/lean-package-validator.phar
mv lean-package-validator.phar /usr/local/bin/lean-package-validator
```

## Usage

Run the lean package validator CLI within or against a project/micro-package
directory, and it will validate the [export-ignore](https://git-scm.com/book/en/v2/Customizing-Git-Git-Attributes#Exporting-Your-Repository) entries present in
a `.gitattributes` file against a set of common repository artifacts. If no
`.gitattributes` file is present it will suggest to create one.

``` bash
lean-package-validator validate [<directory>]
```

### Available options

The `--enforce-strict-order` option will enforce a strict order comparison
of export-ignores in the `.gitattributes` file and fail validation if the order
differs. Per __default__ the order comparison is done in a non-strict fashion.

``` bash
lean-package-validator validate [<directory>] --enforce-strict-order
```

The `--create|-c` option creates an `.gitattributes` file if nonexistent.

``` bash
lean-package-validator validate [<directory>] --create
```

The `--overwrite|-o` option overwrites an existing `.gitattributes` file when
there are any `export-ignore` entries missing. Using this option on a directory
with a nonexistent `.gitattributes` file implicates the `--create` option.

``` bash
lean-package-validator validate [<directory>] --overwrite
```

The `--glob-pattern` option allows you to overwrite the default pattern used
to match common repository artifacts. The amount of pattern in the grouping
braces is expected to be `>1`. As shown next this utility could thereby also
be used for projects (i.e. Python) outside the PHP ecosystem.

``` bash
lean-package-validator validate [<directory>] --glob-pattern '{.*,*.rst,*.py[cod],dist/}'
```

The default pattern is defined in the PHP preset [file](./src/Presets/PhpPreset.php).

The `--glob-pattern-file` option allows you to load patterns, which should
be used to match the common repository artifacts, from a given file. You
can put a `.lpv` file in the repository which will be used per default and
overwrite the default pattern. The structure of such a glob pattern file
can be taken from the [example](example/.lpv) directory or be created
via `lean-package-validator init`.

``` bash
lean-package-validator validate [<directory>] --glob-pattern-file /path/to/glob-pattern-file
```

The `--keep-license` option will allow a license file in the release/dist archive file which is per default ommitted.

``` bash
lean-package-validator validate [<directory>] --keep-license
```

The `--keep-readme` option will allow a README file in the release/dist archive file which is per default ommitted.

``` bash
lean-package-validator validate [<directory>] --keep-readme
```

The `--keep-glob-pattern` option allows to keep matching files in the release/dist archive file which are per default ommitted.

``` bash
lean-package-validator validate [<directory>] --keep-glob-pattern '{LICENSE.*,README.*,docs*}'
```

The `--align-export-ignores|-a` option will align the created or overwritten export-ignores for a better readability.

``` bash
lean-package-validator validate [<directory>] --align-export-ignores --create
```

The `--sort-from-directories-to-files|-s` option will order the export-ignores from directories to files for a better readability.

``` bash
lean-package-validator validate [<directory>] --sort-from-directories-to-files --create
```

The `--enforce-alignment` option will enforce a strict alignment of export-ignores
in the `.gitattributes` file and fail validation if they aren't aligned. Per __default__
no alignment is enforced.

The `--validate-git-archive` option will validate that no common repository artifacts slip
into the release/dist archive file. It will do so by creating a `temporary archive` from the
current Git `HEAD` and inspecting its content. With a set `--keep-license` option a license
file becomes mandatory and will fail the archive validation if not present.

``` bash
lean-package-validator validate [<directory>] --validate-git-archive
```

The `--diff` option will show a visual diff between the actual and expected `.gitattributes` content.

``` bash
lean-package-validator validate --diff

The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
--- Original
+++ Expected
@@ -7,9 +7,8 @@
 .github/ export-ignore
 .gitignore export-ignore
 .gitmessage export-ignore
 .php-cs-fixer.php export-ignore
-.phpunit.result.cache export-ignore
+.idea/ export-ignore
 bin/application-version export-ignore
 bin/lean-package-validator.phar export-ignore
 bin/release-version export-ignore
```

The `--report-stale-export-ignores` option extends the validation to look for export-ignore statements referencing non-existent
repository artifacts. In combination with the `--diff` option these will be shown in the output.

#### Additional commands

The `init` command will create an initial `.lpv` file with the default patterns used to match common repository artifacts.

``` bash
lean-package-validator init [<directory>]
```

The `--overwrite|-o` option overwrites an existing `.lpv` file.

The `--preset` option allows to choose from a predefined set of glob pattern.
Available presets are `PHP`, `Python`, and `Go`. With `PHP` being the default.

The `tree` command allows you to inspect the __flat__ `source` and `dist package` structure of the given project.

``` bash
lean-package-validator tree [<directory>] --src

Package: stolt/lean-package-validator
.
├── bin
├── coverage-reports
├── example
├── .git
├── .github
├── .idea
├── .phpunit.cache
├── src
├── tests
├── vendor
├── box.json.dist
├── CHANGELOG.md
├── composer.json
├── composer.lock
├── .editorconfig
├── .gitattributes
├── .gitignore
├── .gitmessage
├── LICENSE.md
├── lpv-logo.png
├── peck.json
├── .php-cs-fixer.php
├── phpstan.neon.dist
├── phpunit.xml.dist
└── README.md

10 directories, 15 files
```

``` bash
lean-package-validator tree [<directory>] --dist-package

Package: stolt/lean-package-validator
.
├── bin
├── composer.json
└── src

2 directories, 1 file
```

## Utilisation via Composer scripts, cpx, or it's dedicated GitHub Action

To avoid that changes coming from contributions or own modifications slip into release/dist archives it
might be helpful to use a guarding [Composer script](https://getcomposer.org/doc/articles/scripts.md), which will be available at everyone's fingertips.

By adding the following to the project/micro-package its `composer.json` the `.gitattributes` file can
now be easily validated via `composer validate-gitattributes`.

``` json
{
    "scripts": {
        "validate-gitattributes": "lean-package-validator validate"
    },
}
```
Another option to utilise the lean package validator is via [cpx](https://cpx.dev/).

``` bash
cpx stolt/lean-package-validator validate
```

For utilising a dedicated GitHub Action have a look at the documentation over [here](https://github.com/raphaelstolt/lean-package-validator-action).

### Running tests

``` bash
composer lpv:test
```

### License

This library and its CLI are licensed under the MIT license. Please see [LICENSE.md](LICENSE.md) for more details.

### Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more details.

### Contributing

Please see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for more details.
