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

$id_temp = isset($_GET['id']) ? limpiarCadena($_GET['id']) : '';

// Función para actualizar temp CORREGIDA
function actualizarTemp($id_temp, $campo, $valor, $append = false) {
    if ($append) {
        // Si es append, obtener el valor actual y agregar el nuevo
        $sql_select = "SELECT $campo FROM temp WHERE id = '$id_temp'";
        $result = ejecutarConsulta($sql_select);
        $current = '';
        if ($result && $row = $result->fetch_assoc()) {
            $current = $row[$campo] . ' | ';
        }
        $valor = $current . $valor;
    }
    
    $valor_escape = limpiarCadena($valor);
    $sql = "UPDATE temp SET $campo = '$valor_escape' WHERE id = '$id_temp'";
    return ejecutarConsulta($sql);
}

// Función para firmar PDF CORREGIDA (sin $id_temp)
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

if ($id_temp != '') {
    // Registrar inicio del proceso
    actualizarTemp($id_temp, 'contrato_pdf', 'INICIADO: ' . date('Y-m-d H:i:s'));

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
    if (!$result) {
        $error_msg = "ERROR_BD: " . mysqli_error($conexion) . " - " . date('Y-m-d H:i:s');
        actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Error en consulta de base de datos"
        ]);
        exit;
    }

    if ($result && $row = $result->fetch_assoc()) {
        $contrato_nombre = $row['contrato'];
        
        // 1. SELECCIÓN DINÁMICA DE PLANTILLA
        $partes_contrato = explode('-', $contrato_nombre);
        $nombre_plantilla = $partes_contrato[0] . '.pdf';
        $ruta_contratos = $root_path . "files/contratos/";
        $archivo_pdf_original = $ruta_contratos . $nombre_plantilla;
        
        // Verificar si la plantilla existe
        if (!file_exists($archivo_pdf_original)) {
            $error_msg = "ERROR_PLANTILLA: $nombre_plantilla no existe en $ruta_contratos - " . date('Y-m-d H:i:s');
            actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "La plantilla '$nombre_plantilla' no se encuentra disponible."
            ]);
            exit;
        }
        
        // 2. PREPARAR DATOS DEL CLIENTE
        $fecha = $row['fecha_nacimiento'];
        list($anio_nac, $mes_nac, $dia_nac) = explode('-', $fecha);
        $fecha_formateada = "DÍA:\t$dia_nac MES:\t$mes_nac\tAÑO: $anio_nac";

        $fecha_inicio = date('Y-m-d');
        list($fecha, $hora_completa) = explode(' ', $fecha_inicio);
        $hora = substr($hora_completa, 0, 5);
        list($anio_inicio, $mes_inicio, $dia_inicio) = explode('-', $fecha);
        $anio_fin = $anio_inicio + 1; 
        
        $fecha_inicio_formateada = "DESDE:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_inicio";
        $fecha_fin_formateada = "HASTA:  Horas  $hora del   Día: $dia_inicio   Mes: $mes_inicio   Año: $anio_fin";

        $meses = ['01' =>'ENERO','02'=>'FEBRERO','03'=>'MARZO','04'=>'ABRIL','05'=>'MAYO','06'=>'JUNIO','07'=>'JULIO','08'=>'AGOSTO','09'=>'SEPTIEMBRE','10'=>'OCTUBRE','11'=> 'NOVIEMBRE','12'=>'DICIEMBRE'];
        $mes_actual = $meses[date('m')];
        $lugar_fecha = $row['nombre_ciudad'] . ", " . date('d') . " DE " . $mes_actual . " DE " . date('Y');
        
        $datos = [
            't.contrato' => $contrato_nombre,
            'c.nombres' => $row['nombres'] . ' ' . $row['ap_paterno'] . ' ' . $row['ap_materno'],
            'c.num_documento' => $row['num_documento'].' '.$row['extension'].' '.$row['expedido'],
            'c.fecha_nacimiento' => $fecha_formateada,
            'c.fecha_inicio' => $fecha_inicio_formateada,
            'c.fecha_fin' => $fecha_fin_formateada,
            'c.lugar_fecha' => $lugar_fecha,
        ];

        // Configurar coordenadas según la plantilla
        if ($nombre_plantilla === 'PPCE0210.pdf') {
            $coordenadas = [
                1 => [
                    't.contrato' => ['x' => 238, 'y' => 46, 'size' => 6, 'font' => 'helvetica'],
                    'c.nombres' => ['x' => 75, 'y' => 124, 'size' => 6, 'font' => 'helvetica'],
                    'c.num_documento' => ['x' => 85, 'y' => 136, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_nacimiento' => ['x' => 85, 'y' => 148, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_inicio' => ['x' => 85, 'y' => 168, 'size' => 5, 'font' => 'helvetica'],
                    'c.fecha_fin' => ['x' => 85, 'y' => 174, 'size' => 5, 'font' => 'helvetica'],
                    'c.lugar_fecha' => ['x' => 223, 'y' => 705, 'size' => 7, 'font' => 'helvetica'],
                ],
                2 => [
                    't.contrato' => ['x' => 238, 'y' => 46, 'size' => 6, 'font' => 'helvetica'],
                    'c.lugar_fecha' => ['x' => 223, 'y' => 705, 'size' => 7, 'font' => 'helvetica'],
                ]
            ];
        } elseif ($nombre_plantilla === 'PPCE0196.pdf') {
            $coordenadas = [
                1 => [
                    't.contrato' => ['x' => 230, 'y' => 45, 'size' => 6, 'font' => 'helvetica'],
                    'c.nombres' => ['x' => 75, 'y' => 124, 'size' => 6, 'font' => 'helvetica'],
                    'c.num_documento' => ['x' => 85, 'y' => 136, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_nacimiento' => ['x' => 85, 'y' => 148, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_inicio' => ['x' => 135, 'y' => 159, 'size' => 5, 'font' => 'helvetica'],
                    'c.fecha_fin' => ['x' => 135, 'y' => 167, 'size' => 5, 'font' => 'helvetica'],
                    'c.lugar_fecha' => ['x' => 220, 'y' => 705, 'size' => 7, 'font' => 'helvetica'],
                ]
            ];
        } else {
            $coordenadas = [
                1 => [
                    't.contrato' => ['x' => 230, 'y' => 45, 'size' => 6, 'font' => 'helvetica'],
                    'c.nombres' => ['x' => 75, 'y' => 124, 'size' => 6, 'font' => 'helvetica'],
                    'c.num_documento' => ['x' => 85, 'y' => 136, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_nacimiento' => ['x' => 85, 'y' => 148, 'size' => 6, 'font' => 'helvetica'],
                    'c.fecha_inicio' => ['x' => 135, 'y' => 159, 'size' => 5, 'font' => 'helvetica'],
                    'c.fecha_fin' => ['x' => 135, 'y' => 167, 'size' => 5, 'font' => 'helvetica'],
                    'c.lugar_fecha' => ['x' => 220, 'y' => 705, 'size' => 7, 'font' => 'helvetica'],
                ]
            ];
        }

        // --- GENERAR PDF CON TRY-CATCH ---
        try {
            $pdf = new FPDI();
            $pageCount = $pdf->setSourceFile($archivo_pdf_original);

            for ($i = 1; $i <= $pageCount; $i++) {
                $pdf->AddPage();
                $tplIdx = $pdf->importPage($i);
                $pdf->useTemplate($tplIdx, 0, 0, 210);
                
                if (isset($coordenadas[$i])) {
                    foreach ($coordenadas[$i] as $campo => $cfg) {
                        if (isset($datos[$campo])) {
                            $x_mm = $cfg['x'] * 25.4 / 72;
                            $y_mm = $cfg['y'] * 25.4 / 72;
                            
                            $pdf->SetFont($cfg['font'], '', $cfg['size']);
                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->SetXY($x_mm, $y_mm);
                            $pdf->Cell(0, 0, $datos[$campo], 0, 0, 'L');
                        }
                    }
                }
            }

            // 3. GUARDAR PDF PERSONALIZADO
            $ruta_temporal = $root_path . "files/contratostmp/";
            if (!file_exists($ruta_temporal)) {
                mkdir($ruta_temporal, 0777, true);
            }
            
            $nombre_pdf_personalizado = $contrato_nombre . "_".$row['num_documento'].".pdf";
            $ruta_pdf_personalizado = $ruta_temporal . $nombre_pdf_personalizado;
            
            $pdf->Output($ruta_pdf_personalizado, 'F');

            // Verificar si el archivo se creó correctamente
            if (!file_exists($ruta_pdf_personalizado) || filesize($ruta_pdf_personalizado) === 0) {
                $error_type = !file_exists($ruta_pdf_personalizado) ? "NO_CREADO" : "VACIO";
                $error_msg = "ERROR_GUARDADO_PDF: $error_type - " . date('Y-m-d H:i:s');
                actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Error al guardar el PDF: $error_type"
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            $error_msg = "ERROR_GENERACION_PDF: " . $e->getMessage() . " - " . date('Y-m-d H:i:s');
            actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Error al generar el PDF: " . $e->getMessage()
            ]);
            exit;
        }
        
        // 4. FIRMAR DIGITALMENTE EL PDF
        $certificadoPath = $root_path . 'certificates/ROBERTO_ALFREDO_EWEL_PALENQUE.p12';
        $certificadoPassword = '793966';
        
        if (!file_exists($certificadoPath)) {
            $error_msg = "ERROR_CERTIFICADO: No encontrado en $certificadoPath - " . date('Y-m-d H:i:s');
            actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Certificado digital no encontrado"
            ]);
            exit;
        }
        
        $ruta_firmados = $root_path . "files/contratosfirmados/";
        if (!file_exists($ruta_firmados)) {
            mkdir($ruta_firmados, 0777, true);
        }
        
        $nombre_pdf_firmado = $nombre_pdf_personalizado;
        $ruta_pdf_firmado = $ruta_firmados . $nombre_pdf_firmado;
        
        // CORREGIDO: Solo 4 parámetros
        if (firmarPDF($ruta_pdf_personalizado, $ruta_pdf_firmado, $certificadoPath, $certificadoPassword)) {
            // ÉXITO: Actualizar con ruta del PDF firmado
            actualizarTemp($id_temp, 'contrato_pdf', $ruta_pdf_firmado);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Contrato generado y firmado digitalmente',
                'archivo_firmado' => $nombre_pdf_firmado,
                'ruta_firmado' => $ruta_pdf_firmado
            ]);
        } else {
            $error_msg = "ERROR_FIRMA: Falló la firma digital - " . date('Y-m-d H:i:s');
            actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al firmar el documento digitalmente'
            ]);
        }
        
        exit;
    
    } else {
        $error_msg = "ERROR_REGISTRO: No se encontró registro con id $id_temp - " . date('Y-m-d H:i:s');
        actualizarTemp($id_temp, 'contrato_pdf', $error_msg, true);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el registro en la base de datos'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ID no proporcionado'
    ]);
}

ob_end_flush();
?>