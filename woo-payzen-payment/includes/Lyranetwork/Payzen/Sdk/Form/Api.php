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
 * Utility class for managing parameters checking, internationalization, signature building and more.
 */
class Api
{
    const ALGO_SHA1 = 'SHA-1';
    const ALGO_SHA256 = 'SHA-256';
    const PATTERN_SHORT_PHP_VERSION = '#^\d+(\.\d+)*#';

    public static $SUPPORTED_ALGOS = array(
        self::ALGO_SHA1,
        self::ALGO_SHA256
    );

    /**
     * The list of encodings supported by the API.
     *
     * @var array[string]
     */
    public static $SUPPORTED_ENCODINGS = array(
        'UTF-8',
        'ASCII',
        'Windows-1252',
        'ISO-8859-15',
        'ISO-8859-1',
        'ISO-8859-6',
        'CP1256'
    );

    /**
     * Generate a trans_id.
     * To be independent from shared/persistent counters, we use the number of 1/10 seconds since midnight
     * which has the appropriatee format (000000-899999) and has great chances to be unique.
     *
     * @param int $timestamp
     * @return string the generated trans_id
     */
    public static function generateTransId($timestamp = null)
    {
        if (! $timestamp) {
            $timestamp = time();
        }

        $parts = explode(' ', microtime());
        $id = ($timestamp + $parts[0] - strtotime('today 00:00')) * 10;
        $id = sprintf('%06d', $id);

        return $id;
    }

    /**
     * Returns an array of languages accepted by the payment gateway.
     *
     * @return array[string][string]
     */
    public static function getSupportedLanguages()
    {
        return array(
            'de' => 'German',
            'en' => 'English',
            'zh' => 'Chinese',
            'es' => 'Spanish',
            'fr' => 'French',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'sv' => 'Swedish',
            'tr' => 'Turkish'
        );
    }

    /**
     * Returns true if the entered language (ISO code) is supported.
     *
     * @param string $lang
     * @return boolean
     */
    public static function isSupportedLanguage($lang)
    {
        $supportedLanguages = self::getSupportedLanguages();
        return isset($supportedLanguages[strtolower($lang)]);
    }

    /**
     * Return the list of currencies recognized by the payment gateway.
     *
     * @return array[int][Lyranetwork\Payzen\Sdk\Form\Currency]
     */
    public static function getSupportedCurrencies()
    {
        $currencies = array(
            array('ARS', '032', 2), array('AUD', '036', 2), array('BRL', '986', 2), array('CAD', '124', 2),
            array('CHF', '756', 2), array('CNY', '156', 2), array('COP', '170', 2), array('CZK', '203', 2),
            array('DKK', '208', 2), array('EUR', '978', 2), array('GBP', '826', 2), array('HKD', '344', 2),
            array('HUF', '348', 2), array('IDR', '360', 0), array('INR', '356', 2), array('JPY', '392', 0),
            array('KHR', '116', 0), array('KRW', '410', 0), array('KWD', '414', 3), array('MAD', '504', 2),
            array('MXN', '484', 2), array('MYR', '458', 2), array('NOK', '578', 2), array('NZD', '554', 2),
            array('PEN', '604', 2), array('PHP', '608', 2), array('PLN', '985', 2), array('RUB', '643', 2),
            array('SEK', '752', 2), array('SGD', '702', 2), array('THB', '764', 2), array('TND', '788', 3),
            array('TRY', '949', 2), array('TWD', '901', 2), array('USD', '840', 2), array('XOF', '952', 0),
            array('XPF', '953', 0), array('ZAR', '710', 2)
        );

        $supported_currencies = array();

        foreach ($currencies as $currency) {
            $supported_currencies[] = new Currency($currency[0], $currency[1], $currency[2]);
        }

        return $supported_currencies;
    }

    /**
     * Return a currency from its 3-letters ISO code.
     *
     * @param string $alpha3
     * @return \Lyranetwork\Payzen\Sdk\Form\Currency|null
     */
    public static function findCurrencyByAlphaCode($alpha3)
    {
        $list = self::getSupportedCurrencies();
        foreach ($list as $currency) {
            /**
             * @var \Lyranetwork\Payzen\Sdk\Form\Currency $currency
             */
            if ($currency->getAlpha3() === $alpha3) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Returns a currency form its numeric ISO code.
     *
     * @param int $numeric
     * @return \Lyranetwork\Payzen\Sdk\Form\Currency|null
     */
    public static function findCurrencyByNumCode($numeric)
    {
        $list = self::getSupportedCurrencies();
        foreach ($list as $currency) {
            /**
             * @var \Lyranetwork\Payzen\Sdk\Form\Currency $currency
             */
            if ($currency->getNum() == $numeric) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Return a currency from its 3-letters or numeric ISO code.
     *
     * @param string $code
     * @return \Lyranetwork\Payzen\Sdk\Form\Currency|null
     */
    public static function findCurrency($code)
    {
        $list = self::getSupportedCurrencies();
        foreach ($list as $currency) {
            /**
             * @var \Lyranetwork\Payzen\Sdk\Form\Currency $currency
             */
            if ($currency->getNum() === $code || $currency->getAlpha3() === $code) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Returns currency numeric ISO code from its 3-letters code.
     *
     * @param string $alpha3
     * @return string|null
     */
    public static function getCurrencyNumCode($alpha3)
    {
        $currency = self::findCurrencyByAlphaCode($alpha3);
        return ($currency instanceof Currency) ? $currency->getNum() : null;
    }

    /**
     * Returns an array of card types accepted by the payment gateway.
     *
     * @return array[string][string]
     */
    public static function getSupportedCardTypes()
    {
        return array(
            'CB' => 'CB', 'E-CARTEBLEUE' => 'e-Carte Bleue', 'MAESTRO' => 'Maestro', 'MASTERCARD' => 'Mastercard',
            'VISA' => 'Visa', 'VISA_ELECTRON' => 'Visa Electron', 'VPAY' => 'V PAY', 'AMEX' => 'American Express',
            'ACCORD_STORE' => 'Cartes Enseignes Partenaires', 'ACCORD_STORE_SB' => 'Cartes Enseignes Partenaires (sandbox)',
            'ALINEA' => 'Carte myalinea', 'ALINEA_CDX' => 'Carte Cadeau Alinéa', 'ALINEA_CDX_SB' => 'Carte Cadeau Alinéa (sandbox)',
            'ALINEA_SB' => 'Carte myalinea (sandbox)', 'ALIPAY' => 'Alipay', 'ALLOBEBE_CDX' => 'Carte Cadeau Allobébé',
            'ALLOBEBE_CDX_SB' => 'Carte Cadeau Allobébé (sandbox)', 'ALMA' => 'Alma en 1 fois', 'ALMA_10X' => 'Alma en 10 fois',
            'ALMA_12X' => 'Alma en 12 fois', 'ALMA_2X' => 'Alma en 2 fois', 'ALMA_3X' => 'Alma en 3 fois',
            'ALMA_4X' => 'Alma en 4 fois', 'APETIZ' => 'Apetiz', 'APPLE_PAY' => 'Apple Pay',
            'AUCHAN' => 'Carte Auchan', 'AUCHAN_SB' => 'Carte Auchan (sandbox)', 'AURORE-MULTI' => 'Cpay Aurore',
            'BANCONTACT' => 'Bancontact Mistercash', 'BIZUM' => 'Bizum', 'BIZZBEE_CDX' => 'Carte Cadeau Bizzbee',
            'BIZZBEE_CDX_SB' => 'Carte Cadeau Bizzbee (sandbox)', 'BOULANGER' => 'Carte b+',
            'BOULANGER_SB' => 'Carte b+ (sandbox)', 'BRICE_CDX' => 'Carte Cadeau Brice', 'BRICE_CDX_SB' => 'Carte Cadeau Brice (sandbox)',
            'BUT' => 'But', 'CABAL' => 'Cabal', 'CARNET' => 'Carnet', 'CASINO' => 'Banque Casino', 'CA_DO_CARTE' => 'CA DO Carte',
            'CDGP' => 'Carte Privilège', 'CDISCOUNT' => 'CDiscount', 'CHQ_DEJ' => 'Chèque Déjeuner', 'COF3XCB' => 'Cofinoga 3 fois CB',
            'COF3XCB_SB' => 'Cofinoga 3 fois CB Sandbox', 'COFIDIS_3X_BE' => 'Cofidis en 3 fois', 'COFIDIS_3X_FR' => 'Cofidis en 3 fois',
            'COFIDIS_4X_ES' => 'Cofidis en 4 vencimientos', 'COFIDIS_4X_FR' => 'Cofidis en 4 fois', 'COFIDIS_DFPAY_FR' => 'Cofidis Pay Later',
            'COFIDIS_LOAN_BE' => 'Cofidis en 6-12-18 fois', 'COFIDIS_LOAN_CB' => 'Cofidis en 5-12 fois', 'COFIDIS_LOAN_BE' => 'Cofidis en 6-12-18 fois',
            'COFIDIS_LOAN_ES' => 'Cofidis en 6-12-24 vencimientos', 'COFIDIS_LOAN_FR' => 'Amortissable', 'COFIDIS_LOAN_IT' => 'Cofidis Pagodil',
            'COFIDIS_PAY_FR' => 'Cofidis Pay', 'COFINOGA' => 'Cofinoga', 'COM_BARRY_CDX' => 'Carte Cadeau Comtesse du Barry',
            'COM_BARRY_CDX_SB' => 'Carte Cadeau Comtesse du Barry (sandbox)', 'CONECS' => 'Conecs', 'CONFORAMA' => 'Conforama',
            'CORA' => 'Cora', 'CORA_BLANCHE' => 'Cora blanche', 'CORA_PREM' => 'Cora Visa Premier', 'CORA_VISA' => 'Cora Visa',
            'CVCO' => 'Chèque-Vacances Connect', 'DINERS' => 'Diners', 'DISCOVER' => 'Discover', 'ECCARD' => 'EC Card',
            'EDENRED' => 'Ticket Restaurant', 'EDENRED_CC' => 'Ticket Cheque Consommation', 'EDENRED_EC' => 'Ticket EcoCheque',
            'EDENRED_SC' => 'Ticket Sport & Culture', 'EDENRED_TC' => 'Ticket Compliments', 'EDENRED_TR' => 'Ticket Restaurant', 'ELO' => 'Elo',
            'ELV' => 'ELV', 'FRANFINANCE_3X' => 'Paiement en 3 fois', 'FRANFINANCE_4X' => 'Paiement en 4 fois', 'FULLCB3X' => 'Paiement en 3 fois CB',
            'FULLCB4X' => 'Paiement en 4 fois CB', 'GEMO_CDX' => 'Carte Cadeau Gémo', 'GEMO_CDX_SB' => 'Carte Cadeau Gémo (sandbox)',
            'GIROPAY' => 'Giropay', 'GOOGLEPAY' => 'Google Pay', 'HIPER' => 'Hiper', 'HIPERCARD' => 'Hipercard', 'IDEAL' => 'iDEAL',
            'ILLICADO' => 'Carte Illicado', 'ILLICADO_SB' => 'Carte Illicado (sandbox)', 'IP_WIRE' => 'Virement SEPA',
            'IP_WIRE_INST' => 'Virement SEPA Instantané', 'JCB' => 'JCB', 'JOUECLUB_CDX' => 'Carte Cadeau Joué Club',
            'JOUECLUB_CDX_SB' => 'Carte Cadeau Joué Club (sandbox)', 'JULES_CDX' => 'Carte Cadeau Jules',
            'JULES_CDX_SB' => 'Carte Cadeau Jules (sandbox)', 'KADEOS_CULTURE' => 'Carte Kadéos Culture',
            'KADEOS_GIFT' => 'Carte Kadéos Zénith', 'KLARNA' => 'Klarna', 'LECLERC' => 'Carte Reglo',
            'LEROY-MERLIN' => 'Carte Maison Financement', 'LEROY-MERLIN_SB' => 'Carte Maison Financement (sandbox)',
            'MASTERPASS' => 'MasterPass', 'MC_CORDOBESA' => 'Mastercard Cordobesa', 'MULTIBANCO' => 'Multibanco', 'MYBANK' => 'MyBank',
            'NARANJA' => 'Naranja', 'NORAUTO' => 'Carte Norauto option Financement', 'NORAUTO_SB' => 'Carte Norauto option Financement (sandbox)',
            'ONEY' => 'Paiement en 3 ou 4 fois par CB', 'ONEY_10X_12X' => 'Paiement en 10 ou 12 fois Oney',
            'ONEY_3X_4X' => 'Paiement en 3 ou 4 fois Oney', 'ONEY_ENSEIGNE' => 'Cartes enseignes Oney', 'ONEY_PAYLATER' => 'Pay Later Oney',
            'ONEY_SANDBOX' => 'Paiement en 3 ou 4 fois par CB (sandbox)', 'PASS_BEAU_CDX' => 'Carte Cadeau Passion Beauté',
            'PASS_BEAU_CDX_SB' => 'Carte Cadeau Passion Beauté (sandbox)', 'PAYBOX' => 'Paybox', 'PAYDIREKT' => 'Paydirekt', 'PAYPAL' => 'PayPal',
            'PAYPAL_BNPL' => 'PayPal Pay Later', 'PAYPAL_BNPL_SB' => 'PayPal Pay Later Sandbox',
            'PAYPAL_SB' => 'PayPal Sandbox', 'PICWIC' => 'Carte Picwic', 'PICWIC_SB' => 'Carte Picwic (sandbox)',
            'POSTFINANCE' => 'PostFinance Card', 'POSTFINANCE_EFIN' => 'PostFinance E-Finance', 'PRESTO' => 'Presto',
            'PRZELEWY24' => 'Przelewy24', 'S-MONEY' => 'S-money', 'SCT' => 'Virement SEPA', 'SDD' => 'Prélèvement SEPA',
            'SODEXO' => 'Pass Restaurant', 'SOFICARTE' => 'Soficarte', 'SOFORT_BANKING' => 'Sofort', 'SOROCRED' => 'Sorocred',
            'SYGMA' => 'Sygma', 'TRUFFAUT_CDX' => 'Carte Cadeau Truffaut', 'VILLAVERDE' => 'Carte Cadeau VillaVerde',
            'VILLAVERDE_SB' => 'Carte Cadeau VillaVerde (sandbox)', 'WECHAT' => 'WeChat Pay'
        );
    }

    /**
     * Return the statuses list of finalized successful payments (authorized or captured).
     * @return string[]
     */
    public static function getSuccessStatuses()
    {
        return array(
            'AUTHORISED',
            'CAPTURED',
            'ACCEPTED',
            'PARTIALLY_AUTHORISED'
        );
    }

    /**
     * Return the statuses list of payments that are waiting confirmation (successful but
     * the amount has not been transfered and is not yet guaranteed).
     * @return string[]
     */
    public static function getPendingStatuses()
    {
        return array(
            'INITIAL',
            'WAITING_AUTHORISATION',
            'WAITING_AUTHORISATION_TO_VALIDATE',
            'UNDER_VERIFICATION',
            'PRE_AUTHORISED',
            'WAITING_FOR_PAYMENT',
            'AUTHORISED_TO_VALIDATE',
            'SUSPENDED',
            'PENDING',
            'REFUND_TO_RETRY'
        );
    }

    /**
     * Return the statuses list of payments interrupted by the buyer.
     * @return string[]
     */
    public static function getCancelledStatuses()
    {
        return array(
            'ABANDONED',
            'NOT_CREATED',
            'CANCELLED'
        );
    }

    /**
     * Return the statuses list of payments waiting manual validation from the gateway Back Office.
     * @return string[]
     */
    public static function getToValidateStatuses()
    {
        return array(
            'WAITING_AUTHORISATION_TO_VALIDATE',
            'AUTHORISED_TO_VALIDATE'
        );
    }

    /**
     * Compute the signature. Parameters must be in UTF-8.
     *
     * @param array[string][string] $parameters payment gateway request/response parameters
     * @param string $key shop certificate
     * @param string $algo signature algorithm
     * @param boolean $hashed set to false to get the unhashed signature
     * @return string
     */
    public static function sign($parameters, $key, $algo, $hashed = true)
    {
        ksort($parameters);

        $sign = '';
        foreach ($parameters as $name => $value) {
            if (strpos($name, 'vads_') === 0) {
                $sign .= $value . '+';
            }
        }

        $sign .= $key;

        if (! $hashed) {
            return $sign;
        }

        switch ($algo) {
            case self::ALGO_SHA1:
                return sha1($sign);
            case self::ALGO_SHA256:
                return base64_encode(hash_hmac('sha256', $sign, $key, true));
            default:
                throw new \InvalidArgumentException("Unsupported algorithm passed : {$algo}.");
        }
    }

    /**
     * Get current PHP version without build info.
     * @return string
     */
    public static function shortPhpVersion()
    {
        $version = PHP_VERSION;

        $match = array();
        if (preg_match(self::PATTERN_SHORT_PHP_VERSION, $version, $match) === 1) {
            $version = $match[0];
        }

        return $version;
    }

    /**
     * Format a given list of e-mails separated by commas and render them as HTML links.
     * @param string $emails
     * @return string
     */
    public static function formatSupportEmails($emails)
    {
        $formatted = '';

        $parts = explode(', ', $emails);
        foreach ($parts as $part) {
            $elts = explode(':', $part);
            if (count($elts) === 2) {
                $label = trim($elts[0]) . ': ';
                $email = $elts[1];
            } elseif (count($elts) === 1) {
                $label = '';
                $email = $elts[0];
            } else {
                throw new \InvalidArgumentException("Invalid support e-mails string passed: {$emails}.");
            }

            $email = trim($email);

            if (! empty($formatted)) {
                $formatted .= '<br />';
            }

            $formatted .= $label . '<a href="mailto:' . $email . '">' . $email . '</a>';
        }

        return $formatted;
    }

    /**
     * Return the list of SEPA countries.
     *
     * @return array[string]
     */
    public static function getSepaCountries()
    {
        return array(
            'AD', 'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK',
            'EE', 'ES', 'FI', 'FR', 'GB', 'GI', 'GR', 'HR', 'HU',
            'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MT',
            'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'SM'
        );
    }

    /**
     * Return the list of Overseas countries.
     *
     * @return array[string]
     */
    public static function getOverseasCountries()
    {
        return array(
            'BL', 'GF', 'GP', 'MF', 'MQ', 'NC', 'PF', 'PM', 'RE',
            'TF', 'WF', 'YT'
        );
    }

    /**
     * Returns an array of the online documentation URI of the payment module.
     *
     * @return array[string][string]
     */
    public static function getOnlineDocUri()
    {
        return array(
            'fr' => 'https://payzen.io/fr-FR/plugins/',
            'en' => 'https://payzen.io/en-EN/plugins/',
            'es' => 'https://payzen.io/es-ES/plugins/'
        );
    }
}
