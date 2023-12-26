<?php
/**
 * Copyright © Lyra Network and contributors.
 * This file is part of PayZen plugin for WooCommerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @author    Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @copyright Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL v2)
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Payzen_Subscriptions_Loader
{
    private static $handlers = array(
        'wc-subscriptions' => 'Payzen_WC_Subscriptions_Subscriptions_Handler',
        'subscriptio' => 'Payzen_Subscriptio_Subscriptions_Handler'
    );

    public static function getInstance($handler)
    {
        if (! key_exists($handler, self::$handlers)) { // No valid subscriptions handler provided.
            if ($handler === 'disabled') {
                // Removed handler provided, force using WC Subscriptions.
                $handler = 'wc-subscriptions';
            } else {
                return null;
            }
        }

        include_once 'payzen-subscriptions-handler-interface.php';
        include_once "payzen-$handler-subscriptions-handler.php";

        $class = self::$handlers[$handler];
        return new $class();
    }
}
