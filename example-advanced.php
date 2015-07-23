<?php

 require 'Ludato/HyperCache.php';

 $cache = new Ludato\HyperCache("hypercache", "advanced.php", $_GET['user'], FALSE);
 $cache->dev = TRUE;
 

 if ($cache->isCached()) {
     $cache->getCache();
     die();
 }

//won't save to cache, will just show before generating
 echo "<br> On cache generation (before code): " . time() . "<br>";
 
 $cache->prepend = 'echo "Prepended (always dynamic): " . time();';

 $cache->append = 'echo "Appended (always dynamic): " . time();';


 $cache->startCache();


 echo sprintf("User is %s", $_GET["user"]);

 for ($i = 1; $i <= 100; $i++) {
     echo " " . $i . ",";
 }


$cache->saveCache();

echo "<br> On cache generation (after code): " . time() . "<br>";

die();