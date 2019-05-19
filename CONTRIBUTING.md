Contributing
============

## Report bugs

Please report bugs and feature requests on
[Wikimedia's Phabricator](https://phabricator.wikimedia.org/project/view/4053/).

## Installation

Prerequisites:

* [PHP](https://www.php.net/)
* [Composer](https://getcomposer.org/)
* [Node.js](https://nodejs.org/en/) with the version specified by [.nvmrc](.nvmrc).
* [Yarn](https://yarnpkg.com/en/)
* A [Wikimedia developer account](https://wikitech.wikimedia.org/wiki/Help:Create_a_Wikimedia_developer_account)
  and access to the [Toolforge environment](https://wikitech.wikimedia.org/wiki/Portal:Toolforge).

Install code and dependencies:

* `git clone https://github.com/MusikAnimal/global-search`
* `cd global-search`
* `composer install`
* `yarn install`
* Establish an SSH tunnel to Toolforge so you can connect to the CloudElastic service.
  The command will be something similar to:

      ssh -L 4711:cloudelastic1004.wikimedia.org:8243 your-username@login.tools.wmflabs.org

* `cp .env.dist .env` then fill out the details:
  * `APP_ENV` - `dev` or `prod`.
  * `APP_SECRET` - Used by Symfony to add more entropy to security-related operations.
    http://nux.net/secret can be used to generate a secure string.
  * `ELASTIC_HOST` - Should be `https://localhost:4711` for your local environment. [.env.dist](.env.dist)
    provides the working value for production.
  * `ELASTIC_INSECURE` - Set to `true` on your local, since HTTPS otherwise won't work.
  * `CACHE_ADAPTER` - Use `apcu` for the best performance. If you don't have or are unable to install
    [APCu](https://www.php.net/manual/en/book.apcu.php) in your environment, you may simply use the `filesystem`.
* `./bin/console server:run` to run the development server.

## Generating assets

Use `yarn encore dev --watch` to compile assets for the development environment and watch for changes.

Before making a pull request, run `yarn encore production` to compile assets for production.
Note the generated assets in `public/build/` must be committed. 

## Tests

Use `composer test` to run the test suite.
