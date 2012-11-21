<?php

class Zend_View_Helper_CompressHeadScript extends Zend_View_Helper_Compressor
{
    public function getOutputTemplate()
    {
        return '<script type="text/javascript" src="{$file}"></script>';
    }
    /**
     * Proxy for the different named head script
     */
    public function compressHeadScript()
    {
        $compress_output = TRUE;
        if($compress_output == TRUE)
        {
            $cache = Zend_Registry::get('cache');

            $this->headScript = $this->view->headScript();

            $hash = $this->hash($this->headScript);
            $compressed_scripts = $cache->load("js_{$hash}");


            if($compressed_scripts)
            {
                return $compressed_scripts;
            }
            else
            {
                $where = '/CACHE/js/' . $hash . '.js';
                $content = $this->compress($this->headScript);
                $output = $this->getStorage()->store($content, $where);
                $cache->save($output, "js_{$hash}");
                return $output;

            }
        }
        else
        {
            return $this->view->headScript();
        }
    }

   
}