<?php
/**
  * 1) 空いている方向の内、一番長いところへ向かう
  * issue1) 袋小路に突っ込むことがあ
  *
  * 2) 壁への到達を優先する
  * 3) 直前の評価を優先する
  * 4) 袋小路回避
  *   xy系配列に加え、yx配列を作って更新するようにする
  *   上下列が1でうめつくされる場合に評価を1000倍にする(絶対選ばない)
  * ------アイデア-----
  * 複数壁がある場合壁の近い方を評価する
  * 埋まっている密度を評価に入れる
  * 　(今の座標から上は100個埋まっている、今の座標から下は90個埋まっている。下に行こう)
 **/
class Map
{
  const WIDTH       = 30;
  const HEIGHT      = 20;
  const MAX_PLAYER  = 4;
  const BLOCK_OTHER = 1;
  const BLOCK_OWN   = 2;
  const BLOCK_WALL  = 3;

  //直前のルート
  private $preRoot = 'LEFT';

  //座標情報 0:空いている、1:埋まっている
  //配列処理を軽くするためにYX座標系に加えXY座標系のクローンを持つ
  private $mapInfoYX = array();
  private $mapInfoXY = array();
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
    $this->mapInfoYX = array();
    $this->mapInfoXY = array();

    $this->mapInfoYX = array_fill(-1,self::HEIGHT+2,array());
    $this->mapInfoXY = array_fill(-1,self::WIDTH+2,array());
    for ($i=-1;$i<=self::HEIGHT;++$i){
      if ($i === -1 || $i === self::HEIGHT){
        $defaultValue = self::BLOCK_WALL;
      }
      else{
        $defaultValue = 0;
      }
      for ($j=-1;$j<=self::WIDTH;++$j){
        if ($j === -1 || $j === self::WIDTH){
          $this->mapInfoYX[$i][$j] = self::BLOCK_WALL;
          $this->mapInfoXY[$j][$i] = self::BLOCK_WALL;
        }
        else {
          $this->mapInfoYX[$i][$j] = $defaultValue;
          $this->mapInfoXY[$j][$i] = $defaultValue;
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
         $this->mapInfoYX[$y][$x] = 0;
         $this->mapInfoXY[$x][$y] = 0;
       }
     }
   }
 }

  /**
   * 特定の座標を1にする
   */
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
    $this->mapInfoYX[$y][$x] = $isSelf ? self::BLOCK_OWN : self::BLOCK_OTHER;
    $this->mapInfoXY[$x][$y] = $isSelf ? self::BLOCK_OWN : self::BLOCK_OTHER;

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

  /**
   * 壁に到達することを10倍に評価する
   */
  public function getRoot()
  {
    list($diffArray, $wallTypeArray) = $this->getDiffArray($this->selfPosition['x'],$this->selfPosition['y']);
    $diffArray = $this->envalueDiffArray($diffArray, $wallTypeArray);
    arsort($diffArray);
    reset($diffArray);
    error_log(var_export($diffArray, true));
    $bestRoot = key($diffArray);

    $this->preRoot = $bestRoot;
    return $bestRoot;
  }

  /**
    * 距離配列を評価する
    * @param array $diffArray 距離の配列
    * @param array $wallTypeArray 壁のタイプ
    */
  private function envalueDiffArray(array $diffArray, array $wallTypeArray)
  {
    /*
    * 袋小路評価
    * $diffをindex計算に使うので最初にやる必要がある
    */
    //袋小路を1/1000する
    foreach ($wallTypeArray as $vector => $wallType){
        $diffArray[$vector] *=
            $this->getDeadRootScore($this->selfPosition['x'],$this->selfPosition['y'],$diffArray[$vector], $vector);
    }

    /*
     * 密度評価
     * 密度の比を使って評価する
     */
    $densityInfo = $this->getDensityEnvalue($diffArray);
    foreach ($densityInfo as $vector => $value){
      $diffArray[$vector] *= $value;
    }


    //前回と同じルートを優先する
    $diffArray[$this->preRoot] *= 1.01;

    //障害が1の場合他人のルート
    //障害が2の場合自分
    //障害が3の場合壁
    foreach ($wallTypeArray as $vector => $wallType){
      switch ($wallType){
        case self::BLOCK_WALL :
          $diffArray[$vector] *= 10;
          break;
        default :
          break;
      }
    }
     return $diffArray;
  }

  /**
    * 現在位置からの東西南北の密度を評価する
    */
  private function getDensityEnvalue($x, $y)
  {

  }

  /**
    * 袋小路チェック
    * @param $x 今いるx座標
    * @param $y 今いるy座標
    * @param $diff 障害物までの距離
    * @param $vector 方角
    */
  private function getDeadRootScore($x, $y, $diff, $vector)
  {
    switch ($vector){
      case 'RIGHT' :
        $own = $y;
        $startIndex = $x+1;
        $endIndex   = $x + $diff;
        $target = $this->mapInfoYX;
        break;
      case 'LEFT' :
        $own = $y;
        $startIndex = $x-1;
        $endIndex   = $x - $diff;
        $isPositive = false;
        $target = $this->mapInfoYX;
        break;
      case 'UP' :
        $own = $x;
        $startIndex = $y-1;
        $endIndex   = $y - $diff;
        $isPositive = false;
        $target = $this->mapInfoXY;
        break;
      case 'DOWN' :
        $own = $x;
        $startIndex = $y+1;
        $endIndex   = $y + $diff;
        $isPositive = true;
        $target = $this->mapInfoXY;
        break;
      default :
        break;
    }
    error_log(var_export($vector, true));
    error_log(var_export("own:{$own},start:{$startIndex}, end:{$endIndex}", true));
    return $this->getDeadRootScoreCore($target, $own, $startIndex, $endIndex);
  }

  /**
    * 上下の行に0のセルが見つからなかったらisDead=true
    */
  private function getDeadRootScoreCore($target, $own, $startIndex, $endIndex)
  {
    $min = min($startIndex, $endIndex);
    $max = max($startIndex, $endIndex);
    error_log(var_export("min:{$min}/max:{$max},start:{$startIndex}/end:{$endIndex}", true));

    $aboveRoot = $target[$own+1];
    $underRoot = $target[$own-1];
    //$minが何故かずれる
    $aboveRoot = array_slice($aboveRoot, $min+1, $max-$min+1, true);
    $underRoot = array_slice($underRoot, $min+1, $max-$min+1, true);

    $aboveInfo = array_count_values($aboveRoot);
    $underInfo = array_count_values($underRoot);
    $aboveBlankCount = isset($aboveInfo[0]) ? $aboveInfo[0] : 0;
    $underBlankCount = isset($underInfo[0]) ? $underInfo[0] : 0;
    $blankCount = $aboveBlankCount+$underBlankCount;

    //一個も空いてなかったら
    if ($blankCount === 0){
        $returnValue = 0.0001;
    }
    //半分以上埋まっていたら
    else if (($max-$min+1) > $blankCount && $blankCount < 5){
        $returnValue = ($blankCount)/($max-$min+1)/2;
    }
    else{
      $returnValue = 1;
    }

    error_log(var_export($aboveRoot, true));
    error_log(var_export($underRoot, true));

    return $returnValue;
  }

  /**
    * 現在埋まっているセルの情報を返す
    */
  private function getFillArray(array $rowArray)
  {
    $fillArrayWall = array_keys($rowArray, self::BLOCK_WALL);
    $fillArrayOther = array_keys($rowArray, self::BLOCK_OTHER);
    $fillArrayOwn = array_keys($rowArray, self::BLOCK_OWN);
    return array_merge(array_merge($fillArrayWall,$fillArrayOther), $fillArrayOwn);

  }

  /**
    *
    */
  private function getDiffArray($x,$y)
  {
    error_log(var_export("x:{$x}", true));
    error_log(var_export("y:{$y}", true));
    $rowArray = $this->mapInfoYX[$y];
    list($left, $right) = $this->getDistanceInfo($rowArray, $x);

    $rowArray = $this->mapInfoXY[$x];
    list($up, $down) = $this->getDistanceInfo($rowArray, $y);

    return  [
              ['RIGHT'=>$right['diff'], 'LEFT'=>$left['diff'], 'UP'=>$up['diff'], 'DOWN'=>$down['diff']],
              ['RIGHT'=>$right['wallType'], 'LEFT'=>$left['wallType'], 'UP'=>$up['wallType'], 'DOWN'=>$down['wallType']]
            ];
  }

  /**
    * 1次元配列の任意の位置で両端との距離を返す
    * @param $rowArray 埋まっているセルの情報
    * @param $x 位置
    */
  private function getDistanceInfo(array $rowArray, $x)
  {
    $lineInfo = $this->getFillArray($rowArray);

    //selfPosition - index の最も小さい正のindexは左との距離
    //selfPosition - index の最も大きい負のindexは右との距離
    $default = 100000;
    $rightNearestDiff = $default;
    $rightNearestIndex = -2;
    $leftNearestDiff = $default;
    $leftNearestIndex = -2;
    foreach ($lineInfo as $index){
      //左との距離
      if ($x > $index){
        $leftDiff = $x - $index -1;
        if ($leftDiff < $leftNearestDiff){
          $leftNearestDiff = $leftDiff;
          $leftNearestIndex = $index;
        }
      }
      //右との距離
      else if ($x < $index){
        $rightDiff = $index - $x -1;
        if ($rightDiff < $rightNearestDiff){
          $rightNearestDiff = $rightDiff;
          $rightNearestIndex = $index;
        }
      }
    }
    $leftNearestDiffInfo  = [
      'diff' => $leftNearestDiff,
      'wallType' => $rowArray[$leftNearestIndex],
    ];
    $rightNearestDiffInfo = [
      'diff' => $rightNearestDiff,
      'wallType' => $rowArray[$rightNearestIndex],
    ];

    return array($leftNearestDiffInfo, $rightNearestDiffInfo);
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
        if($X0 !== -1)$map->updateMap($i,$X0,$Y0, $i===$P);
        $map->updateMap($i,$X1,$Y1, $i===$P);
    }

    // Write an action using echo(). DON'T FORGET THE TRAILING \n
    // To debug (equivalent to var_dump): error_log(var_export($var, true));
    echo $map->getRoot($P),"\n";
}
?>
