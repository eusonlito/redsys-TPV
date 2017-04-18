<?php
namespace Redsys\Tpv;

use Exception;
use DOMDocument;
use DOMElement;
use Redsys\Messages\Messages;

class Tpv
{
    private $options = array(
        'Version' => '0.1',
        'Environment' => 'test',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    );

    private $option_prefix = 'Ds_Merchant_';

    private $o_required = array('Environment', 'Currency', 'Terminal', 'ConsumerLanguage', 'MerchantCode', 'Key', 'SignatureVersion', 'MerchantName', 'Titular');
    private $o_optional = array('UrlOK', 'UrlKO', 'TransactionType', 'MerchantURL', 'PayMethods');

    private $environment = '';
    private $environments = array(
        'test' => 'https://sis-t.redsys.es:25443/sis',
        'real' => 'https://sis.redsys.es/sis'
    );

    private $values = array();

    public function __construct(array $options)
    {
        return $this->setOption($options);
    }

    public function setOption($option, $value = null)
    {
        if (is_array($option)) {
            $options = $option;
        } elseif ($value !== null) {
            $options = array($option => $value);
        } else {
            throw new Exception(sprintf('Option <strong>%s</strong> can not be empty', $option));
        }

        $options = array_merge($this->options, $options);

        foreach ($this->o_required as $option) {
            if (empty($options[$option])) {
                throw new Exception(sprintf('Option <strong>%s</strong> is required', $option));
            }

            $this->options[$option] = $options[$option];
        }

        foreach ($this->o_optional as $option) {
            if (array_key_exists($option, $options)) {
                $this->options[$option] = $options[$option];
            }
        }

        if (isset($options['environments'])) {
            $this->environments = array_merge($this->environments, $options['environments']);
        }

        $this->setEnvironment($options['Environment']);

        return $this;
    }

    public function getOption($key = null)
    {
        return $key ? $this->options[$key] : $this->options;
    }

    public function setEnvironment($mode)
    {
        $this->environment = $this->getEnvironments($mode);

        return $this;
    }

    public function getPath($path = '/realizarPago')
    {
        return $this->environment.$path;
    }

    public function getEnvironments($key = null)
    {
        if (empty($this->environments[$key])) {
            $envs = implode('|', array_keys($this->environments));
            throw new Exception(sprintf('Environment <strong>%s</strong> is not valid [%s]', $key, $envs));
        }

        return $key ? $this->environments[$key] : $this->environments;
    }

    public function setFormHiddens(array $options)
    {
        $this->values = array();

        if (isset($options['Order'])) {
            $options['Order'] = $this->getOrder($options['Order']);
        }

        if (isset($options['Amount'])) {
            $options['Amount'] = $this->getAmount($options['Amount']);
        }

        $this->setValueDefault($options, 'Currency');
        $this->setValueDefault($options, 'MerchantCode');
        $this->setValueDefault($options, 'Terminal');
        $this->setValueDefault($options, 'Titular');
        $this->setValueDefault($options, 'TransactionType');
        $this->setValueDefault($options, 'MerchantName');
        $this->setValueDefault($options, 'MerchantURL');
        $this->setValueDefault($options, 'ConsumerLanguage');
        $this->setValueDefault($options, 'UrlOK');
        $this->setValueDefault($options, 'UrlKO');
        $this->setValueDefault($options, 'PayMethods');
        $this->setValueDefault($options, 'Identifier');

        $this->setValues($options);

        return $this;
    }

    public function getFormHiddens()
    {
        if (empty($this->values)) {
            throw new Exception('Form fields must be initialized previously');
        }

        return $this->getInputHidden('SignatureVersion', $this->options['SignatureVersion'])
            .$this->getInputHidden('MerchantParameters', $this->getMerchantParametersEncoded())
            .$this->getInputHidden('Signature', $this->getValuesSignature());
    }

    public function getInputHidden($name, $value)
    {
        return "\n".'<input type="hidden" name="Ds_'.$name.'" value="'.$value.'" />';
    }

    public function getMerchantParameters()
    {
        return $this->values;
    }

    public function getMerchantParametersEncoded()
    {
        return base64_encode(json_encode($this->getMerchantParameters()));
    }

    public function sendXml(array $options)
    {
        $this->values = array();

        $options['Order'] = $this->getOrder($options['Order']);
        $options['Amount'] = $this->getAmount($options['Amount']);

        $this->setValueDefault($options, 'MerchantCode');
        $this->setValueDefault($options, 'MerchantURL');
        $this->setValueDefault($options, 'Currency');
        $this->setValueDefault($options, 'Terminal');
        $this->setValueDefault($options, 'Identifier');
        $this->setValueDefault($options, 'DirectPayment');

        $this->setValues($options);

        $Curl = new Curl(array(
            'base' => $this->getPath('')
        ));

        $Curl->setHeader(CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        return $Curl->post('/operaciones', array(
            'entrada' => $this->xmlArray2string($this->setXmlValues())
        ));
    }

    private function setXmlValues()
    {
        $xml = array('DS_Version' => $this->options['Version']);

        foreach ($this->values as $key => $value) {
            $xml[strtoupper($key)] = $value;
        }

        return $xml;
    }

    private function xmlArray2string($xml)
    {
        $doc = new DOMDocument();

        $data = $doc->createElement('DATOSENTRADA');

        foreach ($xml as $key => $value) {
            if ((strpos($value, '?') !== false) && (strpos($value, '&') !== false) && (strpos($value, '&amp;') === false)) {
                $value = str_replace('&', '&amp;', $value);
            }

            $data->appendChild((new DOMElement(strtoupper($key), $value)));
        }

        $root = $doc->createElement('REQUEST');

        $root->appendChild($data);
        $root->appendChild(new DOMElement('DS_SIGNATUREVERSION', $this->options['SignatureVersion']));
        $root->appendChild(new DOMElement('DS_SIGNATURE', Signature::fromXML((string)$doc->saveXML($data), $this->options['Key'])));

        $doc->appendChild($root);

        return (string) $doc->saveXML();
    }

    public function xmlString2array($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml)), true);
    }

    public function checkTransactionXml(array $post)
    {
        $prefix = 'Ds_';

        if (empty($post) || empty($post[$prefix.'Signature']) || empty($post[$prefix.'MerchantParameters'])) {
            throw new Exception('_POST data is empty');
        }

        $data = $this->getTransactionParameters($post);
        $data = $this->xmlString2array($data['datos']);

        if (empty($data)) {
            throw new Exception('_POST data can not be decoded');
        }

        if (empty($data[$prefix.'Order'])) {
            throw new Exception('Order not found');
        }

        $this->checkTransactionError($data, $prefix);
        $this->checkTransactionResponse($data, $prefix);

        $signature = Signature::fromTransactionXML($post[$prefix.'MerchantParameters'], $data[$prefix.'Order'], $this->options['Key']);

        $this->checkTransactionSignature($signature, $post[$prefix.'Signature']);

        return array_merge($post, $data);
    }

    private function setValueDefault(array $options, $option)
    {
        $code = $this->option_prefix.$option;

        if (isset($options[$option])) {
            $this->values[$code] = $options[$option];
        } elseif (isset($this->options[$option])) {
            $this->values[$code] = $this->options[$option];
        }

        return $this;
    }

    private function setValue(array $options, $option)
    {
        if (isset($options[$option])) {
            $this->values[$this->option_prefix.$option] = $options[$option];
        }

        return $this;
    }

    private function setValues(array $options)
    {
        foreach ($options as $key => $value) {
            $key = $this->option_prefix.$key;

            if (!isset($this->values[$key])) {
                $this->values[$key] = $value;
            }
        }

        return $this;
    }

    public function getOrder($order)
    {
        if (preg_match('/^[0-9]+$/', $order)) {
            $order = sprintf('%012s', $order);
        }

        $len = strlen($order);

        if (($len < 4) || ($len > 12)) {
            throw new Exception('Order code must have more than 4 digits and less than 12');
        } elseif (!preg_match('/^[0-9]{4}[0-9a-zA-Z]{0,8}$/', $order)) {
            throw new Exception('First four order digits must be numbers and then only are allowed numbers and letters');
        }

        return $order;
    }

    public function getAmount($amount)
    {
        if (empty($amount)) {
            return '000';
        }

        if (preg_match('/[\d]+\.[\d]+,[\d]+/', $amount)) {
            $amount = str_replace('.', '', $amount);
        }

        if (strpos($amount, ',') !== false) {
            $amount = floatval(str_replace(',', '.', $amount));
        }

        return (round($amount, 2) * 100);
    }

    public function getValuesSignature()
    {
        return Signature::fromValues($this->option_prefix, $this->values, $this->options['Key']);
    }

    public function checkTransaction(array $post)
    {
        $prefix = 'Ds_';

        if (empty($post) || empty($post[$prefix.'Signature']) || empty($post[$prefix.'MerchantParameters'])) {
            throw new Exception('_POST data is empty');
        }

        $data = $this->getTransactionParameters($post);

        if (empty($data)) {
            throw new Exception('_POST data can not be decoded');
        }

        $this->checkTransactionError($data, $prefix);
        $this->checkTransactionResponse($data, $prefix);

        $signature = Signature::fromTransaction($prefix, $data, $this->options['Key']);

        $this->checkTransactionSignature($signature, $post[$prefix.'Signature']);

        return array_merge($post, array_map('urldecode', $data));
    }

    private function checkTransactionError(array $data, $prefix)
    {
        $error = isset($data[$prefix.'ErrorCode']) ? $data[$prefix.'ErrorCode'] : null;

        if (empty($error)) {
            return null;
        }

        if ($message = Messages::getByCode($error)) {
            throw new Exception(sprintf('TPV returned error code %s: %s', $error, $message['message']));
        } else {
            throw new Exception(sprintf('TPV returned unknown error code %s', $error));
        }
    }

    private function checkTransactionResponse(array $data, $prefix)
    {
        $response = isset($data[$prefix.'Response']) ? $data[$prefix.'Response'] : null;

        if (is_null($response) || (strlen($response) === 0)) {
            throw new Exception('Response code is empty (no length)');
        }

        if (((int)$response < 0) || (((int)$response > 99) && ($response !== 900))) {
            if ($message = Messages::getByCode($response)) {
                throw new Exception(sprintf('Response code is Transaction Denied %s: %s', $response, $message['message']));
            } else {
                throw new Exception(sprintf('Response code is unknown %s', $response));
            }
        }
    }

    private function checkTransactionSignature($signature, $postSignature)
    {
        if ($signature !== strtr($postSignature, '-_', '+/')) {
            throw new Exception(sprintf('Signature not valid (%s != %s)', $signature, $postSignature));
        }
    }

    public function getTransactionParameters(array $post)
    {
        return json_decode(base64_decode(strtr($post['Ds_MerchantParameters'], '-_', '+/')), true);
    }
}
