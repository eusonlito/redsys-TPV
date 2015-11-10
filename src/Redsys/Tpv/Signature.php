<?php
namespace Redsys\Tpv;

use Exception;

class Signature
{
    public static function fromValues($prefix, $values, $key)
    {
        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'TransactionType', 'MerchantURL');

        return self::calculate($prefix, $fields, $values, $key);
    }

    public static function fromTransaction($prefix, $values, $key)
    {
        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'Response');

        return self::calculate($prefix, $fields, $values, $key);
    }

    private static function calculate($prefix, $fields, $values, $key)
    {
        foreach ($fields as $field) {
            if (!isset($values[$prefix.$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and required', $field));
            }
        }

        $key = self::encrypt3DES($values[$prefix.'Order'], base64_decode($key));

        return base64_encode(hash_hmac('sha256', base64_encode(json_encode($values)), $key, true));
    }

    private static function encrypt3DES($message, $key)
    {
        $iv = implode(array_map('chr', array(0, 0, 0, 0, 0, 0, 0, 0)));

        return mcrypt_encrypt(MCRYPT_3DES, $key, $message, MCRYPT_MODE_CBC, $iv);
    }
}