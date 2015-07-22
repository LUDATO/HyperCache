<?php

 require 'Ludato/HyperCache.php';

 $cache = new Ludato\HyperCache("hypercache", NULL, $_GET['user']);
 $cache->dev = TRUE;



 $cache->prepend = 'echo "Prepended (always dynamic): " . time();';

 $cache->append = 'echo "Appended (always dynamic): " . time();';
 
 $cache->evalAppend = TRUE;
 $cache->evalPrepend = TRUE;

 $cache->autoLoadCache();
 
 echo sprintf("User is %s", $_GET["user"]);

 for ($i = 1; $i <= 100; $i++) {
     echo " " . $i . ",";
 }

 $cache->autoEndCache();
