<?php
declare(strict_types=1);

namespace PunktDe\Sentry\Flow;

/*
 * This file is part of the PunktDe.Sentry.Flow package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Exception;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Error\WithReferenceCodeInterface;
use Neos\Flow\ObjectManagement\Exception\CannotBuildObjectException;
use Neos\Flow\ObjectManagement\Exception\UnknownObjectException;
use Neos\Flow\ObjectManagement\ObjectManager;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Security\Context;
use Neos\Flow\Utility\Environment;
use Sentry\ClientBuilder;
use Sentry\ClientBuilderInterface;
use Sentry\ClientInterface;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\Transport\TransportFactoryInterface;
use Throwable;
use function Sentry\captureException;
use function Sentry\captureMessage;

/**
 * @Flow\Scope("singleton")
 */
class SentryClient
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
    protected $transportFactoryClass;

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
        $this->dsn = $settings['dsn'] ?: '';
        $this->transportFactoryClass = $settings['transportFactoryClass'] ?? '';
    }

    /**
     * Initialize the sentry client and fatal error handler (shutdown function)
     */
    public function initializeObject(): void
    {
        if (empty($this->dsn)) {
            return;
        }

        $release = $this->settings['release'] ?: '';
        if (empty($release)) {
            $release = $this->getReleaseFromReleaseFile();
        }

        $http_proxy = $this->settings['http_proxy'] ?: '';
        if ($http_proxy === '%env:http_proxy%') {
            $http_proxy = '';
        }

        $clientBuilder = ClientBuilder::create(
            [
                'dsn' => $this->dsn,
                'environment' => $this->settings['environment'] ?: '',
                'release' => $release,
                'http_proxy' => $http_proxy,
                'prefixes' => [FLOW_PATH_ROOT],
                'sample_rate' => $this->settings['sample_rate'] ?: 1,
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

        $this->setCustomTransportIfConfigured($clientBuilder);

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

        if (!$exception instanceof Throwable) {
            return;
        }

        if ($exception instanceof WithReferenceCodeInterface) {
            $extraData['referenceCode'] = $exception->getReferenceCode();
        }

        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use ($exception, $extraData): void {
            $scope->setUser(['username' => $this->getCurrentUsername()]);
            $scope->setTag('code', (string)$exception->getCode());

            foreach ($extraData as $extraDataKey => $extraDataValue) {
                $scope->setExtra($extraDataKey, $extraDataValue);
            }
        });

        captureException($exception);
    }

    /**
     * Send a message to Sentry
     *
     * @param string $message The message to send
     * @param Severity|null $severity (optional) The severity of the message
     * @param array $extraData (optional) Additional data passed to the Sentry sample
     * @param array $tags (optional) Tags that are passed to the Sentry sample
     */
    public function sendMessage(string $message, Severity $severity = null, array $extraData = [], array $tags = []): void
    {
        if (empty($this->dsn) || empty($message)) {
            return;
        }

        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use ($extraData, $tags): void {
            $scope->setUser(['username' => $this->getCurrentUsername()]);

            foreach ($tags as $tagName => $value) {
                $scope->setTag($tagName, $value);
            }

            foreach ($extraData as $extraDataKey => $extraDataValue) {
                $scope->setExtra($extraDataKey, $extraDataValue);
            }
        });

        captureMessage($message, $severity);
    }

    /**
     * @return ClientInterface|null
     */
    public function getSentryClient(): ?ClientInterface
    {
        return SentrySdk::getCurrentHub()->getClient();
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

        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) use ($flowVersion): void {
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
            } catch (Exception $exception) {
            }
        }
        return $flowVersion;
    }

    /**
     * @param ClientBuilderInterface $clientBuilder
     * @throws InvalidConfigurationTypeException
     * @throws CannotBuildObjectException
     * @throws UnknownObjectException
     */
    private function setCustomTransportIfConfigured(ClientBuilderInterface $clientBuilder): void
    {
        if ($this->transportFactoryClass === '') {
            return;
        }

        /** @var TransportFactoryInterface $transportFactory */
        $transportFactory = $this->objectManager->get($this->transportFactoryClass);

        if ($transportFactory instanceof TransportFactoryInterface) {
            $clientBuilder->setTransportFactory($transportFactory);
        }
    }
}
