<?
  require_once('db.php');
  
   function alphaToDec($val){
    $pow=0;
    $res=0;
    while($val!=""){
      $cur=$val[strlen($val)-1];
      $val=substr($val,0,strlen($val)-1);
      $mul=ord($cur)<58?$cur:ord($cur)-(ord($cur)>96?87:29);
      $res+=$mul*pow(62,$pow);
      $pow++;
    }
    return $res;
  }

  function decToAlpha($val){
    $alphabet="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $ret="";
    while($val){
      $r=floor($val/62);
      $frac=$val/62-$r;
      $ind=(int)round($frac*62);
      $ret=$alphabet[$ind].$ret;
      $val=$r;
    }
    return $ret==""?"0":$ret;
  }

  function getSessionID($len = 16){
    global $link;
    do{
      $ret = '';
      for($i = 0; $i<$len; ++$i) $ret .= rand(1,9);
      $sql = "SELECT id FROM sessions WHERE id = $ret";
      $res = mysqli_query($link, $sql);
    }while(mysqli_num_rows($res));
    return $ret;
  }
  
  function genName(){
    $names = explode(' ', str_replace("\n", '', file_get_contents('words.txt')));
    $newName = '';
    $usedIDs = [];
    for($i=0; $i<2; $i++){
      do{
        $id = rand(0, sizeof($names)-1);
      }while(array_search($id, $usedIDs) !== false);
      array_push($usedIDs, $id);
      $newName .= $names[$id] . ' ';
    }
    return trim($newName);
  }
  
  function createArena($len = 16){
    global $link;
    do{
      $ret = '';
      for($i = 0; $i<$len; ++$i) $ret .= rand(1,9);
      $arena = decToAlpha($ret);
      $sql = "SELECT arena FROM sessions WHERE arena LIKE BINARY \"$arena\"";
      $res = mysqli_query($link, $sql);
    }while(mysqli_num_rows($res));
    return $arena;
  }
  
  function fullCurrentURL() {
    return ($_SERVER['HTTPS'] ? 'https' : 'http') .
            '://' . $_SERVER['SERVER_NAME'] .
            $_SERVER['REQUEST_URI'];
  }

  function getLevel(){
    global $link;
    $url = fullCurrentURL();
    $params = explode('&', parse_url($url)['query']);
    forEach($params as $param){
      $pair = explode('=', $param);
      $key = $pair[0];
      $val = $pair[1];
      if($key == 'arena'){
        $val = mysqli_real_escape_string($link, $val);
        $sql = "SELECT * FROM sessions WHERE arena LIKE BINARY \"$val\"";
        $res = mysqli_query($link, $sql);
        if(mysqli_num_rows($res)){
          $row = mysqli_fetch_assoc($res);
          return intval($row['level']);
        }
      }
    }
    return '';
  }
  
  function beginSession($data){
    global $link, $maxPlayersPerArena;
    if(isset($data->{'ar'}) && !!$data->{'ar'}){
      $arena = mysqli_real_escape_string($link, $data->{'ar'});
      $sql = "SELECT * FROM  sessions WHERE arena LIKE BINARY \"$arena\"";
      $res = mysqli_query($link, $sql);
      if(mysqli_num_rows($res)){
        if(mysqli_num_rows($res) >= $maxPlayersPerArena){
          return json_encode("full");
        }
        $row = mysqli_fetch_assoc($res);
        $level = intval($row['level']);
      } else {
        return json_encode("no arena");
      }
    } else {
      $arena = createArena();
      if(isset($data->{'lv'}) && !!$data->{'lv'}){
        $level = intval(mysqli_real_escape_string($link, $data->{'lv'}));
      }else{
        $level = 1;
      }
    }
    $newID = getSessionID();
    $slug = decToAlpha($newID);
    $newName = genName();
    $data->{'id'} = $newID;
    $data->{'slug'} = $slug;
    $data->{'name'} = $newName;
    $data->{'ar'} = $arena;
    $data->{'lv'} = $level;
    $sanitizedData = mysqli_real_escape_string($link, json_encode($data));
    $timestamp = date("Y-m-d H:i:s");
    $sql = "INSERT INTO sessions (id, slug, data, arena, level, seen)
            VALUES($newID, \"$slug\", \"$sanitizedData\", \"$arena\", $level, \"$timestamp\")";
    if($res = mysqli_query($link, $sql)){
      return json_encode($data);
    }else{
      return 'false';
    }
  }
  
  function endSession($slug){
    global $link;
    $sql = "DELETE FROM sessions WHERE slug LIKE BINARY \"$slug\"";
    mysqli_query($link, $sql);
  }
  
  function sync($data = '{}'){
    global $link;
    $sql = "UPDATE sessions SET data = ";
  }
?>