# Lean package validator

![Test Status](https://github.com/raphaelstolt/lean-package-validator/workflows/test/badge.svg)
[![Version](http://img.shields.io/packagist/v/stolt/lean-package-validator.svg?style=flat)](https://packagist.org/packages/stolt/lean-package-validator)
![PHP Version](https://img.shields.io/badge/php-8.2+-ff69b4.svg)
[![Boost ready](https://img.shields.io/badge/boost-ready-purple.svg?style=flat)](./resources/boost/skills)
![Laravel 13 ready](https://img.shields.io/badge/laravel_13-ready-f54927.svg?style=flat)
![Downloads](https://img.shields.io/packagist/dt/stolt/lean-package-validator)
[![composer.lock available](https://poser.pugx.org/stolt/lean-package-validator/composerlock)](https://packagist.org/packages/stolt/lean-package-validator)
[![PDS Skeleton](https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat)](https://github.com/php-pds/skeleton)
![llms.txt](https://img.shields.io/badge/llms.txt-available-blue.svg?style=flat)
[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

<p align="center">
    <img src="lpv-logo.png" 
         title="lpv: the lean package validator"
         alt="Lean package validator logo">
</p>

The lean package validator, or its abbreviation __lpv__, is a utility tool that `validates` a project/micro-package for its
`leanness`. A project/micro-package is considered `lean` when its common repository artefacts won't be included in release
assets.

It can also [create](https://github.com/raphaelstolt/lean-package-validator?tab=readme-ov-file#create-command), [update](https://github.com/raphaelstolt/lean-package-validator?tab=readme-ov-file#update-command), and [reformat](https://github.com/raphaelstolt/lean-package-validator?tab=readme-ov-file#reformat-command) the `leanness` enforcing export-ignore entries of a `.gitattributes` 
file.

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

The lean package validator also can be installed globally via [Homebrew](https://brew.sh/) on macOS systems:

``` bash
brew tap raphaelstolt/lean-package-validator
brew install lean-package-validator
```

## Usage

Run the lean package validator CLI within or against a project/micro-package directory, and it will validate the
[export-ignore](https://git-scm.com/book/en/v2/Customizing-Git-Git-Attributes#Exporting-Your-Repository) directives present in a `.gitattributes` file against a set of common repository artefacts.

It can handle __classic__ export-ignore directives as well as __negated__ export-ignore directives.

Classic export-ignore directives are defined as follows:
```txt
.aiassistant/               export-ignore
.editorconfig               export-ignore

...

LICENSE.md                  export-ignore
llms.txt                    export-ignore
mago.toml                   export-ignore
peck.json                   export-ignore
phpstan.neon.dist           export-ignore
phpunit.xml.dist            export-ignore
README.md                   export-ignore
tests/                      export-ignore
```

While negated export-ignore directives are defined as follows:

```txt
* export-ignore

composer.json              -export-ignore
bin/                       -export-ignore
bin/lean-package-validator -export-ignore
resources/                 -export-ignore
resources/**               -export-ignore
src/                       -export-ignore
src/**                     -export-ignore
```

Run the following command to validate a project/micro-package.

``` bash
lean-package-validator validate [<directory>]
```

If no `.gitattributes` file is present it will suggest [creating](https://github.com/raphaelstolt/lean-package-validator?tab=readme-ov-file#create-command) one.

### Available options

The `--enforce-strict-order` option will enforce a strict order comparison of export-ignores in the `.gitattributes`
file and fail validation if the order differs. Per __default__ the order comparison is done in a non-strict fashion.

``` bash
lean-package-validator validate --enforce-strict-order [<directory>]
```

The `--glob-pattern` option allows you to overwrite the default pattern used to match common repository artefacts. The
number of patterns in the grouping braces is expected to be `>1`. As shown next, this utility could thereby also be used
for projects (i.e. Python) outside the PHP ecosystem.

``` bash
lean-package-validator validate --glob-pattern '{.*,*.rst,*.py[cod],dist/}' [<directory>]
```

The default pattern is defined in the PHP preset [file](./src/Presets/PhpPreset.php).

The `--glob-pattern-file` option allows you to load patterns, which should
be used to match the common repository artefacts, from a given file. You
can put a `.lpv` file in the repository which will be used per default and
overwrite the default pattern. The structure of such a glob pattern file
can be taken from the [example](example/.lpv) directory or be created
via `lean-package-validator init`.

``` bash
lean-package-validator validate --glob-pattern-file /path/to/glob-pattern-file [<directory>]
```

The `--keep-license` option will allow a license file in the release/dist archive file which is per default omitted.

``` bash
lean-package-validator validate --keep-license [<directory>]
```

The `--keep-readme` option will allow a README file in the release/dist archive file which is per default omitted.

``` bash
lean-package-validator validate --keep-readme [<directory>]
```

The `--keep-glob-pattern` option allows keeping matching files in the release/dist archive file which are per default omitted.

``` bash
lean-package-validator validate --keep-glob-pattern '{LICENSE.*,README.*,docs*}' [<directory>]
```

The `--sort-from-directories-to-files|-s` option will order the export-ignores from directories to files for better readability.

``` bash
lean-package-validator validate --sort-from-directories-to-files [<directory>]
```

The `--enforce-alignment` option will enforce a strict alignment of export-ignores
in the `.gitattributes` file and fail validation if they aren't aligned. Per __default__,
no alignment is enforced.

The `--preset=[<preset>]` option will use a predefined set of glob pattern. Available presets are `PHP`, `Python`,
`Rust`, `JavaScript`, and `Go`. With `PHP` being the default.

The `--validate-git-archive` option will validate that no common repository artefacts slip
into the release/dist archive file. It will do so by creating a `temporary` archive from the
current Git `HEAD` and inspecting its content. With a set `--keep-license` option a license
file becomes mandatory and will fail the archive validation if not present.

``` bash
lean-package-validator validate --validate-git-archive [<directory>]
```

The `--diff` option will show a visual diff between the actual and expected `.gitattributes` content.

``` bash
lean-package-validator validate --diff [<directory>]

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

The `--report-stale-export-ignores` option extends the validation to look for export-ignore statements referencing 
non-existent repository artefacts. In combination with the `--diff` option these will be shown in the output.

The `--stdin-input` option allows the validate command to read from `STDIN`, so that the following piped output 
can be used for validation. It currently only does a strict comparison.

```bash
cat .gitattributes | lean-package-validator validate --stdin-input
```

### Additional commands

#### Create command

The `create` command will create a `.gitattributes` file in the given directory. Via the `--force` option it is possible
to overwrite an existing `.gitattributes` file. 

To define the flavour of the export-ignore directives to use in the creation process, the `--flavour` option can be used, with
the default being `classic`.

#### Update command

The `update` command will update a present `.gitattributes` file in the given directory. Like the above-mentioned `create` 
command it provides a `--dry-run` option to see what the `.gitattributes` content would look like.

#### Reformat command

The `reformat` command will reformat a present `.gitattributes` file in the given directory. This command provides a
`--dry-run` option to see what the `.gitattributes` content would look like.

It is possible to influence the reformatting by providing the `--sort-alphabetically` and `--sort-from-directories-to-files`
options. Via the `--group` option it is possible to group the export-ignores and non-export-ignores entries of the given
`.gitattributes` file.

#### Configuration init command

The `init` command will create an initial `.lpv` file with the default patterns used to match common repository artefacts.

``` bash
lean-package-validator init [<directory>]
```

The `--overwrite|-o` option overwrites an existing `.lpv` file. Also, see the [refresh](https://github.com/raphaelstolt/lean-package-validator?tab=readme-ov-file#configuration-refresh-command) command.

The `--preset` option allows choosing from a predefined set of glob pattern. Available presets are `PHP`, `Python`, `Rust`,
`JavaScript`, and `Go`. With `PHP` being the default.

The `--dry-run` option will show the content of the `.lpv` file that would be created.

#### Configuration refresh command

The `refresh` command updates an existing `.lpv` file by adding missing preset patterns while keeping any manual
modifications already present in the file.

``` bash
lean-package-validator refresh [<directory>]
```

The command expects a present `.lpv` file in the target directory and will fail if none is existent.

The `--preset` option allows choosing from a predefined set of glob pattern. Available presets are `PHP`, `Python`, `Rust`,
`JavaScript`, and `Go`. With `PHP` being the default.

The `--dry-run` option shows the refreshed `.lpv` content without writing any changes to disk.

Existing lines are __preserved__, so custom entries remain untouched while missing preset entries are appended.

#### Tree command

The `tree` command of the lean package validator allows you to inspect the __flat__ `source` and `dist package` structure
of the given project/micro-package. It is __not__ intended for validation use.

``` bash
lean-package-validator tree --src [<directory>]

Package: stolt/lean-package-validator
.
├── .aiassistant/
├── .github/
├── .idea/
├── .phpunit.cache/
├── bin/
├── coverage-reports/
├── example/
├── resources/
├── src/
├── tests/
├── vendor/
├── .editorconfig
├── .gitattributes
├── .gitignore
├── .gitmessage
├── .php-cs-fixer.php
├── CHANGELOG.md
├── LICENSE.md
├── README.md
├── box.json.dist
├── composer.json
├── composer.lock
├── llms.txt
├── lpv-logo.png
├── peck.json
├── phpstan.neon.dist
└── phpunit.xml.dist

11 directories, 16 files
```

``` bash
lean-package-validator tree --dist-package [<directory>]

Package: stolt/lean-package-validator
.
├── bin/
├── resources/
├── src/
└── composer.json

3 directories, 1 file
```

## Utilisation via Composer scripts, cpx, or it's dedicated GitHub Action

To avoid that changes coming from contributions or own modifications slip into release/dist archives, it
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
Another option to use the lean package validator is via [cpx](https://cpx.dev/).

``` bash
cpx stolt/lean-package-validator validate
```

For using a dedicated GitHub Action, have a look at the documentation over [here](https://github.com/raphaelstolt/lean-package-validator-action).

### Included AI skills

This project [includes](./resources/boost/skills) three AI skills focused on managing the `.gitattributes` file for a package:
- __validate__: check whether the current `.gitattributes` content matches the expected export-ignore rules.
- __create__: generate a `.gitattributes` file when it is missing.
- __update__: reconcile an existing `.gitattributes` file with expected export-ignore rules.

### Agentic-friendly output

All commands auto-detect agentic runs, which switches the output from human-readable text to a structured JSON object.
This is useful when integrating the tool into AI workflows or automation pipelines where machine-readable output is preferred.

``` bash
export COPILOT_MODEL=1
lean-package-validator validate [<directory>]
```

``` json
{
    "command": "validate",
    "status": "success",
    "message": "The .gitattributes file is considered valid.",
    "valid": true
}
```

Each response always includes `command`, `status` (`success` or `failure`), and `message` fields. Commands also include additional context-specific fields:

| Command    | Additional fields on success                                                                                         |
|------------|----------------------------------------------------------------------------------------------------------------------|
| `validate` | `valid`, `warnings` (if any), `expected_gitattributes_content` (on failure), `archive_valid`, `unexpected_artifacts` |
| `create`   | `gitattributes_file_path`                                                                                            |
| `update`   | `gitattributes_file_path`                                                                                            |
| `init`     | `lpv_file_path`                                                                                                      |
| `refresh`  | `lpv_file_path`                                                                                                      |
| `tree`     | `package`, `tree`                                                                                                    |

### Spreading the word
You can add the following custom, static [Shields.io](http://shields.io) badge to your repo's `README.md` to mark the package as lean
and spread the word for this tool. It is also _welcome_ to keep the added headers to the modified `.gitattributes` files.

[![Lean dist package](https://img.shields.io/badge/lean-dist%20package-00ffb6.svg?style=flat)](https://github.com/raphaelstolt/lean-package-validator)

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
