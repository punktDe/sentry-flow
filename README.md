# [PunktDe.Sentry.Flow](https://github.com/punktDe/sentry-flow)

[![Latest Stable Version](https://poser.pugx.org/punktDe/sentry-flow/v/stable)](https://packagist.org/packages/punktDe/sentry-flow) [![Total Downloads](https://poser.pugx.org/punktDe/sentry-flow/downloads)](https://packagist.org/packages/punktDe/sentry-flow) [![License](https://poser.pugx.org/punktDe/sentry-flow/license)](https://packagist.org/packages/punktDe/sentry-flow)

This is a Sentry client package for the Flow framework.

Have a look at https://sentry.io for more information about Sentry.

## Installation

```
$ composer require punktde/sentry-flow
```

### Compatibilty matrix

| Flow Sentry Client | Flow           | Sentry SDK | Sentry Server |
|--------------------|----------------|------------|---------------|
| ^1.0               | ^4.0           | ^1.0       | *             |
| ^2.0               | ^5.0           | ^1.0       | *             |
| ^3.0               | ^5.0           | ^2.0       | *             |
| ^4.0               | ^5.0, ^6.0     | ^3.0       | >= v20.6.0    |

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
If you need to use a custom transport e.g. to write the sentry reports to a file, you must implement the `Sentry\TransportInterface`:

```php
<?php
declare(strict_types=1);

namespace Vendor\Package\Sentry\Transport;

use Sentry\Event;
use Sentry\Exception\JsonException;
use Sentry\Transport\TransportInterface;
use Sentry\Util\JSON;

class FileWriterTransport implements TransportInterface
{
    /**
     * @param Event $event
     *
     * @return string|null Returns the ID of the event or `null` if it failed to be sent
     *
     * @throws JsonException
     */
    public function send(Event $event): ?string
    {
        if (file_put_contents('My\Path\And\FileName', JSON::encode($event)) !== false) {
            return $event->getId();
        }
        return null;
    }
}
```

Then you configure the class to be used:

```yaml
PunktDe:
  Sentry:
    Flow:
      transportClass: '\Vendor\Package\Sentry\Transport\FileWriterTransport'
```

## Usage

Sentry will log all exceptions that have the rendering option `logException` enabled. This can be enabled or disabled
by status code or exception class according to the Flow configuration.
