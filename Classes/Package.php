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

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Package\Package as BasePackage;

class Package extends BasePackage
{

    /**
     * {@inheritdoc}
     */
    public function boot(Bootstrap $bootstrap)
    {
        $bootstrap->getSignalSlotDispatcher()->connect(Sequence::class, 'afterInvokeStep',
            function ($step, $runlevel) use ($bootstrap) {
                if ($step->getIdentifier() === 'neos.flow:objectmanagement:runtime') {
                    // This triggers the initializeObject method
                    $bootstrap->getObjectManager()->get(Handler\ErrorHandler::class);
                }
            });
    }
}
