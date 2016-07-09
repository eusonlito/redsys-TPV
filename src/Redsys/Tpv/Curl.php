<?php
namespace Redsys\Tpv;

use DOMDocument;
use DOMXPath;
use Exception;

class Curl
{
    private $settings = array();

    private $connect;
    private $html;
    private $info;

    public function __construct(array $settings)
    {
        if (empty($settings['base'])) {
            throw new Exception('"base" option is empty');
        }

        if (empty($settings['cookie'])) {
            $settings['cookie'] = tempnam(sys_get_temp_dir(), microtime(true));
        }

        if (empty($settings['debug'])) {
            $settings['debug'] = false;
        }

        $this->settings = $settings;

        return $this->connect();
    }

    public function connect()
    {
        if (is_resource($this->connect)) {
            $this->close();
        }

        $this->connect = curl_init();

        $header = array(
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36',
            'Connection: keep-alive',
            'Cache-Control: no-cache',
            'Expect:'
        );

        curl_setopt($this->connect, CURLOPT_HEADER, false);
        curl_setopt($this->connect, CURLOPT_HTTPHEADER, $header);

        if (!ini_get('open_basedir') && !filter_var(ini_get('safe_mode'), FILTER_VALIDATE_BOOLEAN)) {
            curl_setopt($this->connect, CURLOPT_FOLLOWLOCATION, true);
        }

        curl_setopt($this->connect, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->connect, CURLOPT_COOKIEJAR, $this->settings['cookie']);
        curl_setopt($this->connect, CURLOPT_COOKIEFILE, $this->settings['cookie']);
        curl_setopt($this->connect, CURLOPT_COOKIESESSION, false);
        curl_setopt($this->connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->connect, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->connect, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->connect, CURLOPT_MAXREDIRS, 10);

        return $this;
    }

    public function close()
    {
        curl_close($this->connect);

        return $this;
    }

    public function setHeader($option, $value)
    {
        curl_setopt($this->connect, $option, $value);
    }

    public function exec($url, $get = array(), $post = array())
    {
        $url = $this->settings['base'].str_replace($this->settings['base'], '', $url);

        if ($get && is_array($get)) {
            $url .= strstr($url, '?') ? '&' : '?';
            $url .= http_build_query($get);
        }

        if ($this->settings['debug']) {
            if (empty($post)) {
                Debug::d($url);
            } else {
                Debug::d(array('url' => $url, 'post' => $post));
            }
        }

        curl_setopt($this->connect, CURLOPT_URL, $url);

        $this->html = curl_exec($this->connect);
        $this->info = curl_getinfo($this->connect);

        $this->setBase();

        return $this->html;
    }

    public function get($url, $get = array())
    {
        return $this->exec($url, $get);
    }

    public function post($url, $get = array(), $post = array())
    {
        curl_setopt($this->connect, CURLOPT_POST, true);
        curl_setopt($this->connect, CURLOPT_POSTFIELDS, $post);

        $response = $this->exec($url, $get, $post);

        curl_setopt($this->connect, CURLOPT_POST, false);

        return $response;
    }

    public function setBase()
    {
        if (empty($this->settings['autobase'])) {
            return;
        }

        $base = parse_url($this->info['url']);
        $this->settings['base'] = $base['scheme'].'://'.$base['host'];
    }

    public function getXPath()
    {
        if (empty($this->html)) {
            return new DOMXPath(new DOMDocument());
        }

        libxml_use_internal_errors(true);

        $DOM = new DOMDocument();
        $DOM->recover = true;
        $DOM->preserveWhiteSpace = false;
        $DOM->loadHtml($this->html);

        $XPath = new DOMXPath($DOM);

        libxml_use_internal_errors(false);

        return $XPath;
    }

    public function getHtml()
    {
        return $this->html;
    }

    public function getInfo()
    {
        return $this->info;
    }
}
