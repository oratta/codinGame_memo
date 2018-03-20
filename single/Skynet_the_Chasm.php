<?php
/**
 * Auto-generated code below aims at helping you parse
 * the standard input according to the problem statement.
 **/
 /* sample
4
2
5
2
0


 */

fscanf(STDIN, "%d",
    $R // the length of the road before the gap.
);
fscanf(STDIN, "%d",
    $G // the length of the gap.
);
fscanf(STDIN, "%d",
    $L // the length of the landing platform.
);

$actionArray = array();
$isFirst = true;
// game loop
while (TRUE)
{
    fscanf(STDIN, "%d",
        $S // the motorbike's speed.
    );
    fscanf(STDIN, "%d",
        $X // the position on the road of the motorbike.
    );
    if (!isset($S)) $S=0;
    if ($isFirst){
      if ($S===0){
        echo "SPEED\n";
        continue;
      }
      error_log(var_export("gap:$G", true));
      error_log(var_export("road:$R", true));
      error_log(var_export("speed:$S", true));
      $actionArray = getActionArray($G,$R-1,$S);
      $isFirst = false;
    }

    error_log(var_export($actionArray, true));
    echo(array_shift($actionArray) . "\n");
}

function getActionArray($gap,$road,$initialSpeed, $returnArray = array())
{
    //SPEEDが必要な回数
    $speedNum = $gap + 1 -$initialSpeed;
    //SPEEDした結果進む距離
    $moveDistanceForSpeed = 0;
    $tmpSpeed = $initialSpeed;
    for ($i=0;$i<$speedNum;++$i){
      $tmpSpeed++;
      $moveDistanceForSpeed += $tmpSpeed;
    }
    //SPEEDする前に残った距離
    $waitNum = 0;
    $leftRoad = $road - $moveDistanceForSpeed;
    error_log(var_export("initialSpeed:$initialSpeed", true));
    error_log(var_export("leftRoad:$leftRoad", true));
    if ($leftRoad === 0){
      $waitNum = 0;
    }
    else if ($leftRoad % $initialSpeed === 0){
      $waitNum = $leftRoad / $initialSpeed - 1;
    }
    else{
      $returnArray[] = "SLOW";
      return getActionArray($gap, $road-$initialSpeed-1, $initialSpeed-1, $returnArray);
    }

    for ($i=0;$i<$waitNum;++$i){
        $returnArray[] = "WAIT";
    }
    for ($i=0;$i<$speedNum;++$i){
        $returnArray[] = "SPEED";
    }
    $returnArray[] = "JUMP";
    for ($i=0;$i<$speedNum+$initialSpeed;++$i){
        $returnArray[] = "SLOW";
    }

    return $returnArray;
}
?>
