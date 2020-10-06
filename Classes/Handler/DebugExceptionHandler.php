<?php
namespace PunktDe\Sentry\Flow\Handler;

/*
 * This file is part of the PunktDe.Sentry.Flow package.
 *
 * This package is open source software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


class DebugExceptionHandler extends \Neos\Flow\Error\DebugExceptionHandler
{
    use ExceptionHandlerTrait;
}
