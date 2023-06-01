<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Provider\Image;
use Illuminate\Support\Facades\File;


class SalleReportesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
    
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


    // REPORTES
    
    public function reporte_evaluaciones_institucion($fecha)
    {   
        // set_time_limit(600000);
        // ini_set('max_execution_time', 600000);

        // PROCEDIMIENTO OBTIENE EVALUACIONES AGRUPADAS POR INSTITUCION (una evaluacion solo puede pertenecer a un docente)
        $evaluaciones = DB::SELECT("CALL `salle_reporte_evaluaciones_institucion` (?);", [$fecha]);
        // dump($evaluaciones);
        if(!empty($evaluaciones)){
            foreach ($evaluaciones as $key => $value) {
                $vector_evaluaciones = explode(",", $value->evaluaciones);
                $promedio_eval_inst = 0;
                $acum_eval = 0; $acum_doc = 0;
                // dump('*********************************institucion: ' . $value->idInstitucion);
                foreach ($vector_evaluaciones as $keyE => $valueE){

                    $puntaje_respuestas = DB::SELECT("CALL salle_puntaje_respuestas (?);",[$valueE]);
                    // se acumula los puntajes de cada evaluacion por institucion
                    $acum_eval = $acum_eval + $puntaje_respuestas[0]->puntaje;
                    // dump($puntaje_respuestas[0]->puntaje .' - ' .$valueE);
                    $puntaje_por_pregunta = DB::SELECT("CALL salle_puntaje_pregunta (?);",[$valueE]);
                    foreach ($puntaje_por_pregunta as $keyP => $valueP){
                        //puntaje obtenido por cada docente, cada evaluacion se califica por puntajes diferentes
                        $acum_doc = $acum_doc + $valueP->puntaje;
                    }
                    // dump($acum_doc);
                    // $acum_doc = 0;
                }
                // dump($calificaciones);
                $promedio_eval_inst = ( $acum_doc * 100 ) / $acum_eval;

                $promedio_eval_inst = floatval(number_format($promedio_eval_inst, 2));
                
                $data['items'][$key] = [
                    'idInstitucion' => $value->idInstitucion,
                    'nombreInstitucion' => $value->nombreInstitucion,
                    'fecha_evaluacion' => $value->fecha_evaluacion,
                    'ciudad_id' => $value->ciudad_id,
                    'puntaje' => $promedio_eval_inst,
                    'cant_evaluaciones' => count($vector_evaluaciones)
                ];            
            }
        }else{
            $data = [];
        }
        return $data;
    }


    public function salle_promedio_areas($periodo, $institucion){
        //estado = 2; resuelta
        $evaluaciones = DB::SELECT("CALL salle_evaluaciones_institucion ($periodo, $institucion);");
        
        $data_evaluaciones = array();
        
        foreach ($evaluaciones as $key => $value) {
            // areas de cada evaluacion
            $areas = DB::SELECT("CALL salle_areas_evaluacion (?);",[$value->id_evaluacion]);

            $data_areas = array(); $promedio_eval_area_acum = 0;
            foreach ($areas as $keyR => $valueR){
                 $calif_area_eval = 0; $calif_area_doc = 0; $promedio_eval_area = 0;
                // puntaje de la evaluacion por area
                $puntaje_areas = DB::SELECT("CALL salle_puntaje_evaluacion_areas (?, ?)",[$value->id_evaluacion, $valueR->id_area]);

                $calif_area_eval = $puntaje_areas[0]->puntaje;
            
                $puntaje_por_pregunta = DB::SELECT("CALL salle_puntaje_area (?, ?);",[$value->id_evaluacion, $valueR->id_area]);

                foreach ($puntaje_por_pregunta as $keyP => $valueP){
                    //puntaje obtenido de cada docente por area
                    $calif_area_doc = $calif_area_doc + $valueP->puntaje;
                }

                if( $calif_area_doc <= 0 ){ $promedio_eval_area = 0; }
                else{ $promedio_eval_area = ( $calif_area_doc * 100 ) / $calif_area_eval; }

                if( $promedio_eval_area > 100 ){ $promedio_eval_area = 100; }
                
                $data_areas[$keyR] = [
                    'id_area' => $puntaje_areas[0]->id_area,
                    'nombre_area' => $puntaje_areas[0]->nombre_area,
                    'puntaje' => floatval(number_format($promedio_eval_area, 2)),
                    'cant_preguntas' => $puntaje_areas[0]->cant_preguntas
                ];

                $promedio_eval_area_acum += $promedio_eval_area;
            }

            if( count($areas) > 0 ){
                $puntaje_evaluacion = $promedio_eval_area_acum / count($areas);
            }else{
                $puntaje_evaluacion = 0;
            }

            $data_evaluaciones['items'][$key] = [
                'id_evaluacion' => $value->id_evaluacion,
                'puntaje_evaluacion' => floatval(number_format($puntaje_evaluacion, 2)),
                'nombre_docente' => $value->nombre_docente,
                'areas' => $data_areas
            ];

        }
        // esta data devuelve los promedios por areas de cada evaluacion, se debe procesar en el front
        return $data_evaluaciones;
    }



    public function salle_promedio_asignatura($periodo, $institucion, $id_area){
        $evaluaciones = DB::SELECT("CALL salle_evaluaciones_institucion_area ($periodo, $institucion, $id_area);");
        
        $data_evaluaciones = array();
        
        foreach ($evaluaciones as $key => $value) {
            // asignaturas de cada evaluacion
            $asignaturas = DB::SELECT("CALL salle_asignaturas_evaluacion (?, ?);",[$value->id_evaluacion, $id_area]);

            $data_asignaturas = array(); $promedio_eval_asig_acum = 0;
            foreach ($asignaturas as $keyA => $valueA){
                 $calif_asig_eval = 0; $calif_asig_doc = 0; $promedio_eval_asig = 0; $promedio_eval_asignatura = 0;
                // puntaje de la evaluacion por asignatura
                $puntaje_asignaturas = DB::SELECT("CALL salle_puntaje_evaluacion_asignaturas (?, ?);",[$value->id_evaluacion, $valueA->id_asignatura]);

                $calif_asig_eval = $puntaje_asignaturas[0]->puntaje;
            
                $puntaje_por_pregunta = DB::SELECT("CALL salle_puntaje_pregunta_asig (?, ?);",[$value->id_evaluacion, $valueA->id_asignatura]);

                foreach ($puntaje_por_pregunta as $keyP => $valueP){
                    //puntaje obtenido de cada docente por asig
                    $calif_asig_doc = $calif_asig_doc + $valueP->puntaje;
                }

                if( $calif_asig_doc <= 0 ){ $promedio_eval_asig = 0; }
                else{ $promedio_eval_asig = ( $calif_asig_doc * 100 ) / $calif_asig_eval; }
                
                if( $promedio_eval_asig > 100 ){ $promedio_eval_asig = 100; }

                $data_asignaturas[$keyA] = [
                    'id_asignatura' => $puntaje_asignaturas[0]->id_asignatura,
                    'nombre_asignatura' => $puntaje_asignaturas[0]->nombre_asignatura,
                    'puntaje' => floatval(number_format($promedio_eval_asig, 2)),
                    'cant_preguntas' => $puntaje_asignaturas[0]->cant_preguntas
                ];

                $promedio_eval_asig_acum += $promedio_eval_asig;
            }

            if( count($asignaturas) > 0 ){
                $puntaje_evaluacion = $promedio_eval_asig_acum / count($asignaturas);
            }else{
                $puntaje_evaluacion = 0;
            }
            

            $data_evaluaciones['items'][$key] = [
                'id_evaluacion' => $value->id_evaluacion,
                'puntaje_evaluacion' => floatval(number_format($puntaje_evaluacion, 2)),
                'nombre_docente' => $value->nombre_docente,
                'asignaturas' => $data_asignaturas
            ];

        }
        // esta data devuelve los promedios por asignaturas de cada evaluacion, se debe procesar en el front
        return $data_evaluaciones;
    }



    public function salle_promedios_tipos_pregunta($periodo, $institucion, $id_asignatura){

    }


}