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
 * Utility class for managing white label parameters.
 */
class WhiteLabel
{
    /**
     * Returns an array of White Labels.
     *
     * @return array[string][string]
     */
    public static function getWhiteLabels()
    {
        return [
            'EU' => 'PayZen Europe',
            'LAT' => 'PayZen Latam',
            'BR' => 'PayZen Brazil'
        ];
    }

    /**
     * Return the list of currencies supported by the white label.
     *
     * @param string $whiteLabel the white label identifier
     * @return array[int][Lyranetwork\Payzen\Sdk\Form\Currency]
     */
    public static function getSupportedCurrencies($whiteLabel)
    {
        $currencies = [
            'LAT' => [
            array('ARS', '032', 2), array('AUD', '036', 2), array('BRL', '986', 2), array('CAD', '124', 2),
            array('CHF', '756', 2), array('CLP', '152', 0), array('CNY', '156', 2), array('COP', '170', 2),
            array('CRC', '188', 2), array('CZK', '203', 2), array('DKK', '208', 2), array('EUR', '978', 2),
            array('GBP', '826', 2), array('GTQ', '320', 2), array('HKD', '344', 2), array('HNL', '340', 2),
            array('HUF', '348', 2), array('IDR', '360', 0), array('INR', '356', 2), array('JPY', '392', 0),
            array('KHR', '116', 2), array('KRW', '410', 0), array('KWD', '414', 3), array('MXN', '484', 2),
            array('MYR', '458', 2), array('NIO', '558', 2), array('NOK', '578', 2), array('NZD', '554', 2),
            array('PEN', '604', 2), array('PHP', '608', 2), array('PLN', '985', 2), array('RUB', '643', 2),
            array('SEK', '752', 2), array('SGD', '702', 2), array('SVC', '222', 2), array('THB', '764', 2),
            array('TRY', '949', 2), array('TWD', '901', 2), array('USD', '840', 2), array('UYU', '858', 2),
            array('XOF', '952', 0), array('XPF', '953', 0), array('ZAR', '710', 2)
            ],
            'BR' => [
            array('ARS', '032', 2), array('BRL', '986', 2), array('CAD', '124', 2), array('CLP', '152', 0),
            array('COP', '170', 2), array('MXN', '484', 2), array('PEN', '604', 2), array('USD', '840', 2),
            array('UYU', '858', 2)
            ]
        ];

        return $currencies[$whiteLabel] ?? [];
    }

    /**
     * Returns an array of card types accepted by the payment gateway for a given white label.
     *
     * @param string $whiteLabel the white label identifier
     * @return array[string][string] an associative array of card type codes to labels, or an empty array if not found
     */
    public static function getSupportedCardTypes($whiteLabel)
    {
        $cardTypes = [
            'LAT' => [
            'MAESTRO' => 'Maestro', 'MASTERCARD' => 'Mastercard', 'VISA' => 'Visa', 'VISA_ELECTRON' => 'Visa Electron',
            'VPAY' => 'V PAY', 'AMEX' => 'American Express', 'ALKOSTO' => 'Alkosto', 'AMEX_BANSUD' => 'American Express Bansud',
            'AMEX_CENT_AR' => 'American Express Centurion', 'AMEX_CORP_AR' => 'American Express Corporate',
            'AMEX_GALICIA' => 'American Express Galicia', 'AMEX_HSBC' => 'American Express HSBC',
            'AMEX_MACRO' => 'American Express Macro', 'AMEX_NARANJA' => 'American Express Naranja',
            'AMEX_PATAGONIA' => 'American Express Patagonia', 'AMEX_PLAT_AR' => 'American Express Platinium',
            'AMEX_REBANKING' => 'American Express Rebanking', 'AMEX_SANTANDER' => 'American Express Santander',
            'APPLE_PAY' => 'Apple Pay', 'AURA' => 'Aura', 'BANCOLOMBIA_IP' => 'Botón Bancolombia',
            'BANESCARD' => 'Banescard', 'BCO_BOGOTA_OFC' => 'Oficinas Banco de Bogotá', 'BOLETO' => 'Boleto Bancário',
            'BRADESCO_BOLETO' => 'Boleto Bradesco', 'BRE_B' => 'Bre-B', 'BRE_B_M' => 'Bre-B con llave', 'BRE_B_SEND' => 'Bre-b send',
            'CABAL' => 'Cabal', 'CABAL_DEBIT' => 'Cabal Débito', 'CARNET' => 'Carnet',
            'CENCOSUD' => 'Cencosud', 'CMR' => 'CMR', 'CODENSA' => 'Codensa', 'COLSUBSIDIO' => 'Colsubsidio', 'CREDZ' => 'Credz', 'DAVIPLATA' => 'Daviplata',
            'DINERS' => 'Diners', 'DISCOVER' => 'Discover', 'EDENRED_CO' => 'Edenred', 'EDENRED_CO_DON' => 'Edenred Dotación',
            'EDENRED_CO_GAS' => 'Edenred Auto', 'EDENRED_CO_GIFT' => 'Edenred Regalo', 'EDENRED_CO_TR' => 'Edenred Alimenticio',
            'EFECTY' => 'Efecty', 'ELO' => 'Elo', 'ELO_DEBIT' => 'Elo Débit', 'EXITO' => 'Exito', 'EXITO_CASH' => 'Exito Cash',
            'FASTPRO' => 'FastPro', 'GOOGLEPAY' => 'Google Pay', 'HIPER' => 'Hiper', 'HIPERCARD' => 'Hipercard',
            'ITAU_BOLETO' => 'Boleto Itaú', 'ITAU_IB' => 'Internet Banking Itaú', 'JCB' => 'JCB', 'MAIS' => 'Mais!',
            'MASTERCARD_DEBIT' => 'Mastercard Débito', 'MASTERPASS' => 'MasterPass', 'MC_BBVA' => 'Mastercard BBVA',
            'MC_CENCOSUD' => 'Mastercard Cencosud', 'MC_CHACO' => 'Mastercard Chaco', 'MC_CITYBANK' => 'Mastercard CityBank',
            'MC_COLUMBIA' => 'Mastercard Columbia', 'MC_COMAFI' => 'Mastercard Comafi', 'MC_CORDOBESA' => 'Mastercard Cordobesa',
            'MC_CORRIENTES' => 'Mastercard Corrientes', 'MC_ENTRE_RIOS' => 'Mastercard Banco Entre Ríos',
            'MC_FALABELLA' => 'Mastercard Falabella', 'MC_GALICIA' => 'Mastercard Galicia', 'MC_HSBC' => 'Mastercard HSBC',
            'MC_ICBC' => 'Mastercard ICBC', 'MC_ITAU' => 'Mastercard Itau', 'MC_MACRO' => 'Mastercard Macro',
            'MC_MUNICIPAL' => 'Mastercard Banco Municipal', 'MC_NACION' => 'Mastercard Nación', 'MC_PAMPA' => 'Mastercard Pampa',
            'MC_PATAGONIA' => 'Mastercard Patagonia', 'MC_PROVINCIA' => 'Mastercard Provincia', 'MC_SANTANDER' => 'Mastercard Santander',
            'MC_SANTA_FE' => 'Mastercard Banco Santa Fe', 'MOVIL_RED' => 'Movil Red', 'NARANJA' => 'Naranja',
            'NEQUI' => 'Nequi', 'OH' => 'OH !', 'PAGOEFECTIVO' => 'PagoEfectivo',
            'PAYPAL' => 'PayPal', 'PAYPAL_BNPL' => 'PayPal Pay Later', 'PAYPAL_BNPL_SB' => 'PayPal Pay Later Sandbox',
            'PAYPAL_SB' => 'PayPal Sandbox', 'PIM' => 'Pim', 'PSE' => 'PSE', 'RED_AVAL' => 'Red Aval', 'RIPLEY' => 'Ripley', 'SAMSUNG_PAY' => 'Samsung Pay',
            'SDD' => 'Débito directo SEPA', 'SOL' => 'Sol', 'SOROCRED' => 'Sorocred', 'TRANSFIYA' => 'Transfiya', 'TUYA' => 'Tuya', 'VISA_BANCODELSOL' => 'Visa Banco del Sol',
            'VISA_BBVA' => 'Visa BBVA', 'VISA_CHACO' => 'Visa Chaco', 'VISA_CIUDAD' => 'Visa Ciudad', 'VISA_COLUMBIA' => 'Visa Columbia',
            'VISA_COMAFI' => 'Visa Comafi', 'VISA_CORDOBESA' => 'Visa Cordobesa', 'VISA_CORRIENTES' => 'Visa Corrientes',
            'VISA_CREDICOOP' => 'Visa Credicoop', 'VISA_DEBIT' => 'Visa Débito', 'VISA_ENTRE_RIOS' => 'Visa Banco Entre Ríos', 'VISA_FORMOSA' => 'Visa Formosa',
            'VISA_GALICIA' => 'Visa Galicia', 'VISA_HIPOTECARIO' => 'Visa Hipotecario', 'VISA_HSBC' => 'Visa HSBC',
            'VISA_ICBC' => 'Visa ICBC', 'VISA_INDUSTRIAL' => 'Visa Industrial', 'VISA_ITAU' => 'Visa Itau', 'VISA_MACRO' => 'Visa Macro',
            'VISA_MUNICIPAL' => 'Visa Banco Municipal', 'VISA_NACION' => 'Visa Nación', 'VISA_NEUQUEN' => 'Visa Neuquen', 'VISA_PAMPA' => 'Visa Pampa',
            'VISA_PATAGONIA' => 'Visa Patagonia', 'VISA_PROVINCIA' => 'Visa Provincia', 'VISA_SANTANDER' => 'Visa Santander',
            'VISA_SANTA_FE' => 'Visa Banco Santa Fe', 'WEBPAY_PLUS' => 'Webpay Plus', 'WEBPAY_PLUS_CRD' => 'Webpay Plus Crédito',
            'WEBPAY_PLUS_DEB' => 'Webpay Plus Débito'
            ],
            'BR' => [
            'MAESTRO' => 'Maestro', 'MASTERCARD' => 'Mastercard', 'VISA' => 'Visa', 'VISA_ELECTRON' => 'Visa Electron',
            'VPAY' => 'V PAY', 'AMEX' => 'American Express', 'ALKOSTO' => 'Alkosto', 'AMEX_BANSUD' => 'American Express Bansud',
            'AMEX_CENT_AR' => 'American Express Centurion', 'AMEX_CORP_AR' => 'American Express Corporate',
            'AMEX_GALICIA' => 'American Express Galicia', 'AMEX_HSBC' => 'American Express HSBC',
            'AMEX_MACRO' => 'American Express Macro', 'AMEX_NARANJA' => 'American Express Naranja',
            'AMEX_PATAGONIA' => 'American Express Patagonia', 'AMEX_PLAT_AR' => 'American Express Platinium',
            'AMEX_REBANKING' => 'American Express Rebanking', 'AMEX_SANTANDER' => 'American Express Santander',
            'APPLE_PAY' => 'Apple Pay', 'AURA' => 'Aura', 'BANCOLOMBIA_IP' => 'Botón Bancolombia', 'BANESCARD' => 'Banescard',
            'BCO_BOGOTA_OFC' => 'Oficinas Banco de Bogotá', 'BOLETO' => 'Boleto Bancário', 'BRADESCO_BOLETO' => 'Boleto Bradesco',
            'CABAL' => 'Cabal', 'CABAL_DEBIT' => 'Cabal Débito', 'CARNET' => 'Carnet', 'CENCOSUD' => 'Cencosud', 'CMR' => 'CMR',
            'CODENSA' => 'Codensa', 'COLSUBSIDIO' => 'Colsubsidio', 'CREDZ' => 'Credz', 'DINERS' => 'Diners', 'DISCOVER' => 'Discover', 'EDENRED_CO' => 'Edenred',
            'EDENRED_CO_DON' => 'Edenred Dotación', 'EDENRED_CO_GAS' => 'Edenred Auto', 'EDENRED_CO_GIFT' => 'Edenred Regalo',
            'EDENRED_CO_TR' => 'Edenred Alimenticio', 'EFECTY' => 'Efecty', 'ELO' => 'Elo', 'ELO_DEBIT' => 'Elo Débit', 'EXITO' => 'Exito',
            'EXITO_CASH' => 'Exito Cash', 'FASTPRO' => 'FastPro', 'GOOGLEPAY' => 'Google Pay', 'HIPER' => 'Hiper', 'HIPERCARD' => 'Hipercard',
            'ITAU_BOLETO' => 'Boleto Itaú', 'ITAU_IB' => 'Internet Banking Itaú', 'JCB' => 'JCB', 'MAIS' => 'Mais!',
            'MASTERPASS' => 'MasterPass', 'MC_BBVA' => 'Mastercard BBVA', 'MASTERCARD_DEBIT' => 'Mastercard Débito',
            'MC_CENCOSUD' => 'Mastercard Cencosud', 'MC_CHACO' => 'Mastercard Chaco', 'MC_CITYBANK' => 'Mastercard CityBank',
            'MC_COLUMBIA' => 'Mastercard Columbia','MC_COMAFI' => 'Mastercard Comafi', 'MC_CORDOBESA' => 'Mastercard Cordobesa',
            'MC_CORRIENTES' => 'Mastercard Corrientes', 'MC_ENTRE_RIOS' => 'Mastercard Banco Entre Ríos',
            'MC_FALABELLA' => 'Mastercard Falabella', 'MC_GALICIA' => 'Mastercard Galicia', 'MC_HSBC' => 'Mastercard HSBC', 'MC_ICBC' => 'Mastercard ICBC',
            'MC_ITAU' => 'Mastercard Itau', 'MC_MACRO' => 'Mastercard Macro', 'MC_MUNICIPAL' => 'Mastercard Banco Municipal',
            'MC_NACION' => 'Mastercard Nación', 'MC_PAMPA' => 'Mastercard Pampa', 'MC_PATAGONIA' => 'Mastercard Patagonia',
            'MC_PROVINCIA' => 'Mastercard Provincia', 'MC_SANTANDER' => 'Mastercard Santander', 'MC_SANTA_FE' => 'Mastercard Banco Santa Fe',
            'MOVIL_RED' => 'Movil Red', 'NARANJA' => 'Naranja', 'NEQUI' => 'Nequi', 'OH' => 'OH !', 'PAYPAL' => 'PayPal', 'PAYPAL_BNPL' => 'PayPal Pay Later',
            'PAYPAL_BNPL_SB' => 'PayPal Pay Later Sandbox', 'PAYPAL_SB' => 'PayPal Sandbox', 'PIM' => 'Pim', 'PIX' => 'Pix', 'PSE' => 'PSE',
            'RED_AVAL' => 'Red Aval', 'RIPLEY' => 'Ripley', 'SAMSUNG_PAY' => 'Samsung Pay', 'SDD' => 'Débito directo SEPA', 'SICOOB_BOLETO' => 'Boleto Sicoob',
            'SICREDI_BOLETO' => 'Boleto Sicredi', 'SOL' => 'Sol', 'SOROCRED' => 'Sorocred', 'TRANSFIYA' => 'Transfiya', 'TUYA' => 'Tuya', 'VISA_BANCODELSOL' => 'Visa Banco del Sol',
            'VISA_BBVA' => 'Visa BBVA', 'VISA_CHACO' => 'Visa Chaco', 'VISA_CIUDAD' => 'Visa Ciudad', 'VISA_COLUMBIA' => 'Visa Columbia',
            'VISA_COMAFI' => 'Visa Comafi', 'VISA_CORDOBESA' => 'Visa Cordobesa', 'VISA_CORRIENTES' => 'Visa Corrientes',
            'VISA_CREDICOOP' => 'Visa Credicoop', 'VISA_DEBIT' => 'Visa Débito', 'VISA_ENTRE_RIOS' => 'Visa Banco Entre Ríos', 'VISA_FORMOSA' => 'Visa Formosa',
            'VISA_GALICIA' => 'Visa Galicia', 'VISA_HIPOTECARIO' => 'Visa Hipotecario', 'VISA_HSBC' => 'Visa HSBC',
            'VISA_ICBC' => 'Visa ICBC', 'VISA_INDUSTRIAL' => 'Visa Industrial', 'VISA_ITAU' => 'Visa Itau',
            'VISA_MACRO' => 'Visa Macro', 'VISA_MUNICIPAL' => 'Visa Banco Municipal', 'VISA_NACION' => 'Visa Nación',
            'VISA_NEUQUEN' => 'Visa Neuquen', 'VISA_PAMPA' => 'Visa Pampa', 'VISA_PATAGONIA' => 'Visa Patagonia',
            'VISA_PROVINCIA' => 'Visa Provincia', 'VISA_SANTANDER' => 'Visa Santander', 'VISA_SANTA_FE' => 'Visa Banco Santa Fe',
            'WEBPAY_PLUS' => 'WP Débito/Crédito', 'WEBPAY_PLUS_CRD' => 'WP Crédito', 'WEBPAY_PLUS_DEB' => 'WP Débito'
            ]
        ];

        return $cardTypes[$whiteLabel] ?? [];
    }

    /**
     * Returns an array of online documentation URIs for a given white label.
     *
     * @param string $whiteLabel the white label identifier
     * @return array[string][string] an associative array of locale codes to documentation URIs, or an empty array if not found
     */
    public static function getOnlineDocUri($whiteLabel)
    {
        $onlineDocUri = [
            'LAT' => [
            'es' => 'https://payzen.io/lat/plugins/'
            ],
            'BR' => [
            'pt' => 'https://payzen.io/pt-BR/plugins/'
            ]
        ];

        return $onlineDocUri[$whiteLabel] ?? [];
    }

    /**
     * Returns the gateway URLs indexed by white label identifier.
     *
     * @return array[string][string] an associative array mapping white label identifiers to gateway URLs
     */
    public static function getGatewayUrl()
    {
        return [
            'LAT' => 'https://secure.payzen.lat/vads-payment/',
            'BR' => 'https://secure.payzen.com.br/vads-payment/'
        ];
    }

    /**
     * Returns the static asset URLs indexed by white label identifier.
     *
     * @return array[string][string] an associative array mapping white label identifiers to static asset URLs
     */
    public static function getStaticUrl()
    {
        return [
            'LAT' => 'https://static.payzen.lat/static/',
            'BR' => 'https://static.payzen.lat/static/'
        ];
    }

    /**
     * Returns the REST API URLs indexed by white label identifier.
     *
     * @return array[string][string] an associative array mapping white label identifiers to REST API URLs
     */
    public static function getRestUrl()
    {
        return [
            'LAT' => 'https://api.payzen.lat/api-payment/',
            'BR' => 'https://api.payzen.com.br/api-payment/'
        ];
    }

    /**
     * Returns the logo URLs indexed by white label identifier.
     *
     * @return array[string][string] an associative array mapping white label identifiers to logo URLs
     */
    public static function getLogoUrl()
    {
        return [
            'LAT' => 'https://secure.payzen.lat/static/latest/images/type-carte/',
            'BR' => 'https://secure.payzen.com.br/static/latest/images/type-carte/'
        ];
    }

    /**
     * Returns the plugin features indexed by white label identifier.
     *
     * Parses feature definitions embedded as strings (via template placeholders)
     * and extracts the feature array content using a regex.
     *
     * @return array[string][mixed] an associative array mapping white label identifiers to their feature sets
     */
    public static function getFeatures()
    {
        $matches = [];
        preg_match('/plugin_features\s*=\s*array\s*\(([\s\S]*?)\);/', "{plugin_features = array(
    'qualif' => false,
    'prodfaq' => true,
    'restrictmulti' => false,
    'shatwo' => true,
    'subscr' => true,

    'multi' => false,
    'klarna' => true,
    'franfinance' => true,
    'sepa' => true,

    'whitelabelall' => false
);}", $matches);
        $latFeatures = $matches[1] ?? [];

        $matches = [];
        preg_match('/plugin_features\s*=\s*array\s*\(([\s\S]*?)\);/', "{plugin_features = array(
    'qualif' => false,
    'prodfaq' => true,
    'restrictmulti' => false,
    'shatwo' => true,
    'subscr' => true,

    'multi' => false,
    'klarna' => true,
    'franfinance' => true,
    'sepa' => true,

    'whitelabelall' => false
);}", $matches);
        $brFeatures = $matches[1] ?? [];

        return [
            'LAT' => $latFeatures,
            'BR' => $brFeatures
        ];
    }
}