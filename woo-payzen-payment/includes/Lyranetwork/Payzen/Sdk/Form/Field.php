<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Form;

/**
 * Class representing a form field to send to the payment gateway.
 */
class Field
{
    /**
     * field name.
     * Matches the HTML input attribute.
     *
     * @var string
     */
    private $name;

    /**
     * field label in English, may be used by translation systems.
     *
     * @var string
     */
    private $label;

    /**
     * field length.
     * Matches the HTML input size attribute.
     *
     * @var int
     */
    private $length;

    /**
     * PCRE regular expression the field value must match.
     *
     * @var string
     */
    private $regex;

    /**
     * Whether the form requires the field to be set (even to an empty string).
     *
     * @var boolean
     */
    private $required;

    /**
     * field value.
     * Null or string.
     *
     * @var string
     */
    private $value = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $label
     * @param string $regex
     * @param boolean $required
     * @param int length
     */
    public function __construct($name, $label, $regex, $required = false, $length = 255)
    {
        $this->name = $name;
        $this->label = $label;
        $this->regex = $regex;
        $this->required = $required;
        $this->length = $length;
    }

    /**
     * Checks the current value.
     *
     * @return boolean
     */
    public function isValid()
    {
        if ($this->value === null && $this->required) {
            return false;
        }

        if ($this->value !== null && ! preg_match($this->regex, $this->value)) {
            return false;
        }

        return true;
    }

    /**
     * Setter for value.
     *
     * @param mixed $value
     * @return boolean
     */
    public function setValue($value)
    {
        $value = ($value === null) ? null : (string) $value;

        if ($value && $this->getLength()) {
            $value = substr($value, 0, $this->getLength());
        }

        // We save value even if invalid but we return "false" as warning.
        $this->value = $value;

        return $this->isValid();
    }

    /**
     * Return the current value of the field.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Is the field required in the payment request ?
     *
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Return the name (HTML attribute) of the field.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Return the english human-readable name of the field.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Return the length of the field value.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * Has a value been set ?
     *
     * @return boolean
     */
    public function isFilled()
    {
        return ! is_null($this->value);
    }
}
