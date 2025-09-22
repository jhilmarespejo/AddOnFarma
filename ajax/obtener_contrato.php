<?php
ob_start();
if (strlen(session_id()) < 1) {
    session_start();
}

require_once "../config/Conexion.php";

// Incluir librerías
require_once('../libraries/tcpdf/tcpdf.php');
require_once('../vendor/autoload.php');
use setasign\Fpdi\Tcpdf\Fpdi;

// Obtener la ruta absoluta del directorio raíz
$root_path = realpath(dirname(__FILE__) . '/../') . '/';

//$id_temp = isset($_GET['id']) ? limpiarCadena($_GET['id']) : '';
$id_temp = 78;

if ($id_temp != '') {
    // Consultar datos del cliente y contrato
    $sql = "SELECT c.ap_paterno, c.ap_materno, c.nombres, c.num_documento, c.extension, c.expedido, 
                   c.fecha_nacimiento, c.fecha_creacion, c.correo, t.contrato, cdd.nombre_ciudad 
            FROM clientes c 
            LEFT JOIN temp t ON t.id_contratante = c.id  
            LEFT JOIN usuario u ON u.idusuario = t.id_usuario 
            LEFT JOIN agencias a ON a.codigo_agencia = u.codigoAlmacen  
            LEFT JOIN ciudades cdd ON cdd.id = a.codigo_ciudad  
            WHERE t.id = '$id_temp'";

    $result = ejecutarConsulta($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $contrato_nombre = $row['contrato'];
        
        // 1. SELECCIÓN DINÁMICA DE PLANTILLA
        // Extraer el primer bloque antes del guion
        $partes_contrato = explode('-', $contrato_nombre);
        $nombre_plantilla = $partes_contrato[0] . '.pdf';
        $ruta_contratos = $root_path . "files/contratos/";
        $archivo_pdf_original = $ruta_contratos . $nombre_plantilla;
        
        // Verificar si la plantilla existe
        if (!file_exists($archivo_pdf_original)) {
            echo "<html><body><h3>La plantilla '$nombre_plantilla' no se encuentra disponible.</h3></body></html>";
            exit;
        }
        
        // 2. PREPARAR DATOS DEL CLIENTE
        // preparar fecha de nacimiento
        $fecha = $row['fecha_nacimiento'];
        list($anio_nac, $mes_nac, $dia_nac) = explode('-', $fecha);
        $fecha_formateada = "DÍA:\t$dia_nac MES:\t$mes_nac\tAÑO: $anio_nac";

        // preparar fecha de inicio y fin de contrato
        $fecha_inicio = $row['fecha_creacion'];
        list($fecha, $hora_completa) = explode(' ', $fecha_inicio);
        $hora = substr($hora_completa, 0, 5);
        list($anio_inicio, $mes_inicio, $dia_inicio) = explode('-', $fecha);
        $anio_fin = $anio_inicio + 1; 
        
        $fecha_inicio_formateada = "DESDE:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_inicio";
        $fecha_fin_formateada = "HASTA:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_fin";

        // Preparar lugar y fecha de contrato
        $lugar_fecha = "CIUDAD,  ".$row['nombre_ciudad'] . ", " . date('d') . " DE " . date('m') . " DE " . date('Y');
        
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
            't.contrato' => $contrato_nombre,
            'c.nombres' => $row['nombres'] . ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'],
            'c.num_documento' => $row['num_documento'].'-'.$row['extension'].' '.$row['expedido'],
            'c.fecha_nacimiento' => $fecha_formateada,
            'c.fecha_inicio' => $fecha_inicio_formateada,
            'c.fecha_fin' => $fecha_fin_formateada,
            'c.lugar_fecha' => $lugar_fecha,
        ];
        
        // --- GENERAR PDF DINÁMICO ---
        $pdf = new FPDI();
        
        // Importar páginas del PDF original
        $pageCount = $pdf->setSourceFile($archivo_pdf_original);
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($i);
            $pdf->useTemplate($tplIdx, 0, 0, 210);
            
            // Solo en la primera página insertamos los datos
            if ($i == 1) {
                foreach ($datos as $campo => $valor) {
                    if (isset($coordenadas[$campo])) {
                        $cfg = $coordenadas[$campo];
                        $x_mm = $cfg['x'] * 25.4 / 72;
                        $y_mm = $cfg['y'] * 25.4 / 72;

                        $pdf->SetFont($cfg['font'], '', $cfg['size']);
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetXY($x_mm, $y_mm);
                        $pdf->Cell(0, 0, $valor, 0, 0, 'L');
                    }
                }
            }
        }
        
        // 3. GUARDAR PDF PERSONALIZADO EN CARPETA TEMPORAL
        $ruta_temporal = $root_path . "files/contratostmp/";
        if (!file_exists($ruta_temporal)) {
            mkdir($ruta_temporal, 0777, true);
        }
        
        $nombre_pdf_personalizado = $contrato_nombre . "_personalizado.pdf";
        $ruta_pdf_personalizado = $ruta_temporal . $nombre_pdf_personalizado;
        
        // Guardar el PDF generado (usar ruta absoluta)
        $pdf->Output($ruta_pdf_personalizado, 'F');
        
        // 4. FIRMAR DIGITALMENTE EL PDF
        $certificadoPath = $root_path . 'certificates/ROBERTO_ALFREDO_EWEL_PALENQUE.p12';
        $certificadoPassword = '793966';
        
        // Verificar que el certificado existe
        if (!file_exists($certificadoPath)) {
            echo "<html><body><h3>Error: Certificado digital no encontrado en: $certificadoPath</h3></body></html>";
            exit;
        }
        
        // Firmar el documento
        $ruta_firmados = $root_path . "files/contratosfirmados/";
        if (!file_exists($ruta_firmados)) {
            mkdir($ruta_firmados, 0777, true);
        }
        
        $nombre_pdf_firmado = $contrato_nombre . "_firmado.pdf";
        $ruta_pdf_firmado = $ruta_firmados . $nombre_pdf_firmado;
        
        // Función para firmar el PDF
        if (firmarPDF($ruta_pdf_personalizado, $ruta_pdf_firmado, $certificadoPath, $certificadoPassword)) {
            // --- MOSTRAR PDF EN PANTALLA ---
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nombre_pdf_firmado . '"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            
            // Mostrar el PDF firmado
            readfile($ruta_pdf_firmado);
        } else {
            echo "<html><body><h3>Error al firmar el documento digitalmente.</h3></body></html>";
        }
        
        exit;
    
    } else {
        echo "<html><body><h3>No se encontró el registro.</h3></body></html>";
    }
} else {
    echo "<html><body><h3>ID no proporcionado.</h3></body></html>";
}

ob_end_flush();

// Función para firmar PDF digitalmente
function firmarPDF($archivoOrigen, $archivoDestino, $certificadoPath, $password) {
    try {
        // Cargar el certificado
        $pkcs12 = file_get_contents($certificadoPath);
        $certs = [];
        if (!openssl_pkcs12_read($pkcs12, $certs, $password)) {
            error_log("Error al leer certificado PKCS12: " . openssl_error_string());
            return false;
        }
        
        $privateKey = $certs['pkey'];
        $certificate = $certs['cert'];
        
        // Crear instancia de FPDI
        $pdf = new Fpdi();
        
        // Obtener número de páginas del documento original
        $pageCount = $pdf->setSourceFile($archivoOrigen);
        
        // Procesar cada página
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($i);
            $pdf->useTemplate($tplIdx, 0, 0, 210);
        }
        
        // Configurar la firma
        $pdf->setSignature($certificate, $privateKey, $password, '', 2, array(
            'Name' => 'Alfredo Vicente Ascarrunz Arana',
            'Location' => 'Bolivia',
            'Reason' => 'Firma digital de contrato',
            'ContactInfo' => ''
        ));
        
        // Guardar el PDF firmado
        $pdf->Output($archivoDestino, 'F');
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al firmar PDF: " . $e->getMessage());
        return false;
    }
}
?>