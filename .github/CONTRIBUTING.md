# How to contribute

Thanks for considering to contribute to the `lean package validator`.

## Setting up a development environment

To set up a development environment, please follow the next shown instructions.

```bash
git clone git@github.com:raphaelstolt/lean-package-validator.git
composer install
// implement your changes
composer pre-commit-check
```

Please follow these guidelines:

- All code __MUST__ follow the PSR-2 coding standard. Please see [PSR-2](http://www.php-fig.org/psr/psr-2/) for more details.

- Coding standard compliance __MUST__ be ensured before committing or opening pull requests by running `composer lpv:cs-fix` or `composer lpv:cs-lint` in the root directory of this repository.

- Commits __MUST__ use the provided [commit message template](../.gitmessage), which follows the [rules](http://chris.beams.io/posts/git-commit/) described by Chris Beams. It can be configured via `composer lpv:configure-commit-template` prior to committing.

- All upstreamed contributions __MUST__ use [feature / topic branches](https://git-scm.com/book/en/v2/Git-Branching-Branching-Workflows) to ease merging.

- Please run `composer lpv:pre-commit-check` before opening a pull request.
