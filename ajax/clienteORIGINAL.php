<?php
ob_start();
if (strlen(session_id()) < 1){
	session_start();//Validamos si existe o no la sesión
}
require_once "../modelos/Cliente.php";
require_once "../modelos/Varios.php";
//require_once "buscarCliente2ND.php";

$cliente=new Cliente();
$varios= new Varios();


$id_usuario = $_SESSION['idusuario'];

//dep($_POST);
//dep($_SESSION);

//$encontrado = isset($_GET["encontrado"])? limpiarCadena($_GET["encontrado"]):"";

$encontrado = isset($_POST["encontrado"])? limpiarCadena($_POST["encontrado"]):"";
$planes = isset($_POST["planes"])? limpiarCadena($_POST["planes"]):"";
$id_cliente = isset($_POST["id_cliente"])? limpiarCadena($_POST["id_cliente"]):"";
$tipo_documento = isset($_POST["tipo_documento"])? limpiarCadena($_POST["tipo_documento"]):"";
$num_documento = isset($_POST["num_documento"])? limpiarCadena($_POST["num_documento"]):"";
$extension = isset($_POST["extension"])? limpiarCadena($_POST["extension"]):"";
$ap_paterno = isset($_POST["ap_paterno"])? limpiarCadena($_POST["ap_paterno"]):"";
$ap_materno = isset($_POST["ap_materno"])? limpiarCadena($_POST["ap_materno"]):"";
$nombres = isset($_POST["nombres"])? limpiarCadena($_POST["nombres"]):"";
$genero = isset($_POST["genero"])? limpiarCadena($_POST["genero"]):"";
$fecha_nacimiento = isset($_POST["fecha_nacimiento"])? limpiarCadena($_POST["fecha_nacimiento"]):"";
$num_telefono = isset($_POST["telefono"])? limpiarCadena($_POST["telefono"]):"";
$cedula_asesor = isset($_POST["cedula_asesor"])? limpiarCadena($_POST["cedula_asesor"]):"";
$codigo_renovacion = isset($_POST["codigo_renovacion"])? limpiarCadena($_POST["codigo_renovacion"]):"";
$donde = isset($_POST["donde"])? limpiarCadena($_POST["donde"]):"";


$encontrado = 'NO';
$planes = 'PC0009';
$id_cliente = '362';
$tipo_documento = 'C';
$num_documento = '12421039';
$extension = "";
$ap_paterno = 'CAMACHO';
$ap_materno = 'SANDOVAL';
$nombres = 'MARIA ISABEL';
$genero = 'F';
$fecha_nacimiento = '1985-09-14';
$num_telefono = '78238653';
$cedula_asesor = '2345678';
$id_usuario = '139';
$cod_cli = '5000000';


switch ($_GET["op"]){

	case 'guardarContratante':

		date_default_timezone_set('America/La_Paz');
		$fecha_creacion = date('Y-m-d H:i:s');

		if($encontrado != 'SI'){

			echo "DONDE: " . $donde . "<br>";
			if($donde == 'P'){
				$cod_cli = $varios->getParameterValues('cod_cli');

				echo "<br>Dentro de Buscar Cliente en BDPM<br><br>";
				//$dataCli = buscarCliente_en_BDPM($cedula);
				//$status = $dataCli['status'];
				$status = 'x';
				if($status == 'ok' ){

				/*
					$ap_paterno = $dataCli['ap_paterno'];
					$ap_materno = $dataCli['ap_materno'];
					$nombres = $dataCli['nombres'];
					$fecha_nacimiento = $dataCli['fecha_nacimiento'];
					$num_telefono = $dataCli['telefono'];
					$genero = $dataCli['genero'];
					$tipo_documento = $dataCli['tipo_documento'];
					$extension = $dataCli['extension'];
				*/
				}
			}

			//echo "Lamare a Insertar<br>";
			$rspta=$cliente->insertar($tipo_documento,$num_documento,$extension,$ap_paterno,$ap_materno,
							$nombres,$fecha_nacimiento,$num_telefono,$genero,$cod_cli,$fecha_creacion);
			//echo "ID USR: " . $rspta . "<br>";
			$new_registro_tit = $rspta;

		}else{
			$new_registro_tit = $id_cliente;
		}

		//$planes = substr($planes,0,8);

		die();


		$codigo_canal = $_SESSION['codigo_canal'];
		$codigo_agencia = $_SESSION['codigo_agencia'];

		//$codigo_canal = 'C001';
		//$codigo_agencia = 'ELT-JP';
		//$codigo_renovacion = 'PC0010';

		//$planes = 'PC000X';
		$len = strlen($codigo_renovacion);
		//echo "LEN PLAN RENOVACION: " . $len . "<br>";
		//echo "PLAN RENOVACION: " . $codigo_renovacion . "<br>";
		if($len){
			$planes = $codigo_renovacion;
		}
		//echo "PLANES3: " . $planes . "<br>";
		$rspta = $varios->getPrecioDelPlan($planes,$codigo_canal);
		$row = mysqli_fetch_assoc($rspta);
		$deuda = $row['precio_padre'];

		//echo "DEUDA: " . $deuda . "<br>";
		//dep($row);
		//die();
		//$id_usuario = 6;
		//$new_registro_tit = 5;
		//$fecha_creacion = '2024-01-05';
		//$encontrado = 'SI';

		$ndx_temp = $varios->insertar_temp($id_usuario,$new_registro_tit,$planes,$fecha_creacion,$deuda,$codigo_canal,
							$cedula_asesor,$codigo_agencia);

		$data = array();
		$data['status']     = 'ok';
		$data['id_cliente'] = $new_registro_tit;
		$data['encontrado'] = $encontrado;
		$data['id_temp']   = $ndx_temp;
		$data['plan']   = $planes;
		$data['deuda'] = $deuda;
		$data['nombre'] = $nombres . ' ' . $ap_paterno . ' ' . $ap_materno;

		$_SESSION['temp']   = $ndx_temp;

		echo json_encode($data);

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
