<?php
declare(strict_types=1);

namespace PunktDe\Sentry\Flow\Aspect;

/*
 * This file is part of the PunktDe.Sentry.Flow package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPoint;

/**
 * @Flow\Aspect
 */
class CatchableViewHelperExceptionAspect
{

    /**
     * @Flow\Inject
     * @var \PunktDe\Sentry\Flow\SentryClient
     */
    protected $errorHandler;

    /**
     * @Flow\AfterThrowing("within(Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper) && method(.*->render())")
     * @param JoinPoint $joinPoint
     */
    public function catchException(JoinPoint $joinPoint): void
    {
        $exception = $joinPoint->getException();
        $this->errorHandler->handleException($exception);
    }
}
