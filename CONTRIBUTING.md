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
  * `OAUTH_KEY` / `OAUTH_SECRET` - See below for OAuth instructions.
  * `LOGGED_IN_USER` - For development purposes; Set this to any value to simulate login and bypass OAuth.
* `./bin/console server:run` to run the development server.

While developing, you may need to clear the cache to get the latest results from your query.
To do this, use `./bin/console cache:clear` (or `./bin/console c:c` for short).

## OAuth

The OAuth consumer can be created at https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose.
Set the OAuth "callback" URL to `https://tools.wmflabs.org/global-search/oauth_callback`, and check the
"Allow consumer to specify a callback" option.

Similarly for a local environment you'd set the callback URL to `http://localhost:8000/oauth_callback`
(or whatever port the app is running on). However unless you're testing the OAuth functionality itself,
it is easier to set the `LOGGED_IN_USER` option in .env to any value. This will simulate login and you
won't need to bother with creating an OAuth consumer. Note you still will need to click the 'Login' button.

## Generating assets

Use `yarn encore dev --watch` to compile assets for the development environment and watch for changes.

Before making a pull request, run `yarn encore production` to compile assets for production.
Note the generated assets in `public/build/` must be committed. 

## Tests

Use `composer test` to run the test suite.
