<?php 
//*************************************************************
//************ SWIFT IDENTITY GENERATED PHP API ***************
//*Copyright (c) Swipe Identity (http://www.swipeidentity.com)*
//*THIS CLASS IS FOR THE USE OF THE SWIFT IDENTITY API ONLY ! *
//*YOU CAN USE IT FOR YOUR APPLICATIONS IF YOU ARE A MEMBER   *
//*AND IF YOU HAVE AN ACCOUNT OF A SWIFT IDENTITY REALM ONLY  *
//*                                                           *
//*Generated at :  Tue Aug 13 14:06:59 EDT 2013               *
//*************************************************************

class SpiExpressSecondFactor extends SpiBaseObject
{
	protected $serialVersionUID;
	protected $returnCode;
	protected $userCode;
	protected $userSwipeActivationCode;
	protected $userSwipeActivated;
	protected $appSmsEnabled;
	protected $appSwipeEnabled;
	protected $userSmsActivated;



	public function setElement($key,$element){$this->$key=$element;}


	 public function getSerialVersionUID(){ return $this->serialVersionUID;}
	 public function setSerialVersionUID($serialVersionUID){ $this->serialVersionUID=$serialVersionUID;}
	 public function getReturnCode(){ return $this->returnCode;}
	 public function setReturnCode($returnCode){ $this->returnCode=$returnCode;}
	 public function getUserCode(){ return $this->userCode;}
	 public function setUserCode($userCode){ $this->userCode=$userCode;}
	 public function getUserSwipeActivationCode(){ return $this->userSwipeActivationCode;}
	 public function setUserSwipeActivationCode($userSwipeActivationCode){ $this->userSwipeActivationCode=$userSwipeActivationCode;}
	 public function getUserSwipeActivated(){ return $this->userSwipeActivated;}
	 public function setUserSwipeActivated($userSwipeActivated){ $this->userSwipeActivated=$userSwipeActivated;}
	 public function getAppSmsEnabled(){ return $this->appSmsEnabled;}
	 public function setAppSmsEnabled($appSmsEnabled){ $this->appSmsEnabled=$appSmsEnabled;}
	 public function getAppSwipeEnabled(){ return $this->appSwipeEnabled;}
	 public function setAppSwipeEnabled($appSwipeEnabled){ $this->appSwipeEnabled=$appSwipeEnabled;}
	 public function getUserSmsActivated(){ return $this->userSmsActivated;}
	 public function setUserSmsActivated($userSmsActivated){ $this->userSmsActivated=$userSmsActivated;}
} ?>