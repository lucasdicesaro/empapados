<?php

	include 'Domain.php';
	include 'Logger.php';
	include 'functions.php';

	$dom = new DOMDocument();
	libxml_use_internal_errors(true); // Disable warnings when loading non-well-formed HTML by DomDocument
	$html = $dom->loadHTMLFile("http://www.empa.edu.ar/index.php?seccion=formBasicaHorarios");
	if ($html === false) {
		echo "<br>Error de conexion. Saliendo...<br>";
		return;
	}
	$dom->preserveWhiteSpace = false; 


	date_default_timezone_set('America/Argentina/Buenos_Aires');
	$current_datetime = date('d/m/Y H:i:s', time());

	$logger = new Logger();
	$levels = [];
	$levelsIndex = -1;
	$titles = $dom->getElementsByTagName('h2');

	// PARSEO DE NIVELES	
  	foreach ($titles as $title) {
		$nodeValue = filter($title->nodeValue);
		if ($nodeValue != '' && !startsWith($nodeValue, 'HORARIOS') ) {
			$level = new Level();
			$level->id = ++$levelsIndex;
			$level->name = removeFromHyphenToEnd($nodeValue);
			$level->isShow =  (!startsWith($level->name, 'Instrumento') && !startsWith($level->name, 'Repertorio')) ? 1 : 0; //si es Repertorio o Instrumento, no se muestra en el combo
			$levels[$levelsIndex] = $level;
			$logger->info("Nivel: ".$level->name." - Mostrar: ".$level->isShow);
		}
	}


	$levelsIndex = 0;
	$subjectsIndex = -1;
	$choicesIndex = 0;
	$subjects = [];
	$choices = [];
	$lastDay = null;
	// PARSEO DE MATERIAS
	$tables = $dom->getElementsByTagName('table'); 
	foreach ($tables as $table) {
		$tbody = $table->getElementsByTagName('tbody');
		$rows = $tbody->item(0)->getElementsByTagName('tr');
		$colsLengthBefore = 0;
		foreach ($rows as $row) {
			$cols = $row->getElementsByTagName('td');
			$colsLength = $cols->length;

			if ($cols->item(0) != null) {
				if (filter($cols->item(0)->nodeValue) === "") {
					// Para amparar los casos donde toda una fila esta vacia
					continue;
				}

				$firstColValue = filter($cols->item(0)->nodeValue);
				if ($firstColValue === "ASIGNATURA" ||
					$firstColValue === "INSTRUMENTO" ||
					$firstColValue === "PROFESOR" ||
					$firstColValue === "Instrumento" ||
					strpos($firstColValue, "ARMÓNICO") !== false) {
					$tableHeaderLength = $colsLength;
					$tableHeaderValue = $firstColValue;
					$logger->debug("Encabezado: ".$tableHeaderValue." Cantidad de filas en el encabezado: ".$tableHeaderLength);
					continue; // No se parsea el encabezado.
				}

				$logger->debug("Cantidad de filas en el cuerpo: ".$colsLength);


				// PARSEA LA TABLA DE MATERIAS COLECTIVAS
				if ($tableHeaderLength === 7) {
					$currentRowIndex = 0;

					$currentSubject = null;
					if ($tableHeaderLength === $colsLength && $cols->item($currentRowIndex) != null) {
						$currentSubject = filter($cols->item($currentRowIndex++)->nodeValue);
						$lastSubject = $currentSubject;

						// Se cargan las materias colectivas 
						$subject = buildSubject($levelsIndex, $currentSubject);
						$subject->id = ++$subjectsIndex;
						$subject->levelId = $levelsIndex;
						$subjects[$subjectsIndex] = $subject;
						$logger->debug("Insertando en el indice ".$subjectsIndex." la materia : ".$subject->name);

					}
					if ($currentSubject === null) {
						$currentSubject = $lastSubject;
					}

					$row = "Materia: ".$currentSubject." ";

					// Se cargan las opciones
					$choice = new Choice();
					$choice->id = $choicesIndex;
					$choice->subjectId = $subjectsIndex;
					$choice->commission = filter($cols->item($currentRowIndex++)->nodeValue);
					$row = $row."Comision: ".$choice->commission." ";

					$day = filter($cols->item($currentRowIndex++)->nodeValue);
					$weekDays = filterAndSplitWeekDay($day);

					$startHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$startHour = filterHour($startHourFull);

					$endHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$endHour = filterHour($endHourFull);


					$dayOne = encodeWeekDay($weekDays[0]);

					$date = new Date();
					$date->dayId = $dayOne;

					if (sizeof($weekDays) == 1) {
						$difference = $endHour - $startHour;
						if ($difference <= 2) {// es una materia con un modulo semanal
							$date->start = $startHour;
							$date->end = $endHour;
							$choice->addDate($date);
						} else if ($difference == 4) { // es una materia con dos modulos semanales, uno a continuacion del otro
							$date->start = $startHour;
							$date->end = ($startHour+2);
							$choice->addDate($date);

							$dateNextMod = new Date();
							$dateNextMod->dayId = $dayOne;						
							$dateNextMod->start = $date->end; // La hora fin del modulo anterior
							$dateNextMod->end = $endHour;
							$choice->addDate($dateNextMod);
						}
					} else if (sizeof($weekDays) > 1) {  // es una materia con dos modulos semanales, en dos dias separados
						$dayTwo = encodeWeekDay($weekDays[1]);
						$date->start = $startHour;
						$date->end = $endHour;
						$choice->addDate($date);

						$dateNextDay = new Date();
						$dateNextDay->dayId = $dayTwo;						
						$dateNextDay->start = $startHour;
						$dateNextDay->end = $endHour;
						$choice->addDate($dateNextDay);
					}


					$choice->classroom = filter($cols->item($currentRowIndex++)->nodeValue)." ";
					$row = $row."Aula: ".$choice->classroom." ";

					$currentTeacher = null;
					if ($cols->item($currentRowIndex) != null) {
						$currentTeacher = filter($cols->item($currentRowIndex)->nodeValue);
						$lastTeacher = $currentTeacher;
					}
					if ($currentTeacher == null) {
						$currentTeacher = $lastTeacher;
					}

					$choice->teacherId = getIndexOfTeacherArray($currentTeacher, $teacherNames);
					addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

					$row = $row."Profesor: ".$currentTeacher." ";
					$choices[$choicesIndex++] = $choice;
					$logger->info($row);

				} else if ($tableHeaderLength === 6 || $tableHeaderLength === 5) {

					// $tableHeaderLength === 6: PARSEA LA TABLA DE INSTRUMENTO ARMONICO E INSTRUMENTOS
					// $tableHeaderLength === 5: PARSEA LA TABLA DE REPERTORIO

					$currentRowIndex = $tableHeaderLength;

					if ($tableHeaderLength === 6) {
						// ES LA TABLA DE INSTRUMENTO ARMONICO O INSTRUMENTOS
						$currentSubject = null;

						if (strpos($tableHeaderValue, "ARMÓNICO") !== false && $lastSubject !== $tableHeaderValue) {
							// Es la tabla de Instrumentos Armonicos (esta en el mismo encabezado)
							$currentSubject = $tableHeaderValue;
							$lastSubject = $currentSubject;

							// Se cargan las materias instrumento armonico
							$subject = buildSubject($levelsIndex, $currentSubject);
							if ($tableHeaderLength === 6) { // Solo 6, para evitar las de 5 columnas, que es Repertorio.
								// Se setea "1" en las materias que son Instrumento (se omite repertorio).
		 						$subject->isInstrument = 1;
							}
							$subject->id = ++$subjectsIndex;
							$subject->levelId = $levelsIndex;
							$subjects[$subjectsIndex] = $subject;
						} else {
							// Es la tabla de Instrumentos. Es por descarte. Si no es el Armonico es el individual
							if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
								$currentSubject = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
								$lastSubject = $currentSubject;
					
								// Se cargan las materias instrumento principal
								$subject = buildSubject($levelsIndex, $currentSubject);
								if ($tableHeaderLength === 6) { // Solo 6, para evitar las de 5 columnas, que es Repertorio.
									// Se setea "1" en las materias que son Instrumento (se omite repertorio).
			 						$subject->isInstrument = 1;
								}
								$subject->id = ++$subjectsIndex;
								$subject->levelId = $levelsIndex;
								$subjects[$subjectsIndex] = $subject;
							}
							if ($currentSubject === null) {
								$currentSubject = $lastSubject;
							}
						}

						$currentRowIndex--;

					} else if ($tableHeaderLength === 5) {
						// ES LA TABLA DE REPERTORIO
						$currentSubject = "Repertorio";
											
						if ($lastSubject !== $currentSubject) {
							// Se pone esta condicion ' $lastSubject !== $currentSubject ' para que entre solo una vez para Repertorio
					
							$lastSubject = $currentSubject;						
							// Se carga la materia Repertorio
							$subject = buildSubject($levelsIndex, $currentSubject);
							$subject->isForSingers = 1;
							$subject->id = ++$subjectsIndex;
							$subject->levelId = $levelsIndex;
							$subjects[$subjectsIndex] = $subject;
						}
					}

					$row = "Materia: ".$currentSubject." ";

					// Se cargan las opciones
					$choice = new Choice();
					$choice->id = $choicesIndex;
					$choice->subjectId = $subjectsIndex;
					
					$currentTeacher = null;
					if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
						$currentTeacher = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
						$lastTeacher = $currentTeacher;
					}
					if ($currentTeacher === null) {
						$currentTeacher = $lastTeacher;
					}

					$choice->teacherId = getIndexOfTeacherArray($currentTeacher, $teacherNames);
					addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

					$row = $row."Profesor: ".$choice->teacherId." ";

					$currentRowIndex--;

					if ($currentRowIndex <= $colsLength && $cols->item($colsLength-$currentRowIndex) != null) {
						$currentDay = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
						$lastDay = $currentDay;
					}
					if ($currentDay == null) {
						$currentDay = $lastDay;
					}

					$currentRowIndex--;

					$currentClassroom = null;
					if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
						$currentClassroom = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
						if (isAWeekDay(strtolower($currentClassroom))) { // Para amparar los casos en que la columna Aula esta vacia, y por ende la columna Dia queda corrida (ejemplo HERRMANN, Malena	el viernes no tiene aula, se toma la de arriba)
							$currentDay = $currentClassroom;
							$currentClassroom = $lastClassroom;
						} else {
							// Si es currentClassroom no viene un dia de semana, es porque es un aula normal.
							$lastClassroom = $currentClassroom;
						}
					}
					if ($currentClassroom == null ) {
						// Soluciona el caso en donde hay dia pero no hay Aula, por lo que setea el Aula de la fila anterior.
						$currentClassroom = $lastClassroom;
					} else if (!is_numeric($currentDay)) {
						// Soluciona el caso en donde hay Aula, pero no hay dia, por lo que setea el dia de la fila anterior.
						//$currentDay = $lastDay;
					}


					$encodedWeekDay = encodeWeekDay(filterAndSplitWeekDay($currentDay)[0]);
					$row = $row."Dia: ".$currentDay." "; // Se imprime aca, porque aun se sigue manipulando el dia al momento de analizar el Aula
					
					$row = $row."Aula: ".$currentClassroom." ";

					$date = new Date();
					$date->dayId = $encodedWeekDay;

					$currentRowIndex--;
					if ($colsLength >= $currentRowIndex) {
					 	$row = $row."Desde: ".filter($cols->item($colsLength-$currentRowIndex)->nodeValue)." ";
						$date->start = filterHour($cols->item($colsLength-$currentRowIndex)->nodeValue);
					}
					$currentRowIndex--;
					if ($colsLength >= $currentRowIndex) {
					 	$row = $row."Hasta: ".filter($cols->item($colsLength-$currentRowIndex)->nodeValue)." ";
						$date->end = filterHour($cols->item($colsLength-$currentRowIndex)->nodeValue);
					}

					$choice->addDate($date);
					$choice->commission = '';
					$choice->classroom = $currentClassroom;
					$choices[$choicesIndex++] = $choice;

				    $logger->info($row);

				} else {

				    $logger->error("Esta tabla tiene un encabezado de menos de 5 columnas. Ignorando...");

				}
			
			} else {
				//echo "Fila nula<br>";
				echo "<br>";
			}
		}
		// Si lo que se esta evaluando INSTRUMENTO ARMÓNICO PIANO, no se incrementa el Nivel. 
		if (strcmp($tableHeaderValue, "INSTRUMENTO ARMÓNICO PIANO") != 0) {
			$levelsIndex++;
		}
	}





/*
	$teacherNameForSubjects = '';
	$oldTeacherNameForSubjects = '';

	$instrumentName = '';
	$oldInstrumentName = '';
	$firstTimeInstruments = TRUE;

	$classroom = '';
	$day = '';

	$levelsIndex = 0;
	$subjects = [];
	$subjectsIndex = -1;
	$teacherNames = [];
	$subjectTeachers = [];
	$choices = [];
	$choicesIndex = 0;
	
	// PARSEO DE MATERIAS
	$tables = $dom->getElementsByTagName('table'); 
	foreach ($tables as $table) {
		$tbody = $table->getElementsByTagName('tbody');
		$rows = $tbody->item(0)->getElementsByTagName('tr');
		$colsLengthBefore = 0;
		foreach ($rows as $row) {
			$cols = $row->getElementsByTagName('td');
			$colsLength = $cols->length;


			if ($colsLength == 0 || $colsLength == 1) {
				continue;
			}
			$index = 0;
			if ($cols->item(0) != null) {				
				if (filter($cols->item(0)->nodeValue) === "") {
					// Para amparar los casos donde toda una fila esta vacia
					continue;
				}
				if ($cols->item(0)->getAttribute('rowspan') > 0) {
					if ($colsLength <= 5) {
						// Esta validacion es para descartar la tabla de Repertorio, donde  hay profesores como primer columna
						continue;
					}
					if ($colsLength >= $colsLengthBefore) { 
						// Se busca la mayor cantidad de columnas porque son las que tienen las materias.
						// Ademas, sacaron el <th> que tenian en 2018 y no se puede solo filtrar con 'rowspan'.
						$colsLengthBefore = $colsLength;

						$subject = new Subject();
						$subject->id = ++$subjectsIndex;
						$subject->name = filter($cols->item(0)->nodeValue);
						$subject->levelId = $levelsIndex;
						$subject->isForSingers = (stripos($subject->name, 'cantantes') !== false) ? 1 : 0;
						$subject->isInstrument = 0;
						$subjects[$subjectsIndex] = $subject;

						
						$index++; // Para poder obtener la comision en los casos donde la fila incluye la materia, se debe avanzar en 1 el indice.

						$choice = new Choice();
						$choice->id = $choicesIndex;
						$choice->commission = filter($cols->item($index)->nodeValue);
						$choice->subjectId = $subjectsIndex;						
					}


				} else {
					$choice = new Choice();
					$choice->id = $choicesIndex;
					if (filter($cols->item(0)->nodeValue) != '') {// Para amparar los casos en que hay una celda vacia antes de la comision 
						$choice->commission = filter($cols->item(0)->nodeValue);
					}
					$choice->subjectId = $subjectsIndex;
				}

				$weekDays = filterAndSplitWeekDay($cols->item($index+1)->nodeValue);

				$index++;

				$startHour = filterHour($cols->item($index+1)->nodeValue);

				$index++;

				$endHour = filterHour($cols->item($index+1)->nodeValue);
				$dayOne = encodeWeekDay($weekDays[0]);				

				$date = new Date();
				$date->dayId = $dayOne;

				if (sizeof($weekDays) == 1) {
					$difference = $endHour - $startHour;
					if ($difference <= 2) {// es una materia con un modulo semanal
						$date->start = $startHour;
						$date->end = $endHour;
						$choice->addDate($date);
					} else if ($difference == 4) { // es una materia con dos modulos semanales, uno a continuacion del otro
						$date->start = $startHour;
						$date->end = ($startHour+2);
						$choice->addDate($date);

						$dateNextMod = new Date();
						$dateNextMod->dayId = $dayOne;						
						$dateNextMod->start = $date->end; // La hora fin del modulo anterior
						$dateNextMod->end = $endHour;
						$choice->addDate($dateNextMod);
					}
				} else if (sizeof($weekDays) > 1) {  // es una materia con dos modulos semanales, en dos dias separados
					$dayTwo = encodeWeekDay($weekDays[1]);
					$date->start = $startHour;
					$date->end = $endHour;
					$choice->addDate($date);

					$dateNextDay = new Date();
					$dateNextDay->dayId = $dayTwo;						
					$dateNextDay->start = $startHour;
					$dateNextDay->end = $endHour;
					$choice->addDate($dateNextDay);
				}


				$index++;

				$choice->classroom = filter($cols->item($index++)->nodeValue);					

				$index++;

				// SE DESCARTAN VALORES NO DESEADOS EN EL COMBO de MATERIAS
				if ($cols->item($index) != null &&
					filter($cols->item($index)->nodeValue) !== "AULA" &&
					filter($cols->item($index)->nodeValue) !== "a cobertura" &&
					filter($cols->item($index)->nodeValue) !== "Hasta" &&
					filter($cols->item($index)->nodeValue) !== "15:00") {
					if ($colsLength > 4) {
						$teacherNameForSubjects = filter($cols->item($index++)->nodeValue);
					}
					if ($teacherNameForSubjects != '') { // Para amparar los casos de celdas sin profesor
						$oldTeacherNameForSubjects = $teacherNameForSubjects;
					} else {
						$teacherNameForSubjects = $oldTeacherNameForSubjects;
					}

					$choice->teacherId = getIndexOfTeacherArray($teacherNameForSubjects, $teacherNames);
					addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

					$choices[$choicesIndex++] = $choice;
				}
			} else {

				if ($colsLength > 5) {
					$instrumentName = filter($cols->item($colsLength-6)->nodeValue);
				}
				if ($instrumentName == "INSTRUMENTO") {
					continue;
				} else if ($instrumentName == '') {// Para amparar el caso del titulo "Repertorio" que esta fuera del esquema.
					$instrumentName = $levels[$levelsIndex]->name;
				}


				if ($oldInstrumentName != $instrumentName || $firstTimeInstruments) {
					$oldInstrumentName = $instrumentName;
					$firstTimeInstruments = FALSE;

					$subject = new Subject();
					$subject->id = ++$subjectsIndex;
					$subject->name = $instrumentName;
					$subject->levelId = $levelsIndex;
					$subject->isForSingers = (stripos($subject->name, 'Repertorio') !== false || stripos($subject->name, 'Canto') !== false) ? 1 : 0;
					$subject->isInstrument = 1;
					$subjects[$subjectsIndex] = $subject;

				}

				$choice = new Choice();
				$choice->subjectId = $subjectsIndex;
				$choice->id = $choicesIndex;

				if ($colsLength > 4) {
					$teacherNameForInstruments = filter($cols->item($colsLength-5)->nodeValue);
				}
				if ($teacherNameForInstruments != '') { // Para amparar los casos de celdas sin profesor
					$oldTeacherNameForInstruments = $teacherNameForInstruments;
				} else {
					$teacherNameForInstruments = $oldTeacherNameForInstruments;
				}


				if ($colsLength > 3) {
					if (is_numeric($day)) {
						$dayAux = $day;
					}
					$day = encodeWeekDay(filterAndSplitWeekDay($cols->item($colsLength-4)->nodeValue)[0]);
				}
				if ($colsLength > 2) {				
					$classroomAux = $classroom;
					$classroom = filter($cols->item($colsLength-3)->nodeValue);
					if ($classroom == '') {
						// Soluciona el caso en donde hay dia pero no hay Aula, por lo que setea el Aula de la fila anterior.
						$classroom = $classroomAux;
					} else if (!is_numeric($day)) {
						// Soluciona el caso en donde hay Aula, pero no hay dia, por lo que setea el dia de la fila anterior.
						$day = $dayAux;
					}

					if (isAWeekDay($classroom)) { // Para amparar los casos en que la columna Aula esta vacia, y por ende la columna Dia queda corrida
						$day = encodeWeekDay(filterAndSplitWeekDay($classroom)[0]);
						$classroom = $classroomAux;
					}
				}


				$date = new Date();
				$date->dayId = $day;
				$date->start = filterHour($cols->item($colsLength-2)->nodeValue);
				$date->end = filterHour($cols->item($colsLength-1)->nodeValue);
				$choice->addDate($date);

				$choice->commission = 'PEPE';
				$choice->classroom = $classroom;

				$choice->teacherId = getIndexOfTeacherArray($teacherNameForInstruments, $teacherNames);
				addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

				$choices[$choicesIndex++] = $choice;
			}					
		}
		$levelsIndex++;
	}

*/



	$htmlIdent = '&nbsp;&nbsp;&nbsp;';

	$message = '/*<br>';
	$message = $message.' * Datos extra&iacute;dos mediante PHP, de la p&aacute;gina de la EMPA el '.$current_datetime.'<br>';
	$message = $message.' *<br>';
	$message = $message.' *  Origen de los datos:<br>';
	$message = $message.' *    http://www.empa.edu.ar/index.php?seccion=formBasicaHorarios<br>';
	$message = $message.' */<br><br>';

	$message = $message.buildWeekDayDataStructure();
	$message = $message.buildHoursDataStructure();


	$message = $message.'var allLevels = [';
	for ($index = 0; $index < sizeof($levels); $index++) {
		$level = $levels[$index];
		$message = $message.'<br>'.$htmlIdent.'{id: '.$index.', isShow: '.$level->isShow.', name: \''.$level->name.'\'},';
	}	
	$message = substr($message, 0, -1); // last comma
	$message = $message.'];<br><br>';


	$subjectsSize = sizeof($subjects);
	$logger->debug("Cantidad de Materias: ".$subjectsSize);

	$message = $message.'var allSubjects = [';
	for ($index = 0; $index < sizeof($subjects); $index++) {
		//$logger->debug("Accediendo a la materia con indice : ".$index);
		$subject = $subjects[$index];
		$message = $message.'<br>'.$htmlIdent.'{id: '.$index.', levelId: '.$subject->levelId.', isInstrument: '.$subject->isInstrument.', isForSingers: '.$subject->isForSingers.', name: \''.$subject->name.'\'},';
	}	
	$message = substr($message, 0, -1); // last comma
	$message = $message.'];<br><br>';


	$teachersSize = sizeof($teacherNames);
	$logger->debug("Cantidad de Profesores: ".$teachersSize);

	$message = $message.'var allTeacherNames = [';
	//sort($teacherNames);
	for ($index = 0; $index < sizeof($teacherNames); $index++) {
		$teacherName = $teacherNames[$index];
		$message = $message.'<br>'.$htmlIdent.'{id: '.$index.', name: \''.$teacherName.'\'},';
	}	
	$message = substr($message, 0, -1); // last comma
	$message = $message.'];<br><br>';


	$choicessSize = sizeof($choices);
	$logger->debug("Cantidad de Opciones: ".$choicessSize);	
	
	$message = $message.'var allChoices = [';
	for ($index = 0; $index < sizeof($choices); $index++) {
		$choice = $choices[$index];
		$message = $message.'<br>'.$htmlIdent.'{id: '.$index.', subjectId: '.$choice->subjectId.', commission: \''.$choice->commission.'\', dates: [';
		$dates = $choice->dates;
		for ($j = 0; $j < sizeof($dates); $j++) {
			$date = $dates[$j];
			$message = $message.'{dayId: '.$date->dayId.', start: '.$date->start.', end: '.$date->end.'},';
		}
		$message = substr($message, 0, -1); // last comma
		$message = $message.'], classroom: \''.$choice->classroom.'\', teacherId: '.$choice->teacherId.'},';
	}	
	$message = substr($message, 0, -1); // last comma
	$message = $message.'];<br><br>';


	$message = $message.'var subjectTeachers = [';
	for ($index = 0; $index < sizeof($subjectTeachers); $index++) {
		$subjectTeacher = $subjectTeachers[$index];
		$message = $message.'<br>'.$htmlIdent.'{id: '.$index.', subjectId: '.$subjectTeacher->subjectId.', teacherId: '.$subjectTeacher->teacherId.'},';
	}	
	$message = substr($message, 0, -1); // last comma
	$message = $message.'];<br><br>';



	echo $message;

?>