<?php
class ParseController
{
	var $agent="Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0";
	
	
	function __construct(){
		//$username="admin";$password="divya123";$database="mfdb_dev";
		//$this->conn=mysqli_connect('127.0.0.1:3306',$username,$password,$database)or die( "Unable to select database");
	}
	
	
	public function getFundCategory($daily=NULL){
	
		$response=$this->doCurl("http://www.moneycontrol.com/mutual-funds/performance-tracker/returns/large-cap.html");
		$dom= new \DOMDocument();
		@$dom->loadHTML($response);
		$xpath = new DOMXpath($dom);
		
		
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
}
