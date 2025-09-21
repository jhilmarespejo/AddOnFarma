let valorSeleccionado; 
let respuesta;
let planes_cargados = 'NO';

function init(){

	$('#vamos_a_cobrar').hide();
	limpiar();
	//++
	$("#formulario").on("submit", function (e) {
		guardaryeditar(e);
	});

	let datos_asesor = $('#datos_asesor').val();
	if(datos_asesor == 'NO'){
		$('.datos_asesor').hide();
	}
	console.log('Datos Asesor: ' + datos_asesor);

    	$('#id_genera_factura').hide();
    	//$('#btnCancelar').attr('disabled',true);

	$('#datos_titular').hide();
	$('#formularioregistros').show();
	$('#registra_beneficiario').hide();

	$('#idmensaje').hide();
	$('#idmensaje_final').hide();


	//listarPlanes();
	$('#genero').change(function() {

		listarPlanes();

	});

	$('#planes').change(function() {
		// Obtener el valor de la opción seleccionada
		valorSeleccionado = $(this).val();

		// Hacer algo con el valor seleccionado
		console.log("Plan Elegido: " + valorSeleccionado);

		let cedula = $('#num_documento').val();
		let codigo_canal = $('#codigo_canal').val();

		if(codigo_canal === 'C991'){

			$.post("../ajax/varios.php?op=buscaClienteAntiguo", 
				{cedula : cedula, valorSeleccionado:valorSeleccionado}, 
				function(data){
					data = JSON.parse(data);
					console.log(data);
					if(data.status == 'ok'){
						console.log(data);

						codigo = data.codigo_plan_renovacion;
						nombre = data.nombre_plan_renovacion;

						console.log('CODIGO:' + codigo);
						console.log('NOMBRE: ' + nombre);
						$('#codigo_renovacion').val(codigo);

						mensaje = 'El cliente tiene descuento para este plan \nRecibe plan: ' + nombre;
						//bootbox.alert('El cliente tiene descuento para este plan \n Recibe plan: ' + nombre);
						alert(mensaje);
					}

			});
		}

	  });

	$(document).ready(function () {
		$("#planesXX").change(function () {
			$("#planes option:selected").each(function () {

				plan_elegido = $(this).val();
				console.log("PLAN ELEGIDO:" + plan_elegido);

		  	});
		});
	});

}



function handleChange(checkbox){

	if(checkbox.checked === true){
		console.log('Mostrando Titular . . . . . .');
		igual = 'NO';
        $('#despliega_titular').show();
    }else{
		igual = 'SI';
		console.log('Ocultando Titular');
        $('#despliega_titular').hide();
   }
   $('#tit_idem_con').val(igual);

}


function listarPlanes(){

	$("#genero option:selected").each(function () {

		genero_paciente = $(this).val();
		console.log("GENERO CLIENTE:" + genero_paciente);
		
		$.post("../ajax/varios.php?op=listarPlanesCanal", 
		{genero_paciente:genero_paciente},
		function (r) {
			//console.log(r);
			$("#planes").html(r);
			///JJJ 
				$("#planes").prop("selectedIndex", 1);
				$('#cedula_asesor').val('111333');
			///JJJ

			$("#planes").selectpicker("refresh");
		});
	});


}

// Buscamos al PACIENTE x medio de su Cédula
function buscaPaciente(){

	let cedula;
	cedula = $('#num_documento').val();
	tipo_documento = $('#tipo_documento').val();

	console.log('CEDULA:'+cedula);
	console.log('TIP DOC:'+tipo_documento);


	//Buscamos al Cliente
	$.ajax({
		type:'POST',
		url:'../ajax/buscarCliente.php',
		dataType: "json",
		async: false,
		data:{cedula: cedula, tipo_documento: tipo_documento},
		success:function(data){

			console.log(data);
			if(data.status == 'ok'){

				ap_pat = data.result.ap_paterno;
				ap_mat = data.result.ap_materno;
				f_name = data.result.nombres;
				c_exte = data.result.extension;
				s_phon = data.result.telefono;
				f_gene = data.result.genero;
				f_fecn = data.result.fecha_nacimiento;
				//f_plan = data.result.plan;
				tipdoc = data.result.tipo_documento;
				donde  = data.donde;

				nu_ced = data.result.num_documento;
				tipdoc = tipdoc!==null ? data.result.tipo_documento : '';
				c_exte = c_exte!==null ? data.result.extension : " ";
				ap_pat = ap_pat!==null ? data.result.ap_paterno : " ";
				ap_mat = ap_mat!==null ? data.result.ap_materno : " ";
				f_name = f_name!==null ? data.result.nombres : " ";
				f_gene = f_gene!==null ? data.result.genero : " ";
				s_phon = s_phon!==null ? data.result.telefono : " ";
				f_fecn = f_fecn!==null ? data.result.fecha_nacimiento : " ";
				f_email = f_email!==null ? data.result.email : " ";
				donde  = donde!==null ? data.donde : " ";
				//f_plan = f_plan!==null ? data.result.planes : " ";
				if(data.donde === 'I'){
					encontrado = 'SI';
				}else{
					encontrado = 'NO';
				}
				mensaje = 'Cliente encontrado. Completando sus datos!!';

				limpiar();
				$('#donde').val(donde);
				$('#num_documento').val(nu_ced);
				$('#extension').val(c_exte);
				$('#ap_paterno').val(ap_pat);
				$('#ap_materno').val(ap_mat);
				$('#nombres').val(f_name);
				$('#genero').val(f_gene);
				$("#genero").selectpicker("refresh");
				$('#telefono').val(s_phon);
				$('#fecha_nacimiento').val(f_fecn);
				$('#encontrado').val(encontrado);
				$('#id_cliente').val(data.result.id);
				$('#tipo_documento').val(tipdoc);
				$('#email').val(f_email);
				$('#tipo_documento').selectpicker("refresh");
				$('#datos_titular').show();


			}else{
				encontrado = 'NO';
				mensaje = 'Cliente no encontrado. Favor ingrese sus datos!';
				console.log(data);
				$('#encontrado').val(encontrado);

				$("#idmensaje").css("border-style", "solid");
				$("#idmensaje").css("border-width", "1px");
				$("#idmensaje").css("color", "red");
				$("#idmensaje").css("border-color", "black");
				$("#idmensaje").css("padding", "3px");
				$("#idmensaje").text('Cliente no encontrado. Favor ingrese sus datos!');

				text = 'Esta seguro del número de esta cédula?\nCEDULA: ' + cedula;
				if (confirm(text) === true) {
					text = "You pressed OK!";
				} else {
					$(location).attr("href", "admision.php");
				}

				$('#idmensaje').show();
				$('#datos_titular').show();

			}

			listarPlanes();

		}

	});


}

function limpiar(){

	$('#ap_paterno').val('');
	$('#ap_materno').val('');
	$('#nombres').val('');
	$('#fecha_nacimiento').val('');
	$('#genero').val('');
	$('#genero').selectpicker("refresh");
	$('#telefono').val('');
	$('#planes').val('');
	$('#planes').selectpicker("refresh");
	$('#cedula_asesor').val('');


}



function guarda_info(){

	var formData = new FormData($("#formulario")[0]);

/*
	let a1 = $('#num_documento').val();
	let a2 = $('#ap_paterno').val();
	let a3 = $('#ap_materno').val();
	let a4 = $('#nombres').val();
	let a5 = $('#genero').val();
	let a6 = $('#telefono').val();
	let a7 = $('#fecha_nacimiento').val();
	let a8 = $('#encontrado').val();
	let a9 = $('#id_cliente').val();
	let a10 = $('#tipo_documento').val();
	let a11 = $('#planes').val();
	

	console.log('AP PAT: ' + a2);
	console.log('AP MAT: ' + a3);
	console.log('NOMBRE: ' + a4);
	console.log('TP DOC: ' + a10);
	console.log('CEDULA: ' + a1);
	console.log('GENERO: ' + a5);
	console.log('FC NAC: ' + a7);
	console.log('FOUND : ' + a8);
	console.log('ID CLI: ' + a9);
	console.log('PHONE : ' + a6);
	console.log('PLAN  : ' + a11);
*/


	$.ajax({
		url: "../ajax/cliente.php?op=guardarContratante",
		type: "POST",
		data: formData,
		contentType: false,
		processData: false,


		success: function (datos) {

			console.log('Volvi de guarda Contratante');
			data = JSON.parse(datos)
			console.log(data);

			if(data.status == 'ok'){
				$('#idmensaje_final').show();
				$("#mensaje_final").css("color", "green");
				$("#mensaje_final").css("border-color", "black");
				$("#mensaje_final").css("padding", "3px");
				$("#mensaje_final").text('Cliente registrado de manera satisfactoria!');
				$("#mensaje_final").css("font-weight", "bold");

				$('#nombre_pac').val(data.nombre);
				$('#monto_a_pagar1').val(data.deuda);
				$('#monto_a_pagar2').val(data.deuda);
				$('#id_pagado').css("color", "green");
				$('#id_total_a_pagar').css("color", "red");
				$('#registro_a_facturar').val(data.id_temp);
				
				/// JJJ
				$('#vamos_a_cobrar').show();
				// $('#formularioregistros').hide();
				// limpiar();
				// setTimeout(()=> {
				// 	$('#vamos_a_cobrar').show();
				// }
				// ,100);


			}else{
				console.log('BAAAD!');
				alert('Ocurrió un error al guardar el beneficiario');
				$(location).attr("href", "escritorio.php");
			}

		},
	});

}

//++
function guardaryeditar(e) {
  e.preventDefault(); //No se activará la acción predeterminada del evento

	var debug;
	debug = 0;

	let planes = $('#planes').val();
	let telefono = $('#telefono').val();
    	let len_tel = telefono.length;
	let generoCliente = $('#genero').val();

	console.log('PLANES: '+planes);


	if(planes === '0'){
		alert('Debe elegir un plan!!');
	}else{
		console.log(telefono);
	    if(len_tel < 7 || len_tel > 8){
	        alert('Cantidad de digitos del teléfono debe contener 7 u 8 digítos');
	    }else{

    		//this.disabled = true;
    		$('#btnGuardar').prop('disabled', true);

			console.log("Pidiendo Genero del Plan");
			console.log('Plan Elegido GYE: ' + valorSeleccionado);

			$.post(
				'../ajax/varios.php?op=obtieneGeneroDelPlan',
				{valorSeleccionado:valorSeleccionado},
				function (generoPlan) {
				  	console.log("GENERO PLAN:" + generoPlan);
					console.log("GENERO CLIENTE: " + generoCliente);
				  	console.log("Procediendo a GUARDAR la INFORMACION");

					let codigo_canal = $('#codigo_canal').val();
					console.log('CODIGO DE CANAL xx: ' + codigo_canal);


					if(codigo_canal === 'C015'){ // PROMUJER

						if((generoPlan === generoCliente) || (generoPlan === 'X')){

							console.log("TRATANDO DE GUARDAR LA INFO");

							guarda_info();

							//alert("Genero Correcto");
							$('#respuesta_c').val("Genero Correcto");


						}else{

							let text = "El género del cliente es diferente al tipo de Plan\nPresione OK para grabar o Cancel para modificar.";
							if (confirm(text) == true) {
								text = "You pressed OK!";
								guarda_info();
							} else {
								text = "You canceled!";
								$('#btnGuardar').prop('disabled', false);
							}
							//alert("Cruce de Generos");
							console.log("Cruce de Generos");
							console.log(text);
							//$('#respuesta_c').val("Cruce de Generos");

						}

					}else{   // CIDRE y OTROS

						guarda_info();
						$('#vamos_a_cobrar').hide();

						$('#idmensaje_final').show();
						$("#mensaje_final").css("color", "green");
						$("#mensaje_final").css("border-color", "black");
						$("#mensaje_final").css("padding", "3px");
						$("#mensaje_final").text('Cliente registrado de manera satisfactoria!');
						$("#mensaje_final").css("font-weight", "bold");

						// setTimeout(()=> {
						// 	$(location).attr("href", "admision_cobranzas.php");
						// }
						// ,10);
                        // limpiar();

					}

				}

			);

	    }

	}

}
//+P1
function generarFactura(){
	let registro_a_facturar = $('#registro_a_facturar').val();
    
    document.body.style.cursor = 'wait';

    // Mostrar mensaje de carga
    $('#loadingMensaje').show();
    $('#btnImprimir').hide();
    $('#btnVolverInicio').hide();
	
	// JJJ cambiar de false a true
    $('#btnImprimir').attr('disabled', false);

	// JJJ cambiar de false a true
    $('#btnCancelar').attr('disabled', false);
	
	$('#btnContrato').show().attr('disabled', false); // muestra el botón de contrato


	// Simulación directa sin AJAX
    let planElegido = $('#planes option:selected').text() || 'Plan no seleccionado';
    
    $('#btnImprimir').show().attr('disabled', false);
    $('#btnVolverInicio').show();
    $('#btnCancelar').attr('disabled', true);

    $('#loadingMensaje').hide();

    $('#idmensaje_final').html(
        `<div class="alert alert-success" style="font-size:18px;">
            ¡Cobranza registrada correctamente!<br>
            <strong>Plan elegido:</strong> ${planElegido}
        </div>`
    ).show();


    //+P2
	/* ///JJJ descomentar este bloque para usar AJAX
    $.ajax({
        url: "../ajax/cobranzas.php?op=generarFactura",
        type: "POST",
        dataType: "json",
        async: true,
        data: { registro_a_facturar: registro_a_facturar },

        success: function (datos) {
            if (datos.status_fact === 'ok') {
                $('#btnImprimir')
                    .attr('href', datos.factura_url)
                    .attr('target', '_blank')
                    .show()
                    .attr('disabled', false);

				// Mostrar botón de contrato
				$('#btnContrato').show().attr('disabled', false);
                

                $('#btnVolverInicio').show();
                $('#btnCancelar').attr('disabled', true);

                limpiar();
            } else {
                alert("Error generando factura: " + datos.msg1);
            }
        },

        error: function () {
            alert('Error al comunicarse con el servidor.');
        },

        complete: function () {
            document.body.style.cursor = 'default';

            // Ocultar mensaje de carga al terminar
            $('#loadingMensaje').hide();
        }
    });*/
}

function verContrato() {
    // let registro_a_facturar = $('#registro_a_imprimir').val().trim();
	let registro_a_facturar = $('#registro_a_facturar').val();
	// console.log('Registro a facturar: ' + registro_a_facturar);
	// if (!registro_a_facturar) {
	// 	alert('No se ha definido el registro a facturar');
	// 	return;
	// } else {
	// 	console.log('Registro a facturar: ' + registro_a_facturar);
	// 	return;
	// }
		
    // Mostrar el contenedor del PDF
    $('#contenedor-pdf').show();
		$('html, body').animate({
			scrollTop: $('#contenedor-pdf').offset().top
		}, 500); // 500 ms de duración
    // Cargar el PDF en el iframe
    $('#visor-pdf').attr('src', '../ajax/obtener_contrato.php?id=' + registro_a_facturar);
}
function cerrarContrato() {
    // Ocultar el contenedor del PDF
    $('#contenedor-pdf').hide();
    
    // Limpiar el iframe
    $('#visor-pdf').attr('src', '');
}


init();
