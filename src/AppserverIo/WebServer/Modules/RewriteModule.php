<?php

/**
 * \AppserverIo\WebServer\Modules\RewriteModule
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

namespace AppserverIo\WebServer\Modules;

use AppserverIo\Server\Dictionaries\ModuleHooks;
use AppserverIo\Server\Exceptions\ModuleException;
use AppserverIo\Server\Dictionaries\ServerVars;
use AppserverIo\Server\Dictionaries\EnvVars;
use AppserverIo\Server\Interfaces\RequestContextInterface;
use AppserverIo\Server\Interfaces\ServerContextInterface;
use AppserverIo\Server\Dictionaries\ModuleVars;
use AppserverIo\WebServer\Modules\Rewrite\Rule;
use AppserverIo\WebServer\Modules\Rules\AbstractRuleAwareModule;

/**
 * Class RewriteModule
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */
class RewriteModule extends AbstractRuleAwareModule
{

    /**
     * The server's context instance which we preserve for later use
     *
     * @var \AppserverIo\Server\Interfaces\ServerContextInterface $serverContext $serverContext
     */
    protected $serverContext;

    /**
     * The requests's context instance
     *
     * @var \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext The request's context instance
     */
    protected $requestContext;

    /**
     *
     * @var array $dependencies The modules we depend on
     */
    protected $dependencies = array();

    /**
     * Defines the module name
     *
     * @var string
     */
    const MODULE_NAME = 'rewrite';

    /**
     * Defines the SCRIPT_URL constant's name we keep track of
     *
     * @var string
     */
    const SCRIPT_URL = 'SCRIPT_URL';

    /**
     * Defines the SCRIPT_URI constant's name we keep track of
     *
     * @var string
     */
    const SCRIPT_URI = 'SCRIPT_URI';

    /**
     * Initiates the module
     *
     * @param \AppserverIo\Server\Interfaces\ServerContextInterface $serverContext The server's context instance
     *
     * @return bool
     * @throws \AppserverIo\Server\Exceptions\ModuleException
     */
    public function init(ServerContextInterface $serverContext)
    {
        // We have to throw a ModuleException on failure, so surround the body with a try...catch block
        try {
            // Save the server context for later re-use
            $this->serverContext = $serverContext;

            // Register our dependencies
            $this->dependencies = array(
                'virtualHost'
            );

            parent::init($serverContext);

            // Get the rules as the array they are within the config
            // We might not even get anything, so prepare our rules accordingly
            $this->configuredRules = $this->serverContext->getServerConfig()->getRewrites();
        } catch (\Exception $e) {
            // Re-throw as a ModuleException
            throw new ModuleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Will get all volatile rules applying to the current request
     *
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext The context of the current request
     *
     * @return array
     */
     protected function getVolatileRules(RequestContextInterface $requestContext)
     {
         $volatileRewrites = array();
         if ($requestContext->hasModuleVar(ModuleVars::VOLATILE_REWRITES)) {
             $volatileRewrites = $requestContext->getModuleVar(ModuleVars::VOLATILE_REWRITES);
         }

         return $volatileRewrites;
     }

    /**
     * Will create an instance of a rule appropriate for the module in use
     *
     * @param string $condition  The condition string configured for this rule
     * @param string $target     The configured target
     * @param string $flagString The string of configured flags
     *
     * @return \AppserverIo\WebServer\Modules\Rules\Entities\AbstractRule
     */
     protected function newRule($condition, $target, $flagString)
     {
        return new Rule($condition, $target, $flagString);
     }

    /**
     * Returns an array of module names which should be executed first
     *
     * @return array The array of module names
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * Returns the module name
     *
     * @return string The module name
     */
    public function getModuleName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Return's the request context instance
     *
     * @return \AppserverIo\Server\Interfaces\RequestContextInterface
     */
    public function getRequestContext()
    {
        return $this->requestContext;
    }

    /**
     * Prepares the module for upcoming request in specific context
     *
     * @return void
     */
    public function prepare()
    {
        // nothing to prepare for this module
    }
}
