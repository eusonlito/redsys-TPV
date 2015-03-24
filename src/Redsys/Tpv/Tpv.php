<?php
namespace Redsys\Tpv;

use Exception;
use DOMDocument, DOMElement;
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

    private $o_required = array('Environment', 'Currency', 'Terminal', 'ConsumerLanguage', 'MerchantCode', 'Key', 'MerchantName', 'Titular');
    private $o_optional = array('UrlOK', 'UrlKO', 'TransactionType', 'MerchantURL', 'PayMethods');

    private $environment = '';
    private $environments = array(
        'test' => 'https://sis-t.redsys.es:25443/sis',
        'real' => 'https://sis.redsys.es/sis'
    );

    private $values = array();
    private $hidden = array();

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
        $this->hidden = $this->values = array();

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

        $this->setValue($options, 'MerchantData');
        $this->setValue($options, 'Order');
        $this->setValue($options, 'ProductDescription');
        $this->setValue($options, 'Amount');

        $options['MerchantSignature'] = $this->getSignature();

        $this->setValue($options, 'MerchantSignature');

        $this->setHiddensFromValues();

        return $this;
    }

    private function setHiddensFromValues()
    {
        return $this->hidden = $this->values;
    }

    public function getFormHiddens()
    {
        if (empty($this->hidden)) {
            throw new Exception('Form fields must be initialized previously');
        }

        $html = '';

        foreach ($this->hidden as $field => $value) {
            $html .= "\n".'<input type="hidden" name="'.$field.'" value="'.$value.'" />';
        }

        return trim($html);
    }

    public function sendXml(array $options)
    {
        $this->values = array();

        if (isset($options['Order'])) {
            $options['Order'] = $this->getOrder($options['Order']);
        }

        if (isset($options['Amount'])) {
            $options['Amount'] = $this->getAmount($options['Amount']);
        }

        $this->setValueDefault($options, 'MerchantCode');
        $this->setValueDefault($options, 'MerchantName');
        $this->setValueDefault($options, 'MerchantData');
        $this->setValueDefault($options, 'Currency');
        $this->setValueDefault($options, 'Terminal');
        $this->setValueDefault($options, 'TransactionType');
        $this->setValueDefault($options, 'Order');
        $this->setValueDefault($options, 'Amount');

        $this->setValue(array('MerchantURL' => ''), 'MerchantURL');

        $options['MerchantSignature'] = $this->getSignature();

        $this->setValueDefault($options, 'MerchantURL');
        $this->setValueDefault($options, 'MerchantSignature');

        $xml = $this->setXmlFromValues();

        $Curl = new Curl(array(
            'base' => $this->getPath('')
        ));

        $Curl->setHeader(CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        return $Curl->post('/operaciones', array(), 'entrada='.$this->xmlArray2string($xml));
    }

    private function setXmlFromValues()
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
        $doc->formatOutput = true;

        $root = $doc->createElement('DATOSENTRADA');

        foreach ($xml as $key => $value) {
            $root->appendChild((new DOMElement($key, $value)));
        }

        $doc->appendChild($root);

        return (string) $doc->saveXML();
    }

    public function xmlString2array($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml)), true);
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

    public function getOrder($order)
    {
        if (preg_match('/^[0-9]+$/', $order)) {
            $order = sprintf('%012d', $order);
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
        } elseif (preg_match('/[\.,]/', $amount)) {
            return str_replace(array('.', ','), '', $amount);
        } else {
            return ($amount * 100);
        }
    }

    public function getSignature()
    {
        $prefix = $this->option_prefix;
        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'TransactionType', 'MerchantURL');
        $key = '';

        foreach ($fields as $field) {
            if (!isset($this->values[$prefix.$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to create signature key', $field));
            }

            $key .= $this->values[$prefix.$field];
        }

        return strtoupper(sha1($key.$this->options['Key']));
    }

    public function checkTransaction(array $post)
    {
        $prefix = 'Ds_';

        if (empty($post) || empty($post[$prefix.'Signature'])) {
            throw new Exception('_POST data is empty');
        }

        $error = isset($post[$prefix.'ErrorCode']) ? $post[$prefix.'ErrorCode'] : null;

        if ($error) {
            if ($message = Messages::getByCode($error)) {
                throw new Exception(sprintf('TPV returned error code %s: %s', $error, $message['message']));
            } else {
                throw new Exception(sprintf('TPV returned unknown error code %s', $error));
            }
        }

        $error = isset($post[$prefix.'Response']) ? $post[$prefix.'Response'] : null;

        if (is_null($error) || (strlen($response) === 0)) {
            throw new Exception('Response code is empty (no length)');
        }

        if (((int)$response < 0) || ((int)$response > 99)) {
            if ($message = Messages::getByCode($response)) {
                throw new Exception(sprintf('Response code is Transaction Denied %s: %s', $response, $message['message']));
            } else {
                throw new Exception(sprintf('Response code is unknown %s', $response));
            }
        }

        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'Response');
        $key = '';

        foreach ($fields as $field) {
            if (empty($post[$prefix.$field])) {
                throw new Exception(sprintf('Field <strong>%s</strong> is empty and is required to verify transaction'));
            }

            $key .= $post[$prefix.$field];
        }

        $signature = strtoupper(sha1($key.$this->options['Key']));

        if ($signature !== $post[$prefix.'Signature']) {
            throw new Exception(sprintf('Signature not valid (%s != %s)', $signature, $post[$prefix.'Signature']));
        }

        $response = (int) $post[$prefix.'Response'];

        if (($response >= 100) && ($response !== 900)) {
            throw new Exception(sprintf('Transaction error. Code: <strong>%s</strong>', $post[$prefix.'Response']));
        }

        return $post[$prefix.'Signature'];
    }
}
