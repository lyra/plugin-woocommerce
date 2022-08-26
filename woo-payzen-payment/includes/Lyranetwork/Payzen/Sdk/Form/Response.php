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
 * Class representing the result of a transaction (sent by the IPN URL or by the client return).
 */
class Response
{
    const TYPE_RESULT = 'result';
    const TYPE_AUTH_RESULT = 'auth_result';
    const TYPE_WARRANTY_RESULT = 'warranty_result';
    const TYPE_RISK_CONTROL = 'risk_control';
    const TYPE_RISK_ASSESSMENT = 'risk_assessment';

    /**
     * Raw response parameters array.
     *
     * @var array[string][string]
     */
    private $rawResponse;

    /**
     * Certificate used to check the signature.
     *
     * @see Lyranetwork\Payzen\Sdk\Form\Api::sign
     * @var string
     */
    private $certificate;

    /**
     * Algorithm used to check the signature.
     *
     * @see Lyranetwork\Payzen\Sdk\Form\Api::sign
     * @var string
     */
    private $algo = Api::ALGO_SHA1;

    /**
     * Value of vads_result.
     *
     * @var string
     */
    private $result;

    /**
     * Value of vads_extra_result.
     *
     * @var string
     */
    private $extraResult;

    /**
     * Value of vads_auth_result
     *
     * @var string
     */
    private $authResult;

    /**
     * Value of vads_warranty_result
     *
     * @var string
     */
    private $warrantyResult;

    /**
     * Transaction status (vads_trans_status)
     *
     * @var string
     */
    private $transStatus;

    /**
     * Constructor for response class.
     * Prepare to analyse check URL or return URL call.
     *
     * @param array[string][string] $params
     * @param string $ctx_mode
     * @param string $key_test
     * @param string $key_prod
     * @param string $algo
     */
    public function __construct($params, $ctx_mode, $key_test, $key_prod, $algo = Api::ALGO_SHA1)
    {
        $this->rawResponse = $params;
        $this->certificate = trim(($ctx_mode === 'PRODUCTION') ? $key_prod : $key_test);

        if (in_array($algo, Api::$SUPPORTED_ALGOS, true)) {
            $this->algo = $algo;
        }

        // Payment results.
        $this->result = self::findInArray('vads_result', $this->rawResponse, null);
        $this->extraResult = self::findInArray('vads_extra_result', $this->rawResponse, null);
        $this->authResult = self::findInArray('vads_auth_result', $this->rawResponse, null);
        $this->warrantyResult = self::findInArray('vads_warranty_result', $this->rawResponse, null);

        $this->transStatus = self::findInArray('vads_trans_status', $this->rawResponse, null);
    }

    /**
     * Check response signature.
     * @return bool
     */
    public function isAuthentified()
    {
        return $this->getComputedSignature() === $this->getSignature();
    }

    /**
     * Return the signature computed from the received parameters, for log/debug purposes.
     * @param bool $hashed
     * @return string
     */
    public function getComputedSignature($hashed = true)
    {
        return Api::sign($this->rawResponse, $this->certificate, $this->algo, $hashed);
    }

    /**
     * Check if the payment was successful (waiting confirmation or captured).
     * @return bool
     */
    public function isAcceptedPayment()
    {
        return in_array($this->transStatus, Api::getSuccessStatuses(), true) || $this->isPendingPayment();
    }

    /**
     * Check if the payment is waiting confirmation (successful but the amount has not been
     * transfered and is not yet guaranteed).
     * @return bool
     */
    public function isPendingPayment()
    {
        return in_array($this->transStatus, Api::getPendingStatuses(), true);
    }

    /**
     * Check if the payment process was interrupted by the buyer.
     * @return bool
     */
    public function isCancelledPayment()
    {
        return in_array($this->transStatus, Api::getCancelledStatuses(), true);
    }

    /**
     * Check if the payment is to validate manually in the gateway Back Office.
     * @return bool
     */
    public function isToValidatePayment()
    {
        return in_array($this->transStatus, Api::getToValidateStatuses(), true);
    }

    /**
     * Check if the payment is suspected to be fraudulent.
     * @return bool
     */
    public function isSuspectedFraud()
    {
        // At least one control failed...
        $riskControl = $this->getRiskControl();
        if (in_array('WARNING', $riskControl, true) || in_array('ERROR', $riskControl, true)) {
            return true;
        }

        // Or there was an alert from risk assessment module.
        $riskAssessment = $this->getRiskAssessment();
        if (in_array('INFORM', $riskAssessment, true)) {
            return true;
        }

        return false;
    }

    /**
     * Return the risk control result.
     * @return array[string][string]
     */
    public function getRiskControl()
    {
        $riskControl = $this->get('risk_control');
        if (! isset($riskControl) || ! trim($riskControl)) {
            return array();
        }

        // Get a URL-like string.
        $riskControl = str_replace(';', '&', $riskControl);

        $result = array();
        parse_str($riskControl, $result);

        return $result;
    }

    /**
     * Return the risk assessment result.
     * @return array[string]
     */
    public function getRiskAssessment()
    {
        $riskAssessment = $this->get('risk_assessment_result');
        if (! isset($riskAssessment) || ! trim($riskAssessment)) {
            return array();
        }

        return explode(';', $riskAssessment);
    }

    /**
     * Return the value of a response parameter.
     * @param string $name
     * @return string
     */
    public function get($name, $hasPrefix = true)
    {
        if ($hasPrefix) {
            // Manage shortcut notations by adding 'vads_' prefix.
            $name = (strpos($name, 'vads_') !== 0) ? 'vads_' . $name : $name;
        }

        return array_key_exists($name, $this->rawResponse) ? $this->rawResponse[$name] : null;
    }

    /**
     * Shortcut for getting ext_info_* fields.
     * @param string $key
     * @return string
     */
    public function getExtInfo($key)
    {
        return $this->get("ext_info_$key");
    }

    /**
     * Return the expected signature received from gateway.
     * @return string
     */
    public function getSignature()
    {
        return $this->get('signature', false);
    }

    /**
     * Return the paid amount converted from cents (or currency equivalent) to a decimal value.
     * @return float
     */
    public function getFloatAmount()
    {
        $currency = Api::findCurrencyByNumCode($this->get('currency'));
        return $currency->convertAmountToFloat($this->get('amount'));
    }

    /**
     * Return the payment response result.
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Return the payment response extra result.
     * @return string
     */
    public function getExtraResult()
    {
        return $this->extraResult;
    }

    /**
     * Return the payment response authentication result.
     * @return string
     */
    public function getAuthResult()
    {
        return $this->authResult;
    }

    /**
     * Return the payment response warranty result.
     * @return string
     */
    public function getWarrantyResult()
    {
        return $this->warrantyResult;
    }

    /**
     * Return all the payment response results as array.
     * @return array[string][string]
     */
    public function getAllResults()
    {
        return array(
            'result' => $this->result,
            'extra_result' => $this->extraResult,
            'auth_result' => $this->authResult,
            'warranty_result' => $this->warrantyResult
        );
    }

    /**
     * Return the payment transaction status.
     * @return string
     */
    public function getTransStatus()
    {
        return $this->transStatus;
    }

    /**
     * Return the response message translated to the payment langauge.
     * @param $type string
     * @return string
     */
    public function getMessage($type = self::TYPE_RESULT)
    {
        $text = self::translate($this->get($type), $type, $this->get('language'), true);

        if ($type === self::TYPE_RESULT && $this->get($type) === '30' /* form error */) {
            $text .= ' ' . self::extraMessage($this->extraResult);
        }

        return $text;
    }

    /**
     * Return the complete response message translated to the payment langauge.
     * @param $type string
     * @return string
     */
    public function getCompleteMessage($sep = ' ')
    {
        $text = $this->getMessage(self::TYPE_RESULT);
        $text .= $sep . $this->getMessage(self::TYPE_AUTH_RESULT);
        $text .= $sep . $this->getMessage(self::TYPE_WARRANTY_RESULT);

        return $text;
    }

    /**
     * Return a short description of the payment result, useful for logging.
     * @return string
     */
    public function getLogMessage()
    {
        $text = self::translate($this->result, self::TYPE_RESULT, 'en', true);
        if ($this->result === '30' /* form error */) {
            $text .= ' ' . self::extraMessage($this->extraResult);
        }

        $text .= ' ' . self::translate($this->authResult, self::TYPE_AUTH_RESULT, 'en', true);
        $text .= ' ' . self::translate($this->warrantyResult, self::TYPE_WARRANTY_RESULT, 'en', true);

        return $text;
    }

    public function getOutputForPlatform()
    {
        return call_user_func_array(array($this, 'getOutputForGateway'), func_get_args());
    }

    /**
     * Return a formatted string to output as a response to the notification URL call.
     *
     * @param string $case shortcut code for current situations. Most useful : payment_ok, payment_ko, auth_fail
     * @param string $extra_message some extra information to output to the payment gateway
     * @param string $original_encoding some extra information to output to the payment gateway
     * @return string
     */
    public function getOutputForGateway($case = '', $extra_message = '', $original_encoding = 'UTF-8')
    {
        // Predefined response messages according to case.
        $cases = array(
            'payment_ok' => array(true, 'Accepted payment, order has been updated.'),
            'payment_ko' => array(true, 'Payment failure, order has been cancelled.'),
            'payment_ko_bis' => array(true, 'Payment failure.'),
            'payment_ok_already_done' => array(true, 'Accepted payment, already registered.'),
            'payment_ko_already_done' => array(true, 'Payment failure, already registered.'),
            'order_not_found' => array(false, 'Order not found.'),
            'payment_ko_on_order_ok' => array(false, 'Order status does not match the payment result.'),
            'auth_fail' => array(false, 'An error occurred while computing the signature.'),
            'empty_cart' => array(false, 'Empty cart detected before order processing.'),
            'unknown_status' => array(false, 'Unknown order status.'),
            'amount_error' => array(false, 'Total paid is different from order amount.'),
            'ok' => array(true, ''),
            'ko' => array(false, '')
        );

        $success = array_key_exists($case, $cases) ? $cases[$case][0] : false;
        $message = array_key_exists($case, $cases) ? $cases[$case][1] : '';

        if (! empty($extra_message)) {
            $message .= ' ' . $extra_message;
        }

        $message = str_replace("\n", ' ', $message);

        // Set original CMS encoding to convert if necessary response to send to gateway.
        $encoding = in_array(strtoupper($original_encoding), Api::$SUPPORTED_ENCODINGS, true) ?
            strtoupper($original_encoding) : 'UTF-8';
        if ($encoding !== 'UTF-8') {
            $message = iconv($encoding, 'UTF-8', $message);
        }

        $content = $success ? 'OK-' : 'KO-';
        $content .= "$message\n";

        $response = '<span style="display:none">';
        $response .= htmlspecialchars($content, ENT_COMPAT, 'UTF-8');
        $response .= '</span>';
        return $response;
    }

    /**
     * Return a translated short description of the payment result for a specified language.
     * @param string $result
     * @param string $type
     * @param string $lang
     * @param boolean $appendCode
     * @return string
     */
    public static function translate($result, $type = self::TYPE_RESULT, $lang = 'en', $appendCode = false)
    {
        // If language is not supported, use the domain default language.
        if (! array_key_exists($lang, self::$RESPONSE_TRANS)) {
            $lang = 'en';
        }

        $translations = self::$RESPONSE_TRANS[$lang];

        $default = isset($translations[$type]['UNKNOWN']) ? $translations[$type]['UNKNOWN'] :
            $translations['UNKNOWN'];
        $text = self::findInArray($result ? $result : 'empty', $translations[$type], $default);

        if ($text && $appendCode) {
            $text = self::appendResultCode($text, $result);
        }

        return $text;
    }

    public static function appendResultCode($message, $result_code)
    {
        if ($result_code) {
            $message .= ' (' . $result_code . ')';
        }

        return $message . '.';
    }

    public static function extraMessage($extra_result)
    {
        $error = self::findInArray($extra_result, self::$FORM_ERRORS, 'OTHER');
        return self::appendResultCode($error, $extra_result);
    }

    public static function findInArray($key, $array, $default)
    {
        if (is_array($array) && array_key_exists($key, $array)) {
            return $array[$key];
        }

        return $default;
    }

    /**
     * Associative array containing human-readable translations of response codes.
     *
     * @var array
     * @access private
     */
    public static $RESPONSE_TRANS = array(
        'fr' => array(
            'UNKNOWN' => 'Inconnu',

            'result' => array(
                'empty' => '',
                '00' => 'Action réalisée avec succès',
                '02' => 'Le marchand doit contacter la banque du porteur',
                '05' => 'Action refusée',
                '17' => 'Action annulée',
                '30' => 'Erreur de format de la requête',
                '96' => 'Erreur technique'
            ),
            'auth_result' => array(
                'empty' => '',
                '00' => 'Transaction approuvée ou traitée avec succès',
                'UNKNOWN' => 'Voir le détail de la transaction pour plus d\'information'
            ),
            'warranty_result' => array(
                'empty' => 'Garantie de paiement non applicable',
                'YES' => 'Le paiement est garanti',
                'NO' => 'Le paiement n\'est pas garanti',
                'UNKNOWN' => 'Suite à une erreur technique, le paiment ne peut pas être garanti'
            ),
            'risk_control' => array (
                'CARD_FRAUD' => 'Contrôle du numéro de carte',
                'SUSPECT_COUNTRY' => 'Contrôle du pays émetteur de la carte',
                'IP_FRAUD' => 'Contrôle de l\'adresse IP',
                'CREDIT_LIMIT' => 'Contrôle de l\'encours',
                'BIN_FRAUD' => 'Contrôle du code BIN',
                'ECB' => 'Contrôle e-carte bleue',
                'COMMERCIAL_CARD' => 'Contrôle carte commerciale',
                'SYSTEMATIC_AUTO' => 'Contrôle carte à autorisation systématique',
                'INCONSISTENT_COUNTRIES' => 'Contrôle de cohérence des pays (IP, carte, adresse de facturation)',
                'NON_WARRANTY_PAYMENT' => 'Contrôle le transfert de responsabilité',
                'SUSPECT_IP_COUNTRY' => 'Contrôle Pays de l\'IP'
            ),
            'risk_assessment' => array(
                'ENABLE_3DS' => '3D Secure activé',
                'DISABLE_3DS' => '3D Secure désactivé',
                'MANUAL_VALIDATION' => 'La transaction est créée en validation manuelle',
                'REFUSE' => 'La transaction est refusée',
                'RUN_RISK_ANALYSIS' => 'Appel à un analyseur de risques externes',
                'INFORM' => 'Une alerte est remontée'
            )
        ),

        'en' => array(
            'UNKNOWN' => 'Unknown',

            'result' => array(
                'empty' => '',
                '00' => 'Action successfully completed',
                '02' => 'The merchant must contact the cardholder\'s bank',
                '05' => 'Action rejected',
                '17' => 'Action canceled',
                '30' => 'Request format error',
                '96' => 'Technical issue'
            ),
            'auth_result' => array(
                'empty' => '',
                '00' => 'Approved or successfully processed transaction',
                'UNKNOWN' => 'See the transaction details for more information'
            ),
            'warranty_result' => array(
                'empty' => 'Payment guarantee not applicable',
                'YES' => 'The payment is guaranteed',
                'NO' => 'The payment is not guaranteed',
                'UNKNOWN' => 'Due to a technical error, the payment cannot be guaranteed'
            ),
            'risk_control' => array (
                'CARD_FRAUD' => 'Card number control',
                'SUSPECT_COUNTRY' => 'Card country control',
                'IP_FRAUD' => 'IP address control',
                'CREDIT_LIMIT' => 'Card outstanding control',
                'BIN_FRAUD' => 'BIN code control',
                'ECB' => 'E-carte bleue control',
                'COMMERCIAL_CARD' => 'Commercial card control',
                'SYSTEMATIC_AUTO' => 'Systematic authorization card control',
                'INCONSISTENT_COUNTRIES' => 'Countries consistency control (IP, card, shipping address)',
                'NON_WARRANTY_PAYMENT' => 'Transfer of responsibility control',
                'SUSPECT_IP_COUNTRY' => 'IP country control'
            ),
            'risk_assessment' => array(
                'ENABLE_3DS' => '3D Secure enabled',
                'DISABLE_3DS' => '3D Secure disabled',
                'MANUAL_VALIDATION' => 'The transaction has been created via manual validation',
                'REFUSE' => 'The transaction is refused',
                'RUN_RISK_ANALYSIS' => 'Call for an external risk analyser',
                'INFORM' => 'A warning message appears'
            )
        ),

        'es' => array(
            'UNKNOWN' => 'Desconocido',

            'result' => array(
                'empty' => '',
                '00' => 'Accion procesada con exito',
                '02' => 'El mercante debe contactar el banco del portador',
                '05' => 'Accion rechazada',
                '17' => 'Accion cancelada',
                '30' => 'Error de formato de solicitutd',
                '96' => 'Problema technico'
            ),
            'auth_result' => array(
                'empty' => '',
                '00' => 'Transacción aceptada o procesada con exito',
                'UNKNOWN' => 'Vea los detalles de la transacción para más información'
            ),
            'warranty_result' => array(
                'empty' => 'Garantia de pago no aplicable',
                'YES' => 'El pago es garantizado',
                'NO' => 'El pago no es garantizado',
                'UNKNOWN' => 'Debido a un problema tecnico, el pago no puede ser garantizado'
            ),
            'risk_control' => array (
                'CARD_FRAUD' => 'Control de numero de tarjeta',
                'SUSPECT_COUNTRY' => 'Control de pais de tarjeta',
                'IP_FRAUD' => 'Control de direccion IP',
                'CREDIT_LIMIT' => 'Control de saldo de vivo de tarjeta',
                'BIN_FRAUD' => 'Control de codigo BIN',
                'ECB' => 'Control de E-carte bleue',
                'COMMERCIAL_CARD' => 'Control de tarjeta comercial',
                'SYSTEMATIC_AUTO' => 'Control de tarjeta a autorizacion sistematica',
                'INCONSISTENT_COUNTRIES' => 'Control de coherencia de pais (IP, tarjeta, direccion de envio)',
                'NON_WARRANTY_PAYMENT' => 'Control de transferencia de responsabilidad',
                'SUSPECT_IP_COUNTRY' => 'Control del pais de la IP'
            ),
            'risk_assessment' => array(
                'ENABLE_3DS' => '3D Secure activado',
                'DISABLE_3DS' => '3D Secure desactivado',
                'MANUAL_VALIDATION' => 'La transaccion ha sido creada con validacion manual',
                'REFUSE' => 'La transaccion ha sido rechazada',
                'RUN_RISK_ANALYSIS' => 'Llamada a un analisador de riesgos exterior',
                'INFORM' => 'Un mensaje de advertencia aparece'
            )
        ),

        'pt' => array (
            'UNKNOWN' => 'Desconhecido',

            'result' => array (
                'empty' => '',
                '00' => 'Ação realizada com sucesso',
                '02' => 'O comerciante deve contactar o banco do portador',
                '05' => 'Ação recusada',
                '17' => 'Ação cancelada',
                '30' => 'Erro no formato dos dados',
                '96' => 'Erro técnico durante o pagamento'
            ),
            'auth_result' => array (
                'empty' => '',
                '00' => 'Transação aprovada ou tratada com sucesso',
                'UNKNOWN' => 'Veja os detalhes da transação para mais informações'
            ),
            'warranty_result' => array (
                'empty' => 'Garantia de pagamento não aplicável',
                'YES' => 'O pagamento foi garantido',
                'NO' => 'O pagamento não foi garantido',
                'UNKNOWN' => 'Devido à un erro técnico, o pagamento não pôde ser garantido'
            ),
            'risk_control' => array (
                'CARD_FRAUD' => 'Card number control',
                'SUSPECT_COUNTRY' => 'Card country control',
                'IP_FRAUD' => 'IP address control',
                'CREDIT_LIMIT' => 'Card outstanding control',
                'BIN_FRAUD' => 'BIN code control',
                'ECB' => 'E-carte bleue control',
                'COMMERCIAL_CARD' => 'Commercial card control',
                'SYSTEMATIC_AUTO' => 'Systematic authorization card control',
                'INCONSISTENT_COUNTRIES' => 'Countries consistency control (IP, card, shipping address)',
                'NON_WARRANTY_PAYMENT' => 'Transfer of responsibility control',
                'SUSPECT_IP_COUNTRY' => 'IP country control'
            ),
            'risk_assessment' => array(
                'ENABLE_3DS' => '3D Secure enabled',
                'DISABLE_3DS' => '3D Secure disabled',
                'MANUAL_VALIDATION' => 'The transaction has been created via manual validation',
                'REFUSE' => 'The transaction is refused',
                'RUN_RISK_ANALYSIS' => 'Call for an external risk analyser',
                'INFORM' => 'A warning message appears'
            )
        ),

        'de' => array (
            'UNKNOWN' => 'Unbekannt',

            'result' => array (
                'empty' => '',
                '00' => 'Aktion erfolgreich ausgeführt',
                '02' => 'Der Händler muss die Bank des Karteninhabers kontaktieren',
                '05' => 'Aktion abgelehnt',
                '17' => 'Aktion abgebrochen',
                '30' => 'Fehler im Format der Anfrage',
                '96' => 'Technischer Fehler bei der Zahlung'
            ),
            'auth_result' => array (
                'empty' => '',
                '00' => 'Zahlung durchgeführt oder mit Erfolg bearbeitet',
                'UNKNOWN' => 'Weitere Informationen finden Sie in den Transaktionsdetails'
            ),
            'warranty_result' => array (
                'empty' => 'Zahlungsgarantie nicht anwendbar',
                'YES' => 'Die Zahlung ist garantiert',
                'NO' => 'Die Zahlung ist nicht garantiert',
                'UNKNOWN' => 'Die Zahlung kann aufgrund eines technischen Fehlers nicht gewährleistet werden'
            ),
            'risk_control' => array (
                'CARD_FRAUD' => 'Card number control',
                'SUSPECT_COUNTRY' => 'Card country control',
                'IP_FRAUD' => 'IP address control',
                'CREDIT_LIMIT' => 'Card outstanding control',
                'BIN_FRAUD' => 'BIN code control',
                'ECB' => 'E-carte bleue control',
                'COMMERCIAL_CARD' => 'Commercial card control',
                'SYSTEMATIC_AUTO' => 'Systematic authorization card control',
                'INCONSISTENT_COUNTRIES' => 'Countries consistency control (IP, card, shipping address)',
                'NON_WARRANTY_PAYMENT' => 'Transfer of responsibility control',
                'SUSPECT_IP_COUNTRY' => 'IP country control'
            ),
            'risk_assessment' => array(
                'ENABLE_3DS' => '3D Secure enabled',
                'DISABLE_3DS' => '3D Secure disabled',
                'MANUAL_VALIDATION' => 'The transaction has been created via manual validation',
                'REFUSE' => 'The transaction is refused',
                'RUN_RISK_ANALYSIS' => 'Call for an external risk analyser',
                'INFORM' => 'A warning message appears'
            )
        )
    );

    // Relative to https://payzen.io/fr-FR/form-payment/error-code/sitemap.html
    public static $FORM_ERRORS = array(
        '00' => 'SIGNATURE',
        '01' => 'VERSION',
        '02' => 'SITE_ID',
        '03' => 'TRANS_ID',
        '04' => 'TRANS_DATE',
        '05' => 'VALIDATION_MODE',
        '06' => 'CAPTURE_DELAY',
        '07' => 'PAYMENT_CONFIG',
        '08' => 'PAYMENT_CARDS',
        '09' => 'AMOUNT',
        '10' => 'CURRENCY',
        '11' => 'CTX_MODE',
        '12' => 'LANGUAGE',
        '13' => 'ORDER_ID',
        '14' => 'ORDER_INFO',
        '15' => 'CUST_EMAIL',
        '16' => 'CUST_ID',
        '17' => 'CUST_TITLE',
        '18' => 'CUST_NAME',
        '19' => 'CUST_ADDRESS',
        '20' => 'CUST_ZIP',
        '21' => 'CUST_CITY',
        '22' => 'CUST_COUNTRY',
        '23' => 'CUST_PHONE',
        '24' => 'URL_SUCCESS',
        '25' => 'URL_REFUSED',
        '26' => 'URL_REFERRAL',
        '27' => 'URL_CANCEL',
        '28' => 'URL_RETURN',
        '29' => 'URL_ERROR',
        '30' => 'IDENTIFIER',
        '31' => 'CONTRIB',
        '32' => 'THEME_CONFIG',
        '33' => 'URL_CHECK',
        '34' => 'REDIRECT_SUCCESS_TIMEOUT',
        '35' => 'REDIRECT_SUCCESS_MESSAGE',
        '36' => 'REDIRECT_ERROR_TIMEOUT',
        '37' => 'REDIRECT_ERROR_MESSAGE',
        '38' => 'RETURN_POST_PARAMS',
        '39' => 'RETURN_GET_PARAMS',
        '40' => 'CARD_NUMBER',
        '41' => 'CARD_EXP_MONTH',
        '42' => 'CARD_EXP_YEAR',
        '43' => 'CARD_CVV',
        '44' => 'CARD_CVV_AND_BIRTH',
        '46' => 'PAGE_ACTION',
        '47' => 'ACTION_MODE',
        '48' => 'RETURN_MODE',
        '49' => 'ABSTRACT_INFO',
        '50' => 'SECURE_MPI',
        '51' => 'SECURE_ENROLLED',
        '52' => 'SECURE_CAVV',
        '53' => 'SECURE_ECI',
        '54' => 'SECURE_XID',
        '55' => 'SECURE_CAVV_ALG',
        '56' => 'SECURE_STATUS',
        '60' => 'PAYMENT_SRC',
        '61' => 'USER_INFO',
        '62' => 'CONTRACTS',
        '63' => 'RECURRENCE',
        '64' => 'RECURRENCE_DESC',
        '65' => 'RECURRENCE_AMOUNT',
        '66' => 'RECURRENCE_REDUCED_AMOUNT',
        '67' => 'RECURRENCE_CURRENCY',
        '68' => 'RECURRENCE_REDUCED_AMOUNT_NUMBER',
        '69' => 'RECURRENCE_EFFECT_DATE',
        '70' => 'EMPTY_PARAMS',
        '71' => 'AVAILABLE_LANGUAGES',
        '72' => 'SHOP_NAME',
        '73' => 'SHOP_URL',
        '74' => 'OP_COFINOGA',
        '75' => 'OP_CETELEM',
        '76' => 'BIRTH_DATE',
        '77' => 'CUST_CELL_PHONE',
        '79' => 'TOKEN_ID',
        '80' => 'SHIP_TO_NAME',
        '81' => 'SHIP_TO_STREET',
        '82' => 'SHIP_TO_STREET2',
        '83' => 'SHIP_TO_CITY',
        '84' => 'SHIP_TO_STATE',
        '85' => 'SHIP_TO_ZIP',
        '86' => 'SHIP_TO_COUNTRY',
        '87' => 'SHIP_TO_PHONE_NUM',
        '88' => 'CUST_STATE',
        '89' => 'REQUESTOR',
        '90' => 'PAYMENT_TYPE',
        '91' => 'EXT_INFO',
        '92' => 'CUST_STATUS',
        '93' => 'SHIP_TO_STATUS',
        '94' => 'SHIP_TO_TYPE',
        '95' => 'SHIP_TO_SPEED',
        '96' => 'SHIP_TO_DELIVERY_COMPANY_NAME',
        '97' => 'PRODUCT_LABEL',
        '98' => 'PRODUCT_TYPE',
        '99' => 'OTHER',
        '100' => 'PRODUCT_REF',
        '101' => 'PRODUCT_QTY',
        '102' => 'PRODUCT_AMOUNT',
        '103' => 'PAYMENT_OPTION_CODE',
        '104' => 'CUST_FIRST_NAME',
        '105' => 'CUST_LAST_NAME',
        '106' => 'SHIP_TO_FIRST_NAME',
        '107' => 'SHIP_TO_LAST_NAME',
        '108' => 'TAX_AMOUNT',
        '109' => 'SHIPPING_AMOUNT',
        '110' => 'INSURANCE_AMOUNT',
        '111' => 'PAYMENT_ENTRY',
        '112' => 'CUST_ADDRESS_NUMBER',
        '113' => 'CUST_DISTRICT',
        '114' => 'SHIP_TO_STREET_NUMBER',
        '115' => 'SHIP_TO_DISTRICT',
        '116' => 'SHIP_TO_USER_INFO',
        '118' => 'STEP_UP_DATA',
        '201' => 'PAYMENT_AUTH_CODE',
        '202' => 'PAYMENT_CUST_CONTRACT_NUM',
        '888' => 'ROBOT_REQUEST',
        '999' => 'SENSITIVE_DATA'
    );
}
