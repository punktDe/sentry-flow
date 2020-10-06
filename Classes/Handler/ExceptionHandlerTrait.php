<?php
declare(strict_types=1);

namespace PunktDe\Sentry\Flow\Handler;

/*
 * This file is part of the PunktDe.Sentry.Flow package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use PunktDe\Sentry\Flow\SentryClient;

trait ExceptionHandlerTrait
{

    public function echoExceptionWeb($exception): void
    {
        $this->sendExceptionToSentry($exception);
        parent::echoExceptionWeb($exception);
    }

    public function echoExceptionCli(\Throwable $exception): void
    {
        $this->sendExceptionToSentry($exception);
        parent::echoExceptionCli($exception);
    }

    /**
     * Send an exception to Sentry, but only if the "logException" rendering option is TRUE
     *
     * During compile time there might be missing dependencies, so we need additional safeguards to
     * not cause errors.
     *
     * @param \Throwable $throwable
     */
    protected function sendExceptionToSentry(\Throwable $throwable): void
    {
        if (!Bootstrap::$staticObjectManager instanceof ObjectManagerInterface) {
            return;
        }

        $options = $this->resolveCustomRenderingOptions($throwable);
        if (isset($options['logException']) && $options['logException']) {
            try {
                /** @var SentryClient $errorHandler */
                $errorHandler = Bootstrap::$staticObjectManager->get(SentryClient::class);
                if ($errorHandler !== null) {
                    $errorHandler->handleException($throwable);
                }
            } catch (\Exception $throwable) {
                // Quick'n dirty workaround to catch exception with the error handler is called during compile time
            }
        }
    }
}
