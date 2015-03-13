<?php

/**
 * \AppserverIo\WebServer\Modules\Rewrite\Rule
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

namespace AppserverIo\WebServer\Modules\Rewrite;

use AppserverIo\Psr\HttpMessage\Protocol;
use AppserverIo\Http\HttpResponseStates;
use AppserverIo\Psr\HttpMessage\ResponseInterface;
use AppserverIo\Server\Dictionaries\ServerVars;
use AppserverIo\Server\Interfaces\RequestContextInterface;
use AppserverIo\WebServer\Modules\Rules\Entities\AbstractRule;
use AppserverIo\WebServer\Modules\Rules\Entities\Condition;

/**
 * This class provides an object based representation of a rewrite rule, including logic for testing, applying
 * and handling conditions.
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
    const DEFAULT_OPERAND = '@$X_REQUEST_URI';

    /**
     * Default constructor
     *
     * @param string       $conditionString Condition string e.g. "^_Resources/.*" or "-f{OR}-d{OR}-d@$REQUEST_FILENAME"
     * @param string|array $target          The target to rewrite to, might be null if we should do nothing
     * @param string       $flagString      A flag string which might be added to to the rule e.g. "L" or "C,R"
     */
    public function __construct($conditionString, $target, $flagString)
    {
        // Set the raw string properties and append our default operand to the condition string
        $this->conditionString = $conditionString;
        $conditionString .= $this->getDefaultOperand();
        $this->target = $target;
        $this->flagString = $flagString;

        // Get the sorted flags, should be easy to break up
        $this->sortedFlags = $this->sortFlags($this->flagString);

        // Set our default values here
        $this->allowedTypes = array(
            'relative',
            'absolute',
            'url'
        );
        $this->matchingBackreferences = array();

        // filter the condition string using our regex, but first of all we will append the default operand
        $conditionPieces = array();
        preg_match_all('`(.*?)@(\$[0-9a-zA-Z_]+)`', $conditionString, $conditionPieces);
        // The first index is always useless, unset it to avoid confusion
        unset($conditionPieces[0]);

        // Conditions are kind of sorted now, we can split them up into condition actions and their operands
        $conditionActions = $conditionPieces[1];
        $conditionOperands = $conditionPieces[2];

        // Iterate over the condition piece arrays, trim them and build our array of sorted condition objects
        for ($i = 0; $i < count($conditionActions); $i ++) {
            // Trim whatever we got here as the string might be a bit dirty
            $actionString = trim($conditionActions[$i], self::CONDITION_OR_DELIMITER . '|' . self::CONDITION_AND_DELIMITER);

            // Collect all and-combined pieces of the conditionstring
            $andActionStringPieces = explode(self::CONDITION_AND_DELIMITER, $actionString);

            // Iterate through them and build up conditions or or-combined condition groups
            foreach ($andActionStringPieces as $andActionStringPiece) {
                // Everything is and-combined (plain array) unless combined otherwise (with a "{OR}" symbol)
                // If we find an or-combination we will make a deeper array within our sorted condition array
                if (strpos($andActionStringPiece, self::CONDITION_OR_DELIMITER) !== false) {
                    // Collect all or-combined conditions into a separate array
                    $actionStringPieces = explode(self::CONDITION_OR_DELIMITER, $andActionStringPiece);

                    // Iterate over the pieces we found and create a condition for each of them
                    $entry = array();
                    foreach ($actionStringPieces as $actionStringPiece) {
                        // Get a new condition instance
                        $entry[] = new Condition($conditionOperands[$i], $actionStringPiece);
                    }
                } else {
                    // Get a new condition instance
                    $entry = new Condition($conditionOperands[$i], $andActionStringPiece);
                }

                $this->sortedConditions[] = $entry;
            }
        }
    }

    /**
     * Initiates the module
     *
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext       The request's context
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface         $response             The response instance
     * @param array                                                  $serverBackreferences Server backreferences
     *
     * @throws \InvalidArgumentException
     *
     * @return boolean
     */
    public function apply(RequestContextInterface $requestContext, ResponseInterface $response, array $serverBackreferences)
    {

        // First of all we have to resolve the target string with the backreferences of the matching condition
        // Separate the keys from the values so we can use them in str_replace
        // And also mix in the server's backreferences for good measure
        $this->matchingBackreferences = array_merge($this->matchingBackreferences, $serverBackreferences);
        $backreferenceHolders = array_keys($this->matchingBackreferences);
        $backreferenceValues = array_values($this->matchingBackreferences);

        // If we got a target map (flag "M") we have to resolve the target string we have to use first
        // The following checks will be treated as an additional condition
        if (array_key_exists(RuleFlagsDictionary::MAP, $this->sortedFlags) && ! empty($this->sortedFlags[RuleFlagsDictionary::MAP])) {
            // Get our map key for better readability
            $mapKey = str_replace(array_keys($this->matchingBackreferences), $this->matchingBackreferences, $this->sortedFlags[RuleFlagsDictionary::MAP]);

            // Still here? That sounds good. Get the needed target string now
            if (isset($this->target[$mapKey])) {
                $this->target = $this->target[$mapKey];
            } else {
                // Empty string, we will do nothing with this rule
                $this->target = '';

                // Also clear any L-flag we might find, we could not find what we are looking for so we should not end
                if (array_key_exists(RuleFlagsDictionary::LAST, $this->sortedFlags)) {
                    unset($this->sortedFlags[RuleFlagsDictionary::LAST]);
                }
            }
        }

        // Back to our rule...
        // If the target string is empty we do not have to do anything
        if (! empty($this->target)) {
            // Just make sure that you check for the existence of the query string first, as it might not be set
            $queryFreeRequestUri = $requestContext->getServerVar(ServerVars::X_REQUEST_URI);
            if ($requestContext->hasServerVar(ServerVars::QUERY_STRING)) {
                $queryFreeRequestUri = str_replace('?' . $requestContext->getServerVar(ServerVars::QUERY_STRING), '', $queryFreeRequestUri);

                // Set the "redirect" query string as a backup as we might change the original
                $requestContext->setServerVar('REDIRECT_QUERY_STRING', $requestContext->getServerVar(ServerVars::QUERY_STRING));
            }
            $requestContext->setServerVar('REDIRECT_URL', $queryFreeRequestUri);

            // Substitute the backreferences in our operation
            $this->target = str_replace($backreferenceHolders, $backreferenceValues, $this->target);

            // We have to find out what type of rule we got here
            if (is_readable($this->target)) {
                // We have an absolute file path!
                $this->type = 'absolute';

                // Set the REQUEST_FILENAME path
                $requestContext->setServerVar(ServerVars::REQUEST_FILENAME, $this->target);
            } elseif (filter_var($this->target, FILTER_VALIDATE_URL) !== false) {
                // We have a complete URL!
                $this->type = 'url';
            } else {
                // Last but not least we might have gotten a relative path (most likely)
                // Build up the REQUEST_FILENAME from DOCUMENT_ROOT and X_REQUEST_URI (without the query string)
                $this->type = 'relative';

                // Setting the X_REQUEST_URI for internal communication
                // Requested uri always has to begin with a slash
                $this->target = '/' . ltrim($this->target, '/');
                $requestContext->setServerVar(ServerVars::X_REQUEST_URI, $this->target);

                // Only change the query string if we have one in our target string
                if (strpos($this->target, '?') !== false) {
                    $requestContext->setServerVar(ServerVars::QUERY_STRING, substr(strstr($this->target, '?'), 1));
                }
            }

            // do we have to make a redirect?
            // if so we have to have to set the status code accordingly and dispatch the response
            if (array_key_exists(RuleFlagsDictionary::REDIRECT, $this->sortedFlags)) {
                $this->prepareRedirect($requestContext, $response);
            }

            // Lets tell them that we successfully made a redirect
            $requestContext->setServerVar(ServerVars::REDIRECT_STATUS, '200');
        }
        // If we got the "LAST"-flag we have to end here, so return false
        if (array_key_exists(RuleFlagsDictionary::LAST, $this->sortedFlags)) {
            return false;
        }

        // Still here? That sounds good
        return true;
    }

    /**
     * Will prepare a response for a redirect.
     * This includes setting the new target, the appropriate status code and dispatching it to break the
     * module chain
     *
     * @param \AppserverIo\Server\Interfaces\RequestContextInterface $requestContext The request's context
     * @param \AppserverIo\Psr\HttpMessage\ResponseInterface         $response       The response instance to be prepared
     *
     * @return void
     */
    protected function prepareRedirect($requestContext, ResponseInterface $response)
    {
        // if we got a specific status code we have to filter it and apply it if possible
        $statusCode = 301;
        $proposedStatusCode = $this->sortedFlags[RuleFlagsDictionary::REDIRECT];
        if (is_numeric($proposedStatusCode) && $proposedStatusCode >= 300 && $proposedStatusCode < 400) {
            $statusCode = $proposedStatusCode;
        }

        // there might be work to be done depending on whether or not we got a complete URL
        if ($this->type === 'relative') {
            $newTarget = $requestContext->getServerVar(ServerVars::REQUEST_SCHEME);
            $newTarget .= '://';
            $newTarget .= $requestContext->getServerVar(ServerVars::HTTP_HOST);
            $this->target = $newTarget . $this->getTarget();
        }

        // set enhance uri to response
        $response->addHeader(Protocol::HEADER_LOCATION, $this->target);
        // send redirect status
        $response->setStatusCode($statusCode);
        // set response state to be dispatched after this without calling other modules process
        $response->setState(HttpResponseStates::DISPATCH);
    }
}
