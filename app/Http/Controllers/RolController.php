<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // if(\Auth::user()->id_group == 11){
        //     return $data = [
        //         ['id'=>10,
        //         'level'=>'Director',
        //         'deskripsi'=>'Director'],
        //         ['id'=>4,
        //         'level'=>'Estudiantes',
        //         'deskripsi'=>'Estudiantes'],
        //         ['id'=>6,
        //         'level'=>'Docente',
        //         'deskripsi'=>'Docente'],
        //     ];
        // }
        // if(\Auth::user()->id_group == 10){
        //     return $data = [
        //         ['id'=>4,
        //         'level'=>'Estudiantes',
        //         'deskripsi'=>'Estudiantes'],
        //         ['id'=>6,
        //         'level'=>'Docente',
        //         'deskripsi'=>'Docente'],
        //     ];
        // }
        // if(\Auth::user()->id_group == 1){
            $rol = Rol::all();
            return $rol;
        //}
    }

    public function select()
    {
        if(\Auth::user()->id_group == 11){
            return $data = [
                ['id'=>'11',
                'level'=>'Asesor',
                'deskripsi'=>'Asesor'],
                ['id'=>'4',
                'level'=>'Estudiantes',
                'deskripsi'=>'Estudiantes'],
                ['id'=>'6',
                'level'=>'Docente',
                'deskripsi'=>'Docente'],
            ];
        }
        if(\Auth::user()->id_group == 1){
            $rol = Rol::all();
            return $rol;
        }
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
     * @param  \App\Rol  $rol
     * @return \Illuminate\Http\Response
     */
    public function show(Rol $rol)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Rol  $rol
     * @return \Illuminate\Http\Response
     */
    public function edit(Rol $rol)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Rol  $rol
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Rol $rol)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Rol  $rol
     * @return \Illuminate\Http\Response
     */
    public function destroy(Rol $rol)
    {
        //
    }
}
