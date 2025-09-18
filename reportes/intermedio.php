<?php

require '../config/global.php';

session_start();

$con = mysqli_connect(DB_HOST,DB_USERNAME,DB_PASSWORD,DB_NAME);
mysqli_set_charset($con, 'utf8');
// Check connection
if (mysqli_connect_errno())
{
 echo "Failed to connect to MySQL: " . mysqli_connect_error();
}


function obtenerDatosRegistro($db, $id_registro){

	$sql = "SELECT CONCAT(c.nombres, ' ',c.ap_paterno,' ',c.ap_materno) nombre, t.codigo_plan, t.precio, p.descripcion_plan_padre, c.num_documento cedula,t.fecha_cobranzas, t.factura 
	FROM temp t, clientes c, plan_padre p
	WHERE t.id_contratante = c.id
	AND t.codigo_plan = p.codigo_plan_padre
	AND t.id ='$id_registro'";

	//echo "SQL: " . $sql . "<br>";
	$result = mysqli_query($db, $sql);
	$row = mysqli_fetch_assoc($result);

	return $row;
}

if($_GET){

    $id_registro = $_GET['id_registro'];

	//$id_registro = 34;
    $row = obtenerDatosRegistro($con, $id_registro);
    dep($row);
    die();

    if(isset($row['factura'])){
		$link = $row['factura'];
		echo "<script>window.open('$link', '_blank');</script>";
        exit();
	}else{

        echo '<script><a href="reporte_cajero.php?id_registro=" . $id_registro . target="_blank"></script>'

	}

    //dep($row);


}

function dep($data){
	$format = print_r('<pre>');
	$format .= print_r($data);
	$format .= print_r('</pre>');
	return $format;
}