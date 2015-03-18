<?php

/**
 * \AppserverIo\WebServer\Modules\Proxy\Rule
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

use AppserverIo\Http\HttpProtocol;
use AppserverIo\Http\HttpResponseStates;
use AppserverIo\Psr\HttpMessage\RequestInterface;
use AppserverIo\Psr\HttpMessage\ResponseInterface;
use AppserverIo\Server\Dictionaries\ServerVars;
use AppserverIo\Server\Interfaces\RequestContextInterface;
use AppserverIo\WebServer\Modules\Proxy\Balancers\RandomBalancer;
use AppserverIo\WebServer\Modules\Rules\Entities\AbstractRule;

/**
 * This class provides an object based representation of a proxy rule
 *
 * @author    Bernhard Wick <bw@appserver.io>
 * @copyright 2015 TechDivision GmbH <info@appserver.io>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/appserver-io/webserver
 * @link      http://www.appserver.io/
 */
class Rule extends AbstractRule
{

    /**
     * The default operand we will check all conditions against if none was given explicitly
     *
     * @const string DEFAULT_OPERAND
     */
    const DEFAULT_BALANCER = '\AppserverIo\WebServer\Modules\Proxy\Balancers\RandomBalancer';

    /**
     * The default operand we will check all conditions against if none was given explicitly
     *
     * @const string DEFAULT_OPERAND
     */
    const DEFAULT_OPERAND = '@$X_REQUEST_URI';

    /**
     *
     */
    const HEADER_CHAIN_SEPARATOR = ',';

    /**
     * Connector which is used to separate different URLs to which incoming request should be relayed
     *
     * @const string TARGET_CONNECTOR
     */
    const TARGET_CONNECTOR = ',';

    /**
     * The balancer to use for this rule
     *
     * @var \AppserverIo\WebServer\Modules\Proxy\Balancers\BalancerInterface $balancer
     */
    protected $balancer;

    /**
     * Array of target URLs incoming requests are relayed to
     *
     * @var array $targetUrls
     */
    protected $targetUrls = array();

    /**
     * Default constructor
     *
     * @param string       $conditionString Condition string e.g. "^_Resources/.*" or "-f{OR}-d{OR}-d@$REQUEST_FILENAME"
     * @param string|array $target          The target to rewrite to, might be null if we should do nothing
     * @param string       $flagString      A flag string which might be added to to the rule e.g. "L" or "C,R"
     */
    public function __construct($conditionString, $target, $flagString)
    {

        // split the target into different URLs (if any)
        $this->targetUrls = explode(self::TARGET_CONNECTOR, $target);

        // invoke parent constructor to fill properties
        parent::__construct($conditionString, $target, $flagString);

        // get the right balancer
        $this->findBalancer();
    }

    /**
     * Initiates the module
     *
     * @param \AppserverIo\Psr\HttpMessage\RequestInterface          $request              The request instance
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface         $response             The response instance
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext       The request's context
     * @param array                                                  $serverBackreferences Server backreferences
     *
     * @return boolean
     *
     * @throws \Exception
     */
    public function apply(RequestInterface $request, ResponseInterface $response, RequestContextInterface $requestContext, array $serverBackreferences)
    {

        // do the load balancing
        $targetUrl = $this->balancer->balance($this->targetUrls);

        // we need a valid URL as a target, otherwise applying the rule makes no sense
        if(filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
            throw new \Exception(sprintf('The target %s is neither a valid URL nor a list of URLs. The rule cannot be applied', $targetUrl));
        }

        // make a CURL call to the configured target
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);

        // forward the headers + the proxy additions
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->collectHeaders($request, $requestContext));

        // other options
        curl_setopt($ch, CURLOPT_USERAGENT, $request->getHeader(HttpProtocol::HEADER_USER_AGENT));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        // make the result known
        $response->appendBodyStream($result);
        // set response state to be dispatched after this without calling other modules process
        $response->setState(HttpResponseStates::DISPATCH);
    }

    /**
     * Will prepare all headers for a proxy request including the original client headers and the ones indicating proxy
     * uage to the origin server
     *
     * @param \AppserverIo\Psr\HttpMessage\RequestInterface          $request              The request instance
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext       The request's context
     *
     * @return array
     */
    protected function collectHeaders(RequestInterface $request, RequestContextInterface $requestContext)
    {
        // prepare the client headers we got already
        $headers = array();
        foreach ($request->getHeaders() as $headerName => $headerValue) {
            $headers[$headerName] = $headerName . HttpProtocol::HEADER_SEPARATOR . $headerValue;
        }

        // prepare the array of headers indicating this proxy use
        $proxyHeaders = array(
            'X-Forwarded-For' => $requestContext->getServerVar(ServerVars::REMOTE_ADDR),
            'X-Forwarded-Host' => $request->getHeader(HttpProtocol::HEADER_HOST),
            'X-Forwarded-Server' => $requestContext->getServerVar(ServerVars::SERVER_NAME)
        );

        // set or update (if already there) the header fields which indicate proxy use
        foreach ($proxyHeaders as $headerName => $headerValue) {
            // iterate all headers set by a proxy and set them based on their current usage
            if ($request->hasHeader($headerName)) {
                $headers[$headerName] = $headerName . HttpProtocol::HEADER_SEPARATOR . $headers[$headerName] . self::HEADER_CHAIN_SEPARATOR . $headerValue;
            } else {
                $headers[$headerName] = $headerName . HttpProtocol::HEADER_SEPARATOR . $headerValue;
            }
        }

        return $headers;
    }

    /**
     * Finds the balancer based on our configured flags
     *
     * @return void
     */
    protected function findBalancer()
    {
        // if there is no BALANCE flag set we use our default balancer
        if (!isset($this->sortedFlags[RuleFlagsDictionary::BALANCE])) {
            $tmp = self::DEFAULT_BALANCER;
            $this->balancer = new $tmp;

        } else {
            // flag is set, determine which balancer has been specified

        }
    }
}
