<?php

namespace PunktDe\Sentry\Flow\Handler;

use PunktDe\Sentry\Flow\Handler\ExceptionHandlerTrait;

class ProductionExceptionHandler extends \Neos\Flow\Error\ProductionExceptionHandler
{

    use ExceptionHandlerTrait;

}
