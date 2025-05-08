<?
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
echo genName() . "\n";
?>
