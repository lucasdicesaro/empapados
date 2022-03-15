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
	$logger->setLogLevel(Logger::DEBUG);

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
					//$logger->debug("Encabezado: ".$tableHeaderValue." Cantidad de filas en el encabezado: ".$tableHeaderLength);
					continue; // No se parsea el encabezado.
				}

				//$logger->debug("Cantidad de filas en el cuerpo: ".$colsLength);


				// PARSEA LA TABLA DE MATERIAS COLECTIVAS
				if ($tableHeaderLength === 7) {
					$currentRowIndex = 0;

					$currentSubject = null;
					if ($tableHeaderLength === $colsLength && $cols->item($currentRowIndex) != null) {
						$currentSubject = filter($cols->item($currentRowIndex++)->nodeValue);
						$lastSubject = $currentSubject;
					}
					if ($currentSubject === null) {
						$currentSubject = $lastSubject;
					}

					$row = "Materia: ".$currentSubject." ";
					$commission = filter($cols->item($currentRowIndex++)->nodeValue);
					$row = $row."Comision: ".$commission." ";

					$day = filter($cols->item($currentRowIndex++)->nodeValue);
					$weekDays = filterAndSplitWeekDay($day);
					$row = $row."Dia: ".$day." ";

					$firstModuleDayId = encodeWeekDay($weekDays[0]);

					$startHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$startHour = filterHour($startHourFull);
					$row = $row."Desde: ".$startHourFull." (startHour: ".$startHour.") ";
					$endHourFull = filter($cols->item($currentRowIndex++)->nodeValue);
					$endHour = filterHour($endHourFull);
					$row = $row."Hasta: ".$endHourFull." (endHour: ".$endHour.") ";

					if (sizeof($weekDays) == 1) {
						$row = $row." (dayId: ".$firstModuleDayId.") ";
					} else if (sizeof($weekDays) == 2) {  // es una materia con dos modulos semanales, en dos dias separados
						$row = $row." (firstDayId: ".$firstModuleDayId.") ";
						$secondModuleDayId = encodeWeekDay($weekDays[1]);
						$row = $row." (secondDayId: ".$secondModuleDayId.") ";
					} else {
						$logger->error("CANTIDAD DE MODULOS POR SEMANA NO IMPLEMENTADA: ".sizeof($weekDays));
					}

					$currentNode = $cols->item($currentRowIndex++);
					if ($currentNode != null) {
						$classroom = filter($currentNode->nodeValue)." ";
						$row = $row."Aula: ".$classroom." ";
					}

					$currentTeacher = null;
					if ($cols->item($currentRowIndex) != null) {
						$currentTeacher = filter($cols->item($currentRowIndex)->nodeValue);
						$lastTeacher = $currentTeacher;
					}
					if ($currentTeacher == null) {
						$currentTeacher = $lastTeacher;
					}

					$teacherId = findOrCreate($currentTeacher, $teacherNames);
					$row = $row."Profesor: ".$currentTeacher." (teacherId: ".$teacherId.") ";

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
						} else {
							// Es la tabla de Instrumentos. Es por descarte. Si no es el Armonico es el individual
							if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
								$currentSubject = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
								$lastSubject = $currentSubject;
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
						}
					}

					$row = "Materia: ".$currentSubject." ";

					$currentTeacher = null;
					if ($colsLength >= $currentRowIndex && $cols->item($colsLength-$currentRowIndex) != null) {
						$currentTeacher = filter($cols->item($colsLength-$currentRowIndex)->nodeValue);
						$lastTeacher = $currentTeacher;
					}
					if ($currentTeacher === null) {
						$currentTeacher = $lastTeacher;
					}

					$currentTeacherId = findOrCreate($currentTeacher, $teacherNames);

					$row = $row."Profesor: ".$currentTeacher." (teacherId: ".$currentTeacherId.") ";

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
						 	//$row = $row." Seteando el currentDay a partir de lo que deberia ser una classroom. Classroom: ".$currentClassroom." ";
							$currentDay = $currentClassroom;
						 	//$row = $row." Seteando en currentClassroom el valor de lastClassroom. LastClassroom: ".$lastClassroom." ";
							$currentClassroom = $lastClassroom;
						} else {
							// Si es currentClassroom no viene un dia de semana, es porque es un aula normal.
							$lastClassroom = $currentClassroom;
						}
					}
					if ($currentClassroom == null && isset($lastClassroom)) {
						// Soluciona el caso en donde hay dia pero no hay Aula, por lo que setea el Aula de la fila anterior.
						$currentClassroom = $lastClassroom;
						//$row = $row."currentClassroom null. Seteando lastClassroom: ".$lastClassroom." en currentClassroom. ";
					} else if (isset($currentDay) && !is_numeric($currentDay)) {
						// Soluciona el caso en donde hay Aula, pero no hay dia, por lo que setea el dia de la fila anterior.
						//$row = $row."currentClassroom not null y currentDay is not numeric. CurrentDay: ".$currentDay." Seteando lastDay: ".$lastDay." en currentDay. ";
						//$currentDay = $lastDay;
					}

					if (isset($currentDay)) {
						$encodedWeekDay = encodeWeekDay(filterAndSplitWeekDay($currentDay)[0]);
						$row = $row."Dia: ".$currentDay." (dayId: ".$encodedWeekDay.") "; // Se imprime aca, porque aun se sigue manipulando el dia al momento de analizar el Aula
					}
					$row = $row."Aula: ".$currentClassroom." ";


					$currentRowIndex--;
					if ($colsLength >= $currentRowIndex) {
					 	$row = $row."Desde: ".filter($cols->item($colsLength-$currentRowIndex)->nodeValue)." ";
						$start = filterHour($cols->item($colsLength-$currentRowIndex)->nodeValue);
					 	$row = $row." (start: ".$start.") ";
					}
					$currentRowIndex--;
					if ($colsLength >= $currentRowIndex) {
					 	$row = $row."Hasta: ".filter($cols->item($colsLength-$currentRowIndex)->nodeValue)." ";
						$end = filterHour($cols->item($colsLength-$currentRowIndex)->nodeValue);
					 	$row = $row." (end: ".$end.") ";
					}


				    $logger->info($row);

				} else {

				    $logger->error("Esta tabla tiene un encabezado de menos de 5 columnas. Ignorando...");

				}
			
			} else {
				//echo "Fila nula<br>";
				echo "<br>";
			}
		}
	}


?>