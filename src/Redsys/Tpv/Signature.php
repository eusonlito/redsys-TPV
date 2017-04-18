<?php
namespace Redsys\Tpv;

use Exception;

class Signature
{
    public static function fromValues($prefix, array $values, $key)
    {
        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'TransactionType', 'MerchantURL');

        return self::calculate($prefix, $fields, $values, $key);
    }

    public static function fromTransaction($prefix, array $values, $key)
    {
        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'Response');

        return self::calculate($prefix, $fields, $values, $key);
    }

    public static function fromTransactionXML($parameters, $order, $key)
    {
        return self::MAC256($parameters, self::encryptKey($order, $key));
    }

    public static function fromXML($xml, $key)
    {
        preg_match('#<DS_MERCHANT_ORDER>([^<]+)</DS_MERCHANT_ORDER>#i', $xml, $order);

        if (empty($order[1])) {
            throw new Exception('Can not be extracted Order from XML string');
        }

        return self::MAC256($xml, self::encryptKey($order[1], $key));
    }

    private static function calculate($prefix, array $fields, array $values, $key)
    {
        foreach ($fields as $field) {
            if (!isset($values[$prefix.$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and required', $field));
            }
        }

        $key = self::encryptKey($values[$prefix.'Order'], $key);

        return self::MAC256(base64_encode(json_encode($values)), $key);
    }

    private static function encrypt3DES($message, $key)
    {
        if (function_exists('openssl_encrypt')) {
            return self::encrypt3DESOpenSSL($message, $key);
        }

        return self::encrypt3DESMcrypt($message, $key);
    }

    private static function encrypt3DESOpenSSL($message, $key)
    {
        $l = ceil(strlen($message) / 8) * 8;
        $message = $message.str_repeat("\0", $l - strlen($message));

        return substr(openssl_encrypt($message, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, "\0\0\0\0\0\0\0\0"), 0, $l);
    }

    private static function encrypt3DESMcrypt($message, $key)
    {
        $iv = implode(array_map('chr', array(0, 0, 0, 0, 0, 0, 0, 0)));

        return mcrypt_encrypt(MCRYPT_3DES, $key, $message, MCRYPT_MODE_CBC, $iv);
    }

    private static function encryptKey($order, $key)
    {
        return self::encrypt3DES($order, base64_decode($key));
    }

    private static function MAC256($string, $key)
    {
        return base64_encode(hash_hmac('sha256', $string, $key, true));
    }
}
