<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comunicaciones;
use DB;
class ComunicacionesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $contador = 0;
        $datos    =  [];
        $query = DB::table('comunicaciones as c')
        ->select(DB::RAW("c.*,CONCAT(u.nombres,' ',u.apellidos) as emisor,
            CONCAT(urec.nombres,' ',urec.apellidos) as usuario_receptor,
            CONCAT(urec.nombres,' ',urec.apellidos) as usuario_receptor,
            CONCAT(n.nombrenivel,' ',p.descripcion) as cursoReceptor,
            (CASE WHEN (c.tipo_comunicacion = '1') then 'Comunicación General'
                WHEN (c.tipo_comunicacion   = '2') then 'Comunicación a Docente'
                WHEN (c.tipo_comunicacion   = '3') then 'Comunicación a Estudiante'
            end) as tipoC,
            (
                SELECT COUNT(cf.id) AS contador
                FROM comunicaciones_files cf
                WHERE cf.comunicacion_id = c.id

            ) as contador,
            (
                SELECT estadoMessage FROM comunicaciones_estado_messages cm
                WHERE cm.comunicacion_id = c.id
                AND cm.usuario_id = '$request->user'
            ) AS estadoMessage
        "))
        ->leftJoin('usuario as u', 'u.idusuario', '=', 'c.user_created')
        ->leftJoin('usuario as urec', 'urec.idusuario', '=', 'c.receptor_id')
        ->leftJoin('mat_niveles_institucion as cred', 'cred.nivelInstitucion_id', '=', 'c.receptor_id')
        ->leftJoin('nivel as n', 'n.idnivel', '=', 'cred.nivel_id')
        ->leftJoin('mat_paralelos as p', 'p.paralelo_id', '=', 'cred.paralelo_id')
        ->Where('c.institucion_id','=',$request->institucion_id)
        ->Where('c.periodo_id', '=',$request->periodo_id)
        ->OrderBy('c.id','desc')
        ->get();
        if(count($query) == 0){
            return ["query" => [], "docentes" => []];
        }
        foreach($query as $key => $item){
            //obtener la cantidad de comunicados al docentes enviado
            if($item->tipo_comunicacion == 2){
                $consulta = DB::SELECT("SELECT DISTINCT COUNT(c.id) AS contador,
                CONCAT(u.nombres,' ',u.apellidos) AS docente
                FROM comunicaciones c
                LEFT JOIN usuario u ON c.receptor_id = u.idusuario
                WHERE c.institucion_id = '$request->institucion_id'
                AND c.periodo_id = '$request->periodo_id'
                AND c.receptor_id = '$item->receptor_id'
                ");
                $datos[$contador] = [
                    "tipo_comunicacion" => $item->tipo_comunicacion,
                    "receptor_id"       => $item->receptor_id,
                    "docente"           => $consulta[0]->docente,
                    "docenteContador"   => $consulta[0]->contador,
                ];
                $contador++;
            }
            //actualizar a finalizado despues de 15 dias
            if($this->UpdateFinalizados($query));
        }
        return ["query" => $query, "docentes" => $datos];
    }
    public function UpdateFinalizados($query){
        $fecha =  date("Y-m-d");
        foreach($query as $key => $item){
            if($item->estado == 1 && $fecha >= $item->fecha_15_dias ){
                $change = Comunicaciones::findOrFail($item->id);
                $change->estado = 0;
                $change->save();
            }
        }
    }
    public function listadoComunicaciones(Request $request){
      //listado de comunicaciones con files
      if($request->listadoComunicacionesFiles){
        return $this->listadoComunicacionesFiles($request->comunicacion_id);
      }
      //listado de respuestas
      if($request->listadoRespuestas){
        return $this->listadoRespuestas($request->comunicacion_id);
      }
    }
    public function listadoComunicacionesFiles($comunicacion_id){
        $consulta = DB::SELECT("SELECT * FROM comunicaciones_files cf
        WHERE cf.comunicacion_id = '$comunicacion_id'
        ");
        return $consulta;
    }
    public function listadoRespuestas($comunicacion_id){
        $query = DB::SELECT("SELECT cr.*, CONCAT(u.nombres, ' ',u.apellidos) AS mensajero
        FROM comunicaciones_respuesta cr
        LEFT JOIN usuario u ON cr.usuario_id = u.idusuario
        WHERE cr.comunicacion_id = '$comunicacion_id'
        ORDER BY id ASC
        ");
        $datos = [];
        foreach($query as $key => $item){
            $consulta = DB::SELECT("SELECT * FROM comunicaciones_respuesta_files crf
            WHERE crf.comunicacion_respuesta_id = '$item->id'
            ");
            $datos[$key] = [
                "id"                => $item->id,
                "comunicacion_id"   => $item->comunicacion_id,
                "usuario_id"        => $item->usuario_id,
                "mensaje"           => $item->mensaje,
                "id_group"          => $item->id_group,
                "created_at"        => $item->created_at,
                "mensajero"         => $item->mensajero,
                "files"             => $consulta,
            ];
        }
        return $datos;
    }
    //cambiar estado del comunicado
    public function cambiarEstadoComunicado(Request $request){
        $change = Comunicaciones::findOrFail($request->id);
        $change->estado = $request->estado;
        $change->save();
        return $change;
    }
    public function cambiarEstadoMessage(Request $request){
        if($request->grupo == "individual"){
            //actualizar usuario
            DB::table('comunicaciones_estado_messages')
            ->where('comunicacion_id', $request->id)
            ->where('usuario_id',      $request->receptor)
            ->update(['estadoMessage' => "2"]);
        }
        if($request->grupo == "general"){
            DB::UPDATE("UPDATE comunicaciones_estado_messages cm
            SET estadoMessage = '2'
            WHERE cm.comunicacion_id = '$request->id'
            -- AND cm.usuario_id <> '$request->receptor'
            ");
        }
        //actualizar emisor
        DB::table('comunicaciones_estado_messages')
        ->where('comunicacion_id', $request->id)
        ->where('usuario_id',      $request->emisor)
        ->update(['estadoMessage' => "1"]);
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
