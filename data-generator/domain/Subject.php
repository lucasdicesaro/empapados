<?php

class Subject
{
    public $id;
    public $name;
    public $levelId;
    public $isInstrument;
    public $isForSingers;


   	function buildSubject($levelsIndex, $subjectName) {

		return self::buildItem($levelsIndex, $subjectName, 0);
	}

	function buildInstrument($levelsIndex, $subjectName) {

		return self::buildItem($levelsIndex, $subjectName, 1);
	}

	private function buildItem($levelsIndex, $subjectName, $isInstrument) {

		$subject = new Subject();
		$subject->name = $subjectName;
		$subject->levelId = $levelsIndex;
		$subject->isForSingers = (stripos($subject->name, 'cantantes') !== false) || (stripos($subject->name, 'canto') !== false) ? 1 : 0;
		$subject->isInstrument = $isInstrument;

		return $subject;
	}

}

?>