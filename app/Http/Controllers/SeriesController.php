<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Series;

class SeriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Series::all();
    }


    public function series_formato_periodo($periodo)
    {//SOLO FORMATOS YA CONFIGURADOS INNER
        $series = DB::SELECT("SELECT * FROM series s WHERE s.id_serie != 6"); // omitir plan lector
        $datos = array();
        foreach($series as $key => $value){
            $areas = DB::SELECT("SELECT DISTINCT ar.idarea, ar.nombrearea, t.nombretipoarea, pf.* FROM area ar
            INNER JOIN asignatura a ON ar.idarea = a.area_idarea
            INNER JOIN libro l ON a.idasignatura = l.asignatura_idasignatura
            INNER JOIN libros_series ls ON l.idlibro = ls.idLibro
            INNER JOIN tipoareas t ON ar.tipoareas_idtipoarea = t.idtipoarea
            INNER JOIN pedidos_formato pf ON pf.id_area = ar.idarea
            WHERE ls.id_serie = ? AND pf.id_periodo = ? AND pf.id_serie = ? AND ar.estado = '1' AND a.estado = '1' AND l.Estado_idEstado = 1
            GROUP BY ar.idarea
            ORDER BY ar.tipoareas_idtipoarea;", [$value->id_serie, $periodo, $value->id_serie]);

            if( $areas ){
                $datos[$key] = [
                    "id_serie" => $value->id_serie,
                    "nombre_serie" => $value->nombre_serie,
                    "areas" => $areas
                ];
            }


        }

        return $datos;
    }

    public function series_formato_full($periodo)
    {//TODAS LAS SERIES LEFT
        $series = DB::SELECT("SELECT * FROM series s WHERE s.id_serie != 6"); // omitir plan lector
        $datos = array();
        foreach($series as $key => $value){
            $areas = DB::SELECT("SELECT DISTINCT ar.idarea, ar.nombrearea, t.nombretipoarea, pc.* FROM area ar
            INNER JOIN asignatura a ON ar.idarea = a.area_idarea
            INNER JOIN libro l ON a.idasignatura = l.asignatura_idasignatura
            INNER JOIN libros_series ls ON l.idlibro = ls.idLibro
            INNER JOIN tipoareas t ON ar.tipoareas_idtipoarea = t.idtipoarea
            LEFT JOIN pedidos_formato pc ON ar.idarea = pc.id_area
            WHERE ls.id_serie = ? AND ar.estado = '1' AND a.estado = '1' AND l.Estado_idEstado = 1 AND pc.id_periodo = $periodo
            ORDER BY ar.tipoareas_idtipoarea;", [$value->id_serie]);

            $datos[$key] = [
                "id_serie" => $value->id_serie,
                "nombre_serie" => $value->nombre_serie,
                "areas" => $areas
            ];

        }

        return $datos;
    }


    public function series_full()
    {//TODAS LAS SERIES LEFT
        $series = DB::SELECT("SELECT * FROM series s WHERE s.id_serie != 6"); // omitir plan lector
        $datos = array();
        foreach($series as $key => $value){
            $areas = DB::SELECT("SELECT DISTINCT ar.idarea, ar.nombrearea, t.nombretipoarea FROM area ar
            INNER JOIN asignatura a ON ar.idarea = a.area_idarea
            INNER JOIN libro l ON a.idasignatura = l.asignatura_idasignatura
            INNER JOIN libros_series ls ON l.idlibro = ls.idLibro
            INNER JOIN tipoareas t ON ar.tipoareas_idtipoarea = t.idtipoarea
            WHERE ls.id_serie = ? AND ar.estado = '1' AND a.estado = '1' AND l.Estado_idEstado = 1
            ORDER BY ar.tipoareas_idtipoarea;", [$value->id_serie]);

            $datos[$key] = [
                "id_serie" => $value->id_serie,
                "nombre_serie" => $value->nombre_serie,
                "areas" => $areas
            ];

        }

        return $datos;
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
        if($request->id > 0){
            $serie = Series::findOrFail($request->id);
        }else{
            $serie = new Series();
            $serie->nombre_serie        = $request->nombre_serie;
            $serie->longitud_numeros    = $request->longitud_numeros;
            $serie->longitud_letras     = $request->longitud_letras;
            $serie->longitud_codigo     = $request->longitud_codigo;
            $serie->save();
        }
        if($serie){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "1", "message" => "No se pudo guardar"];
        }
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
