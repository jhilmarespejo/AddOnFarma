<?php
ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

require_once "../config/Conexion.php";

// Incluir TCPDF PRIMERO
require_once('../libraries/tcpdf/tcpdf.php');

// Luego incluir FPDI
require_once('../vendor/autoload.php'); // Si usas Composer
// O si instalaste manualmente:
// require_once('../libraries/fpdi/src/autoload.php');

use setasign\Fpdi\Tcpdf\Fpdi;

//$id_temp = isset($_GET['id']) ? limpiarCadena($_GET['id']) : '';
$id_temp =41;

if ($id_temp != '') {
    // Consultar datos del cliente y contrato
    $sql = "SELECT c.ap_paterno, c.ap_materno, c.nombres, c.num_documento,c.extension,c.expedido, c.fecha_nacimiento, c.fecha_creacion, c.correo, t.contrato,
                cdd.nombre_ciudad FROM clientes c 
            LEFT JOIN temp t ON t.id_contratante = c.id  
            LEFT JOIN usuario u ON u.idusuario = t.id_usuario 
            LEFT JOIN agencias a ON a.codigo_agencia = u.codigoAlmacen  
            LEFT JOIN ciudades cdd ON cdd.id = a.codigo_ciudad  
            WHERE t.id = '$id_temp'";

    $result = ejecutarConsulta($sql);
    
   
    // Formatear la salida como una sola cadena
    //$fecha_formateada = "DÍA:\t$dia_nac MES:\t$mes_nac\tAÑO: $anio_nac";

    
    
    if ($result && $row = $result->fetch_assoc()) {
        $contrato_nombre = $row['contrato']; // nombre del archivo PDF sin extensión
        $ruta_contratos = "../files/contratos/";
        $archivo_pdf_original = $ruta_contratos . $contrato_nombre . ".pdf";
        
        // var_dump($row['nombres']);exit;
        
        // preparar fecha de nacimiento
        $fecha = $row['fecha_nacimiento']; // ejemplo: '1988-03-12'
        list($anio_nac, $mes_nac, $dia_nac) = explode('-', $fecha);
        $fecha_formateada = "DÍA:\t$dia_nac MES:\t$mes_nac\tAÑO: $anio_nac";

        //preparar fecha de inicio y fin de contrato
        $fecha_inicio = $row['fecha_creacion']; // ejemplo: '2025-09-20 23:59:35'
        // Separar fecha y hora
        list($fecha, $hora_completa) = explode(' ', $fecha_inicio);
        // Extraer hora en formato HH:MM
        $hora = substr($hora_completa, 0, 5); // '23:59'
        // Separar año, mes y día
        list($anio_inicio, $mes_inicio, $dia_inicio) = explode('-', $fecha);
        $anio_fin = $anio_inicio + 1; 
        // Formatear la salida
        $fecha_inicio_formateada = "DESDE:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_inicio";
        $fecha_fin_formateada = "HASTA:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_fin ";

        // Preparar lugar y fechade contrato
        
        $row['lugar_fecha'] = "CIUDAD,  ".$row['nombre_ciudad'] . ", " . date('d') . " DE " . date('m') . " DE " . date('Y');
        //var_dump($row['lugar_fecha']);exit;
        // Verificar si el archivo PDF original existe
        if (!file_exists($archivo_pdf_original)) {
            echo "<html><body><h3>El contrato no se encuentra disponible.</h3></body></html>";
            exit;
        }
        
        // --- DEFINIR COORDENADAS ---
        $coordenadas = [
            't.contrato' => ['x' => 230, 'y' => 46, 'size' => 6, 'font' => 'helvetica'],
            'c.nombres' => ['x' => 75, 'y' => 124, 'size' => 6, 'font' => 'helvetica'],
            'c.num_documento' => ['x' => 85, 'y' => 136, 'size' => 6, 'font' => 'helvetica'],
            'c.fecha_nacimiento' => ['x' => 85, 'y' => 148, 'size' => 6, 'font' => 'helvetica'],
            'c.fecha_inicio' => ['x' => 112, 'y' => 159, 'size' => 5, 'font' => 'helvetica'],
            'c.fecha_fin' => ['x' => 112, 'y' => 167, 'size' => 5, 'font' => 'helvetica'],
            'c.lugar_fecha' => ['x' => 220, 'y' => 705, 'size' => 7, 'font' => 'helvetica'],
        ];
        
        // --- DATOS A INSERTAR ---
        $datos = [
            't.contrato' => $contrato_nombre, // o lo que quieras mostrar
            'c.nombres' => $row['nombres'] . ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'],
            'c.num_documento' => $row['num_documento'].'-'.$row['extension'].' '.$row['expedido'],
            'c.fecha_nacimiento' => $fecha_formateada,
            'c.fecha_inicio' => $fecha_inicio_formateada,
            'c.fecha_fin' => $fecha_fin_formateada,
            'c.lugar_fecha' =>  $row['lugar_fecha'],
        ];
        
        // --- GENERAR PDF DINÁMICO ---
        $pdf = new FPDI();
        
        // Importar primera página del PDF original
        $pageCount = $pdf->setSourceFile($archivo_pdf_original);
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($i);
            $pdf->useTemplate($tplIdx, 0, 0, 210); // Ajusta 210 si el PDF no es A4
            
            // Solo en la primera página insertamos los datos (ajusta si necesitas en otras páginas)
            if ($i == 1) {
                foreach ($datos as $campo => $valor) {
                    if (isset($coordenadas[$campo])) {
                        $cfg = $coordenadas[$campo];
                        // Convertir coordenadas a mm (TCPDF usa mm; si tus coordenadas están en puntos, convierte)
                        // Asumimos que tus coordenadas están en puntos → convertimos a mm
                        $x_mm = $cfg['x'] * 25.4 / 72; // px/pt → mm
                        $y_mm = $cfg['y'] * 25.4 / 72;

                        $pdf->SetFont($cfg['font'], '', $cfg['size']);
                        $pdf->SetTextColor(0, 0, 0); // negro
                        $pdf->SetXY($x_mm, $y_mm);
                        $pdf->Cell(0, 0, $valor, 0, 0, 'L'); // o usa Write() o Text() si prefieres
                    }
                }
            }
        }
        
        // --- MOSTRAR PDF EN PANTALLA ---
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $contrato_nombre . '_generado.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');

        // En lugar de readfile, enviamos el PDF generado
        echo $pdf->Output('', 'S'); // 'S' = return as string

        exit;
    
    } else {
        echo "<html><body><h3>No se encontró el registro.</h3></body></html>";
    }
} else {
    echo "<html><body><h3>ID no proporcionado.</h3></body></html>";
}

ob_end_flush();
?>