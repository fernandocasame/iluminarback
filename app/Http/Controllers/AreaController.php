<?php

namespace App\Http\Controllers;

use App\Models\Area;
use DB;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // return csrf_token();
        $area = DB::SELECT("SELECT a.*, t.nombretipoarea
         FROM area a 
         LEFT JOIN tipoareas t ON a.tipoareas_idtipoarea  = t.idtipoarea 
        ORDER  BY idarea ASC
        ");

        $tipoArea = DB::SELECT("SELECT tipoareas.* FROM tipoareas");
        return["area" => $area, "tipoArea" => $tipoArea];
    }

    public function select(Request $request)
    {
        //$area = Area::paginate(5);
        // return $area;
        $area = DB::SELECT("SELECT * FROM areas where estado = '1'");
        foreach ($area as $key => $post) {
            // $respuesta = DB::SELECT("SELECT DISTINCT a.idasignatura as id, a.nombreasignatura as name
            // FROM asignatura  a
            // join area on area.idarea = a.area_idarea
            // LEFT JOIN libro l ON l.asignatura_idasignatura = a.idasignatura
            // LEFT JOIN libros_series ls ON l.idlibro = ls.idLibro
            // WHERE a.area_idarea = ?
            // AND a.estado = '1'
            // AND (ls.version <> 'PLUS' OR ls.version IS NULL)
            // ",[$post->idarea]);
            $respuesta = DB::SELECT("SELECT DISTINCT l.idlibro as id, l.nombrelibro as name
            FROM libro l
            join area on area.idarea = l.area_id
            LEFT JOIN libros_series ls ON l.idlibro = ls.idLibro
            WHERE l.area_id = ?
            AND l.Estado_idEstado = '1'
            AND (ls.version <> 'PLUS' OR ls.version IS NULL)
            AND ls.year = '$request->nivel'
            ",[$post->idarea]);
            $data['items'][$key] = [
                'id' => "a".$post->idarea,
                'name' => $post->nombrearea,
                'children'=>$respuesta,
            ];
        }
        return $data;
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
        
       if($request->idarea){
       
        $area = Area::findOrFail($request->idarea);
        $area->nombrearea = $request->nombrearea;
        $area->tipoareas_idtipoarea = $request->idtipoarea;

       }else{
           $area = new Area;
           $area->nombrearea = $request->nombrearea;
           $area->tipoareas_idtipoarea = $request->idtipoarea;
       }
       $area->save();
       if($area){
           return "Se guardo correctamente";
       }else{
           return "No se pudo guardar/actualizar";
       }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function show(Area $area)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function edit(Area $area)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Area $area)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Area  $area
     * @return \Illuminate\Http\Response
     */
    public function areaeliminar(Request $request)
    {
        DB::table('area')
        ->where('idarea', $request->idarea)
        ->update(['estado' => $request->estado]);

    }
}
