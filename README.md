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

## Usage

Sentry will log all exceptions that have the rendering option `logException` enabled. This can be enabled or disabled
by status code or exception class according to the Flow configuration.
