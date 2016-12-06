# Travis Integration for SilverStripe Modules
[![Build Status](https://travis-ci.org/silverstripe-labs/silverstripe-travis-support.svg?branch=master)](https://travis-ci.org/silverstripe-labs/silverstripe-travis-support)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-travis-support/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-travis-support/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-travis-support/badges/build.png?b=master)](https://scrutinizer-ci.com/g/silverstripe-labs/silverstripe-travis-support/build-status/master)
[![codecov.io](https://codecov.io/github/silverstripe-labs/silverstripe-travis-support/coverage.svg?branch=master)](https://codecov.io/github/silverstripe-labs/silverstripe-travis-support?branch=master)

![codecov.io](https://codecov.io/github/silverstripe-labs/silverstripe-travis-support/branch.svg?branch=master)

## Introduction

[Travis](http://travis-ci.org) is an open source platform for [continuous integration](http://en.wikipedia.org/wiki/Continuous_integration), 
which mostly means running your unit tests every time you commit to a codebase.
The platform is free for open source projects, and integrates nicely with Github.
Since each SilverStripe module needs to be tested within a SilverStripe project,
there's a bit of setup work required on top of the standard [Composer](http://getcomposer.org) dependency management.

The scripts allow you to test across multiple branches, and rewrite the `composer.json` to match dependencies.
The scripts will test your module against multiple core releases, as well as multiple databases (if supported).

Why bother? Because it shows your users that you care about the quality of your codebase,
and gives them a clear picture of the current status of it. And it helps you manage the complexity
of coding against multiple databases, SilverStripe core releases and other dependencies.

Haven't written unit tests yet? Then it's high time you get started with the [SilverStripe Testing Guide](http://doc.silverstripe.org/framework/en/topics/testing/).

## Composer Setup

Since this script works based on [Composer](http://getcomposer.org),
you need to add some metadata to your module. Copy the `composer.json` into the root directory
of your module and adjust it to suit your needs. If you have mulitple branches in your module
which support different core releases, then commit the file to each of those branches. 
Ensure you set the right [dependency versions](http://getcomposer.org/doc/01-basic-usage.md#package-versions).

Don't have branches? You really should, so your users can trust in the stability of releases,
and have clear guidance on dependencies. Read on [semver.org](http://semver.org/) for details on version numbering.

Abbreviated `composer.json` for a branch supporting SS 2.4 only:

  ```json
  {
    "name":"some-vendor-prefix/my-awesome-module",
    ...
    'require': {"silverstripe/framework": "2.4.*", "silverstripe/cms": "2.4.*"}
  }
  ```
  
Abbreviated `composer.json` for a branch supporting SS 3.0 and newer:

  ```json
  {
    "name":"some-vendor-prefix/my-awesome-module",
    ...
    'require': {"silverstripe/framework": "~3.0", "silverstripe/cms": "~3.0"}
  }
  ```
  
Now commit those files to the various module branches, and register them on [Packagist](http://packagist.org)
so they're discoverable by Composer.

## Travis Setup

The free [Travis](http://travis-ci.com/) CI service is configured by placing a hidden
`.travis.yml` file into the root of your module
(read me about the [Travis YML format](http://about.travis-ci.org/docs/user/build-configuration)).

Here's an example `.travis.yml`:

  ```yml
language: php 
php: 
 - 5.3

env:
  matrix:
   - DB=MYSQL CORE_RELEASE=3.0
   - DB=MYSQL CORE_RELEASE=3.1
   - DB=MYSQL CORE_RELEASE=master
   - DB=PGSQL CORE_RELEASE=master

matrix:
  include:
    - php: 5.4
      env: DB=MYSQL CORE_RELEASE=master

before_script:
 - phpenv rehash
 - composer self-update
 - git clone git://github.com/silverstripe-labs/silverstripe-travis-support.git ~/travis-support
 - php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss
 - cd ~/builds/ss

script: 
 - vendor/bin/phpunit <yourmodule>/tests/
```

When getting set up, to avoid repeatedly pushing to trigger the service hook, you should [save time by linting your configuration with Travis WebLint](https://lint.travis-ci.org/).

Now adjust the `<yourmodule>` path in `.travis.yml`, in the example above it would be `my-awesome-module`.
Adjust the supported PHP versions, SS core versions and databases in `.travis.yml` (read more about the [Travis PHP config](http://about.travis-ci.org/docs/user/languages/php/)). Consider [blacklisting or whitelisting](http://about.travis-ci.org/docs/user/build-configuration/#The-Build-Matrix) builds to keep the number of individual builds to a reasonable level.

The sample file above will run the following builds:

 * DB=MYSQL CORE_RELEASE=3.0, php: 5.3
 * DB=MYSQL CORE_RELEASE=3.1, php: 5.3
 * DB=MYSQL CORE_RELEASE=master, php: 5.3
 * DB=PGSQL CORE_RELEASE=master, php: 5.3
 * DB=MYSQL CORE_RELEASE=master, php: 5.4

After you committed the files, as a final step you'll want to enable your module on travis-ci.org.
The first builds should start within a few minutes.

As a bonus, you can include build status images in your README to promote the fact that
your module values quality and does continuous integration.

## Adding extra modules

If you need to add extra modules during setup, that aren't explicitly included in the module
composer requirements, you can use the `--require` parameter.

E.g.

    php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss --require silverstripe/behat-extension
    
You can also specify multiple modules by either comma separating the names, or
by the addition of multiple ``--require`` flags. Each name can also be suffixed
with `:<version>` to add a version dependency.

E.g.

    php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss --require silverstripe/behat-extension:dev-master --require silverstripe-cms:4.0.x-dev

or equivalently

    php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss --require silverstripe/behat-extension:dev-master,silverstripe-cms:4.0.x-dev

## PDO DB Connectors

Many database connectors support connection via PDO. If you would like to include PDO support
you can also add the PDO=1 environment variable.

Note that this feature is only supported in SilverStripe 3.2 or later and will be ignored in 3.1 or below

```yml
env:
  matrix:
    - DB=MYSQL CORE_RELEASE=3.2 PDO=1
    - DB=MYSQL CORE_RELEASE=3.2
  ```

## Working with Pull Requests

The logic relies on pulling in different core releases based on the `CORE_RELEASE` constant.
This can lead to inconsistencies where pull requests rely on other branches,
for example where a pull request for the `cms` module relies on an equivalent in the `framework` module.
While there's no clean way to tell Travis which branches to use, temporary modifications
to `travis.yml` can help prove a build is passing with the right dependencies.

 * Add custom fork `repositories` to your module's `composer.json`. They'll get pulled up into the root `composer.json` automatically
 * Set the `CORE_RELEASE` environment variable to the branch name on your fork.
 * Create branches with the same name on both `cms` and `framework` modules
 * Either create a branch on `installer`, or set a different `CORE_INSTALLER_RELEASE` environment variable
 * Set a `CORE_ALIAS` environment variable in order to satisfy 
   [constrains](https://getcomposer.org/doc/articles/aliases.md) (e.g. `4.0.x-dev`)

Note that these `.travis.yml` changes in your fork are temporary,
and need to be reverted before the pull request will be merged.
Unfortunately Travis CI doesn't support per-build configuration settings
outside of version control.

## Github Rate Limitation

Composer heavily relies on github's APIs for retrieving repository info and downloading archives.
Github has a low rate limitation for unauthenticated requests. This means depending
on how often your builds run (and the amount of executed API requests per build),
your build can fail because of these side effects.

This script supports a `GITHUB_API_TOKEN` value. If set, it'll write it to a global composer configuration
([details](http://blog.simplytestable.com/creating-and-using-a-github-oauth-token-with-travis-and-composer/)).
It can optionally be encrypted through Travis' [secure environment variables](http://about.travis-ci.org/docs/user/build-configuration/#Secure-environment-variables).

In order to activate the configuration, add an entry to `env.global` in your `.travis.yml`:

```yml
env:
  global:
    - GITHUB_API_TOKEN=<token>
  matrix:
    - ...
```

## Behat and Selenium

The scripts also allow behaviour testing through [Behat](http://behat.org).
The easiest way to get this going is through a locally running Selenium server
and PHP's built-in webserver. Here's a sample setup:

```yml
language: php 

matrix:
  include:
    - php: 5.3
      env: DB=MYSQL CORE_RELEASE=3.1
    - php: 5.4
      env: DB=MYSQL CORE_RELEASE=3.1 BEHAT_TEST=1

before_script:
 - composer self-update
 - phpenv rehash
 - git clone -b tmp/travis-artifacts git://github.com/silverstripe-labs/silverstripe-travis-support.git ~/travis-support
 - "if [ \"$BEHAT_TEST\" = \"\" ]; then php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss; fi"
 - "if [ \"$BEHAT_TEST\" = \"1\" ]; then php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss --require silverstripe/behat-extension; fi"
 - cd ~/builds/ss
 - php ~/travis-support/travis_setup_selenium.php --if-env BEHAT_TEST
 - php ~/travis-support/travis_setup_php54_webserver.php --if-env BEHAT_TEST

script: 
 - "if [ \"$BEHAT_TEST\" = \"\" ]; then vendor/bin/phpunit framework/tests; fi"
 - "if [ \"$BEHAT_TEST\" = \"1\" ]; then vendor/bin/behat @framework; fi"
```

## Artifacts Upload

Since Travis builds are stateless, you can't inspect anything apart from the actual build log
after the build has finished. This is an issue for larger files like server logs, and of course for images.

You can use https://github.com/travis-ci/artifacts for this purpose, allowing upload to Amazon S3.
Since Behat creates screenshots of any failed test step already, this is a useful addition to any
Behat script. The `behat.yml` created through `travis_setup_selenium.php` is already set up to
save its screenshots into `artifacts/screenshots/`.

```yml
language: php 

env:
  global:
    - ARTIFACTS_REGION=us-east-1
    - ARTIFACTS_BUCKET=silverstripe-travis-artifacts
    - secure: "..." # Encrypted ARTIFACTS_KEY
    - secure: "..." # Encrypted ARTIFACTS_SECRET

matrix:
  include:
    - ...

before_script:
 - ...

script: 
 - ...

after_script:
 - php ~/travis-support/travis_upload_artifacts.php --if-env BEHAT_TEST,ARTIFACTS_BUCKET,ARTIFACTS_KEY,ARTIFACTS_SECRET --target-path artifacts/$TRAVIS_REPO_SLUG/$TRAVIS_BUILD_ID/$TRAVIS_JOB_ID --artifacts-base-url https://s3.amazonaws.com/$ARTIFACTS_BUCKET/
```

## Troubleshooting

### Testing travis_setup.php locally

While its not 100% accurate, you can get pretty close to reproducing Travis' behaviour on your own environment.
Just look at the CLI output from a previous travis build to get started. Here's an example
on building a specific commit on the `1.0` branch of the `translatable` module:

  ```bash
  export TRAVIS_BRANCH=1.0
  export TRAVIS_COMMIT=dd792af2fba119cfa22423203dd9f2e70676e651
  export TRAVIS_REPO_SLUG=silverstripe/silverstripe-translatable
  export DB=MYSQL
  export CORE_RELEASE=3.0
  git clone --depth=50 --branch=1.0 git://github.com/silverstripe/silverstripe-translatable.git silverstripe/silverstripe-translatable
  cd silverstripe/silverstripe-translatable
  git checkout -qf dd792af2fba119cfa22423203dd9f2e70676e651
  git clone git://github.com/silverstripe-labs/silverstripe-travis-support.git ~/travis-support
  php ~/travis-support/travis_setup.php --source `pwd` --target ~/builds/ss
  cd ~/builds/ss
  phpunit translatable/tests
  ```
Note: Each SilverStripe module only works as a subfolder in the context of a SilverStripe project,
and requires at least the SilverStripe framework. So we need to ensure the plain module
checkout which Travis performs by defaults get rewritten to this.
