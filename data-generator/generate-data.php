<?php


	foreach (glob("domain/*.php") as $filename) {
		include $filename;
	}
	foreach (glob("utils/*.php") as $filename) {
		include $filename;
	}


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
	$logger->setLogLevel(Logger::NONE);

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
					continue; // No se parsea el encabezado.
				}



				// PARSEA LA TABLA DE MATERIAS COLECTIVAS
				if ($tableHeaderLength === 7) {
					$currentRowIndex = 0;

					$currentSubject = null;
					if ($tableHeaderLength === $colsLength && $cols->item($currentRowIndex) != null) {
						$currentSubject = filter($cols->item($currentRowIndex++)->nodeValue);
						$lastSubject = $currentSubject;

						// Se cargan las materias colectivas 
						$subject = Subject::buildSubject($levelsIndex, $currentSubject);
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


					$firstModuleDayId = encodeWeekDay($weekDays[0]);

					$date = new Date();
					$date->dayId = $firstModuleDayId;
					
					$startHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$startHour = filterHour($startHourFull);

					$endHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$endHour = filterHour($endHourFull);


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
							$dateNextMod->dayId = $firstModuleDayId;						
							$dateNextMod->start = $date->end; // La hora fin del modulo anterior
							$dateNextMod->end = $endHour;
							$choice->addDate($dateNextMod);
						}
					} else if (sizeof($weekDays) == 2) {  // es una materia con dos modulos semanales, en dos dias separados
						$secondModuleDayId = encodeWeekDay($weekDays[1]);
						$date->start = $startHour;
						$date->end = $endHour;
						$choice->addDate($date);

						$dateNextDay = new Date();
						$dateNextDay->dayId = $secondModuleDayId;						
						$dateNextDay->start = $startHour;
						$dateNextDay->end = $endHour;
						$choice->addDate($dateNextDay);
					} else {
						$logger->error("CANTIDAD DE MODULOS POR SEMANA NO IMPLEMENTADA: ".sizeof($weekDays));
					}

					$currentNode = $cols->item($currentRowIndex++);
					if ($currentNode != null) {
						$choice->classroom = filter($currentNode->nodeValue)." ";
						$row = $row."Aula: ".$choice->classroom." ";
					}

					$currentTeacher = null;
					if ($cols->item($currentRowIndex) != null) {
						$currentTeacher = filter($cols->item($currentRowIndex)->nodeValue);
						$lastTeacher = $currentTeacher;
					}
					if ($currentTeacher == null) {
						$currentTeacher = $lastTeacher;
					}

					$choice->teacherId = findOrCreate($currentTeacher, $teacherNames);
					addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

					$row = $row."Profesor: ".$currentTeacher." ";
					$choices[$choicesIndex++] = $choice;
					$logger->info($row);

				} else if ($tableHeaderLength === 6 || $tableHeaderLength === 5) {

					// $tableHeaderLength === 6: PARSEA LA TABLA DE INSTRUMENTO ARMONICO E INSTRUMENTO PRINCIPAL
					// $tableHeaderLength === 5: PARSEA LA TABLA DE REPERTORIO

					$currentRowIndex = $tableHeaderLength;

					if ($tableHeaderLength === 6) {
						// ES LA TABLA DE INSTRUMENTO ARMONICO O INSTRUMENTO PRINCIPAL
						$currentSubject = null;

						if (strpos($tableHeaderValue, "ARMÓNICO") !== false && $lastSubject !== $tableHeaderValue) {
							// Es la tabla de Instrumentos Armonicos (esta en el mismo encabezado)
							$currentSubject = $tableHeaderValue;
							$lastSubject = $currentSubject;

							// Se cargan las materias instrumento armonico
							if ($tableHeaderLength === 6) { // Solo 6, para evitar las de 5 columnas, que es Repertorio.
								$subject = Subject::buildInstrument($levelsIndex, $currentSubject);
							} else {
								// Repertorio (si es que tiene 5 columnas)
								$subject = Subject::buildSubject($levelsIndex, $currentSubject);
							}
							$subject->id = ++$subjectsIndex;
							$subject->levelId = $levelsIndex;
							$subjects[$subjectsIndex] = $subject;
						} else {
							// Es la tabla de Instrumentos Principales (por descarte). Si no es el Armonico es el principal
							if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
								$currentSubject = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
								$lastSubject = $currentSubject;
					
								// Se carga las materias de instrumento principal
								if ($tableHeaderLength === 6) { // Solo 6, para evitar las de 5 columnas, que es Repertorio.
									$subject = Subject::buildInstrument($levelsIndex, $currentSubject);
								} else {
									// Repertorio (si es que tiene 5 columnas)
									$subject = Subject::buildSubject($levelsIndex, $currentSubject);
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
							$subject = Subject::buildSubject($levelsIndex, $currentSubject);
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

					$choice->teacherId = findOrCreate($currentTeacher, $teacherNames);
					addSubjectTeacher($choice->subjectId, $choice->teacherId, $subjectTeachers);

					$row = $row."Profesor: ".$choice->teacherId." ";

					$currentRowIndex--;

					if ($currentRowIndex <= $colsLength && $cols->item($colsLength-$currentRowIndex) != null) {
						$currentDay = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
						$lastDay = $currentDay;
					}
					if (isset($currentDay) && $currentDay == null) {
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
					if ($currentClassroom == null && isset($lastClassroom)) {
						// Soluciona el caso en donde hay dia pero no hay Aula, por lo que setea el Aula de la fila anterior.
						$currentClassroom = $lastClassroom;
					} else if (isset($currentDay) && !is_numeric($currentDay)) {
						// Soluciona el caso en donde hay Aula, pero no hay dia, por lo que setea el dia de la fila anterior.
						//$currentDay = $lastDay;
					}

					if (isset($currentDay)) {
						$encodedWeekDay = encodeWeekDay(filterAndSplitWeekDay($currentDay)[0]);
						$row = $row."Dia: ".$currentDay." "; // Se imprime aca, porque aun se sigue manipulando el dia al momento de analizar el Aula
					}
					$row = $row."Aula: ".$currentClassroom." ";

					if (isset($encodedWeekDay)) {
						$date = new Date();
						$date->dayId = $encodedWeekDay;
					}

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
		// Si lo que se esta evaluando es INSTRUMENTO ARMÓNICO PIANO, no se incrementa el Nivel.
		// Asi los dos instrumentos ARMONICOS no quedan en dos niveles distintos. 
		// Esto es porque lo pusieron en dos tablas y hasta ahora solo se cambiaba de tabla, cada vez que se cambiaba de nivel.
		if (strcmp($tableHeaderValue, "INSTRUMENTO ARMÓNICO PIANO") != 0) {
			$levelsIndex++;
		}
	}


	/* Json's generation */

	$htmlIdent = '&nbsp;&nbsp;&nbsp;';

	$message = '/*<br>';
	$message = $message.' * Datos extra&iacute;dos mediante PHP, de la p&aacute;gina de la EMPA el '.$current_datetime.'<br>';
	$message = $message.' *<br>';
	$message = $message.' *  Origen de los datos:<br>';
	$message = $message.' *    http://www.empa.edu.ar/index.php?seccion=formBasicaHorarios<br>';
	$message = $message.' */<br><br>';

	$message = $message.'var currentFormattedDatetime = \''.$current_datetime.'\';<br><br>';

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