<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;///instanciamos base de datos para poder hacer consultas con varias tablas
use App\Models\Evaluaciones;//modelo Evaluaciones.php

class EvaluacionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {   
        $evaluaciones = DB::SELECT("SELECT e.id, e.nombre_evaluacion, e.id_asignatura,e.id_docente, e.descripcion, e.puntos, e.fecha_inicio, e.fecha_fin, e.duracion, e.estado, a.nombreasignatura FROM evaluaciones e, asignatura a WHERE e.id_asignatura = a.idasignatura");
        
        //return Evaluaciones::all();
        return $evaluaciones;

    }


    public function evaluacionesDocente(Request $request)
    {   
        $evaluaciones = DB::SELECT("SELECT DISTINCT c.nombre as nombre_curso, c.materia, c.aula, c.seccion, e.codigo_curso, e.id, e.nombre_evaluacion, e.id_asignatura,e.id_docente, e.descripcion, e.puntos, e.fecha_inicio, e.fecha_fin, e.duracion, e.estado, a.nombreasignatura, e.created_at, e.id_tipoeval, et.tipo_nombre, e.grupos_evaluacion, e.cant_unidades FROM evaluaciones e, asignatura a, curso c, eval_tipos et WHERE e.id_asignatura = a.idasignatura and e.id_docente = $request->docente AND e.codigo_curso = '$request->codigo' AND e.codigo_curso = c.codigo AND et.id = e.id_tipoeval ORDER BY e.created_at DESC");
        
        return $evaluaciones;
    }

    public function TiposEvaluacion()
    {
        $tiposevaluacion = DB::SELECT("SELECT tipo_nombre as label, id FROM eval_tipos WHERE tipo_estado = 1");
        return $tiposevaluacion;
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
    public function store(Request $request)//request datos que ingreso en los input del formulario
    {//agregar

        if( $request->id ){
            $evaluacion = Evaluaciones::find($request->id);
        }else{
            $evaluacion = new Evaluaciones();
        }

        $evaluacion->nombre_evaluacion = $request->nombre;
        $evaluacion->id_asignatura = $request->asignatura;
        $evaluacion->descripcion = $request->descripcion;
        $evaluacion->puntos = $request->puntos;
        $evaluacion->fecha_inicio = $request->fecha_inicio;
        $evaluacion->fecha_fin = $request->fecha_fin;
        $evaluacion->duracion = $request->duracion;
        $evaluacion->estado = $request->estado;
        $evaluacion->id_docente = $request->docente;
        $evaluacion->codigo_curso = $request->codigo;
        $evaluacion->id_tipoeval = $request->idtipoeval;
        $evaluacion->grupos_evaluacion = $request->id_grupo_opciones;
        $evaluacion->cant_unidades = $request->cant_unidades;
        $evaluacion->save();

        return $evaluacion;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $evaluaciones = DB::SELECT("SELECT * FROM evaluaciones WHERE id = $id");

        if($evaluaciones){
            return $evaluaciones;
        }else{
            return 0;
        }
    }


    
    public function evaluacionesEstudianteCurso(Request $request)
    {
        $evaluaciones = DB::SELECT("SELECT DISTINCT es.grupo, cu.nombre as nombre_curso, cu.seccion, cu.materia, cu.aula, e.id, e.nombre_evaluacion, e.descripcion, e.puntos, e.fecha_inicio, e.fecha_fin, e.duracion, e.estado, es.usuario_idusuario as id_estudiante, a.nombreasignatura FROM evaluaciones e, estudiante es, curso cu, asignatura a WHERE e.codigo_curso = es.codigo AND e.estado = 1 AND es.usuario_idusuario = $request->estudiante AND es.usuario_idusuario NOT IN (SELECT c.id_estudiante from calificaciones c WHERE c.id_evaluacion = e.id) AND es.codigo = cu.codigo AND cu.codigo = '$request->codigo' AND e.id_asignatura = a.idasignatura");

        return $evaluaciones;
    }

    
    public function evalCompleEstCurso(Request $request)
    {   
        $evaluaciones = DB::SELECT("SELECT DISTINCT e.id, c.grupo,
         c.calificacion, e.nombre_evaluacion, e.descripcion,
          e.puntos, e.fecha_inicio, e.fecha_fin, e.duracion, a.nombreasignatura
           FROM calificaciones c, evaluaciones e, estudiante es, asignatura a 
           WHERE c.id_evaluacion = e.id
            AND c.id_estudiante = $request->estudiante
             AND e.codigo_curso = '$request->codigo'
              AND es.codigo = e.codigo_curso
               AND es.usuario_idusuario = c.id_estudiante
                AND e.estado = 1
                 AND e.id_asignatura = a.idasignatura
                  ORDER BY e.id");

        return $evaluaciones;
    }


     public function verCalificacionEval($codigo)
    {   
        $estudiantes = DB::SELECT("CALL getCalificacionesEval ('$codigo');");

        /*$estudiantes = DB::SELECT("SELECT DISTINCT e.id, e.usuario_idusuario, e.codigo, u.cedula, u.nombres, u.apellidos, e.estado as estado_estudiante, u.estado_idEstado as estado_usuario, e.created_at FROM estudiante e, usuario u WHERE e.usuario_idusuario = u.idusuario AND u.estado_idEstado = 1 AND e.codigo = '$codigo'");*/

        if(!empty($estudiantes)){
            foreach ($estudiantes as $key => $value) {
                $calificaciones = DB::SELECT("SELECT DISTINCT e.id, e.nombre_evaluacion, e.puntos, e.duracion, es.usuario_idusuario, (SELECT c.calificacion FROM calificaciones c WHERE c.id_estudiante = es.usuario_idusuario AND c.id_evaluacion = e.id) as calificacion FROM evaluaciones e, estudiante es WHERE e.codigo_curso = ? AND e.codigo_curso = es.codigo AND es.usuario_idusuario = ?",[$codigo, $value->usuario_idusuario]);

                $total = DB::SELECT("SELECT DISTINCT * FROM evaluaciones e WHERE e.codigo_curso = ?",[$codigo]);

                $data['items'][$key] = [
                    'id' => $value->id,
                    'cedula' => $value->cedula,
                    'nombres' => $value->nombres,
                    'apellidos' => $value->apellidos,
                    'usuario_idusuario' => $value->usuario_idusuario,
                    'codigo' => $value->codigo,
                    'estado_estudiante' => $value->estado_estudiante,
                    'estado_usuario' => $value->estado_usuario,
                    'created_at' => $value->created_at,
                    'calificaciones'=>$calificaciones,
                    'total'=>$total,
                ];            
            }
        }else{
            $data = [];
        }
        return $data;
    }


    
    public function verEstCursoEval($id)
    {        
        $estudiantes = DB::SELECT("SELECT DISTINCT e.grupo, u.idusuario, u.nombres, u.apellidos, u.cedula, u.email, u.telefono FROM estudiante e, usuario u WHERE e.codigo = '$id' AND e.usuario_idusuario = u.idusuario AND e.estado = '1' ORDER BY e.grupo");

        return $estudiantes;
    }


    public function asignarGrupoEst(Request $request)
    {        
        $estudiantes = DB::UPDATE("UPDATE estudiante SET grupo = $request->grupo WHERE usuario_idusuario = $request->estudiante AND codigo = '$request->codigo'");

        return $estudiantes;
    }
    
    public function verEvalCursoExport($codigo)
    {
        $evaluaciones = DB::SELECT("SELECT DISTINCT * FROM evaluaciones e WHERE e.codigo_curso = '$codigo'");

        return $evaluaciones; 
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
        $evaluacion = Evaluaciones::find($id);
        $evaluacion->nombre_evaluacion = $request->nombre_evaluacion;
        $evaluacion->id_asignatura = $request->id_asignatura;
        $evaluacion->descripcion = $request->descripcion;
        $evaluacion->puntos = $request->puntos;
        $evaluacion->fecha_inicio = $request->fecha_inicio;
        $evaluacion->fecha_fin = $request->fecha_fin;
        $evaluacion->estado = $request->estado;
        $evaluacion->save();

        return $evaluacion;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    
    public function eliminar_evaluacion($id_evaluacion)
    {
        $evaluacion = DB::SELECT("SELECT * FROM calificaciones WHERE id_evaluacion = $id_evaluacion");

        if($evaluacion){
            return 0;
        }else{
            $preguntas = DB::DELETE("DELETE FROM `pre_evas` WHERE `id_evaluacion` = $id_evaluacion");
            $eval = DB::DELETE("DELETE FROM `evaluaciones` WHERE `id` = $id_evaluacion");
        }
    }




    public function destroy($id_evaluacion)
    {
        $evaluacion = Evaluaciones::find($id_evaluacion);
        $evaluacion->delete();
    }
}