<?php

namespace App\Http\Controllers;

use App\Models\Institucion;
use App\Models\Ciudad;
use App\Models\PeriodoInstitucion;
use Illuminate\Http\Request;
use DB;
use App\Quotation;
use App\Models\Configuracion_salle;

class InstitucionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */


    public function index()
    {
        $institucion = DB::select("CALL `listar_instituciones_periodo_activo` ();");
        return $institucion;

    }
    public function traerInstitucion(Request $request){
        $institucion = DB::select("SELECT * FROM institucion WHERE  idInstitucion = $request->institucion_idInstitucion
        ");
        return $institucion;
    }

    public function selectInstitucion(Request $request){
        if(empty($request->idregion) && empty($request->idciudad)){
            $institucion = DB::SELECT("SELECT i.idInstitucion,UPPER(i.nombreInstitucion) as nombreInstitucion
             FROM institucion i, periodoescolar_has_institucion pi
              WHERE i.idInstitucion != 66
              AND i.idInstitucion != 1170
              AND i.idInstitucion != 981
              AND i.idInstitucion != 914
              AND i.idInstitucion != 871
              AND i.idInstitucion != 1281
              AND i.estado_idEstado = 1
              AND i.punto_venta = '0'
              AND pi.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi WHERE phi.institucion_idInstitucion = i.idInstitucion)
            ");
        }
        if(!empty($request->idregion) && empty($request->idciudad)){
            $institucion = DB::SELECT("SELECT i.idInstitucion,UPPER(i.nombreInstitucion) as nombreInstitucion
             FROM institucion i, periodoescolar_has_institucion pi
             WHERE i.region_idregion = ? AND i.idInstitucion != 66
             AND i.idInstitucion != 1170
              AND i.idInstitucion != 981
              AND i.idInstitucion != 914
              AND i.idInstitucion != 871
              AND i.idInstitucion != 1281
             AND i.estado_idEstado = 1
             AND i.punto_venta = '0'
            AND pi.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi WHERE phi.institucion_idInstitucion = i.idInstitucion)
            ",[$request->idregion]);
        }
        if(!empty($request->idciudad) && empty($request->idregion)){
            $institucion = DB::SELECT("SELECT i.idInstitucion,UPPER(i.nombreInstitucion) as nombreInstitucion
             FROM institucion i, periodoescolar_has_institucion pi
             WHERE i.ciudad_id = ? AND i.idInstitucion != 66
              AND i.idInstitucion != 1170
              AND i.idInstitucion != 981
              AND i.idInstitucion != 914
              AND i.idInstitucion != 871
              AND i.idInstitucion != 1281
              AND i.estado_idEstado = 1
              AND i.punto_venta = '0'
             AND pi.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi WHERE phi.institucion_idInstitucion = i.idInstitucion)
             ",[$request->idciudad]);
        }
        if(!empty($request->idciudad) && !empty($request->idregion)){
            $institucion = DB::SELECT("SELECT idInstitucion,UPPER(nombreInstitucion) as nombreInstitucion
            FROM institucion i, periodoescolar_has_institucion pi
             WHERE i.ciudad_id = ? AND i.region_idregion = ? AND i.idInstitucion != 66
              AND i.idInstitucion != 1170
              AND i.idInstitucion != 981
              AND i.idInstitucion != 914
              AND i.idInstitucion != 871
              AND i.idInstitucion != 1281
              AND i.estado_idEstado = 1
              AND i.punto_venta = '0'
             AND pi.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi WHERE phi.institucion_idInstitucion = i.idInstitucion)
            ",[$request->idciudad,$request->idregion]);
        }
        return $institucion;

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    //api:get/institucionActiva
    public function institucionActiva(Request $request){
        $validar = DB::SELECT("SELECT i.estado_idEstado AS estado FROM institucion i
        WHERE i.idInstitucion = '$request->idInstitucion'
        ");
        return $validar;
    }
    public function store(Request $request)
    {
        $datosValidados=$request->validate([
            'nombreInstitucion' => 'required',
            'telefonoInstitucion' => 'required',
            'direccionInstitucion' => 'required',
            'vendedorInstitucion' => 'required',
            'region_idregion' => 'required',
            'solicitudInstitucion' => 'required',
            'ciudad_id' => 'required',
            'tipo_institucion' => 'required',
        ]);
        if(!empty($request->idInstitucion)){
            // $institucion = Institucion::find($request->idInstitucion)->update($request->all());
            $cambio = Institucion::find($request->idInstitucion);
            $archivo = $cambio->imgenInstitucion;
            if($request->enviarArchivo){
                //eliminar el archivo anterior si existe
                if($archivo == "" || $archivo == null || $archivo == 0){

                }else{
                    if(file_exists('archivos/instituciones_logos/'.$archivo) ){
                        unlink('archivos/instituciones_logos/'.$archivo);
                    }
                }
                $ruta = public_path('/archivos/instituciones_logos/');
                if(!empty($request->file('imagenInstitucion'))){
                    $file = $request->file('imagenInstitucion');
                    $fileName = uniqid().$file->getClientOriginalName();
                    $file->move($ruta,$fileName);
                }
                $cambio->imgenInstitucion = $fileName;
            }
        }
        else{
            $cambio = new Institucion();
            if($request->enviarArchivo){
                $ruta = public_path('/archivos/instituciones_logos/');
                if(!empty($request->file('imagenInstitucion'))){
                    $file = $request->file('imagenInstitucion');
                    $fileName = uniqid().$file->getClientOriginalName();
                    $file->move($ruta,$fileName);

                }
                $cambio->imgenInstitucion = $fileName;
            }

        }
        $cambio->idcreadorinstitucion   = $request->idcreadorinstitucion;
        $cambio->nombreInstitucion      = $request->nombreInstitucion;
        $cambio->direccionInstitucion   = $request->direccionInstitucion;
        $cambio->telefonoInstitucion    = $request->telefonoInstitucion;
        $cambio->solicitudInstitucion   = $request->solicitudInstitucion;
        $cambio->codigo_institucion_milton   = $request->codigo_institucion_milton;
        $cambio->vendedorInstitucion    = $request->vendedorInstitucion;
        $cambio->tipo_institucion       = $request->tipo_institucion;
        $cambio->region_idregion        = $request->region_idregion;
        $cambio->ciudad_id              = $request->ciudad_id;
        $cambio->estado_idEstado        = $request->estado;
        $cambio->aplica_matricula       = $request->aplica_matricula;
        $cambio->punto_venta            = $request->punto_venta;
        $cambio->asesor_id              = $request->asesor_id;
        $cambio->save();
        return $cambio;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Institucion  $institucion
     * @return \Illuminate\Http\Response
     */
    public function show(Institucion $institucion)
    {
        return $institucion;
    }


    public function verInstitucionCiudad($idciudad)
    {
        $instituciones = DB::SELECT("SELECT idInstitucion as id, nombreInstitucion as label
         FROM institucion
         WHERE idInstitucion != '66'
         AND idInstitucion != '981'
         AND idInstitucion != '795'
         AND idInstitucion != '871'
         AND idInstitucion != '914'
         AND idInstitucion != '1170'
         AND idInstitucion != '1281'
         AND ciudad_id = '$idciudad'
         AND estado_idEstado = '1'
         ");
        return $instituciones;
    }


    public function verificarInstitucion($id)
    {
        $instituciones = DB::SELECT("SELECT u.institucion_idInstitucion, i.aplica_matricula FROM usuario u, institucion i WHERE u.idusuario = $id AND u.institucion_idInstitucion = i.idInstitucion");

        return $instituciones;
    }


    public function asignarInstitucion(Request $request)
    {
        $institucion = DB::UPDATE("UPDATE usuario SET institucion_idInstitucion = $request->institucion WHERE idusuario = $request->usuario");

        return $institucion;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Institucion  $institucion
     * @return \Illuminate\Http\Response
     */
    public function edit(Institucion $institucion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Institucion  $institucion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Institucion $institucion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Institucion  $institucion
     * @return \Illuminate\Http\Response
     */
    public function destroy(Institucion $institucion)
    {
        $institucion = Institucion::find($institucion->idarea)->update(['estado_idEstado' => '0']);
        return $institucion;
    }


    //guardar foto de institucion desde perfil de director
    public function guardarLogoInstitucion(Request $request)
    {
        $cambio = Institucion::find($request->institucion_idInstitucion);

        $ruta = public_path('/instituciones_logos');
        if(!empty($request->file('archivo'))){
        $file = $request->file('archivo');
        $fileName = uniqid().$file->getClientOriginalName();
        $file->move($ruta,$fileName);
        $cambio->imgenInstitucion = $fileName;
        }

        $cambio->ideditor = $request->ideditor;
        $cambio->nombreInstitucion = $request->nombreInstitucion;
        $cambio->direccionInstitucion = $request->direccionInstitucion;
        $cambio->telefonoInstitucion = $request->telefonoInstitucion;
        $cambio->region_idregion = $request->region_idregion;
        $cambio->ciudad_id = $request->ciudad_id;
        $cambio->updated_at = now();

        $cambio->save();
        return $cambio;

    }
    public function institucionesSalle()
    {
        // $institucion = DB::select("SELECT nombreInstitucion, idInstitucion FROM  institucion  WHERE tipo_institucion = 2 and estado_idEstado = 1 ");
        // return $institucion;

        $institucion = DB::select("SELECT i.nombreInstitucion, i.idInstitucion, concat(i.nombreInstitucion,' - ',c.nombre) AS institucion_ciudad
        FROM  institucion i
        INNER JOIN ciudad c ON i.ciudad_id = c.idciudad
        WHERE i.tipo_institucion = 2 and i.estado_idEstado = 1 ");
        return $institucion;
    }

    public function instituciones_salle(){
        $instituciones = DB::SELECT("SELECT i.*, c.nombre as nombre_ciudad, concat(i.nombreInstitucion,' - ',c.nombre) AS institucion_ciudad, sc.fecha_inicio, sc.fecha_fin, sc.ver_respuestas, sc.observaciones, sc.cant_evaluaciones FROM institucion i INNER JOIN ciudad c ON i.ciudad_id = c.idciudad LEFT JOIN salle_configuracion sc ON i.id_configuracion = sc.id_configuracion WHERE i.tipo_institucion = 2");

        if(!empty($instituciones)){
            foreach ($instituciones as $key => $value) {
                $periodo = DB::SELECT("SELECT p.idperiodoescolar, p.fecha_inicial, p.fecha_final, p.periodoescolar, p.estado FROM periodoescolar_has_institucion pi, periodoescolar p WHERE pi.institucion_idInstitucion = ? AND pi.periodoescolar_idperiodoescolar = p.idperiodoescolar ORDER BY p.idperiodoescolar DESC LIMIT 1",[$value->idInstitucion]);

                $data['items'][$key] = [
                    'institucion' => $value,
                    'periodo' => $periodo,
                ];
            }
        }else{
            $data = [];
        }
        return $data;
    }

    public function instituciones_salle_select(){
        $instituciones = DB::SELECT("SELECT i.*, c.nombre as nombre_ciudad, concat(i.nombreInstitucion,' - ',c.nombre) AS institucion_ciudad, sc.fecha_inicio, sc.fecha_fin, sc.ver_respuestas, sc.observaciones, sc.cant_evaluaciones FROM institucion i INNER JOIN ciudad c ON i.ciudad_id = c.idciudad LEFT JOIN salle_configuracion sc ON i.id_configuracion = sc.id_configuracion WHERE i.tipo_institucion = 2");

        return $instituciones;
    }

    public function save_instituciones_salle(Request $request){
        if( $request->id_configuracion == 0 ){
            $configuracion = new Configuracion_salle();
        }else{
            $configuracion = Configuracion_salle::find($request->id_configuracion);
        }

        $configuracion->fecha_inicio = $request->fecha_inicio;
        $configuracion->fecha_fin = $request->fecha_fin;
        $configuracion->ver_respuestas = $request->ver_respuestas;
        $configuracion->observaciones = $request->observaciones;
        $configuracion->cant_evaluaciones = $request->cant_evaluaciones;

        $configuracion->save();

        if( $request->id_configuracion == 0 ){
            DB::UPDATE("UPDATE `institucion` SET `id_configuracion` = $configuracion->id_configuracion WHERE `idInstitucion` = $request->id_institucion");
        }

        return $configuracion;
    }
    public function listaInstitucionesActiva(){

        $institucion = DB::SELECT("SELECT inst.idInstitucion,
        UPPER(inst.nombreInstitucion) as nombreInstitucion,
        UPPER(ciu.nombre) as ciudad,
        UPPER(reg.nombreregion) as nombreregion,
        inst.solicitudInstitucion,
        -- inst.vendedorInstitucion as asesor
        concat_ws(' ', usu.nombres, usu.apellidos) as asesor


        FROM institucion inst, ciudad ciu, region reg, usuario usu
        where inst.ciudad_id = ciu.idciudad
        AND inst.region_idregion = reg.idregion
        AND inst.vendedorInstitucion = usu.cedula
        AND inst.estado_idEstado = 1");
                return $institucion;
    }
    public function institucionConfiguracionSalle($id)
    {
        $configuracion = DB::SELECT("SELECT inst.id_configuracion, sc.*
        FROM institucion inst, salle_configuracion sc
        WHERE inst.id_configuracion = sc.id_configuracion
        AND inst.idInstitucion  = $id");
        return $configuracion;
    }
    public function listaInsitucion(Request $request)
    {
        if($request->asesor){
            $cedula = "";
            //para hacer pruebas
            if($request->cedula == "854564544564564"){
                $cedula = "0915171920";
            }else{
                $cedula = $request->cedula;
            }
            $lista = DB::SELECT("SELECT i.idInstitucion, i.nombreInstitucion,i.aplica_matricula,
            IF(i.estado_idEstado = '1','activado','desactivado') AS estado,i.estado_idEstado as estadoInstitucion,
            c.nombre AS ciudad, u.idusuario AS asesor_id,u.nombres AS nombre_asesor,
            u.apellidos AS apellido_asesor, i.fecha_registro, r.nombreregion, i.codigo_institucion_milton,
            ic.estado as EstadoConfiguracion, ic.periodo_configurado,i.codigo_mitlon_coincidencias,
            pec.periodoescolar as periodoNombreConfigurado,i.vendedorInstitucion,u.iniciales
            FROM institucion i
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            LEFT JOIN region r ON i.region_idregion = r.idregion
            LEFT JOIN usuario u ON i.vendedorInstitucion = u.cedula
            LEFT JOIN institucion_configuracion_periodo ic ON i.region_idregion = ic.region
            LEFT JOIN periodoescolar pec ON ic.periodo_configurado = pec.idperiodoescolar
            WHERE i.nombreInstitucion LIKE '%$request->busqueda%'
            AND  i.vendedorInstitucion = '$cedula'
            ORDER BY i.fecha_registro DESC
            ");
        }else{
            $lista = DB::SELECT("SELECT i.idInstitucion, i.nombreInstitucion,i.aplica_matricula,
            IF(i.estado_idEstado = '1','activado','desactivado') AS estado,i.estado_idEstado as estadoInstitucion,
            c.nombre AS ciudad, u.idusuario AS asesor_id,u.nombres AS nombre_asesor,
            u.apellidos AS apellido_asesor, i.fecha_registro, r.nombreregion, i.codigo_institucion_milton,
            ic.estado as EstadoConfiguracion, ic.periodo_configurado,i.codigo_mitlon_coincidencias,
            pec.periodoescolar as periodoNombreConfigurado,i.vendedorInstitucion,u.iniciales
            FROM institucion i
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            LEFT JOIN region r ON i.region_idregion = r.idregion
            LEFT JOIN usuario u ON i.vendedorInstitucion = u.cedula
            LEFT JOIN institucion_configuracion_periodo ic ON i.region_idregion = ic.region
            LEFT JOIN periodoescolar pec ON ic.periodo_configurado = pec.idperiodoescolar
            WHERE i.nombreInstitucion LIKE '%$request->busqueda%'
            ORDER BY i.fecha_registro DESC
            ");
        }
        $datos = [];
        if(count($lista) ==0){
            return ["status" => "0","message"=> "No se encontro instituciones con ese nombre"];
        }else{

            foreach($lista as $key => $item){
                //buscar periodo
                $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo_id , periodoescolar AS periodo,
                IF(estado = '1' ,'Activo','Desactivado') as estadoPeriodo,estado
                 FROM periodoescolar
                  WHERE idperiodoescolar = (
                    SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
                    from institucion i,  periodoescolar_has_institucion pir
                    WHERE i.idInstitucion = pir.institucion_idInstitucion
                    AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
                    WHERE phi.institucion_idInstitucion = i.idInstitucion
                    AND i.idInstitucion = '$item->idInstitucion'))
                ");
                if(count($periodoInstitucion) > 0){
                    $datos[$key]=[
                        "idInstitucion" =>     $item->idInstitucion,
                        "nombreInstitucion" => $item->nombreInstitucion,
                        "aplica_matricula" =>  $item->aplica_matricula,
                        "estado" =>            $item->estado,
                        "estadoInstitucion" => $item->estadoInstitucion,
                        "ciudad" =>            $item->ciudad,
                        "asesor_id" =>         $item->asesor_id,
                        "nombre_asesor" =>     $item->nombre_asesor,
                        "apellido_asesor" =>   $item->apellido_asesor,
                        "asesor"           =>  $item->nombre_asesor." ".$item->apellido_asesor,
                        "fecha_registro" =>    $item->fecha_registro,
                        "nombreregion" =>      $item->nombreregion,
                        "periodo_id" =>        $periodoInstitucion[0]->periodo_id,
                        "periodo" =>           $periodoInstitucion[0]->periodo,
                        "estadoPeriodo" =>     $periodoInstitucion[0]->estadoPeriodo,
                        "statusPeriodo" =>     $periodoInstitucion[0]->estado,
                        "EstadoConfiguracion" =>  $item->EstadoConfiguracion,
                        "periodo_configurado" => $item->periodo_configurado,
                        "periodoNombreConfigurado" => $item->periodoNombreConfigurado,
                        "codigo_institucion_milton" => $item->codigo_institucion_milton,
                        "codigo_mitlon_coincidencias" => $item->codigo_mitlon_coincidencias,
                        "vendedorInstitucion"   => $item->vendedorInstitucion,
                        "iniciales"             => $item->iniciales,
                    ];
                }else{
                    $datos[$key]=[
                        "idInstitucion" =>     $item->idInstitucion,
                        "nombreInstitucion" => $item->nombreInstitucion,
                        "aplica_matricula" =>  $item->aplica_matricula,
                        "estado" =>            $item->estado,
                        "estadoInstitucion" => $item->estadoInstitucion,
                        "ciudad" =>            $item->ciudad,
                        "asesor_id" =>         $item->asesor_id,
                        "nombre_asesor" =>     $item->nombre_asesor,
                        "apellido_asesor" =>   $item->apellido_asesor,
                        "fecha_registro" =>    $item->fecha_registro,
                        "nombreregion" =>      $item->nombreregion,
                        "periodo_id" =>        '0',
                        "periodo" =>           'Sin periodo',
                        "estadoPeriodo" =>     "",
                        "EstadoConfiguracion" =>  $item->EstadoConfiguracion,
                        "periodo_configurado" => $item->periodo_configurado,
                        "periodoNombreConfigurado" => $item->periodoNombreConfigurado,
                        "codigo_institucion_milton" => $item->codigo_institucion_milton,
                        "codigo_mitlon_coincidencias" => $item->codigo_mitlon_coincidencias,
                        "vendedorInstitucion"   => $item->vendedorInstitucion,
                        "iniciales"             => $item->iniciales,
                    ];
                }
            }
            return $datos;
        }
    }

    public function listaInsitucionAsesor(Request $request)
    {
        if($request->porCedula){
            $lista = Institucion::select('institucion.idInstitucion','institucion.nombreInstitucion','institucion.aplica_matricula','institucion.solicitudInstitucion','estado.nombreestado as estado','ciudad.nombre as ciudad','usuario.idusuario as asesor_id','usuario.nombres as nombre_asesor', 'usuario.apellidos as apellido_asesor', 'institucion.fecha_registro', 'region.nombreregion' )
            ->leftjoin('ciudad','institucion.ciudad_id','=','ciudad.idciudad')
            ->leftjoin('region','institucion.region_idregion','=','region.idregion')
            ->leftjoin('usuario','institucion.vendedorInstitucion','=','usuario.cedula')
            ->join('estado','institucion.estado_idEstado','=','estado.idEstado')
            ->where('institucion.vendedorInstitucion','=',$request->cedula)
            ->orderBy('institucion.fecha_registro','desc')
            ->get();
        }
        //traer las instituciones temporales  creadas por el asesor
        if($request->temporales){
            $instituciones = DB::SELECT("SELECT t.institucion_temporal_id,
            IF(t.region = 2,'Costa','Sierra') AS nombreregion, 
            t.nombre_institucion AS nombreInstitucion,
            t.periodo_id,t.asesor_id,t.ciudad,pe.periodoescolar AS periodo
            FROM seguimiento_institucion_temporal t
         	LEFT JOIN periodoescolar pe ON t.periodo_id = pe.idperiodoescolar
            WHERE t.asesor_id = '$request->asesor_id'
            ORDER BY t.institucion_temporal_id DESC
            
            ");
            return $instituciones;
        }
        //traer la agenda por instituciones de prolipa o temporales
        if($request->todo){
            $todoAgenda = DB::SELECT("SELECT i.nombreInstitucion,
            c.*,
           CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
           CONCAT(f.nombres, ' ', f.apellidos) AS finalizador,
           CONCAT(cr.nombres, ' ', cr.apellidos) AS creador,
           (case when (c.estado_institucion_temporal = 1) then c.nombre_institucion_temporal  else i.nombreInstitucion end) as institucionFinal,
           (case when (c.estado = 1) then 'Finalizada' else 'Generada' end) as status,
           p.idperiodoescolar,p.periodoescolar AS periodo
            FROM agenda_usuario c
            LEFT JOIN usuario u ON c.id_usuario = u.idusuario
            LEFT JOIN usuario f ON c.usuario_editor = f.idusuario
            LEFT JOIN usuario cr ON c.usuario_creador = cr.idusuario
            LEFT JOIN periodoescolar p ON c.periodo_id = p.idperiodoescolar
            LEFT JOIN institucion i ON c.institucion_id = i.idInstitucion
           WHERE c.id_usuario = '$request->asesor_id'
           AND c.estado <> '2'
           ORDER BY c.id DESC
            ");
            return $todoAgenda;
        }
        else{

            $lista = Institucion::select('institucion.idInstitucion','institucion.nombreInstitucion','institucion.aplica_matricula','institucion.solicitudInstitucion','estado.nombreestado as estado','ciudad.nombre as ciudad','usuario.idusuario as asesor_id','usuario.nombres as nombre_asesor', 'usuario.apellidos as apellido_asesor', 'institucion.fecha_registro', 'region.nombreregion' )
            ->leftjoin('ciudad','institucion.ciudad_id','=','ciudad.idciudad')
            ->leftjoin('region','institucion.region_idregion','=','region.idregion')
            ->leftjoin('usuario','institucion.vendedorInstitucion','=','usuario.cedula')
            ->join('estado','institucion.estado_idEstado','=','estado.idEstado')
            ->where('institucion.nombreInstitucion','like','%'.$request->busqueda.'%')
            ->where('institucion.vendedorInstitucion','=',$request->cedula)
            ->orderBy('institucion.fecha_registro','desc')
            ->get();
        }

        if(count($lista) ==0){
            return ["status" => "0","message"=> "Esta institución no esta asignada a su perfil"];
        }else{
            return $lista;
        }

    }
    //api::post//>/institucionEliminar
    public function institucionEliminar(Request $request){
        Institucion::findOrFail($request->id)->delete();
        return "Se elimino correctamente";
    }

    public function instituciones_ciudad(Request $request)
    {
        $lista = DB::SELECT("SELECT i.idInstitucion, i.nombreInstitucion,i.aplica_matricula,
        IF(i.estado_idEstado = '1','activado','desactivado') AS estado,i.estado_idEstado as estadoInstitucion,
        c.nombre AS ciudad, u.idusuario AS asesor_id,u.nombres AS nombre_asesor,
        u.apellidos AS apellido_asesor, i.fecha_registro, r.nombreregion, i.codigo_institucion_milton,
        ic.estado as EstadoConfiguracion, ic.periodo_configurado,i.codigo_mitlon_coincidencias,
        pec.periodoescolar as periodoNombreConfigurado,i.vendedorInstitucion,u.iniciales
        FROM institucion i
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN region r ON i.region_idregion = r.idregion
        LEFT JOIN usuario u ON i.vendedorInstitucion = u.cedula
        LEFT JOIN institucion_configuracion_periodo ic ON i.region_idregion = ic.region
        LEFT JOIN periodoescolar pec ON ic.periodo_configurado = pec.idperiodoescolar
        WHERE i.ciudad_id = '$request->ciudad_id'
        ORDER BY i.fecha_registro DESC
        ");

        return $lista;
    }
    public function getInstitucionConfiguracion (){
        $dato = DB::table('institucion_configuracion_periodo as ic')
        ->leftjoin('periodoescolar as p', 'ic.periodo_configurado','p.idperiodoescolar')
        ->leftjoin('region as r', 'r.idregion','=','ic.region')
        ->select('ic.*','r.nombreregion', 'p.idperiodoescolar','p.descripcion','p.periodoescolar','p.codigo_contrato','p.porcentaje_descuento')
        ->get();
        return $dato;
    }
    public function institucion_conf_periodo(Request $request)
    {
        $valores = [
            'id' => $request->id,
            'region' => $request->region,
            'periodo_configurado' => $request->periodo_configurado,
            'estado' => $request->estado
        ];
        if ($request->id > 0) {
            $dato = DB::table('institucion_configuracion_periodo')
            ->where('id',$request->id)
            ->update($valores);
            return [ 'dato'=>$dato, 'mensaje'=>'Datos actualizados'];
        }else {
            $dato = DB::table('institucion_configuracion_periodo')->insert($valores);
            return [ 'dato'=>$dato, 'mensaje'=>'Datos registrados'];
        }
    }
}
