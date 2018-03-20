<?php
//ほげほげ

//ふがふが

function valueChange1(stdClass $obj)
{
  $obj->a = "hoge";
  echo __LINE__ ,":",$obj->a,"\n";
}
function valueChange2(stdClass &$obj)
{
  $obj->a = "hoge";
  echo __LINE__ ,":",$obj->a,"\n";
}
function arrayChange1(array $objArray)
{
  foreach($objArray as &$obj){
    $obj->a="hoge";
  }
}
function arrayChange2(array &$objArray)
{
  foreach($objArray as $obj){
    $obj->a="hoge";
  }
  $first = reset($objArray);
  echo __LINE__ ,":",$first->a,"\n";
}

$obj = getTest();
valueChange1($obj);
echo __LINE__ ,":",$obj->a,"\n";
$obj = getTest();
valueChange2($obj);
echo __LINE__ ,":",$obj->a,"\n";
$objArray = getTestArray();
arrayChange1($objArray);
$first = reset($objArray);
echo __LINE__ ,":",$first->a,"\n";
$objArray = getTestArray();
arrayChange2($objArray);
$first = reset($objArray);
echo __LINE__ ,":",$first->a,"\n";

function getTest()
{
  $obj = new stdClass();
  $obj->a = "fuga";
  return $obj;
}
function getTestArray()
{
  $array = array();
  for($i=0;$i<3;$i++){
    $obj = new stdClass();
    $obj->a = "fuga";
    $array[] = $obj;
  }
  return $array;
}

?>
