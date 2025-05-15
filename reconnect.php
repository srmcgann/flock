<?
  require_once('db.php');
  require_once('functions.php');

  $input = json_decode(file_get_contents('php://input'));
  if($data = $input->{'playerData'}){
    $arena = mysqli_real_escape_string($link, $data->{'ar'});
    if(!!$arena){
      $id = mysqli_real_escape_string($link, $data->{'id'});
      $slug = decToAlpha($id);
      $timestamp = date("Y-m-d H:i:s");
      
      $sql = "SELECT * FROM  sessions WHERE arena LIKE BINARY \"$arena\"";
      $res = mysqli_query($link, $sql);
      if(mysqli_num_rows($res)){
        if(mysqli_num_rows($res) >= $maxPlayersPerArena){
          echo json_encode("full");
          return
        }
        $row = mysqli_fetch_assoc($res);
        $level = intval($row['level']);
      }      
      
      $sanitizedData = mysqli_real_escape_string($link, json_encode($data));
      $sql = "INSERT INTO sessions (id, slug, data, seen, arena, level)
              VALUES($id, \"$slug\", \"$sanitizedData\", \"$timestamp\", \"$arena\", $level)";
      mysqli_query($link, $sql);
      
      // maintenance //
      $players = [];
      $sql = "SELECT id, slug, seen, data FROM sessions WHERE arena LIKE BINARY \"$arena\"";
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