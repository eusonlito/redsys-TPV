<?php
namespace ANS\Redsys;

class Redsys
{
    private $options = array(
        'Environment' => 'test',
        'Currency' => '978',
        'Terminal' => '1',
        'ConsumerLanguage' => '001'
    );

    private $option_prefix = 'Ds_Merchant_';

    private $o_required = array('Environment', 'Currency', 'Terminal', 'ConsumerLanguage', 'MerchantCode', 'Key', 'MerchantName', 'Titular');
    private $o_optional = array('UrlOK', 'UrlKO', 'TransactionType', 'MerchantURL');

    private $environment = '';
    private $environments = array(
        'test' => 'https://sis-t.redsys.es:25443/sis/realizarPago',
        'real' => 'https://sis.sermepa.es/sis/realizarPago'
    );

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
            throw new \Exception(sprintf('Option <strong>%s</strong> can not be empty', $option));
        }

        $options = array_merge($this->options, $options);

        foreach ($this->o_required as $option) {
            if (empty($options[$option])) {
                throw new \Exception(sprintf('Option <strong>%s</strong> is required', $option));
            }

            $this->options[$option] = $options[$option];
        }

        foreach ($this->o_optional as $option) {
            if (array_key_exists($option, $options)) {
                $this->options[$option] = $options[$option];
            }
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
        if (empty($this->environments[$mode])) {
            $envs = implode('|', array_keys($this->environments));
            throw new \Exception(sprintf('Environment <strong>%s</strong> is not valid [%s]', $mode, $envs));
        }

        $this->environment = $this->environments[$mode];

        return $this;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getEnvironments($key = null)
    {
        return $key ? $this->environments[$key] : $this->environments;
    }

    public function setFormHiddens(array $options)
    {
        $this->hidden = array();

        if (isset($options['Order'])) {
            $options['Order'] = sprintf('%010d', $options['Order']);
        }

        if (isset($options['Amount'])) {
            $options['Amount'] = $this->getAmount($options['Amount']);
        }

        $this->setFormHiddenDefault($options, 'Currency');
        $this->setFormHiddenDefault($options, 'MerchantCode');
        $this->setFormHiddenDefault($options, 'Terminal');
        $this->setFormHiddenDefault($options, 'Titular');
        $this->setFormHiddenDefault($options, 'TransactionType');
        $this->setFormHiddenDefault($options, 'MerchantName');
        $this->setFormHiddenDefault($options, 'MerchantURL');
        $this->setFormHiddenDefault($options, 'ConsumerLanguage');
        $this->setFormHiddenDefault($options, 'UrlOK');
        $this->setFormHiddenDefault($options, 'UrlKO');

        $this->setFormHidden($options, 'MerchantData');
        $this->setFormHidden($options, 'Order');
        $this->setFormHidden($options, 'ProductDescription');
        $this->setFormHidden($options, 'Amount');

        $options['MerchantSignature'] = $this->getSignature();

        $this->setFormHidden($options, 'MerchantSignature');

        return $this;
    }

    private function setFormHiddenDefault(array $options, $option)
    {
        $code = $this->option_prefix.$option;

        if (isset($options[$option])) {
            $this->hidden[$code] = $options[$option];
        } elseif (isset($this->options[$option])) {
            $this->hidden[$code] = $this->options[$option];
        }

        return $this;
    }

    private function setFormHidden(array $options, $option)
    {
        if (isset($options[$option])) {
            $this->hidden[$this->option_prefix.$option] = $options[$option];
        }

        return $this;
    }

    public function getFormHiddens()
    {
        if (empty($this->hidden)) {
            throw new \Exception('Form fields must be initialized previously');
        }

        $html = '';

        foreach ($this->hidden as $field => $value) {
            $html .= "\n".'<input type="hidden" name="'.$field.'" value="'.$value.'" />';
        }

        return trim($html);
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
            if (!isset($this->hidden[$prefix.$field])) {
                throw new \Exception(sprintf('Field <strong>%s</strong> is empty and is required to create signature key', $field));
            }

            $key .= $this->hidden[$prefix.$field];
        }

        return strtoupper(sha1($key.$this->options['Key']));
    }

    public function checkTransaction(array $post)
    {
        $prefix = 'Ds_';

        if (empty($post) || empty($post[$prefix.'Signature'])) {
            throw new \Exception('_POST data is empty');
        }

        $fields = array('Amount', 'Order', 'MerchantCode', 'Currency', 'Response');
        $key = '';

        foreach ($fields as $field) {
            if (empty($post[$prefix.$field])) {
                throw new \Exception(sprintf('Field <strong>%s</strong> is empty and is required to verify transaction'));
            }

            $key .= $post[$prefix.$field];
        }

        $signature = strtoupper(sha1($key.$this->options['Key']));

        if ($signature !== $post[$prefix.'Signature']) {
            throw new \Exception(sprintf('Signature not valid (%s != %s)', $signature, $post[$prefix.'Signature']));
        }

        if ((int)$post[$prefix.'Response'] >= 100) {
            throw new \Exception(sprintf('Transaction error. Code: <strong>%s</strong>', $post[$prefix.'Response']));
        }

        return $post[$prefix.'Signature'];
    }
}
