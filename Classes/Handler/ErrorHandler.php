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
use Neos\Flow\ObjectManagement\ObjectManager;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Security\Context;
use Neos\Flow\Utility\Environment;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Transport\TransportInterface;

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
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @var string
     */
    protected $transportClass;

    /**
     * @Flow\Inject
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
        $this->dsn = $settings['dsn'];
        $this->transportClass = $settings['transportClass'] ?? '';
    }

    /**
     * Initialize the sentry client and fatal error handler (shutdown function)
     */
    public function initializeObject(): void
    {
        if (empty($this->dsn)) {
            return;
        }

        $release = $this->settings['release'];
        if (empty($release)) {
            $release = $this->getReleaseFromReleaseFile();
        }

        $clientBuilder = ClientBuilder::create(
            [
                'dsn' => $this->dsn,
                'environment' => $this->settings['environment'],
                'release' => $release,
                'project_root' => FLOW_PATH_ROOT,
                'http_proxy' => $this->settings['http_proxy'],
                'prefixes' => [FLOW_PATH_ROOT],
                'sample_rate' => $this->settings['sample_rate'],
                'in_app_exclude' => [
                    FLOW_PATH_ROOT . '/Packages/Application/PunktDe.Sentry.Flow/Classes/',
                    FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Aop/',
                    FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Error/',
                    FLOW_PATH_ROOT . '/Packages/Framework/Neos.Flow/Classes/Log/',
                    FLOW_PATH_ROOT . '/Packages/Libraries/neos/flow-log/'
                ],
                'default_integrations' => $this->settings['default_integrations'],
                'attach_stacktrace' => $this->settings['attach_stacktrace'],
            ]
        );

        if ($this->transportClass !== '') {
            /** @var TransportInterface $transport */
            $transport = $this->objectManager->get($this->transportClass);
            $clientBuilder->setTransport($transport);
        }

        $client = $clientBuilder->getClient();

        SentrySdk::init()->bindClient($client);

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
     * @return ClientInterface|null
     */
    public function getSentryClient(): ?ClientInterface
    {
        return Hub::getCurrent()->getClient();
    }

    /**
     * @Flow\Signal
     * @return void
     */
    public function emitSentryClientCreated(): void
    {
    }

    /**
     * Set tags on the Sentry client context
     */
    private function setTags(): void
    {
        $flowVersion = $this->determineFlowVersion();

        Hub::getCurrent()->configureScope(static function (Scope $scope) use ($flowVersion): void {
            $scope->setTag('flow_version', $flowVersion);
            $scope->setTag('flow_context', (string)Bootstrap::$staticObjectManager->get(Environment::class)->getContext());
            $scope->setTag('php_version', PHP_VERSION);
            $scope->setTag('php_process_inode', (string)getmyinode());
            $scope->setTag('php_process_pid', (string)getmypid());
            $scope->setTag('php_process_uid', (string)getmyuid());
            $scope->setTag('php_process_gid', (string)getmygid());
            $scope->setTag('php_process_user', get_current_user());
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
     * @return string
     */
    private function determineFlowVersion(): string
    {
        $flowVersion = FLOW_VERSION_BRANCH;

        if ($this->packageManager instanceof PackageManager) {
            try {
                $flowVersion = $this->packageManager->getPackage('Neos.Flow')->getInstalledVersion();
            } catch (\Exception $exception) {
            }
        }
        return $flowVersion;
    }
}
