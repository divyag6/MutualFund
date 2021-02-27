<?php
include 'ParseController.php';
$url='http://www.portal.amfiindia.com/spages/NAV0.txt';
//$date=date("dmY");
//$url=$url.$date."070402";
$controller=new ParseController();
$response=$controller->doCurl($url);
echo($response);	
$query="load data local infile 'E:/NAV.bat/NAV.txt' into table mfdb_dev.daily_nav fields terminated by ';'  lines terminated by '\n'
(scheme_code,div_payout_growth,div_reinvestment,scheme_name,nav,repurchase_price,sale_price,@date) 
SET date = STR_TO_DATE(@date, '%d-%b-%Y')";
mysqli_query($controller->conn,$query);
$query="delete FROM mfdb_dev.daily_nav where date='0000-00-00'";
mysqli_query($controller->conn,$query);
mysqli_close($controller->conn);
?>