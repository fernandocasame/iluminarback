<?php

namespace App\Http\Controllers;

use App\Models\AsignaturaDocente;
use Illuminate\Http\Request;
use DB;
use App\Models\Curso;
use App\Quotation;
class AsignaturaDocenteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $usuario = DB::select("CALL `asignaturasDocente` ( $request->idusuario );");
        return $usuario;
    }

    public function asignaturas_crea_docente($id)
    {
        $asignaturas = DB::SELECT("SELECT a.idasignatura, a.nombreasignatura FROM asignatura a, asignaturausuario au WHERE a.idasignatura = au.asignatura_idasignatura AND au.usuario_idusuario = $id AND a.tipo_asignatura = 0 AND a.estado = '1'");
        return $asignaturas;
    }

    
    public function deshabilitarasignatura($id)
    {
        $asignatura = DB::UPDATE("UPDATE asignatura SET estado = '0' WHERE idasignatura = $id");

        return $asignatura;
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
        DB::SELECT("DELETE FROM `asignaturausuario` WHERE usuario_idusuario = ?",[$request->usuario_idusuario]);
        foreach ($request->asignaturas as $key => $post) {
            $asignatura = new AsignaturaDocente();
            $asignatura->usuario_idusuario = $request->usuario_idusuario;
            $asignatura->asignatura_idasignatura = $post;
            $asignatura->save();
        }
    }


    
     public function guardar_asignatura_usuario(Request $request)
    {
        $asignatura = new AsignaturaDocente();
        $asignatura->usuario_idusuario = $request->usuario_idusuario;
        $asignatura->asignatura_idasignatura = $request->asignatura_idasignatura;

        $asignatura->save();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\AsignaturaDocente  $asignaturaDocente
     * @return \Illuminate\Http\Response
     */
    public function show(AsignaturaDocente $asignaturaDocente)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\AsignaturaDocente  $asignaturaDocente
     * @return \Illuminate\Http\Response
     */
    public function edit(AsignaturaDocente $asignaturaDocente)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\AsignaturaDocente  $asignaturaDocente
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AsignaturaDocente $asignaturaDocente)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\AsignaturaDocente  $asignaturaDocente
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $respuesta=DB::delete('DELETE FROM `asignaturausuario` WHERE  idasiguser = ?',[$request->asignatura_idasignatura]);
        return $respuesta;
    }
    public function asignaturas_x_docente(Request $request)
    {
        $dato = DB::table('asignaturausuario as ausu')
        ->where('ausu.idcurso','=',$request->idcurso)
        ->leftjoin('libro as asig','ausu.asignatura_idasignatura','=','asig.idlibro')
        ->select('asig.nombrelibro as nombreasignatura','asig.idlibro as idasignatura','asig.area_id as area_idarea', 'ausu.idcurso','ausu.asignatura_idasignatura','ausu.idasiguser as idasignado')
        ->get();
        return $dato;
    }
    public function asignar_asignatura_docentes(Request $request)
    {
        $dato = DB::table('asignaturausuario')
        ->where('idcurso','=',$request->idcurso)
        ->where('asignatura_idasignatura','=',$request->asignatura_idasignatura)
        ->get();
        if ($dato->count() > 0) {
            return $dato->count();
        }else{
            $asignatura = new AsignaturaDocente();
            $asignatura->idcurso = $request->idcurso;
            $asignatura->asignatura_idasignatura = $request->asignatura_idasignatura;            
            $asignatura->save();
            // $this->addCurso($request->asignatura_idasignatura,$request->idcurso);
            return $asignatura;
        }
    }
  
    public function addCurso($id_asignatura,$idusuario)
    {
        $periodo=DB::SELECT("SELECT u.idusuario, i.idInstitucion, MAX(pi.periodoescolar_idperiodoescolar) AS periodo FROM usuario u, institucion i, periodoescolar_has_institucion pi WHERE u.institucion_idInstitucion = i.idInstitucion AND i.idInstitucion = pi.institucion_idInstitucion AND u.idusuario = $idusuario");

        $curso = Curso::create([
            'nombre' => 'DEMO',
            'id_asignatura'=> $id_asignatura,
            'seccion' => 'DEMO',
            'materia' => 'DEMO',
            'aula' => 'DEMO',
            'codigo' => $this->codigo(8),
            'idusuario'=> $idusuario,
            'id_periodo'=> $periodo[0]->periodo,
        ]);
    }

    function codigo($count)
    {
        // This string MUST stay FS safe, avoid special chars
        $base = 'ABCDEFGHKMNPRSTUVWXYZ123456789';
        $ret = '';
        $strlen = \strlen($base);
        for ($i = 0; $i < $count; ++$i) {
            $ret .= $base[random_int(0, $strlen - 1)];
        }

        return $ret;
    }
    public function eliminaAsignacion($id)
    {
        //validar que la materia no tenga asignado un profesor
        $validate = DB::SELECT("SELECT * FROM asignaturausuario ac
        WHERE ac.idasiguser = '$id'
        AND (ac.docente_id = '0' OR ac.docente_id IS null)
        ");
        if(empty($validate)){
            return ["status" => "0", "message" => "Existe un docente asignado a esta materia"];
        }
        $data = AsignaturaDocente::find($id);
        $data->delete();
        return $data;
    }
    public function quitarTodasAsignaturasDocente(Request $request)
    {
        $ids = explode(",",$request->idasiguser);
        $data = AsignaturaDocente::destroy($ids);
        return $data;
     
    }
}
