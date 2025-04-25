<?php 
//ini_set('display_errors', '1');
//ini_set('display_startup_errors', '1');
error_reporting(0);
  $db_user  = 'user';
  $db_pass  = 'chrome57253';
  $db_host  = 'localhost';
  $db       = "flock";
  $port     = '3306';
  $link     = mysqli_connect($db_host,$db_user,$db_pass,$db,$port);
?>
