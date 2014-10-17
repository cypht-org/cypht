<?php

class SwipeIdentityExpressAPI extends ApiBase{

function __construct($url) {
	parent::__construct($url);
  } 

	 public function setUserSmsNumber($anUser,$anApplicationCode,$anPhoneNumber)
	{
		 return parent::callServer("setUserSmsNumber", array($anUser,$anApplicationCode,$anPhoneNumber));
	}

	 public function disableSwipe($anApplicationCode)
	{
		 return parent::callServer("disableSwipe", array($anApplicationCode));
	}

	 public function disableSms($anApplicationCode)
	{
		 return parent::callServer("disableSms", array($anApplicationCode));
	}

	 public function answerSMS($anUser,$anApplicationCode,$aSMSAnswer)
	{
		 return parent::callServer("answerSMS", array($anUser,$anApplicationCode,$aSMSAnswer));
	}

	 public function setSwipeAsPrimaryChoice($anApplicationCode)
	{
		 return parent::callServer("setSwipeAsPrimaryChoice", array($anApplicationCode));
	}

	 public function enableSms($anApplicationCode)
	{
		 return parent::callServer("enableSms", array($anApplicationCode));
	}

	 public function apiSwipeTokenLogin($aSwipeToken,$anApiKey)
	{
		 return parent::callServer("api-swipe-token-login", array($aSwipeToken,$anApiKey));
	}

	 public function apiLogin($anUserEmail,$anUserPassword,$anApiKey)
	{
		 return parent::callServer("api-login", array($anUserEmail,$anUserPassword,$anApiKey));
	}

	 public function enableSwipe($anApplicationCode)
	{
		 return parent::callServer("enableSwipe", array($anApplicationCode));
	}

	 public function doSecondFactor($anUser,$anApplicationCode,$anIpAddress)
	{
		 return parent::callServer("do-second-factor", array($anUser,$anApplicationCode,$anIpAddress));
	}

	 public function setSmsAsPrimaryChoice($anApplicationCode)
	{
		 return parent::callServer("setSmsAsPrimaryChoice", array($anApplicationCode));
	}

	 public function getApplicationExpress($anApplicationCode)
	{
		 return parent::callServer("getApplicationExpress", array($anApplicationCode));
	}

	 public function removeExpressUser($anUser,$anApplicationCode)
	{
		 return parent::callServer("removeExpressUser", array($anUser,$anApplicationCode));
	}

	 public function getUsersExpress($anApplicationCode)
	{
		 return parent::callServer("getUsersExpress", array($anApplicationCode));
	}


        public function validatePhoneNumber($aPhoneNumber,$aCountryCode)
        {
		$phone =  parent::callServer("validate-phone-number", array($aPhoneNumber,$aCountryCode));
		return $phone->getResult();
        }


	function __autoload($class_name)	
	{	
		require_once(dirname(__FILE__)."/".strtolower($class_name).".php");
	}


}
