<?php

/**
 * \AppserverIo\WebServer\Modules\Proxy\Balancers\RandomBalancer
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */

namespace AppserverIo\WebServer\Modules\Proxy\Balancers;

/**
 * Balancer which balances based on a random assignment of targets
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */
class RandomBalancer implements BalancerInterface
{

    /**
     * The name of this balancer
     *
     * @var string NAME
     */
    const NAME = 'random';

    /**
     * Will take an array or targeted URLs and return the one which has to be targeted after balance considerations
     *
     * @param array $targetUrls Array of possible target URLs
     *
     * @return string
     */
    public function balance(array $targetUrls)
    {
        return $targetUrls[array_rand($targetUrls)];
    }

    /**
     * Returns the name of this balancer
     *
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }
}
