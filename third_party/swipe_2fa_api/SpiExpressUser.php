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

class SpiExpressUser extends SpiBaseObject
{
	protected $serialVersionUID;
	protected $smsEnabled;
	protected $swipeEnabled;
	protected $smsNumber;
	protected $name;



	public function setElement($key,$element){$this->$key=$element;}


	 public function getSerialVersionUID(){ return $this->serialVersionUID;}
	 public function setSerialVersionUID($serialVersionUID){ $this->serialVersionUID=$serialVersionUID;}
	 public function getSmsEnabled(){ return $this->smsEnabled;}
	 public function setSmsEnabled($smsEnabled){ $this->smsEnabled=$smsEnabled;}
	 public function getSwipeEnabled(){ return $this->swipeEnabled;}
	 public function setSwipeEnabled($swipeEnabled){ $this->swipeEnabled=$swipeEnabled;}
	 public function getSmsNumber(){ return $this->smsNumber;}
	 public function setSmsNumber($smsNumber){ $this->smsNumber=$smsNumber;}
	 public function getName(){ return $this->name;}
	 public function setName($name){ $this->name=$name;}
} ?>