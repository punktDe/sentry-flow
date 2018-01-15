<?php

namespace PunktDe\Sentry\Flow\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPoint;

/**
 * @Flow\Aspect
 */
class CatchableViewHelperExceptionAspect
{

    /**
     * @Flow\Inject
     * @var \PunktDe\Sentry\Flow\Handler\ErrorHandler
     */
    protected $errorHandler;

    /**
     * @Flow\AfterThrowing("within(Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper) && method(.*->render())")
     * @param JoinPoint $joinPoint
     */
    public function catchException(JoinPoint $joinPoint)
    {
        $exception = $joinPoint->getException();
        $this->errorHandler->handleException($exception);
    }

}
