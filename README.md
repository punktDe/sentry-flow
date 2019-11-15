# [PunktDe.Sentry.Flow](https://github.com/punktDe/sentry-flow)

[![Latest Stable Version](https://poser.pugx.org/punktDe/sentry-flow/v/stable)](https://packagist.org/packages/punktDe/sentry-flow) [![Total Downloads](https://poser.pugx.org/punktDe/sentry-flow/downloads)](https://packagist.org/packages/punktDe/sentry-flow) [![License](https://poser.pugx.org/punktDe/sentry-flow/license)](https://packagist.org/packages/punktDe/sentry-flow)

This is a Sentry client package for the Flow framework.

Have a look at https://sentry.io for more information about Sentry.

## Installation

```
$ composer require punktde/sentry-flow
```

## Configuration

Add the following to your `Settings.yaml` and replace the `dsn` setting with your project DSN (API Keys in your Sentry project):

```yaml
PunktDe:
  Sentry:
    Flow:
      dsn: 'https://public_key@your-sentry-server.com/project-id'
```

You can also set the Sentry Environment to filter your exceptions by e.g. dev-/staging-/live-system. 
Set the env variable `SENTRY_ENVIRONMENT` or add your value to your `Settings.yaml`: 

```yaml
PunktDe:
  Sentry:
    Flow:
      environment: 'live'
```

Furthermore you can set the Sentry Release version to help to identifiy with which release an error occurred the first time.
By default, a file which is starting with the name `RELEASE_` is searched and the values after `RELEASE_` is used for Sentry.
Alternatively you can override the filebased release number and set an environment variable `SENTRY_RELEASE` or add your value to your `Settings.yaml`: 

```yaml
PunktDe:
  Sentry:
    Flow:
      release: '5.0.3'
```     


## Usage

Sentry will log all exceptions that have the rendering option `logException` enabled. This can be enabled or disabled
by status code or exception class according to the Flow configuration.
