<?php 
class SwipeApiStringObject
{
	private $result;

	public function getResult(){return $this->result;}
	public function setResult($aResult){$this->result=$aResult;}
	public function setElement($key,$element){$this->$key=$element;}	

}

?>
