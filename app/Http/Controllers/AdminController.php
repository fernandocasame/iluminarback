<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\tipoJuegos;
use App\Models\J_juegos;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CodigosLibros;
use App\Models\CuotasPorCobrar;
use App\Models\EstudianteMatriculado;
use App\Models\HistoricoCodigos;
use App\Models\PedidoAlcance;
use App\Models\PedidoAlcanceHistorico;
use App\Models\RepresentanteEconomico;
use App\Models\RepresentanteLegal;
use App\Models\SeminarioCapacitador;
use App\Models\Temporada;
use App\Models\Usuario;
use DB;
use GraphQL\Server\RequestError;
use Mail;
use Illuminate\Support\Facades\Http;
use stdClass;
use App\Traits\Pedidos\TraitPedidosGeneral;
use App\Traits\Codigos\TraitCodigosGeneral;
class AdminController extends Controller
{
    use TraitPedidosGeneral;
    use TraitCodigosGeneral;
    public function getFilesTest(){
        $query = DB::SELECT("SELECT * FROM tempfiles");
        return $query;
    }

    // public function datoEscuela(Request $request){
    //      set_time_limit(6000);
    //     ini_set('max_execution_time', 6000);
    //    $buscarUsuario = DB::SELECT("SELECT codl.idusuario

    //    FROM codigoslibros AS codl, usuario AS u, institucion AS its
    //    WHERE its.idInstitucion = 424
    //    AND its.idInstitucion = u.institucion_idInstitucion
    //    AND codl.idusuario = u.idusuario
    //    AND u.cedula <> '000000016'
    //     ORDER BY codl.idusuario DESC
    //    LIMIT 10
    //    ");



    //     $data  = [];
    //     $datos = [];
    //     $libros=[];
    //    foreach($buscarUsuario as $key => $item){
    //         $buscarLibros = DB::SELECT("SELECT  * FROM codigoslibros
    //         WHERE idusuario  = '$item->idusuario'
    //         ORDER BY updated_at DESC
    //         ");

    //         foreach($buscarLibros  as $l => $tr){

    //             $libros[$l] = [
    //                 "codigo" => $tr->codigo
    //             ];


    //             $data[$key] =[
    //                 "usuario" => $item->idusuario,
    //                 "libros" => $libros
    //             ];
    //         }


    //    }
    //    $datos = [
    //        "informacion" => $data
    //    ];
    //    return $datos;
    // }
    public function index()
    {
        $usuarios = DB::select("CALL `prolipa` ();");
        return $usuarios;
    }
    function filtrarPorEdad($persona) {
        return $persona["edad"] == 30;
    }
    public function pruebaApi(Request $request){
        try {
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);
            $datos=[];
            $periodo  = $request->periodo_id;
            $anio = date("Y");
            //obtener los vendedores que tienen pedidos
            $query = DB::SELECT("SELECT DISTINCT p.id_asesor ,
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor, u.cedula,u.iniciales
            FROM pedidos p
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            WHERE p.id_asesor <> '68750'
            AND p.id_asesor <> '6698'
            AND u.id_group = '11'
            ");
            foreach($query as $keyP => $itemP){
                //asesores
                $teran = ["OT","OAT"];
                $galo  = ["EZ","EZP"];
                //buscar el codigo periodo
                $search = DB::SELECT("SELECT * FROM periodoescolar pe
                WHERE pe.idperiodoescolar = '$periodo'
                ");
                //buscar las iniciales asesor
                $search2 = DB::SELECT("SELECT  u.iniciales FROM usuario  u
                WHERE u.idusuario = '$itemP->id_asesor'
                ");

                if(empty($search) || empty($search2) ){
                    return ["status" => "0","message" => "No hay codigo de periodo o no hay codigo de asesor"];
                }
                //VARIABLES
                $codPeriodo = $search[0]->codigo_contrato;
                $iniciales = $search2[0]->iniciales;
                $region    = $search[0]->region_idregion;
                //BUSCAR EL PERIODO ANTERIOR
                $anio = date("Y");
                $menosUno = "";
                if($region == 1){
                    $menosUno = "S".substr(($anio-1),-2);
                }else{
                    $menosUno = "C".substr(($anio-1),-2);
                }

                //ASESORES QUE TIENE MAS DE UNA INICIAL
                $valores     = [];
                $arrayAsesor = [];
                $JsonEnviar  = [];
                if($iniciales == 'OT' || $iniciales == 'EZ'){
                    if($iniciales == 'OT') $arrayAsesor = $teran;
                    if($iniciales == 'EZ') $arrayAsesor = $galo;
                    foreach($arrayAsesor as $key => $item){
                        $test = Http::get('http://186.46.24.108:9095/api/f_ClienteInstitucion/Get_contratounificado?codasesor='.$item.'&periodo=C-'.$codPeriodo);
                        $json = json_decode($test, true);
                    $valores[$key] = $json;
                    }
                    $setearArray =  array_merge(...$valores);
                    $JsonEnviar = array_unique($setearArray,SORT_REGULAR);
                }else{
                    $test = Http::get('http://186.46.24.108:9095/api/f_ClienteInstitucion/Get_contratounificado?codasesor='.$iniciales.'&periodo=C-'.$codPeriodo);
                    $json = json_decode($test, true);
                    $JsonEnviar = $json;
                }
                //Función para filtrar los no convertidos
                $resultado = array_filter($JsonEnviar, function($p) {
                    return $p["estVenCodigo"] != 3 && !str_starts_with($p["venConvertido"] , 'C');
                    // print_r($p );
                });
                $renderSet = array_values($resultado);
                //enviar valores
                $dataFinally = array();
                $contador = 0;
                foreach($renderSet as $key => $item){
                    //variables
                    $venValor = $renderSet[$contador]["venValor"];
                    $descuento = $renderSet[$contador]["venDescuento"];
                    //proceso
                    $obj = new stdClass();
                    $obj->VEN_VALOR = $venValor;
                    $obj->ven_neta =  ( $venValor - (($venValor * $descuento)/100));
                    $obj->contrato = $renderSet[$contador]["venCodigo"];
                    $obj->insNombre = $renderSet[$contador]["insNombre"];
                    $obj->ciuNombre = $renderSet[$contador]["ciuNombre"];
                    $obj->anticipo_aprobado =  $renderSet[$contador]["venAnticipo"];
                    array_push($dataFinally,$obj);
                    $contador++;
                }
                //===SIN CONTRATOS ===
                $query = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
                SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
                FROM pedidos p
                WHERE p.id_asesor = '$itemP->id_asesor'
                AND p.id_periodo = '$periodo'
                AND p.contrato_generado IS NULL
                AND p.estado = '1'
                ");
                $arraySinContrato = [];
                $ventaBrutaActual = $query[0]->ventaBrutaActual;
                $ven_neta_actual  = $query[0]->ven_neta_actual;
                $arraySinContrato[0] = [
                    "ventaBrutaActual"      => $ventaBrutaActual == null ? '0' :$ventaBrutaActual,
                    "ven_neta_actual"       => $ven_neta_actual  == null ? '0' :$ven_neta_actual,
                ];
                //traer la data de los pedidios en prolipa con facturacion
                $datosContratos = [];
                $contador = 0;
                foreach($dataFinally as $key => $item){
                    $pedido = DB::SELECT("SELECT p.*, ph.estado as historicoEstado
                    FROM pedidos p
                    LEFT JOIN pedidos_historico  ph ON p.id_pedido = ph.id_pedido
                    WHERE p.contrato_generado = '$item->contrato'
                    ");
                    if(empty($pedido)){
                        $datosContratos[$contador] = [
                            "VEN_VALOR"         => $item->VEN_VALOR,
                            "ven_neta"          => $item->ven_neta,
                            "contrato"          => $item->contrato,
                            "insNombre"         => $item->insNombre,
                            "ciuNombre"         => $item->ciuNombre,
                            "anticipo_aprobado" => $item->anticipo_aprobado,
                            //prolipa
                            "id_pedido"         => null,
                        ];
                    }else{
                        $datosContratos[$contador] = [
                            "VEN_VALOR"         => $item->VEN_VALOR,
                            "ven_neta"          => $item->ven_neta,
                            "contrato"          => $item->contrato,
                            "insNombre"         => $item->insNombre,
                            "ciuNombre"         => $item->ciuNombre,
                            "anticipo_aprobado" => $item->anticipo_aprobado,
                            "id_pedido"         => $pedido[0]->id_pedido,
                            "id_periodo"        => $pedido[0]->id_periodo,
                            "contrato_generado" => $pedido[0]->contrato_generado,
                            "tipo_venta"        => $pedido[0]->tipo_venta,
                            "estado"            => $pedido[0]->estado,
                            "historicoEstado"   => $pedido[0]->historicoEstado,
                        ];
                    }
                    $contador++;
                }
                $contratosEnviar = [
                    "contratos"     => $datosContratos,
                    "sin_contratos" => $arraySinContrato
                ];
                //====VENTA ANTERIOR======/
                //VENTA BRUTA ANTERIOR
                    $queryMenosUno = DB::SELECT("SELECT   t.VEN_VALOR,t.PERIODO,
                    ( t.VEN_VALOR - ((t.VEN_VALOR * t.VEN_DESCUENTO)/100)) AS ven_neta
                    FROM temp_reporte t
                    WHERE t.VEN_D_CI = '$itemP->cedula'
                    AND t.PERIODO = '$menosUno'
                ");
                // return [
                //     "contratos"     => $datosContratos,
                //     "sin_contratos" => $arraySinContrato
                // ];
                $datos[$keyP] = [
                    "id_asesor"             => $itemP->id_asesor,
                    "asesor"                => $itemP->asesor,
                    "iniciales"             => $itemP->iniciales,
                    "cedula"                => $itemP->cedula,
                    "contratosEnviar"       => $contratosEnviar,
                    "MenosUno"              => $queryMenosUno,
                ];

            }//FIN FOR EACH ASESORES
            return $datos;
            } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
    }
    public function UpdateCodigo($codigo,$union,$TipoVenta){
        if($TipoVenta == 1){
            return $this->updateCodigoVentaDirecta($codigo,$union);
        }
        if($TipoVenta == 2){
            return $this->updateCodigoVentaLista($codigo,$union);
        }
    }
    public function updateCodigoVentaDirecta($codigo,$union){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'           => 'f001',
                'bc_institucion'    => 981,
                'bc_periodo'        => 22,
                'venta_estado'      => 2,
                'codigo_union'      => $union
            ]);
        return $codigo;
    }
    public function updateCodigoVentaLista($codigo,$union){
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'                   => 'f001',
                'venta_lista_institucion'   => 981,
                'bc_periodo'                => 22,
                'venta_estado'              => 2,
                'codigo_union'              => $union
            ]);
        return $codigo;
    }
    public function pruebaData(Request $request){
        $pedido = "486";
        try{
            $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
            p.descuento, p.id_periodo,
            p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
            se.nombre_serie
            FROM pedidos_val_area pv
            left join area ar ON  pv.id_area = ar.idarea
            left join series se ON pv.id_serie = se.id_serie
            INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
            WHERE pv.id_pedido = '$pedido'
            AND pv.alcance = '0'
            GROUP BY pv.id;
            ");
            $datos = [];
            foreach($val_pedido as $key => $item){
                $valores = [];
                //plan lector
                if($item->plan_lector > 0 ){
                    $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,l.asignatura_idasignatura,
                    (
                        SELECT f.pvp AS precio
                        FROM pedidos_formato f
                        WHERE f.id_serie = '6'
                        AND f.id_area = '69'
                        AND f.id_libro = '$item->plan_lector'
                        AND f.id_periodo = '$item->id_periodo'
                    )as precio, ls.codigo_liquidacion,ls.version,ls.year
                    FROM libro l
                    left join libros_series ls  on ls.idLibro = l.idlibro
                    WHERE l.idlibro = '$item->plan_lector'
                    ");
                    $valores = $getPlanlector;
                }else{
                    $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,l.asignatura_idasignatura,
                    (
                        SELECT f.pvp AS precio
                        FROM pedidos_formato f
                        WHERE f.id_serie = ls.id_serie
                        AND f.id_area = a.area_idarea
                        AND f.id_periodo = '$item->id_periodo'
                    )as precio
                    FROM libros_series ls
                    LEFT JOIN libro l ON ls.idLibro = l.idlibro
                    LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                    WHERE ls.id_serie = '$item->id_serie'
                    AND a.area_idarea  = '$item->id_area'
                    AND l.Estado_idEstado = '1'
                    AND a.estado = '1'
                    AND ls.year = '$item->year'
                    LIMIT 1
                    ");
                    $valores = $getLibros;
                }
                $datos[$key] = [
                    "id"                => $item->id,
                    "id_pedido"         => $item->id_pedido,
                    "valor"             => $item->valor,
                    "id_area"           => $item->id_area,
                    "tipo_val"          => $item->tipo_val,
                    "id_serie"          => $item->id_serie,
                    "year"              => $item->year,
                    // "anio"              => $valores[0]->year,
                    // "version"           => $valores[0]->version,
                    "created_at"        => $item->created_at,
                    "updated_at"        => $item->updated_at,
                    "descuento"         => $item->descuento,
                    "anticipo"          => $item->anticipo,
                    "comision"          => $item->comision,
                    "plan_lector"       => $item->plan_lector,
                    "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                    // "idlibro"           => $valores[0]->idlibro,
                    // "nombrelibro"       => $valores[0]->nombrelibro,
                    // "precio"            => $valores[0]->precio,
                    // "idasignatura"      => $valores[0]->asignatura_idasignatura,
                    // "subtotal"          => $item->valor * $valores[0]->precio,
                    // "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
                    "values"            => $valores
                ];
            }
            return $datos;
        }
        catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
        // $miArrayDeObjetos = [
        //     (object) ["codigo" => "SMLL3-Y84W9MP666"],
        //     (object) ["codigo" => "PSMLL3-HFRTCYT"],
        //     (object) ["codigo" => "SMLL3-PU2WSDV"],
        //     (object) ["codigo" => "PSMLL3-PV53YWA"],
        // ];
        // $usuario_editor     = 463;
        // $comentario         = "HOLA MUNDO";
        // $contador           = 0;
        // $getLongitud        = sizeof($miArrayDeObjetos);
        // $longitud           = $getLongitud/2;
        // $TipoVenta          = 1;
        // $institucion_id     = 981;
        // $periodo_id         = 22;
        // // Supongamos que tienes una colección vacía
        // $codigoNoExiste     = collect();
        // $codigoConProblemas = collect();
        // for($i = 0; $i<$longitud; $i++){
        //     // Creamos un nuevo array para almacenar los objetos quitados
        //     $nuevoArray             = [];
        //     $codigoActivacion       = "";
        //     $codigoDiagnostico      = "";
        //     $validarA               = [];
        //     $validarD               = [];
        //     // Eliminamos los dos primeros objetos del array original y los agregamos al nuevo array
        //     $nuevoArray[]           = array_shift($miArrayDeObjetos);
        //     $nuevoArray[]           = array_shift($miArrayDeObjetos);
        //     $codigoActivacion       = $nuevoArray[0]->codigo;
        //     $codigoDiagnostico      = $nuevoArray[1]->codigo;
        //     //===CODIGO DE ACTIVACION====
        //     //validacion
        //     $validarA               = $this->getCodigos($codigoActivacion,0);
        //     $validarD               = $this->getCodigos($codigoDiagnostico,0);
        //     //======si ambos codigos existen========
        //     if(count($validarA) > 0 && count($validarD) > 0){
        //         //====VARIABLES DE CODIGOS===
        //         //====Activacion=====
        //         //validar si el codigo ya esta liquidado
        //         $ifLiquidadoA                = $validarA[0]->estado_liquidacion;
        //         //validar si el codigo no este liquidado
        //         $ifBloqueadoA                = $validarA[0]->estado;
        //         //validar si tiene bc_institucion
        //         $ifBc_InstitucionA           = $validarA[0]->bc_institucion;
        //         //validar que el periodo del estudiante sea 0 o sea igual al que se envia
        //         $ifid_periodoA               = $validarA[0]->id_periodo;  
        //         //validar si el codigo tiene venta_estado 
        //         $venta_estadoA               = $validarA[0]->venta_estado;
        //         //venta lista
        //         $ifventa_lista_institucionA  = $validarA[0]->venta_lista_institucion;
        //         //======Diagnostico=====
        //         //validar si el codigo ya esta liquidado
        //         $ifLiquidadoD                = $validarD[0]->estado_liquidacion;
        //         //validar si el codigo no este liquidado
        //         $ifBloqueadoD                = $validarD[0]->estado;
        //         //validar si tiene bc_institucion
        //         $ifBc_InstitucionD           = $validarD[0]->bc_institucion;
        //         //validar que el periodo del estudiante sea 0 o sea igual al que se envia
        //         $ifid_periodoD               = $validarD[0]->id_periodo;  
        //         //validar si el codigo tiene venta_estado 
        //         $venta_estadoD               = $validarD[0]->venta_estado;
        //         //venta lista
        //         $ifventa_lista_institucionD  = $validarD[0]->venta_lista_institucion;
        //         //===VENTA DIRECTA====
        //         if($TipoVenta == 1){
        //             if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "") && ( $ifBc_InstitucionA == 0 || $ifBc_InstitucionA == $institucion_id )   && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 && ($venta_estadoA == 0  || $venta_estadoA == null || $venta_estadoA == "null")){ 
        //                 if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ( $ifBc_InstitucionD == 0 || $ifBc_InstitucionD == $institucion_id )   && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && ($venta_estadoD == 0  || $venta_estadoD == null || $venta_estadoD == "null")){ 
        //                     //Ingresar Union a codigo de activacion
        //                    $old_valuesA = CodigosLibros::Where('codigo',$codigoActivacion)->get(); 
        //                    $codigoA     =  $this->UpdateCodigo($codigoActivacion,$codigoDiagnostico,$TipoVenta);
        //                    if($codigoA){  $contador++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA); }
        //                    //Ingresar Union a codigo de prueba diagnostico
        //                    $old_valuesD = CodigosLibros::findOrFail($codigoDiagnostico);
        //                    $codigoB = $this->UpdateCodigo($codigoDiagnostico,$codigoActivacion,$TipoVenta);
        //                    if($codigoB){  $contador++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD); }
        //                 }else{
        //                     $codigoConProblemas->push($validarD);
        //                 } 
        //             }else{
        //                 $codigoConProblemas->push($validarA);
        //             } 
        //         }
        //         if($TipoVenta == 2){
        //             if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "") && ($venta_estadoA == 0  || $venta_estadoA == null || $venta_estadoA == "null") && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 && $ifventa_lista_institucionA == '0'){  
        //                 if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ($venta_estadoD == 0  || $venta_estadoD == null || $venta_estadoD == "null") && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && $ifventa_lista_institucionD == '0'){  
        //                     //Ingresar Union a codigo de activacion
        //                     $old_valuesA    = CodigosLibros::findOrFail($codigoActivacion);
        //                     $codigoA        =  $this->UpdateCodigo($codigoActivacion,$codigoDiagnostico,$TipoVenta);
        //                     if($codigoA){  $contador++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoActivacion,$usuario_editor,$comentario,$old_valuesA); }
        //                     //Ingresar Union a codigo de prueba diagnostico
        //                     $old_valuesD    = CodigosLibros::findOrFail($codigoDiagnostico);
        //                     $codigoB        = $this->UpdateCodigo($codigoDiagnostico,$codigoActivacion,$TipoVenta);
        //                     if($codigoB){  $contador++; $this->GuardarEnHistorico(0,$institucion_id,$periodo_id,$codigoDiagnostico,$usuario_editor,$comentario,$old_valuesD); }
        //                 }else{
        //                     $codigoConProblemas->push($validarD);
        //                 }
        //             }
        //             else{
        //                 $codigoConProblemas->push($validarA);
        //             } 
        //         }
        //     }
        //     //Si uno de los 2 codigos no existen
        //     else{
        //         //si no existe el codigo de activacion
        //         if(count($validarA) == 0 && count($validarD) > 0){
        //             $codigoNoExiste->push(['codigoNoExiste' => "activacion", 'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
        //         }
        //         //si no existe el codigo de diagnostico
        //         if(count($validarD) == 0 && count($validarA) > 0){
        //             $codigoNoExiste->push(['codigoNoExiste' => "diagnostico",'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
        //         }
        //         //si no existe ambos
        //         if(count($validarA) == 0 && count($validarD) == 0){
        //             $codigoNoExiste->push(['codigoNoExiste' => "ambos",      'codigoActivacion' => $codigoActivacion, 'codigoDiagnostico' => $codigoDiagnostico]);
        //         }
        //     }
        // }
        // return [
        //     "codigoNoExiste"        => $codigoNoExiste->all(),
        //     "codigoConProblemas"    => array_merge(...$codigoConProblemas->all()),
        //     "cambiados"             => $contador,
        // ];
        // Ahora, $nuevoArray contiene los dos primeros objetos
        // y $miArrayDeObjetos contiene los objetos restantes

        // Imprimimos los objetos del nuevo array
        // foreach ($nuevoArray as $objeto) {
        //     echo $objeto->codigo ."<br>";
        // }

        // Imprimimos los objetos del array original (los restantes)
        // foreach ($miArrayDeObjetos as $objeto) {
        //     echo $objeto->codigo."<br>";
        // }
        // $json = '
        //     [
        //         {
        //         "venCodigo": "C-C22-0000030-LJ",
        //         "docCodigo": 18834,
        //         "docValor": 1200,
        //         "docNumero": "CH13374, EG54042",
        //         "docNombre": "PRISCILA MARIANA LOPEZ ORDOÑEZ",
        //         "docCi": "ANT",
        //         "docCuenta": null,
        //         "docInstitucion": null,
        //         "docTipo": null,
        //         "docObservacion": null,
        //         "docFecha": null,
        //         "estVenCodigo": 4,
        //         "venConvertido": ""
        //         },
        //         {
        //         "venCodigo": "C-C22-0000030-LJ",
        //         "docCodigo": 19317,
        //         "docValor": 252.8,
        //         "docNumero": "CH 13793; EG 55655; FACT 13634",
        //         "docNombre": "PRISCILA MARIANA LOPEZ ORDOÑEZ",
        //         "docCi": "LIQ",
        //         "docCuenta": null,
        //         "docInstitucion": null,
        //         "docTipo": null,
        //         "docObservacion": null,
        //         "docFecha": null,
        //         "estVenCodigo": 4,
        //         "venConvertido": ""
        //         }
        //     ]
        // ';
        // // Convertir la cadena JSON a un array de objetos
        // $arrayObjetos = json_decode($json);
        // return $arrayObjetos;
        // //variables
        // $contrato = "C-C20-0000008-LJ";
        // try {
        //     $dataFinally    = [];
        //     $dato = Http::get("http://186.46.24.108:9095/api/Contrato/".$contrato);
        //     $JsonContrato = json_decode($dato, true);
        //     if($JsonContrato == "" || $JsonContrato == null){
        //         return ["status" => "0", "message" => "No existe el contrato en facturación"];
        //     }
        //     $covertido      = $JsonContrato["veN_CONVERTIDO"];
        //     $estado         = $JsonContrato["esT_VEN_CODIGO"];
        //     if($estado != 3 && !str_starts_with($covertido , 'C')){
        //         //===PROCESO======
        //         $dato2 = Http::get("http://186.46.24.108:9095/api/f_DocumentoLiq/Get_docliq_venta_x_vencod?venCodigo=".$contrato);
        //         $JsonDocumentos = json_decode($dato2, true);
        //         return $JsonDocumentos;
        //     }else{
        //         // return $dataFinally;
        //         return ["status" => "0", "message" => "El contrato $contrato esta anulado o pertenece a un ven_convertido"];
        //     }

        // } catch (\Exception  $ex) {
        // return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        // }
    }
    public function GuardarEnHistorico ($id_usuario,$institucion_id,$periodo_id,$codigo,$usuario_editor,$comentario,$old_values){
        $historico = new HistoricoCodigos();
        $historico->id_usuario     =  $id_usuario;
        $historico->usuario_editor =  $institucion_id;
        $historico->id_periodo     =  $periodo_id;
        $historico->codigo_libro   =  $codigo;
        $historico->idInstitucion  =  $usuario_editor;
        $historico->observacion    =  $comentario;
        $historico->old_values     =  $old_values;
        $historico->save();
     }

    public function saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,$cantidad_anterior,$nueva_cantidad,$user_created,$tipo){
        //vadidate that it's not exists
        $query = DB::SELECT("SELECT * FROM pedidos_alcance_historico h
        WHERE h.alcance_id = '$id_alcance'
        AND h.id_pedido ='$id_pedido'");
        if(empty($query)){
            $historico                      = new PedidoAlcanceHistorico();
            $historico->contrato            = $contrato;
            $historico->id_pedido           = $id_pedido;
            $historico->alcance_id          = $id_alcance;
            $historico->cantidad_anterior   = $cantidad_anterior;
            $historico->nueva_cantidad      = $nueva_cantidad;
            $historico->user_created        = $user_created;
            $historico->tipo                = $tipo;
            $historico->save();
        }
    }
    public function get_val_pedidoInfo_alcance($pedido,$alcance){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
        p.descuento, p.id_periodo,
        p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie
        FROM pedidos_val_area pv
        left join area ar ON  pv.id_area = ar.idarea
        left join series se ON pv.id_serie = se.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        AND pv.alcance = '$alcance'
        GROUP BY pv.id;
        ");
        $datos = [];
        foreach($val_pedido as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->plan_lector'
                    AND f.id_periodo = '$item->id_periodo'
                )as precio, ls.codigo_liquidacion,ls.version,ls.year
                FROM libro l
                left join libros_series ls  on ls.idLibro = l.idlibro
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$key] = [
                "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $valores[0]->year,
                "version"           => $valores[0]->version,
                "created_at"        => $item->created_at,
                "updated_at"        => $item->updated_at,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "idlibro"           => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
            ];
        }
        return $datos;
    }
    public function traerPeriodo($institucion_id){
        $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo , periodoescolar AS descripcion FROM periodoescolar WHERE idperiodoescolar = (
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion_id'))
        ");
        if(count($periodoInstitucion)>0){
            return ["status" => "1", "message"=>"correcto","periodo" => $periodoInstitucion];
        }else{
            return ["status" => "0", "message"=>"no hay periodo"];
        }
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }


    public function guardarData(Request $request){
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);
        //pasar los capacitadores
        $query = DB::SELECT("SELECT * FROM seminarios s
        WHERE s.tipo_webinar = '2'
        AND s.capacitador_id IS NOT NULL
        and s.estado = '1'
        ");
        $contador = 0;
        foreach($query as $key => $item){
            //validar que el no este registrado
            $validate = DB::SELECT("SELECT * FROM seminarios_capacitador
            WHERE seminario_id = '$item->id_seminario'
            AND idusuario = '$item->capacitador_id'");
            if(empty($validate)){
                $capacitador = new SeminarioCapacitador();
                $capacitador->idusuario      = $item->capacitador_id;
                $capacitador->seminario_id   = $item->id_seminario;
                $capacitador->save();
                $contador++;
            }
        }
        return "Se guardo $contador";
        // $query = DB::SELECT("SELECT * FROM temporadas t
        // WHERE t.temporal_institucion IS NULL
        // AND t.year = '2023'
        // AND t.id_periodo <> '20'
        // and t.estado  = '1'
        // ");
        // $contador = 0;
        // foreach($query as $key => $item){
        //     $contrato = $item->contrato;
        //     //validar que el contrato exista en pedidos
        //     $validate = DB::SELECT("SELECT p.id_periodo, p.id_institucion,p.id_asesor,
        //     pe.periodoescolar as periodo,
        //         CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
        //         u.cedula AS cedulaAsesor,
        //         CONCAT(ur.nombres, ' ', ur.apellidos) AS nombreDocente,
        //         ur.cedula AS cedulaDocente,
        //         i.nombreInstitucion, c.nombre AS ciudad,
        //         SUBSTRING(pe.codigo_contrato, 1,1 ) AS temporada
        //         FROM pedidos p
        //         LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        //         LEFT JOIN usuario ur ON p.id_responsable = ur.idusuario
        //         LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        //         LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        //         LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        //         WHERE contrato_generado = '$contrato'
        //         and p.estado = '1'
        //         LIMIT 1
        //     ");
        //     if(sizeOf($validate) > 0){

        //         $id_institucion     = $validate[0]->id_institucion;
        //         $asesor_id          = $validate[0]->id_asesor;
        //         $temporada          = $validate[0]->temporada;
        //         $periodo            = $validate[0]->id_periodo;
        //         $ciudad             = $validate[0]->ciudad;
        //         $asesor             = $validate[0]->asesor;
        //         $cedulaAsesor       = $validate[0]->cedulaAsesor;
        //         $nombreDocente      = $validate[0]->nombreDocente;
        //         $cedulaDocente      = $validate[0]->cedulaDocente;
        //         $nombreInstitucion  = $validate[0]->nombreInstitucion;
        //         $this->guardarContratoTemporada($contrato,$id_institucion,$asesor_id,$temporada,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion);
        //         $contador ++;
        //     }

        // }
        // return "se guardo $contador correctamente";
    }
    public function crearCapacitadores($request,$arreglo){
        $datos = json_decode($request->capacitadores);
        //eliminar si ya han quitado al capacitador
        $getCapacitadores = $this->getCapacitadoresXCapacitacion($arreglo->id_seminario);
        if(sizeOf($getCapacitadores) > 0){
            foreach($getCapacitadores as $key => $item){
                $capacitador        = "";
                $capacitador        = $item->idusuario;
                $searchCapacitador  = collect($datos)->filter(function ($objeto) use ($capacitador) {
                    // Condición de filtro
                    return $objeto->idusuario == $capacitador;
                });
                if(sizeOf($searchCapacitador) == 0){
                    DB::DELETE("DELETE FROM seminarios_capacitador
                      WHERE seminario_id = '$arreglo->id_seminario'
                      AND idusuario = '$capacitador'
                    ");
                }
            }
        }
        //guardar los capacitadores
        foreach($datos as $key => $item){
            $query = DB::SELECT("SELECT * FROM seminarios_capacitador c
            WHERE c.idusuario = '$item->idusuario'
            AND c.seminario_id = '$arreglo->id_seminario'");
            if(empty($query)){
                $capacitador = new SeminarioCapacitador();
                $capacitador->idusuario      = $item->idusuario;
                $capacitador->seminario_id   = $arreglo->id_seminario;
                $capacitador->save();
            }
        }
    }

    public function guardarContratoTemporada($contrato,$institucion,$asesor_id,$temporadas,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion){
        //validar que el contrato no existe
        $validate = DB::SELECT("SELECT * FROM temporadas t
        WHERE t.contrato = '$contrato'
        ");
        if(empty($validate)){
            $temporada = new Temporada();
            $temporada->contrato                = $contrato;
            $temporada->year                    = date("Y");
            $temporada->ciudad                  = $ciudad;
            $temporada->temporada               = $temporadas;
            $temporada->id_asesor               = $asesor_id;
            $temporada->cedula_asesor           = 0;
            $temporada->id_periodo              = $periodo;
            $temporada->id_profesor             = "0";
            $temporada->idInstitucion           = $institucion;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }else{
            $id_temporada                       = $validate[0]->id_temporada;
            $temporada                          = Temporada::findOrFail($id_temporada);
            $temporada->id_periodo              = $periodo;
            $temporada->idInstitucion           = $institucion;
            $temporada->id_asesor               = $asesor_id;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function show(Admin $admin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function edit(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Admin $admin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function destroy(Admin $admin)
    {
        //
    }

    // Consultas para administrador
    public function cant_user(){
        $cantidad = DB::SELECT("SELECT id_group, COUNT(id_group) as cantidad FROM usuario WHERE estado_idEstado =1  GROUP BY id_group");
        return $cantidad;
    }
    public function cant_cursos(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM curso  GROUP BY estado");
        return $cantidad;
    }
    public function cant_codigos(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM codigoslibros WHERE idusuario > 0");
        return $cantidad;
    }
    public function cant_codigostotal(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM codigoslibros");
        return $cantidad;
    }
    public function cant_evaluaciones(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM evaluaciones  GROUP BY estado");
        return $cantidad;
    }
    public function cant_preguntas(){
        $cantidad = DB::SELECT("SELECT id_tipo_pregunta, COUNT(id_tipo_pregunta) as cantidad FROM preguntas  GROUP BY id_tipo_pregunta");
        return $cantidad;
    }
    public function cant_multimedia(){
        $cantidad = DB::SELECT("SELECT tipo, COUNT(tipo) as cantidad FROM actividades_animaciones  GROUP BY tipo");
        return $cantidad;
    }
    public function cant_juegos(){
        // $cantidad = DB::SELECT("SELECT jj.id_tipo_juego, COUNT(jj.id_tipo_juego) as cantidad , jt.nombre_tipo_juego FROM j_juegos jj INNER JOIN j_tipos_juegos jt ON jj.id_tipo_juego = jt.id_tipo_juego GROUP BY jt.id_tipo_juego GROUP BY jj.id_tipo_juego");

        $cantidad = DB::table('j_juegos')
        ->join('j_tipos_juegos', 'j_tipos_juegos.id_tipo_juego','=','j_juegos.id_tipo_juego')
        ->select('j_tipos_juegos.nombre_tipo_juego', DB::raw('count(*) as cantidad'))
        ->groupBy('j_tipos_juegos.nombre_tipo_juego')
        ->get();
        return $cantidad;
    }
    public function cant_seminarios(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM seminario  WHERE estado=1");
        return $cantidad;
    }
    public function cant_encuestas(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM encuestas_certificados");
        return $cantidad;
    }
    public function cant_institucion(){
        $cantidad = DB::SELECT("SELECT DISTINCT COUNT(*) FROM institucion i, periodoescolar p, periodoescolar_has_institucion pi WHERE  i.idInstitucion = pi.institucion_idInstitucion AND pi.periodoescolar_idperiodoescolar = p.idperiodoescolar AND p.estado = 1 GROUP BY i.region_idregion");
        return $cantidad;
    }

    public function get_periodos_activos(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p WHERE p.estado = '1' ORDER BY p.idperiodoescolar DESC;");
        return $periodos;
    }
    public function get_periodos_pedidos(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p ORDER BY p.idperiodoescolar DESC  ");
        return $periodos;
    }

    public function get_asesores(){
        $asesores = DB::SELECT("SELECT `idusuario`, CONCAT(`nombres`,' ',`apellidos`) AS nombres, `cedula` FROM `usuario` WHERE `estado_idEstado` = '1' AND `id_group` = '5';");
        return $asesores;
    }

    public function reporte_asesores(){

        $fecha_fin    = date("Y-m-d");
        $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 7 days"));

        $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");

        $email = 'mcalderonmediavilla@hotmail.com';
        $emailCC = 'reyesjorge10@gmail.com';
        $reporte = 'Reporte asesores';

        $envio = Mail::send('plantilla.reporte_asesores',
            [
                'fecha_fin'    => $fecha_fin,
                'fecha_inicio' => $fecha_inicio,
                'agendas'      => $agendas,
            ],
            function ($message) use ($email, $emailCC, $reporte) {
                $message->from('reportesgerencia@prolipadigital.com.ec', $reporte);
                $message->to($email)->bcc($emailCC)->subject('Agenda de asesores');
            }
        );
    }

    // public function reporte_asesores_view($periodo, $fecha_inicio, $fecha_fin){

    //     if( $periodo != 'null' ){
    //         $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND p.idperiodoescolar = $periodo ORDER BY u.cedula;");
    //     }else{
    //         if( $fecha_inicio != 'null' ){
    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }else{

    //             $fecha_fin    = date("Y-m-d");
    //             $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }
    //     }

    //     return $agendas;

    // }


    public function reporte_asesores_view($fecha_inicio, $fecha_fin,$periodo){

        // if( $periodo != 'null' ){
        //     $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.periodo_id = $periodo order BY u.nombres");
        // }else{
        //     if( $fecha_inicio != 'null' ){
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }else{
        //         $fecha_fin    = date("Y-m-d");
        //         $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }
        // }
        if( $periodo != 'null' ){
            $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucionxPeriodo`('$periodo');
                ");
            $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporalxPeriodo('$periodo')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                 ];
        }else{
            if($fecha_inicio != 'null' ){
                $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucion`('$fecha_inicio','$fecha_fin');
                ");
                $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporal('$fecha_inicio', '$fecha_fin')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                ];
            }
        }

        return $agendas;

    }

    public function get_estadisticas_asesor_inst($periodo, $fecha_inicio, $fecha_fin){

        if( $periodo != 'null' ){
            $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
            FROM seguimiento_cliente s
            INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
            WHERE s.periodo_id = $periodo AND s.fecha_genera_visita
            GROUP BY i.idInstitucion;");
        }else{
            if( $fecha_inicio != 'null' ){
                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }else{

                $fecha_fin    = date("Y-m-d");
                $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }
        }

        return $visitas;

    }


}
