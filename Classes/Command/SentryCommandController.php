<?php
namespace PunktDe\Sentry\Flow\Command;

/*
 * This file is part of the PunktDe.Sentry.Flow package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use PunktDe\Sentry\Flow\Exception\SentryTestException;

/**
 * @Flow\Scope("singleton")
 */
class SentryCommandController extends CommandController
{
    /**
     * @Flow\InjectConfiguration(path="dsn")
     * @var string
     */
    protected $sentryDsn = '';

    /**
     * This command triggers a test exception which is send to your sentry server
     *
     * @throws SentryTestException
     */
    public function testCommand()
    {
        $this->outputLine(sprintf("<b>Triggering a test exception which is send to the DSN '%s'</b>\n\n", $this->sentryDsn));

        throw new SentryTestException('This is a sentry test exception.', 1516900282);
    }
}
