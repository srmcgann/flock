<?
$file = <<<'FILE'
<?
  require_once('db.php');
  require_once('functions.php');

  $input = json_decode(file_get_contents('php://input'));
  if($data = $input->{'playerData'}){
    $arena = mysqli_real_escape_string($link, $data->{'ar'});
    if(!!$arena){
      $id = mysqli_real_escape_string($link, $data->{'id'});
      $timestamp = date("Y-m-d H:i:s");
      $sanitizedData = mysqli_real_escape_string($link, json_encode($data));
      $sql = "UPDATE sessions SET seen = \"$timestamp\",
              data = \"$sanitizedData\"
              WHERE id = $id AND arena LIKE BINARY \"$arena\"";
      mysqli_query($link, $sql);
      
      // maintenance //
      $players = [];
      $sql = "SELECT id, slug, seen, data FROM sessions
                WHERE arena LIKE BINARY \"$arena\"";
      $res = mysqli_query($link, $sql);
      for($i=0; $i<mysqli_num_rows($res); ++$i){
        $row = mysqli_fetch_assoc($res);
        $seen = date(strtotime($row['seen']));
        $now = time();
        if($now - $seen > 8){
          endSession($row['slug']);
        } else {
          array_push($players, $row['data']);
        }
      }
      /////////////////
      
      echo json_encode($players);
    } else {
      echo '[false]';
    }
  } else {
    echo '[false]';
  }
?>

FILE;
file_put_contents('../../flock/sync.php', $file);
?>