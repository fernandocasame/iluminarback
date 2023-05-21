<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\SalleAreas;
use App\Models\SalleAsignaturas;

class SalleAreasController extends Controller
{
    public function index(Request $request)
    {   
        $areas = DB::SELECT("SELECT * FROM salle_areas");

        return $areas;

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
    public function store(Request $request){

    }
    public function crea_area_salle(Request $request)
    {
        if( $request->id_area ){
            $area = SalleAreas::find($request->id_area);
        }else{
            $area = new SalleAreas();
        }

        $area->nombre_area = $request->nombre_area;
        $area->descripcion_area = $request->descripcion_area;
        $area->estado = $request->estado;
        $area->save();

        return $area;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $areas = DB::SELECT("SELECT * FROM salle_areas WHERE id_area = $id");

        return $areas;
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
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        // $area = SalleAreas::find($request->id);

        // if($area->delete()){
        //     return 1;
        // }else{
        //     return 0;
        // }

    }
    public function areasSinBasica()
    {   
        $areas = DB::SELECT("SELECT * FROM salle_areas WHERE id_area != '1' ");
        return $areas;
    }

    public function eliminaArea($id)
    {
        $contar = DB::table('salle_asignaturas as asig')
        ->where('asig.id_area','=',$id)
        ->count();
        if($contar > 0){
            return $contar;
        }else{
            $area = SalleAreas::find($id);
            $area->delete();
           return $area;
        }
    }
}
