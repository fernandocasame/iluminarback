<?php

namespace App\Http\Controllers;

use App\Models\FreeEstudianteLibro;
use App\Models\Libro;
use App\Models\LibroSerie;
use Illuminate\Http\Request;
use DB;
use App\Quotation;
use DateTime;
class LibroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // return csrf_token();
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $idusuario = $request->idusuario;
        $idInstitucion = $request->idinstitucion;
        if($idInstitucion == 66){
            $libro = DB::select("SELECT libro.*,asignatura.* FROM libro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura  ORDER BY  `libro`.`asignatura_idasignatura` ASC ");
        }else{
            $libro = DB::select('CALL datoslibrosd(?)',[$idusuario]);
        }
        return $libro;
    }
    public function librosEstudiante(Request $request)
    {
        $idregion='';
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $idusuario = $request->idusuario;
        $region = DB::SELECT("SELECT * FROM `usuario` JOIN institucion ON institucion.idInstitucion = usuario.institucion_idInstitucion WHERE `idusuario`= ?",[$idusuario]);
        foreach ($region as $key) {
            $idregion = $key->region_idregion;
        }
        if($idregion == 1){

            $libro = DB::select('CALL datoslibrosEstudianteSierra(?)',[$idusuario]);
            return $libro;

        }else{

            $libro = DB::select('CALL datoslibrosEstudiante(?)',[$idusuario]);
            return $libro;

        }
    }

    public function Historial(Request $request){
        $date = new DateTime();
        $idusuario = auth()->user()->idusuario;
        $idlibro = $request->idlibro;
        $fecha = $date->format('y-m-d');
        $hora = $date->format('H:i:s');
        DB::insert("INSERT INTO `libro_has_usuario`(`libro_idlibro`, `usuario_idusuario`, `fecha`, `hora`) VALUES (?,?,?,?)",[$idlibro,$idusuario,$fecha,$hora]);
    }

    public function aplicativo(Request $request)
    {
        $libros = DB::select('CALL datoslibrosd(?)',[$request->idusuario]);
        return $libros;
    }

    public function aplicativoEstudiante(Request $request)
    {
        $libros = DB::select("SELECT libro.* FROM `estudiante` JOIN curso ON curso.codigo = estudiante.codigo JOIN libro_has_curso ON libro_has_curso.curso_idcurso = curso.idcurso join libro ON libro.idlibro = libro_has_curso.libro_idlibro WHERE estudiante.usuario_idusuario = ? AND curso.estado = '1' AND libro.grupo = '1'",[$request->idusuario]);
        return $libros;
    }

    public function libro(Request $request)
    {
        // if($request->idgrupo == 1){
        //     $libro = DB::select('SELECT * FROM libro');
        //     return $libro;
        // }

        // if($request->idgrupo == 11){
        //     switch ($request->idinstitucion) {
        //         case 66:
        //             $libro = DB::select('SELECT libro.* FROM libros_region_free join libro on libro.idlibro=libros_region_free.libro');
        //             return $libro;
        //         break;
        //         case 905:
        //             $libro = DB::select('SELECT libro.* FROM libros_region_free join libro on libro.idlibro=libros_region_free.libro');
        //             return $libro;
        //         break;
        //     }
        // }
        $libros = DB::SELECT("SELECT ls.id_serie,ls.year, ls.version, s.nombre_serie, l.* FROM libro l
        LEFT JOIN libros_series ls ON l.idlibro = ls.idLibro
        LEFT JOIN series s ON ls.id_serie = s.id_serie
        WHERE s.id_serie IS NOT NULL
        AND l.Estado_idEstado = '1'
        ");
        return $libros;
    }

    public function planlector(Request $request)
    {
        if($request->idgrupo == 1){
            $planlector = DB::select('SELECT * FROM planlector WHERE planlector.estado_idEstado = "1"');
            return $planlector;
        }

        if($request->idgrupo == 11){
            switch ($request->idinstitucion) {
                case 66:
                    $planlector = DB::select('SELECT planlector.* FROM planlector_region_free join planlector on planlector.idplanlector=planlector_region_free.planlector WHERE planlector.estado_idEstado = "1"');
                    return $planlector;
                break;
                case 905:
                    $planlector = DB::select('SELECT planlector.* FROM planlector_region_free join planlector on planlector.idplanlector=planlector_region_free.planlector WHERE planlector.estado_idEstado = "1"');
                    return $planlector;
                break;
            }
        }
    }

    public function setNivelFree(Request $request)
    {
        $niveles = explode(",", $request->niveles);
        try {
            DB::delete('DELETE FROM `planlector_nivel` WHERE `institucion_planlector` = ?', [$request->id]);
            foreach ($niveles as $key => $value) {
                DB::insert('insert into planlector_nivel (institucion_planlector, nivel) values (?, ?)', [$request->id, $value]);
            }
        } catch (\Throwable $th) {
            foreach ($niveles as $key => $value) {
                DB::insert('insert into planlector_nivel (institucion_planlector, nivel) values (?, ?)', [$request->id, $value]);
            }
        }
        // return $request->niveles;
    }

    
    public function guardarLibroFree(Request $request){
        $libro = new FreeEstudianteLibro();
        $libro->institucion_id = $request->institucion_id;
        $libro->periodo_id = $request->periodo_id;
        $libro->libro_id = $request->idlibro;
        $libro->serie_id = $request->serie_id;
        $libro->nivel_id = $request->nivel_id;
      
        $libro->save();
        if($libro){
            return ["status" => "1", "message"=> "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message"=> "No se pudo guardar"]; 
        }

    }

    public function eliminarLibroGratis(Request $request){
        FreeEstudianteLibro::findOrFail($request->id)->delete();
    }


    public function libroFree(Request $request){
        $libro = DB::INSERT("INSERT INTO institucion_libro(idinstitucion, idlibro) VALUES (?,?)",[$request->idinstitucion, $request->idlibro]);
    }

    public function planlectorFree(Request $request){
        $libro = DB::INSERT("INSERT INTO institucion_planlector(idinstitucion, idplanlector) VALUES (?,?)",[$request->idinstitucion, $request->idplanlector]);
    }

    public function listaFree(Request $request){

        $getPeriodo = $this->traerPeriodo($request->institucion_id);

        $periodo = $getPeriodo[0]->periodo;
     

        $libros = DB::SELECT("SELECT n.orden, ls.nombre_serie,  n.nombrenivel, l.nombrelibro, f.* FROM free_estudiante_libro f
        LEFT JOIN nivel n ON f.nivel_id = n.idnivel
        LEFT JOIN libro l ON f.libro_id = l.idlibro
        LEFT JOIN series ls ON f.serie_id = ls.id_serie
        WHERE institucion_id = '$request->institucion_id'
        AND periodo_id ='$periodo'
        ");
        return $libros;
        // $libros = DB::SELECT("SELECT * FROM institucion_libro join libro on libro.idlibro = institucion_libro.idlibro join asignatura on asignatura.idasignatura = libro.asignatura_idasignatura WHERE institucion_libro.idinstitucion = ? AND institucion_libro.estado = '1'",[$request->idinstitucion]);
        // foreach ($libros as $key => $post) {
        //     $respuesta = DB::SELECT("SELECT * FROM libro_nivel join nivel on nivel.idnivel = libro_nivel.nivel WHERE institucion_libro = ? ",[$post->id]);
        //     $data['items'][$key] = [
        //         'id' => $post->id,
        //         'idinstitucion' => $post->idinstitucion,
        //         'idlibro' => $post->idlibro,
        //         'nombrelibro' => $post->nombrelibro,
        //         'nombreasignatura' => $post->nombreasignatura,
        //         'estado' => $post->estado,
        //         'niveles'=>$respuesta,
        //     ];
        // }
        // return $data;
    }

    public function traerPeriodo($institucion){
           //para traer el periodo de una institucion
           $periodoInstitucion = DB::SELECT("SELECT idperiodoescolar AS periodo ,(SELECT nombreInstitucion FROM institucion where idInstitucion = '$institucion' ) as nombreInstitucion, periodoescolar AS descripcion FROM periodoescolar WHERE idperiodoescolar = (
            SELECT  pir.periodoescolar_idperiodoescolar as id_periodo
            from institucion i,  periodoescolar_has_institucion pir
            WHERE i.idInstitucion = pir.institucion_idInstitucion
            AND pir.id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi
            WHERE phi.institucion_idInstitucion = i.idInstitucion
            AND i.idInstitucion = '$institucion'))
        ");
        return $periodoInstitucion;
    }

    public function listaFreePlanlector(Request $request){
        try {
            $data['items'] = [];
            //code...
            $planlectors = DB::SELECT("SELECT * FROM institucion_planlector join planlector on planlector.idplanlector = institucion_planlector.idplanlector WHERE institucion_planlector.idinstitucion = ?
            AND institucion_planlector.estado = 1",[$request->idinstitucion]);
            foreach ($planlectors as $key => $post) {
                $respuesta = DB::SELECT("SELECT * FROM planlector_nivel join nivel on nivel.idnivel = planlector_nivel.nivel WHERE institucion_planlector = ? ",[$post->id]);
                $data['items'][$key] = [
                    'id' => $post->id,
                    'idinstitucion' => $post->idinstitucion,
                    'idplanlector' => $post->idplanlector,
                    'nombreplanlector' => $post->nombreplanlector,
                    'estado' => $post->estado,
                    'niveles'=>$respuesta,
                ];
            }
        } catch (\Throwable $th) {
            $data['items'] = [];
        }
        return $data;
    }

    public function eliminarLibroFree(Request $request){
        DB::DELETE("UPDATE `institucion_libro` SET `estado`='0' WHERE  `id` = ?",[$request->id]);
    }

    public function eliminarPlanlectorFree(Request $request){
        $resp = DB::DELETE("UPDATE `institucion_planlector` SET `estado`= 0 WHERE  `id` = ?",[$request->id]);
        return $resp;
    }

    public function audio(Request $request)
    {
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $idusuario = auth()->user()->idusuario;
        $libro = DB::select('CALL datoslibrosdd(?)',[$idusuario]);
        return $libro;
    }

    public function registraringreso(){
        $idusuario = auth()->user()->idusuario;
        $ip = $_SERVER['REMOTE_ADDR'];
        $navegador = "GoogleChrome";
        DB::insert("INSERT INTO `registro_usuario`( `ip`, `navegador`, `usuario_idusuario`) VALUES (?,?,?)",["$ip","$navegador",$idusuario]);
        //DB::update("UPDATE `usuario` SET `p_ingreso`=?   WHERE `idusuario` = ?",['1',$idusuario]);
    }




    public function quitarlibroestudiante(Request $request){

        $buscarCodigo = DB::SELECT("SELECT codigoslibros.codigo FROM `codigoslibros` WHERE idusuario = ? AND libro_idlibro=?",[$request->idusuario,$request->idlibro]);
        DB::INSERT("INSERT INTO hist_codlibros(id_usuario, codigo_libro, observacion) VALUES (?,?,?)",[$request->idusuario, $buscarCodigo[0]->codigo, 'eliminado']);
        $libro = DB::UPDATE("UPDATE `codigoslibros` SET `idusuario` = 0 WHERE `idusuario` = $request->idusuario AND `libro_idlibro` = $request->idlibro");
        //registro en el historico de codigos al quitar el libro del estudiante
        return $libro;
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
        Libro::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Libro  $libro
     * @return \Illuminate\Http\Response
     */
    public function show(Libro $libro)
    {
        $libros = DB::select('CALL datoslibrosdd(?)',[$libro]);
        return $libros;
    }



    public function desgloselibrousuario($libro)
    {
        $libro = DB::select('CALL desgloselibro(?)',[$libro]);
        return $libro;
    }



    public function menu_unidades_libros($libro)
    {
        $unidades = DB::SELECT('SELECT u.*, l.weblibro FROM unidades_libros u, libro l WHERE u.id_libro = l.idlibro AND u.id_libro = ?',[$libro]);
        return $unidades;
    }


    public function unidades_asignatura($idasignatura)
    {
        $unidades = DB::SELECT('SELECT u .*, concat(u.unidad, " - ", u.nombre_unidad) as label_unidad, l.weblibro, concat(u.unidad, " - ", u.nombre_unidad) as label, u.id_unidad_libro as id FROM unidades_libros u, libro l WHERE u.id_libro = l.idlibro AND l.asignatura_idasignatura = ? ORDER BY u.unidad',[$idasignatura]);
        return $unidades;
    }

    public function planificacionesunidades_tema($id_tema)
    {
        $animaciones = DB::SELECT('SELECT * FROM actividades_animaciones aa, temas t WHERE aa.id_tema = t.id AND aa.tipo = 1 AND t.id_unidad = ?',[$id_tema]);

        return $animaciones;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Libro  $libro
     * @return \Illuminate\Http\Response
     */
    public function edit(Libro $libro)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Libro  $libro
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $respuesta=DB::update('UPDATE libro SET nombrelibro = ? ,descripcionlibro = ? ,weblibro = ? ,exelibro = ? ,pdfsinguia = ? ,pdfconguia = ? ,guiadidactica = ? ,Estado_idEstado = ? ,asignatura_idasignatura = ? ,ziplibro = ?  WHERE idlibro = ?',[$request->nombrelibro,$request->descripcionlibro,$request->weblibro,$request->exelibro,$request->pdfsinguia,$request->pdfconguia,$request->guiadidactica,$request->Estado_idEstado,$request->asignatura_idasignatura,$request->ziplibro,$request->idlibro]);
        return $respuesta;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Libro  $libro
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        DB::delete('DELETE FROM libro WHERE idlibro = ?',[$request->idlibro]);
    }


    //Para listar la tabla de libros
    public function listaLibro(){
        $libros = DB::SELECT("SELECT l.*, a.nombreasignatura as asignatura,  ls.iniciales, ls.codigo_liquidacion, ls.year, ls.version, s.id_serie, s.nombre_serie
        FROM libro l, asignatura a , libros_series ls, series s
         WHERE  l.asignatura_idasignatura  = a.idasignatura
         and l.idlibro = ls.idLibro
         and ls.id_serie = s.id_serie
         ORDER  BY l.idlibro  DESC
       ");

    $asignatura = DB::SELECT("SELECT asignatura.* FROM asignatura 
    WHERE estado = '1' 
    AND tipo_asignatura = '1'
    ORDER BY idasignatura DESC");


        return["libros" => $libros, "asignatura" => $asignatura];
    }
    //para guardar o actualizar libro api::/guardarLibro
    public function guardarLibro(Request $request){


        try{
            DB::beginTransaction();
            if($request->idlibro ){

                $libro = Libro::findOrFail($request->idlibro);
                $libro->nombrelibro = $request->nombrelibro;
                $libro->descripcionlibro = $request->descripcionlibro;
                $libro->serie = $request->serie;
                $libro->weblibro = $request->weblibro;
                $libro->exelibro = $request->exelibro;
                $libro->pdfsinguia = $request->pdfsinguia;
                $libro->pdfconguia = $request->pdfconguia;
                $libro->guiadidactica = $request->guiadidactica;
                $libro->asignatura_idasignatura  = $request->asignatura_idasignatura;
                $libro->ziplibro = $request->ziplibro;
                $libro->portada = $request->portada;
                $libro->save();

                $librosSerie  = DB::table('libros_series')
                ->where('idLibro',$request->idlibro)
                ->update([
                    'id_serie' =>  $request->id_serie,
                    'iniciales' => $request->codigo_liquidacion,
                    'codigo_liquidacion' => $request->codigo_liquidacion,
                    'nombre'=> $libro->nombrelibro,
                    'year' => $request->year,
                    'version' => $request->version2
                ]);
                // ->get();






               }else{
                   $libro = new Libro;
                   $libro->nombrelibro = $request->nombrelibro;
                   $libro->descripcionlibro = $request->descripcionlibro;
                   $libro->serie = $request->serie;
                   $libro->weblibro = $request->weblibro;
                   $libro->exelibro = $request->exelibro;
                   $libro->pdfsinguia = $request->pdfsinguia;
                   $libro->pdfconguia = $request->pdfconguia;
                   $libro->guiadidactica = $request->guiadidactica;
                   $libro->asignatura_idasignatura  = $request->asignatura_idasignatura;
                   $libro->ziplibro = $request->ziplibro;
                   $libro->portada = $request->portada;
                   $libro->save();
                    //para agregar en la tabla serie
                    $librosSerie = new LibroSerie();
                    $librosSerie->idLibro = $libro->idlibro;
                    $librosSerie->id_serie = $request->id_serie;
                    $librosSerie->iniciales = $request->iniciales;
                    $librosSerie->codigo_liquidacion = $request->codigo_liquidacion;
                    $librosSerie->nombre = $libro->nombrelibro;
                    $librosSerie->year = $request->year;
                    $librosSerie->version = $request->version2;
                    $librosSerie->boton = "success";
                    $librosSerie->save();
               }



            DB::commit();
        }catch(\Exception $e){
            return ["error"=>"0", "message" => "No se pudo actualizar/guardar","error"=>$e];
            DB::rollback();
        }

           if($libro){
            return ["status"=>"1", "message" => "Se guardo correctamente"];

           }else{
            return ["error"=>"0", "message" => "No se pudo actualizar/guardar"];

           }
    }
    //para eliminar el libro
    public function eliminarLibro(Request $request){
       $res =DB::table('libro')
        ->where('idlibro', $request->idlibro)
        ->update(['Estado_idEstado' => 4]);


        if($res){
            return "Se desactivo correctamente";
        }else{
            return "No se desactivo";
        }
    }
    public function activarLibro(Request $request){
        $res =DB::table('libro')
        ->where('idlibro', $request->idlibro)
        ->update(['Estado_idEstado' => 1]);


        if($res){
            return "Se Activo correctamente";
        }else{
            return "No se activo";
        }
    }



    public function get_links_libro($id_libro){
        $links = DB::SELECT("SELECT ll.id_link, ll.pag_ini, ll.pag_fin, ll.fecha_ini, ll.fecha_fin, l.weblibro ,ll.recurso_externo,
        i.nombreInstitucion
        FROM links_libros ll
        LEFT JOIN libro l ON l.idlibro = ll.id_libro
        LEFT JOIN institucion i ON ll.institucion_id = i.idInstitucion
        WHERE ll.id_libro = l.idlibro AND ll.id_libro = $id_libro
        ORDER BY ll.id_link DESC
         ");
        return $links;
    }
    public function guardar_link_libro(Request $request){
        if($request->recurso_externo){
            $validarSiExisteFecha = DB::SELECT("SELECT * FROM links_libros l
            WHERE  l.institucion_id = '$request->institucion_id'
            AND l.id_libro = '$request->id_libro'
            AND l.fecha_fin > '$request->fecha_fin'
            ");
            if(count($validarSiExisteFecha) >0){
                return ["status" => "0","message" => "Ya existe un link activo para esas fechas selecciono otra fecha"];
            }else{
                $libro = DB::insert("INSERT INTO `links_libros`(`id_libro`, `pag_ini`, `pag_fin`, `fecha_ini`, `fecha_fin`, `institucion_id`, `link`, `recurso_externo`) VALUES (?,?,?,?,?,?,?,?)",[$request->id_libro,$request->pag_ini,$request->pag_fin,$request->fecha_ini,$request->fecha_fin,$request->institucion_id,$request->link,'1']);
            }
        }else{
            $libro = DB::insert("INSERT INTO `links_libros`(`id_libro`, `pag_ini`, `pag_fin`, `fecha_ini`, `fecha_fin`) VALUES (?,?,?,?,?)",[$request->id_libro,$request->pag_ini,$request->pag_fin,$request->fecha_ini,$request->fecha_fin,]);
        }
        return $libro;
    }
}
