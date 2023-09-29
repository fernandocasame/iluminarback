<?php

namespace App\Http\Controllers;

use App\Models\estudiante;
use App\Models\CodigosLibros;
use Illuminate\Http\Request;
use DB;
use Illuminate\Auth\EloquentUserProvider;
use PhpParser\Node\Stmt\Else_;

class EstudianteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $usuarios = DB::select("CALL `estudiantes` ();");
        return $usuarios;
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

    public function estudianteCurso(Request $request){
        $data = [];
        $cursos = DB::SELECT("CALL `cursoAlumno` (?);",[$request->idusuario]);
        foreach ($cursos as $key => $post) {
            $docente = $this->getUser($post->idusuario);
            $data['items'][$key] = [
                'cursos' => $post,
                "docente" => $docente,
                'tareas' => $this->tareasEstudiante($post->idcurso),
                'tareas_pendientes' => $this->tareaEstudiantePendienteD($post->idcurso,$request->idusuario),
                'tareas_realizadas' => $this->tareaEstudianteRealizadaD($post->idcurso,$request->idusuario),
            ];
        }
        return $data;
    }
    public function getUser($usuario){
        $query = DB::SELECT("SELECT CONCAT(u.nombres,' ',u.apellidos) as docente
        FROM usuario u
        WHERE u.idusuario = '$usuario'
        ");
        return $query;
    }
    public function tareaEstudiantePendienteD($idcurso,$idusuario){
        $data=array();
        $tarea = DB::SELECT("SELECT * FROM tarea left join contenido on contenido.idcontenido = tarea.contenido_idcontenido WHERE tarea.curso_idcurso = ? AND tarea.estado = '1' AND tarea.usuario_idusuario IS NULL OR tarea.usuario_idusuario = ?",[$idcurso,$idusuario]);
        foreach ($tarea as $key => $post) {
            $verifica = DB::SELECT("SELECT * FROM usuario_tarea WHERE tarea_idtarea = ? AND usuario_idusuario = ?",[$post->idtarea,$idusuario]);
            if(!empty($verifica)){
            }else{
                array_push ($data , $post);
            }
        }
        return $data;
    }

    public function tareaEstudianteRealizadaD($idcurso,$idusuario){
        $data=array();
        $tarea = DB::SELECT("SELECT * FROM tarea WHERE curso_idcurso = ? AND estado = '1' ",[$idcurso]);
        foreach ($tarea as $key => $post) {
            $verifica = DB::SELECT("SELECT tarea.*,usuario_tarea.*,contenido.url as urldocente,contenido.nombre as tareadocente FROM usuario_tarea join tarea on tarea.idtarea = usuario_tarea.tarea_idtarea left join contenido on contenido.idcontenido = tarea.contenido_idcontenido WHERE tarea_idtarea = ? AND usuario_tarea.usuario_idusuario = ?",[$post->idtarea,$idusuario]);

            if(!empty($verifica)){
                array_push ($data , $verifica[0]);
            }else{
            }
        }
        return $data;
    }

    public function cursoSugerencias(){
        $sugerencias = array();
        $aux = array();
        $idusuario = auth()->user()->idusuario;
        $usuarioId = DB::SELECT(" CALL `sugUsuario` (?);",[$idusuario]);
        $cursos = DB::SELECT("SELECT curso.* FROM estudiante join curso on curso.codigo = estudiante.codigo WHERE estudiante.usuario_idusuario = ?",[$idusuario]);
        foreach ($usuarioId as $key => $value) {
            $sugerencia = DB::SELECT("SELECT * FROM curso WHERE curso.idusuario = ?",[$value->idusuario]);
            foreach ($sugerencia as $key => $val) {
                array_push($sugerencias, $val);
            }
        }
        foreach ($cursos as $key => $value) {
            foreach (array_keys($sugerencias, $value) as $key)
            {
                unset($sugerencias[$key]);
            }
        }
        $aux = array_values($sugerencias);
        return $aux;
    }

    public function remover ($valor,$arr)
    {
        foreach (array_keys($arr, $valor) as $key)
        {
            unset($arr[$key]);
        }
        return $arr;
    }

    public function tareasEstudiante($id){
        $tareas = DB::SELECT('SELECT * FROM tarea WHERE tarea.curso_idcurso = ?', [$id]);
        return $tareas;
    }

    public function tareaEstudiantePendiente(Request $request){
        $data=array();
        $tarea = DB::SELECT("SELECT * FROM tarea left join contenido on contenido.idcontenido = tarea.contenido_idcontenido WHERE tarea.curso_idcurso = ? AND tarea.estado = '1' AND tarea.usuario_idusuario IS NULL OR tarea.usuario_idusuario = ?",[$request->idcurso,$request->idusuario]);
        foreach ($tarea as $key => $post) {
            $verifica = DB::SELECT("SELECT * FROM usuario_tarea WHERE tarea_idtarea = ? AND usuario_idusuario = ?",[$post->idtarea,$request->idusuario]);
            if(!empty($verifica)){
            }else{
                array_push ($data , $post);
            }
        }
        return $data;
    }

    public function tareaEstudianteRealizada(Request $request){
        $data=array();
        $tarea = DB::SELECT("SELECT * FROM tarea WHERE curso_idcurso = ? AND estado = '1' ",[$request->idcurso]);
        foreach ($tarea as $key => $post) {
            $verifica = DB::SELECT("SELECT tarea.*,usuario_tarea.*,contenido.url as urldocente,contenido.nombre as tareadocente FROM usuario_tarea join tarea on tarea.idtarea = usuario_tarea.tarea_idtarea left join contenido on contenido.idcontenido = tarea.contenido_idcontenido WHERE tarea_idtarea = ? AND usuario_tarea.usuario_idusuario = ?",[$post->idtarea,$request->idusuario]);

            if(!empty($verifica)){
                array_push ($data , $verifica[0]);
            }else{
            }
        }
        return $data;
    }


    public function estudianteCodigo($id){
        $estudiante = DB::SELECT("SELECT u.cedula, u.nombres, u.apellidos, u.email FROM usuario u WHERE idusuario = $id");

        return $estudiante;
    }



    public function cedulasEstudiantes($cedula){
        $cedula = DB::SELECT("SELECT u.idusuario, u.cedula, u.nombres, u.apellidos, u.email FROM usuario u WHERE u.id_group = 4 AND u.cedula like '%$cedula%'");

        return $cedula;

    }


    public function institucionEstCod($id){
        $institucion = DB::SELECT("SELECT idInstitucion,
         nombreInstitucion, ciudad.nombre as nombre_ciudad,
         pi.periodoescolar_idperiodoescolar as id_periodo,institucion.region_idregion as region
         FROM institucion, usuario, ciudad, periodoescolar_has_institucion pi
         WHERE usuario.institucion_idInstitucion = institucion.idInstitucion
         AND ciudad.idciudad = institucion.ciudad_id
         AND usuario.idUsuario = $id
         AND usuario.institucion_idInstitucion = pi.institucion_idInstitucion
         AND pi.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi WHERE phi.institucion_idInstitucion = institucion.idInstitucion)");

        return $institucion;

    }


    public function addClase(Request $request){
        // $datosValidados=$request->validate([
        //     'codigo' => 'exists:curso,codigo',
        // ]);

        //validacion

        $verificarSiexisteCodigo = DB::SELECT("SELECT * FROM curso WHERE codigo = ? AND estado = '1'  ",["$request->codigo"]);


        if(count($verificarSiexisteCodigo) <=0){
            return ["status"=>"0", "message" => "Ocurrio un error al ingresar el curso / o el codigo no existe"];


        }else{


            $verificarSiexisteCodigoEstudiante = DB::SELECT("SELECT * FROM estudiante WHERE codigo = ? AND usuario_idusuario = $request->idusuario AND estado = '1' ",["$request->codigo"]);

            if(count($verificarSiexisteCodigoEstudiante) <=0){
                $idusuario = $request->idusuario;
                $codigo = $request->codigo;
                $res = DB::INSERT('INSERT INTO `estudiante`(`usuario_idusuario`, `codigo`) VALUES (?,?)',[$idusuario,$codigo]);
                if($res){
                    return ["status"=>"1", "message" => "Se ingreso correctamente"];
                }else{
                    return ["status"=>"0", "message" => "No se pudo ingresar el curso"];
                }

            }else{
                return ["status"=>"0", "message" => "Ocurrio un error al ingresar el curso / o el curso ya existe"];
            }
        }



    }


    public function verificarCursoEstudiante(Request $request){

        $estudiante = DB::SELECT("SELECT * FROM estudiante WHERE usuario_idusuario = $request->idusuario AND codigo = '$request->codigo'");
        return $estudiante;
    }


    public function estudiantesEvalCurso(Request $request)
    {
        $estudiantes = DB::SELECT("SELECT DISTINCT u.idusuario as id, CONCAT(u.nombres, ' ', u.apellidos) as label, c.grupo FROM estudiante e, usuario u, calificaciones c WHERE e.codigo = '$request->codigo' AND e.usuario_idusuario = u.idusuario AND c.id_estudiante = e.usuario_idusuario AND c.id_evaluacion = $request->evaluacion AND e.usuario_idusuario IN (SELECT c.id_estudiante FROM calificaciones c WHERE c.id_evaluacion = $request->evaluacion) ORDER BY u.nombres");

        return $estudiantes;
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
     * @param  \App\estudiante  $estudiante
     * @return \Illuminate\Http\Response
     */
    public function show($cedula)
    {
        $estudiante = DB::SELECT("SELECT idusuario FROM usuario WHERE cedula = $cedula");

        return $estudiante;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\estudiante  $estudiante
     * @return \Illuminate\Http\Response
     */
    public function edit(estudiante $estudiante)
    {
        //
    }

    //api:get/informationStudent
    public function informationStudent(Request $request){
        if($request->guardarInfo){
           $info =  DB::INSERT("INSERT INTO `estudiante_datos`(idusuario,institucion_id,ciudad_id,periodo_id,institucion_actual) VALUES (?,?,?,?,?)
            ",[$request->idusuario,$request->institucion_id,$request->ciudad_id,$request->periodo_id,$request->institucion_actual]);
            if($info){
                return ["status" => "1", "message" => "Se actualizo los datos correctamente"];
            }else{
                return ["status" => "0", "message" => "No se pudo actualizar los datos"];
            }
        }else{
             //para traer el periodo
             $buscarPeriodo = $this->traerPeriodo($request->institucion_id);
             if($buscarPeriodo["status"] == "1"){
                 $periodo = $buscarPeriodo["periodo"][0]->periodo;
             }
            $validate = DB::SELECT("SELECT d.* FROM estudiante_datos d
            WHERE d.periodo_id = '$periodo'
            AND d.idusuario = '$request->idusuario'
            ");
            //quiere decir que el usuario ya valido su informacion
            if(count($validate) > 0) {
                return ["status" => "1", "message" => "Estudiante ya valido sus datos"];
            }
            //estudiante aun no valida su informacion
            else{
                return ["status" => "0", "message" => "Estimado estudiante necesita actualizar su información"];
            }
        }
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
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\estudiante  $estudiante
     * @return \Illuminate\Http\Response
     */
    public function destroy(estudiante $estudiante)
    {
        //
    }
    public function estudiantesLibros(Request $request)
    {
        $mensaje ='';
        $tipo=0;
        //obtener periodo activo de la institucion
        $dato = DB::table('institucion as i')
        ->where('i.idInstitucion','=',$request->idInstitucion)
        ->select('i.nombreInstitucion','i.region_idregion','pes.estado','pes.descripcion as name_periodo','pes.idperiodoescolar')
        ->leftjoin('periodoescolar_has_institucion as pei', 'i.idInstitucion', '=','pei.institucion_idInstitucion')
        ->leftjoin('periodoescolar as pes', 'pei.periodoescolar_idperiodoescolar', '=','pes.idperiodoescolar')
        ->where('pes.estado','=','1')
        ->get();
        // return $dato;
        if ($dato->count() > 1) {
            $mensaje = 'Su institución tiene mas de un periodo activo asignado, y no es posible obtener la lista de libros correcta, comuníquese con su asesor.';
            $tipo=1;
            return compact('tipo','mensaje');
        }else if($dato->count() == 0){
            $inst = DB::table('institucion as i')
            ->where('i.idInstitucion','=',$request->idInstitucion)
            ->get(['i.nombreInstitucion']);
            $mensaje = 'La institución - '.$inst[0]->nombreInstitucion.' - no tiene un periodo activo, comuníquese con su asesor.';
            $tipo=0;
            return compact('tipo','mensaje');
        }else{
            //obtener lista de libros de la institucion del periodo activo
            $lista = DB::table('codigoslibros as codb')
            ->where('codb.idusuario','=',$request->idUsuario )
            ->where('codb.id_periodo','=',$dato[0]->idperiodoescolar)
            ->get();
            return $lista;
        }
    }
    //agregar libros a un estudiantes desde perfil director, asesor y administrador - requeridopor el colegio ventanas de quevedo
    public function addLibroEstudianteDirector(Request $request)
    {
        $mensaje ='';
        $tipo=0;

        //obtener periodo activo de la institucion
        $dato = DB::table('institucion as i')
        ->where('i.idInstitucion','=',$request->idInstitucion)
        ->select('i.nombreInstitucion','i.region_idregion','pes.estado','pes.descripcion as name_periodo','pes.idperiodoescolar')
        ->leftjoin('periodoescolar_has_institucion as pei', 'i.idInstitucion', '=','pei.institucion_idInstitucion')
        ->leftjoin('periodoescolar as pes', 'pei.periodoescolar_idperiodoescolar', '=','pes.idperiodoescolar')
        ->where('pes.estado','=','1')
        ->get();
        if ($dato->count() > 1) {
            $mensaje = 'Su institución tiene mas de un periodo activo asignado, y no es posible asignar el codigo '.$request->codigo.' al estudiante solicitado, comuníquese con su asesor.';
            $tipo=1;
            return compact('tipo','mensaje');
        }else if($dato->count() == 0){
            $mensaje = 'Su institución no tiene un periodo activo, comuníquese con su asesor.';
            $tipo=0;
            return compact('tipo','mensaje');
        }else{
            //obtener codigo solicitado
            // return $request->codigo;
            $codigo = DB::table('codigoslibros')
            ->where('codigo','=',$request->codigo)
            ->get();
            // return $codigo;
            if ($codigo[0]->idusuario > 0 && $codigo[0]->idusuario != NULL ) {
                $mensaje = ' El código que esta intentando agregar - '.$request->codigo.' - ya esta siendo utilizado por otro usuario, favor comuníquese con su asesor, o envie un correo a soporte@prolipa.com.ec, e incluya los datos del estudiante y el código a utilizar.';
                return $mensaje;
            }else{
                $codigo = DB::table('codigoslibros')
                ->where('codigo','=',$request->codigo)
                ->update(['codigo'=>$request->codigo,'idusuario'=>$request->idUsuario,'id_periodo'=>$dato[0]->idperiodoescolar]);
                $mensaje = 'Codigo agregado correctamente';
                return $mensaje;
            }

        }
    }


    //api para filtrar busqueda del estudiante
    public function busquedaFiltroEstudiante(Request $request){
        if($request->libroEstudiante){
           //PARA BUSCAR LOS LIBROS DE LOS LIBROS
            $estudiante = DB::select("SELECT COUNT(c.codigo) as cantidad_libros
            FROM codigoslibros c
            LEFT JOIN periodoescolar p ON p.idperiodoescolar = c.id_periodo
            WHERE c.idusuario = $request->idusuario
            and c.estado <> '2'
            ");
            foreach($estudiante as $clave => $valor ){
                $traercodigos = DB::select("SELECT c.idusuario,c.codigo, c.updated_at, p.descripcion,
                IF(c.estado ='2', 'bloqueado','activo') as codigoEstado,
                (case when (c.estado_liquidacion = '0') then 'liquidado'
                    when (c.estado_liquidacion = '1') then 'sin liquidar'
                    when (c.estado_liquidacion = '2') then 'codigo regalado'
                end) as liquidacion,c.verif1,c.verif2,c.verif3,c.verif4,c.verif5,c.verif6,c.verif7,c.verif8,c.verif9,c.verif10
                FROM codigoslibros c
                LEFT JOIN periodoescolar p ON p.idperiodoescolar = c.id_periodo
                WHERE c.idusuario = $request->idusuario
                and c.estado <> '2'
                ");
            }
            return [ "cantidad_libros" => $valor,"codigos" => $traercodigos];
        }
        //para buscar los cursos del estudiante
        if($request->busquedaCurso){
            $cantidad = DB::SELECT("SELECT  COUNT(DISTINCT e.codigo) as cantidad_cursos
            FROM estudiante e
              LEFT JOIN curso c ON c.codigo = e.codigo
              LEFT JOIN periodoescolar pe ON c.id_periodo = pe.idperiodoescolar
              WHERE  usuario_idusuario  = '$request->idusuario'
              AND e.estado = '1'
              AND c.estado = '1'
              AND pe.estado = '1'
            ");
            $codigos = DB::SELECT("SELECT DISTINCT c.codigo, e.usuario_idusuario FROM
                estudiante e
                LEFT JOIN curso c ON c.codigo = e.codigo
                LEFT JOIN periodoescolar pe ON c.id_periodo = pe.idperiodoescolar
                WHERE  usuario_idusuario  = '$request->idusuario'
                AND e.estado = '1'
                AND c.estado = '1'
                AND pe.estado = '1'
            ");
            return ["cantidad" => $cantidad , "codigos" => $codigos];
        }

        //para buscar por email
        if($request->busqueda == 'email'){
            if($request->asesor){
                $estudiantes =  DB::SELECT("SELECT DISTINCT   u.idusuario, u.nombres,
                 u.apellidos, u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado, u.id_group, u.institucion_idInstitucion ,i.nombreInstitucion, gr.deskripsi as perfil, u.fecha_nacimiento, u.cargo_id, c.cargo, u.iniciales,
                u.foto_user,u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE (u.id_group = '10' OR u.id_group = '6' OR u.id_group = '13' OR u.id_group = '4' OR u.id_group = '9')
                AND email LIKE '%$request->razonBusqueda%'
                ");
            }else{
                $estudiantes =  DB::SELECT("SELECT DISTINCT   u.idusuario, u.nombres, u.apellidos,
                 u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado, u.id_group,
                 u.institucion_idInstitucion ,i.nombreInstitucion, gr.deskripsi as perfil,
                 u.fecha_nacimiento, u.cargo_id, c.cargo, u.iniciales,u.foto_user,
                 u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE email LIKE '%$request->razonBusqueda%'

                ");
            }
           if(!empty($estudiantes)){
            return $estudiantes;
           }else{
            return ["status"=> "0","message"=> "No se encontro datos"];
           }
        }
        if($request->busqueda == 'cedula'){
            if($request->asesor){
                $estudiantes =  DB::SELECT("SELECT DISTINCT  u.idusuario, u.nombres,
                u.apellidos, u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado,
                u.id_group, u.institucion_idInstitucion ,i.nombreInstitucion,
                gr.deskripsi as perfil, u.fecha_nacimiento, u.cargo_id, c.cargo,
                u.iniciales,u.foto_user,u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE (u.id_group = '10' OR u.id_group = '6' OR u.id_group = '13' OR u.id_group = '4' OR u.id_group = '9')
                AND cedula LIKE '%$request->razonBusqueda%'

                ");
            }
            else{
                $estudiantes =  DB::SELECT("SELECT DISTINCT  u.idusuario, u.nombres,
                u.apellidos, u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado,
                 u.id_group, u.institucion_idInstitucion ,i.nombreInstitucion,
                 gr.deskripsi as perfil , u.fecha_nacimiento, u.cargo_id,cargo,
                  u.iniciales,u.foto_user,u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE cedula LIKE '%$request->razonBusqueda%'

                ");
            }
        }
        if($request->busqueda == 'usuario'){
            if($request->asesor){
                $estudiantes =  DB::SELECT("SELECT DISTINCT  u.idusuario, u.nombres,
                u.apellidos, u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado,
                 u.id_group, u.institucion_idInstitucion ,i.nombreInstitucion,
                 gr.deskripsi as perfil, u.fecha_nacimiento, u.cargo_id, c.cargo,
                  u.iniciales,u.foto_user,u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE (u.id_group = '10' OR u.id_group = '6' OR u.id_group = '13' OR u.id_group = '4' OR u.id_group = '9')
                AND name_usuario LIKE '%$request->razonBusqueda%'
                ");
            }
            else{
                $estudiantes =  DB::SELECT("SELECT DISTINCT  u.idusuario, u.nombres,
                 u.apellidos, u.email, u.cedula, u.name_usuario,u.telefono,u.estado_idEstado,
                  u.id_group, u.institucion_idInstitucion ,i.nombreInstitucion,
                   gr.deskripsi as perfil, u.fecha_nacimiento, u.cargo_id, c.cargo,
                    u.iniciales,u.foto_user,u.change_password,u.fecha_change_password,u.capacitador
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                LEFT JOIN sys_group_users gr ON gr.id = u.id_group
                LEFT JOIN institucion_cargos  c ON u.cargo_id = c.id
                WHERE name_usuario LIKE '%$request->razonBusqueda%'
                ");
            }
        }

        if(!empty($estudiantes)){
            return $estudiantes;
       }else{
           return ["status"=> "0","message"=> "No se encontro datos"];
       }
        }
    }

