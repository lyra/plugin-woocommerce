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
 * Class representing a currency, used for converting alpha/numeric ISO codes and float/integer amounts.
 */
class Currency
{
    private $alpha3;
    private $num;
    private $decimals;

    /**
     * @param string $alpha3
     * @param string $num
     * @param int $decimals
     */
    public function __construct($alpha3, $num, $decimals = 2)
    {
        $this->alpha3 = $alpha3;
        $this->num = $num;
        $this->decimals = $decimals;
    }

    /**
     * @param float $float
     * @return int
     */
    public function convertAmountToInteger($float)
    {
        $coef = 10 ** $this->decimals;

        $amount = $float * $coef;
        return (int) (string) $amount; // Cast amount to string (to avoid rounding) than return it as int.
    }

    /**
     * @param int $integer
     * @return float|int
     */
    public function convertAmountToFloat($integer)
    {
        $coef = 10 ** $this->decimals;

        return ((float) $integer) / $coef;
    }

    /**
     * @return string
     */
    public function getAlpha3()
    {
        return $this->alpha3;
    }

    /**
     * @return string
     */
    public function getNum()
    {
        return $this->num;
    }

    /**
     * @return int
     */
    public function getDecimals()
    {
        return $this->decimals;
    }
}
