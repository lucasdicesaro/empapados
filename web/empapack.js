
$(document).ready(function() {



/****************************************************************************************************
*
* PARTE 1:
*
* Carga de Combos. Definicion de handlers "change" de los mismos.
*
*
*
*****************************************************************************************************/


	var EMPTY_SELECTION = "000";
	var SATURDAY = 5;
	var MAX_SUBJECT_SIZE = 10;


	var selectedLevelIds = [];
	var selectedSubjectIds = [];
	var selectedInstrumentId;
	var selectedTeacherIds = [];
	var selectedDateTimes = [];
	var selectedInstrumentAndSubjectsIds = [];

	var dateTimeBooleanMatrix = [];



	function showFilters() {

		populateLevelsDropdown()

		populateInstrumentsDropdown()

		populateSubjectsDropdown()

		setChangeEventToTeachersDropdown()

		populateScheduleTable()

	}


	showFilters();


	function populateLevelsDropdown() {
		$.each(allLevels, function(index, level) {
			if (level.isShow) {
				$("#levelSelect").append('<option value="'+level.id+'">'+level.name+'</option>');
			}
		});

		// https://gist.github.com/AkdM/8518513
		$("#levelSelect").change(function () {
            $("#subjectSelect, #teacherSelect").find("option:gt(0)").remove();
            $("#subjectSelect").find("option:first").text("-Seleccione un Nivel-");

   			selectedLevelIds = $('select#levelSelect').val();

			if (selectedLevelIds == null || selectedLevelIds.length == 0) {
				return;
			}

			selectedSubjectIds = getSubjectIdsFromLevels(selectedLevelIds);
            //console.log("selectedSubjectIds : " + selectedSubjectIds);

            $.each(selectedSubjectIds, function (index, selectedSubjectId) {
            	var selectedSubject = allSubjects[selectedSubjectId];
                $("#subjectSelect").find("option:first").text("-Seleccione Materias-");
                $("#subjectSelect").append('<option rel="' + index + '" value="'+selectedSubjectId+'">'+selectedSubject.name+' ('+allLevels[selectedSubject.levelId].name+')</option>');
            });            
			$("#subjectSelect").attr("size", (selectedSubjectIds.length + 1));
			$("#teacherSelect").attr("size", 1);
           	$("#subjectSelect option:first-child").attr("selected", "selected");
           	$('select#subjectSelect').val(null);
          	$("#subjectSelect").change();
        });
	}


	function populateInstrumentsDropdown() {
		$.each(allSubjects, function(index, subject) {
			if (subject.isInstrument) {
				$("#instrumentSelect").append('<option value="'+subject.id+'">'+subject.name+'</option>');				
			}
		});

        $("#instrumentSelect").change(function () {

			$("#teacherSelect").find("option:gt(0)").remove();
            $("#teacherSelect").find("option:first").text("-Seleccione una Materia o Instrumento-");

            selectedInstrumentId = $(this).find('option:selected').val();
            console.log("Instrument Select INDEX : " + selectedInstrumentId);
			if (typeof selectedInstrumentId == 'undefined' || selectedInstrumentId == EMPTY_SELECTION) {
				//$("#consolePrph").text('Debe seleccionar un instrumento');
				//return;
				selectedInstrumentId = null; // No se selecciono instrumento
            	console.log("No se selecciono instrumento");
			}
			//selectedInstrument = allSubjects[selectedInstrumentId];
            //console.log("selectedInstrumentId: " + selectedInstrumentId+"-selectedSubjectIds: " + selectedSubjectIds);
			populateTeachersDropdown(selectedInstrumentId, selectedSubjectIds, subjectTeachers);
        });	
	}

	function populateSubjectsDropdown() {
		$("#subjectSelect").change(function () {
			if (selectedLevelIds == null || selectedLevelIds.length == 0) {
				$("#consolePrph").text('Debe seleccionar un nivel');
				return;
			}

			$("#teacherSelect").find("option:gt(0)").remove();
            $("#teacherSelect").find("option:first").text("-Seleccione una Materia o Instrumento-");
			
			selectedSubjectIds = $('select#subjectSelect').val();
            //console.log("Materias Select INDEX : " + selectedSubjectIds);
			$("#consolePrph").text('');
			if (selectedSubjectIds == null || selectedSubjectIds.length == 0) {
				console.log("Materias seleccionadas: Ninguna");
				//$("#consolePrph").text('Debe seleccionar al menos una materia, o Todas');	
			} else if (selectedSubjectIds[0] == EMPTY_SELECTION) {
				console.log("Materias seleccionadas: Todos");
				$("#consolePrph").text('Ha seleccionado todas las materias');
				selectedSubjectIds = getSubjectIdsFromLevels(selectedLevelIds);
			} else {
				console.log("Materias seleccionadas: Algunas");
				//for (var a = 0; a < selectedSubjectIds.length; a++) {
				//	console.log(allSubjects[selectedSubjectIds[a]]);
				//}
			}

            //console.log("selectedInstrumentId: " + selectedInstrumentId+"-selectedSubjectIds: " + selectedSubjectIds);
			populateTeachersDropdown(selectedInstrumentId, selectedSubjectIds, subjectTeachers);
        });
	}

	function setChangeEventToTeachersDropdown() {
		$("#teacherSelect").change(function () {
			if (selectedLevelIds == null || selectedLevelIds.length == 0) {
				$("#consolePrph").text('Debe seleccionar un nivel');
				return;
			}
			if ((selectedSubjectIds == null || selectedSubjectIds.length == 0) && 
				(selectedInstrumentId == null || selectedInstrumentId  == '')) {
				$("#consolePrph").text('Debe seleccionar al menos una materia o instrumento');
				return;
			}
	
			selectedTeacherIds = $('select#teacherSelect').val();
            //console.log("Materias Select INDEX : " + selectedTeacherIds);
			if (selectedTeacherIds == null || selectedTeacherIds.length == 0) {
				$("#consolePrph").text('Debe seleccionar al menos un profesor, o Todos');
				return;
			}

			if (selectedTeacherIds[0] == EMPTY_SELECTION) {
				$("#consolePrph").text('Ha seleccionado todos los profesores');
				console.log("Profesores seleccionados: Todos");
			} else {		
				console.log("Profesores seleccionados: Algunos");
				//for (var a = 0; a < selectedTeacherIds.length; a++) {
				//	console.log(allTeacherNames[selectedTeacherIds[a]]);
				//}
			}
        });		

	}


	function populateScheduleTable() {

		// Tabla de Horarios 
		var table = $('#scheduleSelectionSection');
		var thead = $('<thead></thead>').appendTo(table)
		var row = $('<tr></tr>').appendTo(thead)
		$('<th></th>').text('Horas').appendTo(row);
		$.each(allDates, function(index, date) {
			$('<th></th>').text(date.initial).appendTo(row);
		});
				

		var tbody = $('<tbody></tbody>').appendTo(table)
		dateTimeBooleanMatrix = new Array;
		$.each(allHours, function(hourIndex, hour) {
			var row = $('<tr></tr>').appendTo(tbody)
			$('<th id="selectionHour' + hour.start + '"></th>').text(hour.start + '-' + hour.end).appendTo(row);
			var dateTimeBooleanRow = new Array;
			$.each(allDates, function(dateIndex, date) {
				dateTimeBooleanRow[dateIndex] = false;

				if ((date.id - SATURDAY != 0) || hour.start < 17) { 
					$('<td class="place" id="selectionWeekDay' + date.id + '_' + hour.id + '" ></td>').text('X').attr('style', 'text-align: center').appendTo(row).click(function() {
						if (!$(this).val()) {
							$(this).css('background-color', '#89b789');
							$(this).text('O');
						}
						else {
							$(this).css('background-color', '#dddfcd');
							$(this).text('X');
						}
						var id = $(this).attr('id');
						$(this).val(!$(this).val());
						
						var coords = id.substring("selectionWeekDay".length).split("_");
						//console.log ('Valor de ' + coords[0] + ' ' + coords[1] + ': ' + $(this).val());
						var dateTimeBooleanRow = dateTimeBooleanMatrix[coords[1]];
						dateTimeBooleanRow[coords[0]] = $(this).val();
						//console.log("Se selecciono el dia: " + allDates[coords[0]].name + " y horario: " + allHours[coords[1]].start + "-" + allHours[coords[1]].end + "hs");

					});
				} else {
					//console.log('los sabados despues de las 17, no hay actividades date.id_ ' + date.id+'-dhour.start ' + hour.start);
					// los sabados despues de las 17, no hay actividades.
					$('<td id="selectionWeekDay' + date.id + '_' + hour.id + '" ></td>').text('-').attr('style', 'text-align: center').appendTo(row);
				}
			});
			dateTimeBooleanMatrix[hourIndex] = dateTimeBooleanRow;
		});
	}


	function populateTeachersDropdown(selectedInstrumentId, selectedSubjectIds, subjectTeachers) {
		selectedInstrumentAndSubjectsIds = mergeArrays(selectedInstrumentId, selectedSubjectIds);
		var selectedSubjectTeachers = [];
		var selectedSubjectTeachersIndex = 0;

		for (var j = 0; j < selectedInstrumentAndSubjectsIds.length; j++) {
			var selectedInstrumentAndSubjectsId = selectedInstrumentAndSubjectsIds[j];

			for (var i = 0; i < subjectTeachers.length; i++) {
				var subjectTeacher = subjectTeachers[i];
				if (subjectTeacher.subjectId == selectedInstrumentAndSubjectsId) {
					//console.log('subjectTeacher.subjectId: ' + subjectTeacher.subjectId + '-subjectTeacher.teacherId: ' + subjectTeacher.teacherId);
					selectedSubjectTeachers[selectedSubjectTeachersIndex++] = subjectTeacher;
				}
			}
		}

		$("#teacherSelect").find("option:gt(0)").remove();
		$("#teacherSelect").attr("size", (selectedSubjectTeachers.length + 1));
		$.each(selectedSubjectTeachers, function(a, selectedSubjectTeacher) {
			var teacherName = allTeacherNames[selectedSubjectTeacher.teacherId].name;
			//console.log('Agregando el profesor: ' + teacherName);
			$("#teacherSelect").find("option:first").text("-Todos los profesores-");
			$("#teacherSelect").append('<option value="'+a+'">'+teacherName+' ('+allSubjects[selectedSubjectTeacher.subjectId].name+')</option>');
		});	

        $("#teacherSelect option:first-child").attr("selected", "selected");
        $('select#teacherSelect').val(EMPTY_SELECTION);
      	$("#teacherSelect").change();
	}



	function getSubjectIdsFromLevels(selectedlevelIds) {
		var selectedSubjectIds = [];
		var selectedSubjectIdsIndex = 0;
		for (var a = 0; a < selectedlevelIds.length; a++) {
			var selectedlevelId = selectedlevelIds[a];
			for (var p = 0; p < allSubjects.length; p++) {
				var subject = allSubjects[p];
				if (subject.levelId == selectedlevelId) {
					selectedSubjectIds[selectedSubjectIdsIndex++] = subject.id;
				}
			}
		}
		return selectedSubjectIds;
	}	

	function getTeacherIdsFromLevels(selectedlevelIds) {
		var selectedTeacherIds = [];
		var selectedTeacherIdsIndex = 0;
		for (var a = 0; a < selectedlevelIds.length; a++) {
			var selectedlevelId = selectedlevelIds[a];
			for (var p = 0; p < allSubjects.length; p++) {
				var subject = allSubjects[p];
				if (subject.levelId == selectedlevelId) {
					selectedTeacherIds[selectedTeacherIdsIndex++] = subject.id;
				}
			}
		}
		return selectedTeacherIds;
	}	


	function mergeArrays(selectedInstrumentId, selectedSubjectIds) {

		var selectedInstrumentAndSubjectsIds = [];
        //console.log("selectedInstrumentId: " + selectedInstrumentId+"-selectedSubjectIds: " + selectedSubjectIds);
		if (selectedInstrumentId != null) {
			selectedInstrumentAndSubjectsIds = selectedInstrumentAndSubjectsIds.concat(selectedInstrumentId);
		}
		if (selectedSubjectIds != null) {
			selectedInstrumentAndSubjectsIds = selectedInstrumentAndSubjectsIds.concat(selectedSubjectIds);
		}

		return selectedInstrumentAndSubjectsIds;
	}










/****************************************************************************************************
*
* PARTE 2:
*
* Validacion de los Filtros Ingresados. Visualizacion de los filtros. 
*
*
*
*****************************************************************************************************/





	function filtersPreview() {
		//console.log('============================================');
		$("#consolePrph").empty();
		var selectedLevelsFilter = $('#selectedLevelsFilter').empty().css('color', '#555');		
		var selectedInstrumentFilter = $('#selectedInstrumentFilter').empty().css('color', '#555');
		var selectedSubjectsFilter = $('#selectedSubjectsFilter').empty().css('color', '#555');
		var selectedTeachersFilter = $('#selectedTeachersFilter').empty().css('color', '#555');
		var selectedDatesFilter = $('#selectedDatesFilter').empty().css('color', '#555');

		if (selectedLevelIds == null) {
			//$("#consolePrph").text('Debe seleccionar un nivel');
			selectedLevelsFilter.text('Debe seleccionar un nivel').css('color', '#f44336');
			return -1;
		}
		$.each(selectedLevelIds, function(i, selectedLevelId) {
			selectedLevelsFilter.text(allLevels[selectedLevelId].name);
		});

		selectedInstrumentFilter.text(selectedInstrumentId != null && selectedInstrumentId != EMPTY_SELECTION ? allSubjects[selectedInstrumentId].name : 'Ninguno')
		
		var table = $('<table></table>').appendTo(selectedSubjectsFilter);
		if (selectedSubjectIds != null && selectedSubjectIds.length > 0) {
			$.each(selectedSubjectIds, function(i, selectedSubjectId) {				
				var row = $('<tr></tr>').appendTo(table)
				$('<td></td>').text(allSubjects[selectedSubjectId].name).appendTo(row)
			});
		} else {
			selectedSubjectsFilter.text('Ninguna')
		}

		if ((selectedSubjectIds == null || selectedSubjectIds.length == 0) && 
			(selectedInstrumentId == null || selectedInstrumentId  == '' || selectedInstrumentId == EMPTY_SELECTION)) {
			selectedInstrumentFilter.text('Debe seleccionar al menos una materia o instrumento').css('color', '#f44336')
			return -1;
		}

		if (selectedTeacherIds.length > 0 && selectedTeacherIds[0] != EMPTY_SELECTION) {
			$.each(selectedTeacherIds, function(i, selectedTeacherId) {
				selectedTeachersFilter.text(allTeacherNames[selectedTeacherId].name)
			});
		} else {
			selectedTeachersFilter.text('Todos')
		}


		selectedDateTimes = getSelectedDateTimes(dateTimeBooleanMatrix);

		if (selectedDateTimes == null || selectedDateTimes.length == 0) {
			//$("#consolePrph").text('Debe seleccionar sus horarios disponibles para cursar');
			selectedDatesFilter.text('Debe seleccionar sus horarios disponibles para cursar').css('color', '#f44336')
			return -1;
		}

		if (selectedInstrumentAndSubjectsIds.length > MAX_SUBJECT_SIZE) {
			//$("#consolePrph").text('Debe seleccionar sus horarios disponibles para cursar');
			//console.log(selectedInstrumentAndSubjectsIds);
			selectedDatesFilter.text('Por favor, seleccione menor cantidad de materias (hasta '+MAX_SUBJECT_SIZE+', Instrumentos inclusive)').css('color', '#f44336')
			return -1;
		}		

		selectedDateTimes = collapseDateTimes(selectedDateTimes);

		//console.log('Despues de colapsar horarios: ');

		//for (var i = dateTimeBooleanMatrix.length - 1; i >= 0; i--) {;
		//	var selectedDateTime = dateTimeBooleanMatrix[i];
		//	if (selectedDateTime)
		//		console.log('Horario Seleccionado');
		//}		

		table = $('<table></table>').appendTo(selectedDatesFilter);
		for (var i = 0; i < selectedDateTimes.length; i++) {
			var selectedDateTime = selectedDateTimes[i];
			var row = $('<tr></tr>').appendTo(table)
			$('<td></td>').text(allDates[selectedDateTime.dayId].name).css('width', '10%').appendTo(row)
			$('<td></td>').text(' desde las ' + selectedDateTime.start + ':00hs hasta las ' + selectedDateTime.end + ':00hs').css('width', '30%').appendTo(row)
		}

		return 0;
	}



	function getSelectedDateTimes(selectedDateTimes) {

		var selectedDateTimes = [];
		var selectedDateTimesIndex = 0;
		for (var i = 0; i < dateTimeBooleanMatrix.length; i++) {
			var selectedDates = dateTimeBooleanMatrix[i];
			for (var j = 0; j < selectedDates.length; j++) {				
				if (selectedDates[j]) {
					selectedDateTimes[selectedDateTimesIndex++] = {dayId: allDates[j].id, start: allHours[i].start, end: allHours[i].end};
				}
			}
		}
		return selectedDateTimes;
	}


	function collapseDateTimes(selectedDateTimes) {

	
		selectedDateTimes.sort(function (a, b) {
			return a.dayId - b.dayId || a.start - b.start;
		});

		var collapsedDateTimes = []
		var collapsedDateTimesIndex = 0;
		for (var i = 0; i < selectedDateTimes.length; i++) {	
			var dateTimeA = selectedDateTimes[i];
			var endHour = dateTimeA.end;
			for (var k = i + 1; k < selectedDateTimes.length; k++) {
				//console.log('Comparando Indice ' + i + ' contra Indice ' + k);
				var dateTimeB = selectedDateTimes[k];
				if (dateTimeA.dayId != dateTimeB.dayId) {
					//console.log('Diferentes dias. Se agrega el dia ' + dateTimeA.dayId + ' hora ' + dateTimeA.start + ' ' + endHour);
					collapsedDateTimes[collapsedDateTimesIndex++] = {dayId: dateTimeA.dayId, start: dateTimeA.start, end: endHour};
					break;
				} else if (dateTimeA.dayId == dateTimeB.dayId && endHour != dateTimeB.start) {
					//console.log('Mismo dia, pero se corto la continuidad de hora. Se agrega el dia ' + dateTimeA.dayId + ' hora ' + dateTimeA.start + ' ' + endHour);
					collapsedDateTimes[collapsedDateTimesIndex++] = {dayId: dateTimeA.dayId, start: dateTimeA.start, end: endHour};
					break;
				} else if (dateTimeA.dayId == dateTimeB.dayId && endHour != dateTimeB.start) {
					//console.log('Mismo dia, pero se corto la continuidad de hora. Se agrega el dia ' + dateTimeA.dayId + ' hora ' + dateTimeA.start + ' ' + endHour);
					collapsedDateTimes[collapsedDateTimesIndex++] = {dayId: dateTimeA.dayId, start: dateTimeA.start, end: endHour};
					break;
				} else if (dateTimeA.dayId == dateTimeB.dayId && endHour == dateTimeB.start) {
					var endHour = dateTimeB.end;
					if (i < k) {
						//console.log('Subiendo el indice de ' + i + ' a ' + k);
						i = k;
					}					
					//console.log('Mismo dia, y hay continuidad. Se extiende a la hora ' + endHour);
				}
			}
			if (i == (selectedDateTimes.length - 1)) {
				//console.log('Se agrega el ultimo horario. Dia ' + dayId + ' hora ' + dateTimeA.start + ' ' + endHour);
				collapsedDateTimes[collapsedDateTimesIndex++] = {dayId: dateTimeA.dayId, start: dateTimeA.start, end: endHour};
			}
		}
		return collapsedDateTimes;
	}














/****************************************************************************************************
*
* PARTE 3:
*
* Calculo de Posibilidades. 
* Se reduce el universo de datos con los filtros, se quitan horarios con colision, se reducen las 
* opciones donde hay mas de un profesor en la misma hora.
*
*
*
*****************************************************************************************************/




	var MAX_COMBINATIONS = 100;

	var noDateTimeCollisionChoices;

	var subjectColors = [	
			{id: 0, subjectId: null, subjectName: null, color: '#56c7e9'},
			{id: 1, subjectId: null, subjectName: null, color: '#cbca94'},
			{id: 2, subjectId: null, subjectName: null, color: '#e0a955'},
			{id: 3, subjectId: null, subjectName: null, color: '#fbe68b'},
			{id: 4, subjectId: null, subjectName: null, color: '#e9003a'},
			{id: 5, subjectId: null, subjectName: null, color: '#ff5300'},
			{id: 6, subjectId: null, subjectName: null, color: '#b5c3e3'},
			{id: 7, subjectId: null, subjectName: null, color: '#577a55'},
			{id: 8, subjectId: null, subjectName: null, color: '#aabbed'},
			{id: 9, subjectId: null, subjectName: null, color: '#99cc99'},
			{id:10, subjectId: null, subjectName: null, color: '#008080'}
		];	

	function findChoices() {

		$.each(selectedLevelIds, function(i, selectedLevelId) {
			console.log(allLevels[selectedLevelId].name);
		});


		var filteredElements = applyInstrumentAndSubjectsFilter(selectedInstrumentAndSubjectsIds, allChoices);
		filteredElements = applySchedulesFilters(selectedDateTimes, filteredElements);
		if (filteredElements == null || filteredElements.length == 0) {
			//console.log('Cantidad de opciones resultantes luego de aplicar el filtro de horarios es 0');
			$("#consolePrph").text('No hay opciones disponibles con los horarios seleccionados. Por favor, amplie el rango de dias/horas');
			return 0;
		}		
		filteredElements = applyTeacherFilters(selectedTeacherIds, filteredElements);
		if (filteredElements == null || filteredElements.length == 0) {
			//console.log('Cantidad de opciones resultantes luego de aplicar los filtros de profesores es 0');
			$("#consolePrph").text('No hay opciones disponibles con los profesores seleccionados. Por favor, seleccione mas profesores o Todos');
			return 0;
		}

		updatedSelectedInstrumentAndSubjectIds = updateSelectedInstrumentAndSubjectIds(filteredElements);
		checkRemovedInstrumentAndSubjectIds(selectedInstrumentAndSubjectsIds, updatedSelectedInstrumentAndSubjectIds);
		selectedInstrumentAndSubjectsIds = updatedSelectedInstrumentAndSubjectIds;

		generateSubjectColors(selectedInstrumentAndSubjectsIds);


		var choicesGroupBySubjects = loadChoicesGroupBySubjects(selectedInstrumentAndSubjectsIds, filteredElements);
		var totalChoices = 0;
		console.log('');
		for (var s = 0; s < choicesGroupBySubjects.length; s++) {
			var choices = choicesGroupBySubjects[s];
			console.log('La materia [' + allSubjects[choices[0].subjectId].name + '] quedo con [' + choicesGroupBySubjects[s].length + '] opciones horarias');
		}


		console.log('');
		var cartesianProductChoices = cartesianProduct(choicesGroupBySubjects);
		if (cartesianProductChoices == null) {
			console.log('Cantidad de opciones resultantes del Producto Cartesiano es 0');
			return 0;
		}
		console.log('Cantidad de opciones resultantes del Producto Cartesiano: ' + cartesianProductChoices.length);
		noDateTimeCollisionChoices = removeChoicesWithCollisions(cartesianProductChoices);		
		console.log('Cantidad de opciones con las colisiones horarias filtradas: ' + noDateTimeCollisionChoices.length);
		
		var table = $('<table></table>');
		$('#combinationResults').empty().append(table);
		$('<tr></tr>').html('<b>' + noDateTimeCollisionChoices.length + '</b>').appendTo(table)
		if (noDateTimeCollisionChoices.length > MAX_COMBINATIONS) {
			$('<tr></tr>').text('Los filtros seleccionados generan demasiadas combinaciones. Por favor, reduzca el rango horario disponible o quite materias').css('color', '#f44336').appendTo(table)
		} else if (cartesianProductChoices.length > 0 && noDateTimeCollisionChoices.length == 0) {
			$('<tr></tr>').text('Los filtros seleccionados generan ' + cartesianProductChoices.length + ' opciones, pero los horarios entre las materias se superponen. Por favor, amplie el rango horario disponible o quite materias').css('color', '#f44336').appendTo(table)
		}


		console.log('');
		console.log('Listo.');
		return noDateTimeCollisionChoices.length;
	}


	function applyInstrumentAndSubjectsFilter(selectedInstrumentAndSubjectsIds, choices) {
		var filteredChoices = [];
		var filteredChoicesIndex = 0;

		for (var q = 0; q < selectedInstrumentAndSubjectsIds.length; q++) {
			var selectedInstrumentAndSubjectsId = selectedInstrumentAndSubjectsIds[q];
			for (var c = 0; c < choices.length; c++) {
				var choice = choices[c];
				if (choice.subjectId == selectedInstrumentAndSubjectsId) {
					//console.log('Se incluye la opcion : ' + choice.id + ' de la materia: ' + choice.subjectId)
					filteredChoices[filteredChoicesIndex++] = choice;
				}
			}	
		}

		if (filteredChoices == null || filteredChoices.length == 0) {
			console.log('Cantidad de opciones resultantes luego de aplicar el filtro de materias es 0');
			//$("#consolePrph").text('No hay opciones disponibles con las materias seleccionadas. Por favor, agregue materias');
		}

		return filteredChoices;
	}


	function applySchedulesFilters(selectedDateTimes, choices) {
		var filteredChoices = [];
		var filteredChoicesIndex = 0;


		for (var c = 0; c < choices.length; c++) {
			var choice = choices[c];
			if (isDatesInInterval(selectedDateTimes, choice.dates)) {
				//console.log('Se incluye la opcion : ' + choice.id + ' de la materia: ' + choice.subjectId + ' del dia: ' + allDates[choice.dates[0].dayId].name + " y horario: " + choice.dates[0].start + "-" + choice.dates[0].end + "hs");
				filteredChoices[filteredChoicesIndex++] = choice;
			} else {
				//console.log('Se excluye la opcion : ' + choice.id + ' de la materia: ' + choice.subjectId + ' del dia: ' + allDates[choice.dates[0].dayId].name + " y horario: " + choice.dates[0].start + "-" + choice.dates[0].end + "hs");
			}	
		}

		if (filteredChoices == null || filteredChoices.length == 0) {
			console.log('Cantidad de opciones resultantes luego de aplicar el filtro de horarios es 0');
			$("#consolePrph").text('No hay opciones disponibles con los horarios seleccionados. Por favor, amplie el rango de dias/horas');
		} else {
			console.log('Cantidad de opciones resultantes luego de aplicar el filtro de horarios es: ' + filteredChoices.length);
		}
		return filteredChoices;
	}		


	function applyTeacherFilters(selectedTeacherIds, choices) {
		if (selectedTeacherIds == null || selectedTeacherIds.length == 0 || selectedTeacherIds[0] == EMPTY_SELECTION) {
			console.log('No hay profesores seleccionados. Se tiene en cuenta toda la oferta de profesores.');
			return choices;
		}

		var filteredChoices = [];
		var filteredChoicesIndex = 0;
		for (var q = 0; q < selectedTeacherIds.length; q++) {
			var selectedTeacherId = selectedTeacherIds[q];
			for (var c = 0; c < choices.length; c++) {
				var choice = choices[c];
				if (choice.teacherId == selectedTeacherId) {
					//console.log('Se incluye la opcion: ' + choice.id + ' de la materia: ' + choice.subjectId + ' del profesor: ' + choice.teacherId)
					filteredChoices[filteredChoicesIndex++] = choice;
				} else {
					//console.log('Se excluye la opcion: ' + choice.id + ' de la materia: ' + choice.subjectId + ' del profesor: ' + choice.teacherId)
				}
			}
		}

		if (filteredChoices == null || filteredChoices.length == 0) {
			console.log('Cantidad de opciones resultantes luego de aplicar los filtros de profesores es 0');
			$("#consolePrph").text('No hay opciones disponibles con los profesores seleccionados. Por favor, seleccione mas profesores o Todos');
		} else {
			console.log('Cantidad de opciones resultantes luego de aplicar el filtro de profesores es: ' + filteredChoices.length);
		}
		return filteredChoices;
	}








	function isDatesInInterval(selectedDateTimes, dates) {
		var allDatesInInterval = [];
		var allDatesInIntervalIndex = 0;
		for (var b = 0; b < dates.length; b++) {
			var date = dates[b];
			allDatesInInterval[allDatesInIntervalIndex++] = {date: date, isDateInInterval: isDateInInterval(selectedDateTimes, date)};
		}

		for (var b = 0; b < allDatesInInterval.length; b++) {
			var result = allDatesInInterval[b];			
			if (!result.isDateInInterval) {
				//console.log('El horario de la materia esta por fuera del horario seleccionado. Dia ' + date.dayId + ' hora: ' + date.start + ' fin: ' + date.end);
				return false;
			}
		}		
		return true;
	}

	function isDateInInterval(selectedDateTimes, date) {
		for (var a = 0; a < selectedDateTimes.length; a++) {
			var selectedDateTime = selectedDateTimes[a];
			//console.log('selectedDateTime.dayId: ' + selectedDateTime.dayId + ' - date.dayId: '+ date.dayId + ' - selectedDateTime.start: '+ selectedDateTime.start + ' - date.start: '+ date.start);

			if (selectedDateTime.dayId == date.dayId && selectedDateTime.start <= date.start && selectedDateTime.end >= date.end) {
				return true;
			}
		}
		return false;
	}



	function updateSelectedInstrumentAndSubjectIds(filteredChoices) {
		var updatedSelectedInstrumentAndSubjectIds = [];
		var updatedSelectedInstrumentAndSubjectIdsIndex = 0;
		var firstTime = true;
		var oldSubjectId = null;
		for (var s = 0; s < filteredChoices.length; s++) {
			var filteredChoice = filteredChoices[s];
			if (firstTime || oldSubjectId != filteredChoice.subjectId) {
				firstTime = false;
				oldSubjectId = filteredChoice.subjectId;
				updatedSelectedInstrumentAndSubjectIds[updatedSelectedInstrumentAndSubjectIdsIndex++] = filteredChoice.subjectId;
			}
		}
		return updatedSelectedInstrumentAndSubjectIds;
	}
	
	
	function checkRemovedInstrumentAndSubjectIds(selectedInstrumentAndSubjectsIds, updatedSelectedInstrumentAndSubjectIds) {
		var removedInstrumentAndSubjectsIds = [];
		var removedInstrumentAndSubjectsIndex = 0;
		for (var s = 0; s < selectedInstrumentAndSubjectsIds.length; s++) {
			var selectedInstrumentAndSubjectsId = selectedInstrumentAndSubjectsIds[s];
			var found = findInstrumentAndSubjectIds(selectedInstrumentAndSubjectsId, updatedSelectedInstrumentAndSubjectIds);
			if (!found) {
				removedInstrumentAndSubjectsIds[removedInstrumentAndSubjectsIndex++] = selectedInstrumentAndSubjectsId;
			}
		}
		if (removedInstrumentAndSubjectsIds.length > 0) {		
			var message = 'Las materias ';
			for (var s = 0; s < removedInstrumentAndSubjectsIds.length; s++) {
				var removedInstrumentAndSubjectsId = removedInstrumentAndSubjectsIds[s];
				message += allSubjects[removedInstrumentAndSubjectsId].name + ', ';
			}
			message = message.substring(0, message.length - 2);
			message += ' han quedado afuera, luego de aplicar los filtros.';
			$("#consolePrph").text(message);
		}
	}

	function findInstrumentAndSubjectIds(selectedInstrumentAndSubjectsId, updatedSelectedInstrumentAndSubjectIds) {
		for (var u = 0; u < updatedSelectedInstrumentAndSubjectIds.length; u++) {
			var updatedSelectedInstrumentAndSubjectId = updatedSelectedInstrumentAndSubjectIds[u];
			if (updatedSelectedInstrumentAndSubjectId == selectedInstrumentAndSubjectsId) {
				return true;
			}
		}
		return false;
	}	

	function generateSubjectColors(selectedInstrumentAndSubjectsIds) {
		// se limpian asingaciones de Subject-Color previas
		for (var s = 0; s < subjectColors.length; s++) {
			subjectColors[s].subjectId = null;
			subjectColors[s].subjectName = null;
		}
		var subjectColorsIndex = 0;
		for (var s = 0; s < selectedInstrumentAndSubjectsIds.length; s++) {
			var selectedInstrumentAndSubjectsId = selectedInstrumentAndSubjectsIds[s];
			subjectColors[subjectColorsIndex] = {subjectId: selectedInstrumentAndSubjectsId, subjectName: allSubjects[selectedInstrumentAndSubjectsId].name, color: subjectColors[subjectColorsIndex].color};
			subjectColorsIndex++;
		}
	}






	function loadChoicesGroupBySubjects(selectedInstrumentAndSubjectsIds, choices) {
		var choicesGroupBySubjects = new Array;
		var choicesGroupBySubjectsIndex = 0;
/*
		choices.sort(function (a, b) {
			return a.subjectId - b.subjectId;			
		});

*/
		for (var s = 0; s < selectedInstrumentAndSubjectsIds.length; s++) {
			var selectedInstrumentAndSubjectsId = selectedInstrumentAndSubjectsIds[s];
			
			var filteredChoices = new Array;
			var filteredChoicesIndex = 0;

			for (var c = 0; c < choices.length; c++) {
				var choice = choices[c];
				if (choice.subjectId == selectedInstrumentAndSubjectsId) {
					filteredChoices[filteredChoicesIndex++] = choice;
				} else {

				}
			}

			if (filteredChoices.length > 0) {
				//console.log('Se agregan las [' + filteredChoices.length + '] opciones filtradas a la materia [' + allSubjects[selectedInstrumentAndSubjectsId].name + '].');
				choicesGroupBySubjects[choicesGroupBySubjectsIndex++] = shrinkSameDateTimesInSchedule(filteredChoices);
				//console.log('Luego de juntar los profesores de las mismas horas quedaron [' + choicesGroupBySubjects[choicesGroupBySubjectsIndex-1].length + '] opciones para la materia [' + allSubjects[selectedInstrumentAndSubjectsId].name + '].');
			} else {
				//console.log('Luego de aplicar los filtros  anteriores, la materia [' + subject.name + '] quedo sin opciones horarias.');
				$("#consolePrph").append('Luego de aplicar los filtros anteriores, la materia [' + allSubjects[selectedInstrumentAndSubjectsId].name + '] quedo sin opciones horarias.<br>');
			}
		}

		return choicesGroupBySubjects;
	}

	function shrinkSameDateTimesInSchedule(choices) {

		var filteredChoices = new Array;
		var filteredChoicesIndex = 0;

		for (var c = 0; c < choices.length; c++) {
			var choice = choices[c];
			var existentChoiceIndex = isDateExistsInChoiceArray(filteredChoices, choice.dates[0]);
			if (existentChoiceIndex == -1) {
				filteredChoices[filteredChoicesIndex++] = {id: choice.id, subjectId: choice.subjectId, dates : choice.dates, assignments: [{teacherId: choice.teacherId, commission: choice.commission, classroom: choice.classroom}]};
				//console.log('Se agrega la terna {profesor [' + choice.teacherId + ']-commission [' + choice.commission + ']-profesor [' + choice.classroom + ']}, a la opcion [' + choice.id + '] de la materia [' + choice.subjectId + '] con Horario NO EXISTENTE AUN: Dia [' + choice.dates[0].dayId + '] Horario [' + choice.dates[0].start + ':00]');
			} else {
				var existentChoice = filteredChoices[existentChoiceIndex];
				existentChoice.assignments[existentChoice.assignments.length] = {teacherId: choice.teacherId, commission: choice.commission, classroom: choice.classroom};
				filteredChoices[existentChoiceIndex] = existentChoice;
				//console.log('Se agrega la terna {profesor [' + choice.teacherId + ']-commission [' + choice.commission + ']-profesor [' + choice.classroom + ']}, a la opcion [' + choice.id + '] de la materia [' + choice.subjectId + '] con Horario YA REPETIDO: Dia [' + choice.dates[0].dayId + '] Horario [' + choice.dates[0].start + ':00]');
			}
		}

		return filteredChoices;
	}	

	function isDateExistsInChoiceArray(choices, date) {
		for (var b = 0; b < choices.length; b++) {
			var choice = choices[b];
			if (isSameDate(choice.dates[0], date)) {
				return b;
			}
		}
		return -1;
	}




	function cartesianProduct(arr) {
		var array = arr.reduce(function(a,b){
			return a.map(function(x){
				return b.map(function(y){
					return x.concat(y);
				})
			}).reduce(function(a,b){ return a.concat(b) },[])
		}, [[]])
		if (array.length == 0 || (array.length == 1 && array[0] == '')) {
			return null;
		}
		return array;
	}

	function removeChoicesWithCollisions(cartesianProductChoices) {
		var noDateTimeCollisionChoices = new Array;
		var noDateTimeCollisionChoicesIndex = 0;
		for (var i = 0; i < cartesianProductChoices.length; i++) {
			//console.log(cartesianProductChoices[i]);		
			if (!hasCollisions(cartesianProductChoices[i])) {
				noDateTimeCollisionChoices[noDateTimeCollisionChoicesIndex++] = cartesianProductChoices[i];
			}
		}
		return noDateTimeCollisionChoices;
	}

	function hasCollisions(choices) {
		for (var i = 0; i < choices.length; i++) {
			var choiceA = choices[i];
			for (var k = i + 1; k < choices.length; k++) {		
				var choiceB = choices[k];
				if (isSameDateTime(choiceA, choiceB)) {
					return true;
				}
			}
		}
		return false; // No hay colisiones.
	}
	
	function isSameDateTime(choiceA, choiceB) {
		for (var a = 0; a < choiceA.dates.length; a++) {
			var choiceADate = choiceA.dates[a];
			for (var b = 0; b < choiceB.dates.length; b++) {
				var choiceBDate = choiceB.dates[b];
				if (isSameDate(choiceADate, choiceBDate)) {
					return true;
				}
			}
		}
		return false;
	}
	
	function isSameDate(dateA, dateB) {
		return dateA.dayId == dateB.dayId && dateA.start == dateB.start;
	}






	$('#processBtn').click(function() {
		ga('send', 'event', 'Touch/Click en boton Procesar', 'click', 'Filters Form');
	
		//$('#consolePrph').text('Aguarde...');
		//setTimeout(findChoices(), 1000);
		//$('#consolePrph').text('');
		var returnCode = filtersPreview();
		if (returnCode == 0) { // No hay errores en los filtros
			var combinationsSize = findChoices();
			if (combinationsSize == 0) {
				//$("#consolePrph").append(' Seleccione mas horarios o quite materias. Con los filtros actuales no hay ninguna oferta horaria');
				ga('send', 'event', 'No hay ofertas', 'click', 'Preview Filters Form');
				$(document).scrollTop( $("#statusMessages").offset().top ); 
			} else if (combinationsSize > MAX_COMBINATIONS) {
				//$("#consolePrph").text('Los filtros seleccionados generan demasiadas combinaciones. Por favor, reduzca los horarios o quite materias');
				ga('send', 'event', 'Hay demasiadas ofertas', 'click', 'Preview Filters Form');
				$(document).scrollTop( $("#statusMessages").offset().top ); 
			} else {
				$('#showBtn').show();
				$('#showBtnNote').show();
				$('#filtersForm').hide();
				$('#processBtn').hide();
				$('#backBtn').show();
				$('#backBtn2').show();
			}
		}
	});


	$('#backBtn').click(function() {
		ga('send', 'event', 'Touch/Click en boton Volver', 'click', 'Preview Filters Form');
		backBtnHandler();
	});

	function backBtnHandler() {
		$('#filtersForm').show();
		$('#processBtn').show();
		$('#showBtn').hide();
		$('#showBtnNote').hide();		
		$('#backBtn').hide();
		$('#backBtn2').hide();
		$('#levelSelected').empty();
		$('#subjectsSelected').empty();
		$('#instrumentSelected').empty();
		$('#datesSelected').empty();
		$('#teacherSelected').empty();	
		$('#referencesTable').empty();
		$('#choicesTable').empty();
		$('#combinationResults').empty();
		$("#consolePrph").empty();
		window.scrollTo(0, 0);
		$('html, body').animate({scrollTop:0}, 'slow');
	}















/****************************************************************************************************
*
* PARTE 4:
*
* Despliegue y muestra de resultados en Tablas.
*
*
*
*****************************************************************************************************/





var clicked = false;
	$('#showBtn').click(function() {
		ga('send', 'event', 'Touch/Click en boton Mostrar Opciones', 'click', 'Preview Filters Form');
		if (!clicked) {
			clicked = true;
			$.when( showWaitMessage() ).done(function() {
				$('#waitMessage').show();
				$('#referencesTable').empty();
				$('#choicesTable').empty();			
				if (noDateTimeCollisionChoices.length > 0) {
					showReferencesTable();
					$.each(noDateTimeCollisionChoices, function(index, noDateTimeCollisionChoicesFromSubject) {
						showFilteredChoices(index, noDateTimeCollisionChoicesFromSubject);
					});
				}
				$('#processBtn').hide();
				//$('#showBtn').hide();
				$('#showBtnNote').hide();
				$('#filtersForm').hide();
				$('#backBtn').show();
				$('#backBtn2').show();
				//$("#consolePrph").empty();
				$('#waitMessage').hide();
				clicked = false;
			});
		} else {
			$("#consolePrph").append('Se esta procesando. Por favor, aguarde...');
		}
	});

	function showReferencesTable(tableId, choices) {
		var table = $('#referencesTable');
		var thead = $('<thead></thead>').appendTo(table)
		var row = $('<tr></tr>').appendTo(thead)
		$('<th></th>').text('Referencias').appendTo(row);
		var tbody = $('<tbody></tbody>').appendTo(table)
		$.each(subjectColors, function(index, subjectColor) {
			if (subjectColor.subjectName != null) {
				row = $('<tr></tr>').appendTo(tbody)
				$('<td></td>').css('background-color', subjectColor.color).text(subjectColor.subjectName).appendTo(row);
			}
		});
		row = $('<tr></tr>').appendTo(tbody)
		$('<td></td>').text('Coloque el mouse/dedo arriba de la Comision para conocer el o los profesores de cada horario').appendTo(row);
	}


	function showFilteredChoices(tableId, choices) {
		//console.log('============================================');
		var table = $('<table id="table' + tableId + '"></table>').attr('class', 'table table-striped table-sm').css('text-align', 'center').appendTo($('#choicesTable'));
		var thead = $('<thead></thead>').appendTo(table)
		var row = $('<tr></tr>').appendTo(thead)
		$('<th></th>').text('Hs').appendTo(row);
		$.each(allDates, function(index, date) {
			$('<th></th>').text(date.initial).appendTo(row);
		});

		var tbody = $('<tbody></tbody>').appendTo(table)
		$.each(allHours, function(index, hour) {
			var row = $('<tr></tr>').appendTo(tbody)
			$('<th id="hour' + hour.start + '"></th>').text(hour.start).appendTo(row);
			$.each(allDates, function(index, date) {
				$('<td id="weekDay' + date.id + hour.start + '"></td>').text('').appendTo(row);
			});
		});

		for (var m = 0; m < choices.length; m++) {
			var choice = choices[m];
			var previewTag = '';
			var bodyTag = '' ;
			if (choice.assignments.length == 1) {
				var assignment = choice.assignments[0];
				previewTag = assignment.commission;
				if (previewTag == '') {
					previewTag = '-'
				}
				bodyTag = allTeacherNames[assignment.teacherId].name + ' (Aula: ' + assignment.classroom + ')';
			} else {
				previewTag = '+';
				for (var l = 0; l < choice.assignments.length; l++) {
					var assignment = choice.assignments[l];
					var commissionString = (assignment.commission == '') ? ', Sin comisión' : ', Comisión: ' + assignment.commission;
					bodyTag += allTeacherNames[assignment.teacherId].name + ' (Aula: ' + assignment.classroom + commissionString + ') o ';
				}
				bodyTag = bodyTag.substring(0, bodyTag.length - 2);
			}

			for (var l = 0; l < choice.dates.length; l++) {
				var choiceDate = choice.dates[l];
				$('#table' + tableId + ' #weekDay' + choiceDate.dayId + choiceDate.start).html('<p>' + previewTag + '</p><div class="content">' + allSubjects[choice.subjectId].name + ':<br>' + bodyTag + '</div>').css('background-color', getSubjectColor(choice.subjectId));
				//console.log('Horarios: ' + dates[choiceDate.dayId].name + ' ' + choiceDate.start + 'hs');
			}
		}

		var row = $('<tr></tr>').appendTo(tbody);
		$('<td colspan="' + (allDates.length + 1) + '"></td>').appendTo(row);
		
	}

		
	function showWaitMessage() {
		$('#waitMessage').show();
		$('#showBtn').hide();
	}



	function getSubjectColor(subjectId) {
		for (var s = 0; s < subjectColors.length; s++) {
			var subjectColor = subjectColors[s];
			if (subjectColor.subjectId == subjectId) {
				return subjectColor.color;
			}
		}
		return '';
	}




	$('#backBtn2').click(function() {
		ga('send', 'event', 'Touch/Click en boton Volver', 'click', 'Result Grids Form');
		backBtnHandler();
	});





	
});
