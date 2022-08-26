<?php
/**
 * Copyright © Lyra Network and contributors.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network and contributors
 * @license   See COPYING.md for license details.
 */

namespace Lyranetwork\Payzen\Sdk\Refund;

interface Processor
{
    /**
     * Action to do in case of error during refund process.
     *
     */
    public function doOnError($errorCode, $message);

    /**
     * Action to do after sucessful refund process.
     *
     */
    public function doOnSuccess($operationResponse, $operationType);

    /**
     * Action to do after failed refund process.
     *
     */
    public function doOnFailure($errorCode, $message);

    /**
     * Log informations.
     *
     */
    public function log($message, $level);

    /**
     * Translate the given message.
     *
     */
    public function translate($message);
}
