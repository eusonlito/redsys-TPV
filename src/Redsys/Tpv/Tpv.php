<?php
namespace Redsys\Tpv;

use Exception;
use DOMDocument;
use DOMElement;
use Redsys\Messages\Messages;

class Tpv
{
    protected $options = array(
        'Version' => '0.1',
        'Environment' => 'test',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    );

    protected $option_prefix = 'Ds_Merchant_';

    protected $o_required = array(
        'Environment', 'Currency', 'Terminal', 'ConsumerLanguage',
        'MerchantCode', 'Key', 'SignatureVersion', 'MerchantName'
    );

    protected $o_optional = array(
        'UrlOK', 'UrlKO', 'TransactionType', 'MerchantURL', 'PayMethods', 'Titular'
    );

    protected $environment = '';
    protected $environments = array(
        'test' => 'https://sis-t.redsys.es:25443/sis',
        'real' => 'https://sis.redsys.es/sis'
    );

    protected $values = array();

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
        $hiddens = '';

        foreach ($this->getFormValues() as $key => $value) {
            $hiddens .= $this->getInputHidden($key, $value, false);
        }

        return $hiddens;
    }

    public function getFormValues()
    {
        if (empty($this->values)) {
            throw new Exception('Form fields must be initialized previously');
        }

        return array(
            'Ds_SignatureVersion' => $this->options['SignatureVersion'],
            'Ds_MerchantParameters' => $this->getMerchantParametersEncoded(),
            'Ds_Signature' => $this->getValuesSignature()
        );
    }

    public function getInputHidden($name, $value, $prefix = true)
    {
        return "\n".'<input type="hidden" name="'.($prefix ? 'Ds_' : '').$name.'" value="'.$value.'" />';
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

    protected function setXmlValues()
    {
        $xml = array('DS_Version' => $this->options['Version']);

        foreach ($this->values as $key => $value) {
            $xml[strtoupper($key)] = $value;
        }

        return $xml;
    }

    protected function xmlArray2string($xml)
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

    protected function setValueDefault(array $options, $option)
    {
        $code = $this->option_prefix.$option;

        if (isset($options[$option])) {
            $this->values[$code] = $options[$option];
        } elseif (isset($this->options[$option])) {
            $this->values[$code] = $this->options[$option];
        }

        return $this;
    }

    protected function setValues(array $options)
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

        $amount = preg_replace('/[^0-9,\.]/', '', $amount);

        // Remove pretty number format: 1.234,56 > 1234,56
        if (preg_match('/[\d]+\.[\d]+,[\d]+/', $amount)) {
            $amount = str_replace('.', '', $amount);
        }

        // Remove pretty number format: 1,234.56 > 1234.56
        if (preg_match('/[\d]+,[\d]+\.[\d]+/', $amount)) {
            $amount = str_replace(',', '', $amount);
        }

        // Remove comma as decimal separator: 1234,56 > 1234.56
        if (strpos($amount, ',') !== false) {
            $amount = str_replace(',', '.', $amount);
        }

        $amount = floatval($amount);

        // Truncate float from second decimal (not rounded): 1.119 > 1.11
        if (($point = strpos($amount, '.')) !== false) {
            $amount = substr($amount, 0, $point + 1 + 2);
        }

        // Set as Redsys valid amount value: 12.34 = 1234
        // Avoid to use intval, round or sprintf without remove decimals before
        // because this functions applies a round.
        return sprintf('%03d', preg_replace('/\.[0-9]+$/', '', $amount * 100));
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

    public function getTransactionParameters(array $post)
    {
        return json_decode(base64_decode(strtr($post['Ds_MerchantParameters'], '-_', '+/')), true);
    }

    protected function checkTransactionError(array $data, $prefix)
    {
        if ($error = (isset($data[$prefix.'ErrorCode']) ? $data[$prefix.'ErrorCode'] : false)) {
            $this->throwErrorByCode($error);
        }
    }

    protected function checkTransactionResponse(array $data, $prefix)
    {
        $response = isset($data[$prefix.'Response']) ? $data[$prefix.'Response'] : null;

        if (is_null($response) || (strlen($response) === 0)) {
            throw new Exception('Response code is empty (no length)');
        }

        $value = (int)$response;

        if (($value < 0) || (($value > 99) && ($value !== 900))) {
            $this->throwErrorByCode($response);
        }
    }

    protected function checkTransactionSignature($signature, $postSignature)
    {
        if ($signature !== strtr($postSignature, '-_', '+/')) {
            throw new Exception(sprintf('Signature not valid (%s != %s)', $signature, $postSignature));
        }
    }

    protected function throwErrorByCode($code)
    {
        $message = Messages::getByCode($code);

        throw new Exception($message ? $message['message'] : '', (int)$code);
    }
}
