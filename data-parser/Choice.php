<?php

class Choice
{
    public $id;
    public $subjectId;
    public $commission;
    public $classroom;
    public $teacherId;
  	public $dates = array();

	public function addDate($date) {
		$this->dates[] = $date;
	}
}

?>