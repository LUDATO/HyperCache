<?php

 /*
  * The MIT License
  *
  * Copyright 2015 LUDATO.
  *
  * Permission is hereby granted, free of charge, to any person obtaining a copy
  * of this software and associated documentation files (the "Software"), to deal
  * in the Software without restriction, including without limitation the rights
  * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  * copies of the Software, and to permit persons to whom the Software is
  * furnished to do so, subject to the following conditions:
  *
  * The above copyright notice and this permission notice shall be included in
  * all copies or substantial portions of the Software.
  *
  * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  * THE SOFTWARE.
  */

 namespace Ludato;

 /**
  * LUDATO HyperCache
  * @author David Kostal
  * @license http://opensource.org/licenses/MIT MIT
  */
 Class HyperCache {

     private $fullDirectory;
     private $fullFilename;
     private $cacheDirectory;
     private $pagePath;

     /**
      *
      * @var boolean Determines if development texts are shown
      */
     public static $dev;

     /**
      * @var string Valid PHP code (without opening tags) to be prepended to cache
      */
     public $prepend;

     /**
      * @var string Valid PHP code (without opening tags) to be appended to cache
      */
     public $append;

     /**
      *
      * @var boolean Determines if prepended code should be eval()'ed when generating cache (alternative - put code before startCache())
      */
     public $evalPrepend;

     /**
      *
      * @var boolean Determines if appended code should be eval()'ed when generating cache (alternative - put code after saveCache())
      */
     public $evalAppend;

     /**
      * Saving params and configuring
      * Note: Paths are saved url-encoded
      * @param string $directory Path to directory for caching
      * @param string|null $page Full page name, including extension (if you want it determined automatically using PHP_SELF, use NULL)
      * @param string $param Parameters of caching (eg. for details.php it would be ID). You can combine whatever you want here
      */
     function __construct($directory, $page = NULL, $param = "default") {
         if (ini_get('short_open_tag')) {
             ini_set('short_open_tag', '0'); //disabling short tags, in order to work with XML
         }
         mb_internal_encoding("UTF-8");
         if ($page === NULL) {
             $pathinfo = pathinfo($_SERVER['PHP_SELF']);
             $page = $pathinfo['basename'];
         }
         /*
           if (mb_strpos($directory, "./") === 1) {
           $directory = "./" . $directory;
           } */
         if (mb_substr($directory, -1) === DIRECTORY_SEPARATOR) {
             $directory = rtrim($directory, DIRECTORY_SEPARATOR);
         }
         $this->fullDirectory = urlencode($directory) . DIRECTORY_SEPARATOR . urlencode($page) . DIRECTORY_SEPARATOR . urlencode($param) . DIRECTORY_SEPARATOR;

         $this->cacheDirectory = urlencode($directory);
         $this->pagePath = urlencode($directory) . DIRECTORY_SEPARATOR . urlencode($page) . DIRECTORY_SEPARATOR;

         $this->fullFilename = $this->fullDirectory . "cached" . ".php";
     }

     /**
      * Starts caching
      * @return void
      */
     function startCache() {
         if ($this->evalPrepend) {
             eval($this->prepend);
         }
         flush();
         ob_start();
     }

     /**
      * Saves cached file and flushes the buffer (shows output)
      * @return boolean
      */
     function saveCache() {
         if (is_writable($this->fullDirectory)) {
             
         } else {
             $dirmade = @mkdir($this->fullDirectory, 0777, TRUE);
         }

         if (!$dirmade) {
             throw new \Exception("Caching directory not writeable", 0);
         }

         if (!is_file($this->cacheDirectory . DIRECTORY_SEPARATOR . ".htaccess")) {
             $hw = fopen($this->cacheDirectory . DIRECTORY_SEPARATOR . ".htaccess", "w");
             $htaccess = <<<EOT
Order deny,allow
Deny from all
EOT;
             fputs($hw, $htaccess, strlen($htaccess));
             fclose($hw);
         }

         $page = ob_get_contents();
         ob_end_clean();
         //$time = time();
         @unlink($this->fullFilename);
         //chmod($file, 0777);
         $fw = fopen($this->fullFilename, "w");
         $page_prepend_append = "<?php " . $this->prepend . " ?>" . $page . "<?php " . $this->append . " ?>";
         fputs($fw, $page_prepend_append, strlen($page_prepend_append));
         fclose($fw);
         echo $page;
         if ($this->dev) {
             echo 'cache generated';
         }
         if ($this->evalAppend) {
             eval($this->append);
         }
         return TRUE;
     }

     /**
      * Loads cached file
      * @return void
      */
     function getCache() {
         if ($this->dev) {
             $time_pre = microtime(true);
         }
         require $this->fullFilename;
         if ($this->dev) {
             $time_post = microtime(true);
             $exec_time = ($time_post - $time_pre) * 1000;
             echo "Loaded from cache ({$this->fullFilename} in {$exec_time} ms)"; //DEBUG!
         }
     }

     /**
      * Shows cache and if file is cached, otherwise starts caching and executing the rest of the file
      * @return void
      */
     function autoLoadCache() {
         if ($this->isCached()) {
             $this->getCache();
             die();
         } else {
             $this->startCache();
         }
     }

     /**
      * Automaically ending and saving cache
      * @return void
      */
     function autoEndCache() {
         try {
             $this->saveCache();
         } catch (Exception $e) {
             echo "\n" . $e . "\n";
             die();
         }
     }

     /**
      * Returns if file is cached
      * @return boolean Returns true if cached, else returns false.
      */
     function isCached() {
         if (is_readable($this->fullFilename)) {
             return TRUE;
         } else {
             return FALSE;
         }
     }

     /**
      * Deletes cache for current page and params (eg. You have CMS and publish a new article. You need to purge just article_list.php, not article_details.php. If user publishes comment, purge just article_details.php with param of article name/ID)
      * @return void
      */
     function purgeCurrent() {
         $this->recursiveDelete($this->fullDirectory);
     }

     /**
      * Purges all files specified cacing directory
      * @param string $dir Path to caching directory
      * @return void
      */
     function purgeAll() {
         $this->recursiveDelete($this->cacheDirectory);
     }

     /**
      * Purges file for current page (eg. You have CMS and publish a new article. You need to purge just article_list.php, not article_details.php. If user publishes comment, purge just article_details.php with param of article name/ID)
      * @return void
      */
     function purgePage() {
         $this->recursiveDelete($this->pagePath);
     }

     /**
      * Purge cache from specified directory, for specific page (and params)
      * @param string $directory Caching directory
      * @param string $page Page
      * @param string $param Params (opt.)
      * @deprecated 0.1.0
      */
     function purgeCustom($directory, $page, $param = NULL) {
         if (!(mb_substr($directory, -1) === DIRECTORY_SEPARATOR)) {
             $directory = $directory . DIRECTORY_SEPARATOR;
         }
         if ($param !== NULL) {

             if (!(mb_substr($param, -1) === DIRECTORY_SEPARATOR)) {
                 $param = $param . DIRECTORY_SEPARATOR;
             }

             $dir = urlencode($directory) . urlencode($page) . DIRECTORY_SEPARATOR . urlencode($param);
         } else {
             $dir = urlencode($directory) . urlencode($page) . DIRECTORY_SEPARATOR;
         }
         $this->recursiveDelete($dir);
     }

     /**
      * Delete a file or recursively delete a directory
      * @param string $str Path to file or directory
      * @return void
      */
     private function recursiveDelete($str) {
         try {
             if (is_file($str)) {
                 return @unlink($str);
             } elseif (is_dir($str)) {
                 $scan = glob(rtrim($str, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');
                 foreach ($scan as $path) {
                     $this->recursiveDelete($path);
                 }
                 return @rmdir($str);
             } else {
                 throw new \Exception("Invalid path", 0);
             }
         } catch (Exception $e) {
             echo $e;
             echo "Internal HyperCache error";
             die();
         }
     }

 }

 /**
  * @todo finih this
  * Sanitizes folder/file name
  * @param string $str Input string
  * @return string Sanitized string
  */
 /*
   function cache_filename_sanitize($str) {
   preg_replace("/[\/]")
   }
  */
