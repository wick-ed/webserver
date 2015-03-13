<?php

/**
 * \AppserverIo\WebServer\Modules\Proxy\RuleFlagsDictionary
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

namespace AppserverIo\WebServer\Modules\Proxy;

use AppserverIo\WebServer\Modules\Rules\Dictionaries\RuleFlags;

/**
 * Class RuleFlagsDictionary
 *
 * This file is a dictionary for rule flags.
 * Defines constant for flags we might use within the rule's flag field
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */
class RuleFlagsDictionary extends RuleFlags
{

    /**
     * Make proxy fall back to the next target if applied connection times out
     *
     * @var string
     */
    const FALLBACK = 'F';
}
