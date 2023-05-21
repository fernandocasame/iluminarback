<?php

namespace App\Http\Controllers;

use App\Models\Pedidos;
use App\Models\Beneficiarios;
use App\Models\Usuario;
use App\Models\PedidosAsesores;
use App\Models\User;
use DB;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PedidosController extends Controller
{
    public function index()
    {

    }

    public function store(Request $request)
    {   
        // se guardan todas las instituciones nuevas en la base de milton
        $this->guardar_institucines_base_milton();

        $asesor = DB::SELECT("SELECT iniciales FROM `usuario` WHERE `idusuario` = ?", [$request->id_asesor]);
        $institucion = DB::SELECT("SELECT codigo_institucion_milton FROM `institucion` WHERE `idInstitucion` = ?", [$request->institucion]);

        if( !$asesor[0]->iniciales ){
            return response()->json(['pedido' => '', 'error' => 'Faltan las iniciales del asesor']);
        }
        if( !$institucion[0]->codigo_institucion_milton ){
            return response()->json(['pedido' => '', 'error' => 'Falta el código de la institución, revise si el codigo de la ciudad es correcto.']);
        }
        
        if( $request->id_pedido ){
            $pedido = Pedidos::find($request->id_pedido);
        }else{
            $pedido = new Pedidos();
        }
        $pedido->tipo_venta = $request->tipo_venta;
        $pedido->tipo_venta_descr = $request->tipo_venta_descr;
        $pedido->fecha_envio = $request->fecha_envio;
        $pedido->fecha_entrega = $request->fecha_entrega;
        $pedido->id_institucion = $request->institucion;
        $pedido->id_periodo = $request->periodo;
        $pedido->descuento = $request->descuento;
        $pedido->anticipo = $request->anticipo;
        $pedido->id_asesor = $request->id_asesor; //asesor/vendedor
        $pedido->id_usuario_verif = 0; //$request->id_usuario_verif; //facturador se guarda al generar el pedido
        
        $pedido->save();
        return response()->json(['pedido' => $pedido, 'error' => ""]);
    }

    public function save_val_pedido(Request $request)
    {
        $val_pedido = DB::SELECT("SELECT * FROM `pedidos_val_area` WHERE `id_pedido` = ? AND `tipo_val` = ? AND `id_area` = ? AND `id_serie` = ?", [$request->id_pedido, $request->tipo_val, $request->id_area, $request->id_serie]);

        if( count($val_pedido) > 0 ){
            DB::UPDATE("UPDATE `pedidos_val_area` SET `valor` = ? WHERE `id` = ?", [$request->valor, $val_pedido[0]->id]);
        }else{
            DB::INSERT("INSERT INTO `pedidos_val_area`(`id_pedido`, `valor`, `id_area`, `tipo_val`, `id_serie`) VALUES (?,?,?,?,?)", [$request->id_pedido, $request->valor, $request->id_area, $request->tipo_val, $request->id_serie]);
        }
    }

    public function save_pvp_area_formato(Request $request)
    {
        $valida_pvp = DB::SELECT("SELECT * FROM `pedidos_formato` WHERE `id_periodo` = ? AND `id_serie` = ? AND `id_area` = ? AND `id_libro` = ?", [$request->id_periodo, $request->id_serie, $request->id_area, $request->id_libro]);

        if( count($valida_pvp) > 0 ){
            DB::UPDATE("UPDATE `pedidos_formato` SET `pvp`= ? WHERE `id` = ?", [$request->pvp, $valida_pvp[0]->id]);
        }else{
            DB::INSERT("INSERT INTO `pedidos_formato`(`id_serie`, `id_area`, `id_libro`, `id_periodo`, `pvp`) VALUES (?,?,?,?,?)", [$request->id_serie, $request->id_area, $request->id_libro, $request->id_periodo, $request->pvp]);
        }

        // para refrescar checks de niveles
        $pvp_data = DB::SELECT("SELECT * FROM `pedidos_formato` WHERE `id_libro` = ? AND `id_periodo` = ? AND `id_serie` = ? AND `id_area` = ?", [$request->id_libro, $request->id_periodo, $request->id_serie, $request->id_area]);

        return $pvp_data;
    }

    public function get_pedido($usuario, $periodo, $institucion)
    {
        $pedido = DB::SELECT("SELECT DISTINCT p.*, v.valor, v.tipo_val, i.idInstitucion, i.nombreInstitucion, c.nombre AS nombre_ciudad FROM pedidos p
        LEFT JOIN pedidos_val_area v ON p.id_pedido = v.id_pedido
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON p.ciudad = c.idciudad
        WHERE p.id_asesor = $usuario AND p.id_periodo = $periodo AND p.id_institucion = $institucion;");

        return $pedido;
    }

    public function anular_pedido_asesor($id_pedido, $id_usuario)
    {
        DB::SELECT("UPDATE `pedidos` SET `id_usuario_verif`=$id_usuario, `estado`=2 WHERE `id_pedido` = $id_pedido");
    }

    public function get_libros_plan_pedido($serie, $periodo){ // plan lector
        $libros_plan = DB::SELECT("SELECT l.*, p.pvp, p.id_periodo FROM libro l 
        INNER JOIN libros_series ls ON l.idLibro = ls.idLibro 
        LEFT JOIN pedidos_formato p ON l.idlibro = p.id_libro AND p.id_periodo = $periodo 
        WHERE ls.id_serie = 6  
        ORDER BY `p`.`pvp` DESC");

        return $libros_plan;
    }

    public function get_datos_pedido($pedido)
    {
        $pedido = DB::SELECT("SELECT DISTINCT p.*, u.nombres, u.apellidos, u.cedula, v.valor, v.tipo_val, i.idInstitucion, i.nombreInstitucion, c.nombre AS nombre_ciudad FROM pedidos p
		INNER JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN pedidos_val_area v ON p.id_pedido = v.id_pedido
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON p.ciudad = c.idciudad
        WHERE p.id_pedido = $pedido;");

        return $pedido;
    }

    public function get_val_pedido($pedido)
    {
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*, p.descuento, p.anticipo, p.comision FROM pedidos_val_area pv
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido 
        WHERE pv.id_pedido = $pedido GROUP BY pv.id;");

        return $val_pedido;
    }

    public function get_pvp_planes_periodo($periodo)
    {
        $pvp_planes = DB::SELECT("SELECT p.*, l.nombrelibro FROM pedidos_formato p
        INNER JOIN libro l ON p.id_libro = l.idlibro
        WHERE p.id_periodo = $periodo AND p.id_libro != 0");

        return $pvp_planes;
    }


    public function save_niveles_area_formato(Request $request)
    {
        $valida_nivel = DB::SELECT("SELECT * FROM `pedidos_formato` WHERE `id_periodo` = ? AND `id_serie` = ? AND `id_area` = ?", [$request->id_periodo, $request->id_serie, $request->id_area]);
        $check_valid = true;
        if($request->check == true){
            $check_valid = false;
        }
        if( count($valida_nivel) > 0 ){
            DB::UPDATE("UPDATE `pedidos_formato` SET `n".$request->index."`= ? WHERE `id` = ?", [$check_valid, $valida_nivel[0]->id]);
        }else{
            DB::INSERT("INSERT INTO `pedidos_formato`(`id_serie`, `id_area`, `id_periodo`, `n".$request->index."`) VALUES (?,?,?,?)", [ $request->id_serie, $request->id_area, $request->id_periodo, $check_valid]);
        }
    }


    public function get_pedidos_periodo($periodo)
    {
        $pedidos = DB::SELECT("SELECT p.*, CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor, i.nombreInstitucion, c.nombre AS nombre_ciudad, f.id_facturador
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON p.ciudad = c.idciudad 
        LEFT JOIN pedidos_asesores_facturador f ON u.idusuario = f.id_asesor
        WHERE p.id_periodo = $periodo AND p.estado != 2");

        return $pedidos;
    }

    public function get_pedidos_asesor($periodo, $asesor)
    {
        $pedidos = DB::SELECT("SELECT
        p.*, CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor, i.nombreInstitucion, c.nombre AS nombre_ciudad
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON p.ciudad = c.idciudad
        WHERE p.id_periodo = $periodo AND u.idusuario = $asesor");

        return $pedidos;
    }

    public function get_comentarios_pedido($pedido)
    {
        $comentarios = DB::SELECT("SELECT p.*, u.nombres, u.apellidos FROM pedidos_comentarios p, usuario u WHERE p.id_usuario = u.idusuario AND p.id_pedido = $pedido ORDER BY p.id DESC");

        return $comentarios;
    }

    public function get_beneficiarios_pedidos($pedido)
    {
        $beneficiarios = DB::SELECT("SELECT b.*, CONCAT(u.nombres, ' ', u.apellidos), u.cedula FROM pedidos_beneficiarios b
        INNER JOIN usuario u ON b.id_usuario = u.idusuario
        WHERE b.id_pedido = $pedido");

        return $beneficiarios;
    }

    public function guardar_comentario(Request $request)
    {

        DB::INSERT("INSERT INTO `pedidos_comentarios`(`id_pedido`, `comentario`, `id_usuario`) VALUES (?,?,?)", [$request->id_pedido,$request->comentario,$request->id_usuario]);

    }

    public function get_facturadores_pedido()
    {

        $facturadores = DB::SELECT("SELECT u.idusuario, CONCAT(u.nombres, ' ', u.apellidos) AS facturador FROM usuario u WHERE u.id_group = 22;");
        $data = array();
        foreach ($facturadores as $key => $value) {
            $asesores = DB::SELECT("SELECT u.idusuario, CONCAT(u.nombres, ' ', u.apellidos) AS asesor FROM pedidos_asesores_facturador f INNER JOIN usuario u ON f.id_asesor = u.idusuario WHERE f.id_facturador = ?;",[$value->idusuario]);

            $data[$key] = [
                'idusuario' => $value->idusuario,
                'facturador' => $value->facturador,
                'asesores' => $asesores
            ];
        }

        return $data;
    }

    public function get_asesores_factuador($id_facturador)
    {

        $asesores = DB::SELECT("SELECT u.idusuario, CONCAT(u.nombres, ' ', u.apellidos) AS asesor, IF(a.id, true, false) AS asignado FROM usuario u LEFT JOIN pedidos_asesores_facturador a ON u.idusuario = a.id_asesor AND a.id_facturador = $id_facturador WHERE u.id_group = 11 ORDER BY `asignado` DESC");

        return $asesores;

    }

    public function asignar_asesor_fact($id_factuador, $id_asesor, $asignado)
    {
        if( $asignado == 'true' ){
            DB::INSERT("INSERT INTO `pedidos_asesores_facturador`(`id_facturador`, `id_asesor`) VALUES ($id_factuador, $id_asesor)");
        }else{
            DB::DELETE("DELETE FROM `pedidos_asesores_facturador` WHERE `id_facturador` = $id_factuador AND `id_asesor` = $id_asesor");
        }

    }

    public function get_instituciones_asesor($cedula)
    {

        $instituciones = DB::SELECT("SELECT *, nombreInstitucion AS 'nombre_institucion', idInstitucion AS 'id_institucion' FROM `institucion` WHERE `vendedorInstitucion` = '$cedula'");
        return $instituciones;

    }

    public function get_responsables_pedidos()
    {
        $responsables = DB::SELECT("SELECT *, CONCAT(nombres,' ', apellidos, ' - ', cedula) AS 'nombres_responsable' FROM `usuario` WHERE `estado_idEstado` = 1 AND (`id_group` = 6 OR `id_group` = 10);");
        return $responsables;
    }

    public function guardar_total_pedido($id_pedido, $total_usd, $total_unid)
    {
        DB::UPDATE("UPDATE `pedidos` SET `total_venta` = $total_usd, `total_unidades` = $total_unid WHERE `id_pedido` = $id_pedido");
    }

    public function guardar_responsable_pedido(Request $request) //docente
    {   
        $datosValidados = $request->validate([
            'cedula' => 'required|max:15|unique:usuario',
            'nombres' => 'required',
            'apellidos' => 'required',
            'email' => 'required|email|unique:usuario',
            'institucion_idInstitucion' => 'required',
            'telefono' => 'required',
        ]);
        
        // SE GUARDA EN BASE DE MILTON, SI YA ESTA REGISTRADO NO GUARDARIA POR VALIDACION DE MILTON
        // try {
            $form_data = [
                'cli_ci'        => $request->cedula,
                'cli_apellidos' => $request->apellidos,
                'cli_nombres'   => $request->nombres,
                'cli_direccion' => $request->idcreadorusuario,
                'cli_telefono'  => $request->telefono,
                'cli_email'     => $request->email
            ];
            Http::post('http://186.46.24.108:9095/api/Cliente', $form_data);
        // } catch (\Throwable $th) {
        //     dump($th);
        // }

        // LUEGO SE GUARDA EN BASE PROLIPA
        $password = sha1(md5($request->cedula));
        $user = new User();
        $user->cedula = $request->cedula;
        $user->nombres = $request->nombres;
        $user->apellidos = $request->apellidos;
        $user->name_usuario = $request->email;
        $user->password = $password;
        $user->email = $request->email;
        $user->id_group = 6;
        $user->institucion_idInstitucion = $request->institucion_idInstitucion;
        $user->estado_idEstado = 1;
        $user->idcreadorusuario = $request->idcreadorusuario;
        $user->telefono = $request->telefono;

        $user->save();
        return $user;
    }

    public function save_beneficiarios_pedido(Request $request) //docente
    {   
        $docente = DB::SELECT("SELECT cedula FROM `usuario` WHERE `idusuario` = ?", [$request->id_responsable]);
        // generar cli_ins_codigo
        $asesor = DB::SELECT("SELECT iniciales FROM `usuario` WHERE `idusuario` = ?", [$request->id_asesor]);
        $institucion = DB::SELECT("SELECT codigo_institucion_milton FROM `institucion` WHERE `idInstitucion` = ?", [$request->institucion]);

        // SE VERIFICA QUE NO ESTE YA CREADO EL CLI INS CODIGO
        $verif_cli_ins_cod = DB::SELECT("SELECT * FROM `pedidos_asesor_institucion_docente` WHERE `id_asesor` = ? AND `id_institucion` = ? AND `id_docente` = ?", [$asesor[0]->iniciales, $institucion[0]->codigo_institucion_milton, $docente[0]->cedula]);
        
        if( count($verif_cli_ins_cod) == 0){
            // SE GENERA EL CLI INS CODIGO EN BASE DE MILTON
            $form_data = [
                'cli_ci'       => $docente[0]->cedula,
                'ins_codigo'   => intval($institucion[0]->codigo_institucion_milton),
                'ven_d_codigo' => $asesor[0]->iniciales,
            ];
            $cliente_escuela = Http::post('http://186.46.24.108:9095/api/ClienteEscuela', $form_data);
            $json_cliente_escuela = json_decode($cliente_escuela, true);
            
            if( $json_cliente_escuela ){
                // SE GUARDA EN BASE PROLIPA EL CLI INS CODIGO GENERADO
                DB::INSERT("INSERT INTO `pedidos_asesor_institucion_docente`(`cli_ins_codigo`, `id_asesor`, `id_institucion`, `id_docente`) VALUES (?,?,?,?)", [$json_cliente_escuela['cli_ins_codigo'], $asesor[0]->iniciales, $institucion[0]->codigo_institucion_milton, $docente[0]->cedula]);
            }else{
                return response()->json(['pedido' => '', 'error' => "No se pudo generar el cli_ins_codigo, comuníquese con soporte. Datos enviados, cedula: ".$docente[0]->cedula." ins_codigo: ". intval($institucion[0]->codigo_institucion_milton) . " vendedor: " . $asesor[0]->iniciales]);
            }
            
        }

        if( $request->id_beneficiario ){
            $beneficiario = Beneficiarios::find($request->id_beneficiario);
        }else{
            $beneficiario = new Beneficiarios();
        }
        
        $beneficiario->id_pedido = $request->id_pedido;
        $beneficiario->id_usuario = $request->id_responsable;
        $beneficiario->tipo_identificacion = $request->tipo_identificacion;
        $beneficiario->direccion = $request->direccion;
        $beneficiario->comision = $request->comision;
        $beneficiario->banco = $request->banco;
        $beneficiario->tipo_cuenta = $request->tipo_cuenta;
        $beneficiario->num_cuenta = $request->num_cuenta;
        $beneficiario->correo = $request->correo;
        $beneficiario->observacion = $request->observacion;
        $beneficiario->valor = $request->valor;

        $beneficiario->save();
        return $beneficiario;
    }

    public function eliminar_beneficiario_pedido($id_beneficiario){
        DB::SELECT("DELETE FROM `pedidos_beneficiarios` WHERE `id_beneficiario_pedido` = $id_beneficiario");
    }

    public function save_beneficiarios_db_milton(Request $request){
        $query = "SELECT b.*, u.nombres, u.apellidos, u.email, u.cedula, u.telefono FROM pedidos_beneficiarios b INNER JOIN usuario u ON b.id_usuario = u.idusuario WHERE b.id_pedido = " . $request->id_pedido;
        $beneficiarios = DB::SELECT($query);

        foreach ($beneficiarios as $key => $value) {
            $form_data = [
                "ben_nombre"      => $value->nombres,
                "ben_apellido"    => $value->apellidos,
                "ben_telefono"    => $value->telefono,
                "ben_cuenta"      => $value->num_cuenta,
                "ben_tipo_cuenta" => $value->tipo_cuenta,
                "ben_banco"       => $value->banco,
                "ben_contrato"    => $request->cod_contrato,
                "ben_comision"    => $value->comision,
                "ben_valor"       => $value->valor
            ];
            try {
                $benef = Http::post('http://186.46.24.108:9095/api/beneficiario', $form_data);
                return $benef;
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }

    public function cargar_codigos_vendedores(){
        $vendedores = Http::get('http://186.46.24.108:9095/api/vendedor');
        $json_vendedores = json_decode($vendedores, true);
        // return count($json_vendedores);

        foreach ($json_vendedores as $key => $value) {
            $cedula = str_replace(" ","",$value['ven_d_ci']);
            try {
                $query = "UPDATE `usuario` SET `iniciales`= '".$value['ven_d_codigo']."' WHERE `cedula` = '".$cedula."';";
                DB::SELECT($query);
                dump($query);
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }


    public function cargar_codigos_usuarios(){
        $usuarios = Http::get('http://186.46.24.108:9095/api/usuario');
        $json_usuarios = json_decode($usuarios, true);
        // return count($json_usuarios);

        foreach ($json_usuarios as $key => $value) {
            try {
                $query = "UPDATE `usuario` SET `cod_usuario`='".$value['usu_codigo']."' WHERE `cedula` = '".trim($value['usu_ci'])."';";
                DB::SELECT($query);
                dump($query);
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }

    public function cargar_codigo_institucion(){ 
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);

        $escuelas = Http::get('http://186.46.24.108:9095/api/Escuela');
        $json_escuelas = json_decode($escuelas, true);
        // return count($json_escuelas);

        foreach ($json_escuelas as $key => $value) {
            try {
                $query = "UPDATE `institucion` SET `codigo_institucion_milton`= '".$value['ins_codigo']."' WHERE `nombreInstitucion` LIKE '%".$value['ins_nombre']."%'";
                DB::SELECT($query);
                dump($query);
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }

    public function cargar_codigo_ciudad(){ /// base de milton
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);

        $ciudades = Http::get('http://186.46.24.108:9095/api/Ciudad');
        $json_ciudades = json_decode($ciudades, true);
        // return count($json_ciudades);

        foreach ($json_ciudades as $key => $value) {
            try {
                $query = "UPDATE `ciudad` SET `id_ciudad_milton`='".$value['ciu_codigo']."' WHERE `nombre` LIKE '%".$value['ciu_nombre']."%'";
                DB::SELECT($query);
                dump($query);
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }

    public function guardar_institucines_base_milton(){ /// instituciones de prolipa en base de milton DEBEN TENER EL ID DE CIUDAD CORRECTO
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);

        $instituciones = DB::SELECT("SELECT i.*, c.id_ciudad_milton FROM institucion i, ciudad c WHERE i.ciudad_id = c.idciudad AND i.codigo_institucion_milton IS NULL AND c.id_ciudad_milton IS NOT NULL;");
        foreach ($instituciones as $key => $value) {
            try {
                $form_data = [
                    'ciu_codigo'     => intval($value->id_ciudad_milton),
                    'tip_ins_codigo' => 2, // por defecto particulares
                    'cic_codigo'     => 1, // por defecto ??
                    'ins_nombre'     => $value->nombreInstitucion,
                    'ins_direccion'  => $value->direccionInstitucion,
                    'ins_telefono'   => $value->telefonoInstitucion,
                    'ins_ruc'        => '', // no tienen
                    'ins_sector'     => '', // no tienen
                ];
                $institucion = Http::post('http://186.46.24.108:9095/api/Escuela', $form_data);
                
                $json_institucion = json_decode($institucion, true);

                // guardar en base de prolipa tabla institucion
                if( count($json_institucion) > 0 ){
                    $query = "UPDATE `institucion` SET `codigo_institucion_milton`='".$json_institucion['ins_codigo']."' WHERE `idInstitucion` = ".$value->idInstitucion.";";
                    DB::SELECT($query);
                    dump($query);
                }

            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }


    public function generar_contrato_pedido($id_pedido, $usuario_fact){

        $pedido = DB::SELECT("SELECT p.*, pe.codigo_contrato, u.iniciales, i.codigo_institucion_milton FROM pedidos p, periodoescolar pe, usuario u, institucion i WHERE p.id_periodo = pe.idperiodoescolar AND p.id_asesor = u.idusuario AND p.id_institucion = i.idInstitucion AND `id_pedido` = $id_pedido");

        $usuario_verifica = DB::SELECT("SELECT * FROM `usuario` WHERE `idusuario` = ?", [$usuario_fact]);
        $docente = DB::SELECT("SELECT cedula FROM `usuario` WHERE `idusuario` = ?", [$pedido[0]->id_responsable]);
        $observacion = DB::SELECT("SELECT * FROM `pedidos_comentarios` WHERE `id_pedido` = $id_pedido ORDER BY `id` DESC;");
        $comentario = '';
        if( count($observacion) > 0 ){
            $comentario = $observacion[0]->comentario; 
        }

        if( $pedido[0]->id_pedido < 10 ){
            $format_id_pedido = '000000' . $pedido[0]->id_pedido;
        }
        if( $pedido[0]->id_pedido >= 10 && $pedido[0]->id_pedido < 1000 ){
            $format_id_pedido = '00000' . $pedido[0]->id_pedido;
        }
        if( $pedido[0]->id_pedido > 1000 ){
            $format_id_pedido = '0000' . $pedido[0]->id_pedido;
        }

        $codigo_ven = 'C-' . $pedido[0]->codigo_contrato . '-' . $format_id_pedido . '-' . $pedido[0]->iniciales;

        $fecha_formato = date_create($pedido[0]->updated_at);
        $fecha_formato = $fecha_formato->format(DateTime::ATOM);

        if( !$pedido[0]->codigo_contrato ){
            return response()->json(['json_contrato' => '', 'form_data' => '', 'error' => 'Falta el código del periodo']);
        }
        if( !$usuario_verifica[0]->cod_usuario ){
            return response()->json(['json_contrato' => '', 'form_data' => '', 'error' => 'Falta el código del usuario facturador']);
        }

        $cli_ins_cod = DB::SELECT("SELECT * FROM `pedidos_asesor_institucion_docente` WHERE `id_asesor` = ? AND `id_institucion` = ? AND `id_docente` = ?", [$pedido[0]->iniciales, $pedido[0]->codigo_institucion_milton, $docente[0]->cedula]);
        
        $form_data = [
            'veN_CODIGO' => $codigo_ven, //codigo formato milton
            'usU_CODIGO' => strval($usuario_verifica[0]->cod_usuario),
            'veN_D_CODIGO' => $pedido[0]->iniciales, // codigo del asesor
            'clI_INS_CODIGO' => floatval($cli_ins_cod[0]->cli_ins_codigo),
            'tiP_veN_CODIGO' => $pedido[0]->tipo_venta,
            'esT_veN_CODIGO' => 2, // por defecto
            'veN_OBSERVACION' => $comentario,
            'veN_VALOR' => $pedido[0]->total_venta,
            'veN_PAGADO' => 0.00, // por defecto
            'veN_ANTICIPO' => $pedido[0]->anticipo,
            'veN_DESCUENTO' => $pedido[0]->descuento,
            'veN_FECHA' => $fecha_formato,
            'veN_CONVERTIDO' => '', // por defecto
            'veN_TRANSPORTE' => 0.00, // por defecto
            'veN_ESTADO_TRANSPORTE' => false, // por defecto
            'veN_FIRMADO' => 'DS', // por defecto
            'veN_TEMPORADA' => $pedido[0]->id_periodo,
            'cueN_NUMERO' => $pedido[0]->num_cuenta
        ];

        // return $form_data;
        $contrato = Http::post('http://186.46.24.108:9095/api/Contrato', $form_data);

        $json_contrato = json_decode($contrato, true);
        
        $query = "UPDATE `pedidos` SET `contrato_generado` = '$codigo_ven', `id_usuario_verif` = $usuario_fact WHERE `id_pedido` = $id_pedido;";
        DB::SELECT($query);

        return response()->json(['json_contrato' => $json_contrato, 'form_data' => $form_data]);

    }



}
