<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\tipoJuegos;
use App\Models\J_juegos;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CuotasPorCobrar;
use App\Models\EstudianteMatriculado;
use App\Models\RepresentanteEconomico;
use App\Models\RepresentanteLegal;
use App\Models\Usuario;
use DB;
use GraphQL\Server\RequestError;
use Mail;

class AdminController extends Controller
{


    // public function datoEscuela(Request $request){
    //      set_time_limit(6000);
    //     ini_set('max_execution_time', 6000);
    //    $buscarUsuario = DB::SELECT("SELECT codl.idusuario

    //    FROM codigoslibros AS codl, usuario AS u, institucion AS its
    //    WHERE its.idInstitucion = 424
    //    AND its.idInstitucion = u.institucion_idInstitucion
    //    AND codl.idusuario = u.idusuario
    //    AND u.cedula <> '000000016'
    //     ORDER BY codl.idusuario DESC
    //    LIMIT 10
    //    ");



    //     $data  = [];
    //     $datos = [];
    //     $libros=[];
    //    foreach($buscarUsuario as $key => $item){
    //         $buscarLibros = DB::SELECT("SELECT  * FROM codigoslibros
    //         WHERE idusuario  = '$item->idusuario'
    //         ORDER BY updated_at DESC
    //         ");

    //         foreach($buscarLibros  as $l => $tr){

    //             $libros[$l] = [
    //                 "codigo" => $tr->codigo
    //             ];


    //             $data[$key] =[
    //                 "usuario" => $item->idusuario,
    //                 "libros" => $libros
    //             ];
    //         }


    //    }
    //    $datos = [
    //        "informacion" => $data
    //    ];
    //    return $datos;
    // }
    public function index()
    {
        $usuarios = DB::select("CALL `prolipa` ();");
        return $usuarios;
    }


    public function pruebaData(Request $request){
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);


        $dataActual = DB::SELECT("SELECT * FROM userback");


        $dataAnterior = DB::SELECT("SELECT m.* from mat_representante_legal m , usuario u
        WHERE m.c_estudiante = u.cedula
        AND u.institucion_idInstitucion = '1063'
        ");




        $cont =0;
        while ($cont < count($dataActual)) {


                DB::table('mat_representante_legal')
                ->where('c_estudiante', $dataActual[$cont]->cedula)
                ->update(['email_institucional' => $dataActual[$cont]->representante_email]);

            $cont=$cont+1;
        }

        return "se guardo";


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


    public function guardarData(Request $request){
        set_time_limit(6000);
        ini_set('max_execution_time', 6000);
        $data = $this->TraerData($request->institucion);


        $cont =0;


        while ($cont < count($data)) {



                    //Parar registrar la reserva de matricula
                    $fecha  = date('Y-m-d');
                    $matricula = new EstudianteMatriculado();
                    $matricula->id_estudiante = $data[$cont]->idusuario;
                    $matricula->id_periodo = $data[$cont]->periodo;
                    $matricula->fecha_matricula = $fecha;
                    $matricula->estado_matricula = "2";
                    $matricula->nivel  = $data[$cont]->orden;
                    $matricula->save();

                    //Para registrar las cuotas


                    // $cont =0;
                    // $couta = intval($data[$cont]->cuotas);

                    $fecha_configuracion = "$request->fecha_inicio";
                    // $fecha_configuracion = "2021-04-25";
                    // if($couta == 2){
                    $fecha1= $fecha_configuracion;
                    $fecha0= date("Y-m-d",strtotime($fecha_configuracion."- 1  month"));
                    $fecha2= date("Y-m-d",strtotime($fecha_configuracion."+ 1 month"));

                    $fecha3= date("Y-m-d",strtotime($fecha_configuracion."+ 2 month"));
                    $fecha4= date("Y-m-d",strtotime($fecha_configuracion."+ 3 month"));
                    $fecha5= date("Y-m-d",strtotime($fecha_configuracion."+ 4 month"));
                    $fecha6= date("Y-m-d",strtotime($fecha_configuracion."+ 5 month"));
                    $fecha7= date("Y-m-d",strtotime($fecha_configuracion."+ 6 month"));
                    $fecha8= date("Y-m-d",strtotime($fecha_configuracion."+ 7 month"));
                    $fecha9= date("Y-m-d",strtotime($fecha_configuracion."+ 8 month"));
                    $fecha10= date("Y-m-d",strtotime($fecha_configuracion."+ 9 month"));
                    $fecha11= date("Y-m-d",strtotime($fecha_configuracion."+ 10 month"));


                        // $dividirCuota = $request->valor * 10;
                        $dividirCuota = $data[$cont]->valor;
                        $decimalCuota = $dividirCuota;
                        // $decimalCuota = number_format($dividirCuota,2);

                              //COUTA 0 PARA VALORES PENDIENTES ANTERIORES
                            $cuotas0=new CuotasPorCobrar;
                            $cuotas0->id_matricula=$matricula->id_matricula;
                            $cuotas0->valor_cuota=0;
                            $cuotas0->valor_pendiente=0;
                            $cuotas0->fecha_a_pagar = $fecha0;
                            $cuotas0->num_cuota = 0;
                            $cuotas0->save();


                        //matricula
                            $cuotas=new CuotasPorCobrar;
                            $cuotas->id_matricula=$matricula->id_matricula;
                            $cuotas->valor_cuota=$data[$cont]->matricula;
                            $cuotas->valor_pendiente=$data[$cont]->matricula;
                            $cuotas->fecha_a_pagar = $fecha1;
                            $cuotas->num_cuota = 1;
                            $cuotas->save();
                        //pensiones
                            $cuotas1=new CuotasPorCobrar;
                            $cuotas1->id_matricula=$matricula->id_matricula;
                            $cuotas1->valor_cuota=$decimalCuota;
                            $cuotas1->valor_pendiente=$decimalCuota;
                            $cuotas1->fecha_a_pagar = $fecha2;
                            $cuotas1->num_cuota = 2;
                            $cuotas1->save();

                            $cuotas2=new CuotasPorCobrar;
                            $cuotas2->id_matricula=$matricula->id_matricula;
                            $cuotas2->valor_cuota=$decimalCuota;
                            $cuotas2->valor_pendiente=$decimalCuota;
                            $cuotas2->fecha_a_pagar = $fecha3;
                            $cuotas2->num_cuota = 3;
                            $cuotas2->save();

                            $cuotas3=new CuotasPorCobrar;
                            $cuotas3->id_matricula=$matricula->id_matricula;
                            $cuotas3->valor_cuota=$decimalCuota;
                            $cuotas3->valor_pendiente=$decimalCuota;
                            $cuotas3->fecha_a_pagar = $fecha4;
                            $cuotas3->num_cuota = 4;
                            $cuotas3->save();

                            $cuotas4=new CuotasPorCobrar;
                            $cuotas4->id_matricula=$matricula->id_matricula;
                            $cuotas4->valor_cuota=$decimalCuota;
                            $cuotas4->valor_pendiente=$decimalCuota;
                            $cuotas4->fecha_a_pagar = $fecha5;
                            $cuotas4->num_cuota = 5;
                            $cuotas4->save();

                            $cuotas5=new CuotasPorCobrar;
                            $cuotas5->id_matricula=$matricula->id_matricula;
                            $cuotas5->valor_cuota=$decimalCuota;
                            $cuotas5->valor_pendiente=$decimalCuota;
                            $cuotas5->fecha_a_pagar = $fecha6;
                            $cuotas5->num_cuota = 6;
                            $cuotas5->save();

                            $cuotas6=new CuotasPorCobrar;
                            $cuotas6->id_matricula=$matricula->id_matricula;
                            $cuotas6->valor_cuota=$decimalCuota;
                            $cuotas6->valor_pendiente=$decimalCuota;
                            $cuotas6->fecha_a_pagar = $fecha7;
                            $cuotas6->num_cuota = 7;
                            $cuotas6->save();

                            $cuotas7=new CuotasPorCobrar;
                            $cuotas7->id_matricula=$matricula->id_matricula;
                            $cuotas7->valor_cuota=$decimalCuota;
                            $cuotas7->valor_pendiente=$decimalCuota;
                            $cuotas7->fecha_a_pagar = $fecha8;
                            $cuotas7->num_cuota = 8;
                            $cuotas7->save();

                            $cuotas8=new CuotasPorCobrar;
                            $cuotas8->id_matricula=$matricula->id_matricula;
                            $cuotas8->valor_cuota=$decimalCuota;
                            $cuotas8->valor_pendiente=$decimalCuota;
                            $cuotas8->fecha_a_pagar = $fecha9;
                            $cuotas8->num_cuota = 9;
                            $cuotas8->save();

                            $cuotas9=new CuotasPorCobrar;
                            $cuotas9->id_matricula=$matricula->id_matricula;
                            $cuotas9->valor_cuota=$decimalCuota;
                            $cuotas9->valor_pendiente=$decimalCuota;
                            $cuotas9->fecha_a_pagar = $fecha10;
                            $cuotas9->num_cuota = 10;
                            $cuotas9->save();

                            $cuotas10=new CuotasPorCobrar;
                            $cuotas10->id_matricula=$matricula->id_matricula;
                            $cuotas10->valor_cuota=$decimalCuota;
                            $cuotas10->valor_pendiente=$decimalCuota;
                            $cuotas10->fecha_a_pagar = $fecha11;
                            $cuotas10->num_cuota = 11;
                            $cuotas10->save();


                    $cont=$cont+1;
        }

        return ["status" => "1" ,"message" => "Se actualizo correctamente"];

    }

    public function TraerData($institucion){
        $data = DB::SELECT("SELECT DISTINCT  ni.valor, ni.matricula,
        u.idusuario, u.nombres, u.apellidos, u.cedula, u.email,u.update_datos, u.curso ,u.institucion_idInstitucion,
             i.reporte, n.nombrenivel, n.orden, per.fecha_inicio_pension,



        (SELECT periodoescolar_idperiodoescolar AS periodo FROM periodoescolar_has_institucion
        WHERE id = (SELECT MAX(phi.id) AS periodo_maximo FROM periodoescolar_has_institucion phi, institucion i
         WHERE phi.institucion_idInstitucion = i.idInstitucion
        AND i.idInstitucion = '$institucion')) as periodo

        FROM usuario u
        LEFT JOIN institucion i ON  u.institucion_idInstitucion = i.idInstitucion
        LEFT JOIN mat_representante_economico rc ON  u.cedula = rc.c_estudiante
        LEFT JOIN mat_representante_legal rl ON  u.cedula = rl.c_estudiante
        -- LEFT JOIN estado_cuenta_colegio ec ON  u.cedula = ec.cedula
        LEFT JOIN nivel n ON u.curso = n.orden
        LEFT JOIN periodoescolar_has_institucion per ON u.institucion_idInstitucion = per.institucion_idInstitucion
        LEFT JOIN mat_niveles_institucion ni ON u.curso = ni.nivel_id


       WHERE  ni.institucion_id = '$institucion'
        AND u.institucion_idInstitucion = '$institucion'
        AND u.id_group = '14'
        AND ni.periodo_id = '12'
        ORDER BY u.apellidos ASC

        -- LIMIT 5
        ");


        return $data;
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
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function show(Admin $admin)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function edit(Admin $admin)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Admin $admin)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Admin  $admin
     * @return \Illuminate\Http\Response
     */
    public function destroy(Admin $admin)
    {
        //
    }

    // Consultas para administrador
    public function cant_user(){
        $cantidad = DB::SELECT("SELECT id_group, COUNT(id_group) as cantidad FROM usuario WHERE estado_idEstado =1  GROUP BY id_group");
        return $cantidad;
    }
    public function cant_cursos(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM curso  GROUP BY estado");
        return $cantidad;
    }
    public function cant_codigos(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM codigoslibros WHERE idusuario > 0");
        return $cantidad;
    }
    public function cant_codigostotal(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM codigoslibros");
        return $cantidad;
    }
    public function cant_evaluaciones(){
        $cantidad = DB::SELECT("SELECT estado, COUNT(estado) as cantidad FROM evaluaciones  GROUP BY estado");
        return $cantidad;
    }
    public function cant_preguntas(){
        $cantidad = DB::SELECT("SELECT id_tipo_pregunta, COUNT(id_tipo_pregunta) as cantidad FROM preguntas  GROUP BY id_tipo_pregunta");
        return $cantidad;
    }
    public function cant_multimedia(){
        $cantidad = DB::SELECT("SELECT tipo, COUNT(tipo) as cantidad FROM actividades_animaciones  GROUP BY tipo");
        return $cantidad;
    }
    public function cant_juegos(){
        // $cantidad = DB::SELECT("SELECT jj.id_tipo_juego, COUNT(jj.id_tipo_juego) as cantidad , jt.nombre_tipo_juego FROM j_juegos jj INNER JOIN j_tipos_juegos jt ON jj.id_tipo_juego = jt.id_tipo_juego GROUP BY jt.id_tipo_juego GROUP BY jj.id_tipo_juego");

        $cantidad = DB::table('j_juegos')
        ->join('j_tipos_juegos', 'j_tipos_juegos.id_tipo_juego','=','j_juegos.id_tipo_juego')
        ->select('j_tipos_juegos.nombre_tipo_juego', DB::raw('count(*) as cantidad'))
        ->groupBy('j_tipos_juegos.nombre_tipo_juego')
        ->get();
        return $cantidad;
    }
    public function cant_seminarios(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM seminario  WHERE estado=1");
        return $cantidad;
    }
    public function cant_encuestas(){
        $cantidad = DB::SELECT("SELECT COUNT(*) as cantidad FROM encuestas_certificados");
        return $cantidad;
    }
    public function cant_institucion(){
        $cantidad = DB::SELECT("SELECT DISTINCT COUNT(*) FROM institucion i, periodoescolar p, periodoescolar_has_institucion pi WHERE  i.idInstitucion = pi.institucion_idInstitucion AND pi.periodoescolar_idperiodoescolar = p.idperiodoescolar AND p.estado = 1 GROUP BY i.region_idregion");
        return $cantidad;
    }

    public function get_periodos_activos(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p WHERE p.estado = '1' ORDER BY p.idperiodoescolar DESC;");
        return $periodos;
    }

    public function get_asesores(){
        $asesores = DB::SELECT("SELECT `idusuario`, CONCAT(`nombres`,' ',`apellidos`) AS nombres, `cedula` FROM `usuario` WHERE `estado_idEstado` = '1' AND `id_group` = '5';");
        return $asesores;
    }

    public function reporte_asesores(){

        $fecha_fin    = date("Y-m-d");
        $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 7 days"));

        $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");

        $email = 'mcalderonmediavilla@hotmail.com';
        $emailCC = 'reyesjorge10@gmail.com';
        $reporte = 'Reporte asesores';

        $envio = Mail::send('plantilla.reporte_asesores',
            [
                'fecha_fin'    => $fecha_fin,
                'fecha_inicio' => $fecha_inicio,
                'agendas'      => $agendas,
            ],
            function ($message) use ($email, $emailCC, $reporte) {
                $message->from('reportesgerencia@prolipadigital.com.ec', $reporte);
                $message->to($email)->bcc($emailCC)->subject('Agenda de asesores');
            }
        );
    }

    // public function reporte_asesores_view($periodo, $fecha_inicio, $fecha_fin){

    //     if( $periodo != 'null' ){
    //         $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND p.idperiodoescolar = $periodo ORDER BY u.cedula;");
    //     }else{
    //         if( $fecha_inicio != 'null' ){
    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }else{

    //             $fecha_fin    = date("Y-m-d");
    //             $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

    //             $agendas = DB::SELECT("SELECT a.id, a.title, a.label, a.classes, a.startDate, a.endDate, a.hora_inicio, a.hora_fin, a.url, a.nombre_institucion_temporal, a.opciones, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM agenda_usuario a, usuario u, periodoescolar p WHERE a.id_usuario = u.idusuario AND a.periodo_id = p.idperiodoescolar AND u.id_group != 6 AND p.estado = '1' AND a.startDate BETWEEN '$fecha_inicio' AND '$fecha_fin' ORDER BY u.cedula;");
    //         }
    //     }

    //     return $agendas;

    // }


    public function reporte_asesores_view($fecha_inicio, $fecha_fin,$periodo){

        // if( $periodo != 'null' ){
        //     $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.periodo_id = $periodo order BY u.nombres");
        // }else{
        //     if( $fecha_inicio != 'null' ){
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }else{
        //         $fecha_fin    = date("Y-m-d");
        //         $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));
        //         $agendas = DB::SELECT("SELECT s.id, s.num_visita, s.tipo_seguimiento, s.fecha_genera_visita, s.fecha_que_visita, s.observacion, s.estado, i.nombreInstitucion, u.nombres, u.apellidos, u.cedula, u.email, p.periodoescolar FROM seguimiento_cliente s, usuario u, institucion i, periodoescolar p WHERE s.asesor_id = u.idusuario AND s.institucion_id = i.idInstitucion AND s.periodo_id = p.idperiodoescolar AND s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin' order BY u.nombres");
        //     }
        // }
        if( $periodo != 'null' ){
            $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucionxPeriodo`('$periodo');
                ");
            $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporalxPeriodo('$periodo')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                 ];
        }else{
            if($fecha_inicio != 'null' ){
                $visitas = DB::SELECT("CALL `pr_reporteVisitasInstitucion`('$fecha_inicio','$fecha_fin');
                ");
                $visitasITemporal = DB::SELECT("CALL pr_reporteVisitasInstitucionTemporal('$fecha_inicio', '$fecha_fin')");
                return [
                    "visitasInstitucion" => $visitas,
                    "visitasInstitucionTemporal" => $visitasITemporal
                ];
            }
        }

        return $agendas;

    }

    public function get_estadisticas_asesor_inst($periodo, $fecha_inicio, $fecha_fin){

        if( $periodo != 'null' ){
            $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
            FROM seguimiento_cliente s
            INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
            WHERE s.periodo_id = $periodo AND s.fecha_genera_visita
            GROUP BY i.idInstitucion;");
        }else{
            if( $fecha_inicio != 'null' ){
                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }else{

                $fecha_fin    = date("Y-m-d");
                $fecha_inicio = date("Y-m-d",strtotime($fecha_fin."- 30 days"));

                $visitas = DB::SELECT("SELECT i.nombreInstitucion, COUNT(i.idInstitucion) AS cant_visitas
                FROM seguimiento_cliente s
                INNER JOIN institucion i ON s.institucion_id = i.idInstitucion
                WHERE s.fecha_genera_visita BETWEEN '$fecha_inicio' AND '$fecha_fin'
                GROUP BY i.idInstitucion;");
            }
        }

        return $visitas;

    }


}
