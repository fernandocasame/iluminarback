<?php

namespace App\Http\Controllers;

 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;///instanciamos base de datos para poder hacer consultas con varias tablas
use App\Models\Calificaciones;//modelo Calificaciones.php
use App\Models\Evaluaciones;
use App\Models\MatCalificaciones1Quimestre;
use App\Models\MatCalificaciones2Quimestre;
use App\Models\MatCalificaciones;
class CalificacionEvalController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return Calificaciones::all();
            
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
        $validate = DB::SELECT("SELECT * FROM calificaciones c 
        WHERE c.id_estudiante = '$request->estudiante'
        AND c.id_evaluacion = '$request->evaluacion'
        ");
        if(empty($validate)){
            $calificacion = new Calificaciones();
            $calificacion->id_estudiante = $request->estudiante;
            $calificacion->id_evaluacion = $request->evaluacion;
            $calificacion->grupo         = $request->grupo;
            $calificacion->calificacion  = $request->calificacion;
            $calificacion->evId          = $request->evId;
            $calificacion->save();
            //validar que el estudiante tenga un registro en las calificaciones
            if($request->quimestre == 1){
                $this->saveEstudiante1quimestre($request->estudiante,$request->curso_id,$request->materia_id,$request->evId,$request->calificacion);
            }else{
                $this->saveEstudiante2quimestre($request->estudiante,$request->curso_id,$request->materia_id,$request->evId,$request->calificacion);
            }
            return $calificacion;
        }
        return $validate;
    }

    public function saveEstudiante1quimestre($idusuario,$curso_id,$materia_id,$evId,$calificacion){
       //traigo el id del registro de notas
       $validate = DB::SELECT("SELECT * FROM mat_calificaciones q
       WHERE q.curso_id = '$curso_id'
       AND q.materia_id = '$materia_id'
       AND q.estudiante_id = '$idusuario'
       ");
       $id = 0;
        if(empty($validate)){
            $quimestre = new MatCalificaciones();
            $quimestre->curso_id        = $curso_id;
            $quimestre->materia_id      = $materia_id;
            $quimestre->estudiante_id   = $idusuario;
            $quimestre->save();
            $id = $quimestre->id;
            //formatos de calificaciones
            //Primer quimestre
            $this->crearFormatoPrimerQ($idusuario,$id);
            //Segundo quimestre
            $this->crearFormatoSegundoQ($idusuario,$id);
        }else{
            $id = $validate[0]->id;
        }
      

    }
    public function saveEstudiante2quimestre($idusuario,$curso_id,$materia_id,$evId,$calificacion){
       //traigo el id del registro de notas
       $validate = DB::SELECT("SELECT * FROM mat_calificaciones q
       WHERE q.curso_id = '$curso_id'
       AND q.materia_id = '$materia_id'
       AND q.estudiante_id = '$idusuario'
       ");
       $id = 0;
        if(empty($validate)){
            $quimestre = new MatCalificaciones();
            $quimestre->curso_id        = $curso_id;
            $quimestre->materia_id      = $materia_id;
            $quimestre->estudiante_id   = $idusuario;
            $quimestre->save();
            $id = $quimestre->id;
            //formatos de calificaciones
            //Primer quimestre
            $this->crearFormatoPrimerQ($idusuario,$id);
            //Segundo quimestre
            $this->crearFormatoSegundoQ($idusuario,$id);
        }
        $id = $validate[0]->id;
    }
    public function crearFormatoPrimerQ($estudiante_id,$id){
        $saveStudent                        = new MatCalificaciones1Quimestre();
        $saveStudent->estudiante_id         = $estudiante_id;
        $saveStudent->mat_calificacion_id   = $id;
        $saveStudent->save();
    }
    public function crearFormatoSegundoQ($estudiante_id,$id){
        $saveStudent2 = new MatCalificaciones2Quimestre();
        $saveStudent2->estudiante_id         = $estudiante_id;
        $saveStudent2->mat_calificacion_id   = $id;
        $saveStudent2->save();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        
    }


    public function verifRespEvaluacion(Request $request)
    {
        $calificaciones = DB::SELECT("SELECT * FROM calificaciones WHERE id_evaluacion = $request->evaluacion AND id_estudiante = $request->estudiante");
        if($calificaciones){
            return $calificaciones;
        }else{
            return 0;
        }
    }
    public function modificarEvaluacion(Request $request)
    {
        $calificacion = DB::UPDATE("UPDATE `calificaciones` SET `calificacion`=$request->calificacion WHERE `id_evaluacion`=$request->evaluacion AND `id_estudiante`=$request->estudiante");
        $respuesta = DB::UPDATE("UPDATE `respuestas_preguntas` SET `puntaje`=$request->puntaje WHERE `id_respuesta_pregunta` = $request->id_respuesta");
    }

    public function guardarRespuesta(Request $request)
    {
        $validate = DB::SELECT("SELECT * FROM respuestas_preguntas r
        WHERE r.id_pregunta = '$request->pregunta'
        AND r.id_evaluacion = '$request->evaluacion'
        AND r.id_estudiante = '$request->estudiante'
        ");
        if(empty($validate)){
            $respuestas = DB::INSERT("INSERT INTO `respuestas_preguntas`(`id_evaluacion`,
            `id_pregunta`, `id_estudiante`, `respuesta`, `puntaje`) 
            VALUES ($request->evaluacion, $request->pregunta, $request->estudiante,
            '$request->respuesta', $request->puntaje)");  
        }
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
        /*$calificacion = Calificaciones::find($id);
        $calificacion->id_estudiante = $request->estudiante;
        $calificacion->id_evaluacion = $request->evaluacion;
        $calificacion->calificacion = $request->calificacion;
        $calificacion->save();
        return $calificacion;*/
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $calificacion = Calificaciones::find($id);
        $calificacion->delete();
    }


     public function evaluacionEstudiante($id)
    {   
        $responder = DB::SELECT("SELECT e.*,l.nombrelibro, now() as fecha_actual 
        FROM evaluaciones e, libro l
        WHERE e.id = $id
        AND e.id_asignatura = l.idlibro
        AND e.estado = 1
        ");
        if($responder){
            return $responder;
        }else{
            return 0;
        }
    }
    
}