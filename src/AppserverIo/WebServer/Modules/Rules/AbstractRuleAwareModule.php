<?php

/**
 * \AppserverIo\WebServer\Modules\Rules\AbstractRuleAwareModule
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

namespace AppserverIo\WebServer\Modules\Rules;

use AppserverIo\Psr\HttpMessage\RequestInterface;
use AppserverIo\Psr\HttpMessage\ResponseInterface;
use AppserverIo\WebServer\Interfaces\HttpModuleInterface;
use AppserverIo\Server\Dictionaries\ModuleHooks;
use AppserverIo\Server\Exceptions\ModuleException;
use AppserverIo\Server\Dictionaries\ServerVars;
use AppserverIo\Server\Dictionaries\EnvVars;
use AppserverIo\Server\Interfaces\RequestContextInterface;
use AppserverIo\Server\Interfaces\ServerContextInterface;
use AppserverIo\Server\Dictionaries\ModuleVars;

/**
 * Abstract base module which enables child modules to store their rules and use common backreferences
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */
abstract class AbstractRuleAwareModule implements HttpModuleInterface
{

    /**
     * Server variables we support and need
     *
     * @var array $supportedServerVars
     */
    protected $supportedServerVars = array();

    /**
     * SSL environment variables we support and need
     *
     * @var array $supportedEnvVars
     */
    protected $supportedEnvVars = array();

    /**
     * This array will hold all locations (e.g.
     * /example/websocket) we ever encountered in our live time.
     * It will provide a mapping to the $configs array, as several locations can share one config
     * (e.g. a "global" .htaccess or nginx config).
     *
     * @var array<string> $locations
     */
    protected $locations = array();

    /**
     * All rules we have to check (sorted by requested URL)
     *
     * @var array $rules
     */
    protected $rules = array();

    /**
     * The rules as we got it from our basic configuration
     *
     * @var array $configuredRules
     */
    protected $configuredRules = array();

    /**
     * This array will hold all values which one would suspect as part of the PHP $_SERVER array.
     * As it will be filled from different sources we better keep it as a flat array here so we can
     * easily search for any value we need.
     * Filling and refilling will take place in init() and process() as we need it.
     *
     * @var array $serverVars
     */
    protected $serverBackreferences = array();

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

            $this->supportedServerVars = array(
                'headers' => array(
                    ServerVars::HTTP_USER_AGENT,
                    ServerVars::HTTP_REFERER,
                    ServerVars::HTTP_COOKIE,
                    ServerVars::HTTP_FORWARDED,
                    ServerVars::HTTP_HOST,
                    ServerVars::HTTP_PROXY_CONNECTION,
                    ServerVars::HTTP_ACCEPT
                )
            );

            $this->supportedEnvVars = EnvVars::all();

        } catch (\Exception $e) {
            // Re-throw as a ModuleException
            throw new ModuleException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Implements module logic for given hook
     *
     * @param \AppserverIo\Psr\HttpMessage\RequestInterface          $request        A request object
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface         $response       A response object
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext A requests context instance
     * @param int                                                    $hook           The current hook to process logic for
     *
     * @return bool
     * @throws \AppserverIo\Server\Exceptions\ModuleException
     */
    public function process(RequestInterface $request, ResponseInterface $response, RequestContextInterface $requestContext, $hook)
    {
        // In php an interface is, by definition, a fixed contract. It is immutable.
        // So we have to declare the right ones afterwards...

        // if false hook is coming do nothing
        if (ModuleHooks::REQUEST_POST !== $hook) {
            return;
        }

        // set member ref for request context
        $this->requestContext = $requestContext;

        // We have to throw a ModuleException on failure, so surround the body with a try...catch block
        try {
            $requestUrl = $requestContext->getServerVar(ServerVars::HTTP_HOST) . $requestContext->getServerVar(ServerVars::X_REQUEST_URI);

            if (! isset($this->rules[$requestUrl])) {
                // Reset the $serverBackreferences array to avoid mixups of different requests
                $this->serverBackreferences = array();

                // Resolve all used backreferences which are NOT linked to the query string.
                // We will resolve query string related backreferences separately as we are not able to cache them
                // as easily as, say, the URI
                // We also have to resolve all the changes rules in front of us made, so build up the backreferences
                // IN the loop.
                $this->fillContextBackreferences();
                $this->fillHeaderBackreferences($request);

                // Get the rules as the array they are within the config.
                // We have to also collect any volatile rules which might be set on request base.
                // We might not even get anything, so prepare our rules accordingly
                $volatileRules = $this->getVolatileRules($requestContext);

                // Build up the complete ruleset, volatile rules up front
                $rules = array_merge($volatileRules, $this->configuredRules);
                $this->rules[$requestUrl] = array();

                // Only act if we got something
                if (is_array($rules)) {
                    // Convert the rules to our internally used objects
                    foreach ($rules as $rule) {
                        // Add the rule as a Rule object
                        $rule = $this->newRule($rule['condition'], $rule['target'], $rule['flag']);
                        $rule->resolve($this->serverBackreferences);
                        $this->rules[$requestUrl][] = $rule;
                    }
                }
            }

            // Iterate over all rules, resolve vars and apply the rule (if needed)
            foreach ($this->rules[$requestUrl] as $rule) {
                // Check if the rule matches, and if, apply the rule
                if ($rule->matches()) {
                    // Apply the rule. If apply() returns TRUE this means this was the last rule to process
                    if ($rule->apply($request, $response, $requestContext, $this->serverBackreferences) === true) {
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            // Re-throw as a ModuleException
            throw new ModuleException($e);
        }
    }

    /**
     * Will get all volatile rules applying to the current request
     *
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext The context of the current request
     *
     * @return array
     */
    abstract protected function getVolatileRules(RequestContextInterface $requestContext);

    /**
     * Will create an instance of a rule appropriate for the module in use
     *
     * @param string $condition  The condition string configured for this rule
     * @param string $target     The configured target
     * @param string $flagString The string of configured flags
     *
     * @return \AppserverIo\WebServer\Modules\Rules\Entities\AbstractRule
     */
    abstract protected function newRule($condition, $target, $flagString);

    /**
     * Will fill the header variables into our pre-collected $serverVars array
     *
     * @param \AppserverIo\Psr\HttpMessage\RequestInterface $request The request instance
     *
     * @return void
     */
    protected function fillHeaderBackreferences(RequestInterface $request)
    {
        $headerArray = $request->getHeaders();

        // Iterate over all header vars we know and add them to our serverBackreferences array
        foreach ($this->supportedServerVars['headers'] as $supportedServerVar) {
            // As we got them with another name, we have to rename them, so we will not have to do this on the fly
            $tmp = strtoupper(str_replace('HTTP', 'HEADER', $supportedServerVar));
            if (@isset($headerArray[constant("AppserverIo\\Psr\\HttpMessage\\Protocol::$tmp")])) {
                $this->serverBackreferences['$' . $supportedServerVar] = $headerArray[constant("AppserverIo\\Psr\\HttpMessage\\Protocol::$tmp")];

                // Also create for the "dynamic" substitution syntax
                $this->serverBackreferences['$' . constant("AppserverIo\\Psr\\HttpMessage\\Protocol::$tmp")] = $headerArray[constant("AppserverIo\\Psr\\HttpMessage\\Protocol::$tmp")];
            }
        }
    }

    /**
     * Something we might need within conditions or target definitions are server and environment variables.
     * So add them.
     *
     * @throws \AppserverIo\Server\Exceptions\ModuleException
     *
     * @return void
     */
    protected function fillContextBackreferences()
    {
        // get local ref of request context
        $requestContext = $this->getRequestContext();

        // Iterate over all server variables and add them to the backreference array
        foreach ($requestContext->getServerVars() as $varName => $serverVar) {
            // Pre-fill the value
            $this->serverBackreferences['$' . $varName] = $serverVar;
        }

        // Do the same for environment variables
        foreach ($requestContext->getEnvVars() as $varName => $envVar) {
            // Pre-fill the value
            $this->serverBackreferences['$' . $varName] = $envVar;
        }
    }
}
