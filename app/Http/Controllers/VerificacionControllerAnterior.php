<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HistoricoCodigos;
use App\Models\TemporadaVerificacionHistorico;
use App\Models\Verificacion;
use App\Models\VerificacionHasInstitucion;
use Illuminate\Http\Request;
use DB;

class VerificacionControllerAnterior extends Controller
{


    //PARA TRAER EL CONTRATO POR NUMERO DE VERIFICACION
    public function liquidacionVerificacionNumero($contrato,$numero){
        if($numero == "regalados"){
            return $this->CodigosRegalos($contrato);
        }
        //validar si el contrato esta activo
        $validarContrato = DB::select("SELECT t.*
        FROM temporadas t
        WHERE t.contrato = '$contrato'
        and t.estado = '0'
        ");
        if(count($validarContrato) > 0){
            return ["status"=>"0", "message" => "El contrato esta inactivo"];
        }
            $traerInformacion = DB::select(" SELECT   vt.verificacion_id as numero_verificacion,vt.contrato,vt.codigo,vt.cantidad,
            vt.nombre_libro, v.fecha_inicio, v.fecha_fin, vt.contrato
            FROM verificaciones v
            LEFT JOIN verificaciones_has_temporadas vt ON v.num_verificacion = vt.verificacion_id
            WHERE v.num_verificacion ='$numero'
            AND v.contrato = '$contrato'
            AND vt.verificacion_id = '$numero'
            AND vt.contrato = '$contrato'
            and vt.estado = '1'
            and v.nuevo = '1'
            and vt.nuevo = '1'
            ORDER BY vt.verificacion_id desc
            ");

            if(empty($traerInformacion)){
                return ["status"=>"0","message"=>"No se encontro datos para este  contrato"];
            }else{
                return $traerInformacion;
            }
    }
    //para codigo regalados
    public function CodigosRegalos($contrato){
        $regalados = DB::SELECT("SELECT ls.codigo_liquidacion AS codigo,  COUNT(ls.codigo_liquidacion) AS cantidad, c.serie,
        c.libro_idlibro,ls.nombre as nombrelibro
            FROM codigoslibros c
            LEFT JOIN usuario u ON c.idusuario = u.idusuario
            LEFT JOIN  libros_series ls ON ls.idLibro = c.libro_idlibro
            WHERE c.estado_liquidacion = '2'
               AND c.contrato = '$contrato'
           AND ls.idLibro = c.libro_idlibro
           GROUP BY ls.codigo_liquidacion,ls.nombre, c.serie,c.libro_idlibro
        ");
        if(empty($regalados)){
            return ["status" => "0","message" => "No hay codigos regalados"];
        }else{
            return $regalados;
        }

    }
     //PARA GUARDAR LA LIQUIDACION

     public function liquidacionVerificacion($contrato){
        set_time_limit(0);
        //validar si el contrato esta activo
        $validarContrato = DB::select("SELECT t.*
        FROM temporadas t
        WHERE t.contrato = '$contrato'
        ");
        if(empty($validarContrato)){
            return ["status" => "0", "message" => "No existe el contrato"];
        }
        $estado = $validarContrato[0]->estado;
        $year   = $validarContrato[0]->year;
        if($estado == '0'){
            return ["status" => "0", "message" => "El contrato esta inactivo"];
        }
        if($year > 2022){
            //validar que el contrato este en pedidos
            $query = DB::SELECT("SELECT * FROM pedidos p
            WHERE p.contrato_generado = '$contrato'
            AND p.estado = '1'
            ");
            if(empty($query)){
                return ["status" => "0", "message" => "El contrato no se encuentra en pedidos"];
            }
            $id_pedido = $query[0]->id_pedido;
            //validar que el pedido no tenga alcaces abiertos o activos
            $query2 = DB::SELECT("SELECT * FROM pedidos_alcance pa
            WHERE pa.id_pedido = '$id_pedido'
            AND pa.estado_alcance = '0'
            ");
            if(count($query2) > 0){
                return ["status"=>"0", "message" => "El contrato tiene alcances abiertos"];
            }
        }
        $buscarContrato= DB::select("SELECT t.*, p.idperiodoescolar
        FROM temporadas t, periodoescolar p
        WHERE t.id_periodo = p.idperiodoescolar
        AND contrato = '$contrato'
        ");
        if(count($buscarContrato) <= 0){
            return ["status"=>"0", "message" => "No existe el contrato o no tiene asignado a un período"];
        }else{
            //almacenar el id de la institucion
            $institucion = $buscarContrato[0]->idInstitucion;
            //almancenar el periodo
            $periodo =  $buscarContrato[0]->idperiodoescolar;
            //traer temporadas
            $temporadas= $buscarContrato;
            //traigo la liquidacion actual por cantidad
            $data = DB::SELECT("SELECT ls.codigo_liquidacion AS codigo,  COUNT(ls.codigo_liquidacion) AS cantidad, c.serie,
            c.libro_idlibro,ls.nombre as nombrelibro
                FROM codigoslibros c
                LEFT JOIN usuario u ON c.idusuario = u.idusuario
                LEFT JOIN  libros_series ls ON ls.idLibro = c.libro_idlibro
                WHERE c.bc_estado = '2'
                   AND c.estado <> 2
                   and c.estado_liquidacion = '1'
                   AND c.bc_periodo  = '$periodo'
                   AND c.bc_institucion = '$institucion'
                   AND c.prueba_diagnostica = '0'
               AND ls.idLibro = c.libro_idlibro
               GROUP BY ls.codigo_liquidacion,ls.nombre, c.serie,c.libro_idlibro
            ");
            //INVIVIDUAL VERSION 1
            //traigo la liquidacion  con los codigos invidivuales
            $traerCodigosIndividual = DB::SELECT("SELECT c.codigo, ls.codigo_liquidacion,   c.serie,
                c.libro_idlibro,c.libro as nombrelibro
               FROM codigoslibros c
               LEFT JOIN usuario u ON c.idusuario = u.idusuario
               LEFT JOIN  libros_series ls ON ls.idLibro = c.libro_idlibro
               WHERE c.bc_estado = '2'
               AND c.estado <> 2
               and c.estado_liquidacion = '1'
               AND c.bc_periodo  = '$periodo'
               AND c.bc_institucion = '$institucion'
               AND ls.idLibro = c.libro_idlibro
            ");
            //SI TODO HA SALIDO BIEN TRAEMOS LA DATA
            if(count($data) >0){
                //obtener la fecha actual
                $fechaActual  = date('Y-m-d');
                //verificar si es el primer contrato
                $vericacionContrato = $this->getVerificacionXcontrato($contrato);
                //======PARA REALIZAR LA VERIFICACION EN CASO QUE EL CONTRATO YA TENGA VERIFICACIONES====
                if(count($vericacionContrato) > 0){
                    //obtener el numero de verificacion en el que se quedo el contrato
                    $traerNumeroVerificacion =  $vericacionContrato[0]->num_verificacion;
                    $traeridVerificacion     =  $vericacionContrato[0]->id;
                    //Para guardar la verificacion si  existe el contrato
                    //SI EXCEDE LAS 10 VERIFICACIONES
                    $finVerificacion="no";
                    if($traerNumeroVerificacion >10){
                        $finVerificacion = "yes";
                    }
                    else{
                        //OBTENER LA CANTIDAD DE LA VERIFICACION ACTUAL
                        $this->updateCodigoIndividualInicial($traeridVerificacion,$traerCodigosIndividual,$contrato,$traerNumeroVerificacion,$periodo,$institucion);
                        //Ingresar la liquidacion en la base
                        $this->guardarLiquidacion($data,$traerNumeroVerificacion,$contrato);
                        //Actualizo a estado 0 la verificacion anterior
                        DB::table('verificaciones')
                        ->where('id', $traeridVerificacion)
                        ->update([
                            'fecha_fin' => $fechaActual,
                            'estado' => "0"
                        ]);
                        //  Para generar una verficacion y que quede abierta
                        $this->saveVerificacion($traerNumeroVerificacion+1,$contrato);
                    }
                }else{
                    //=====PARA GUARDAR LA VERIFICACION SI EL CONTRATO AUN NO TIENE VERIFICACIONES======
                    //para indicar que aun no existe el fin de la verificacion
                    $finVerificacion = "0";
                    //Para guardar la primera verificacion en la tabla
                    $verificacion =  new Verificacion;
                    $verificacion->num_verificacion = 1;
                    $verificacion->fecha_inicio     = $fechaActual;
                    $verificacion->fecha_fin        = $fechaActual;
                    $verificacion->contrato         = $contrato;
                    $verificacion->estado           = "0";
                    $verificacion->nuevo            = '1';
                    $verificacion->save();
                    //Obtener Verificacion actual
                    $encontrarVerificacionContratoInicial = $this->getVerificacionXcontrato($contrato);
                    //obtener el numero de verificacion en el que se quedo el contrato
                    $traerNumeroVerificacionInicial     =  $encontrarVerificacionContratoInicial[0]->num_verificacion;
                    //obtener la clave primaria de la verificacion actual
                    $traerNumeroVerificacionInicialId   = $encontrarVerificacionContratoInicial[0]->id;
                    //Actualizar cada codigo de la verificacion
                    $this->updateCodigoIndividualInicial($traerNumeroVerificacionInicialId,$traerCodigosIndividual,$contrato,$traerNumeroVerificacionInicial,$periodo,$institucion);
                    //Ingresar la liquidacion en la base
                    $this->guardarLiquidacion($data,$traerNumeroVerificacionInicial,$contrato);
                    //Para generar la siguiente verificacion y quede abierta
                    $this->saveVerificacion($traerNumeroVerificacionInicial+1,$contrato);
                }
                if($finVerificacion =="yes"){
                    return [
                        "verificaciones"=>"Ha alzancado el limite de verificaciones permitidas",
                        'temporada'=>$temporadas,
                        'codigos_libros' => $data
                    ];
                }else{
                    $fecha2 = date('Y-m-d H:i:s');
                    DB::UPDATE("UPDATE pedidos SET estado_verificacion = '0' , fecha_solicita_verificacion = null WHERE contrato_generado = '$contrato'");
                    DB::UPDATE("UPDATE temporadas_verificacion_historico SET estado = '2', fecha_realiza_verificacion = '$fecha2' WHERE contrato = '$contrato' AND estado = '1'");
                    return ['temporada'=>$temporadas,'codigos_libros' => $data];
                }
            }else{
                return ["status"=>"0", "message" => "No existe NUEVOS VALORES para guardar la verificación"];
            }
        }
    }
    public function getVerificacionXcontrato($contrato){
        $query = DB::SELECT("SELECT
            * FROM verificaciones
            WHERE contrato = '$contrato'
            AND nuevo = '1'
            ORDER BY id DESC
        ");
        return $query;
    }
    public function saveVerificacion($num_verificacion,$contrato){
        $fechaActual  = date('Y-m-d');
        $verificacion =  new Verificacion;
        $verificacion->num_verificacion = $num_verificacion;
        $verificacion->fecha_inicio     = $fechaActual;
        $verificacion->contrato         = $contrato;
        $verificacion->nuevo            = "1";
        $verificacion->save();
    }
    public function guardarLiquidacion($data,$traerNumeroVerificacionInicial,$traerContrato){
        //Ingresar la liquidacion
        foreach($data as $item){
            VerificacionHasInstitucion::create([
                'verificacion_id' => $traerNumeroVerificacionInicial,
                'contrato' => $traerContrato,
                'codigo' => $item->codigo,
                'cantidad' => $item->cantidad,
                'nombre_libro' => $item->nombrelibro,
                'estado' => '1',
                'nuevo' => '1'
            ]);

        }
     }
     public function updateCodigoIndividualInicial($traerNumeroVerificacionInicialId,$traerCodigosIndividual,$contrato,$num_verificacion,$periodo,$institucion){
        $columnaVerificacion = "verif".$num_verificacion;
        //PARA RECORRER Y IR ACTUALIZANDO A CADA CODIGO LA VERIFICACION
        foreach($traerCodigosIndividual as $item){
           $ingresar =  DB::table('codigoslibros')
            ->where('codigo', $item->codigo)
            ->update([
                $columnaVerificacion =>  $traerNumeroVerificacionInicialId,
                'estado_liquidacion' => "0",
                'contrato' => $contrato,
            ]);
            if($ingresar){
                $historico = new HistoricoCodigos();
                $historico->id_usuario = "0";
                $historico->codigo_libro = $item->codigo;
                $historico->usuario_editor = $institucion;
                $historico->id_periodo = $periodo;
                $historico->observacion = "liquidacion";
                $historico->contrato_actual = $contrato;
                $historico->save();
            }

        }
     }

     public function index(Request $request)
     {
         set_time_limit(60000);
         ini_set('max_execution_time', 60000);

         //PARA VER EL CONTRATO POR CODIGO

         if($request->id){
             $buscarContrato = DB::select("SELECT
             v.* from verificaciones v
             WHERE v.id = '$request->id'
             ");
              if(empty($buscarContrato)){
                 return ["status"=>"0","message"=>"No se encontro datos para este  contrato"];
             }else{
                 return $buscarContrato;
             }
         }

         //PARA VER LA INFORMACION DE LAS VERIFICACIONES DEL CONTRATO
         if($request->informacion){
            $verificaciones = $this->getVerificacionXcontrato($request->contrato);
            $institucion = DB::SELECT("SELECT t.*, i.nombreInstitucion
            FROM temporadas t
            LEFT JOIN institucion i ON i.idInstitucion = t.idInstitucion
            WHERE t.contrato = '$request->contrato'
            ");
            return ["verificaciones" => $verificaciones, "institucion" => $institucion];
         }
         //para ver el historico de contrato liquidacion
         if($request->historico){
            return $this->historicoContrato($request->contrato);
         }
         //para traer los detalle de cada verificacion
         if($request->detalles){
             $detalles = DB::SELECT("SELECT vl.* ,ls.idLibro AS libro_id,
             ls.id_serie,t.id_periodo,a.area_idarea
             FROM verificaciones_has_temporadas vl
             LEFT JOIN libros_series ls ON vl.codigo = ls.codigo_liquidacion
             LEFT JOIN libro l ON ls.idLibro = l.idlibro
             LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
             LEFT JOIN temporadas t ON vl.contrato = t.contrato
             WHERE vl.verificacion_id = '$request->verificacion_id'
             AND vl.contrato = '$request->contrato'
             AND vl.estado = '1'
             AND nuevo  = '1'
             ");
            $datos = [];
            $contador = 0;
            foreach($detalles as $key => $item){
                //plan lector
                $precio = 0;
                $query = [];
                if($item->id_serie == 6){
                    $query = DB::SELECT("SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->libro_id'
                    AND f.id_periodo = '$item->id_periodo'");
                }else{
                    $query = DB::SELECT("SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '$item->id_serie'
                    AND f.id_area = '$item->area_idarea'
                    AND f.id_periodo = '$item->id_periodo'
                    ");
                }
                if(count($query) > 0){
                    $precio = $query[0]->precio;
                }
                $datos[$contador] = [
                    "id_verificacion_inst"  => $item->id_verificacion_inst,
                    "verificacion_id"       => $item->verificacion_id,
                    "contrato"              => $item->contrato,
                    "codigo"                => $item->codigo,
                    "cantidad"              => $item->cantidad,
                    "nombre_libro"          => $item->nombre_libro,
                    "estado"                => $item->estado,
                    "desface"               => $item->desface,
                    "nuevo"                 => $item->nuevo,
                    "libro_id"              => $item->libro_id,
                    "id_serie"              => $item->id_serie,
                    "id_periodo"            => $item->id_periodo,
                    "precio"                => $precio,
                    "valor"                 => $item->cantidad * $precio
                ];
                $contador++;
            }
             return $datos;
         }
         //para ver los codigos de cada libro
         if($request->verCodigos){
            $verificacion_id        = 0;
            $columnaVerificacion    = 0;
            if($request->buscarIdVerificacion){
                $getId = DB::SELECT("SELECT * FROM verificaciones v
                WHERE v.contrato = '$request->contrato'
                AND v.nuevo = '1'
                AND v.num_verificacion = '$request->num_verificacion'
                ");
                $verificacion_id = $getId[0]->id;
            }else{
                $verificacion_id =  $request->verificacion_id;
            }
            $columnaVerificacion = "verif".$request->num_verificacion;
             $codigos = DB::table('codigoslibros')
             ->select('codigo')
             ->where($columnaVerificacion, $verificacion_id)
             ->where('contrato', $request->contrato)
             ->where('prueba_diagnostica', '0')
             ->where('libro_idlibro', $request->libro_id)
             ->get();
             return $codigos;

             }

     }

     //para cambiar la data de la liquidacion a la nueva
     public function changeLiquidacion(Request $request){

        if($request->datosTranspasar){
            $datos = DB::SELECT("SELECT * FROM verificaciones_has_temporadas vt
            WHERE vt.contrato = '$request->contrato'
            AND vt.nuevo = '$request->nuevo'
            AND vt.verificacion_id = '$request->num_verificacion'
            ");
            return $datos;
        }
        else{
            $change = DB::SELECT("SELECT * FROM verificaciones
            WHERE contrato = '$request->contrato'

            ");
            return $change;
        }


     }
    public function historicoContrato($contrato){
       $codigos = DB::SELECT("SELECT DISTINCT vl.codigo,l.nombrelibro as nombre_libro,ls.idLibro AS libro_id
       FROM verificaciones_has_temporadas vl
       LEFT JOIN libros_series ls ON vl.codigo = ls.codigo_liquidacion
       LEFT JOIN libro l ON l.idlibro = ls.idLibro
       WHERE vl.contrato = '$contrato'
       AND vl.nuevo = '1'
       AND vl.estado = '1'
       ");
       if(empty($codigos)){
        return 0;
       }else{
            $data = [];
            foreach($codigos as $key => $item){
                $codigo = DB::SELECT("SELECT id_verificacion_inst,verificacion_id,codigo,cantidad
                FROM verificaciones_has_temporadas
                WHERE contrato = '$contrato'
                AND nuevo = '1'
                AND codigo = '$item->codigo'
                ORDER BY verificacion_id ASC
                ");
                $data = $codigo;
                $cantidad = count($codigo);
                foreach($codigo as $k => $tr){
                    if($cantidad == 1){
                        $data2[$key] =[
                            "codigo"                            => $data[0]->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "total"                             => $data[0]->cantidad
                        ];
                    }
                    if($cantidad == 2){
                        $suma2 = $data[0]->cantidad+$data[1]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "total"                             => $suma2
                        ];
                    }
                    if($cantidad == 3){
                        $suma3 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "total"                             => $suma3
                        ];
                    }
                    if($cantidad == 4){
                        $suma4 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "total"                             => $suma4
                        ];
                    }
                    if($cantidad == 5){
                        $suma5 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "total"                             => $suma5
                        ];
                    }
                    if($cantidad == 6){
                        $suma6 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad+$data[5]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "verif".$data[5]->verificacion_id   => $data[5]->cantidad,
                            "total"                             => $suma6
                        ];
                    }
                    if($cantidad == 7){
                        $suma7 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad+$data[5]->cantidad+$data[6]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "verif".$data[5]->verificacion_id   => $data[5]->cantidad,
                            "verif".$data[6]->verificacion_id   => $data[6]->cantidad,
                            "total"                             => $suma7
                        ];
                    }
                    if($cantidad == 8){
                        $suma8 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad+$data[5]->cantidad+$data[6]->cantidad+$data[7]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "verif".$data[5]->verificacion_id   => $data[5]->cantidad,
                            "verif".$data[6]->verificacion_id   => $data[6]->cantidad,
                            "verif".$data[7]->verificacion_id   => $data[7]->cantidad,
                            "total"                             => $suma8
                        ];
                    }
                    if($cantidad == 9){
                        $suma9 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad+$data[5]->cantidad+$data[6]->cantidad+$data[7]->cantidad+$data[8]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "verif".$data[5]->verificacion_id   => $data[5]->cantidad,
                            "verif".$data[6]->verificacion_id   => $data[6]->cantidad,
                            "verif".$data[7]->verificacion_id   => $data[7]->cantidad,
                            "verif".$data[8]->verificacion_id   => $data[8]->cantidad,
                            "total"                             => $suma9
                        ];
                    }
                    if($cantidad == 10){
                        $suma10 = $data[0]->cantidad+$data[1]->cantidad+$data[2]->cantidad+$data[3]->cantidad+$data[4]->cantidad+$data[5]->cantidad+$data[6]->cantidad+$data[7]->cantidad+$data[8]->cantidad+$data[9]->cantidad;
                        $data2[$key] =[
                            "codigo" => $item->codigo,
                            "libro_id"                          => $item->libro_id,
                            "nombre_libro"                      => $item->nombre_libro,
                            "verif".$data[0]->verificacion_id   => $data[0]->cantidad,
                            "verif".$data[1]->verificacion_id   => $data[1]->cantidad,
                            "verif".$data[2]->verificacion_id   => $data[2]->cantidad,
                            "verif".$data[3]->verificacion_id   => $data[3]->cantidad,
                            "verif".$data[4]->verificacion_id   => $data[4]->cantidad,
                            "verif".$data[5]->verificacion_id   => $data[5]->cantidad,
                            "verif".$data[6]->verificacion_id   => $data[6]->cantidad,
                            "verif".$data[7]->verificacion_id   => $data[7]->cantidad,
                            "verif".$data[8]->verificacion_id   => $data[8]->cantidad,
                            "verif".$data[9]->verificacion_id   => $data[9]->cantidad,
                            "total"                             => $suma10
                        ];
                    }
                }
            }
            return $data2;
        }
    }
    //para crear una verificacion
    public function crearVerificacion(Request $request){
        //obtener la fecha actual
         $fechaActual  = date('Y-m-d');
        $vericacionContrato = DB::select("SELECT
        * FROM verificaciones
        WHERE contrato = '$request->contrato'
        AND nuevo = '1'
        ORDER BY id DESC
        ");
        //Si existe una verificacion
        if(count($vericacionContrato) >0){
              //obtener el numero de verificacion en el que se quedo el contrato
            $traerNumeroVerificacion =  $vericacionContrato[0]->num_verificacion;
            $traeridVerificacion     =  $vericacionContrato[0]->id;
           //Actualizo a estado 0 la verificacion anterior
           DB::table('verificaciones')
           ->where('id', $traeridVerificacion)
           ->update([
               'fecha_fin' => $fechaActual,
               'estado' => "0"
           ]);
           //  Para generar una verficacion y que quede abierta
           $verificacion =  new Verificacion;
           $verificacion->num_verificacion = $traerNumeroVerificacion+1;
           $verificacion->fecha_inicio = $fechaActual;
           $verificacion->contrato = $request->contrato;
           $verificacion->nuevo = '1';
           $verificacion->save();
        //si no existe una verificacion
        }else{
            $verificacion =  new Verificacion;
            $verificacion->num_verificacion = 1;
            $verificacion->fecha_inicio = $fechaActual;
            $verificacion->fecha_fin = $fechaActual;
            $verificacion->contrato = $request->contrato;
            $verificacion->estado = "0";
            $verificacion->nuevo = '1';
            $verificacion->save();

            $traerNumeroVerificacionInicial =  $verificacion->num_verificacion;
            //Para generar la siguiente verificacion y quede abierta
            $verificacion =  new Verificacion;
            $verificacion->num_verificacion = $traerNumeroVerificacionInicial+1;
            $verificacion->fecha_inicio = $fechaActual;
            $verificacion->contrato = $request->contrato;
            $verificacion->nuevo = "1";
            $verificacion->save();
        }

           if($verificacion){
               return ["status"=>"1","message"=>"Se agrego correctamente una nueva verificacion"];
           }else{
               return ["status"=>"1","message"=>"No se pudo agregar una nueva verificacion"];
           }
    }

    //para guardar los cambios  para cambiar de liquidacion de la  anterior a la nueva
    public function guardarChangeLiquidacion(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //change a estado nuevo = 1
            $this->actualizarLiquidacion($request->contrato,$request->num_verificacion_anterior,$request->num_verificacion_nueva,$request->nuevo);
        //actualizar cada codigo al actual id de liquidacion
            $buscarInstitucion= DB::select("SELECT idInstitucion,id_periodo
                FROM temporadas
                where contrato = '$request->contrato'
            ");
            if(empty($buscarInstitucion)){
                return ["status"=>"0","message"=>"No se encontro la institucion o no tiene periodo"];
            }
            $periodo = $buscarInstitucion[0]->id_periodo;
            if($periodo == ""){
                return ["status"=>"0","message"=>"Ingrese un periodo al contrato por favor"];
            }
            $institucion = $buscarInstitucion[0]->idInstitucion;
            $num_verificacion = 'verif'.$request->num_verificacion_anterior;
            $num_verificacionNueva = 'verif'.$request->num_verificacion_nueva;

        //guardar en el historico
            $this->guardarHistoricoLiquidacion($periodo,$request->contrato,$num_verificacion,$request->id_verificacion_anterior,$request->id_verificacion_nuevo,$request->usuario_editor,$institucion);

              //actualizar cada codigo a la nueva liquidacion
            DB::table('codigoslibros')
            ->where('id_periodo', $periodo)
            ->where('contrato', $request->contrato)
            ->where($num_verificacion, $request->id_verificacion_anterior)
            ->update([
                $num_verificacionNueva => $request->id_verificacion_nuevo,
            ]);

            if($num_verificacion == $num_verificacionNueva){

            }
            //vaciar la verificacion anterior
            else{
                DB::table('codigoslibros')
                ->where('id_periodo', $periodo)
                ->where('contrato', $request->contrato)
                ->where($num_verificacion, $request->id_verificacion_anterior)
                ->update([
                    $num_verificacion => '',
                ]);
            }
    }

    public function actualizarLiquidacion($contrato,$num_verificacion_anterior,$num_verificacion_nueva,$nuevo){
        DB::table('verificaciones_has_temporadas')
        ->where('contrato', $contrato)
        ->where('verificacion_id', $num_verificacion_anterior)
        ->where('nuevo', $nuevo)
        ->update([
            'nuevo' => '1',
            'verificacion_id' => $num_verificacion_nueva
        ]);
    }

    public function guardarHistoricoLiquidacion($periodo,$contrato,$num_verificacion,$id_verificacion_anterior,$id_verificacion_nuevo,$usuario_editor,$institucion){
        $codigos = DB::table('codigoslibros')
        ->where('id_periodo', $periodo)
        ->where('contrato', $contrato)
        ->where($num_verificacion, $id_verificacion_anterior)
        ->get();

        $mensaje = "Se cambio el id de la verificacion de ".$id_verificacion_anterior." a el id de verificacion ".$id_verificacion_nuevo;
        foreach($codigos as $key => $item){
            $historico = new HistoricoCodigos();
            $historico->id_usuario = $item->idusuario;
            $historico->codigo_libro = $item->codigo;
            $historico->idInstitucion = $usuario_editor;
            $historico->usuario_editor = $institucion;
            $historico->id_periodo = $periodo;
            $historico->observacion = $mensaje;
            $historico->contrato_actual = $contrato;
            $historico->save();
        }
    }
    //SOLICITAR VERIFICACION
    //api:post/solicitarVerificacion
    public function solicitarVerificacion(Request $request){
        $fechaActual = null;
        $fechaActual = date('Y-m-d H:i:s');
        DB::UPDATE("UPDATE pedidos SET estado_verificacion = '1' , fecha_solicita_verificacion = '$fechaActual' WHERE contrato_generado = '$request->contrato'");
        //registrar trazabilidad
        //validar que no este registrado
        $query = DB::SELECT("SELECT * FROM temporadas_verificacion_historico th
        WHERE th.contrato = '$request->contrato'
        AND th.estado = '1'");
        if(empty($query)){
            $trazabilidad = new TemporadaVerificacionHistorico();
            $trazabilidad->contrato                     = $request->contrato;
            $trazabilidad->fecha_solicita_verificacion  = $fechaActual;
            $trazabilidad->estado                       = 1;
            $trazabilidad->save();
        }
    }
    //api:get/notificacionesVerificaciones
    public function notificacionesVerificaciones(){
        $query = DB::SELECT("SELECT
            CONCAT(u.nombres,' ',u.apellidos) as asesor,
            i.nombreInstitucion,
            pe.region_idregion, c.nombre AS ciudad,
            p.contrato_generado,p.fecha_solicita_verificacion,p.tipo_venta
            FROM pedidos p
            LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            WHERE p.estado = '1'
            AND p.estado_verificacion ='1'
            order by p.fecha_solicita_verificacion desc
        ");
        return $query;
    }
    //api para traer la trazabilidad de las verificaciones
    //api:get/getTrazabilidadVerificacion
    public function getTrazabilidadVerificacion(Request $request){
        $query = DB::SELECT("SELECT th.*,
            CONCAT(u.nombres,' ', u.apellidos) AS asesor, i.nombreInstitucion,
            c.nombre AS ciudad
            FROM temporadas_verificacion_historico th
            LEFT JOIN temporadas t ON th.contrato = t.contrato
            LEFT JOIN usuario u ON t.id_asesor = u.idusuario
            LEFT JOIN institucion i ON t.idInstitucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            WHERE th.contrato = '$request->contrato'
            AND t.estado = '1'
        ");
        return $query;
    }
    //api para traer todo el historico de las solicitudes de verificaciones
    //api:get/getHistoricoVerificaciones
    public function getHistoricoVerificaciones(Request $request){
        $query = DB::SELECT("SELECT th.*,
            CONCAT(u.nombres,' ', u.apellidos) AS asesor, i.nombreInstitucion,
            c.nombre AS ciudad
            FROM temporadas_verificacion_historico th
            LEFT JOIN temporadas t ON th.contrato = t.contrato
            LEFT JOIN usuario u ON t.id_asesor = u.idusuario
            LEFT JOIN institucion i ON t.idInstitucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            ORDER BY th.id DESC
        ");
        return $query;
    }
    //api:get/getVerificacionXId/{id}
    public function getVerificacionXId($id){
        $query = DB::SELECT("SELECT * FROM verificaciones v
        WHERE v.id = '$id'
        ");
        return $query;
    }
    //api:post/saveDatosVerificacion
    public function saveDatosVerificacion(Request $request){
        $verificacion =  Verificacion::findOrFail($request->id);
        $observacion = "";
        if($request->observacion == null || $request->observacion == "null"){
            $observacion  = null;
        }else{
            $observacion = $request->observacion;
        }
        $verificacion->observacion = $observacion;
        $verificacion->save();
        if($verificacion){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
}
