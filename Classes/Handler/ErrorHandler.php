<?php

namespace PunktDe\Sentry\Flow\Handler;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;

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
     * @var \Raven_Client
     */
    protected $client;

    /**
     * Initialize the raven client and fatal error handler (shutdown function)
     */
    public function initializeObject()
    {
        $client = new \Raven_Client($this->dsn);
        $errorHandler = new \Raven_ErrorHandler($client, true);
        $errorHandler->registerShutdownFunction();
        $this->client = $client;

        $this->setTags();
        $this->setEnvironment();
        $this->setRelease();
        $this->setAppPath();
        $this->setPrefixes();

        $this->emitSentryClientCreated($client);
    }

    /**
     * Explicitly handle an exception, should be called from an exception handler (in Flow or TypoScript)
     *
     * @param object $exception The exception to capture
     * @param array $extraData Additional data passed to the Sentry sample
     */
    public function handleException($exception, array $extraData = array())
    {
        if (!$this->client instanceof \Raven_Client) {
            return;
        }

        if (!$exception instanceof \Throwable) {
            return;
        }

        $this->setUser();

        $tags = array('code' => $exception->getCode());
        if ($exception instanceof \Neos\Flow\Exception) {
            $extraData['referenceCode'] = $exception->getReferenceCode();
        }

        $this->client->captureException($exception, array(
                'message' => $exception->getMessage(),
                'extra' => $extraData,
                'tags' => $tags
            )
        );
    }

    /**
     * Set tags on the raven context
     */
    protected function setTags()
    {
        $objectManager = Bootstrap::$staticObjectManager;
        /** @var \Neos\Flow\Utility\Environment $environment */
        $environment = $objectManager->get('Neos\Flow\Utility\Environment');

        $tags = array(
            'php_version' => phpversion(),
            'flow_context' => (string)$environment->getContext(),
            'flow_version' => FLOW_VERSION_BRANCH
        );

        $this->client->tags_context($tags);
    }

    /**
     * Set user information on the raven context
     */
    protected function setUser()
    {
        $objectManager = Bootstrap::$staticObjectManager;
        /** @var \Neos\Flow\Security\Context $securityContext */
        $securityContext = $objectManager->get('Neos\Flow\Security\Context');

        $userContext = array();

        if ($securityContext->isInitialized()) {
            $account = $securityContext->getAccount();
            if ($account !== null) {
                $userContext['username'] = $account->getAccountIdentifier();
            }
        }

        if ($userContext !== array()) {
            $this->client->user_context($userContext);
        }
    }

    protected function setEnvironment()
    {
        $this->client->setEnvironment($this->environment);
    }

    protected function setRelease()
    {
        $filenames = scandir(FLOW_PATH_ROOT);
        $release = '';
        foreach ($filenames as $filename) {
            if (strpos($filename, 'RELEASE_') === 0) {
                $release = substr($filename, 8);
                break;
            }
        }
        $this->client->setRelease($release);
    }

    protected function setAppPath()
    {
        $this->client->setAppPath(FLOW_PATH_ROOT);
    }

    protected function setPrefixes()
    {
        $this->client->setPrefixes([FLOW_PATH_ROOT]);
    }

    /**
     * @param array $settings
     */
    public function injectSettings(array $settings)
    {
        $this->dsn = isset($settings['dsn']) ? $settings['dsn'] : '';
        $this->environment = isset($settings['environment']) ? $settings['environment'] : '';
    }

    /**
     * @return \Raven_Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @Flow\Signal
     * @param $client
     * @return void
     */
    public function emitSentryClientCreated($client)
    {
    }

}
