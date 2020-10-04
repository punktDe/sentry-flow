<?php
declare(strict_types=1);

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
use PunktDe\Sentry\Flow\Handler\ErrorHandler;
use Sentry\ClientInterface;

/**
 * @Flow\Scope("singleton")
 */
class SentryCommandController extends CommandController
{

    /**
     * @Flow\Inject()
     * @var ErrorHandler
     */
    protected $errorHandler;

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
    public function testCommand(): void
    {

        $sentryClient = $this->errorHandler->getSentryClient();
        if ($sentryClient instanceof ClientInterface) {

            $this->outputLine('<b>Sentry is configured with the following options:</b>');
            $this->outputLine();

            $options = $sentryClient->getOptions();

            $this->output->outputTable([
                ['DSN', $options->getDsn()],
                ['Environment', $options->getEnvironment()],
                ['Release', $options->getRelease()],
                ['Tags', implode(', ',$options->getTags())],
            ], [
                'Option',
                'Value'
            ]);
        } else {
            $this->outputLine('<error>The Sentry client could not be initialized properly. Please check your configuration.</error>');
            $this->sendAndExit(1);
        }

        $this->outputLine();
        $this->outputLine(sprintf("<b>Triggering a test exception which is send to the DSN '%s'</b>\n\n", $this->sentryDsn));

        throw new SentryTestException('This is a sentry test exception.', 1516900282);
    }
}
