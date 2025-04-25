<?
  require_once('db.php');
  require_once('functions.php');

  $input = json_decode(file_get_contents('php://input'));
  if($data = $input->{'playerData'}){
    echo sync(json_encode($data));
  } else {
    echo '[false]';
  }
?>