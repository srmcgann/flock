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
    for($i=0; $i<3; $i++){
      do{
        $id = rand(0, sizeof($names)-1);
      }while(array_search($id, $usedIDs) !== false);
      array_push($usedIDs, $id);
      $newName .= $names[$id] . ' ';
    }
    return trim($newName);
  }
  
  function beginSession($data){
    global $link;
    $newID = getSessionID();
    $slug = decToAlpha($newID);
    $newName = genName();
    $data->{'id'} = $newID;
    $data->{'slug'} = $slug;
    $data->{'name'} = $newName;
    $sanitizedData = mysqli_real_escape_string($link, json_encode($data));
    $timestamp = date("Y-m-d H:i:s");
    $sql = "INSERT INTO sessions (id, slug, data, seen)
            VALUES($newID, \"$slug\", \"$sanitizedData\", \"$timestamp\")";
    if($res = mysqli_query($link, $sql)){
      return json_encode($data);
    }else{
      return 'false';
    }
  }
  
  function endSession($slug){
    global $link;
    $sid = alphaToDec($slug);
    $sql = "DELETE FROM sessions WHERE id = $sid";
    mysqli_query($link, $sql);
  }
  
  function sync($data = '{}'){
    global $link;
    $sql = "UPDATE sessions SET data = ";
  }
?>
