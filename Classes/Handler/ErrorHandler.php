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

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Error\WithReferenceCodeInterface;
use Neos\Flow\Security\Context;
use Neos\Flow\Utility\Environment;
use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * @Flow\Scope("singleton")
 */
class ErrorHandler
{

    /**
     * @var string
     */
    protected $dsn;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->dsn = $settings['dsn'] ?? '';
        $this->environment = $settings['environment'] ?? '';
    }

    /**
     * Initialize the raven client and fatal error handler (shutdown function)
     */
    public function initializeObject()
    {
        \Sentry\init(['dsn' => $this->dsn]);

        $options = Hub::getCurrent()->getClient()->getOptions();
        $options->setEnvironment($this->environment);
        $options->setRelease($this->getReleaseFromRelaseFile());
        $options->setProjectRoot(FLOW_PATH_ROOT);
        $options->setPrefixes([FLOW_PATH_ROOT]);

        $this->setTags();

        $this->emitSentryClientCreated();
    }

    /**
     * Explicitly handle an exception, should be called from an exception handler (in Flow or TypoScript)
     *
     * @param object $exception The exception to capture
     * @param array $extraData Additional data passed to the Sentry sample
     */
    public function handleException($exception, array $extraData = []): void
    {

        if (!$exception instanceof \Throwable) {
            return;
        }

        if ($exception instanceof WithReferenceCodeInterface) {
            $extraData['referenceCode'] = $exception->getReferenceCode();
        }

        Hub::getCurrent()->configureScope(function (Scope $scope) use ($exception, $extraData): void {
            $scope->setUser(['username' => $this->getCurrentUserName()]);
            $scope->setTag('code', (string)$exception->getCode());

            foreach ($extraData as $extraDataKey => $extraDataValue) {
                $scope->setExtra($extraDataKey, $extraDataValue);
            }
        });

        \Sentry\captureException($exception);
    }

    /**
     * Set tags on the raven context
     */
    protected function setTags(): void
    {
        Hub::getCurrent()->configureScope(function (Scope $scope): void {
            $scope->setTag('php_version', 'phpversion()');
            $scope->setTag('flow_context', (string)Bootstrap::$staticObjectManager->get(Environment::class)->getContext());
            $scope->setTag('flow_version', FLOW_VERSION_BRANCH);
        });
    }

    /**
     * @return string
     */
    protected function getCurrentUserName(): string
    {
        $objectManager = Bootstrap::$staticObjectManager;
        /** @var Context $securityContext */
        $securityContext = $objectManager->get(Context::class);

        $userName = '';

        if ($securityContext->isInitialized()) {
            $account = $securityContext->getAccount();
            if ($account !== null) {
                $userName = $account->getAccountIdentifier();
            }
        }

        return $userName;
    }

    /**
     * @return string
     */
    private function getReleaseFromRelaseFile(): string
    {
        $filenames = scandir(FLOW_PATH_ROOT);
        $release = 'Unknown Release';

        foreach ($filenames as $filename) {
            if (strpos($filename, 'RELEASE_') === 0) {
                $release = substr($filename, 8);
                break;
            }
        }

        return $release;
    }

    /**
     * @Flow\Signal
     * @return void
     */
    public function emitSentryClientCreated()
    {
    }
}
