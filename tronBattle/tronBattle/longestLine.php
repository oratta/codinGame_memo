<?php
/**
  * 1) 空いている方向の内、一番長いところへ向かう
  * issue1) 袋小路に突っ込むことがあ
  *
 **/
class Map
{
  const WIDTH = 30;
  const HEIGHT = 20;
  const MAX_PLAYER = 4;

  //座標情報 0:空いている、1:埋まっている
  private $mapInfo = array();
  //プレイヤーごとの座標情報 0:通っていない、1:通っている
  private $playerMapInfo = array();

  //生存情報 1:生きてる, 0:死んでる
  private $playerAliveInfo = array();

  //自分の今の位置 (x,y)
  private $selfPosition = array();

  public function __construct()
  {
    $this->initMapInfo();
    $this->initPlayerMapInfo(self::MAX_PLAYER);
    $this->initPlayerAliveInfo(self::MAX_PLAYER);
  }

  /**
   * @param $count 人数
   */
  private function initPlayerMapInfo($count)
  {
    for ($i=0;$i<$count;++$i){
      $this->playerMapInfo[$i] = array();
      for ($j=0;$j<self::HEIGHT;++$j){
        $this->playerMapInfo[$i][$j] = array_fill(0, self::WIDTH,0);
      }
    }
    return true;
  }
  /**
   * @param $count 人数
   */
  private function initPlayerAliveInfo($count)
  {
    $this->playerAliveInfo = array_fill(0, $count,1);
    return true;
  }

  /**
   * マップの初期化
   * 壁もマップに含める
   *
  */
  private function initMapInfo()
  {
    $this->mapInfo = array();

    for ($i=-1;$i<=self::HEIGHT;++$i){
      $this->mapInfo[$i] = array();
      if ($i === -1 || $i === self::HEIGHT){
        $defaultValue = 1;
      }
      else{
        $defaultValue = 0;
      }
      for ($j=-1;$j<=self::WIDTH;++$j){
        if ($j === -1 || $j === self::WIDTH){
          $this->mapInfo[$i][$j] = 1;
        }
        else {
          $this->mapInfo[$i][$j] = $defaultValue;
        }
      }
    }
    return true;
  }

 /**
   * 与えられた配列に応じて$this->mapInfoのフラグを反転する
  */
 public function reverseMap(array $reverseArray)
 {
   foreach($reverseArray as $y => $xArray){
     foreach ($xArray as $x => $value){
       if ($value === 1){
         $this->mapInfo[$y][$x] = 0;
       }
     }
   }
 }

  public function updateMap($id,$x,$y, $isSelf)
  {
    //死んだ場合、Map から消す
    if ($x === -1 && $this->playerAliveInfo[$id]){
      error_log(var_export("die : $id", true));
      $this->reverseMap($this->playerMapInfo[$id]);
      $this->playerAliveInfo[$id] = 0;
      return;
    }

    //mapInfo 更新
    $this->mapInfo[$y][$x] = 1;
    // error_log(var_export("update to1 x:{}$x}", true));
    // error_log(var_export("update to1 y:{}$y}", true));


    //playerMapInfo 更新
    if(!isset($this->playerMapInfo[$id])){
      $this->playerMapInfo[$id] = array();
    }
    if(!isset($this->playerMapInfo[$id][$y])){
      $this->playerMapInfo[$id][$y] = array();
    }
    $this->playerMapInfo[$id][$y][$x] = 1;

    if ($isSelf){
      $this->selfPosition = ['x' =>$x, 'y' =>$y];
    }
  }

  public function getRoot()
  {
    $diffArray = $this->getDiffArray($this->selfPosition['x'],$this->selfPosition['y']);
    error_log(var_export($diffArray, true));
    arsort($diffArray);
    reset($diffArray);
    error_log(var_export($diffArray, true));
    return key($diffArray);
  }

  /**
    *
    */
  private function getDiffArray($x,$y)
  {
    error_log(var_export($x, true));
    error_log(var_export($y, true));
    $rowArray = $this->mapInfo[$y];
    // error_log(var_export($rowArray, true));

    $fillArray = array_keys($rowArray, 1);
    error_log(var_export($fillArray, true));

    list($left, $right) = $this->getDistanceInfo($fillArray, $x);

    $fillArray = array();
    foreach ($this->mapInfo as $indexY => $rowArray){
      foreach ($rowArray as $indexX => $column){
        if ($column === 1 && $indexX === $x){
          $fillArray[] = $indexY;
        }
      }
    }
    error_log(var_export($fillArray, true));

    list($up, $down) = $this->getDistanceInfo($fillArray, $y);

    return ['RIGHT'=>$right, 'LEFT'=>$left, 'UP'=>$up, 'DOWN'=>$down];
  }

  /**
    * 1次元配列の任意の位置で
    */
  private function getDistanceInfo($lineInfo, $x)
  {
    //selfPosition - index の最も小さい正のindexは左との距離
    //selfPosition - index の最も大きい負のindexは右との距離
    $default = 100000;
    $rightNearestDiff = $default;
    $leftNearestDiff = $default;
    foreach ($lineInfo as $index){
      //左との距離
      if ($x > $index){
        $leftDiff = $x - $index;
        if ($leftDiff < $leftNearestDiff){
          $leftNearestDiff = $leftDiff;
        }
      }
      //右との距離
      else if ($x < $index){
        $rightDiff = $index - $x;
        if ($rightDiff < $rightNearestDiff){
          $rightNearestDiff = $rightDiff;
        }
      }
    }
    $leftNearestDiff = ($leftNearestDiff === $default) ? -1 : $leftNearestDiff;
    $rightNearestDiff = ($rightNearestDiff === $default) ? -1 : $rightNearestDiff;

    return array($leftNearestDiff, $rightNearestDiff);
  }
}

$map = new Map();
// game loop
while (TRUE)
{
    fscanf(STDIN, "%d %d",
        $N, // total number of players (2 to 4).
        $P // your player number (0 to 3).
    );
    for ($i = 0; $i < $N; $i++)
    {
        fscanf(STDIN, "%d %d %d %d",
            $X0, // starting X coordinate of lightcycle (or -1)
            $Y0, // starting Y coordinate of lightcycle (or -1)
            $X1, // starting X coordinate of lightcycle (can be the same as X0 if you play before this player)
            $Y1 // starting Y coordinate of lightcycle (can be the same as Y0 if you play before this player)
        );
        $map->updateMap($i,$X1,$Y1, $i===$P);
    }

    // Write an action using echo(). DON'T FORGET THE TRAILING \n
    // To debug (equivalent to var_dump): error_log(var_export($var, true));
    echo $map->getRoot($P),"\n";
}
?>
