<?php

include 'ParseController.php';
$url='http://www.amfiindia.com/nav-history-download';
$controller=new ParseController();
$response=$controller->doCurl($url);
//var_dump($response);
$dom= new \DOMDocument();
@$dom->loadHTML($response);
$xpath = new DOMXpath($dom);
$name='NavDownMFName';
$nodeList = $xpath->query("//select[string-length(@name)!=0 and @name='$name']");
$nodes=$nodeList->item(0)->childNodes;
$fh = fopen('NAV060317.txt', 'a+');
//var_dump($nodes);
	foreach ($nodes as $node)
	{
		var_dump($node->nodeValue);
	   	$mf=$node->getAttribute('value');
	   	if($mf!="")
	   	{
	   		for($i=1;$i<=3;$i++)
			{
				$url="http://www.portal.amfiindia.com/DownloadNAVHistoryReport_Po.aspx?frmdt=06-March-2017&todt=06-Mar-2017&mf=";
			    $url=$url.$mf."&tp=".$i;
			    var_dump($url);
			    $response=$controller->doCurl($url);
			    @$dom->loadHTML($response);
			    $xpath = new DOMXpath($dom);
			    $className='labelRed';
			    $nodeList = $xpath->query("//td/span[string-length(@class)!=0 and @class='$className']");
			    if($nodeList->item(0)==NULL)
			    	fwrite($fh, $response);
			    
			}
		}
	}
	fclose($fh);
?>