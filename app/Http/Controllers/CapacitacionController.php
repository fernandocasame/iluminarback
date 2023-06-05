<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Capacitacion;
use App\Models\Seminarios;
use DB;

class CapacitacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $capacitacion = DB::SELECT("SELECT c.id_seminario as id,
        c.tema_id,
        t.capacitador,
        c.label,
        c.nombre as title,
        c.classes,
        date_format(c.fecha_inicio, '%Y-%m-%d %H:%i:%s') as endDate ,
        c.fecha_inicio as startDate,
        c.hora_inicio,
        c.hora_fin,
        c.id_institucion as institucion_id,
        c.periodo_id,
        c.id_usuario,
        c.estado_capacitacion as estado,
        c.observacion_admin as observacion,
        c.institucion_id_temporal,
        c.nombre_institucion_temporal,
        c.estado_institucion_temporal,
        c.tipo,
        c.cant_asistentes as personas,
        p.idperiodoescolar, p.periodoescolar AS periodo, i.nombreInstitucion
        FROM seminarios c
        LEFT JOIN periodoescolar p ON c.periodo_id = p.idperiodoescolar
        LEFT JOIN institucion i ON  c.id_institucion = i.idInstitucion
        LEFT JOIN capacitacion_temas t ON c.tema_id = t.id
        WHERE c.id_usuario = '$request->id_usuario'
        AND c.tipo_webinar = '2'
        AND c.estado = '1'
        ");
        return $capacitacion;
    }
    public function temasCapacitacion(Request $request){
        if($request->validarPorFecha){
            $validar = DB::SELECT("SELECT * FROM seminarios a
            WHERE a.fecha_inicio_temp = '$request->fecha'
            AND a.tema_id  ='$request->tema_id'
            AND a.estado = '1'
            AND a.tipo_webinar = '2'
            ");
            return $validar;
        }
        if($request->encontrarCapacitaciones){
            $capacitaciones = DB::SELECT("SELECT CONCAT(u.nombres,' ',u.apellidos) AS vendedor,
            i.nombreInstitucion,  ciu.nombre AS ciudad, it.ciudad AS ciudad_temporal, t.capacitador, t.tema,
             c.*
             FROM seminarios c
            LEFT JOIN institucion i ON c.id_institucion = i.idInstitucion
            LEFT JOIN usuario u ON c.id_usuario = u.idusuario
            LEFT JOIN ciudad ciu ON ciu.idciudad = i.ciudad_id
            LEFT JOIN seguimiento_institucion_temporal it ON c.institucion_id_temporal = it.institucion_temporal_id
            LEFT JOIN capacitacion_temas t ON  c.tema_id = t.id
            WHERE c.fecha_inicio_temp = '$request->fecha'
            AND c.estado = '1'
            AND c.tipo_webinar = '2' 
            ");
            return $capacitaciones;
            
        }
        else{
            $temas = DB::SELECT("SELECT c.*   FROM capacitacion_temas c
            WHERE c.estado = '1'
            ");
            return $temas;
        }
    
    }
    public function solicitarTema(Request $request){
        //para eliminar la solicitud
    if($request->eliminar){
        $deleted = DB::table('capacitacion_solicitudes')->where('id', '=', $request->id)->delete();
        return "se elimino";
    }
     //para traer todas las solicitudes de los asesores
      if($request->todo){
          $todo = $this->todasSolicitudes();
         return $todo;
      }
      if($request->listado){
        $listadoAsesor = $this->solicitudTemasAsesor($request->asesor_id);
        return $listadoAsesor;
      }
      $ingreso = DB::insert('insert into capacitacion_solicitudes (tema, asesor_id,observacion) values (?, ?,?)', [$request->tema, $request->asesor_id, $request->observacion]);
      if($ingreso){
            return ["status" => "1","message" => "Se solicito correctamente"];
      }else{
            return ["status" => "0","message" => "No se pudo solicitar"]; 
      }
    }
    public function editarSolicitudTema(Request $request){
        DB::table('capacitacion_solicitudes')
        ->where('id', $request->id)
        ->where('asesor_id', $request->asesor)
        ->update([
            'comentario_admin' => $request->comentario,
            'estado' => $request->estado,
            
        ]);
        return "se editor correctamente";
    }
    public function todasSolicitudes(){
        $todo = DB::SELECT("SELECT  c.* , CONCAT(u.nombres, ' ', u.apellidos) AS asesor
        FROM  capacitacion_solicitudes c
       LEFT JOIN usuario u ON u.idusuario = c.asesor_id
       ORDER BY c.id DESC
       LIMIT 50
       ");
       return $todo;
    }
    public function solicitudTemasAsesor($asesor){
        $listado = DB::SELECT("SELECT  * FROM  capacitacion_solicitudes
        WHERE asesor_id = '$asesor'
        ORDER BY id DESC
        LIMIT 50
        ");
        return $listado;
    }
    public function store(Request $request)
    {
       
        //para editar la capacitacion agenda
        if( $request->id != 0 ){
            $agenda = Seminarios::find($request->id);
        //para guardar la capacitacion agenda  
        }else{
            $agenda = new Seminarios();
            $agenda->fecha_fin = $request->fecha_fin;
        } 
        //si crean una insitucion temporal
        if($request->estado_institucion_temporal == 1 ){
            $agenda->periodo_id = $request->periodo_id_temporal;
            $agenda->institucion_id_temporal = $request->institucion_id_temporal;
            $agenda->nombre_institucion_temporal = $request->nombreInstitucion;
            $agenda->id_institucion = "";
        }
        if($request->estado_institucion_temporal == 0){
            $agenda->id_institucion = $request->id_institucion;
            $agenda->institucion_id_temporal = "";
            $agenda->nombre_institucion_temporal = "";
              //para traer el periodo
              $buscarPeriodo = $this->traerPeriodo($request->id_institucion);
              if($buscarPeriodo["status"] == "1"){
                  $obtenerPeriodo = $buscarPeriodo["periodo"][0]->periodo;
                  $agenda->periodo_id = $obtenerPeriodo;
              }
        }
        $agenda->descripcion = $request->fecha_inicio;
        $agenda->tipo_webinar = "2";
        $agenda->id_usuario = $request->idusuario;
        $agenda->nombre = $request->nombre;
        $agenda->label = $request->label;
        $agenda->classes = $request->classes;
        $agenda->fecha_inicio = $request->fecha_inicio;
        $agenda->fecha_inicio_temp = $request->fecha_inicio;
        $agenda->tipo = $request->tipo;
        if($request->observacion_admin == "null"){
            $agenda->observacion_admin = "";    
        }else{
            $agenda->observacion_admin = $request->observacion_admin;   
        }
        $agenda->hora_inicio = $request->hora_inicio;
        $agenda->hora_fin = $request->hora_fin;
        $agenda->tema_id = $request->tema_id;
        // $agenda->capacitador = $request->capacitador;
        $agenda->estado_institucion_temporal =$request->estado_institucion_temporal;
        $agenda->save();
        return 
        $agenda;
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
    public function delete_agenda_asesor($id_agenda)
    {
        DB::DELETE("DELETE FROM `seminarios` WHERE `id_seminario` = $id_agenda");
    }
    public function edit_agenda_admin(Request $request)
    {
        $agenda = Capacitacion::find($request->id);
        $agenda->personas =$request->personas;
        $agenda->observacion =$request->observacion;
        $agenda->estado =$request->estado;
        $agenda->startDate = $request->endDate;
        $agenda->endDate = $request->endDate;
        $agenda->save();
    }

    public function filtroCapacitacionInstitucion(Request $request){
        // $periodo = $this->periodosActivos();
        // if(count($periodo) < 0){
        //     return ["status" => "0","No existe periodos activos"];
        // }
        //almacenar los periodos
        // $periodo1 = $periodo[0]->idperiodoescolar;
        // $periodo2 = $periodo[1]->idperiodoescolar;
        $filtro = DB::SELECT("SELECT p.periodoescolar, i.nombreInstitucion, a.*  
        FROM seminarios a 
        LEFT JOIN institucion i ON a.id_institucion = i.idInstitucion
        LEFT JOIN periodoescolar p ON a.periodo_id = p.idperiodoescolar
        WHERE a.id_usuario = '$request->asesor_id'
        AND a.tipo_webinar = '2'
        AND a.estado = '1'
        ORDER BY a.id_seminario DESC
        LIMIT 100    
        ");
        return $filtro;
    }
    public function periodosActivos(){
        $periodo = DB::SELECT("SELECT DISTINCT  p.* FROM periodoescolar p
        LEFT JOIN  codigoslibros c ON p.idperiodoescolar  = c.id_periodo
        WHERE  p.estado = '1'
        ");
        return $periodo;
    }
    public function getCapacitadores(){
        $query = DB::SELECT("SELECT u.idusuario, CONCAT(u.nombres, ' ',u.apellidos) AS capacitador
        FROM 
       usuario u
       WHERE u.capacitador = '1'");
       return $query;
    }
}
