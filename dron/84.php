<?php

function dump($value)
{
  error_log(var_export($value, true));
}
/**
  * Map
  * └ hasMany Zone
  *   └ belongsTo Player
  * └ hasMany Drone
  * 　└ belongsTo Player
  * ------------------------
  * ドローンの経路選択戦略
  * 　・コントロールを維持する
  * 　・自分一人でコントロールできる
  * 　・近くにある
  *
  * アイデア
  *   Zoneの半径にどのくらい
 **/

 class Map
 {
   private $zoneArray               = array();
   private $playerDroneArray        = array();
   private $enemyArray              = array();
   private $playerScoreArray        = array();
   private $playerId;
   private $playerCount;
   private $zoneCount;

   /**
    * @param $playerCount
    * @param $playerId
    * @param $droneCount 一人あたりのドローン数
    * @param $zoneCount
    *
    */
   public function __construct($playerCount, $playerId, $droneCount, $zoneArray)
   {
     $this->playerCount = $playerCount;
     $this->playerScoreArray = array_fill(0,$playerCount,0);
     $this->zoneCount   = count($zoneArray);
     foreach ($zoneArray as $id => $coordinates){
       $this->zoneArray[$id] = new Zone($coordinates['x'],$coordinates['y'], $id);
     }
     for ($i=0; $i<$playerCount; ++$i){
       $droneArray = array();
       for ($j=0; $j<$droneCount; ++$j){
         $droneArray[$j] = new Drone($i, $j);
       }
       $this->playerDroneArray[$i] = $droneArray;
     }
     $this->playerId = $playerId;
   }

   public function initTurn()
   {
     //Zoneの初期化
     foreach ($this->zoneArray as &$zone) $zone->initTurn($this->playerCount);
   }

   public function dumpStatus()
   {
     foreach ($this->playerDroneArray as $playerId => $droneArray){
       dump("------playerId:{$playerId},score:{$this->playerScoreArray[$playerId]}-----");
       foreach ($droneArray as $droneId => $drone){
         dump("ID:{$droneId}, (x:y) = ({$drone->x}, {$drone->y}), aim2:{$drone->getNextRootZoneId()}");
       }
     }
     foreach ($this->zoneArray as $zoneId => $zone){
       $zone->dumpStatus();
     }
   }

   public function echoRoot($dronId)
   {
     $zoneId = $this->playerDroneArray[$this->playerId][$dronId]->getNextRootZoneId();
     $zone = $this->zoneArray[$zoneId];
     echo "{$zone->x} {$zone->y}\n";
   }

   /**
    * 現在の状態から自分のドローンのルートを計算する
    * 最寄りのコントロールされていないZoneへ向かう
    * -------これから
    *   自分より近くのドローンがいたら他のドローンとペアになる
    */
   public function createRoot()
   {
     $assignArray = array();
     $count = 0; //同じプレイヤーのドローンで設定済みのドローンの数
     foreach ($this->playerDroneArray[$this->playerId] as &$drone){
       foreach ($this->zoneArray as $zone){
         $drone->setRootValue($zone, $count);
       }
       if (isset($this->zoneArray[$drone->getNextRootZoneId()])){
         $this->zoneArray[$drone->getNextRootZoneId()]->unsetAssign($drone->getId(), $this->playerId);
       }
       $this->zoneArray[$drone->setNextRoot()]->setAssign($drone->getId());

       ++$count;
     }
   }

   /**
    * $x,$yとの距離順にソートしたZoneId配列を返す
    */
   private function getSortedZoneIdArray($x, $y)
   {
     $diffArray = array();
     foreach($this->zoneArray as $id => $zone){
       $diffArray[$id] = Map::getDistance($x, $y, $zone->x, $zone->y);
     }
     asort($diffArray);
     return array_keys($diffArray);
   }

   /**
    * 2点間の距離を返す
    */
   static public function getDistance($x1, $y1, $x2, $y2)
   {
     return sqrt(pow($x1-$x2,2) + pow($y1-$y2, 2));
   }

   /**
    * ドローンの座標を更新する
    *
    */
   public function updateDrone($playerId, $droneId, $x, $y)
   {
     $this->playerDroneArray[$playerId][$droneId]->updateCoordinate($x, $y, $this->zoneCount, $this->zoneArray);

     //所属しているZoneがあればフラグを立てる
     foreach ($this->zoneArray as $id => &$zone){
       $zone->updateTakePlayerDroneArray($playerId, $droneId, $x, $y);
     }
   }

   /**
    * スコアを更新
    * ゾーンのオーナーを更新
    */
   public function updateScore($zoneId, $playerId)
   {
     $this->playerScoreArray[$playerId]++;
     $this->zoneArray[$zoneId]->setOwner($playerId);
     return true;
   }

 }
 class Zone
 {
   public $x;
   public $y;
   private $id;
   private $takePlayerDroneArray  = array();       //占拠中のdroneの配列
   private $ownerPlayerId         = -1;           //ポイントゲット中のプレイヤー
   private $asignOwnDroneIdArray  = array(); //このZoneを目指す自分のDroneのID(1ターン前の話)
   private $distanceArray         = array(); //各ドローンとの距離

   public function __construct($x, $y, $id)
   {
     $this->x   = $x;
     $this->y   = $y;
     $this->id  = $id;
   }

   /**
    * Zoneのstatusを出力する
    */
   public function dumpStatus()
   {
     dump("====zoneId:{$this->id}(x:y) = ({$this->x}, {$this->y}),owner:{$this->getOwnerPlayerId()}===");
     foreach ($this->takePlayerDroneArray as $playerId => $droneArray){
       $count = count($droneArray);
       dump("player:{$playerId} takes [{$count}]");
     }
     if (empty($this->asignOwnDroneIdArray)){
       dump("no one move to the one");
     }
     else {
       $count = count($this->asignOwnDroneIdArray);
       $implode = implode(",", $this->asignOwnDroneIdArray);
       dump("{$count} move to the one ({$implode})");
     }
     dump("*distance to ...");
     foreach ($this->distanceArray as $playerId => $distance){
       $distArray = array_map('round', $distance);
       $distString = implode(",",$distArray);
       dump("\t{$playerId} : ({$distString})");
     }

   }

   public function getOnwerPlayerId(){return $this->ownerPlayerId;}
   public function getId(){return $this->id;}

   /**
    * 占拠中のドローン数を返す
    * $playerIdを省略した場合現在のオーナーの値を返す
    * @param $isEstimate trueの場合移動中も加味する
    * @param $doneCount すでにアサイン済みの自機数
    */
   public function getTakeDroneCount($playerId = null, $isEstimate = false, $doneCount = 0)
   {
     if (!is_null($playerId)){
       return $this->ownerPlayerId === -1 ? 0 : $this->ownerPlayerId;
     }
     else {
       if ($isEstimate){
         $now = count($this->takePlayerDroneArray[$playerId]);
         $removeCount = $doneCount - count($this->asignOwnDroneIdArray);
         return $now - $removeCount;
       }
       else {
         return count($this->takePlayerDroneArray[$playerId]);
       }
     }
   }

   public function resetAsignOwnDroneIdArray()
   {
     $this->asignOwnDroneIdArray  = array();
     return true;
   }

   public function getOwnerPlayerId()
   {
     return $this->ownerPlayerId;
   }

   public function setOwner($playerId)
   {
     $this->ownerPlayerId = $playerId;
   }

   /**
    * ターンごとの初期化
    */
   public function initTurn($playerCount)
   {
     $this->takePlayerDroneArray = array_fill(0, $playerCount, array());
     $this->distanceArray        = array_fill(0, $playerCount, array());
    //  $this->resetAsignOwnDroneIdArray();
    //  var_dump($this->takePlayerDroneArray);
   }

   /**
    * $drondIdの自分のドローンをZoneに向かわせる
    */
   public function setAssign($droneId)
   {
     $this->asignOwnDroneIdArray[$droneId] = $droneId;
     return true;
   }
   public function unsetAssign($droneId, $playerId)
   {
     unset($this->asignOwnDroneIdArray[$droneId]);
     unset($this->takePlayerDroneArray[$playerId][$droneId]);
     return true;
   }

   /**
     * 支配するために必要なドローン数
     * $isAdd = falseの場合、合計何台でコントロールできるかを返す
     * $isAdd = trueの場合、あと何台追加するとコントロールできるかを返す
     * @param $myPlayerId 自分のプレイヤーID
     * @param $isAdd
     * @param $ignoreDroneIdArray この配列に含まれるDroneIdをカウントから除外する
     */
   public function getNeedTakeCount($myPlayerId, $isAdd = true, array $ignoreDroneIdArray = array())
   {
     $droneCountArray = array();
    //  var_dump($this->takePlayerDroneArray);
     foreach ($this->takePlayerDroneArray as $playerId => $playerInfo){
       if(!empty($ignoreDroneIdArray)){
         if ($playerId === $myPlayerId){
           foreach ($ignoreDroneIdArray as $droneId){
             unset($playerInfo[$droneId]);
           }
         }
         $ignoreDroneIdArray = array(); //次回以降最初のif文で分岐が終わる
       }
       $droneCountArray[$playerId] = count($playerInfo);
     }

      // dump($droneCountArray);

     $ownCount = 0;
     if($isAdd){
       $ownCount = $droneCountArray[$myPlayerId];
     }
     unset($droneCountArray[$myPlayerId]);
     arsort($droneCountArray);

     $needTakeCount = reset($droneCountArray) + 1 - $ownCount;
     if ($this->ownerPlayerId === $myPlayerId) $needTakeCount--;

     return $needTakeCount;
   }

   /**
    * コントロールを取得するために必要なアサイン台数
    * @param $myPlayerId 自分のプレイヤーId
    * @param array $ignoreDroneIdArray この配列に含まれるDroneIdをカウントから除外する
    */
   public function getNeedAsignCount($myPlayerId, array $ignoreDroneIdArray = array())
   {
     list($needTakeCount, $asignCount) = $this->getNeedTakeCountAndAsignCount($myPlayerId, $ignoreDroneIdArray);
     return $needTakeCount - $asignCount;
   }
   public function getNeedTakeCountAndAsignCount($myPlayerId, array $ignoreDroneIdArray = array())
   {
     $ignoreIdArray = $this->takePlayerDroneArray[$myPlayerId];
     foreach ($ignoreDroneIdArray as $value){
       if (!isset($ignoreIdArray[$value])) $ignoreIdArray[$value] = $value;
     }
     $asignArray = $this->getAsignDroneArray($myPlayerId, $isIgnoreTake = true, $ignoreIdArray);
     $needTakeCount = $this->getNeedTakeCount($myPlayerId, true);
     dump("needTakeCount:{$needTakeCount}");
     dump("asignCount:" . count($asignArray));
     return [$needTakeCount, count($asignArray)];
   }

   /**
    * このZoneに向かっている途中のドローンの配列
    * @param $myPlayerId
    * @param $isIgnoreTake trueの場合すでにコントロール中のドローンを除外する
    * @param array $ignoreDroneIdArray この配列に含まれるDroneIdをカウントから除外する
    */
   public function getAsignDroneArray($myPlayerId, $isIgnoreTake, $ignoreDroneIdArray = array())
   {
     $asignOwnDroneIdArray = $this->asignOwnDroneIdArray;
     $ignoreArray = $ignoreDroneIdArray;
     if ($isIgnoreTake) $ignoreArray = array_merge($ignoreArray,$this->takePlayerDroneArray[$myPlayerId]);

     foreach ($ignoreArray as $droneId){
       unset($asignOwnDroneIdArray[$droneId]);
     }

     return $asignOwnDroneIdArray;
   }

   /**
    * 現在所属中のドローン数を更新する
    */
   public function updateTakePlayerDroneArray($playerId, $droneId, $x, $y)
   {
     $this->distanceArray[$playerId][$droneId] = Map::getDistance($this->x, $this->y, $x, $y);
     //半径100以内だったら支配中
     if(100 >= $this->distanceArray[$playerId][$droneId]){
       $this->takePlayerDroneArray[$playerId][$droneId] = $droneId;
     }
   }

   /**
    *  $playerIdの$dorneIdが現在このゾーンの100m以内にいるか
    */
   public function isTake($playerId, $droneId)
   {
     if(isset($this->takePlayerDroneArray[$playerId]) &&
        isset($this->takePlayerDroneArray[$playerId][$droneId])){
       return true;
     }
     return false;
   }


   /**
    * このゾーンとの距離が$drone以下であるドローンの数に応じて
    * プレイヤーのランキングを作成
    * $droneのオーナーの順位を返す
    */
   public function getDistanceRank(Drone $drone)
   {
     $rankPointArray = array_fill(0, count($this->distanceArray),0);
     $baseDistance = $this->distanceArray[$drone->getOwnerPlayerId()][$drone->getId()];
     foreach ($this->distanceArray as $playerId => $playerInfo){
       foreach ($playerInfo as $droneId => $distance){
         if ($distance >= $baseDistance) ++$rankPointArray[$playerId];
       }
     }
     arsort($rankPointArray);
     $keyArray = array_keys($rankPointArray);
     return array_search($drone->getOwnerPlayerId(), $keyArray) + 1;
   }
   public function getDistance($playerId, $droneId)
   {
     return $this->distanceArray[$playerId][$droneId];
   }
 }
 class Drone
 {
   private $ownerPlayerId;
   private $id;
   public $x;
   public $y;
   private $preX;
   private $preY;
   private $nextRootZoneId;
   private $rootValueArray = array(); //ルートの優先順位配列
   private $distanceArray = array();


   public function __construct($ownerPlayerId, $id)
   {
     $this->ownerPlayerId = $ownerPlayerId;
     $this->id            = $id;
   }

   public function getOwnerPlayerId(){return $this->ownerPlayerId;}
   public function getNextRootZoneId(){return $this->nextRootZoneId;}
   public function setNextRootZoneId($zoneId){$this->nextRootZoneId = $zoneId;}
   public function getId(){return $this->id;}

   private function isOwner(Zone $zone)
   {
     return $this->ownerPlayerId === $zone->getOnwerPlayerId();
   }

   /**
    * $zoneに向かう価値を評価する
    * @param すでにアサイン済みの同プレイヤーのドローン数
    */
   public function setRootValue(Zone $zone, $doneCount)
   {
     dump("#### drone:{$this->id} envalue zone:{$zone->getId()}###");
     //自分のゾーンを離れると他に取られる場合に自分のゾーンのプライオリティ増
     if ($this->isTakenByMove($zone, $this->ownerPlayerId, $doneCount)){
       dump("it's a keeper");
       $this->addRootValue($zone->getId(), 200);
     }

      //Zoneを取るために数が足りない
        //もうちょっとで取れそうなゾーンを優先する
    //  $needAsignCount = $zone->getNeedAsignCount($this->ownerPlayerId, array($this->id));
     list($needTakeCount, $asignCount) = $zone->getNeedTakeCountAndAsignCount($this->ownerPlayerId, array($this->id));
     $needAsignCount = $needTakeCount - $asignCount;
     if ($needAsignCount > 0){
        dump("go to get. remain {$needAsignCount}");
        $this->addRootValue($zone->getId(), 200/$needAsignCount);//もっとでかく
     }
     //十分数がアサインされている状態
      // 自分がアサインされている状態なら維持する
     else {
        //自分が既にtake中 or 向かっているドローンの中で近かったら向かう
        if ($zone->isTake($this->ownerPlayerId, $this->id) || $this->getNearRank($zone) <= $needTakeCount){
          $this->addRootValue($zone->getId(), 100);
        }
        //近くなかったら単なる定員オーバー
        else {
          $this->addRootValue($zone->getId(), $needAsignCount * 100);
        }
     }


     //距離の近さランキングの順位に応じたポイント
    //  $rankPoint = $this->getDistanceZoneRankValue($zone, 100);
    //  $this->addRootValue($zone->getId(),$rankPoint );
    //  dump("distance rank {$rankPoint}");

     //実際の距離に応じたポイント
    //  $dist = max(1, Map::getDistance($zone->x,$zone->y, $this->x, $this->y));
    //  $this->divRootValue($zone->getId(), $dist);

    $rankPoint = $this->getDistanceMyRankValue($zone->getId(), 10);
    $this->addRootValue($zone->getId(), $rankPoint);
    dump("distance rank {$rankPoint}");
   }

   /**
    *  このドローンが$zoneにとって何番目に近いか
    *  (自機内ランキング)
    */
   private function getNearRank(Zone $zone, $targetDroneArray = array())
   {
     if (empty($targetDroneArray)) $targetDroneArray = $zone->getAsignDroneArray($this->ownerPlayerId, true);
     $baseDistance = $zone->getDistance($this->ownerPlayerId, $this->id);
     $rank = 1;
     foreach ($targetDroneArray as $droneId){
       $distance = $zone->getDistance($this->ownerPlayerId, $droneId);
       if ($baseDistance > $distance) $rank++;
     }
     return $rank;
   }

   private function addRootValue($zoneId, $value)
   {
     $this->rootValueArray[$zoneId] += $value;
   }
   private function divRootValue($zoneId, $value)
   {
     $this->rootValueArray[$zoneId] /= $value;
   }


   /**
    * ドローンの距離ランクに応じたポイント
    * $zone付近に多くのドローンを集めているユーザはポイントが高い
    */
   private function getDistanceZoneRankValue(Zone $zone, $baseValue)
   {
     $rank = $zone->getDistanceRank($this);
     return $baseValue/$rank;
   }

   private function getDistanceMyRankValue($zoneId, $baseValue)
   {
     $mainDist = $this->distanceArray[$zoneId];
     $score = 0;
     foreach($this->distanceArray as $id => $distance){
       if ($mainDist > $distance) $score++;
     }
     return $baseValue / ($score + 1);
   }

   /**
    * 今移動するとコントロールを失う場合に1を返す
    * 自分コントロール台数 <= 他のプレイヤーのコントロール台数
    * @param Zone $zone
    * @param $myPlayerId
    */
   private function isTakenByMove(Zone $zone, $myPlayerId, $doneCount = 0)
   {
     if($zone->getOwnerPlayerId() === $myPlayerId && $zone->isTake($myPlayerId, $this->id)){
       $needTakeCount = $zone->getNeedTakeCount($myPlayerId, true);
       if ( $needTakeCount === 0){
         return true;
       }
     }

     return false;
   }

   public function setNextRoot()
   {
     dump("**** drone:{$this->id} set root ****");
     dump($this->rootValueArray);
     arsort($this->rootValueArray);
     reset($this->rootValueArray);
     $zoneId = key($this->rootValueArray);

     $this->nextRootZoneId = $zoneId;
     dump("move to:{$this->nextRootZoneId}");
     return $this->nextRootZoneId;
   }

   /**
    * ドローンの座標を更新する
    */
   public function updateCoordinate($x, $y, $zoneCount, array $zoneArray)
   {
     $this->preX = $this->x;
     $this->preY = $this->y;
     $this->x = $x;
     $this->y = $y;
     $this->rootValueArray = array_fill(0,$zoneCount,0);

     //$distanceArrayの更新
     foreach ($zoneArray as $zoneId => $zone){
       $this->distanceArray[$zoneId] = Map::getDistance($x, $y, $zone->x, $zone->y);
     }

   }
 }

fscanf(STDIN, "%d %d %d %d",
    $P, // number of players in the game (2 to 4 players)
    $ID, // ID of your player (0, 1, 2, or 3)
    $D, // number of drones in each team (3 to 11)
    $Z // number of zones on the map (4 to 8)
);
$zoneArray = array();
for ($i = 0; $i < $Z; $i++)
{
    fscanf(STDIN, "%d %d",
        $X, // corresponds to the position of the center of a zone. A zone is a circle with a radius of 100 units.
        $Y
    );
    $zoneArray[$i] = ['x' => $X, 'y' => $Y];
}
$map = new Map($P, $ID, $D, $zoneArray);

// game loop
while (TRUE)
{
    $map->initTurn();
    for ($i = 0; $i < $Z; $i++)
    {
        fscanf(STDIN, "%d",
            $TID // ID of the team controlling the zone (0, 1, 2, or 3) or -1 if it is not controlled. The zones are given in the same order as in the initialization.
        );
        if ($TID !== -1)$map->updateScore($i, $TID);
    }
    for ($i = 0; $i < $P; $i++)
    {
        for ($j = 0; $j < $D; $j++)
        {
            fscanf(STDIN, "%d %d",
                $DX, // The first D lines contain the coordinates of drones of a player with the ID 0, the following D lines those of the drones of player 1, and thus it continues until the last player.
                $DY
            );
            $map->updateDrone($i, $j, $DX, $DY);
        }
    }
    $map->dumpStatus();

    $map->createRoot();
    for ($i = 0; $i < $D; $i++)
    {
        // Write an action using echo(). DON'T FORGET THE TRAILING \n
        // To debug (equivalent to var_dump): dump($var, true));
        $map->echoRoot($i);
    }
}
?>
