<?php

class ParseController
{
	var $agent="Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0";
	var $fundCategory=array(array());
	var $allFunds=array(array());
	var $fullPortfolio=array(array());
	var $fundMarker=['scheme','rank','aum','1mnth','3mnth','6mnth','1yr','2yr','3yr','5yr'];
	var $equityPortfolioMarker=['Asset','Sector','Qty','Value','Percentage'];
	var $assetPortfolioMarker=['Asset','Rating','Value','Percentage'];
	var $mfPortfolioMarker=['Asset','Class','Rating','Value','Percentage'];
	var $conn;
	var $noDataFunds=['MPA001'=>'MDE003','MPA006'=>'MDE065','MPA011'=>'MDE001','MPA013'=>'MDE029',
			'MPA015'=>'MDE005','MPA020'=>'MDE130','MPA027'=>'MDE005','MPA041'=>'MDE487',
			'MPA043'=>'MDE513','MPA045'=>'MDE516','MPA053'=>'MDE490','MPA058'=>'MDE529',
			'MPA063'=>'MDE529','MPA072'=>'MDE506','MPA149'=>'MDE007','MPA152'=>'MDE524'];

	function __construct(){
		$username="admin";$password="divya123";$database="mfdb_dev";
		$this->conn=mysqli_connect('127.0.0.1:3306',$username,$password,$database)or die( "Unable to select database");
	}
	public function getFundCategory($daily=NULL){
		
		$response=$this->doCurl("http://www.moneycontrol.com/mutual-funds/performance-tracker/returns/large-cap.html");
		$dom= new \DOMDocument();
		@$dom->loadHTML($response);
		$xpath = new DOMXpath($dom);
		$classname="FL lsh";
		$nodeList = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");
		$count=0;
		foreach ($xpath->evaluate('.//li//a[@href]',$nodeList->item(0)) as $a) {
			
			if($a->nodeValue!='Large Cap')
			{
				$this->fundCategory[$count]['category'] = $a->nodeValue;
				$this->fundCategory[$count]['url'] = 'http://www.moneycontrol.com/india'.$a->getAttribute('href');
				$count++;	
			}
		}
		$this->getAllFundsInCategory($response,'Large Cap',$daily);
		echo("Starting...getAllFundsInCategory multi..");
		//$url="http://www.moneycontrol.com/mutual-funds/performance-tracker/returns/";
		$this->doMultiCurl('getAllFundsInCategory',NULL,$daily);
		mysqli_close($this->conn);
	}
		
	public function getAllFundsInCategory($response=NULL,$fundCategory=NULL,$daily=NULL)
	{
		$this->allFunds=[];
		//$url="http://www.moneycontrol.com/mutual-funds/performance-tracker/returns/large-cap.html";
		//$response=$this->doCurl($url);
		var_dump("Getting All Funds for Category ".$fundCategory.".....");
		$dom= new \DOMDocument();
		@$dom->loadHTML($response);
		$xpath = new DOMXpath($dom);
		$classname='bl_12';
		$count=0;
		$nodeList = $xpath->query("//a[contains(@class, '$classname')]");
		foreach ($nodeList as $node)
		{
			$this->allFunds[$count]['fundCategory']=$fundCategory;
			$this->allFunds[$count]['$href']=$node->getAttribute('href');
				
			$end=strrpos($this->allFunds[$count]['$href'],'/');
				
			if($end !== false)
			{
				$this->allFunds[$count]['mfId']=substr_replace($this->allFunds[$count]['$href'], "",0,$end+1);
			}
			$parentNode= $node->parentNode;
			$this->allFunds[$count][$this->fundMarker[0]]=$node->nodeValue;
			
			for($i=1;$i<10;$i++)
			{
				$parentNode=$parentNode->nextSibling->nextSibling;
				$this->allFunds[$count][$this->fundMarker[$i]]=$parentNode->nodeValue;
				if($i>=2)
				{
					if($this->fundMarker[$i]=='aum')
						$this->allFunds[$count][$this->fundMarker[$i]]=str_replace(",","",$this->allFunds[$count][$this->fundMarker[$i]]);
					if($this->allFunds[$count][$this->fundMarker[$i]]!=='--')
						$this->allFunds[$count][$this->fundMarker[$i]]=doubleval($this->allFunds[$count][$this->fundMarker[$i]]);
					else 
						$this->allFunds[$count][$this->fundMarker[$i]]=0.00;
				}
			}
			$count++;
			
		}
		
		var_dump("Getting Benchmark..");
		$url="http://www.moneycontrol.com/india/mutualfunds/mfinfo/investment_info/";
		$this->doMultiCurl('getFundBenchmark',$url);
		
		if($daily!=true)
		{
			var_dump("Getting Full Portflio for ".$count." funds in Category ".$fundCategory."....");
			$url="http://www.moneycontrol.com/india/mutualfunds/mfinfo/portfolio_holdings/";
			if($count!==0)
				$this->doMultiCurl('getFullPortfolio',$url);
		}
	}
	
	public  function getFundBenchmark($response,$key)
	{
			$dom= new \DOMDocument();
			@$dom->loadHTML($response);
			$xpath = new DOMXpath($dom);
			$classname='FL';
			$nodeList = $xpath->query("//div/form/div[string-length(@class)!=0 and @class and @class='$classname']");
			if($nodeList->item(0)!==NULL)
			{
				$options=$nodeList->item(0)->nodeValue;
				$end=strrpos($options,':');
				if($end !== false)
					$this->allFunds[$key]['options']=trim(substr_replace($options, "",0,$end+1));
				else
					$this->allFunds[$key]['options']="";
			}
			else
				$this->allFunds[$key]['options']="";
			$classname='FL w150';
			$nodeList = $xpath->query("//div[contains(@class, '$classname')]");
			foreach ($nodeList as $node)
			{
				if($node->nodeValue=="Benchmark")
					$this->allFunds[$key]['benchmark']=$node->nextSibling->nextSibling->nodeValue;
			}
			
			//var_dump($this->allFunds);
			if($this->allFunds[$key]['mfId']=='MBO088'||'MBO169'||'MBO010'||'MBO168')
				$this->allFunds[$key]['benchmark']='S&P BSE 200';
			$query = "INSERT INTO funds (mfid,scheme,category,aum,rank,benchmark,1mnth,3mnth,6mnth,1yr,2yr,3yr,5yr,options)VALUES
			('".$this->allFunds[$key]['mfId']."','".$this->allFunds[$key]['scheme']."','".$this->allFunds[$key]['fundCategory']."','".$this->allFunds[$key]['aum']."',
			'".$this->allFunds[$key]['rank']."','".$this->allFunds[$key]['benchmark']."','".$this->allFunds[$key]['1mnth']."','".$this->allFunds[$key]['3mnth']."',
			'".$this->allFunds[$key]['6mnth']."','".$this->allFunds[$key]['1yr']."','".$this->allFunds[$key]['2yr']."','".$this->allFunds[$key]['3yr']."',
			'".$this->allFunds[$key]['5yr']."','".$this->allFunds[$key]['options']."'
			)";
			mysqli_query($this->conn,$query);
			
		
			
	}
	
	public function  getFullPortfolio($response=NULL,$key=NULL)
	{
		$this->fullPortfolio=[];
		//$url="http://www.moneycontrol.com/india/mutualfunds/mfinfo/portfolio_holdings/MAA126";
		//$response=$this->doCurl($url);
		$count=0;
		$dom= new \DOMDocument();
		@$dom->loadHTML($response);
		$xpath = new DOMXpath($dom);
		
		$count=$this->getPortfolioEquity('tblporhd',$xpath,$key,$count);
		
		$this->getOtherPortfolioAssets('tblporhd MT25',$xpath,$key,$count);
		
		
	}
	
	protected function setCurlOptions($myRequest)
	{
		
		//curl_setopt($myRequest, CURLOPT_USERAGENT, $this->agent);
		
		curl_setopt($myRequest,CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($myRequest,CURLOPT_CONNECTTIMEOUT, 60);
		//curl_setopt($myRequest,CURLOPT_COOKIEFILE,$this->cookie_filename);
		curl_setopt($myRequest, CURLOPT_ENCODING , "gzip");
		//curl_setopt($myRequest,CURLOPT_COOKIEJAR,$this->cookie_filename);
	
		curl_setopt($myRequest, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($myRequest,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($myRequest,CURLOPT_FOLLOWLOCATION,true);
		curl_setopt($myRequest, CURLOPT_AUTOREFERER, true );
		//	curl_setopt($myRequest, CURLOPT_VERBOSE, TRUE);
		//curl_setopt($myRequest, CURLOPT_HEADER,true);
		//curl_setopt($myRequest, CURLINFO_HEADER_OUT,true);
		curl_setopt($myRequest, CURLOPT_TIMEOUT,45);
		curl_setopt($myRequest, CURLOPT_MAXREDIRS, 10 );
	
		return $myRequest;
	}
	
	/**
	 * Does curl on the url provided and returns the response
	 * @param string $url
	 * @param boolean $login
	 * @return mixed
	 */
	public function doCurl($url,$login=false)
	{
	
		$myRequest=curl_init($url);
		$myRequest=$this->setCurlOptions($myRequest);
		/*if($login)
		{
			curl_setopt($myRequest,CURLOPT_POST,TRUE);
			curl_setopt($myRequest,CURLOPT_POSTFIELDS,$this->postfields);
		}*/
		$response=curl_exec($myRequest);
		$info = curl_getinfo($myRequest);
		curl_close($myRequest);
		return $response;
	}
	public function doMultiCurl($callback,$url=NULL,$daily=NULL)
	{
		// make sure the rolling window isn't greater than the # of urls
		
		$rolling_window = 100;
		if($callback=='getAllFundsInCategory')
			$size=sizeof($this->fundCategory);
		else
			$size=sizeof($this->allFunds);
		$rolling_window = ($size < $rolling_window) ? $size : $rolling_window;
		
		$mh = curl_multi_init();
		$myRequest = array();
		
		// start the first batch of requests
		for ($i = 0; $i <$rolling_window; $i++) {
			$requestUrl=$this->getURL($url,$i);
			$myRequest[$i]=curl_init($requestUrl);
			$myRequest[$i]=$this->setCurlOptions($myRequest[$i]);
			curl_multi_add_handle($mh,$myRequest[$i]);
		
		}
		$running=null;
		do
		{
			$exec = curl_multi_exec($mh, $running);
				
		}while( $exec == CURLM_CALL_MULTI_PERFORM);
		
		while ($running && $exec == CURLM_OK)
		{
			do
			{
				$ret = curl_multi_exec($mh, $running);
			} while ($ret == CURLM_CALL_MULTI_PERFORM);
		
			while($done = curl_multi_info_read($mh))
			{
					
				$info = curl_getinfo($done['handle']);
				if ($info['http_code'] == 200 )
				{
						
					// request successful.  process output using the callback function.
					$response = curl_multi_getcontent($done['handle']);
					//echo "<br/>-------------header-------------";
					//var_dump($info);
					for ($j=0;$j<$i;$j++)
					{
						if($myRequest[$j]==$done['handle'])
						{
							if($callback=='getAllFundsInCategory')
								$this->$callback($response,$this->fundCategory[$j]['category'],$daily);
							else	
								$this->$callback($response,$j);
							
							curl_close($myRequest[$j]);
							curl_close($myRequest[$j]);
							curl_multi_remove_handle($mh, $done['handle']);
						}
							
					}
						
					// start a new request (it's important to do this before removing the old one)
					
					while( $size > $i)
					{
						$requestUrl=$this->getURL($url,$i);
						$myRequest[$i]=curl_init($requestUrl);
						$myRequest[$i]=$this->setCurlOptions($myRequest[$i]);
						curl_multi_add_handle($mh,$myRequest[$i]);
						$i++;
					}
						
					do
					{
						$exec = curl_multi_exec($mh, $running);
					} while ($exec == CURLM_CALL_MULTI_PERFORM);
						
						
				}
				else
				{
					// remove the curl handle that just completed
					curl_close($done['handle']);
					curl_close($done['handle']);
					curl_multi_remove_handle($mh, $done['handle']);
		
						
					// request failed.  add error handling.
					for ($j=0;$j<$i;$j++)
					{
						if($myRequest[$j]==$done['handle'])
						{
							var_dump("<".$info['http_code']."> Could not process portfolio for ".$url);
							
						}
					}
				}
		
			}//end while that runs if there is some info.
				
		}//end of while that exits when running=0
		
		curl_multi_close($mh);
	}
		
	public function getURL($url,$i)
	{
		if($url==NULL)
			$requestUrl=$this->fundCategory[$i]['url'];
		else
		{
			if(!array_key_exists($this->allFunds[$i]['mfId'], $this->noDataFunds))
				$mfId=$this->allFunds[$i]['mfId'];
			else 
				$mfId=$this->noDataFunds[$this->allFunds[$i]['mfId']];
			
			$requestUrl=$url.$mfId;
			
		}
		
		return $requestUrl;
	}
	
	
	public function getPortfolioEquity($classname,$xpath,$key,$count)
	{
		$nodeList = $xpath->query("//table[string-length(@class)!=0 and @class and @class='$classname']");
		
		if($nodeList->item(0)!==NULL)
		{
			foreach ($xpath->evaluate('.//tr',$nodeList->item(0)) as $node) {
				$j=0;
				$flag=false;
				$childNodeList=$node->childNodes;
				foreach ($childNodeList as $childNode){
					if($childNode->nodeName=='th')
					{
						if($j==0)
							$asset_type=trim($childNode->nodeValue);
						$j++;
					}
					else
					{
						if($childNode->nodeName!='th' && $childNode->nodeName!="#text" && $childNode->nodeValue!="")
						{
							$flag=true;
							$this->fullPortfolio[$count][$this->equityPortfolioMarker[$j]]=$childNode->nodeValue;
							if($j>=2)
							{
								if($this->fullPortfolio[$count][$this->equityPortfolioMarker[$j]]!=='-')
									$this->fullPortfolio[$count][$this->equityPortfolioMarker[$j]]=doubleval($this->fullPortfolio[$count][$this->equityPortfolioMarker[$j]]);
							}
							$j++;
						}
					}
				}
				if($flag)
				{
					$this->fullPortfolio[$count]['Asset_Type']=$asset_type;
					$query = "INSERT INTO fund_portfolio (mfid,asset,asset_type,sector,qty,value,percentage)VALUES
						('".$this->allFunds[$key]['mfId']."','".$this->fullPortfolio[$count]['Asset']."','".$this->fullPortfolio[$count]['Asset_Type']."','".$this->fullPortfolio[$count]['Sector']."',
						'".$this->fullPortfolio[$count]['Qty']."','".$this->fullPortfolio[$count]['Value']."','".$this->fullPortfolio[$count]['Percentage']."'
						)";
					mysqli_query($this->conn,$query);
					$count++;
				}
			}
		}
		return $count;
	}
	
	public function getOtherPortfolioAssets($classname,$xpath,$key,$count)
	{
		//$count=0;
		
		$nodeList = $xpath->query("//table[string-length(@class)!=0 and @class and @class='$classname']");
		if($nodeList->item(0)!==NULL)
		{
			foreach ($nodeList as $subList)
			{
				$flag=false;
				$isBold=false;
				foreach ($xpath->evaluate('.//tr',$subList) as $node) {
					$j=0;
					$isBold=false;
					$flag=false;
					
					$childNodeList=$node->childNodes;
					foreach ($childNodeList as $childNode){
						if($childNode->nodeName=='th')
						{
							if($j==0)
								$asset_type=trim($childNode->nodeValue);
							$j++;
						}
						else
						{
							if($childNode->nodeName!='th' && $childNode->nodeName!="#text")
							{
								foreach ($xpath->evaluate('.//span/b',$childNode) as $boldNode)
								{
								
									var_dump($boldNode->nodeName);
									$isBold=true;
								
								}
								if($isBold)
									break;
								//var_dump($isBold);
								if($asset_type!='Mutual Funds' && $isBold != true)
								{
									$flag=true;
									$this->fullPortfolio[$count][$this->assetPortfolioMarker[$j]]=$childNode->nodeValue;
									if($j>=2)
									{
										if($this->fullPortfolio[$count][$this->assetPortfolioMarker[$j]]!=='-')
											$this->fullPortfolio[$count][$this->assetPortfolioMarker[$j]]=doubleval($this->fullPortfolio[$count][$this->assetPortfolioMarker[$j]]);
									}
								}
								elseif($asset_type=='Mutual Funds' && $isBold != true)
								{
									$flag=true;
									$this->fullPortfolio[$count][$this->mfPortfolioMarker[$j]]=$childNode->nodeValue;
									if($j>=3)
									{
										if($this->fullPortfolio[$count][$this->mfPortfolioMarker[$j]]!=='-')
											$this->fullPortfolio[$count][$this->mfPortfolioMarker[$j]]=doubleval($this->fullPortfolio[$count][$this->mfPortfolioMarker[$j]]);
									}
									
								}
								$j++;
							}
						}
					}
					//var_dump($flag);
					if($flag)
					{
						
						$this->fullPortfolio[$count]['Asset_Type']=$asset_type;
						var_dump($this->allFunds[$key]['mfId']);
						var_dump($asset_type);
						var_dump($count);
						if($asset_type!='Mutual Funds')
							$query = "INSERT INTO fund_portfolio (mfid,asset,asset_type,value,percentage,rating)VALUES
								('".$this->allFunds[$key]['mfId']."','".$this->fullPortfolio[$count]['Asset']."','".$this->fullPortfolio[$count]['Asset_Type']."',
								'".$this->fullPortfolio[$count]['Value']."','".$this->fullPortfolio[$count]['Percentage']."','".$this->fullPortfolio[$count]['Rating']."'
								)";
						else 
							$query = "INSERT INTO fund_portfolio (mfid,asset,asset_type,value,percentage,rating,fund_class)VALUES
								('".$this->allFunds[$key]['mfId']."','".$this->fullPortfolio[$count]['Asset']."','".$this->fullPortfolio[$count]['Asset_Type']."',
								'".$this->fullPortfolio[$count]['Value']."','".$this->fullPortfolio[$count]['Percentage']."','".$this->fullPortfolio[$count]['Rating']."',
								'".$this->fullPortfolio[$count]['Class']."'
								)";
						mysqli_query($this->conn,$query);
						$count++;
					}
				}
			}
		}
	}
}
?>