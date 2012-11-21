<?php

class Zend_View_Helper_CompressHeadLink extends Application_View_Helper_Compressor
{
    public function getOutputTemplate()
    {
        return '<link rel="stylesheet" href="{$file}" />';
    }
    /**
     * Proxy for the different named head script
     */
    public function compressHeadLink()
    {
        $this->head = $this->view->headLink();

        $compress_output = TRUE;
        if($compress_output == TRUE)
        {
            try {
                $cache = Zend_Registry::get('cache');
            }
            catch(Exception $e)
            {
                throw new Exception('CompressHeadLink requires Zend_Cache - please ensure this is available in the registry. See setup section of Compressor docs: ');
            }


            $hash = $this->hash($this->head);
            $compressed_scripts = $cache->load("css_{$hash}");

            if($compressed_scripts)
            {
                return $compressed_scripts;
            }
            else
            {
                $where = '/CACHE/css/' . $hash . '.css';
                $content = $this->compress($this->head);
                $output = $this->getStorage()->store($content, $where);
                $cache->save($output, "css_{$hash}");
                return $output;

            }
        }
        else
        {
            return $this->view->headLink();
        }
    }

   
}