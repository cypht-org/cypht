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

class SpiExpressApplication extends SpiBaseObject
{
	protected $serialVersionUID;
	protected $appSmsEnabled;
	protected $appSwipeEnabled;
	protected $firstChoice;
	protected $code;



	public function setElement($key,$element){$this->$key=$element;}


	 public function getSerialVersionUID(){ return $this->serialVersionUID;}
	 public function setSerialVersionUID($serialVersionUID){ $this->serialVersionUID=$serialVersionUID;}
	 public function getAppSmsEnabled(){ return $this->appSmsEnabled;}
	 public function setAppSmsEnabled($appSmsEnabled){ $this->appSmsEnabled=$appSmsEnabled;}
	 public function getAppSwipeEnabled(){ return $this->appSwipeEnabled;}
	 public function setAppSwipeEnabled($appSwipeEnabled){ $this->appSwipeEnabled=$appSwipeEnabled;}
	 public function getFirstChoice(){ return $this->firstChoice;}
	 public function setFirstChoice($firstChoice){ $this->firstChoice=$firstChoice;}
	 public function getCode(){ return $this->code;}
	 public function setCode($code){ $this->code=$code;}
} ?>