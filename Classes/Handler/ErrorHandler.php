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
use Neos\Flow\Package\PackageManagerInterface;
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
    protected $release;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings): void
    {
        $this->dsn = $settings['dsn'] ?? '';
        $this->environment = $settings['environment'] ?? '';
        $this->release = $settings['release'] ?? '';
    }

    /**
     * Initialize the raven client and fatal error handler (shutdown function)
     */
    public function initializeObject()
    {
        if (empty($this->dsn)) {
            return;
        }

        if (empty($this->release)) {
            $this->release = $this->getReleaseFromReleaseFile();
        }

        \Sentry\init([
            'dsn' => $this->dsn,
            'environment' => $this->environment,
            'release' => $this->release,
            'project_root' => FLOW_PATH_ROOT,
            'prefixes' => [FLOW_PATH_ROOT],
            'sample_rate' => 1,
            'in_app_exclude' => [
                FLOW_PATH_ROOT . '/Packages/Application/Flownative.Sentry/Classes/',
                FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Aop/',
                FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Error/',
                FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Log/',
                FLOW_PATH_ROOT . '/Packages/Libraries/neos/flow-log/'
            ],
            'default_integrations' => false,
            'attach_stacktrace' => true
        ]);

        $client = Hub::getCurrent()->getClient();
        if (!$client) {
            return;
        }

        $this->setTags();

        $this->emitSentryClientCreated();
    }

    /**
     * Explicitly handle an exception, should be called from an exception handler (in Flow or Fusion)
     *
     * @param object $exception The exception to capture
     * @param array $extraData Additional data passed to the Sentry sample
     */
    public function handleException($exception, array $extraData = []): void
    {
        if (empty($this->dsn)) {
            return;
        }

        if (!$exception instanceof \Throwable) {
            return;
        }

        if ($exception instanceof WithReferenceCodeInterface) {
            $extraData['referenceCode'] = $exception->getReferenceCode();
        }

        Hub::getCurrent()->configureScope(function (Scope $scope) use ($exception, $extraData): void {
            $scope->setUser(['username' => $this->getCurrentUsername()]);
            $scope->setTag('code', (string)$exception->getCode());

            foreach ($extraData as $extraDataKey => $extraDataValue) {
                $scope->setExtra($extraDataKey, $extraDataValue);
            }
        });

        \Sentry\captureException($exception);
    }

    /**
      * Set tags on the Sentry client context
     */
    private function setTags(): void
    {
        $flowVersion = FLOW_VERSION_BRANCH;
        if ($this->packageManager) {
            $flowPackage = $this->packageManager->getPackage('Neos.Flow');
            $flowVersion = $flowPackage->getInstalledVersion();
        }
        Hub::getCurrent()->configureScope(static function (Scope $scope) use ($flowVersion): void {
            $scope->setTag('flow_version', $flowVersion);
            $scope->setTag('flow_context', (string)Bootstrap::$staticObjectManager->get(Environment::class)->getContext());
            $scope->setTag('php_version', PHP_VERSION);
            $scope->setTag('php_process_inode',(string)getmyinode());
            $scope->setTag('php_process_pid',(string)getmypid());
            $scope->setTag('php_process_uid',(string)getmyuid());
            $scope->setTag('php_process_gid',(string)getmygid());
            $scope->setTag('php_process_user',get_current_user());
        });
    }

    /**
     * @return string
     */
    private function getCurrentUsername(): string
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
    private function getReleaseFromReleaseFile(): string
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
