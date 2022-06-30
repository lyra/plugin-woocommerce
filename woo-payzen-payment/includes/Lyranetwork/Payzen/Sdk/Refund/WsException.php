<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Refund;

class WsException extends \Exception
{
    protected $code;

    /**
     * @param message[optional]
     * @param code[optional]
     */
    public function __construct($message, $code = null)
    {
        parent::__construct($message, null);
        $this->code = $code;
    }
}
