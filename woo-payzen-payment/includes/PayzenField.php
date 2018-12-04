<?php
/**
 * PayZen V2-Payment Module version 1.6.2 for WooCommerce 2.x-3.x. Support contact : support@payzen.eu.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @category  Payment
 * @package   Payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @author    Alsacréations (Geoffrey Crofte http://alsacreations.fr/a-propos#geoffrey)
 * @copyright 2014-2018 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html  GNU General Public License (GPL v2)
 */

if (! class_exists('PayzenField', false)) {

    /**
     * Class representing a form field to send to the payment platform.
     */
    class PayzenField
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

            if ($this->value !== null && !preg_match($this->regex, $this->value)) {
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
            // we save value even if invalid but we return "false" as warning
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
}
