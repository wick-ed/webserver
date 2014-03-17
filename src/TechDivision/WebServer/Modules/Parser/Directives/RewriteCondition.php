<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @category   Webserver
 * @package    TechDivision_WebServer
 * @subpackage Modules
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 *             Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 */

namespace TechDivision\WebServer\Modules\Parser\Directives;

use TechDivision\WebServer\Interfaces\DirectiveInterface;

/**
 * TechDivision\WebServer\Modules\Parser\Directives\RewriteCondition
 *
 * This class acts as a generic implementation of a rewrite condition which should be usable by apache and nginx alike
 *
 * @category   Webserver
 * @package    TechDivision_WebServer
 * @subpackage Modules
 * @author     Bernhard Wick <b.wick@techdivision.com>
 * @copyright  2014 TechDivision GmbH - <info@techdivision.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 *             Open Software License (OSL 3.0)
 * @link       http://www.techdivision.com/
 *
 * TODO implement condition flags
 */
class RewriteCondition implements DirectiveInterface
{
    /**
     * The allowed values for the $types member
     *
     * @var array<string> $allowedTypes
     */
    protected $allowedTypes = array();

    /**
     * All possible modifiers aka flags
     *
     * @var array<string> $flagMapping
     */
    protected $allowedModifiers = array();

    /**
     * Possible additions to the known PCRE regex. These additions get used by htaccess notation only.
     *
     * @var array<string> $htaccessAdditions
     */
    protected $htaccessAdditions = array();

    /**
     * The type of this condition
     *
     * @var string $type
     */
    protected $type;

    /**
     * The value to check with the given action
     *
     * @var string $operand
     */
    protected $operand;

    /**
     * How the operand has to be checked, this will hold the needed action as a string and cannot be
     * processed automatically.
     *
     * @var string $action
     */
    protected $action;

    /**
     * This is a combination of the operand and the action to perform, wrapped in an eval-able string
     *
     * @var string $operation
     */
    protected $operation;

    /**
     * Modifier which should be used to integrate things like apache flags and others
     *
     * @var string $modifier
     */
    protected $modifier;

    /**
     * When the ornext|OR flag is used we have to set this member to true
     *
     * @var boolean $orCombined
     */
    protected $orCombined;

    /**
     * At least in the apache universe we can negate the logical meaning with a "!"
     *
     * @var boolean $isNegated
     */
    protected $isNegated;

    /**
     * Default constructor
     *
     * @param string $type     Type of this condition directive
     * @param string $operand  The value to check with the given action
     * @param string $action   How the operand has to be checked, this will hold the needed action
     * @param string $modifier Modifier which should be used to integrate things like apache flags and others
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($type = 'regex', $operand = '', $action = '', $modifier = '')
    {
        // Fill the default values for our members here
        $this->allowedTypes = array('regex', 'check');
        $this->htaccessAdditions = array(
            '!' => '!',
            '<' => 'strcmp(\'#1\', \'#2\') < 0',
            '>' => 'strcmp(\'#1\', \'#2\') > 0',
            '=' => 'strcmp(\'#1\', \'#2\') == 0',
            '-d' => 'is_dir(\'#1\')',
            '-f' => 'is_file(\'#1\')',
            '-s' => 'is_file(\'#1\') && filesize(\'#1\') > 0',
            '-l' => 'is_link(\'#1\')',
            '-x' => 'is_executable(\'#1\')',
            '-F',
            '-U'
        );
        $this->allowedModifiers = array('[NC]', '[OR]', '[NV]', '[nocase]', '[ornext]', '[novary]');

        // We do not negate by default, nor do we combine with the following condition via "or"
        $this->isNegated = false;
        $this->orCombined = false;

        // Check if the passed type is valid
        if (!isset(array_flip($this->allowedTypes)[$type])) {

            throw new \InvalidArgumentException($type . ' is not an allowed condition type.');
        }

        // Check if the passed modifier is valid (or empty)
        if (!isset(array_flip($this->allowedModifiers)[$modifier]) && !empty($modifier)) {

            throw new \InvalidArgumentException($modifier . ' is not an allowed condition modifier.');
        }

        // Fill the instance using the array logic
        $this->fillFromArray(array(__CLASS__, $operand, $action, $modifier));
    }

    /**
     * Getter for the $type member
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Getter for the $operand member
     *
     * @return string
     */
    public function getOperand()
    {
        return $this->operand;
    }

    /**
     * Getter for the $operation member
     *
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Getter for the $modifier member
     *
     * @return string
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * Will return true if this conditions is or-combined with the one behind it, false if not
     *
     * @return boolean
     */
    public function isOrCombined()
    {
        return $this->orCombined;
    }

    /**
     * Will resolve the directive's parts by substituting placeholders with the corresponding backreferences
     *
     * @param array $backreferences The backreferences used for resolving placeholders
     *
     * @return void
     */
    public function resolve(array $backreferences)
    {
        // Separate the keys from the values so we can use them in str_replace
        $backreferenceHolders = array_keys($backreferences);
        $backreferenceValues = array_values($backreferences);

        // Substitute the backreferences in our operand and operation
        $this->operand = str_replace($backreferenceHolders, $backreferenceValues, $this->operand);
        $this->operation = str_replace($backreferenceHolders, $backreferenceValues, $this->operation);
    }

    /**
     * Will return true if the condition is true, false if not
     *
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public function matches()
    {
        if ($this->isNegated) {

            return eval('if (!' . $this->operation . '){return true;}');

        } else {

            return eval('if (' . $this->operation . '){return true;}');
        }
    }

    /**
     * Will collect all backreferences based on regex typed conditions
     *
     * @param integer $offset The offset to count from, used so no integer based directive will be overwritten
     *
     * @return array
     */
    public function getBackreferences($offset)
    {
        $backreferences = array();
        $matches = array();
        if ($this->type === 'regex') {

            preg_match('`' . $this->action . '`', $this->operand, $matches);

            // Unset the first find of our backreferences, so we can use it automatically
            unset($matches[0]);
        }

        // Iterate over all our found matches and give them a fine name
        foreach ($matches as $key => $match) {

            $backreferences['%' . (string)($offset + $key)] = $match;
        }

        return $backreferences;
    }

    /**
     * Will fill an empty directive object with vital information delivered via an array.
     * This is mostly useful as an interface for different parsers
     *
     * @param array $parts The array to extract information from
     *
     * @return null
     * @throws \InvalidArgumentException
     */
    public function fillFromArray(array $parts)
    {
        // Array should be 3 or 4 pieces long
        $failureMessage = 'Could not process line ' . implode($parts, ' ');
        if (count($parts) < 3 || count($parts) > 4) {

            throw new \InvalidArgumentException($failureMessage);
        }

        // If the array has 4 parts we have to check if the last part is a valid modifier
        if (!isset(array_flip($this->allowedModifiers)[$parts[3]])) {

            throw new \InvalidArgumentException($failureMessage);
        }

        // Fill operand and action to preserve it
        $this->operand = $parts[1];
        $this->action = $parts[2];

        // We have to handle the different modifiers
        $this->applyModifiers($parts[3]);

        // Fill the instance, "regex" is the default type
        $this->type = 'regex';

        // Preset the operation with a regex check
        $this->operation = 'preg_match(\'`' . $this->action . '`\', \'' . $this->operand . '\') === 1';

        // Are we able to find any of the additions htaccess syntax offers?
        foreach ($this->htaccessAdditions as $addition => $realAction) {

            if (strpos($parts[2], $addition) !== false) {

                // Check if we have to negate
                if ($addition === '!') {

                    $this->isNegated = true;
                    continue;
                }

                // We have a "check" type
                $this->type = 'check';

                // We have to extract the needed parts of our operation and refill it into our operation string
                $additionPart = substr($parts[2], 1);
                $this->operation = str_replace(array('#1', '#2'), array($parts[1], $additionPart), $realAction);
                break;
            }
        }

        // Get the modifier, if there is any
        if (isset($parts[3])) {

            $this->modifier = $parts[3];
        }
    }

    /**
     * <TODO FUNCTION DESCRIPTION>
     *
     * @param string $modifier The modifier we have to apply
     *
     * @return void
     */
    protected function applyModifiers($modifier)
    {
        // If we got the "no case" modifier we have to lowercase both action and operand
        if ($modifier === '[NC]' || $modifier === '[nocase]') {

            $this->operand = strtolower($this->operand);
            $this->action = strtolower($this->action);

        } elseif ($modifier === '[OR]' || $modifier === '[ornext]') {
            // If we got the "or next condition" modifier we have to tell $this

            $this->orCombined = true;

        } elseif ($modifier === '[NV]' || $modifier === '[novary]') {
            // If we got the "no vary"

            $this->operand = strtolower($this->operand);
            $this->action = strtolower($this->action);
        }
    }
}