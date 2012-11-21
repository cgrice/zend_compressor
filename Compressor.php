<?php

class Application_View_Helper_Compressor extends Zend_View_Helper_HeadScript
{
    private $_storage;

    public function __construct()
    {
        parent::__construct();
        $this->setStorage(new Compressor_Storage_S3($this, 'AKIAIUPGXQPJFDJNUKLA', 'iTZ8IrM+dWeSGWMP6ZlbbwDQNre/ZKWftEYASzlf', 'compress-test', 'text/css'));
    }


    public function setStorage(Compressor_Storage $storage)
    {
        $this->_storage = $storage;
        return $this;
    }

    public function getStorage()
    {
        return $this->_storage;
    }

    protected function hash(Zend_View_Helper_Placeholder_Container_Standalone $items)
    {
        $scripts = (string)$items;
        $hash = sha1($scripts);
        return $hash;
    }

    protected function compress(Zend_View_Helper_Placeholder_Container_Standalone $items)
    {
        ob_start();
        foreach($items as $item)
        {
            if($this->_isValid($item))
            {
                if(isset($item->attributes) && array_key_exists('src', $item->attributes))
                {
                    $src = $item->attribtues['src'];
                }
                else if(isset($item->href))
                {
                    $src = $item->href;
                }
                $url = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $src;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                curl_close($ch);
                echo $response;
            }
        }
        $content = ob_get_contents();
        ob_clean();

        return $content;
    }

    protected function _isValid($value)
    {
        if ((!$value instanceof stdClass)
            || !isset($value->type)
            || (!isset($value->source) && !isset($value->attributes) && !isset($value->href)))
        {
            return false;
        }

        return true;
    }


}


abstract class Compressor_Storage 
{
    public function __construct(Zend_View_Helper_Placeholder_Container_Standalone $helper)
    {
        $this->output_template = $helper->getOutputTemplate();
    }

    abstract public function store($content, $where);
}


class Compressor_Storage_File extends Compressor_Storage
{
    public function store($content, $where)
    {
        $dir = str_replace(basename($where), '', $where);
        if(!file_exists(APPLICATION_PATH . '/../public' . $dir))
        {
            mkdir(APPLICATION_PATH . '/../public' . $dir, 0755);
        }
        file_put_contents(APPLICATION_PATH . '/../public' . $where, $content);

        $script_tag = str_replace('{$file}', $where, $this->output_template);
        return $script_tag;
    }
}

class Compressor_Storage_S3 extends Compressor_Storage
{
    public function __construct($helper, $aws_key, $aws_secret, $aws_bucket, $file_type)
    {
        $this->aws_key = $aws_key;
        $this->aws_secret = $aws_secret;
        $this->aws_bucket = $aws_bucket;
        $this->file_type = $file_type;
        parent::__construct($helper);
    }

    public function store($content, $where)
    {
        $aws_url = $this->upload($content, $where);
        $script_tag = str_replace('{$file}', $aws_url, $this->output_template);
        return $script_tag;
    }

    private function upload($content, $where)
    {
        $aws_key = 'AKIAIUPGXQPJFDJNUKLA';
        $aws_secret = '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';

        $aws_bucket = $this->aws_bucket;
        $aws_object = $where;
        $file_length = strlen($content);
        $file_type = 'text/css';

        $dt = gmdate('r'); // GMT based timestamp 

        $string2sign = "PUT


        {$dt}
        /{$aws_bucket}";

        // preparing HTTP PUT query
        $query = "PUT /{$aws_bucket}/{$aws_object} HTTP/1.1
        Host: s3.amazonaws.com
        x-amz-acl: public-read
        Connection: keep-alive
        Content-Type: {$file_type}
        Content-Length: {$file_length}
        Date: $dt
        Authorization: AWS {$aws_key}:".$this->amazon_hmac($string2sign)."\n\n";
        $query .= $content;


        // opening HTTP connection to Amazon S3
        $fp = fsockopen("s3.amazonaws.com", 80, $errno, $errstr, 30);
        if (!$fp) {
            die("$errstr ($errno)\n");
        }
        $resp = $this->sendREST($fp, $query, true);
        var_dump($resp);exit;
        if (strpos($resp, '<Error>') !== false)
        {
            die($resp);
        }
        fclose($fp);

        return "http://s3.amazonaws.com/{$aws_bucket}/{$aws_object}";
    }


    // Sending HTTP query and receiving, with trivial keep-alive support
    private function sendREST($fp, $q, $debug = false)
    {
        if ($debug) echo "\nQUERY<<{$q}>>\n";

        fwrite($fp, $q);
        $r = '';
        $check_header = true;
        while (!feof($fp)) {
            $tr = fgets($fp, 256);
            if ($debug) echo "\nRESPONSE<<{$tr}>>"; 
            $r .= $tr;

            if (($check_header)&&(strpos($r, "\r\n\r\n") !== false))
            {
                // if content-length == 0, return query result
                if (strpos($r, 'Content-Length: 0') !== false)
                    return $r;
            }

            // Keep-alive responses does not return EOF
            // they end with \r\n0\r\n\r\n string
            if (substr($r, -7) == "\r\n0\r\n\r\n")
                return $r;
        }
        return $r;
    }

    // hmac-sha1 code START
    // hmac-sha1 function:  assuming key is global $aws_secret 40 bytes long
    // read more at http://en.wikipedia.org/wiki/HMAC
    // warning: key($aws_secret) is padded to 64 bytes with 0x0 after first function call 
    private function amazon_hmac($stringToSign) 
    {
        // helper function binsha1 for amazon_hmac (returns binary value of sha1 hash)
        if (!function_exists('binsha1'))
        { 
            if (version_compare(phpversion(), "5.0.0", ">=")) { 
                function binsha1($d) { return sha1($d, true); }
            } else { 
                function binsha1($d) { return pack('H*', sha1($d)); }
            }
        }

        global $aws_secret;

        if (strlen($aws_secret) == 40)
            $aws_secret = $aws_secret.str_repeat(chr(0), 24);

        $ipad = str_repeat(chr(0x36), 64);
        $opad = str_repeat(chr(0x5c), 64);

        $hmac = binsha1(($aws_secret^$opad).binsha1(($aws_secret^$ipad).$stringToSign));
        return base64_encode($hmac);
    }
    // hmac-sha1 code END 
}