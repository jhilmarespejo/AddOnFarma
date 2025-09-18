<?php
ob_start();
if (strlen(session_id()) < 1){
	session_start();//Validamos si existe o no la sesión
}

require_once "../modelos/Cobranzas.php";
require_once "../modelos/Varios.php";


$cobranzas = new Cobranzas();
$varios    = new Varios();

$id_usuario = $_SESSION['idusuario'];


$codigo_agencia = isset($_POST['codigo_agencia'])?limpiarCadena($_POST["codigo_agencia"]):"";
$cedula = isset($_POST["cedula"])? limpiarCadena($_POST["cedula"]):"";
$precio = isset($_POST["precio"])? limpiarCadena($_POST["precio"]):"";
$registro_a_facturar = isset($_POST["registro_a_facturar"])? limpiarCadena($_POST["registro_a_facturar"]):"";

$id_registro = isset($_POST["id"])? limpiarCadena($_POST["id"]):"";



switch ($_GET["op"]){


	case 'buscaClienteConDeuda':

                //$cedula = '821048';
                //$codigo_agencia = '302';

                $rspta=$cobranzas->buscaCobranzaxCedula($cedula,$codigo_agencia);

                //dep($rspta);

                $data= Array();

                $ndx = 1;
                while ($reg=$rspta->fetch_object()){
                        //$estado = '<input type="checkbox" name="cobra'.$reg->id. '" id="cobra'. $reg->id . ' onclick="registroSeleccionado('. $$
                        $estado = '<button class="btn btn-success" onclick="registroSeleccionado('.$reg->id.',\''.$reg->cod_plan.'\')"><i class="$
                        $data[]=array(
                                "0"=>$reg->id,
                                "1"=>$reg->nombreCli,
                                "2"=>$reg->fecha_creacion,
                                "3"=>$reg->plan,
                                "4"=>$reg->deuda,
                                "5"=>$estado
                                /* "9"=>($reg->estado=='V')?'<span class="label bg-green">Vendido</span>':
                                '<span class="label bg-red">Anulado</span>' */
                                );
                }
                $results = array(
                        "sEcho"=>1, //Información para el datatables
                        "iTotalRecords"=>count($data), //enviamos el total registros al datatable
                        "iTotalDisplayRecords"=>count($data), //enviamos el total registros a visualizar
                        "aaData"=>$data);
                echo json_encode($results);

        break;






	case 'buscaCobranzaxCedula':

                //$cedula = $_REQUEST['cedula'];
                //$codigo_agencia = $_REQUEST['codigo_agencia'];
                //$codigo_agencia = '302';
                //$cedula = '821048';
                $rspta=$cobranzas->buscaCobranzaxCedula($cedula,$codigo_agencia);


                $data = array();

                if($rspta){
                        $data['status'] = 'ok';
                        $data['data'] = $reg=$rspta->fetch_object();
                }else{
                        $data['status'] = 'error';
                        $data['data'] = 'No se encontró paciente';
                }


		while ($reg=$rspta->fetch_object()){
                        $codigo_plan_padre = $reg->codigo_plan;
                        $codigo_plan_hijo = $reg->plan;

                        if($codigo_plan_padre == 'PC02'){
                                $deuda = 500;
                        }else{
                                $deuda = $reg->deuda;
                        }

                        if(($codigo_plan_padre == 'PC01' && $codigo_plan_hijo == 'PPCE0062') ||
                        ($codigo_plan_padre == 'PC02' && $codigo_plan_hijo == 'PPCE0063')){
                                $data[]=array(
                                        "0"=>$reg->id,
                                         "1"=>$reg->nombre,
                                        "2"=>$reg->fecha_creacion,
                                        "3"=>$reg->plan,
                                        "4"=>$reg->canal,
                                        "5"=>$deuda,
                                        "6"=>'<input type="checkbox" id='.$reg->id.' onchange="muestraDatos()">'
                                         //"7"=>($reg->estado)?'<span class="label bg-green">Por Facturar</span>':
                                         //'<span class="label bg-red">Desactivado</span>'
                                 );

                        }

                }
                $results = array(
                        "sEcho"=>1, //Información para el datatables
                        "iTotalRecords"=>count($data), //enviamos el total registros al datatable
                        "iTotalDisplayRecords"=>count($data), //enviamos el total registros a visualizar
                        "aaData"=>$data);
                echo json_encode($results);
                 //dep($data);
        break;


	case 'buscarDatosCobranzas':

                //$cedula = '821048';

                $data = array();

                $rspta = $cobranzas->buscarDatosCobranzas($cedula,$codigo_agencia,$id_registro);
                $reg=$rspta->fetch_object();

		 if($reg){

                        $codigo_plan = $reg->codigo_plan;
                        if($codigo_plan == 'PC02'){
                                $deuda = 500;
                        }else if($codigo_plan == 'PC03' || $codigo_plan == 'PC04'){
                                $deuda = 620;
                        }else if($codigo_plan == 'PC05'){
                                $deuda = 1100;
                        }else{
                                $deuda = $reg->deuda;
                        }

                        $data['status'] = 'ok';
                        $data['nombre'] = $reg->nombre;
                        $data['deuda'] = $deuda;

                }else{

                        $data['status'] = 'error';
                        $data['nombre'] = "";
                        $data['deuda'] = "";
                }

                echo json_encode($data);

        break;


	case 'generarFactura':

		
		$codigo_agencia = 'B181';
		$id_usuario = '8';
		$precio = '50';
		$registro_a_facturar = '4';
		

		// Generamos los códigos de operación y transacción
		$cod_ope = $varios->getParameterValues('cod_ope');
		$cod_tra = $varios->getParameterValues('cod_tra');

		echo "COD OPE:" . $cod_ope . "<br>";
		echo "COD TRA:" . $cod_tra . "<br>";
		die();
/*
		// Generamos el número de contrato del paciente
		$contrato =  $varios->getNumeroContrato($registro_a_facturar);

		echo "CONTRATO1: " . $contrato . "<br>";

		$data = array();
		$data['cod_tra'] = $cod_tra;
		$data['contrato'] = $contrato;

		dep($data);
		die();


		$ret_val = $cobranzas->generarFactura_c($cod_ope, $cod_tra,$id_usuario,$registro_a_facturar,$contrato);
		//dep($ret_val);
		//die();


		if($ret_val['status'] == 'ok'){

			$id_registro_a_facturar = $ret_val['data']['id'];
			$_SESSION['id_registro_a_facturar'] = $registro_a_facturar;

			//$data = array();

			$data['status']  = $ret_val['status'];
			$data['status_fact']  = $ret_val['status'];
			$data['msg'] = $ret_val['msg'];

		}else{
			$data['status']  = 'error';
			$data['status_fact']  = $ret_val['status'];
			$data['msg'] = $ret_val['msg'];
		}
		//dep($data);
		//die();

		// ------------------------------------- //
		// AHORA VAMOS A ENVIAR AL SAP LOS DATOS //
		// ------------------------------------- //
		$fecha_inicio = date('Ymd');

		//echo "ID REGISTRO: " . $registro_a_facturar . "<br>";
		$rspta = $varios->getRegistroParaFacturar($registro_a_facturar);
		$row = mysqli_fetch_assoc($rspta);

		//dep($row);
		//die();

		$codigo_plan_padre = $row['codigo_plan'];
		$filas = $varios->readPlanPadre($codigo_plan_padre);
		//echo "PLAN PADRE: " . $codigo_plan_padre . "\n";
		$rs_plan = mysqli_fetch_assoc($filas);

		//dep($rs_plan);
		//die();


		$factura = array();

		$factura['cod_cli'] = $row['cod_cli'];
		$factura['cod_ope'] = $cod_ope;
		$factura['cod_tra'] = $cod_tra;
		$factura['tipo_documento'] = $row['tipo_documento'];
		$factura['cedula'] = $row['cedula'];
		$factura['extension'] = isset($row['extension']) ? $row['extension'] : "";
		$factura['expedido'] = isset($row['expedido']) ? $row['expedido'] : '';
		$factura['ap_paterno'] = $row['ap_paterno'];
		$factura['ap_materno'] = $row['ap_materno'];
		$factura['nombres'] = $row['nombres'];
		$factura['razon_social'] = $row['nombres']. ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'];
		$factura['genero'] = $row['genero'];
		$factura['telefono'] = $row['telefono'];
		$factura['email'] = "";
		$factura['direccion'] = 'N-A';
		$factura['fecha_nacimiento'] = $row['fecha_nacimiento'];
		$factura['codigo_plan'] = $rs_plan['codigo_plan_hijo'];
		$factura['precio'] = $row['deuda'];
		$factura['canal'] = $row['codigo_canal'];
		$factura['codigo_agencia'] = $row['codigo_agencia'];
		$factura['precio'] = $rs_plan['precio_hijo']; 
		$factura['fecha_inicio'] = $fecha_inicio; 
		$factura['contrato'] = $contrato;

		//dep($factura);
		//die();


		$horaLlamada = date('H:i:s');
		$rc = $varios->llamamosWS_Sartawi($factura); 
		$horaRetorno = date('H:i:s');

		//dep($rc);

		// Recogemos el mensaje de retorno del WS Sartawi
		$factura['status_fact']  = $rc['status_fact'];
		$factura['mensaje_fact'] = $rc['mensaje_fact'];
		$factura['factura']      = $rc['factura'];
		$factura['hora_llamada'] = $horaLlamada;
		$factura['hora_retorno'] = $horaRetorno;

		if($rc['status_fact'] == 'E'){
			$mi_factura = $rc['factura'];
			// Actualizamos los campos FACTURACION, FECHA_FACTURACION y ESTADO de la table temp
			$rspta = $cobranzas->actualizamosCamposFacturacion($registro_a_facturar,$mi_factura);
		}

		$rspta = $varios->registraDatosLog($factura);

		$data['status_fact1']  = $rc['status_fact'];
		$data['msg1'] = $rc['mensaje_fact'];
		$data['id_reg'] = $registro_a_facturar;
		$data['factura_url'] = $mi_factura;

		//dep($factura);
*/
		echo json_encode($data);
		//dep($data);

	break;


}

function dep($data){
	$format = print_r('<pre>');
	$format .= print_r($data);
	$format .= print_r('</pre>');
	return $format;
}

ob_end_flush();
?>
