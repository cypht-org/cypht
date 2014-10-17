<?php

//*************************************************************
//************ SWIFT IDENTITY GENERATED PHP API ***************
//*Copyright (c) Swipe Identity (http://www.swipeidentity.com)*
//*THIS CLASS IS FOR THE USE OF THE SWIFT IDENTITY API ONLY ! *
//*YOU CAN USE IT FOR YOUR APPLICATIONS IF YOU ARE A MEMBER   *
//*AND IF YOU HAVE AN ACCOUNT OF A SWIFT IDENTITY REALM ONLY  *
//*                                                           *
//*************************************************************
define("RC_ERROR", 0);

define("NEED_REGISTER_SMS",3);
define("NEED_REGISTER_SWIPE",4);
define("NEED_TO_CHOOSE_AUTH",5);
define("RC_NO_SECOND_FACTOR_MATCH_BETWEEN_APP_AND_USER",10);
define("RC_APP_DOES_NOT_EXIST",15);
	
define("RC_NEW_USER",20);
define("RC_SWIPE_TIMEOUT",100);
define("RC_SWIPE_ACCEPTED",101);
define("RC_SWIPE_REJECTED",102);
	
define("RC_SMS_UNREGISTRED",200);
define("RC_SMS_DELIVERED",201);
define("RC_SMS_ANSWER_ACCEPTED",202);
define("RC_SMS_ANSWER_REJECTED",203);


class ApiBase
{
	private $apiUrl;
	private $curl;
	private $spiClasses;
	function __construct($url)
	{
		$this->apiUrl = $url;
		$this->getSpiObjectsList();
	}

	public function callServer($aProvidedUrl,$aParams)
	{
		$para = "/";
		foreach($aParams as $p)
		{
			$para.=urlencode($p)."/";
		}

		curl_setopt($this->curl, CURLOPT_URL,$this->apiUrl.$aProvidedUrl.$para);
		
		$x = curl_exec($this->curl);
		$y = new SimpleXMLElement($x);
		$status = $y->status[0];
		$arr = $this->toArray($y);
		if($status !== 1)
		{
			if(isset($arr["errorCode"]))
			{
				throw new Exception("The transaction has failed to process  : [".$arr["errorCode"]."]");
			}		
		}
		
		$v = (Array)$y->contentObject;
		
		if(isset($v["swipeApiList"]))
		{
			$toReturn = array();
			$type ="";
			foreach($v["swipeApiList"] as $key => $val)
			{
				$obj = $this->createObject($key,$val);
				array_push($toReturn,$obj);
			}
			return $toReturn;
		}
		else
		{
			$arr = $this->toArray($y);
			if(isset($arr["contentObject"]))
			{
				foreach($arr["contentObject"] as $key => $val)
				{
					$obj = $this->createObject($key,$val);
					return $obj;
				}
			}
		}
		return new SimpleXMLElement($x);
	}

	public function toArray(SimpleXMLElement $xml) {
		$array = (array)$xml;

		foreach ( array_slice($array, 0) as $key => $value ) {
			if ( $value instanceof SimpleXMLElement ) {
				$array[$key] = empty($value) ? NULL : $this->toArray($value);
			}
		}
		return $array;
	}

	public function createObject($type,$data)
	{
		if($data instanceof SimpleXMLElement )
		{
			$arr = $this->toArray($data);
		}
		else
		{	
			$arr = $data;
		}
		if(!is_array($arr)){return;}
		$type = $this->spiClasses[strtolower($type)];
		$obj = new $type();
		
		foreach($arr as $objKey => $objVal)
		{
			
			if(array_key_exists(strtolower($objKey),$this->spiClasses))
			{
				$element = $this->createObject($objKey,$objVal);
				$obj->setElement($objKey,$element);
			}
			else 
			{
				if(!is_array($objVal))
				{
					$obj->setElement($objKey,$objVal);
				}
				else
				{
					$element = $this->createObject($objKey,$objVal);
					$obj->setElement($objKey,$element);
				}
			}
		}
		return $obj;
	}

	private function getSpiObjectsList()
	{
		
		$this->spiClasses = array();
		if ($handle = opendir(dirname(__FILE__))) {


			/* This is the correct way to loop over the directory. */
			while (false !== ($entry = readdir($handle))) {
				if(preg_match("/\\.php/",$entry))
				{
					// remove the .php
					$name = preg_split("/\.php/",$entry);
					//array_push($spi,strtolower($name[0]));
					$this->spiClasses[strtolower($name[0])]=$name[0];
				}
			}
			closedir($handle);
		}
	}

	public function startTransaction()
	{
		$cookie_name = "cookiefile".rand(5, 1500);
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_COOKIESESSION, TRUE);
		curl_setopt($this->curl, CURLOPT_HEADER, 0);
		curl_setopt($this->curl, CURLOPT_COOKIEFILE,"/tmp/".$cookie_name);
		curl_setopt($this->curl, CURLOPT_COOKIEJAR,"/tmp/".$cookie_name);
		curl_setopt($this->curl, CURLOPT_COOKIE, session_name() . '=' . session_id());
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
	}

	public function endTransaction()
	{
		curl_close($this->curl);
	}

	
	public static function processResponse(RSChallengeResponse $aResponse,$aResponseType)
	{
	
		$arrResp = $aResponse->getResponse();
		$userResponse = "";
		foreach($arrResp as $resp)
		{
			    $userResponse.="<response>".$resp."</response>";
		}
		$toReturn = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><rsChallengeResponse><challengeResponseMatch>'.$aResponse->getChallengeResponseMatch().'</challengeResponseMatch>';
		$toReturn .='<challengeResponseType>'.$aResponseType.'</challengeResponseType>';
		$toReturn .=$userResponse;
		$toReturn .='<userEmail>'.$aResponse->getUserEmail().'</userEmail><userIp>'.$aResponse->getUserIp().'</userIp></rsChallengeResponse>';
		return self::bin2hex($toReturn);
	}
	
	public static function bin2hex($str) {
		$hex = "";
		$i = 0;
		do {
			$hex .= sprintf("%02x", ord($str{$i}));
			$i++;
		} while ($i < strlen($str));
		return $hex;
	}
	
	
	public static function dispatchUser($spiExpressSecondFactor)
	{
		$userSwipe = $spiExpressSecondFactor->getUserSwipeActivated();
		$userSMS = $spiExpressSecondFactor->getUserSmsActivated();
		$appSMS = $spiExpressSecondFactor->getAppSmsEnabled();
		$appSwipe = $spiExpressSecondFactor->getAppSwipeEnabled();
		
		if($spiExpressSecondFactor->getReturnCode() == RC_NEW_USER ||  $spiExpressSecondFactor->getReturnCode() == RC_NO_SECOND_FACTOR_MATCH_BETWEEN_APP_AND_USER)
		{
	
			if($appSwipe === "true" && $appSMS === "true")
			{
				// as swipe is always prefer if both is present.
				return NEED_TO_CHOOSE_AUTH;
			}
		
			if($appSwipe === "true" && $appSMS === "false")
			{
				return NEED_REGISTER_SWIPE;
			}
		
			if($appSwipe === "false" && $appSMS === "true")
			{
				return NEED_REGISTER_SMS;
			}
			throw new Exception("Application dosen't support any challenge.");
		}
		return $spiExpressSecondFactor->getReturnCode();
	}
}

?>
