<?php

namespace App\Http\Controllers;

use App\Models\Temporada;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Institucion;
use App\Models\Ciudad;
use App\Models\CodigoLibros;
use App\Models\HistoricoContratos;
use App\Models\Verificacion;
use App\Models\VerificacionHasInstitucion;
use App\Models\CodigosLibros;
use App\Models\Models\Verificacion\VerificacionDescuentoDetalle;
use App\Traits\Verificacion\TraitVerificacionGeneral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TemporadaController extends Controller
{
    use TraitVerificacionGeneral;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api para el get para milton
    public function temporadaDatos(){
        $temporada = DB::select("select t.*
        from temporadas t

     ");

     return $temporada;
    }

    //api institucion para milton

    public function instituciones_facturacion(){

    $grupo ="11";
    $estado ="1";
         $institucion_sin_asesor = DB::select("select i.idInstitucion, i.direccionInstitucion, i.cod_contrato, i.telefonoInstitucion, r.nombreregion  as region, c.nombre as ciudad,  u.nombres, u.apellidos
        from institucion i, region r, ciudad c,  usuario u
        where i.region_idregion  = r.idregion
        and i.ciudad_id  =  c.idciudad
        and i.vendedorInstitucion = u.cedula
        and u.id_group <> $grupo
       and i.estado_idEstado  = $estado
     ");
    //para traer las instituciones con asesor

    $institucion_con_asesor = DB::select("select i.idInstitucion, i.direccionInstitucion, i.cod_contrato, i.telefonoInstitucion, r.nombreregion  as region, c.nombre as ciudad,  u.nombres, u.apellidos
    from institucion i, region r, ciudad c,  usuario u
    where i.region_idregion  = r.idregion
    and i.ciudad_id  =  c.idciudad
    and i.vendedorInstitucion = u.cedula
    and u.id_group = $grupo
   and i.estado_idEstado  = $estado
 ");

     return ["institucion_con_asesor"=> $institucion_con_asesor,"institucion_sin_asesor"=>$institucion_sin_asesor];
    }
    //api para actualizar la institucion del asesor
    public function asesorInstitucion(Request $request){


            if($request->idInstitucion){
                //buscar la region
               $institucion=  DB::table('institucion')
                ->select("institucion.region_idregion")
                ->where('idInstitucion',$request->idInstitucion)
                ->get();

                if(count($institucion) <=0){
                    return "No existe la region para la institucion";
                }else{
                    $obtenerRegion = $institucion[0]->region_idregion;


                    if($obtenerRegion == "1"){

                        $res = DB::table('temporadas')
                        ->where('cedula_asesor', $request->cedula_asesor)
                        ->where('contrato',$request->contrato)
                        ->update(['idInstitucion' => $request->idInstitucion, 'temporal_institucion'=>$request->nombre_institucion,'ciudad'=>$request->nombre_ciudad,'temporada'=>'S']);
                        if($res){
                            return "Se guardo correctamente";

                        }else{
                            return "No se pudo guardar";
                        }
                    }


                    else{
                        $res = DB::table('temporadas')
                        ->where('cedula_asesor', $request->cedula_asesor)
                        ->where('contrato',$request->contrato)
                        ->update(['idInstitucion' => $request->idInstitucion, 'temporal_institucion'=>$request->nombre_institucion,'ciudad'=>$request->nombre_ciudad,'temporada'=>'C']);
                        if($res){
                            return "Se guardo correctamente";

                        }else{
                            return "No se pudo guardar";
                        }
                    }

                }

             }

     }



    //api para un formulario de prueba para  milton
    public function crearliquidacion(Request $request){
    //    $user = Auth::user();
    //     return $user;
         return view('testearapis.apitemporada');
    }
    public function eliminarTemporada(Request $request){
        $id = $request->get('id_temporada');
        $temp = Temporada::findOrFail($id);
        $contrato = $temp->contrato;
        //borrar el contrato de la table verificaciones
        DB::DELETE("DELETE FROM verificaciones
        WHERE contrato = '$contrato'
        ");
        //borar los codigos de la tabla verificaciones_liquidacion
        DB::DELETE("DELETE FROM verificaciones_has_temporadas
        WHERE contrato = '$contrato'
        ");
        //eliminar registro temporada
        $temp->delete();
    }
    //api para miton vea los numeros de contratos
    public function show($contrato){

        $contratos =  DB::table('temporadas')
             ->where('contrato', $contrato)
             ->get();
        return $contratos;
    }

    public function index(Request $request)
    {
        //para traer los asesores
        if($request->asesores){
            $asesores= DB::table('usuario')
            ->select(DB::raw('CONCAT(usuario.nombres , " " , usuario.apellidos ) as asesornombres'),'usuario.idusuario','usuario.nombres','usuario.cedula')
            ->where('id_group', '11')
            ->where('estado_idEstado','1')
            ->get();
            return $asesores;
        }
        //para editar el asesor una que se que quito el boton edit
        if($request->editarAsesor){

             //para actualizar la institucion el contrato
              DB::table('temporadas')
              ->where('contrato',  $request->contrato)
              ->update([
                  'cedula_asesor' => $request->cedula_asesor,
                  'id_asesor' => $request->id_asesor
                ]);
            return ["status" => "1", "message" =>"se edito correctamente el asesor"];
        }
        else{

            $asesores= DB::table('usuario')
            ->select(DB::raw('CONCAT(usuario.nombres , " " , usuario.apellidos ) as asesornombres'),'usuario.idusuario','usuario.nombres','usuario.cedula')
            ->where('id_group', '11')
            ->where('estado_idEstado','1')
            ->get();

            // $profesores= DB::table('usuario')
            //     ->select(DB::raw('CONCAT(usuario.nombres , " " , usuario.apellidos ) as  profesornombres'),'usuario.idusuario','usuario.nombres','usuario.cedula')
            //     ->where('id_group', '6')
            //     ->where('estado_idEstado','1')
            //     ->get();


            $ciudad = Ciudad::all();
            $institucion = Institucion::where('estado_idEstado', '=',1)->get();
            $temporada = DB::select("SELECT t.*, p.descripcion as periodo, CONCAT(ascr.nombres , ' ' , ascr.apellidos ) as asesorProlipa
                from temporadas t
                LEFT JOIN periodoescolar p ON t.id_periodo = p.idperiodoescolar
                LEFT JOIN usuario ascr  ON ascr.idusuario = t.id_asesor

            ");

            return ['temporada' => $temporada, 'asesores'=> $asesores, 'ciudad' => $ciudad, 'listainstitucion' => $institucion];
        }


    }
    //api:get/getTemporadas
    public function getTemporadas(Request $request){
        //filtro por periodos
        if($request->temporada){
            $temporada = DB::select("SELECT t.*, p.descripcion as periodo,
             CONCAT(ascr.nombres , ' ' , ascr.apellidos ) as asesorProlipa,
             i.nombreInstitucion, c.nombre as ciudad_prolipa
                from temporadas t
                LEFT JOIN periodoescolar p ON t.id_periodo = p.idperiodoescolar
                LEFT JOIN usuario ascr  ON ascr.idusuario = t.id_asesor
                LEFT JOIN institucion i ON i.idInstitucion = t.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                WHERE t.id_periodo = '$request->periodo_id'
                ORDER BY t.id_temporada DESC
            ");
            return $temporada;
        }
        //filtro por contratos
        if($request->filtroContrato){
            $temporada = DB::select("SELECT t.*, p.descripcion as periodo,
            CONCAT(ascr.nombres , ' ' , ascr.apellidos ) as asesorProlipa,
            i.nombreInstitucion, c.nombre as ciudad_prolipa
                from temporadas t
                LEFT JOIN periodoescolar p ON t.id_periodo = p.idperiodoescolar
                LEFT JOIN usuario ascr  ON ascr.idusuario = t.id_asesor
                LEFT JOIN institucion i ON i.idInstitucion = t.idInstitucion
                LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
                WHERE t.contrato like '%$request->contrato%'
            ");
            return $temporada;
        }
    }
    //para traer las instituciones por ciudad
    public function traerInstitucion(Request $request){
        $ciudad = $request->ciudad_id;
        $traerInstitucion = DB::table('institucion')
        ->select('institucion.idInstitucion','institucion.nombreInstitucion','institucion.region_idregion')
        ->where('ciudad_id', $ciudad)
        ->where('estado_idEstado','1')
        ->get();
      return  $traerInstitucion;


    }
    //para traer los profesores por institucion
    public function traerprofesores(Request $request){
         $institucion = $request->idInstitucion;

        $profesores= DB::table('usuario')
        ->select(DB::raw('CONCAT(usuario.nombres , " " , usuario.apellidos ) as  profesornombres'),'usuario.idusuario','usuario.nombres','usuario.cedula')
        ->where('id_group', '6')
        ->where('institucion_idInstitucion',$institucion)
        ->where('estado_idEstado','1')
        ->get();
        return $profesores;

    }
    //para traer los periodos por institucion
    public function traerperiodos(Request $request){

        $periodo = $request->region_idregion;
        $estado = $request->condicion;
        $traerPeriodo = DB::table('periodoescolar')
        ->select('periodoescolar.idperiodoescolar',DB::raw('CONCAT(periodoescolar.fecha_inicial , " a " , periodoescolar.fecha_final," | " ,periodoescolar.descripcion ) as  periodo'),'periodoescolar.region_idregion')
        ->OrderBy('periodoescolar.idperiodoescolar','desc')
        ->where('region_idregion', $periodo)
        ->where('periodoescolar.estado',$estado)

        ->get();
         return  $traerPeriodo;
    }
    public function validarContrato(Request $request){
        $validar = DB::SELECT("SELECT c.codigo,c.contrato
            FROM codigoslibros c
            WHERE c.bc_institucion = '$request->institucion_id'
            AND c.bc_periodo = '$request->periodo_id'
            AND c.contrato <> ''
            AND c.contrato IS NOT NULL
            AND c.contrato <> '0'
        ");
        //    $validar = DB::SELECT("SELECT c.*
        //    FROM codigoslibros c, usuario u
        //    WHERE   c.idusuario = u.idusuario
        //    AND u.institucion_idInstitucion = '$request->institucion_id'
        //    AND c.id_periodo = '$request->periodo_id'
        //    AND c.contrato <> ''
        //    AND c.contrato IS NOT NULL
        //    AND c.contrato <> '0'
        //    ");
        if(count($validar) > 0){
            $contrato = $validar[0]->contrato;
            return [
                "status" => "0",
                "message" => "Ya existe algun contrato asociado a algun codigo en el periodo como el contrato ".$contrato,
                "codigos" => $validar
            ];
        }else{
            return [
                "status" => "1",
                "message" => "Todo bien no hay ningun contrato asociado en el periodo para la institucion",
            ];
        }
    }


    public function store(Request $request)
    {
          //para buscar  la institucion  y sacar su periodo
        //   $verificarperiodoinstitucion = DB::table('periodoescolar_has_institucion')
        //   ->select('periodoescolar_has_institucion.periodoescolar_idperiodoescolar')

        //   ->where('periodoescolar_has_institucion.institucion_idInstitucion','=',$request->idInstitucion)
        //   ->get();

        //    foreach($verificarperiodoinstitucion  as $clave=>$item){
        //       $verificarperiodos =DB::SELECT("SELECT p.idperiodoescolar
        //       FROM periodoescolar p
        //       WHERE p.estado = '1'
        //       and p.idperiodoescolar = $item->periodoescolar_idperiodoescolar
        //       ");
        //    }

        //    if(count($verificarperiodoinstitucion) <=0){
        //       return ["status"=>"0", "message" => "No existe el periodo lectivo por favor, asigne un periodo a esta institucion"];
        //   }


        //    //verificar que el periodo exista
        //   if(count($verificarperiodos) <= 0){

        //       return ["status"=>"0", "message" => "No existe el periodo lectivo por favor, asigne un periodo a esta institucion"];

        //    }

        //   else{
                  //almancenar el periodo
              $periodo =  $request->periodo;
              //para ingresar el historico contratos
              $historico = new HistoricoContratos;
              $historico->contrato = $request->contrato;
              $historico->institucion=  $request->idInstitucion;
              $historico->periodo_id=  $periodo;
              $historico->save();

         // }

         if( $request->id ){
            $temporada = Temporada::find($request->id);
            $temporada->contrato = $request->contrato;
            $temporada->year = $request->year;
            $temporada->ciudad = $request->ciudad;
            $temporada->temporada = $request->temporada;
            $temporada->id_asesor = $request->id_asesor;
            $temporada->cedula_asesor = $request->cedula_asesor;
            $temporada->id_periodo = $periodo;

            if($request->id_profesor =="undefined"){
                $temporada->id_profesor = "0";
            }else{
                $temporada->id_profesor = $request->id_profesor;
            }
            $temporada->idInstitucion  = $request->idInstitucion;

        }else{

            $temporada = new Temporada();
            $temporada->contrato = $request->contrato;
            $temporada->year = $request->year;
            $temporada->ciudad = $request->ciudad;
            $temporada->temporada = $request->temporada;
            if($request->id_profesor =="undefined"){
                $temporada->id_profesor = "0";
            }else{
                $temporada->id_profesor = $request->id_profesor;
            }

            if($request->temporal_cedula_docente =="undefined"){
                $temporada->temporal_cedula_docente = "0";
            }else{
                $temporada->temporal_cedula_docente = $request->temporal_cedula_docente;
            }

            if($request->temporal_nombre_docente =="undefined"){
                $temporada->temporal_nombre_docente = "0";
            }else{
                $temporada->temporal_nombre_docente = $request->temporal_nombre_docente;
            }

                $temporada->idInstitucion  = $request->idInstitucion;
                $temporada->temporal_institucion  = $request->temporal_institucion;
                $temporada->id_asesor = $request->id_asesor;
                $temporada->cedula_asesor = $request->cedula_asesor;
                $temporada->nombre_asesor = $request->nombre_asesor;
                $historico->periodo_id=  $periodo;

        }

        $temporada->save();

        return ["status"=>"0", "message" => "Se agrego correctamente"];
    }
    //api para que los asesores puedan ver sus contratos
    public function asesorcontratos(Request $request){
        $cedula = $request->cedula;


        $temporadas= DB::table('temporadas')
            ->select('temporadas.*')
            ->where('cedula_asesor', $cedula)
            ->where('estado','1')
            ->get();

        return $temporadas;

    }

    //api:Get>>/liquidacion/contrato
    //api para  hacer la liquidacion
    public function liquidacion($contrato){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $buscarInstitucion= DB::table('temporadas')
        ->select('temporadas.idInstitucion')
        ->where('contrato', '=',$contrato)
        ->where('estado','=' ,'1')
        ->get();
        if(count($buscarInstitucion) <= 0){
            return ["status" => "0", "message" => "No existe el contrato en temporadas"];
        }else{
            $institucion = $buscarInstitucion[0]->idInstitucion;
            //verificar que el periodo exista
            $verificarPeriodo = DB::select("SELECT t.contrato, t.id_periodo, p.idperiodoescolar
             FROM temporadas t, periodoescolar p

             WHERE t.id_periodo = p.idperiodoescolar
             AND contrato = '$contrato'
             ");
             if(empty($verificarPeriodo)){
                return ["status"=>"0", "message" => "No se encontro el periodo"];
             }
            else{
                //almancenar el periodo
                 $periodo =  $verificarPeriodo[0]->idperiodoescolar;
                //traer temporadas
                $temporadas = DB::SELECT("SELECT t.*, CONCAT(u.nombres,' ',u.apellidos) AS asesor,i.nombreInstitucion,
                pe.periodoescolar AS periodo
                FROM temporadas t
                LEFT JOIN usuario u ON t.id_asesor = u.idusuario
                LEFT JOIN institucion i ON i.idInstitucion = t.idInstitucion
                LEFT JOIN periodoescolar pe ON t.id_periodo = pe.idperiodoescolar
                WHERE t.contrato = '$contrato'
                AND t.estado = '1'
                ");
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
                    AND ls.idLibro = c.libro_idlibro
                    AND c.prueba_diagnostica = '0'
                   GROUP BY ls.codigo_liquidacion,ls.nombre, c.serie,c.libro_idlibro");
                //SI TODO HA SALIDO BIEN TRAEMOS LA DATA
                if(count($data) >0){
                 return ['temporada'=>$temporadas,'codigos_libros' => $data];
                }else{
                    return ["status"=>"0", "message" => "No hay codigos para la liquidación"];
                }
            }
        }

    }
    //Api para milton para nos envia la data y nos guarde en nuestra bd
    public function generarApiTemporada(Request $request){
     $contrato= $request->contrato;
     $anio = $request->year;
     $ciudad= $request->ciudad;
     $temporada = $request->temporada;
     $temporal_nombre_docente= $request->temporal_nombre_docente;
     $temporal_cedula_docente= $request->temporal_cedula_docente;
     $temporal_institucion = $request->temporal_institucion;
     $nombre_asesor = $request->nombre_asesor;
     if(is_null($contrato)){
        return "Por favor ingrese el contrato";
     }
     if(is_null($anio)){
        return "Por favor ingrese el anio";
     }
     if(is_null($ciudad)){
        return "Por favor ingrese la ciudad";
     }
     if(is_null($temporada)){
        return "Por favor ingrese la temporada";
     }
     if(is_null($temporal_nombre_docente)){
        return "Por favor ingrese el nombre_docente";
     }
     if(is_null($temporal_cedula_docente)){
        return "Por favor ingrese  la cedula_docente";
     }
     if(is_null($temporal_institucion)){
        return "Por favor ingrese la institucion";
     }
     if(is_null($nombre_asesor)){
        return "Por favor ingrese el nombre_asesor";
     }
    //  if(is_null($contrato) || is_null($anio) ||  is_null($ciudad) ||  is_null($temporada) || is_null($temporal_nombre_docente) || is_null($temporal_cedula_docente) ||   is_null($temporal_institucion) ||  is_null($nombre_asesor)   ){
    //     return "Por favor llene todos los campos";
    //}
    else{
         $verificar_contrato = $request->contrato;
        $verificarcontratos = DB::table('temporadas')
        ->select('temporadas.contrato','temporadas.year')

        ->where('temporadas.contrato','=',$verificar_contrato)
        ->get();

        if(count($verificarcontratos) <= 0){

        $temporada = new Temporada();
        $temporada->contrato = $request->contrato;
        $temporada->year = $request->year;
        $temporada->ciudad = $request->ciudad;
        $temporada->temporada = $request->temporada;
        $temporada->temporal_nombre_docente = $request->temporal_nombre_docente;
        $temporada->temporal_cedula_docente = $request->temporal_cedula_docente;
        $temporada->temporal_institucion = $request->temporal_institucion;
        $temporada->nombre_asesor = $request->nombre_asesor;
        //campos a null
        $temporada->id_profesor= "0";
        $temporada->id_asesor= "0";
        $temporada->idInstitucion= "0";
        $temporada->cedula_asesor = "0";
        $date = Carbon::now();
        $temporada->ultima_fecha = $date;
        $temporada->save();

        return response()->json($temporada);

        }else{
            return "ya existe el contrato";
        }

     }

    }

     public function desactivar(Request $request)
    {
        $temporada =  Temporada::findOrFail($request->get('id_temporada'));
        $temporada->estado = 0;
        $temporada->save();
        return response()->json($temporada);
    }

     public function activar(Request $request)
    {
        $temporada =  Temporada::findOrFail($request->get('id_temporada'));
        $temporada->estado = 1;
        $temporada->save();
        return response()->json($temporada);
    }
    // funcion para agregar el docente a la vista de temporadas
    public function agregardocente(Request $request){
        $docente = new Usuario();
        $docente->cedula = $request->cedula;
        $docente->nombres = $request->nombres;
        $docente->apellidos = $request->apellidos;
        $docente->email = $request->email;
        $docente->name_usuario = $request->name_usuario;
        $docente->password=sha1(md5($request->cedula));
        $docente->id_group = 6;
        $docente->institucion_idInstitucion  = $request->institucion_idInstitucion;
        $docente->save();

        return $docente;
    }

    //APIS NUEVAS CON BARCODE

    //api de liquidacion para el sistema

     //api de milton liquidacion
     public function bliquidacionSistema(Request $request){

            $institucion = $request->institucion_id;
            $periodo     = $request->periodo_id;
            $data = DB::select("SELECT ls.codigo_liquidacion AS codigo,  COUNT(ls.codigo_liquidacion) AS cantidad, c.serie,
            c.libro_idlibro,c.libro as nombrelibro, i.nombreInstitucion ,
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor
               FROM codigoslibros c
               LEFT JOIN  libros_series ls ON ls.idLibro = c.libro_idlibro
               LEFT JOIN institucion i ON i.idInstitucion = c.bc_institucion
               LEFT JOIN usuario u ON u.cedula = i.vendedorInstitucion
               WHERE c.bc_estado = '2'
               AND c.estado <> 2
               AND c.bc_periodo  = '$periodo'
               AND c.bc_institucion = '$institucion'
               AND ls.idLibro = c.libro_idlibro
               GROUP BY ls.codigo_liquidacion,c.libro, c.serie,c.libro_idlibro, u.nombres,u.apellidos
            ");
            return $data;
    }


    //api de milton liquidacion
    public function bliquidacion_milton($contrato){

        set_time_limit(0);
        $buscarInstitucion= DB::SELECT("SELECT  * from temporadas
         WHERE contrato = '$contrato'
         AND estado = '1'
        ");

        if(count($buscarInstitucion) == 0){
            return ["status"=>"0", "message" => "No se encontro el contrato"];
        }else{
            $institucion = $buscarInstitucion[0]->idInstitucion;
             //verificar que el periodo exista
             $verificarPeriodo = DB::select("SELECT t.contrato, t.id_periodo, p.idperiodoescolar
             FROM temporadas t, periodoescolar p
             WHERE t.id_periodo = p.idperiodoescolar
             AND contrato = '$contrato'
             ");
             if(empty($verificarPeriodo)){
                return ["status"=>"0", "message" => "No se encontro el periodo"];
             }

            //traer la liquidacion
            else{
                //almancenar el periodo
                $periodo =  $verificarPeriodo[0]->idperiodoescolar;
                //traer temporadas
                $temporadas= $buscarInstitucion;

                $data = DB::select("SELECT ls.codigo_liquidacion AS codigo,  COUNT(ls.codigo_liquidacion) AS cantidad, c.libro as nombrelibro
                FROM codigoslibros c , libros_series ls
                WHERE c.bc_estado = '2'
                AND c.estado <> 2
                AND bc_periodo  = '$periodo'
                AND bc_institucion = '$institucion'
                AND ls.idLibro = c.libro_idlibro
                GROUP BY ls.codigo_liquidacion,c.libro

            ");
                if(count($data) >0){
                    return ['temporada'=>$temporadas,'codigos_libros' => $data];
                }else{
                    return ["status"=>"0", "message" => "No se pudo cargar la informacion"];
                }

            }
        }
    }
    //GET/getAllRegalados/{institucion,$periodo}
    public function getAllRegalados($institucion,$periodo){
        $key = "getAllRegalados".$institucion.$periodo;
        if (Cache::has($key)) {
           $regalados = Cache::get($key);
        } else {
            $regalados = $this->obtenerAllRegalados($institucion,$periodo);
            Cache::put($key,$regalados);
        }
        return $regalados;
    }
    //REGALADOS
    //API:GET/getRegalados/{institucion}/{periodo}/{num_verificacion}/{idverificacion}
    public function getRegalados($institucion,$periodo,$num_verificacion,$idverificacion){
       $query =  $this->ObtenerRegalados($institucion,$periodo,$num_verificacion,$idverificacion);
       return $query;
    }
    public function showRegalados($institucion,$periodo,$libro_idlibro,$num_verificacion,$idverificacion){
        $getnumVerificacion = "verif".$num_verificacion;
        $query = DB::SELECT("SELECT c.codigo
        FROM codigoslibros c
        WHERE  c.estado_liquidacion = '2'
        AND c.bc_periodo            = ?
        AND (c.bc_institucion       = '$institucion' OR c.venta_lista_institucion = '$institucion')
        AND c.prueba_diagnostica    = '0'
        AND c.libro_idlibro         = ?
        AND `$getnumVerificacion`   = ?
        ",[$periodo,$libro_idlibro,$idverificacion]);
        return $query;
    }
    //guardar regalados en verificacion
    public function saveRegaladosXVerificacion(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        //limpiar cache
        Cache::flush();
        $fecha                  = date("Y-m-d H:i:s");
        $codigos                = json_decode($request->data_codigos);
        $verificacion           = "verif".$request->num_verificacion;
        $verificacion_id        = $request->verificacion_id;
        $contrato               = $request->contrato;
        foreach($codigos as $key => $item){
            $codigo_union = "";
            //limpiar verificaciones
            $this->quitarRegalado($item->codigo);
            //guardar verificaciones
            $this->setVerificacionCodigo($item->codigo,$verificacion_id,$fecha,$verificacion,$contrato);
            //SI TIENE CODIGO DE UNION LO GUARDO
            $getCodigo = CodigosLibros::findOrFail($item->codigo);
            $codigo_union = $getCodigo->codigo_union;
            if($codigo_union == null || $codigo_union == "null" || $codigo_union == ""){
            }else{
                $this->setVerificacionCodigo($codigo_union,$verificacion_id,$fecha,$verificacion,$contrato);
            }
        }
    }
    public function setVerificacionCodigo($codigo,$verificacion_id,$fecha,$verificacion,$contrato){
        $codigo = DB::table('codigoslibros')
        ->where('codigo', '=', $codigo)
        ->update(
            [
                $verificacion       => $verificacion_id,
                "bc_fecha_ingreso"  => $fecha,
                "liquidado_regalado" => "1",
                "contrato"          => $contrato
            ]
        );
    }
    public function  CleanRegalado(Request $request){
        Cache::flush();
        $this->quitarRegalado($request->codigo);
        $getCodigo = CodigosLibros::findOrFail($request->codigo);
        $codigo_union = $getCodigo->codigo_union;
        //si tiene codigo de diagnostico
        if($codigo_union == null || $codigo_union == "null" || $codigo_union == ""){
        }else{
            $this->quitarRegalado($codigo_union);
        }
        return ["status" => "0", "message" => "Se guardo correctamente"];
    }
    public function quitarRegalado($codigo){
        $codigo = DB::table('codigoslibros')
        ->where('codigo', '=', $codigo)
        ->update(
            [
                "verif1"       => null,
                "verif2"       => null,
                "verif3"       => null,
                "verif4"       => null,
                "verif5"       => null,
                "verif6"       => null,
                "verif7"       => null,
                "verif8"       => null,
                "verif9"       => null,
                "verif10"       => null,
                'liquidado_regalado' => "0",
                "bc_fecha_ingreso"=>null,
                "contrato"      => null,
            ]
        );
    }
    //API:GET/getliquidadosDevueltos/{contrato}
    public function getliquidadosDevueltos($contrato){
        $key = "getliquidadosDevueltos".$contrato;
        if (Cache::has($key)) {
           $devueltos = Cache::get($key);
        } else {
            $devueltos = DB::SELECT("SELECT h.codigo_libro,h.devueltos_liquidados,
                h.verificacion_liquidada,h.observacion,h.created_at
                FROM hist_codlibros h
                LEFT JOIN codigoslibros c ON h.codigo_libro = c.codigo
                WHERE h.devueltos_liquidados = ?
                AND c.prueba_diagnostica = '0'
            ",[$contrato]);
            Cache::put($key,$devueltos);
        }
        return $devueltos;
    }
    public function limpiarCache(){
        Cache::flush();
    }
    public function updateVerificacion(Request $request){
        $campo  = $request->campo;
        $campo2 = $request->campo2;
        $campo3 = $request->campo3;
        $valor  = $request->valor;
        $valor2 = $request->valor2;
        $valor3 = $request->valor3;
        if($request->actualizarDosCampo){
            DB::table('verificaciones')
            ->where('id', $request->verificacion_id)
            ->update([
                $campo => $valor,
                $campo2 => $valor2,
            ]);
            return "Se guardo correctamente";
        }
        if($request->actualizarTresCampo){
            DB::table('verificaciones')
            ->where('id', $request->verificacion_id)
            ->update([
                $campo => $valor,
                $campo2 => $valor2,
                $campo3 => $valor3,
            ]);
            return "Se guardo correctamente";
        }
        else{
            $campo = $request->campo;
            $valor = $request->valor;
            DB::table('verificaciones')
            ->where('id', $request->verificacion_id)
            ->update([
                $campo => $valor,
            ]);
        }
    }
    //API:POST/saveDescuentosVerificacion
    public function saveDescuentosVerificacion(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $data_detalle   = json_decode($request->data_detalle);
        //actualizar la verificacion con los valores de total de descuento y campo dinamico
        $totalDescuento     = $request->totalDescuento;
        $personalizado      = $request->personalizado;
        $campoPersonalizado = $request->campoPersonalizado == null || $request->campoPersonalizado == "" ? null : $request->campoPersonalizado;
        $user_created       = $request->user_created;
        $fecha              = date("Y-m-d H:i:s");
        $ingreso = DB::table('verificaciones_descuentos')
        ->where('id', $request->verificaciones_descuentos_id)
        ->update([
            'total_descuento'       => $totalDescuento,
            'nombre_descuento'      => $campoPersonalizado,
            'estado'                => $personalizado,
            'user_created'          => $user_created
        ]);
        foreach($data_detalle as $key => $item){
            $descuento = VerificacionDescuentoDetalle::findOrFail($item->detalle_id);
            $descuento->descripcion             = $item->descripcion;
            $descuento->cantidad_descontar      = $item->cantidad_descontar;
            $descuento->porcentaje_descuento    = $item->porcentaje_descuento;
            $descuento->total_descontar         = $item->total_descontar;
            $descuento->tipo_calculo            = $item->tipo_calculo;
            $descuento->save();
        }
        return ["status" => "1", "message" => "Se guardo correctamente"];
    }
}
