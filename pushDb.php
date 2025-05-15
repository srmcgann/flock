<?
$file = <<<'FILE'
<?php 
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);

  $db_user  = 'user';
  $db_pass  = 'chrome57253';
  $db_host  = 'localhost';
  $db       = "flock";
  $port     = '3306';
  $link     = mysqli_connect($db_host,$db_user,$db_pass,$db,$port);

  $maxPlayersPerArena = 8;
  
?>

FILE;
file_put_contents('../../flock/db.php', $file);
?>