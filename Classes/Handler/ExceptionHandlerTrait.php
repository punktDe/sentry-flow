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
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use PunktDe\Sentry\Flow\SentryClient;

trait ExceptionHandlerTrait
{

    /**
     * Handles the given exception
     *
     * @param \Throwable $exception The exception object
     * @return void
     */
    public function handleException($exception)
    {
        // Ignore if the error is suppressed by using the shut-up operator @
        if (error_reporting() === 0) {
            return;
        }

        $this->renderingOptions = $this->resolveCustomRenderingOptions($exception);

        $exceptionWasLogged = false;
        if ($this->throwableStorage instanceof ThrowableStorageInterface && isset($this->renderingOptions['logException']) && $this->renderingOptions['logException']) {
            $message = $this->throwableStorage->logThrowable($exception);
            $this->logger->critical($message);
            $exceptionWasLogged = true;
        }

        $this->sendExceptionToSentry($exception);

        if (PHP_SAPI === 'cli') {
            parent::echoExceptionCli($exception, $exceptionWasLogged);
        }

        parent::echoExceptionWeb($exception);
    }


    /**
     * Send an exception to Sentry, but only if the "logException" rendering option is TRUE
     *
     * During compile time there might be missing dependencies, so we need additional safeguards to
     * not cause errors.
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
