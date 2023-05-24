<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CuotasPorCobrar;
use App\Models\EstudianteMatriculado;
use App\Models\MatConfiguracion;
use App\Models\MatConfiguracionQuimestre;
use App\Models\NivelInstitucion;
use App\Models\Zoom;
use App\Models\MatCalificaciones;
use App\Models\MatCalificaciones1Quimestre;
use App\Models\MatCalificaciones2Quimestre;
use App\Models\UsuarioTarea;
use Illuminate\Http\Request;
use DB;

class CursoJailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        
        //listar cursos 
        if($request->listado){
            return $this->listadoCursos($request->institucion_id);
        }
        //listar configuracion pagos
        if($request->listadoConfiguracion){
            return $this->listadoConfiguracion($request->institucion_id,$request->periodo_id);
        }
        //listar todas las configuracion de pagos de la institucion
        if($request->listadoConfiguracionAll){
            return $this->listadoConfiguracionAll($request->institucion_id);
        }
        //listar todas las configuracion de los quimestres de la institucion
        if($request->listadoConfiguracionQuimestreAll){
            return $this->listadoConfiguracionQuimestreAll($request->institucion_id);
        }
        //listar todas las configuracion de los quimestres de la institucion x periodo
        if($request->listadoConfiguracionQuimestre){
            return $this->listadoConfiguracionQuimestre($request->institucion_id,$request->periodo_id);
        }
        //lista todos los estudiantes de la institucion
        if($request->listadoTodosEstudiantesInstitucion){
            return $this->listadoTodosEstudiantesInstitucion($request->institucion_id,$request->periodo_id);
        }
        //listar por cedula o apellidos de los estudiantes de la institucion
        if($request->listadoTodosEstudiantesInstitucionIndividual){
            return $this->listadoTodosEstudiantesInstitucionIndividual($request->institucion_id,$request->periodo_id,$request->busqueda,$request->tipo);
        }
        //listar matricula x estudiante
        if($request->getMatriculaXEstudiante){
            return $this->getMatriculaXEstudiante($request->institucion_id,$request->periodo_id,$request->idusuario);
        }
        //listar los estudiantes de la institucion
        if($request->listadoEstudiantes){
            return $this->listadoEstudiantes($request->institucion_id,$request->periodo_id);
        }
        //validacion de la matricula estudiante
        if($request->validacionMatricula){
            return $this->validacionMatricula($request->institucion_id,$request->periodo_id,$request->id_estudiante);
        }
        if($request->valoresPensiones){
            return $this->valoresPensiones($request->id_matricula);
        }
        //listado docentes
        if($request->listadoDocentes){
            return $this->listadoDocentes($request->institucion_id,$request->periodo_id);
        }
        //DOCENTE
        if($request->getCursosDocente){
            return $this->getCursosDocente($request->institucion_id,$request->periodo_id,$request->docente_id);
        }
        //FIN DOCENTE
    }
    //listado de la configuracion de pagos
    public function listadoConfiguracion($institucion,$periodo){
        $validate = DB::SELECT("SELECT c.* FROM mat_configuracion_institucion c
        WHERE c.institucion_id = '$institucion'
        AND c.periodo_id = '$periodo'        
        ");
        return $validate;
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
    //listado de la configuracion de quimestre
    public function listadoConfiguracionQuimestre($institucion,$periodo){
        $buscarPeriodo = $this->traerPeriodo($institucion);
        if($buscarPeriodo["status"] == "1"){
            $getPeriodo = $buscarPeriodo["periodo"][0]->periodo;
        }
        $validate = DB::SELECT("SELECT c.* FROM mat_quimestres c
        WHERE c.institucion_id = '$institucion'
        AND c.periodo_id = '$getPeriodo'        
        ");
        
        return $validate;
    }
    public function listadoConfiguracionAll($institucion){
        $validate = DB::SELECT("SELECT c.* FROM mat_configuracion_institucion c
        WHERE c.institucion_id = '$institucion'       
        ");
        return $validate;
    }
    public function listadoConfiguracionQuimestreAll($institucion){
        $validate = DB::SELECT("SELECT c.* FROM mat_quimestres c
        WHERE c.institucion_id = '$institucion'       
        ");
        return $validate;
    }
    public function listadoTodosEstudiantesInstitucion($institucion,$periodo){
        $usuarios = DB::SELECT("SELECT u.*,i.nombreInstitucion,
         CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
            u.apellidos,u.name_usuario,u.nombres,u.telefono,u.acceso_cursos,
            u.id_group,u.institucion_idInstitucion,u.estado_idEstado,u.fecha_nacimiento
            FROM usuario u
            LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
            WHERE u.institucion_idInstitucion = '$institucion'
            AND u.estado_idEstado = '1'
            AND u.id_group = '4'
            ORDER BY u.idusuario DESC
        ");
        return $this->getEstudianteMatricula($usuarios,$institucion,$periodo);
    }
    public function listadoTodosEstudiantesInstitucionIndividual($institucion,$periodo,$busqueda,$tipo){
        if($tipo == 'cedula'){
            $usuarios = DB::SELECT("SELECT u.*,i.nombreInstitucion, 
                CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
                u.apellidos,u.name_usuario,u.nombres,u.telefono,u.acceso_cursos,
                u.id_group,u.institucion_idInstitucion,u.estado_idEstado,u.fecha_nacimiento
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                WHERE u.institucion_idInstitucion = '$institucion'
                AND u.estado_idEstado = '1'
                AND u.id_group = '4'
                AND u.cedula like '%$busqueda%'
            ");
        }
        if($tipo == 'apellidos'){
            $usuarios = DB::SELECT("SELECT u.*,i.nombreInstitucion, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
                u.apellidos,u.name_usuario,u.nombres,u.telefono,u.acceso_cursos,
                u.id_group,u.institucion_idInstitucion,u.estado_idEstado,u.fecha_nacimiento
                FROM usuario u
                LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
                WHERE u.institucion_idInstitucion = '$institucion'
                AND u.estado_idEstado = '1'
                AND u.id_group = '4'
                AND u.apellidos like '%$busqueda%'
            ");
        }
        return $this->getEstudianteMatricula($usuarios,$institucion,$periodo);
    }
    public function getEstudianteMatricula($usuarios,$institucion,$periodo){
        $datos = [];
        foreach($usuarios as $key => $item){
            $estudiantes = DB::SELECT("SELECT mt.*, CONCAT(u.nombres, ' ',u.apellidos) 
            AS estudiante,
            u.cedula, p.periodoescolar AS periodo, c.nombrenivel AS curso,
            pl.descripcion AS paralelo
            FROM mat_estudiantes_matriculados mt
            LEFT JOIN usuario u ON mt.id_estudiante = u.idusuario
            LEFT JOIN periodoescolar p ON mt.id_periodo = p.idperiodoescolar
            LEFT JOIN mat_niveles_institucion n ON mt.curso_id = n.nivelInstitucion_id
            LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel
            LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id
            WHERE mt.institucion_id = '$institucion'
            AND mt.id_periodo = '$periodo'
            AND mt.id_estudiante = '$item->idusuario'
            ORDER BY mt.id_matricula desc 
            limit 1
            ");
            if($item->becado == "1"){
                if(empty($estudiantes)){
                    $this->generarMatriculaBecado($item->idusuario,$institucion,$periodo);
                }
            }
            $datos[$key] = [
                "nombreInstitucion"         => $item->nombreInstitucion,
                "idusuario"                 => $item->idusuario,
                "name_usuario"              => $item->name_usuario,
                "cedula"                    => $item->cedula,
                "telefono"                  => $item->telefono,
                "estudiante"                => $item->estudiante,
                "nombres"                   => $item->nombres,
                "apellidos"                 => $item->apellidos,
                "email"                     => $item->email,
                "telefono"                  => $item->telefono,
                "acceso_cursos"             => $item->acceso_cursos,
                "id_group"                  => $item->id_group,
                "institucion_idInstitucion" => $item->institucion_idInstitucion,
                "estado_idEstado"           => $item->estado_idEstado,
                "fecha_nacimiento"          => $item->fecha_nacimiento,
                "matricula"                 => $estudiantes,
                "becado"                    => $item->becado,
                "estadoMatricula"           => count($estudiantes) > 0 ? $estudiantes[0]->estado_matricula: '5'
             ];  
        }
        return $datos;
    }
    public function getMatriculaXEstudiante($institucion,$periodo,$estudiante){
        $usuario = DB::SELECT("SELECT u.*,i.nombreInstitucion, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
            u.apellidos
            FROM usuario u
            LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
            WHERE u.idusuario = '$estudiante'
            AND u.estado_idEstado = '1'
            AND u.id_group = '4'
            ORDER BY u.idusuario DESC
        ");
        $matricula = DB::SELECT("SELECT mt.*, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
            u.cedula, p.periodoescolar AS periodo, c.nombrenivel AS curso,
            pl.descripcion AS paralelo
            FROM mat_estudiantes_matriculados mt
            LEFT JOIN usuario u ON mt.id_estudiante = u.idusuario
            LEFT JOIN periodoescolar p ON mt.id_periodo = p.idperiodoescolar
            LEFT JOIN mat_niveles_institucion n ON mt.curso_id = n.nivelInstitucion_id
            LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel
            LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id
            WHERE mt.institucion_id = '$institucion'
            AND mt.id_periodo = '$periodo'
            AND mt.id_estudiante = '$estudiante'
            ORDER BY mt.id_matricula desc 
            limit 1
            ");
            $datos= [
                "nombreInstitucion" => $usuario[0]->nombreInstitucion,
                "idusuario"         => $usuario[0]->idusuario,
                "cedula"            => $usuario[0]->cedula,
                "telefono"          => $usuario[0]->telefono,
                "estudiante"        => $usuario[0]->estudiante,
                "apellidos"         => $usuario[0]->apellidos,
                "matricula"         => $matricula,
                "becado"            => $usuario[0]->becado,
                "estadoMatricula"   => count($matricula) > 0 ? $matricula[0]->estado_matricula: '5'
             ];  
            return $datos;
    }
    public function generarMatriculaBecado($idusuario,$institucion,$periodo){
        $estudiante  = new EstudianteMatriculado();
        $estudiante->id_estudiante      = $idusuario;
        $estudiante->institucion_id     = $institucion;
        $estudiante->id_periodo         = $periodo;
        $estudiante->user_created       = 0;
        $estudiante->estado_matricula   = 0;
        $estudiante->save();
        //get precio matricula
        $getPrecioMatricula = DB::SELECT("SELECT * FROM mat_configuracion_institucion mc
        WHERE mc.institucion_id = '$institucion'
        AND mc.periodo_id = '$periodo'
        ");
        $precio_matricula = $getPrecioMatricula[0]->precio_matricula;
        //REGISTRAR PAGO PAGADO DE ESTUDIANTE BECADO
        $this->registrarPago($idusuario,$estudiante->id_matricula,$precio_matricula,0,1);
    }
    public function listadoEstudiantes($institucion,$periodo){
        $estudiantes = DB::SELECT("SELECT mt.*, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,
        u.cedula,i.nombreInstitucion, p.periodoescolar AS periodo, c.nombrenivel AS curso,
        pl.descripcion AS paralelo
        FROM mat_estudiantes_matriculados mt
        LEFT JOIN usuario u ON mt.id_estudiante = u.idusuario
        LEFT JOIN institucion i ON mt.institucion_id = i.idInstitucion
        LEFT JOIN periodoescolar p ON mt.id_periodo = p.idperiodoescolar
        LEFT JOIN mat_niveles_institucion n ON mt.curso_id = n.nivelInstitucion_id
        LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel
        LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id
        WHERE mt.institucion_id = '$institucion'
        AND mt.id_periodo = '$periodo'
        ");
        return $estudiantes;
    }
    public function listadoCursos($institucion){
        $cursos = DB::SELECT("SELECT c.*, p.descripcion AS paralelo, n.nombrenivel AS curso,
        i.nombreInstitucion, pe.periodoescolar AS periodo,
        CONCAT(n.nombrenivel,' ',p.descripcion) as cursoparalelo,
        (
            SELECT COUNT(mt.id_matricula) AS contador FROM mat_estudiantes_matriculados mt
            WHERE mt.curso_id = c.nivelInstitucion_id
            AND mt.estado_matricula = '1'
        )as contador
        FROM mat_niveles_institucion  c
        LEFT JOIN mat_paralelos p ON  c.paralelo_id = p.paralelo_id
        LEFT JOIN nivel n ON c.nivel_id = n.idnivel
        LEFT JOIN institucion i ON c.institucion_id = i.idInstitucion
        LEFT JOIN periodoescolar  pe ON c.periodo_id = pe.idperiodoescolar
        WHERE pe.estado = '1'
        AND c.institucion_id = '$institucion'
        ORDER BY  c.nivel_id ASC
        ");
        return $cursos;
    }
    public function getCursosInstitucionxPeriodo($institucion,$periodo){
        $consulta = DB::SELECT("SELECT c.*,  CONCAT(n.nombrenivel,' ',p.descripcion) as cursoparalelo
        FROM mat_niveles_institucion c
        LEFT JOIN mat_paralelos p ON  c.paralelo_id = p.paralelo_id
        LEFT JOIN nivel n ON c.nivel_id = n.idnivel
        WHERE c.institucion_id = '$institucion'
        AND c.periodo_id = '$periodo'
        ");
        return $consulta;
    }
    public function validacionMatricula($institucion,$periodo,$estudiante){
        $validate = DB::SELECT("SELECT mt.*, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante,c.idnivel,
        u.cedula, p.periodoescolar AS periodo, c.nombrenivel AS curso,
        pl.descripcion AS paralelo
        FROM mat_estudiantes_matriculados mt
        LEFT JOIN usuario u ON mt.id_estudiante = u.idusuario
        LEFT JOIN periodoescolar p ON mt.id_periodo = p.idperiodoescolar
        LEFT JOIN mat_niveles_institucion n ON mt.curso_id = n.nivelInstitucion_id
        LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel
        LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id
        WHERE mt.institucion_id = '$institucion'
        AND mt.id_periodo = '$periodo'
        AND mt.id_estudiante = '$estudiante'
        AND mt.estado_matricula = '1'
        ORDER BY mt.id_matricula desc 
        ");
        return $validate;
    }
    public function valoresPensiones($id_matricula){
        $cuotas = DB::SELECT("SELECT * FROM mat_cuotas_por_cobrar c
        WHERE id_matricula = '$id_matricula'
        ORDER BY num_cuota +0");
        return $cuotas;
    }
    //listado docentes
    public function listadoDocentes($institucion,$periodo){
        $query = DB::SELECT("SELECT u.*,i.nombreInstitucion
            FROM usuario u
            LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
            WHERE u.institucion_idInstitucion = '1'
            AND u.estado_idEstado = '1'
            AND u.id_group = '6'
        ");
        $datos = [];
        if(empty($query)){
            return $datos;
        }
        $contador = 0;
        foreach($query as $key=> $item){
            $cursos  = DB::SELECT("SELECT DISTINCT mt.idcurso, mt.docente_id,
            c.nombrenivel AS curso,
            pl.descripcion AS paralelo
            FROM asignaturausuario mt
            LEFT JOIN mat_niveles_institucion n ON mt.idcurso = n.nivelInstitucion_id
            LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel
            LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id
            WHERE mt.docente_id = '$item->idusuario'
            AND  n.institucion_id = '$institucion'
            AND n.periodo_id = '$periodo'
            ORDER BY c.nombrenivel desc
            ");
            $datos[$contador] = [
                "idusuario"                 => $item->idusuario,
                "nombres"                   => $item->nombres,
                "apellidos"                 => $item->apellidos,
                "cedula"                    => $item->cedula,
                "name_usuario"              => $item->name_usuario,
                "email"                     => $item->email,
                "id_group"                  => $item->id_group,
                "institucion_idInstitucion" => $item->institucion_idInstitucion,
                "nombreInstitucion"         => $item->nombreInstitucion,
                "fecha_nacimiento"          => $item->fecha_nacimiento,
                "estado_idEstado"           => $item->estado_idEstado,
                "cursos"                    => $cursos
            ];
            $contador++;
        }
        return $datos;
    }
    public function getCursosDocente($institucion,$periodo,$docente_id){
        $datosCursos = [];
        $contador    = 0;
        $getCursoHorario = DB::SELECT("SELECT DISTINCT a.idcurso FROM
        asignaturausuario a
        LEFT JOIN  mat_horario_clases_detalles ds ON a.idasiguser = ds.Sabado
        LEFT join mat_horario_clases_detalles dd ON a.idasiguser = dd.Domingo
        WHERE a.docente_id = '$docente_id'
        AND (ds.Sabado > 0 OR dd.Domingo > 0)
        ORDER BY a.idcurso  asc
        ");
        foreach($getCursoHorario as $key => $item){
            $cursosDocente = DB::SELECT("SELECT mn.*, n.nombrenivel AS curso,
            p.descripcion AS paralelo,
            (
                SELECT COUNT(mt.id_matricula) AS contador FROM mat_estudiantes_matriculados mt
                WHERE mt.curso_id = '$item->idcurso'
                AND mt.estado_matricula = '1'
            )as contador
            FROM mat_niveles_institucion mn
            LEFT JOIN nivel n ON mn.nivel_id = n.idnivel
            LEFT JOIN mat_paralelos p ON  mn.paralelo_id = p.paralelo_id  
            WHERE mn.nivelInstitucion_id = '$item->idcurso'
            AND mn.institucion_id = '$institucion'
            AND mn.periodo_id = '$periodo'
            AND mn.estado = '1'
            ORDER BY mn.nivel_id asc
            ");
            if(count($cursosDocente) > 0){
                $datosCursos[$contador] = [
                    "nivelInstitucion_id"       => $cursosDocente[0]->nivelInstitucion_id,
                    "nivel_id"                  => $cursosDocente[0]->nivel_id,
                    "paralelo_id"               => $cursosDocente[0]->paralelo_id,
                    "institucion_id"            => $cursosDocente[0]->institucion_id,
                    "periodo_id"                => $cursosDocente[0]->periodo_id,
                    "estado"                    => $cursosDocente[0]->estado,
                    "curso"                     => $cursosDocente[0]->curso,
                    "cursoparalelo"             => $cursosDocente[0]->curso." ".$cursosDocente[0]->paralelo,
                    "paralelo"                  => $cursosDocente[0]->paralelo,
                    "contador"                  => $cursosDocente[0]->contador
                ];
                $contador++;
            }
        }
        return $datosCursos;
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
            $curso = NivelInstitucion::findOrFail($request->id);
        }else{
            $curso =  new NivelInstitucion();
              //validate si ya existe el curso y el paralelo
              $validate  = DB::SELECT("SELECT * FROM mat_niveles_institucion n
              WHERE n.nivel_id = '$request->nivel_id'
              AND n.paralelo_id = '$request->paralelo_id'
              AND n.periodo_id = '$request->periodo_id'
              AND n.institucion_id = '$request->institucion_id'
              AND n.estado = '1'
              ");
              if(count($validate) > 0){
                  return ["status" => "0", "message" => "El curso y el paralelo ya existe"];     
              }
        }
            $curso->nivel_id            = $request->nivel_id;
            $curso->paralelo_id         = $request->paralelo_id;
            $curso->institucion_id      = $request->institucion_id;
            $curso->periodo_id          = $request->periodo_id;
            $curso->estado              = $request->estado;
            $curso->maximo_estudiantes  = $request->maximo_estudiantes;
            $curso->save();
            if($curso){
                return ["status" => "1", "message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0", "message" => "No se pudo guardar"];
            }
    }
    public function changeQuimestre(Request $request){
        $quimestre = MatConfiguracionQuimestre::findOrFail($request->id);
        $quimestre->quimestreActivo     = $request->quimestre;
        $quimestre->save();
    }
    public function generarFormatoCalificaciones(Request $request){
        $estudiantes = DB::SELECT("CALL `estudianteCurso`('$request->curso_id')");
        if(count($estudiantes) > 0){
            foreach($estudiantes as $key => $item){
                $this->generateFormatCalificaciones($item->idusuario,$request->curso_id,$request->materia_id);
            }
        }
    }
    public function generateFormatCalificaciones($idusuario,$curso_id,$materia_id){
        //validar si ya existe no crear
        $validate = DB::SELECT("SELECT * FROM mat_calificaciones q
        WHERE q.curso_id = '$curso_id'
        AND q.materia_id = '$materia_id'
        AND q.estudiante_id = '$idusuario'
        ");
        if(empty($validate)){
            $quimestre = new MatCalificaciones();
            $quimestre->curso_id        = $curso_id;
            $quimestre->materia_id      = $materia_id;
            $quimestre->estudiante_id   = $idusuario;
            $quimestre->save();
            if($quimestre){
                //Primer quimestre
                $this->crearFormatoPrimerQ($idusuario,$quimestre->id);
                //Segundo quimestre
                $this->crearFormatoSegundoQ($idusuario,$quimestre->id);
            }
        }
    }
    public function crearFormatoPrimerQ($estudiante_id,$id){
        $saveStudent                        = new MatCalificaciones1Quimestre();
        $saveStudent->estudiante_id         = $estudiante_id;
        $saveStudent->mat_calificacion_id   = $id;
        $saveStudent->save();
    }
    public function crearFormatoSegundoQ($estudiante_id,$id){
        $saveStudent2                        = new MatCalificaciones2Quimestre();
        $saveStudent2->estudiante_id         = $estudiante_id;
        $saveStudent2->mat_calificacion_id   = $id;
        $saveStudent2->save();
    }
    public function estudianteCurso($curso_id,$materia_id){
        $estudiantes = DB::SELECT("SELECT u.*, CONCAT(u.nombres, ' ',u.apellidos) AS estudiante, mt.curso_id AS idcurso, 
        i.nombreInstitucion, p.periodoescolar AS periodo, c.nombrenivel AS curso, 
        pl.descripcion AS paralelo ,mt.id_matricula ,
        (
        	SELECT ca.id FROM mat_calificaciones ca
				WHERE ca.curso_id = '$curso_id'
				AND ca.materia_id = '$materia_id'
				AND ca.estudiante_id = mt.id_estudiante
		)AS idResumen,
        (
            SELECT IF(q1.final IS NULL ,'0',q1.final) AS finalq1
            FROM mat_calificaciones ca
            LEFT JOIN mat_calificaciones_1_quimestre q1 ON ca.id = q1.mat_calificacion_id
            WHERE ca.curso_id = '$curso_id'
            AND ca.materia_id = '$materia_id'
            AND ca.estudiante_id = mt.id_estudiante
        ) AS finalq1,
        (
            SELECT IF(q2.final2 IS NULL ,'0',q2.final2) AS finalq2
            FROM mat_calificaciones ca
            LEFT JOIN mat_calificaciones_2_quimestre q2 ON ca.id = q2.mat_calificacion_id
            WHERE ca.curso_id = '$curso_id'
            AND ca.materia_id = '$materia_id'
            AND ca.estudiante_id = mt.id_estudiante
        ) AS finalq2,
        (
            SELECT IF(q2.promedio_final IS NULL ,'0',q2.promedio_final) AS promedio_final
            FROM mat_calificaciones ca
            LEFT JOIN mat_calificaciones_2_quimestre q2 ON ca.id = q2.mat_calificacion_id
            WHERE ca.curso_id = '$curso_id'
            AND ca.materia_id = '$materia_id'
            AND ca.estudiante_id = mt.id_estudiante
        ) AS promedio_final
        FROM mat_estudiantes_matriculados mt 
        LEFT JOIN usuario u ON mt.id_estudiante = u.idusuario 
        LEFT JOIN institucion i ON mt.institucion_id = i.idInstitucion 
        LEFT JOIN periodoescolar p ON mt.id_periodo = p.idperiodoescolar 
        LEFT JOIN mat_niveles_institucion n ON mt.curso_id = n.nivelInstitucion_id 
        LEFT JOIN nivel  c ON  n.nivel_id = c.idnivel 
        LEFT JOIN mat_paralelos pl ON n.paralelo_id = pl.paralelo_id 
        WHERE mt.curso_id =  '$curso_id'
        AND mt.estado_matricula = '1'
        ");
        return $estudiantes;
    }
    public function resumenCalificaciones(Request $request){
        //TEST
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $formatoEvaluaciones   = array();
        $formatoTareas         = array();
        $getTotalEvaluaciones = DB::SELECT("SELECT *
        FROM evaluaciones e
        WHERE e.codigo_curso = '$request->curso_id'
        AND e.id_asignatura = '$request->materia_id'
        AND e.quimestre = '$request->quimestre'
        AND e.estado = '1';
        ");
        $contador1 = count($getTotalEvaluaciones);
        for($i = 1; $i<=$contador1; $i++){
            array_push($formatoEvaluaciones,"Ev".$i);
        }
        $getTotalTareas =  DB::SELECT("SELECT *
        FROM tarea t
        WHERE t.curso_idcurso = '$request->curso_id'
        AND t.asignatura_id = '$request->materia_id'
        AND t.quimestre = '$request->quimestre'
        AND t.estado = '1'
        ");
        $contador2 = count($getTotalTareas);
        for($i = 1; $i<=$contador2; $i++){
              array_push($formatoTareas,"T".$i);
        }
        $formatUnion = array_merge($formatoTareas,$formatoEvaluaciones);
        //OBTENER CALIFICACIONES NOTAS
        //->Listado de estudiantes
        $datos = [];
        $calificacionesTareas               = [];
        $calificacionesEvaluaciones         = [];
            $contadorWhile = 0;
            while($contadorWhile < 2){
                $estudiantes = $this->estudianteCurso($request->curso_id,$request->materia_id);
                foreach($estudiantes as $key => $item){
                    //TAREAS
                    foreach($getTotalTareas as $key2 => $item2){
                        //getCalificacion
                        $getCalificacion = DB::SELECT("SELECT  ut.* FROM usuario_tarea ut
                        WHERE ut.curso_idcurso = '$request->curso_id'
                        AND ut.usuario_idusuario = '$item->idusuario'
                        AND ut.tarea_idtarea = '$item2->idtarea'");
                        if(empty($getCalificacion)) $nota = 0;
                        else $nota = $getCalificacion[0]->nota;
                        $calificacionesTareas[$key2] = [
                            "cal" => $nota
                        ];
                    }
                    //EVALUACIONES
                    foreach($getTotalEvaluaciones as $key3 => $item3){
                        //getEvaluaciones
                        $getEvaluaciones = DB::SELECT("SELECT * FROM calificaciones c
                        WHERE c.id_evaluacion = '$item3->id'
                        AND c.id_estudiante = '$item->idusuario'");
                        if(empty($getEvaluaciones)) $nota = 0;
                        else $nota = $getEvaluaciones[0]->calificacion;
                        $calificacionesEvaluaciones[$key3] = [
                            "cal" => $nota
                        ];
                    }
                    $valores = array_merge($calificacionesTareas,$calificacionesEvaluaciones);
                    $datos[$key] = [
                        "idResumen"      => $item->idResumen,
                        "finalq1"        => $item->finalq1,
                        "finalq2"        => $item->finalq2,
                        "promedio_final" => $item->promedio_final,
                        "idusuario"      => $item->idusuario,
                        "estudiante"     => $item->nombres . " " .$item->apellidos,
                        "calificaciones" => $valores,
                    ];
                }
                //calificar
                $totalNotas = $contador1 + $contador2;
                $arregloSuma = [];
                foreach($datos as $key4 => $item4){
                    $suma = 0;
                    foreach($item4["calificaciones"] as $key5 => $item5){
                        $suma += $item5["cal"];
                    }
                    //para evitar la division por cero
                    if($totalNotas == 0) $promedioQuimestre = 0;
                    else $promedioQuimestre = $suma  / $totalNotas;
                    $SumaPromedioFinal = $item4["finalq1"]+ round($promedioQuimestre, 2);
                    $PromedioFinal = $SumaPromedioFinal / 2;
                    $arregloSuma[$key4] = [
                        "idResumen"         => $item4["idResumen"],
                        "estudiante_id"     => $item4["idusuario"],
                        "promedioQuimestre" => $promedioQuimestre,
                        "PromedioFinal"     => $PromedioFinal
                    ];
                }
                //actualizar notas
                foreach($arregloSuma as $key6 => $item6){
                    if($request->quimestre == 1){
                        DB::table('mat_calificaciones_1_quimestre')
                        ->where('mat_calificacion_id', $item6["idResumen"])
                        ->where('estudiante_id', $item6["estudiante_id"])
                        ->update([
                            'final' => round($item6["promedioQuimestre"], 2),
                        ]);  
                    }
                    if($request->quimestre == 2){
                        DB::table('mat_calificaciones_2_quimestre')
                        ->where('mat_calificacion_id', $item6["idResumen"])
                        ->where('estudiante_id',$item6["estudiante_id"])
                        ->update([
                            'final2'         => round($item6["promedioQuimestre"], 2),
                            'promedio_final' => round($item6["PromedioFinal"], 2),
                        ]);  
                    }
                }
                $contadorWhile++;
            }//terminar while
        return [
            "calificaciones"    => $datos,
            "formato1q"         => $formatUnion,
            "TotalEvaluaciones" => $contador1,
            "TotalTareas"       => $contador2
        ];
     
    }
    public function getResumen1q($curso_id,$materia_id){
        $getcalificaciones = DB::SELECT("SELECT  c.id,
        c.curso_id,c.materia_id,c.estudiante_id,
        CONCAT(u.nombres ,' ', u.apellidos) AS estudiante,
        q1.*,
        IF(q1.t1 IS NULL,'0',q1.t1)  as c1,
        IF(q1.t2 IS NULL,'0',q1.t2)  as c2,
        IF(q1.t3 IS NULL,'0',q1.t3)  as c3,
        IF(q1.t4 IS NULL,'0',q1.t4)  as c4,
        IF(q1.t5 IS NULL,'0',q1.t5)  as c5,
        IF(q1.t6 IS NULL,'0',q1.t6)  as c6,
        IF(q1.t7 IS NULL,'0',q1.t7)  as c7,
        IF(q1.t8 IS NULL,'0',q1.t8)  as c8,
        IF(q1.t9 IS NULL,'0',q1.t9)  as c9,
        IF(q1.t10 IS NULL,'0',q1.t10)  as c10,
        IF(q1.t11 IS NULL,'0',q1.t11)  as c11,
        IF(q1.t12 IS NULL,'0',q1.t12)  as c12,
        IF(q1.t13 IS NULL,'0',q1.t13)  as c13,
        IF(q1.t14 IS NULL,'0',q1.t14)  as c14,
        IF(q1.t15 IS NULL,'0',q1.t15)  as c15,
        IF(q1.t16 IS NULL,'0',q1.t16)  as c16,
        IF(q1.t17 IS NULL,'0',q1.t17)  as c17,
        IF(q1.t18 IS NULL,'0',q1.t18)  as c18,
        IF(q1.t19 IS NULL,'0',q1.t19)  as c19,
        IF(q1.t20 IS NULL,'0',q1.t20)  as c20,
        IF(q1.t21 IS NULL,'0',q1.t21)  as c21,
        IF(q1.t22 IS NULL,'0',q1.t22)  as c22,
        IF(q1.t23 IS NULL,'0',q1.t23)  as c23,
        IF(q1.t24 IS NULL,'0',q1.t24)  as c24,
        IF(q1.t25 IS NULL,'0',q1.t25)  as c25,
        IF(q1.ev1 IS NULL,'0',q1.ev1)  as c26,
        IF(q1.ev2 IS NULL,'0',q1.ev2)  as c27,
        IF(q1.ev3 IS NULL,'0',q1.ev3)  as c28,
        IF(q1.ev4 IS NULL,'0',q1.ev4)  as c29,
        IF(q1.ev5 IS NULL,'0',q1.ev5)  as c30,
        IF(q1.ev6 IS NULL,'0',q1.ev6)  as c31,
        IF(q1.ev7 IS NULL,'0',q1.ev7)  as c32,
        IF(q1.ev8 IS NULL,'0',q1.ev8)  as c33,
        IF(q1.ev9 IS NULL,'0',q1.ev9)  as c34,
        IF(q1.ev10 IS NULL,'0',q1.ev10)  as c35
        FROM mat_calificaciones c
        LEFT JOIN usuario u ON c.estudiante_id = u.idusuario 
        LEFT JOIN mat_calificaciones_1_quimestre q1 ON  c.id = q1.mat_calificacion_id
        WHERE c.curso_id = '$curso_id'
        AND c.materia_id = '$materia_id'
        ");
        return $getcalificaciones;
    }
    public function getResumen2q($curso_id,$materia_id){
        $getcalificaciones = DB::SELECT("SELECT  c.id,
        c.curso_id,c.materia_id,c.estudiante_id, q1.final,
        CONCAT(u.nombres ,' ', u.apellidos) AS estudiante,
        q2.*,
        IF(q2.t1 IS NULL,'0',q1.t1)  as c1,
        IF(q2.t2 IS NULL,'0',q1.t2)  as c2,
        IF(q2.t3 IS NULL,'0',q1.t3)  as c3,
        IF(q2.t4 IS NULL,'0',q1.t4)  as c4,
        IF(q2.t5 IS NULL,'0',q1.t5)  as c5,
        IF(q2.t6 IS NULL,'0',q1.t6)  as c6,
        IF(q2.t7 IS NULL,'0',q1.t7)  as c7,
        IF(q2.t8 IS NULL,'0',q2.t8)  as c8,
        IF(q2.t9 IS NULL,'0',q2.t9)  as c9,
        IF(q2.t10 IS NULL,'0',q2.t10)  as c10,
        IF(q2.t11 IS NULL,'0',q2.t11)  as c11,
        IF(q2.t12 IS NULL,'0',q2.t12)  as c12,
        IF(q2.t13 IS NULL,'0',q2.t13)  as c13,
        IF(q2.t14 IS NULL,'0',q2.t14)  as c14,
        IF(q2.t15 IS NULL,'0',q2.t15)  as c15,
        IF(q2.t16 IS NULL,'0',q2.t16)  as c16,
        IF(q2.t17 IS NULL,'0',q2.t17)  as c17,
        IF(q2.t18 IS NULL,'0',q2.t18)  as c18,
        IF(q2.t19 IS NULL,'0',q2.t19)  as c19,
        IF(q2.t20 IS NULL,'0',q2.t20)  as c20,
        IF(q2.t21 IS NULL,'0',q2.t21)  as c21,
        IF(q2.t22 IS NULL,'0',q2.t22)  as c22,
        IF(q2.t23 IS NULL,'0',q2.t23)  as c23,
        IF(q2.t24 IS NULL,'0',q2.t24)  as c24,
        IF(q2.t25 IS NULL,'0',q2.t25)  as c25,
        IF(q2.ev1 IS NULL,'0',q2.ev1)  as c26,
        IF(q2.ev2 IS NULL,'0',q2.ev2)  as c27,
        IF(q2.ev3 IS NULL,'0',q2.ev3)  as c28,
        IF(q2.ev4 IS NULL,'0',q2.ev4)  as c29,
        IF(q2.ev5 IS NULL,'0',q2.ev5)  as c30,
        IF(q2.ev6 IS NULL,'0',q2.ev6)  as c31,
        IF(q2.ev7 IS NULL,'0',q2.ev7)  as c32,
        IF(q2.ev8 IS NULL,'0',q2.ev8)  as c33,
        IF(q2.ev9 IS NULL,'0',q2.ev9)  as c34,
        IF(q2.ev10 IS NULL,'0',q2.ev10)  as c35
        FROM mat_calificaciones c
        LEFT JOIN usuario u ON c.estudiante_id = u.idusuario 
        LEFT JOIN mat_calificaciones_1_quimestre q1 ON  c.id = q1.mat_calificacion_id
        LEFT JOIN mat_calificaciones_2_quimestre q2 ON  c.id = q2.mat_calificacion_id
        WHERE c.curso_id = '$curso_id'
        AND c.materia_id = '$materia_id'
        ");
        return $getcalificaciones;
    }

    public function formatQuimestre($curso_id,$materia_id){
        $formato = DB::SELECT("SELECT 
        q1.t1 ,
        q1.t2 ,
        q1.t3 ,
        q1.t4 ,
        q1.t5 ,
        q1.t6 ,
        q1.t7 ,
        q1.t8 ,
        q1.t9 ,
        q1.t10,
        q1.t11,
        q1.t12,
        q1.t13,
        q1.t14,
        q1.t15,
        q1.t16,
        q1.t17,
        q1.t18,
        q1.t19,
        q1.t20,
        q1.t21,
        q1.t22,
        q1.t23,
        q1.t24,
        q1.t25,
        
        q1.ev1,
        q1.ev2,
        q1.ev3,
        q1.ev4,
        q1.ev5,
        q1.ev6,
        q1.ev7,
        q1.ev8,
        q1.ev9,
        q1.ev10
        FROM mat_calificaciones c
        LEFT JOIN usuario u ON c.estudiante_id = u.idusuario 
        LEFT JOIN mat_calificaciones_1_quimestre q1 ON  c.id = q1.mat_calificacion_id
        WHERE c.curso_id = '$curso_id'
        AND c.materia_id = '$materia_id'
        ");
        return $formato;
    }
    
    public function show($id)
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
    //api:get/estudiantexCurso/{curso}
    public function estudiantexCurso($curso){
        $query = DB::SELECT("CALL `estudianteCurso`('$curso')");
        return $query;
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
    //api para asignar el estudiante a la matricula como pendiente
    public function asignarEstudianteToPendiente(Request $request){
            if($request->id_matricula > 0){
                $estudiante  = EstudianteMatriculado::findOrFail($request->id_matricula);
            }
            //guardar
            else{
                $estudiante  = new EstudianteMatriculado();
            }
            $estudiante->id_estudiante      = $request->id_estudiante;
            $estudiante->institucion_id     = $request->institucion_id;
            $estudiante->id_periodo         = $request->id_periodo;
            $estudiante->curso_id           = $request->curso_id;
            $estudiante->observacion        = $request->observacion;
            $estudiante->user_created       = $request->user_created;
            if($request->estado_matricula != 1){
                $estudiante->estado_matricula   = $request->estado_matricula;
            }
            $estudiante->save();
            //para la matricula se validara que haya pagado la matricula
            if($request->estado_matricula == 1){
                //estudiantes del curso
                $totalEstudiantes = DB::SELECT(" SELECT COUNT(mt.id_matricula) AS contador FROM mat_estudiantes_matriculados mt
                WHERE mt.curso_id = '$request->curso_id'
                AND mt.estado_matricula = '1'
                ");
                $contadorCurso = $totalEstudiantes[0]->contador;
                $cupos = $contadorCurso + 1;
                //validacion que haya cupos 
                $validateCupo = DB::SELECT("SELECT * FROM mat_niveles_institucion mn
                WHERE mn.nivelInstitucion_id = '$request->curso_id'
                ");
                $getCupos = $validateCupo[0]->maximo_estudiantes;
                if($cupos > $getCupos){
                    return ["status" => "3",  "message" =>  "Ya no hay cupos para este curso","id_matricula"=>$estudiante->id_matricula];
                } 
                //validacion que este configurado el valor de la pension / la fecha de inicio pension
                $validateConfiguracion = $this->listadoConfiguracion($request->institucion_id,$request->id_periodo);
                //declare variables
                $fecha_inicio_pension   = $validateConfiguracion[0]->fecha_inicio_pension;
                $precio_pension         = $validateConfiguracion[0]->precio_pension;
                $num_cuotas             = $validateConfiguracion[0]->num_cuotas;
                if(empty($validateConfiguracion)){
                    return ["status" => "3",  "message" =>  "Configure por favor los valores de la pensión ","id_matricula"=>$estudiante->id_matricula];
                }
                //validacion a los que no son becados la obligatoriedad de tener pagado la matricula
                if($request->becado == 0){
                    $validate = $this->validatePagoCuota($estudiante->id_matricula,1);
                    if(empty($validate)){
                        return ["status" => "3",  "message" =>  "El estudiante aun no ha pagado la matrícula no puede matricularse ","id_matricula"=>$estudiante->id_matricula];
                    }
                    $valor = $validate[0]->valor_pendiente;
                    if($valor > 0){
                        return ["status" => "3",  "message" =>  "El estudiante aun no ha pagado la matrícula no puede matricularse ","id_matricula"=>$estudiante->id_matricula];
                    }
                }
                //Generar cuotas(Valido si existe la cuota 2 significa que las demas cuotas ya se han generado)
                $validateCuota = $this->validatePagoCuota($estudiante->id_matricula,2);
                if(empty($validateCuota)){
                    //valido que sea becado
                    if($request->becado == 1){
                        $this->generateCuotas($estudiante->id_matricula,$precio_pension,$num_cuotas,$fecha_inicio_pension,$request->id_estudiante,$request->user_created,$request->becado);
                    }
                    //si no es becado
                    else{
                        $this->generateCuotas($estudiante->id_matricula,$precio_pension,$num_cuotas,$fecha_inicio_pension,$request->id_estudiante,$request->user_created,$request->becado);
                    }
                }
                //actualizo la fecha de matricula 
                $estudiante2  = EstudianteMatriculado::findOrFail($estudiante->id_matricula);
                $hoy = date("Y-m-d H:i:s");     
                $estudiante2->fecha_matricula = $hoy;
                $estudiante2->estado_matricula   = $request->estado_matricula;
                $estudiante2->save();
            }
            if($estudiante){
                return $estudiante;
            }else{
                return ["status" => "3",  "message" =>  "No se pudo guardar"];
            }
    }
    //para generar las cuotas de las pensiones
    public function generateCuotas($id_matricula,$valor_cuota,$num_cuotas,$fecha_inicio_pension,$id_estudiante,$user_created,$becado){
        $cont =0;
        $contador = 2;
        while ($cont < $num_cuotas) {
            //pensiones
            $cuotas1=new CuotasPorCobrar;
            $cuotas1->id_matricula=$id_matricula;
            $cuotas1->valor_cuota=$valor_cuota;
            if($becado == 1){
                $cuotas1->valor_pendiente= 0;
            }else{
                $cuotas1->valor_pendiente=$valor_cuota;
            }
            $cuotas1->fecha_a_pagar = date("Y-m-d",strtotime($fecha_inicio_pension."+ $cont month"));
            $cuotas1->num_cuota = $contador;
            $cuotas1->estudiante_id =$id_estudiante;
            $cuotas1->user_created = $user_created;
            $cuotas1->save();
            $cont=$cont+1;
            $contador=$contador+1;
        }
    }
    //para validar si la cuota esta pagada
    public function validatePagoCuota($id_matricula,$cuota){
        $validate = DB::SELECT("SELECT mc.valor_pendiente FROM mat_cuotas_por_cobrar mc
        WHERE mc.id_matricula = '$id_matricula'
        AND mc.num_cuota = '$cuota'
        ");
        return $validate;
    }
    ///============PAGOS===========================
    //Matricula Import
    //api:post/guardarMatricula
    public function guardarMatricula(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $estudiantes = json_decode($request->data_estudiantes);  
        $estudiantesYaMatriculados=[];
        $cedulasNoExiste = [];
        $cedulasNoCambiadas = [];
        $porcentaje = 0;
        $contador = 0; 
        foreach($estudiantes as $key => $item){
            //validar si el estudiante existe
            $validar = DB::SELECT("SELECT u.*, CONCAT(u.nombres,' ',u.apellidos) as estudiante,
            u.becado, i.nombreInstitucion,g.deskripsi AS rol
            FROM usuario u
            LEFT JOIN institucion i ON u.institucion_idInstitucion = i.idInstitucion
            LEFT JOIN sys_group_users g ON u.id_group = g.id
            WHERE u.cedula = '$item->cedula'
            ");
            //valida que el estudiante existe
            if(count($validar) > 0){
                $idusuario = $validar[0]->idusuario;
                //validar si el estudiante  ya se encuentre matriculado
                $validateMatricula = DB::SELECT("SELECT m.*,n.nombrenivel,
                p.descripcion as paralelo,i.nombreInstitucion,u.becado
                FROM mat_estudiantes_matriculados m
                LEFT JOIN mat_niveles_institucion mn ON m.curso_id = mn.nivelInstitucion_id
                LEFT JOIN nivel n ON mn.nivel_id = n.idnivel
                LEFT JOIN mat_paralelos p ON  mn.paralelo_id = p.paralelo_id
                LEFT JOIN institucion i ON mn.institucion_id = i.idInstitucion
                LEFT JOIN usuario u ON m.id_estudiante = u.idusuario
                WHERE m.id_estudiante = '$idusuario'
                AND m.id_periodo = '$request->periodo_id'
                AND m.institucion_id = '$request->institucion_id'
                ");
                //SI el estudiante no ha subido una evidencia de pago
                if(empty($validateMatricula)){
                    $estudiante  = new EstudianteMatriculado();
                    $estudiante->id_estudiante      = $validar[0]->idusuario;
                    $estudiante->institucion_id     = $request->institucion_id;
                    $estudiante->id_periodo         = $request->periodo_id;
                    $estudiante->observacion        = $request->observacion;
                    $estudiante->user_created       = $request->user_created;
                    $estudiante->estado_matricula   = "0";
                    $estudiante->save();
                    if($estudiante){
                        $this->registrarPago($estudiante->id_estudiante,$estudiante->id_matricula,$request->precio_matricula,0,1);
                        $porcentaje++;
                    }else{
                        $cedulasNoCambiadas[$key] =[
                            "cedulas" => $item->cedula
                        ];
                    }  
                }
                else{
                    //SI EL ESTUDIANTE TIENE PENDIENTE EL ESTADO DE LA MATRICULA
                    //validar que no haya pagado la cuota
                    $validate = $this->validatePagoCuota($validateMatricula[0]->id_matricula,1);
                    if(empty($validate)){
                        //registro el pago de la matricula
                        $this->registrarPago($validar[0]->idusuario,$validateMatricula[0]->id_matricula,$request->precio_matricula,0,1);
                        $porcentaje++;
                    }else{
                        $estudiantesYaMatriculados[$contador] = [
                            "estudiante"        => $validar[0]->estudiante,
                            "cedula"            => $validar[0]->cedula,
                            "nombreInstitucion" => $validateMatricula[0]->nombreInstitucion,
                            "rol"               => $validar[0]->rol,
                            "nivelmatriculado"  => $validateMatricula[0]->nombrenivel,
                            "paralelo"          => $validateMatricula[0]->paralelo,
                            "becado"            => $validateMatricula[0]->becado,
                            "estado_matricula"  => $validateMatricula[0]->estado_matricula
                        ];
                        $contador++;
                    }
                   
                }
            }else{
                $cedulasNoExiste[$key] =[
                    "cedulas" => $item->cedula
                ];
            }
        }
        return [
            "cambiados"                 => $porcentaje,
            "cedulasNoCambiadas"        => $cedulasNoCambiadas,
            "estudiantesYaMatriculados" => $estudiantesYaMatriculados,
            "cedulasNoExiste"           => $cedulasNoExiste
        ];
    }
    public function registrarPago($estudiante,$id_matricula,$valor_cuota,$valor_pendiente,$num_cuota){
        $todate  = date('Y-m-d H:i:s');   
        //validate si la cuota ya existe
        $pago = new CuotasPorCobrar();
        $pago->id_matricula     = $id_matricula;
        $pago->valor_cuota      = $valor_cuota;
        $pago->valor_pendiente  = $valor_pendiente;
        $pago->fecha_a_pagar    = $todate;
        $pago->num_cuota        = $num_cuota;
        $pago->fecha_a_pagar    = $todate;
        $pago->estudiante_id    = $estudiante;
        $pago->save();

    }
    ///============FIN PAGOS======================

    //=============CONFIGURACION====================
    public function guardarConfiguracion(Request $request){
        //validar si existe la configuracion para la institucion en el periodo
        $validate = $this->listadoConfiguracion($request->institucion_id,$request->periodo_id);
        if(empty($validate)){
            $configuracion = new MatConfiguracion();
        }else{
            $configuracion = MatConfiguracion::findOrFail($validate[0]->id);
        }
        $configuracion->institucion_id          = $request->institucion_id;
        $configuracion->periodo_id              = $request->periodo_id;
        $configuracion->precio_matricula        = $request->precio_matricula;
        $configuracion->precio_anio_escolar     = $request->precio_anio_escolar;
        if($request->precio_anio_escolar > 10){
            $precio = $request->precio_anio_escolar / $request->num_cuotas;
            $configuracion->precio_pension      = $precio;
        }
        $configuracion->num_cuotas              = $request->num_cuotas;
        $configuracion->fecha_inicio_pension    = $request->fecha_inicio_pension;
        $configuracion->save();
        if($configuracion){
            return ["status" => "1","message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0","message" => "No se pudo guardar"];
        }
    }
    public function guardarConfiguracionQuimestre(Request $request){
         //validar si existe la configuracion del quimestre para la institucion en el periodo
         $validate = $this->listadoConfiguracionQuimestre($request->institucion_id,$request->periodo_id);
         if(empty($validate)){
            $configuracion = new MatConfiguracionQuimestre();
         }else{
            $configuracion = MatConfiguracionQuimestre::findOrFail($validate[0]->id);
         }
         $configuracion->institucion_id             = $request->institucion_id;
         $configuracion->periodo_id                 = $request->periodo_id;
         $configuracion->fecha_inicio_pq            = $request->fecha_inicio_pq;
         $configuracion->fecha_fin_pq               = $request->fecha_fin_pq;
         $configuracion->fecha_inicio_sq            = $request->fecha_inicio_sq;
         $configuracion->fecha_fin_sq               = $request->fecha_fin_sq;
         $configuracion->quimestreActivo            = $request->quimestreActivo;
         $configuracion->save();
         if($configuracion){
             return ["status" => "1","message" => "Se guardo correctamente"];
         }else{
             return ["status" => "0","message" => "No se pudo guardar"];
         }
    }
    //=============FIN CONFIGURACION=================
    ///================ZOOM===========================
    public function zoom(Request $request){
        if($request->listarZoom){
            return $this->listarZoom($request->idcurso);
        }
    }
    public function listarZoom($idcurso){
        $list = DB::SELECT("SELECT * FROM docente_zoom z
        WHERE z.idcurso = '$idcurso'
        ORDER BY z.id DESC
        ");
        return $list;
    }
    public function guardarzoom(Request $request){
        if($request->id > 0){
            $zoom = Zoom::findOrFail($request->id);
        }else{
            $zoom = new Zoom();
            $zoom->periodo_id       = $request->periodo_id;
        }
            $zoom->idcurso          = $request->idcurso;
            $zoom->link             = $request->link;
            $zoom->descripcion      = $request->descripcion;
            $zoom->fecha_expiracion = $request->fecha_expiracion;
            $zoom->user_created     = $request->user_created;
            $zoom->estado           = $request->estado;
            $zoom->save();
            if($zoom){
                return ["status" => "1", "message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0", "message" => "No se pudo guardar"];
            }
    }
    public function deletezoom(Request $request){
        Zoom::findOrFail($request->id)->delete();
    }
    //=================FIN ZOOM==========================
    public function generateFormatoTareas(Request $request){
        $validate = DB::SELECT("SELECT * FROM usuario_tarea t
        WHERE t.curso_idcurso = '$request->curso_id'
        AND t.tarea_idtarea = '$request->tarea_id'
        AND t.usuario_idusuario = '$request->idusuario'
        ");
        if(empty($validate)){
            $tarea = new UsuarioTarea();
            $tarea->tarea_idtarea           = $request->tarea_id;
            $tarea->curso_idcurso           = $request->curso_id;
            $tarea->usuario_idusuario       = $request->idusuario;
            $tarea->estado                  = 5;
            $tarea->save();     
        }
    }
    public function FormatoReportePensiones(Request $request){
        //validacion que este configurado el valor de la pension / la fecha de inicio pension
        $validateConfiguracion = $this->listadoConfiguracion($request->institucion_id,$request->id_periodo);
        //declare variables
        $fecha_inicio_pension   = $validateConfiguracion[0]->fecha_inicio_pension;
        $num_cuotas             = $validateConfiguracion[0]->num_cuotas;
        //generarFechas
        $cont =0;
        $datos = [];
        while ($cont < $num_cuotas) {
            $datos[$cont] = date("Y-m-d",strtotime($fecha_inicio_pension."+ $cont month"));
            $cont=$cont+1;
        }
        return $datos;
    }
    public function ReportePensiones(Request $request){
        //traer los estudiantes del curso seleccionado
        $estudiantes = DB::SELECT("CALL `estudianteCurso`('$request->curso_id')");
        if(empty($estudiantes)){
            return ["status" => "0", "message" => "El curso no tiene estudiantes"];
        }
        $datos = [];
        foreach($estudiantes as $key => $item){ 
            $pagos = DB::SELECT("SELECT * FROM mat_cuotas_por_cobrar m
            WHERE m.estudiante_id = '$item->idusuario'
            AND m.id_matricula = '$item->id_matricula'
            AND m.fecha_a_pagar = '$request->fecha'
            ");
            // return $pagos;
            if(count($pagos) > 0){
                $datos[$key] = [
                    "nombres"           => $item->nombres . " " . $item->apellidos,
                    "cedula"            => $item->cedula,
                    "becado"            => $item->becado,
                    "becadoStatus"      => $item->becado == 1 ? 'Becado' : '',
                    "fecha_a_pagar"     => $pagos[0]->fecha_a_pagar,
                    "valor_cuota"       => $pagos[0]->valor_cuota,
                    "valor_pendiente"   => $pagos[0]->valor_pendiente,
                ];
            }
        }
        return $datos;
    }
}
