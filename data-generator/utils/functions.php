<?php

	$GLOBALS['encodedWeekDays'] = array(0 => 'lunes', 1 => 'martes', 2 => 'miercoles', 3 => 'jueves', 4 => 'viernes', 5 => 'sabado');


	function buildWeekDayDataStructure() {
        $str = 'var allDates = [';
		for ($index = 0; $index < sizeof($GLOBALS['encodedWeekDays']); $index++) {
			$weekDay = $GLOBALS['encodedWeekDays'][$index];
			$weekDayFirstUpper = ucfirst($weekDay);	
			$str = $str.'<br>&nbsp;{id: '.$index.', name: \''.$weekDayFirstUpper.'\', initial: \''.$weekDayFirstUpper[0].'\'},';
		}
		$str = substr($str, 0, -1); // last comma
		$str = $str.'<br>];<br><br>';
		return $str;
    }

	
	function buildHoursDataStructure() {
		$startHour = 9;
		$endHour = 0;
		$endOfDay = 24;
        $str = 'var allHours = [';
		for ($index = 0; ($endHour + 2) < $endOfDay; $index++) {
			$endHour = 2 + $startHour;
			$str = $str.'<br>&nbsp;{id: '.$index.', start: \''.$startHour.'\', end: \''.$endHour.'\'},';
			$startHour = $endHour;
		}
		$str = substr($str, 0, -1); // last comma
		$str = $str.'<br>];<br><br>';
		return $str;
    }	
	
    function filter($str) {
        $str = preg_replace(['(\s+)u', '(^\s|\s$)u'], [' ', ''], $str);
        return $str;
    }

    function filterHour($str) {
        $str = filter($str);
        $str = explode(":", $str)[0];
        return $str;
    }

    function filterAndSplitWeekDay($str) {
        $str = filter($str);	
		$unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
									'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
									'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
									'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
									'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
		$str = strtr( $str, $unwanted_array );		
        $str = strtolower($str);
		$days = explode("y", $str);
        return $days;
    }


    function isClassroom($str) {
        $str = filter($str);	
		if (is_numeric($str)) {
			return TRUE;
		}		
		return FALSE;
    }	

	function encodeWeekDay($str) {
        $str = filter($str);	
		$key = array_search($str, $GLOBALS['encodedWeekDays']);
        return $key;
    }

	function isAWeekDay($str) {
        $str = filterAndSplitWeekDay($str)[0];
		foreach ($GLOBALS['encodedWeekDays'] as $weekDay) {
			if ($weekDay == $str) {
				return TRUE;
			}
		}		
		return FALSE;
    }	

	function startsWith($str, $query) {
        $str = filter($str);	
		if (substr($str, 0, strlen($query)) === $query) {
			return TRUE;
		}
		return FALSE;
    }

	function removeFromHyphenToEnd($str) {
		$pos = strrpos($str, "-");
		if ($pos === false) {
			return $str;
		}
		return trim(substr($str, 0, $pos));
    }


	function getIndexOfTeacherArray($value, &$array) {

		for ($index = 0; $index < sizeof($array); $index++) {
			$arrayValue = $array[$index];
			//echo 'COMPARANDO value: '.$value.'vs arrayValue: '.$arrayValue.'<br>';
			if (strcmp($arrayValue, removeComma($value)) === 0) {
			//	echo 'ENCONTRADO $value: '.$value.' en el index: '.$index.'<br>';
				return $index;
			}
		}

		$index = sizeof($array);
		//echo 'NO ENCONTRADO $value: '.$value.'. Se agrega en el index: '.$index.'<br>';
		$array[$index] = removeComma($value);
		return $index;
	}

    function removeComma($str) {
 		$str = str_replace(',', '', $str);    	
        return $str;
    }

    function addSubjectTeacher($subjectId, $teacherId, &$subjectTeachers) {

		for ($index = 0; $index < sizeof($subjectTeachers); $index++) {
			$subjectTeacher = $subjectTeachers[$index];
			//echo 'COMPARANDO value: '.$value.'vs arrayValue: '.$arrayValue.'<br>';
			if ($subjectId == $subjectTeacher->subjectId && $teacherId == $subjectTeacher->teacherId) {
			//	echo 'ENCONTRADO $value: '.$value.' en el index: '.$index.'<br>';
				return $index;
			}
		}

		$index = sizeof($subjectTeachers);
		//echo 'NO ENCONTRADO $value: '.$value.'. Se agrega en el index: '.$index.'<br>';
		$subjectTeacher = new SubjectTeacher();
		$subjectTeacher->subjectId = $subjectId;
		$subjectTeacher->teacherId = $teacherId;
		$subjectTeachers[$index] = $subjectTeacher;
		return $index;
	}


	function readValue($type, $node) {
		$nodeValue = filter($node->nodeValue);

		if ($nodeValue === "ASIGNATURA" || $nodeValue === "COMISIÓN" || $nodeValue === "AULA" || $nodeValue === "PROFESOR") {
			return $nodeValue;
		} else if ($nodeValue === "DÍA") {
			$weekDays = filterAndSplitWeekDay($node->nodeValue);
			$dayOne = encodeWeekDay($weekDays[0]);
			if (sizeof($weekDays) > 1) {  // es una materia con dos modulos semanales, en dos dias separados
				$dayTwo = encodeWeekDay($weekDays[1]);
				return $dayTwo;
			}
			return $dayOne;
		} else if ($nodeValue === "desde" || $nodeValue === "hasta") {
			return filterHour($node->nodeValue);
		}

	}


?>