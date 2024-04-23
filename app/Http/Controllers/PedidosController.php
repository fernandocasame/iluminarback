<?php

namespace App\Http\Controllers;

use App\Models\Pedidos;
use App\Models\Beneficiarios;
use App\Models\Usuario;
use App\Models\PedidosAsesores;
use App\Models\User;
use App\Models\Temporada;
use App\Models\PedidosSecuencia;
use App\Models\PedidosHistorico;
use App\Models\Institucion;
use App\Models\Models\Pedidos\PedidosDocumentosLiq;
use App\Models\PedidosClienteInstitucion;
use App\Models\PedidosAnticiposSolicitados;
use App\Models\PedidoAlcance;
use App\Models\PedidoAlcanceHistorico;
use App\Models\PedidoAnticipoAprobadoHistorico;
use App\Models\PedidoConvenio;
use App\Models\PedidoConvenioDetalle;
use App\Models\PedidoDocumentoAnterior;
use App\Models\PedidoDocumentoDocente;
use App\Models\PedidoHistoricoActas;
use App\Models\PedidosGuiasBodega;
use App\Models\PedidoGuiaEntrega;
use App\Models\PedidoGuiaEntregaDetalle;
use App\Models\PedidoHistoricoCambios;
use App\Models\Periodo;
use App\Models\Verificacion;
use App\Repositories\PedidosPagosRepository;
use App\Repositories\pedidos\ConvenioRepository;
use App\Traits\Pedidos\TraitPagosGeneral;
use App\Traits\Pedidos\TraitPedidosGeneral;
use App\Traits\Verificacion\TraitVerificacionGeneral;
use Carbon\Carbon;
use DB;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use stdClass;
class PedidosController extends Controller
{
    use TraitPedidosGeneral;
    use TraitVerificacionGeneral;
    use TraitPagosGeneral;
    private $pagoRepository;
    private $convenioRepository;
    public function __construct(PedidosPagosRepository $pagoRepository, ConvenioRepository $convenioRepository)
    {
     $this->pagoRepository          = $pagoRepository;
     $this->convenioRepository      = $convenioRepository;
    }
    //api:get/pedidos
    public function index(Request $request)
    {
        if($request->homeAdmin){
            return $this->homeAdmin();
        }

    }
    //api:get/pedidos{id}
    public function show($idpedido){
       return $this->getPedido(0,$idpedido);
    }
    public function homeAdmin(){
        $periodos = DB::SELECT("SELECT * FROM periodoescolar p
        WHERE p.pedido_facturacion = '1'
        ");
         $facturadores = DB::SELECT("SELECT u.idusuario, CONCAT(u.nombres, ' ', u.apellidos) AS facturador FROM usuario u WHERE u.id_group = 22;");
        $datos = [];
        foreach($periodos as $key => $item){
            $contratos = DB::SELECT("SELECT * FROM pedidos p1
            WHERE p1.estado = '1'
            AND p1.contrato_generado <> ''
            AND p1.contrato_generado IS NOT NULL
            AND p1.id_periodo = '$item->idperiodoescolar'
            ");
            $solicitudes = DB::SELECT("SELECT * FROM pedidos p1
            WHERE p1.estado = '1'
            AND
            (
                p1.contrato_generado = '' OR
                p1.contrato_generado IS NULL
            )
            AND p1.id_periodo = '$item->idperiodoescolar'
            ");
            $anuladas = DB::SELECT("SELECT * FROM pedidos p1
             WHERE p1.estado = '2'
             AND p1.id_periodo = '$item->idperiodoescolar'
            ");
            $datos[$key] =[
                "idperiodoescolar"  => $item->idperiodoescolar,
                "periodo"           => $item->periodoescolar,
                "contratos"         => count($contratos),
                "solicitudes"       => count($solicitudes),
                "anuladas"          => count($anuladas),
                "facturadores"      => count($facturadores),
            ];
        }
        return $datos;
    }

    public function contadorAniosConvenio($idConvenio){
        $estadoConvenio = PedidosDocumentosLiq::Where('tipo_pago_id','=','4')->where('pedidos_convenios_id','=',$idConvenio)->where('estado','1')->get();
        return $estadoConvenio;
    }
    public function store(Request $request)
    {
        //limpiar cache
        Cache::flush();
        if($request->informacionPedido){
            return $this->informacionPedido($request);
        }
        //validar un pedido de institucion por periodo solo para cuando va a guardar no el editar
        if( $request->id_pedido ){
        }else{
            $validate = DB::SELECT("SELECT * FROM pedidos p
            WHERE p.id_institucion =  '$request->institucion'
            AND p.id_periodo = '$request->periodo'
            AND ( p.estado = '1' OR p.estado = '0')
            ");
        }
        if(empty($validate)){
            // se guardan todas las instituciones nuevas en la base de milton
            //$this->guardar_institucines_base_milton();
            $asesor = DB::SELECT("SELECT iniciales,cli_ins_codigo FROM `usuario` WHERE `idusuario` = ?", [$request->id_asesor]);
            $institucion = DB::SELECT("SELECT codigo_institucion_milton FROM `institucion` WHERE `idInstitucion` = ?", [$request->institucion]);
            if( !$asesor[0]->iniciales ){
                return response()->json(['pedido' => '', 'error' => 'Faltan las iniciales del asesor']);
            }
            if( !$asesor[0]->cli_ins_codigo ){
                return response()->json(['pedido' => '', 'error' => 'Faltan el cli_ins_codigo para pedir guias en el asesor']);
            }
            if( !$institucion[0]->codigo_institucion_milton ){
                return response()->json(['pedido' => '', 'error' => 'Falta el código de la institución, revise si el codigo de la ciudad es correcto.']);
            }
            if( $request->id_pedido ){
                //validar que si el pedido ya se entrego no se pueda modificar o crear nuevos valores
                $validate = DB::SELECT("SELECT * FROM pedidos p
                WHERE p.id_pedido = '$request->id_pedido'
                AND (p.estado_entrega = '1' OR p.estado_entrega = '2')
                ");
                if(count($validate) > 0){
                    return ["status" => "0", "message" => "El pedido ya fue entregado por bodega no se puede modificar"];
                }
                $pedido = Pedidos::find($request->id_pedido);
                //Facturacion aprueba el anticipo
                if($request->ifagregado_anticipo_aprobado == 1){
                    $this->aprobarAnticipo($request->id_pedido);
                }
                //convenio
                if($request->convenio_anios == "null" || $request->convenio_anios == null || $request->convenio_anios == 0){
                    $pedido->convenio_anios     = null;
                }else{
                    $pedido->convenio_anios     = $request->convenio_anios;
                }
            }else{
                $pedido = new Pedidos();
                //si existe convenio de la institucion
                $getConvenio = $this->getConvenioInstitucion($request->institucion);
                if(!empty($getConvenio)){
                    $idConvenio     = $getConvenio[0]->id;
                    $aniosConvenio  = $getConvenio[0]->convenio_anios;
                    //valido que si el convenio ya tiene los anios cumplidos lo cierro
                    $estadoConvenio = $this->contadorAniosConvenio($idConvenio);
                    $contadorAnios = sizeOf($estadoConvenio);
                    //si ya paso los años finalizo el convenio
                    if($contadorAnios >= $aniosConvenio){
                        $convenio = PedidoConvenio::findOrFail($idConvenio);
                        $convenio->estado = 0;
                        $convenio->save();
                    }
                    //si aun falta anios agrego convenio a este pedido
                    else{
                        //update a pedido
                        $pedido->convenio_anios         = $aniosConvenio;
                        $pedido->pedidos_convenios_id   = $idConvenio;
                    }
                }
            }
            $pedido->tipo_venta             = $request->tipo_venta;
            $pedido->tipo_venta_descr       = $request->tipo_venta_descr;
            $pedido->fecha_envio            = $request->fecha_envio;
            $pedido->fecha_entrega          = $request->fecha_entrega;
            $pedido->id_institucion         = $request->institucion;
            $pedido->id_periodo             = $request->periodo;
            $pedido->descuento              = $request->descuento;
            if($request->anticipo == "null" || $request->anticipo == null){
                $pedido->anticipo           = null;
            }else{
                $pedido->anticipo           = $request->anticipo;
            }
            $pedido->id_asesor              = $request->id_asesor; //asesor/vendedor
            //si se generar el pedido apartir de un pedido anulado pongo el responsable anterior
            if($request->generarNuevo == 'yes'){
                $pedido->id_responsable         = $request->id_responsable;
            }
           //$request->id_usuario_verif; //facturador se guarda al generar el pedido
            if($request->observacion == "null" || $request->observacion == null){
                $pedido->observacion         = null;
            }else{
                $pedido->observacion        = $request->observacion;
            }
            $pedido->ifanticipo             = $request->ifanticipo;
            $pedido->porcentaje_anticipo    = $request->porcentaje_anticipo;
            $pedido->anticipo_aprobado      = $request->anticipo_aprobado;
            $pedido->pendiente_liquidar     = $request->pendiente_liquidar;
            $pedido->ifagregado_anticipo_aprobado = $request->ifagregado_anticipo_aprobado;
            $pedido->deuda                  = $request->deuda;
            if($request->periodo_deuda == "" || $request->periodo_deuda == "null" | $request->periodo_deuda == null){
                $pedido->periodo_deuda          = null;
            }else{
                $pedido->periodo_deuda          = $request->periodo_deuda;
            }
            $pedido->save();
            if($request->generarNuevo == 'yes'){
            }else{
                //ACTUALIZAR INSTITUCION
                $uinstitucion = Institucion::findOrFail($request->institucion);
                $uinstitucion->telefonoInstitucion         = $request->telefonoInstitucion;
                $uinstitucion->direccionInstitucion        = $request->direccionInstitucion;
                $uinstitucion->ruc                         = $request->ruc;
                $uinstitucion->nivel                       = $request->nivel;
                $uinstitucion->tipo_descripcion            = $request->tipo_descripcion;
                $uinstitucion->save();
            }
            //SI FACTURADOR O ADMIN MODIFICAN EL ANTICIPO APROBADO LO GUARDO EN UN HISTORICO
            if($request->modificarAnticipoAprobado == 1){
                $this->saveHistoricoAnticipos($request);
            }
            if($request->generarNuevo == 'yes'){
                //Si se genera un pedido apartir de un  pedido anulado
                $this->changeBeneficiariosLibros($request->pedidoAnterior,$pedido->id_pedido);
                //CAMBIAR PEDIDO  EN PROCESO A PEDIDO CREADO-> actualizar la fecha de creacion de pedido
                $fechaActual = date('Y-m-d H:i:s');
                DB::table('pedidos')
                ->where('id_pedido', $pedido->id_pedido)
                ->update([
                    'estado' => 1,
                    'fecha_creacion_pedido' => $fechaActual
                ]);
            }
            return response()->json(['pedido' => $pedido, 'error' => ""]);
        }else{
            return ["status" => "0", "message" => "Ya ha sido generado un pedido con esa institución en este período"];
        }
    }
    public function informacionPedido($request){
        $fechaActual = date('Y-m-d H:i:s');
         //validar un pedido de institucion por periodo solo para cuando va a guardar no el editar
         if( $request->id_pedido > 0 ){
        }else{
            $validate = DB::SELECT("SELECT * FROM pedidos p
            WHERE p.id_institucion =  '$request->institucion'
            AND p.id_periodo = '$request->periodo'
            AND ( p.estado = '1' OR p.estado = '0')
            ");
        }
        if(empty($validate)){
            if( $request->id_pedido ){
                $pedido = Pedidos::find($request->id_pedido);
            }else{
                $pedido = new Pedidos();
                $pedido->fecha_creacion_pedido  = $fechaActual;
                //si existe convenio de la institucion
                $getConvenio = $this->getConvenioInstitucion($request->institucion);
                if(!empty($getConvenio)){
                    $idConvenio     = $getConvenio[0]->id;
                    $aniosConvenio  = $getConvenio[0]->convenio_anios;
                    //valido que si el convenio ya tiene los anios cumplidos lo cierro
                    $estadoConvenio = $this->contadorAniosConvenio($idConvenio);
                    $contadorAnios = sizeOf($estadoConvenio);
                    //si ya paso los años finalizo el convenio
                    if($contadorAnios >= $aniosConvenio){
                        $convenio = PedidoConvenio::findOrFail($idConvenio);
                        $convenio->estado = 0;
                        $convenio->save();
                    }
                    //si aun falta anios agrego convenio a este pedido
                    else{
                        //update a pedido
                        $pedido->convenio_anios         = $aniosConvenio;
                        $pedido->pedidos_convenios_id   = $idConvenio;
                    }
                }
            }
            $pedido->tipo_venta             = $request->tipo_venta;
            $pedido->tipo_venta_descr       = $request->tipo_venta_descr;
            $pedido->fecha_envio            = $request->fecha_envio;
            $pedido->fecha_entrega          = $request->fecha_entrega;
            $pedido->id_institucion         = $request->institucion;
            $pedido->id_periodo             = $request->periodo;
            $pedido->descuento              = $request->descuento;
            $pedido->id_asesor              = $request->id_asesor; //asesor/vendedor
            $pedido->facturacion_vee        = $request->facturacion_vee;
            $pedido->observacion            = $request->observacion == null || $request->observacion == "null" ? null : $request->observacion;
            $pedido->save();
            //ACTUALIZAR INSTITUCION
            $uinstitucion = Institucion::findOrFail($request->institucion);
            $uinstitucion->telefonoInstitucion         = $request->telefonoInstitucion;
            $uinstitucion->direccionInstitucion        = $request->direccionInstitucion;
            $uinstitucion->ruc                         = $request->ruc;
            $uinstitucion->nivel                       = $request->nivel;
            $uinstitucion->tipo_descripcion            = $request->tipo_descripcion;
            $uinstitucion->save();
            return response()->json(['pedido' => $pedido, 'error' => ""]);
        }else{
            return ["status" => "0", "message" => "Ya ha sido generado un pedido con esa institución en este período"];
        }
    }
    //save historico anticipos aprobados;
    public function saveHistoricoAnticipos($request){
        $historico = new PedidoAnticipoAprobadoHistorico();
        $historico->anticipo_aprobado   = $request->anticipo_aprobado;
        $historico->id_pedido           = $request->id_pedido;
        $historico->user_created        = $request->user_created;
        $historico->id_group            = $request->id_group;
        $historico->save();
    }
    //Api para guardar el anticipo despues de generar el contrato
    //api:post/guardarAnticipoAprobadoContrato
    public function guardarAnticipoAprobadoContrato(Request $request){

        try{
            $contrato = $request->contrato;
            $aprobado = $request->anticipo_aprobado;
            $pedido = Pedidos::findOrFail($request->id_pedido);
            $pedido->anticipo_aprobado              = $aprobado;
            $pedido->ifagregado_anticipo_aprobado   = 1;
            $pedido->save();
            //actualizar anticipo facturacion
            $form_data = [
                'venAnticipo'   => floatval($aprobado),
            ];
            //guardar en historico
            $this->saveHistoricoAnticipos($request);
            $dato = Http::post("http://186.4.218.168:9095/api/f_Venta/ActualizarVenanticipo?venCodigo=".$contrato,$form_data);
            $prueba_get = json_decode($dato, true);
            if($pedido){
                return ["status" => "1", "message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0", "message" => "No se pudo guardar"];
            }
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }

    }
    public function aprobarAnticipo($id_pedido){
        //validatar que el pedidod en el historico este en estado para seguir al siguiente paso
        //que es aprobado por el facturador o gerente
        $fechaActual = date('Y-m-d H:i:s');
        $validate = DB::SELECT("SELECT * FROM pedidos_historico
        WHERE id_pedido = '$id_pedido'
        AND (estado = '0' or estado = '1')
        ");
        if(count($validate) > 0){
            DB::table('pedidos_historico')
            ->where('id_pedido', $id_pedido)
            ->update([
                'estado' => 1,
                'fecha_aprobacion_anticipo_gerencia' => $fechaActual
            ]);
        }

    }
    public function RechazarAnticipo($id_pedido){
        //validatar que el pedidod en el historico este en estado para seguir al siguiente paso
        //que es aprobado por el facturador o gerente
        $fechaActual = date('Y-m-d H:i:s');
        $validate = DB::SELECT("SELECT * FROM pedidos_historico
        WHERE id_pedido = '$id_pedido'
        AND (estado = '0' OR estado = '1')
        ");
        if(count($validate) > 0){
            DB::table('pedidos_historico')
            ->where('id_pedido', $id_pedido)
            ->update([
                'estado' => 3,
                'fecha_rechazo_gerencia' => $fechaActual
            ]);
        }

    }
    public function changeBeneficiariosLibros($pedidoAnterior,$pedidoNuevo){
        //actualizar beneficiarios
        DB::table('pedidos_beneficiarios')
        ->where('id_pedido', $pedidoAnterior)
        ->update(['id_pedido' => $pedidoNuevo]);
        //actualizar libros
        DB::table('pedidos_val_area')
        ->where('id_pedido', $pedidoAnterior)
        ->where('alcance', '=','0')
        ->update(['id_pedido' => $pedidoNuevo]);
    }
    public function save_val_pedido(Request $request)
    {
        $fechaActual = date('Y-m-d H:i:s');
        //validar que si el pedido ya se entrego no se pueda modificar o crear nuevos valores
        $validate = DB::SELECT("SELECT * FROM pedidos p
        WHERE p.id_pedido = '$request->id_pedido'
        AND (p.estado_entrega = '1' OR p.estado_entrega = '2')
        ");
        if(empty($validate)){
            //validar que el pedido no este anulado
            $validate2 = DB::SELECT("SELECT * FROM pedidos p
            WHERE p.id_pedido = '$request->id_pedido'
            AND p.estado = '2'
            ");
            if(count($validate2)){
                return ["status" => "0", "message" => "No se puede modificar un pedido anulado"];
            }
            $val_pedido = DB::SELECT("SELECT * FROM `pedidos_val_area`
            WHERE `id_pedido` = ?
            AND `tipo_val` = ?
            AND `id_area` = ?
            AND `id_serie` = ?
            AND `alcance`  = 0
            ",
            [$request->id_pedido, $request->tipo_val, $request->id_area, $request->id_serie]);
            if( count($val_pedido) > 0 ){
                $valor          = $request->valor;
                $valorReserva   = 0;
                $totalAReservar = 0;
                if($request->valor == "" || $request->valor == null || $request->valor == 0){
                    DB::DELETE("DELETE FROM pedidos_val_area
                     where id_pedido ='$request->id_pedido'
                     AND tipo_val = '$request->tipo_val'
                     AND id_area = '$request->id_area'
                     AND `alcance`  = 0
                    ");
                    return;
                }
                ///PROCESO DE VALIDACION EN GUIAS SI HAY STOCK
                if($request->guias){
                    try{
                        //VALIDACION CONTANDO LOS HAN RESERVADO LAS GUIAS EXCEPTO DEL PEDIDO MAS LUEGO SE VA A SUMAR
                        $getReserva = $this->getReservaCodigosStock($request);
                        if(count($getReserva) > 0){
                            foreach($getReserva as $key2 => $item2){
                                $valorReserva = $valorReserva + $item2->valor;
                            }
                        }
                        $totalAReservar = $valorReserva + $valor;
                        ///PROCESO
                        $query = [];
                        //plan lector
                        if($request->id_serie == 6) { $query = $this->pedidoxLibroPlanLector($request); }
                        else                        { $query = $this->pedidoxLibro($request); }
                        if(empty($query)){
                            return ["status" => "0", "message" => "No existe el libro"];
                        }
                       $codigoCodigo = $query[0]->codigo_liquidacion;
                       $nombreLibro  = $query[0]->nombre;
                       if($codigoCodigo == null || $codigoCodigo == "null" || $codigoCodigo == ""){
                            return ["status" => "0", "message" => "No existe codigo de liquidacion para el libro"];
                       }
                       $cantidad       = $request->valor;
                       $codigoFact     = "G".$codigoCodigo;
                       //get stock
                       $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigoFact);
                       $json_stock     = json_decode($getStock, true);
                       $stockAnterior  = $json_stock["producto"][0]["proStock"];
                        // $stockAnterior = 16;
                       //post stock
                       //MENOS 1 ES PORQUE es el minimo que se puede pedir
                       $nuevoStock     = $stockAnterior - $totalAReservar;
                       $stockDisponible = $stockAnterior - $valorReserva -1;
                       if($nuevoStock < 1){
                        return ["status" => "0", "message" => "No existe stock del libro ".$nombreLibro." cantidad disponible: ".$stockDisponible];
                       }
                    } catch (\Exception  $ex) {
                        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
                    }
                }
                ///FIN PROCESO DE GUIAS
                DB::UPDATE("UPDATE `pedidos_val_area`
                SET `valor` = ?,`plan_lector` = $request->plan_lector,
                `year` = '$request->libro'
                WHERE `id` = ?
                AND `alcance`  = 0
                ", [$valor, $val_pedido[0]->id]);
            }else{
                if($request->valor == "" || $request->valor == null){
                    return ["status" => "2"];
                }
                $valor          = $request->valor;
                $valorReserva   = 0;
                $totalAReservar = 0;
                   ///PROCESO DE VALIDACION EN GUIAS SI HAY STOCK
                   if($request->guias){
                    try{
                         //VALIDACION CONTANDO LOS HAN RESERVADO LAS GUIAS EXCEPTO DEL PEDIDO MAS LUEGO SE VA A SUMAR
                         $getReserva = $this->getReservaCodigosStock($request);
                         if(count($getReserva) > 0){
                             foreach($getReserva as $key2 => $item2){
                                 $valorReserva = $valorReserva + $item2->valor;
                             }
                         }
                         $totalAReservar = $valorReserva + $valor;
                         ///PROCESO
                         $query = [];
                        //plan lector
                        if($request->id_serie == 6) { $query = $this->pedidoxLibroPlanLector($request); }
                        else                        { $query = $this->pedidoxLibro($request); }
                        if(empty($query)){
                            return ["status" => "0", "message" => "No existe el libro"];
                        }
                       $codigoCodigo = $query[0]->codigo_liquidacion;
                       $nombreLibro  = $query[0]->nombre;
                       if($codigoCodigo == null || $codigoCodigo == "null" || $codigoCodigo == ""){
                            return ["status" => "0", "message" => "No existe codigo de liquidacion para el libro"];
                       }
                       $cantidad       = $request->valor;
                       $codigoFact     = "G".$codigoCodigo;
                       //get stock
                       $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigoFact);
                       $json_stock     = json_decode($getStock, true);
                       $stockAnterior  = $json_stock["producto"][0]["proStock"];
                        // $stockAnterior = 16;
                        //post stock
                        //MENOS 1 ES PORQUE es el minimo que se puede pedir
                        $nuevoStock     = $stockAnterior - $totalAReservar;
                        $stockDisponible = $stockAnterior - $valorReserva -1;
                       if($nuevoStock < 1){
                        return ["status" => "0", "message" => "No existe stock del libro ".$nombreLibro." cantidad disponible: ".$stockDisponible ];
                       }
                    } catch (\Exception  $ex) {
                        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
                    }

                }
                ///FIN PROCESO DE GUIAS
                DB::INSERT("INSERT INTO
                `pedidos_val_area`(`id_pedido`, `valor`, `id_area`, `tipo_val`,
                `id_serie`,`year`,`plan_lector`)
                VALUES (?,?,?,?,?,?,?)",
                [$request->id_pedido, $request->valor, $request->id_area, $request->tipo_val,
                $request->id_serie,$request->libro,$request->plan_lector]);
            }
            //CAMBIAR PEDIDO  EN PROCESO A PEDIDO CREADO-> actualizar la fecha de creacion de pedido
            DB::table('pedidos')
            ->where('id_pedido', $request->id_pedido)
            ->update([
                'estado' => 1,
                'fecha_creacion_pedido' => $fechaActual
            ]);
        }else{
            return ["status" => "0", "message" => "El pedido ya fue entregado por bodega no se puede modificar"];
        }
    }
    public function getReservaCodigosStock($request){
        $query = DB::SELECT("SELECT pa.*
        FROM pedidos p
        LEFT JOIN pedidos_val_area pa ON p.id_pedido = pa.id_pedido
        WHERE p.tipo = '1'
        AND p.estado = '1'
        AND p.estado_entrega = '0'
        and `tipo_val` = '$request->tipo_val'
        AND `id_area` = '$request->id_area'
        AND `id_serie` = '$request->id_serie'
        AND `alcance`  = 0
        AND p.id_pedido <> '$request->id_pedido'
       ");
       return $query;
    }
    public function pedidoxLibro($request){
        $query = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro
        FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
        WHERE ls.id_serie = '$request->id_serie'
        AND a.area_idarea  = '$request->id_area'
        AND l.Estado_idEstado = '1'
        AND a.estado = '1'
        AND ls.year = '$request->libro'
        LIMIT 1
       ");
       return $query;
    }
    public function pedidoxLibroPlanLector($request){
        $query = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro
        FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
        WHERE ls.id_serie = '6'
        AND l.Estado_idEstado = '1'
        AND a.estado = '1'
        AND l.idlibro = '$request->plan_lector'
        LIMIT 1
        ");
        return $query;
    }
    public function save_val_pedido_alcance(Request $request)
    {
        $fechaActual = date('Y-m-d H:i:s');
        //validar que si el pedido ya se entrego no se pueda modificar o crear nuevos valores
        $validate = DB::SELECT("SELECT * FROM pedidos p
        WHERE p.id_pedido = '$request->id_pedido'
        AND (p.estado_entrega = '1' OR p.estado_entrega = '2')
        ");
        if(empty($validate)){
            //validar que el pedido no este anulado
            $validate2 = DB::SELECT("SELECT * FROM pedidos p
            WHERE p.id_pedido = '$request->id_pedido'
            AND p.estado = '2'
            ");
            if(count($validate2)){
                return ["status" => "0", "message" => "No se puede modificar un pedido anulado"];
            }
            $val_pedido = DB::SELECT("SELECT * FROM `pedidos_val_area`
            WHERE `id_pedido` = ?
            AND `tipo_val` = ?
            AND `id_area` = ?
            AND `id_serie` = ?
            AND `alcance` = $request->alcance
            ",
            [$request->id_pedido, $request->tipo_val, $request->id_area, $request->id_serie]);
            if( count($val_pedido) > 0 ){
                $valor = $request->valor;
                if($request->valor == "" || $request->valor == null || $request->valor == 0){
                    DB::DELETE("DELETE FROM pedidos_val_area
                     where id_pedido ='$request->id_pedido'
                     AND tipo_val = '$request->tipo_val'
                     AND id_area = '$request->id_area'
                     AND alcance = '$request->alcance'
                    ");
                    return;
                }
                DB::UPDATE("UPDATE `pedidos_val_area`
                SET `valor` = ?,`plan_lector` = $request->plan_lector,
                `year` = '$request->libro'
                WHERE `id` = ?
                AND alcance = '$request->alcance'
                ", [$valor, $val_pedido[0]->id]);
            }else{
                if($request->valor == "" || $request->valor == null){
                    return ["status" => "2"];
                }
                DB::INSERT("INSERT INTO
                `pedidos_val_area`(`id_pedido`, `valor`, `id_area`, `tipo_val`,
                `id_serie`,`year`,`plan_lector`,`alcance`)
                VALUES (?,?,?,?,?,?,?,?)",
                [$request->id_pedido, $request->valor, $request->id_area, $request->tipo_val,
                $request->id_serie,$request->libro,$request->plan_lector,$request->alcance]);
            }
            //CAMBIAR PEDIDO  EN PROCESO A PEDIDO CREADO-> actualizar la fecha de creacion de pedido
            // DB::table('pedidos')
            // ->where('id_pedido', $request->id_pedido)
            // ->update([
            //     'estado' => 1,
            //     'fecha_creacion_pedido' => $fechaActual
            // ]);
        }else{
            return ["status" => "0", "message" => "El pedido ya fue entregado por bodega no se puede modificar"];
        }
    }
    //actualizar la fecha de creacion del pedido
    public function UpdateFechaCreacionPedido($id_pedido){
        $fechaActual = date('Y-m-d H:i:s');
        DB::table('pedidos')
        ->where('id_pedido', $id_pedido)
        ->update([
            'fecha_creacion_pedido' => $fechaActual
        ]);
    }
    public function save_pvp_area_formato(Request $request)
    {
        $valida_pvp = DB::SELECT("SELECT *
        FROM `pedidos_formato`
        WHERE `id_periodo` = ?
        AND `id_serie` = ?
        AND `id_area` = ?
        AND `id_libro` = ?",
        [$request->id_periodo, $request->id_serie, $request->id_area, $request->id_libro]);
        $orden = 0;
        if($request->orden == "" || $request->orden == null){
            $orden = 0;
        }else{
            $orden = $request->orden;
        }
        if( count($valida_pvp) > 0 ){
            DB::UPDATE("UPDATE `pedidos_formato`
            SET `pvp`= ? , `orden`= ?
            WHERE `id` = ?", [$request->pvp,$orden, $valida_pvp[0]->id]);
        }else{
            DB::INSERT("INSERT INTO
            `pedidos_formato`(`id_serie`, `id_area`, `id_libro`, `id_periodo`, `pvp`,`orden`)
             VALUES (?,?,?,?,?,?)",
             [$request->id_serie, $request->id_area, $request->id_libro,
             $request->id_periodo, $request->pvp,$orden]);
        }
        // para refrescar checks de niveles
        $pvp_data = DB::SELECT("SELECT *
        FROM `pedidos_formato`
        WHERE `id_libro` = ?
        AND `id_periodo` = ?
        AND `id_serie` = ?
        AND `id_area` = ?",
        [$request->id_libro, $request->id_periodo, $request->id_serie, $request->id_area]);
        return $pvp_data;
    }

    public function get_pedido($usuario, $periodo, $institucion)
    {
        $pedido = DB::SELECT("SELECT DISTINCT p.*, v.valor, v.tipo_val, i.idInstitucion, i.nombreInstitucion, c.nombre AS nombre_ciudad
        FROM pedidos p
        LEFT JOIN pedidos_val_area v ON p.id_pedido = v.id_pedido
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON p.ciudad = c.idciudad
        WHERE p.id_asesor = $usuario
        AND p.id_periodo = $periodo
        AND p.id_institucion = $institucion;
        AND p.tipo = '0'
        ");
        return $pedido;
    }
    public function anular_pedido_asesor($id_pedido, $id_usuario,$contrato)
    {
        try{
            //validar si tiene solicitudes  de verificaciones no se pueda anular
            $query = DB::SELECT("SELECT * FROM temporadas_verificacion_historico h
            WHERE h.contrato = '$contrato'
            ");
            if(count($query) > 0){
                return ["status" => "0","message" => "Ya existe solicitudes de verificaciones para este contrato $contrato"];
            }
            $pedido = Pedidos::findOrFail($id_pedido);
            //======Validar que no haya pagos aprobados si hay pagos aprobados no se podra desabilitar=====
            $query = $this->pagoRepository->getPagosInstitucion($pedido->id_institucion,$pedido->id_periodo);
            if(count($query) > 0){
                return ["status" => "0","message" => "No se puede anular el pedido por que existen pagos aprobados"];
            }else{
                $query2 = $this->pagoRepository->getPagosSinContrato($pedido->id_institucion,$pedido->id_periodo);
                foreach($query2 as $key2 => $item2){ PedidosDocumentosLiq::where('doc_codigo', $item2->doc_codigo)->delete(); }
            }
            //=====FIN VALIDACION DE PAGOS APROBADOS
            DB::SELECT("UPDATE `pedidos` SET `id_usuario_verif`=$id_usuario, `convenio_anios`= '0',  `pedidos_convenios_id`='0',  `estado`=2 WHERE `id_pedido` = $id_pedido");
            DB::SELECT("UPDATE `pedidos_convenios` SET estado=2 WHERE `id_pedido` = $id_pedido");
            //DEJAR A CERO SI HAY REVISION O NOTIFICACION
            $this->changeRevisonNotificacion($id_pedido,0);
            if($contrato == "null" || $contrato == null || $contrato == "undefined" ||  $contrato == "" ){
            }else{
                DB::UPDATE("UPDATE temporadas SET estado = '0' WHERE contrato ='$contrato'");

            }
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas en el proceso".$ex];
        }
    }
    public function get_libros_plan_pedido($serie, $periodo){ // plan lector
        $libros_plan = DB::SELECT("SELECT l.*, p.pvp, p.id_periodo FROM libro l
        INNER JOIN libros_series ls ON l.idLibro = ls.idLibro
        LEFT JOIN pedidos_formato p ON l.idlibro = p.id_libro AND p.id_periodo = $periodo
        WHERE ls.id_serie = 6
        ORDER BY `p`.`pvp` DESC");

        return $libros_plan;
    }
    //api:get>>/getTransabilidad/{id_pedido}
    public function getTransabilidad($id_pedido){
        $query = DB::SELECT("SELECT p.fecha_creacion_pedido AS pedido_creacion,ph.*,
        IF(p.ifanticipo = '0',p.fecha_generacion_contrato,ph.fecha_generar_contrato) as f_generateContrato
        FROM pedidos p
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        WHERE p.id_pedido = '$id_pedido'
        ");
        return $query;
    }
    public function get_datos_pedido($id_pedido)
    {
        $pedido = DB::SELECT("SELECT DISTINCT p.*, u.nombres, u.apellidos,
        u.cedula, i.idInstitucion,CONCAT(i.nombreInstitucion,' - ',c.nombre)as nombreInstitucion ,
        i.telefonoInstitucion, i.direccionInstitucion, i.ruc, i.nivel,i.tipo_descripcion,
        c.nombre AS nombre_ciudad, p.ifanticipo,pe.porcentaje_descuento,
        i.codigo_institucion_milton,i.codigo_mitlon_coincidencias,pe.region_idregion,
        ph.estado as historicoEstado,pe.codigo_contrato,pe.periodoescolar
        FROM pedidos p
		LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON pe.idperiodoescolar = p.id_periodo
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        WHERE p.id_pedido = $id_pedido
        LIMIT 1
        ");
        //files
        $files = DB::SELECT("SELECT * FROM pedidos_files pf
        WHERE pf.id_pedido = '$id_pedido'
        ");
        return ["pedido" => $pedido,"files" => $files];
    }
    public function get_datos_pedido_guias($pedido){
        $pedido = DB::SELECT("SELECT DISTINCT p.*, u.nombres, u.apellidos,
            u.cedula, v.valor, v.tipo_val,v.alcance
            FROM pedidos p
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            LEFT JOIN pedidos_val_area v ON p.id_pedido = v.id_pedido
            LEFT JOIN periodoescolar pe ON pe.idperiodoescolar = p.id_periodo
            WHERE p.id_pedido = $pedido
            AND (v.alcance = 0 OR v.alcance is null)
        ");
        return $pedido;
    }
    //Libros de pedido y alcance
    public function get_val_pedidoInfoTodo($pedido){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
        p.descuento, p.id_periodo,
        p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie, 0 as cantidad
        FROM pedidos_val_area pv
        left join area ar ON  pv.id_area = ar.idarea
        left join series se ON pv.id_serie = se.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        GROUP BY pv.id;
        ");
        if(empty($val_pedido)){
            return $val_pedido;
        }
        $arreglo = [];
        $cont    = 0;
        //obtener solo los alcances activos
        foreach($val_pedido as $k => $tr){
            //Cuando es el pedido original
            $alcance_id = 0;
            $alcance_id = $tr->alcance;
            if($alcance_id == 0){
                $arreglo[$cont] =   (object)[
                    "id"                => $tr->id,
                    "id_pedido"         => $tr->id_pedido,
                    "valor"             => $tr->valor,
                    "id_area"           => $tr->id_area,
                    "tipo_val"          => $tr->tipo_val,
                    "id_serie"          => $tr->id_serie,
                    "year"              => $tr->year,
                    "plan_lector"       => $tr->plan_lector,
                    "alcance"           => $tr->alcance,
                    "descuento"         => $tr->descuento,
                    "id_periodo"        => $tr->id_periodo,
                    "anticipo"          => $tr->anticipo,
                    "comision"          => $tr->comision,
                    "serieArea"         => $tr->serieArea,
                    "nombre_serie"      => $tr->nombre_serie,
                    "alcance"           => $alcance_id,
                    "cantidad"          => $tr->cantidad,
                ];
            }else{
                //validate que el alcance este cerrado o aprobado
                $query = $this->getAlcanceAbiertoXId($alcance_id);
                if(count($query) > 0){
                    $arreglo[$cont] = (object) [
                        "id"                => $tr->id,
                        "id_pedido"         => $tr->id_pedido,
                        "valor"             => $tr->valor,
                        "id_area"           => $tr->id_area,
                        "tipo_val"          => $tr->tipo_val,
                        "id_serie"          => $tr->id_serie,
                        "year"              => $tr->year,
                        "plan_lector"       => $tr->plan_lector,
                        "alcance"           => $tr->alcance,
                        "descuento"         => $tr->descuento,
                        "id_periodo"        => $tr->id_periodo,
                        "anticipo"          => $tr->anticipo,
                        "comision"          => $tr->comision,
                        "serieArea"         => $tr->serieArea,
                        "nombre_serie"      => $tr->nombre_serie,
                        "alcance"           => $alcance_id,
                        "cantidad"          => $tr->cantidad,
                    ];
                }
            }
            $cont++;
        }
        //mostrar el arreglo bien
        $renderSet = [];
        $renderSet = array_values($arreglo);
        if(count($renderSet) == 0){
            return $renderSet;
        }
        $datos = [];
        $contador = 0;
        //return $renderSet;
        foreach($renderSet as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro, pro.pro_stock,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->plan_lector'
                    AND f.id_periodo = '$item->id_periodo'
                )as precio, ls.codigo_liquidacion,ls.version,ls.year
                FROM libro l
                left join libros_series ls  on ls.idLibro = l.idlibro
                inner join 1_4_producto pro on ls.codigo_liquidacion=pro.pro_codigo
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro, pro.pro_stock,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                inner join 1_4_producto pro on ls.codigo_liquidacion=pro.pro_codigo
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$contador] = [
                "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $valores[0]->year,
                "version"           => $valores[0]->version,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "idlibro"           => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "stock"            => $valores[0]->pro_stock,
                "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
                "alcance"           => $item->alcance,
                "cantidad"          => $tr->cantidad,
            ];
            $contador++;
        }
        return $datos;

    }
    public function getAlcanceAbiertoXId($id){
        $query = DB::SELECT("SELECT * FROM pedidos_alcance a
        WHERE a.id = '$id'
        AND a.estado_alcance = '1'");
        return $query;
    }
    public function get_val_pedidoInfo($pedido){
        try{
            $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
            p.descuento, p.id_periodo,
            p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
            se.nombre_serie
            FROM pedidos_val_area pv
            left join area ar ON  pv.id_area = ar.idarea
            left join series se ON pv.id_serie = se.id_serie
            INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
            WHERE pv.id_pedido = '$pedido'
            AND pv.alcance = '0'
            GROUP BY pv.id;
            ");
            $datos = [];
            foreach($val_pedido as $key => $item){
                $valores = [];
                //plan lector
                if($item->plan_lector > 0 ){
                    $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,l.asignatura_idasignatura,
                    (
                        SELECT f.pvp AS precio
                        FROM pedidos_formato f
                        WHERE f.id_serie = '6'
                        AND f.id_area = '69'
                        AND f.id_libro = '$item->plan_lector'
                        AND f.id_periodo = '$item->id_periodo'
                    )as precio, ls.codigo_liquidacion,ls.version,ls.year
                    FROM libro l
                    left join libros_series ls  on ls.idLibro = l.idlibro
                    WHERE l.idlibro = '$item->plan_lector'
                    ");
                    $valores = $getPlanlector;
                }else{
                    $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,l.asignatura_idasignatura,
                    (
                        SELECT f.pvp AS precio
                        FROM pedidos_formato f
                        WHERE f.id_serie = ls.id_serie
                        AND f.id_area = a.area_idarea
                        AND f.id_periodo = '$item->id_periodo'
                    )as precio
                    FROM libros_series ls
                    LEFT JOIN libro l ON ls.idLibro = l.idlibro
                    LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                    WHERE ls.id_serie = '$item->id_serie'
                    AND a.area_idarea  = '$item->id_area'
                    AND l.Estado_idEstado = '1'
                    AND a.estado = '1'
                    AND ls.year = '$item->year'
                    LIMIT 1
                    ");
                    $valores = $getLibros;
                }
                $datos[$key] = [
                    "id"                => $item->id,
                    "id_pedido"         => $item->id_pedido,
                    "valor"             => $item->valor,
                    "id_area"           => $item->id_area,
                    "tipo_val"          => $item->tipo_val,
                    "id_serie"          => $item->id_serie,
                    "year"              => $item->year,
                    "anio"              => $valores[0]->year,
                    "version"           => $valores[0]->version,
                    "created_at"        => $item->created_at,
                    "updated_at"        => $item->updated_at,
                    "descuento"         => $item->descuento,
                    "anticipo"          => $item->anticipo,
                    "comision"          => $item->comision,
                    "plan_lector"       => $item->plan_lector,
                    "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                    "idlibro"           => $valores[0]->idlibro,
                    "nombrelibro"       => $valores[0]->nombrelibro,
                    "precio"            => $valores[0]->precio,
                    "idasignatura"      => $valores[0]->asignatura_idasignatura,
                    "subtotal"          => $item->valor * $valores[0]->precio,
                    "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
                ];
            }
            return $datos;
        }
        catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
    }
    public function get_val_pedidoInfo_alcance($pedido,$alcance){
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*,
        p.descuento, p.id_periodo,
        p.anticipo, p.comision, CONCAT(se.nombre_serie,' ',ar.nombrearea) as serieArea,
        se.nombre_serie
        FROM pedidos_val_area pv
        left join area ar ON  pv.id_area = ar.idarea
        left join series se ON pv.id_serie = se.id_serie
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        WHERE pv.id_pedido = '$pedido'
        AND pv.alcance = '$alcance'
        GROUP BY pv.id;
        ");
        $datos = [];
        foreach($val_pedido as $key => $item){
            $valores = [];
            //plan lector
            if($item->plan_lector > 0 ){
                $getPlanlector = DB::SELECT("SELECT l.nombrelibro,l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = '6'
                    AND f.id_area = '69'
                    AND f.id_libro = '$item->plan_lector'
                    AND f.id_periodo = '$item->id_periodo'
                )as precio, ls.codigo_liquidacion,ls.version,ls.year
                FROM libro l
                left join libros_series ls  on ls.idLibro = l.idlibro
                WHERE l.idlibro = '$item->plan_lector'
                ");
                $valores = $getPlanlector;
            }else{
                $getLibros = DB::SELECT("SELECT ls.*, l.nombrelibro, l.idlibro,
                (
                    SELECT f.pvp AS precio
                    FROM pedidos_formato f
                    WHERE f.id_serie = ls.id_serie
                    AND f.id_area = a.area_idarea
                    AND f.id_periodo = '$item->id_periodo'
                )as precio
                FROM libros_series ls
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                WHERE ls.id_serie = '$item->id_serie'
                AND a.area_idarea  = '$item->id_area'
                AND l.Estado_idEstado = '1'
                AND a.estado = '1'
                AND ls.year = '$item->year'
                LIMIT 1
                ");
                $valores = $getLibros;
            }
            $datos[$key] = [
                "id"                => $item->id,
                "id_pedido"         => $item->id_pedido,
                "valor"             => $item->valor,
                "id_area"           => $item->id_area,
                "tipo_val"          => $item->tipo_val,
                "id_serie"          => $item->id_serie,
                "year"              => $item->year,
                "anio"              => $valores[0]->year,
                "version"           => $valores[0]->version,
                "created_at"        => $item->created_at,
                "updated_at"        => $item->updated_at,
                "descuento"         => $item->descuento,
                "anticipo"          => $item->anticipo,
                "comision"          => $item->comision,
                "plan_lector"       => $item->plan_lector,
                "serieArea"         => $item->id_serie == 6 ? $item->nombre_serie." ".$valores[0]->nombrelibro : $item->serieArea,
                "idlibro"           => $valores[0]->idlibro,
                "nombrelibro"       => $valores[0]->nombrelibro,
                "precio"            => $valores[0]->precio,
                "subtotal"          => $item->valor * $valores[0]->precio,
                "codigo_liquidacion"=> $valores[0]->codigo_liquidacion,
            ];
        }
        return $datos;
    }
    public function get_val_pedido($pedido)
    {
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*, p.descuento, p.id_periodo,
        p.anticipo, p.comision ,pe.porcentaje_descuento,p.anticipo_aprobado,
        p.contrato_generado,p.estado_entrega,p.id_asesor
        FROM pedidos_val_area pv
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE pv.id_pedido = '$pedido'
        AND pv.alcance = '0'
        GROUP BY pv.id;
        ");
        return $val_pedido;
    }
    public function get_val_pedido_alcance($pedido,$alcance)
    {
        $val_pedido = DB::SELECT("SELECT DISTINCT pv.*, p.descuento, p.id_periodo,
        p.anticipo, p.comision ,pe.porcentaje_descuento,p.anticipo_aprobado,
        p.contrato_generado,p.estado_entrega
        FROM pedidos_val_area pv
        INNER JOIN pedidos p ON pv.id_pedido = p.id_pedido
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE pv.id_pedido = '$pedido'
        AND pv.alcance = '$alcance'
        GROUP BY pv.id;
        ");
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
        //validar que exista el libro
        $validate = DB::SELECT("SELECT * FROM libros_series ls
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
        WHERE ls.id_serie = '$request->id_serie'
        AND a.area_idarea  = '$request->id_area'
        AND l.Estado_idEstado = '1'
        AND a.estado = '1'
        AND ls.year = '$request->index'
        ");
        if(empty($validate)){
            return ["status" => "0", "message" => "El libro no existe para este nivel"];
        }
        $valida_nivel = DB::SELECT("SELECT *
        FROM `pedidos_formato`
        WHERE `id_periodo` = ?
        AND `id_serie` = ?
        AND `id_area` = ?",
        [$request->id_periodo, $request->id_serie, $request->id_area]);
        if( count($valida_nivel) > 0 ){
            $id = $valida_nivel[0]->id;
            DB::UPDATE("UPDATE `pedidos_formato`
            SET `n".$request->index."` = '$request->check' WHERE id ='$id'",
            );
        }else{
            DB::INSERT("INSERT INTO
            `pedidos_formato`(`id_serie`, `id_area`, `id_periodo`, `n".$request->index."`)
            VALUES (?,?,?,?)", [ $request->id_serie, $request->id_area, $request->id_periodo,
            $request->check]);
        }
        return "se guardo";
    }
    //bodega
    public function getAllPedidos(Request $request){
        $pedidos = DB::SELECT("SELECT p.*,
        SUBSTRING(p.fecha_envio,1,10) as f_fecha_envio,
        SUBSTRING(p.fecha_entrega,1,10) as f_fecha_entrega,
        CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor,
        i.nombreInstitucion,i.codigo_institucion_milton, c.nombre AS nombre_ciudad,
        CONCAT(u.nombres,' ',u.apellidos) as responsable, u.cedula,u.iniciales,
        ph.estado as historicoEstado,ph.evidencia_cheque,ph.evidencia_pagare,
        IF(p.estado = 2,'Anulado','Activo') AS estadoPedido,
        (SELECT f.id_facturador from pedidos_asesores_facturador
        f where f.id_asesor = p.id_asesor  LIMIT 1) as id_facturador,
        (
            SELECT COUNT(*) AS contador_alcance FROM pedidos_alcance pa
            WHERE pa.id_pedido = p.id_pedido
        ) AS contador_alcance,
        (SELECT COUNT(*) FROM verificaciones v WHERE v.contrato = p.contrato_generado AND v.nuevo = '1' AND v.estado = '0') as verificaciones,
        (
            SELECT COUNT(a.id) AS contadorAlcanceAbierto
            FROM pedidos_alcance a
            LEFT JOIN pedidos ped ON ped.id_pedido = a.id_pedido
            WHERE  a.id_pedido = p.id_pedido
            AND a.estado_alcance  = '0'
            AND ped.estado = '1'
            AND a.venta_bruta > 0
        ) as contadorAlcanceAbierto,
        (
            SELECT COUNT(a.id) AS contadorAlcanceCerrado
            FROM pedidos_alcance a
            LEFT JOIN pedidos ped ON ped.id_pedido = a.id_pedido
            WHERE  a.id_pedido = p.id_pedido
            AND a.estado_alcance  = '1'
            AND ped.estado = '1'
        ) as contadorAlcanceCerrado,
        pe.pedido_bodega,pe.periodoescolar
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON pe.idperiodoescolar = p.id_periodo
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        WHERE p.tipo = '0'
        AND p.estado = '1'
        AND p.facturacion_vee = '1'
        AND pe.pedido_bodega   = '1'
        ORDER BY p.id_pedido DESC
        ");
        return $pedidos;
    }

    public function get_pedidos_periodo($periodo,$rol,$idusuario)
    {
        $clave = "get_pedidos_periodo".$periodo.$idusuario;
        if (Cache::has($clave)) {
            $response = Cache::get($clave);
        } else {
            if($rol == 1 || $rol == 23) { $pedidos = $this->getPedido(1,$periodo); }
            if($rol == 11){ $pedidos = $this->getPedido(2,$periodo,$idusuario); }
            // $datos = [];
            // foreach($pedidos as $key => $item){
            //     $resultado  = [];
            //     $valores    = (array) $item;
            //     $pagos      = (array) $this->getPagos($item->contrato_generado);
            //     $resultado  = array_merge($valores,$pagos);
            //     $datos[$key] = [
            //         $resultado
            //     ];
            // }
            //quitar algunos []
            // $response = [];
            // $response = array_merge(...$datos);
            $response = $pedidos;
            Cache::put($clave,$response);
        }
        return $response;
    }
    public function get_pedidos_periodo_facturador($periodo,$id_facturador){
        $clave = "get_pedidos_periodo_facturador".$periodo.$id_facturador;
        if (Cache::has($clave)) {
            $response = Cache::get($clave);
        } else {
           //traer los asesores que tiene asignado el facturador
            $query = DB::SELECT("SELECT DISTINCT f.id_asesor FROM pedidos_asesores_facturador f
            WHERE f.id_facturador = '$id_facturador'
            ");
            $arreglo = [];
            $preval  = [];
            foreach($query as $key => $item){
                $pedidos = $this->getPedido(3,$periodo,$item->id_asesor);
                $arreglo[$key] = (Object)$pedidos;
            }
            $arraySinCorchetes = array_map(function ($item) { return json_decode(json_encode($item)); }, $arreglo);
            // return reset($arreglo);
            $getPedidos =  array_merge(...$arraySinCorchetes);
            $datos      = collect($getPedidos);
            //ordenar id_pedido de forma descendente
            $response = $datos->sortByDesc('id_pedido')->values()->all();
            Cache::put($clave,$response);
        }
        return $response;
    }
    public function get_pedidos_periodo_contrato($contrato){
        $pedidos = DB::SELECT("SELECT p.*, pe.periodoescolar as periodo,
         CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor,
          i.nombreInstitucion, c.nombre AS nombre_ciudad
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE contrato_generado like '%$contrato%'
        ");
        return $pedidos;
    }
    public function get_pedidos_periodo_Only_contrato($contrato,$beneficiario){
        $pedidos = DB::SELECT("SELECT p.*, pe.periodoescolar as periodo,
        CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
        i.nombreInstitucion,i.telefonoInstitucion, c.nombre AS nombre_ciudad,
        (
            SELECT CONCAT(ub.nombres, ' ', ub.apellidos) AS beneficiario
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario ub ON b.id_usuario = ub.idusuario
            WHERE b.id_pedido = p.id_pedido
            AND b.id_beneficiario_pedido = '$beneficiario'
            LIMIT 1
        ) as beneficiario,
        (
            SELECT ub.cedula
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario ub ON b.id_usuario = ub.idusuario
            WHERE b.id_pedido = p.id_pedido
            AND b.id_beneficiario_pedido = '$beneficiario'
            LIMIT 1
        ) as beneficiario_cedula,
        (
            SELECT ub.nombres
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario ub ON b.id_usuario = ub.idusuario
            WHERE b.id_pedido = p.id_pedido
            AND b.id_beneficiario_pedido = '$beneficiario'
            LIMIT 1
        ) as beneficiario_nombres,
        (
            SELECT ub.apellidos
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario ub ON b.id_usuario = ub.idusuario
            WHERE b.id_pedido = p.id_pedido
            AND b.id_beneficiario_pedido = '$beneficiario'
            LIMIT 1
        ) as beneficiario_apellidos,
        CONCAT(uf.apellidos, ' ',uf.nombres) as facturador
        FROM pedidos p
        INNER JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN usuario uf ON p.id_usuario_verif = uf.idusuario
        INNER JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE contrato_generado = '$contrato'
        LIMIT 1
        ");
        return $pedidos;
    }
    public function get_pedidos_periodo_Only_pedido($pedido,$beneficiario){
        $pedidos =$this->getPedido(0,$pedido);
        $datos = [];
        foreach($pedidos as $key => $item){
            $consulta = DB::SELECT(" SELECT LOWER(CONCAT(ub.nombres, ' ', ub.apellidos)) AS beneficiario,
            b.*, ub.email,ub.cedula as cedula_beneficiario, LOWER(b.direccion) as direccionBenefiario
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario ub ON b.id_usuario = ub.idusuario
            WHERE b.id_pedido = '$pedido'
            AND b.id_beneficiario_pedido = '$beneficiario'
            LIMIT 1
            ");
            $datos[$key] = [
                "id_pedido"             => $item->id_pedido,
                "id_asesor"             => $item->id_asesor,
                "tipo_venta"            => $item->tipo_venta == 1 ? 'D':'L',
                "fecha_envio"           => $item->fecha_envio,
                "fecha_entrega"         => $item->fecha_entrega,
                "id_institucion"        => $item->id_institucion,
                "descuento"             => $item->descuento,
                "anticipo"              => $item->anticipo,
                "anticipo_aprobado"     => $item->anticipo_aprobado,
                "comision"              => $item->comision,
                "total_venta"           => $item->total_venta,
                "total_unidades"        => $item->total_unidades,
                "contrato_generado"     => $item->contrato_generado,
                "estado"                => $item->estado,
                "estado_entrega"        => $item->estado_entrega,
                "periodo"               => $item->periodo,
                "asesor"                => $item->asesor,
                "iniciales"             => $item->iniciales,
                "nombreInstitucion"     => $item->nombreInstitucion,
                "telefonoInstitucion"   => $item->telefonoInstitucion,
                "direccionInstitucion"  => ucwords($item->direccionInstitucion),
                "nombre_ciudad"         => ucfirst($item->nombre_ciudad),
                "facturador"            => $item->facturador,
                "observacion"           => $item->observacion,
                "ruc"                   => $item->ruc,
                "tipo_descripcion"      => $item->tipo_descripcion,
                "nivel"                 => $item->nivel,
                "convenio_anios"        => $item->convenio_anios,
                "region"                => $item->region == 1 ? 'SIERRA':'COSTA',
                "tregion"               => $item->region,
                "codigo_contrato"       => $item->codigo_contrato,
                "cod_usuario"           => $item->cod_usuario,
                "fecha_generar_contrato" => $item->ifanticipo == 0 ? $item->fecha_generacion_contrato : $item->fecha_generar_contrato,
                "beneficiario"          => $consulta[0]->beneficiario,
                "tipo_cuenta"           => $consulta[0]->tipo_cuenta,
                "num_cuenta"            => $consulta[0]->num_cuenta,
                "banco"                 => $consulta[0]->banco,
                "email"                 => $consulta[0]->email,
                "cedula_beneficiario"   => $consulta[0]->cedula_beneficiario,
                "direccionBenefiario"   => $consulta[0]->direccionBenefiario
            ];
        }
        return $datos;
    }


    public function get_pedidos_asesor($periodo, $asesor)
    {
        $pedidos = DB::SELECT("SELECT
        p.*, CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor,u.iniciales,
        i.nombreInstitucion, c.nombre AS nombre_ciudad,
        CONCAT(u.nombres,' ',u.apellidos) as responsable, u.cedula,
        ph.estado as historicoEstado,ph.evidencia_cheque,ph.evidencia_pagare,
        pe.codigo_contrato, pe.region_idregion,pe.periodoescolar as periodo
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN pedidos_historico  ph ON p.id_pedido = ph.id_pedido
        LEFT JOIN periodoescolar pe ON pe.idperiodoescolar = p.id_periodo
        WHERE p.id_periodo = $periodo
        AND u.idusuario = $asesor
        AND p.tipo = '1'
        ORDER BY p.id_pedido DESC
        ");
        return $pedidos;
    }
    public function get_pedidos_guias(Request $request){
        $guias = DB::SELECT("SELECT
        p.*, CONCAT(u.nombres, ' ', u.apellidos, ' CI: ', u.cedula) AS asesor,
        CONCAT(u.nombres,' ',u.apellidos) as responsable, u.cedula,u.iniciales,u.cli_ins_codigo,
        pe.codigo_contrato, pe.region_idregion,
        CONCAT(fac.nombres,' ',fac.apellidos) as facturador, pe.periodoescolar as periodo,
        pe.pedido_facturacion, pe.pedido_bodega, pe.pedido_asesor
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN periodoescolar pe ON pe.idperiodoescolar = p.id_periodo
        LEFT JOIN usuario fac ON p.id_usuario_verif  = fac.idusuario
        WHERE p.tipo = '1'
        ORDER BY p.id_pedido DESC
        ");
        return $guias;
    }

    public function get_comentarios_pedido($pedido)
    {
        $comentarios = DB::SELECT("SELECT p.*, u.nombres, u.apellidos FROM pedidos_comentarios p, usuario u WHERE p.id_usuario = u.idusuario AND p.id_pedido = $pedido ORDER BY p.id DESC");

        return $comentarios;
    }

    public function get_beneficiarios_pedidos($pedido)
    {
        $beneficiarios = DB::SELECT("SELECT b.*,u.nombres,u.apellidos,
        CONCAT(u.nombres, ' ', u.apellidos) as nombres_beneficiario, u.cedula,
        CONCAT(u.nombres, ' ', u.apellidos, ' ',  b.comision ,'%') as beneficiarioComision
        FROM pedidos_beneficiarios b
        INNER JOIN usuario u ON b.id_usuario = u.idusuario
        WHERE b.id_pedido = $pedido");
        return $beneficiarios;
    }

    public function guardar_comentario(Request $request)
    {
       //para dejar en visto los mensajes
       $this->VistosMensajesPedidos($request->id_pedido,$request->id_group);
       DB::INSERT("INSERT INTO `pedidos_comentarios`(`id_pedido`, `comentario`, `id_usuario`, `id_group`) VALUES (?,?,?,?)", [$request->id_pedido,$request->comentario,$request->id_usuario,$request->id_group]);
    }
    public function VistosMensajesPedidos($id_pedido,$id_group){
        if($id_group == 11){
            //actualizar los mensajes como leidos para los facturadores
            DB::UPDATE("UPDATE pedidos_comentarios SET visto = '0'
            WHERE id_pedido = '$id_pedido'
            AND id_group <> '11'
            ");
        }else{
            //actualizar los mensajes como leidos para los asesores
            DB::UPDATE("UPDATE pedidos_comentarios SET visto = '0'
            WHERE id_pedido = '$id_pedido'
            AND id_group = '11'
            ");
        }
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
        $instituciones = DB::SELECT("SELECT i.*,
        CONCAT(i.nombreInstitucion,' - ',c.nombre) AS nombre_institucion,
         i.idInstitucion AS id_institucion
         FROM institucion i
         LEFT JOIN ciudad c ON c.idciudad = i.ciudad_id
         WHERE i.vendedorInstitucion = '$cedula'");
        return $instituciones;
    }

    public function get_responsables_pedidos(Request $request)
    {
        $responsables = DB::SELECT("SELECT u.idusuario,u.nombres, u.apellidos,
         u.email,u.id_group,u.cedula,
         CONCAT(u.nombres,' ', u.apellidos, ' - ', u.cedula) AS 'nombres_responsable',
         g.deskripsi as rol,u.telefono
          FROM usuario u
          LEFT JOIN sys_group_users g ON g.id = id_group
          WHERE u.estado_idEstado = 1
          AND (u.id_group = 6 OR u.id_group = 10 OR u.id_group = 11)
          AND u.cedula like '%$request->cedula%'
          ");
        return $responsables;
    }

    public function guardar_total_pedido($id_pedido, $total_usd, $total_unid,$total_guias,$total_series_basicas,$anticipoSugerido)
    {
        //validar que el pedido que tenga contrato no se actualize
        $query = DB::SELECT("SELECT * FROM pedidos WHERE id_pedido = '$id_pedido'");
        // $query = DB::SELECT("SELECT * FROM pedidos WHERE id_pedido = '$id_pedido' AND contrato_generado IS NULL");
        if(count($query) > 0){
            DB::UPDATE("UPDATE `pedidos` SET
                `total_venta` = $total_usd,
                `total_unidades` = $total_unid,
                `total_unidades_guias` = $total_guias,
                `total_series_basicas` = $total_series_basicas,
                `anticipo`             = $anticipoSugerido
                WHERE `id_pedido` = $id_pedido
            ");
        }
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
        try {
            $form_data = [
                'cli_ci'        => $request->cedula,
                'cli_apellidos' => $request->apellidos,
                'cli_nombres'   => $request->nombres,
                'cli_direccion' => $request->direccion,
                'cli_telefono'  => $request->telefono,
                'cli_email'     => $request->email
            ];
            Http::post('http://186.4.218.168:9095/api/Cliente', $form_data);
         } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
        // LUEGO SE GUARDA EN BASE PROLIPA
        $password                           = sha1(md5($request->cedula));
        $user                               = new User();
        $user->cedula                       = $request->cedula;
        $user->nombres                      = $request->nombres;
        $user->apellidos                    = $request->apellidos;
        $user->name_usuario                 = $request->email;
        $user->password                     = $password;
        $user->email                        = $request->email;
        $user->id_group                     = 6;
        $user->institucion_idInstitucion = $request->institucion_idInstitucion;
        $user->estado_idEstado              = 1;
        $user->idcreadorusuario             = $request->idcreadorusuario;
        $user->telefono                     = $request->telefono;
        $user->save();
        return $user;
    }

    public function save_beneficiarios_pedido(Request $request) //docente
    {
        //variables
        $cedula     = $request->num_identificacion;
        $nombres    = $request->nombres;
        $apellidos  = $request->apellidos;
        $email      = $request->correo;
        if($request->telefono == null || $request->telefono == "null"){
            $telefono   = null;
        }else{
            $telefono   = $request->telefono;
        }
          //====PROCESO===
          $docente = DB::SELECT("SELECT cedula FROM `usuario` WHERE `idusuario` = ?", [$request->idusuario]);
          // generar cli_ins_codigo
          $asesor = DB::SELECT("SELECT iniciales FROM `usuario` WHERE `idusuario` = ?", [$request->id_asesor]);
          $institucion = DB::SELECT("SELECT codigo_institucion_milton FROM `institucion` WHERE `idInstitucion` = ?", [$request->institucion]);

         // SE VERIFICA QUE NO ESTE YA CREADO EL CLI INS CODIGO
        //  $verif_cli_ins_cod = DB::SELECT("SELECT * FROM `pedidos_asesor_institucion_docente`
        //  WHERE `id_asesor` = ? AND `id_institucion` = ? AND `id_docente` = ?",
        //  [$asesor[0]->iniciales, $institucion[0]->codigo_institucion_milton, $docente[0]->cedula]);
        //  if( count($verif_cli_ins_cod) == 0){
        //     //====Crear cedula en facturacion si no existe=====
        //     try {
        //         $dato = Http::get("http://186.4.218.168:9095/api/f_Cliente/Busquedaxclici?cli_ci=".$cedula);
        //         $JsonCedula = json_decode($dato, true);
        //         // return $JsonCedula;
        //         if(isset($JsonCedula["clientexclici"])){
        //             $arrayUser = $JsonCedula["clientexclici"][0];
        //             //variables
        //             $cliDireccion         = $arrayUser["cliDireccion"];
        //             $cliCredito           = $arrayUser["cliCredito"];
        //             $cliPlazo             = $arrayUser["cliPlazo"];
        //             $cliAlias             = $arrayUser["cliAlias"];
        //             $cliCelular           = $arrayUser["cliCelular"];
        //             $cliFechaNacimiento   = $arrayUser["cliFechaNacimiento"];
        //             $venDCodigo           = $arrayUser["venDCodigo"];
        //             $cliTitulo            = $arrayUser["cliTitulo"];
        //             //edito
        //             $form_data = [
        //                 'cliCi'                 => $cedula,
        //                 'cliApellidos'          => $apellidos,
        //                 'cliNombres'            => $nombres,
        //                 'cliDireccion'          => $cliDireccion,
        //                 'cliTelefono'           => $telefono,
        //                 'cliEmail'              => $email,
        //                 'cliCredito'            => $cliCredito,
        //                 'cliPlazo'              => $cliPlazo,
        //                 'cliAlias'              => $cliAlias,
        //                 'cliCelular'            => $cliCelular,
        //                 'cliFechaNacimiento'    => $cliFechaNacimiento,
        //                 'venDCodigo'            => $venDCodigo,
        //                 'cliTitulo'             => $cliTitulo
        //             ];
        //             //return $form_data;
        //             $dato = Http::post("http://186.4.218.168:9095/api/f_Cliente", $form_data);
        //             $prueba_post = json_decode($dato, true);
        //         }else{
        //             //no se encontro lo creo
        //             $form_data = [
        //                 'cli_ci'        => $cedula,
        //                 'cli_apellidos' => $apellidos,
        //                 'cli_nombres'   => $nombres,
        //                 'cli_direccion' => null,
        //                 'cli_telefono'  => $telefono,
        //                 'cli_email'     => $email
        //             ];
        //             $client = Http::post('http://186.4.218.168:9095/api/Cliente', $form_data);
        //             $JsonCliente = json_decode($client, true);
        //         }
        //     } catch (\Exception  $ex) {
        //     return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        //     }
        // }
        //====Fin Crear cedula en facturacion si no existe====
        //validar que el pedido no este con contrato
        $validate = DB::SELECT("SELECT * FROM pedidos
        WHERE id_pedido = '$request->id_pedido'
        AND (contrato_generado IS NULL OR contrato_generado = '')
        ");
        // if(empty($validate)){return response()->json(['pedido' => '', 'error' => "Error no se puedo modificar un pedido que ya tiene contrato"]);}
        $concontrato = 0;
        if(empty($validate)){
            $concontrato = 1;
            // return response()->json(['pedido' => '', 'error' => "Error no se puedo modificar un pedido que ya tiene contrato"]);
        }
        //validar que el pedido no este anulado
        $validate2 = DB::SELECT("SELECT * FROM pedidos
        WHERE id_pedido = '$request->id_pedido'
        AND (estado = '0' OR estado = '1')
        ");
        if(empty($validate2)){return response()->json(['pedido' => '', 'error' => "Error no se puedo modificar un pedido que esta anulado"]);}

        // if( count($verif_cli_ins_cod) == 0){
        //     // SE GENERA EL CLI INS CODIGO EN BASE DE MILTON
        //     $form_data = [
        //         'cli_ci'       => $docente[0]->cedula,
        //         'ins_codigo'   => intval($institucion[0]->codigo_institucion_milton),
        //         'ven_d_codigo' => $asesor[0]->iniciales,
        //     ];
        //     $cliente_escuela = Http::post('http://186.4.218.168:9095/api/ClienteEscuela', $form_data);
        //     $json_cliente_escuela = json_decode($cliente_escuela, true);
        //     if( $json_cliente_escuela ){
        //     // SE GUARDA EN BASE PROLIPA EL CLI INS CODIGO GENERADO
        //         DB::INSERT("INSERT INTO `pedidos_asesor_institucion_docente`(`cli_ins_codigo`, `id_asesor`, `id_institucion`, `id_docente`) VALUES (?,?,?,?)", [$json_cliente_escuela['cli_ins_codigo'], $asesor[0]->iniciales, $institucion[0]->codigo_institucion_milton, $docente[0]->cedula]);
        //     }else{
        //         return response()->json(['pedido' => '', 'error' => "No se pudo generar el cli_ins_codigo, comuníquese con soporte. Datos enviados, cedula: ".$docente[0]->cedula." ins_codigo: ". intval($institucion[0]->codigo_institucion_milton) . " vendedor: " . $asesor[0]->iniciales]);
        //     }
        // }
        if( $request->id_beneficiario > 0){
            //usuario
            $usuario = Usuario::findOrFail($request->idusuario);
            $usuario->nombres   = $request->nombres;
            $usuario->apellidos = $request->apellidos;
            $usuario->email     = $request->correo;
            $usuario->save();
            //if($concontrato == 0){
                //usuario
                // $usuario = Usuario::findOrFail($request->idusuario);
                // $usuario->nombres   = $request->nombres;
                // $usuario->apellidos = $request->apellidos;
                // $usuario->save();
            //}
            //beneficiario
            $beneficiario = Beneficiarios::find($request->id_beneficiario);
        }else{
            $beneficiario = new Beneficiarios();
        }
        $beneficiario->id_pedido = $request->id_pedido;
        $beneficiario->id_usuario = $request->idusuario;
        $beneficiario->tipo_identificacion = $request->tipo_identificacion;
        $beneficiario->direccion = $request->direccion;
        $beneficiario->comision = $request->comision;
        //banco
        if($request->banco == null || $request->banco == "null" || $request->banco == ""){
            $beneficiario->banco = null;
        }else{
            $beneficiario->banco = $request->banco;
        }
        //tipo de cuenta
        if($request->tipo_cuenta == null || $request->tipo_cuenta == "null" || $request->tipo_cuenta == ""){
            $beneficiario->tipo_cuenta = null;
        }else{
            $beneficiario->tipo_cuenta = $request->tipo_cuenta;
        }
        //num_cuenta
        if($request->num_cuenta == null || $request->num_cuenta == "null" || $request->num_cuenta == ""){
            $beneficiario->num_cuenta = null;
        }else{
            $beneficiario->num_cuenta = $request->num_cuenta;
        }
        //observacion
        if($request->observacion == null || $request->observacion == "null" || $request->observacion == ""){
            $beneficiario->observacion = null;
        }else{
            $beneficiario->observacion = $request->observacion;
        }
        $beneficiario->correo = $request->correo;
        $beneficiario->valor = $request->valor;
        $beneficiario->save();
        //obtener los beneficiarios del pedidos
        $query = DB::SELECT("SELECT * FROM pedidos_beneficiarios b
        WHERE id_pedido = '$request->id_pedido'
        ");
        //si hay beneficiarios uso el primero
        if(count($query) > 0){
            $primerBeneficiario = $query[0]->id_usuario;
            //actualizar responsable primer beneficiario
            DB::UPDATE("UPDATE pedidos SET id_responsable  = '$primerBeneficiario' WHERE id_pedido = '$request->id_pedido'");
        }
        //Actualizar fecha creacion del pedido
        if($request->id_group == 11){
            $this->UpdateFechaCreacionPedido($request->id_pedido);
        }
        return $beneficiario;
    }
    public function eliminar_beneficiario_pedido(Request $request){
        //validate si tiene contrato
        // $query = DB::SELECT("SELECT * FROM pedidos where id_pedido = '$request->id_pedido' AND contrato_generado  IS NOT NULL");
        // if(count($query)){
        //     return ["status" => "0", "message" => "No se puede eliminar un beneficiarios con contrato"];
        // }
        DB::SELECT("DELETE FROM `pedidos_beneficiarios` WHERE `id_beneficiario_pedido` = $request->id_beneficiario");
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
                $benef = Http::post('http://186.4.218.168:9095/api/beneficiario', $form_data);
                return $benef;
            } catch (\Throwable $th) {
                dump($th);
            }
        }
    }
    public function cargar_codigos_vendedores(){
        $vendedores = Http::get('http://186.4.218.168:9095/api/vendedor');
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
        $usuarios = Http::get('http://186.4.218.168:9095/api/usuario');
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
    public function cargar_codigo_institucion1(){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $escuelas = Http::get('http://186.4.218.168:9095/api/Escuela');
        $json_escuelas = json_decode($escuelas, true);
        return  $json_escuelas;
    }
    public function cargar_codigo_institucion(){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $escuelas = Http::get('http://186.4.218.168:9095/api/Escuela');
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
        $ciudades = Http::get('http://186.4.218.168:9095/api/Ciudad');
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
                $institucion = Http::post('http://186.4.218.168:9095/api/Escuela', $form_data);
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
    public function entregarPedido(Request $request){
        $fechaActual = date('Y-m-d H:i:s');
        //tipo 0 = solicitud; 1 = devolucion;
        if($request->devolverGuias){
           return $this->actualizarStockProlipa($request->acta,$request->asesor_id,1);
        }
        $pedido = Pedidos::find($request->id_pedido);
        $pedido->estado_entrega = $request->estado;
        //GUIAS
        if($request->grupo == 'facturacion'){
            $pedido->fecha_aprobado_facturacion = $fechaActual;
        }
        if($request->grupo == 'bodega'){
            $this->actualizarStockProlipa($request->acta,$request->asesor_id,0);
            $pedido->fecha_entrega_bodega = $fechaActual;
        }
        //PEDIDO
        if($request->entregaPedidoBodega){
            $pedido->fecha_entrega_bodega = $fechaActual;
        }
        $pedido->save();
        return $pedido;
    }
    public function guardarPedidoGuias(Request $request){
        if( $request->id_pedido > 0 ){
            $pedido = Pedidos::find($request->id_pedido);
        }else{
            $pedido = new Pedidos();
        }
        $pedido->fecha_envio            = $request->fecha_envio;
        $pedido->id_periodo             = $request->periodo;
        $pedido->id_asesor              = $request->id_asesor; //asesor/vendedor
        $pedido->id_responsable         = $request->id_asesor;
        $pedido->observacion            = $request->observacion;
        $pedido->id_usuario_verif       = 0; //$request->id_usuario_verif; //facturador se guarda al generar el pedido
        $pedido->tipo                   = 1;
        $pedido->save();
        return response()->json(['pedido' => $pedido, 'error' => ""]);
    }
    //api:post//guardarContratoBdMilton
    public function guardarContratoBdMilton(Request $request){
        //variables
        $fecha_formato      = date('Y-m-d');
        $codigo_ven         = $request->contrato_generado;
        $verificador        = $request->cod_usuario_verif;
        $iniciales          = $request->iniciales;
        $tipo_venta         = $request->tipo_venta;
        $total_venta        = $request->total_venta;
        $region_idregion    = $request->region_idregion;
        $descuento          = $request->descuento;
        $cedulaAsesor       = $request->cedula;
        $id_responsable     = $request->id_responsable;
        $institucion        = $request->id_institucion;
        $asesor_id          = $request->id_asesor;
        $asesor             = $request->asesor;
        $temporada          = substr($request->codigo_contrato,0,1);
        $periodo            = $request->id_periodo;
        $ciudad             = $request->nombre_ciudad;
        $nombreInstitucion  = $request->nombreInstitucion;
        //fin variables
        $observacion = null;
        if($request->observacion == null || $request->observacion == "" || $request->observacion == "null"){
            $observacion        = null;
        }else{
            $observacion        = $request->observacion;
        }
        $setAnticipo = 0;
        if($request->anticipo_aprobado == null || $request->anticipo_aprobado == ""){
            $setAnticipo = 0;
        }else{
            $setAnticipo = $request->anticipo_aprobado;
        }
        $setNumCuenta = 0;
        if($request->num_cuenta == null || $request->num_cuenta == "" || $request->num_cuenta == "null"){
            $setNumCuenta = 0;
        }else{
            $setNumCuenta = $request->num_cuenta;
        }
        //OBTENER EL DOCENTE
        $docente = DB::SELECT("SELECT u.cedula,  CONCAT(u.nombres, ' ', u.apellidos) AS docente FROM  usuario u WHERE `idusuario` = ?", [$id_responsable]);
        $nombreDocente      = $docente[0]->docente;
        $cedulaDocente      = $docente[0]->cedula;
        //cli ins codigo
        $cli_ins_cod = DB::SELECT("SELECT * FROM `pedidos_asesor_institucion_docente`
        WHERE `id_asesor` = ? AND `id_institucion` = ?
        AND `id_docente` = ?", [$iniciales,
        $request->codigo_institucion_milton, $docente[0]->cedula]);
        if(empty($cli_ins_cod)){
            return ["status" => "0","message" => "No existe el ins cliente codigo"];
        }
        if (strlen($observacion) > 500) {
            $cadenaRecortada = substr($observacion, 0, 500); // Recorta la cadena a 500 caracteres
        } else {
            $cadenaRecortada = $observacion; // Si la cadena original tiene 500 caracteres o menos, se asigna tal cual
        }
        //GUARDAR VENTA
        $form_data = [
            'veN_CODIGO'            => $codigo_ven, //codigo formato milton
            'usU_CODIGO'            => strval($verificador),
            'veN_D_CODIGO'          => $iniciales, // codigo del asesor
            'clI_INS_CODIGO'        => floatval($cli_ins_cod[0]->cli_ins_codigo),
            'tiP_veN_CODIGO'        => intval($tipo_venta),
            'esT_veN_CODIGO'        => 2, // por defecto
            'veN_OBSERVACION'       => $cadenaRecortada,
            'veN_VALOR'             => floatval($total_venta),
            'veN_PAGADO'            => 0.00, // por defecto
            'veN_ANTICIPO'          => floatval($setAnticipo),
            'veN_DESCUENTO'         => floatval($descuento),
            'veN_FECHA'             => $fecha_formato,
            'veN_CONVERTIDO'        => '', // por defecto
            'veN_TRANSPORTE'        => 0.00, // por defecto
            'veN_ESTADO_TRANSPORTE' => false, // por defecto
            'veN_FIRMADO'           => 'DS', // por defecto
            'veN_TEMPORADA'         => $region_idregion == 1 ? 0 :1 ,
            'cueN_NUMERO'           => strval($setNumCuenta)
        ];
        // return $form_data;
        //guardar en la tabla de temporadas
        $this->guardarContratoTemporada($codigo_ven,$institucion,$asesor_id,$temporada,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion);
        try {
            $contrato = Http::post('http://186.4.218.168:9095/api/Contrato', $form_data);
            $json_contrato = json_decode($contrato, true);
        } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }

        //DETALLE DE VENTA
        $detalleVenta = $this->get_val_pedidoInfo($request->id_pedido);
        //Si no hay nada en detalle de venta
        if(empty($detalleVenta)){
            return ["status" => "0", "message" => "No hay ningun libro para el detalle de venta"];
        }
        $iva = 0;
        $descontar =0;
        for($i =0; $i< count($detalleVenta);$i++){
            $form_data_detalleVenta = [
                "VEN_CODIGO"            => $codigo_ven,
                "PRO_CODIGO"            => $detalleVenta[$i]["codigo_liquidacion"],
                "DET_VEN_CANTIDAD"      =>  intval($detalleVenta[$i]["valor"]),
                "DET_VEN_VALOR_U"       => floatval($detalleVenta[$i]["precio"]),
                "DET_VEN_IVA"           => floatval($iva),
                "DET_VEN_DESCONTAR"     => intval($descontar),
                "DET_VEN_INICIO"        => false,
                "DET_VEN_CANTIDAD_REAL" => intval($detalleVenta[$i]["valor"]),
            ];
            $detalle = Http::post('http://186.4.218.168:9095/api/DetalleVenta', $form_data_detalleVenta);
        $json_detalle = json_decode($detalle, true);
        }
        return $json_contrato;
        // //actualizar en pedidos que envio a la bd de milton
        // DB::UPDATE("UPDATE pedidos SET enviarMilton = '1' WHERE id_pedido = '$request->id_pedido' ");
        // return response()->json(['json_contrato' => $json_contrato, 'form_data' => $form_data]);
    }
    public function getBeneficiariosXPedido($id_pedido){
        $query              = $this->getAllBeneficiarios($id_pedido);
        return $query;
    }
    public function getBeneficiarios($id_pedido,$tipo,$idVerificacion){
        //tipo => VENTA REAL GENERAL; 1 => VENTA REAL POR VERIFICACION
        $pedido     = $this->getPedido(0,$id_pedido);
        $query      = $this->getAllBeneficiarios($id_pedido);
        $datos = [];
        if(empty($query)){
            return $query;
        }
        $contrato = $query[0]->contrato_generado;
        $ventaReal = 0;
        $comisionReal = 0;
        //si no hay valores no hago nada
        if($contrato == null || $contrato == "null" || $contrato == "" ){
        }
        //si hay contrato traigo la venta real -
        else{
            try {
                $comisionReal       = $pedido[0]->descuento;
                 //tipo => 0 => VENTA REAL GENERAL; 1 => VENTA REAL POR VERIFICACION
                if($tipo == 1 ){
                    $getVentaReal       = Verificacion::findOrFail($idVerificacion);
                    $ventaReal          = $getVentaReal->venta_real;
                }else{
                    $verificaciones     = $this->getVerificaciones($contrato);

                    foreach($verificaciones as $key => $item){
                        $ventaReal      = $ventaReal + $item->venta_real;
                    }
                }
            } catch (\Exception  $ex) {
                return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
            }
        }
        foreach($query as $key => $item){
            //total convenio
            $query2 = DB::SELECT("SELECT SUM(a.venta_bruta) AS total_alcance
            FROM pedidos_alcance a
            WHERE a.id_pedido = '$id_pedido'
            and a.estado_alcance = '1'
            and a.venta_bruta > 0
            ");
            $datos[$key] = [
                "id_beneficiario_pedido"    => $item->id_beneficiario_pedido,
                "id_pedido"                 => $item->id_pedido,
                "id_usuario"                => $item->id_usuario,
                "cod_usuario"               => $item->cod_usuario,
                "tipo_identificacion"       => $item->tipo_identificacion,
                "direccion"                 => $item->direccion,
                "correo"                    => $item->correo,
                "comision"                  => $item->comision,
                "valor"                     => $item->valor,
                "banco"                     => $item->banco,
                "tipo_cuenta"               => $item->tipo_cuenta,
                "num_cuenta"                => $item->num_cuenta,
                "observacion"               => $item->observacion,
                "created_at"                => $item->created_at,
                "updated_at"                => $item->updated_at,
                "beneficiario"              => $item->beneficiario,
                "cedula"                    => $item->cedula,
                "nombres"                   => $item->nombres,
                "apellidos"                 => $item->apellidos,
                "descuento"                 => $item->descuento,
                "total_venta"               => $item->total_venta,
                "ventaReal"                 => $ventaReal,
                "comisionReal"              => $comisionReal,
                "valorComisionReal"         => ($ventaReal * $comisionReal)/100,
                "total_alcances"            => $query2[0]->total_alcance  == null ? 0: $query2[0]->total_alcance
            ];
        }
        return $datos;
    }
    public function generar_contrato_pedido($id_pedido, $usuario_fact){
        //limpiar cache
        Cache::flush();
        $validateBeneficiarios = $this->getAllBeneficiarios($id_pedido);
        if(empty($validateBeneficiarios)){
            return ["status" => "0", "message" => "Seleccione algun beneficiario para poder guardar"];
        }
        $pedido = DB::SELECT("SELECT p.*, pe.codigo_contrato, u.iniciales,u.cli_ins_codigo,
        i.codigo_institucion_milton, pe.region_idregion,
        CONCAT(u.nombres, ' ', u.apellidos) AS asesor,u.cedula,i.nombreInstitucion,
        (
            SELECT c.nombre
            FROM institucion iss
            LEFT JOIN ciudad c ON iss.ciudad_id = c.idciudad
            WHERE iss.idInstitucion = i.idInstitucion
        ) AS ciudad
        FROM pedidos p, periodoescolar pe, usuario u, institucion i
        WHERE p.id_periodo = pe.idperiodoescolar
        AND p.id_asesor = u.idusuario
        AND p.id_institucion = i.idInstitucion
        AND `id_pedido` = $id_pedido");
        $usuario_verifica = DB::SELECT("SELECT * FROM `usuario` WHERE `idusuario` = ?", [$usuario_fact]);
        $docente = DB::SELECT("SELECT u.cedula,  CONCAT(u.nombres, ' ', u.apellidos) AS docente FROM  usuario u WHERE `idusuario` = ?", [$pedido[0]->id_responsable]);
        // $observacion = DB::SELECT("SELECT * FROM `pedidos_comentarios` WHERE `id_pedido` = $id_pedido ORDER BY `id` DESC;");
        $comentario             = '';
        $comentario             = $pedido[0]->observacion;
        $institucion            = $pedido[0]->id_institucion;
        $asesor_id              = $pedido[0]->id_asesor;
        $asesor                 = $pedido[0]->asesor;
        $cedulaAsesor           = $pedido[0]->cedula;
        $temporada              = substr($pedido[0]->codigo_contrato,0,1);
        $periodo                = $pedido[0]->id_periodo;
        $ciudad                 = $pedido[0]->ciudad;
        $iniciales              = $pedido[0]->iniciales;
        $cli_ins_codigoAsesor   = $pedido[0]->cli_ins_codigo;
        $codigo_contrato        = $pedido[0]->codigo_contrato;
        $nombreInstitucion      = $pedido[0]->nombreInstitucion;
        $convenio_anios         = $pedido[0]->convenio_anios;
        $fechaActual = date('Y-m-d H:i:s');
        $setAnticipo = 0;
        //variables del docente beneficiarios
        $nombreDocente      = $docente[0]->docente;
        $cedulaDocente      = $docente[0]->cedula;
        if($pedido[0]->anticipo_aprobado == null || $pedido[0]->anticipo_aprobado == ""){
            $setAnticipo = 0;
        }else{
            $setAnticipo = $pedido[0]->anticipo_aprobado;
        }
        $setNumCuenta = 0;
        if($pedido[0]->num_cuenta == null || $pedido[0]->num_cuenta == ""){
            $setNumCuenta = 0;
        }else{
            $setNumCuenta = $pedido[0]->num_cuenta;
        }
        ///obtener la secuencia
        $getSecuencia = DB::SELECT("SELECT * FROM pedidos_secuencia ps
        WHERE ps.asesor_id = '$asesor_id'
        AND ps.sec_ven_nombre = '$codigo_contrato'
        ");
        $secuencia = 1;
        //vacio seria la primera secuencia
        if(empty($getSecuencia)){
            $secuencia  = 1;
        }else{
            $idSecuencia            = $getSecuencia[0]->id;
            $secuencia              = $getSecuencia[0]->sec_ven_valor + 1;
            //editar de secuencia
        }
        if( $secuencia < 10 ){
            $format_id_pedido = '000000' . $secuencia;
        }
        if( $secuencia >= 10 && $secuencia < 1000 ){
            $format_id_pedido = '00000' . $secuencia;
        }
        if( $secuencia > 1000 ){
            $format_id_pedido = '0000' . $secuencia;
        }
        $codigo_ven = 'C-' . $pedido[0]->codigo_contrato . '-' . $format_id_pedido . '-' . $pedido[0]->iniciales;
        $fecha_formato = date('Y-m-d');
        // if( !$pedido[0]->cli_ins_codigo ){
        //     return response()->json(['json_contrato' => '', 'form_data' => '', 'error' => 'Falta el cliente institucion id para solicitar guias']);
        // }
        if( !$pedido[0]->codigo_contrato ){
            return response()->json(['json_contrato' => '', 'form_data' => '', 'error' => 'Falta el código del periodo']);
        }
        // if( !$usuario_verifica[0]->cod_usuario ){
        //     return response()->json(['json_contrato' => '', 'form_data' => '', 'error' => 'Falta el código del usuario facturador']);
        // }
        $cli_ins_cod = DB::SELECT("SELECT * FROM `pedidos_asesor_institucion_docente`
        WHERE `id_asesor` = ? AND `id_institucion` = ?
        AND `id_docente` = ?", [$pedido[0]->iniciales,
        $pedido[0]->codigo_institucion_milton, $docente[0]->cedula]);
        if (strlen($comentario) > 500) {
            $cadenaRecortada = substr($comentario, 0, 500); // Recorta la cadena a 500 caracteres
        } else {
            $cadenaRecortada = $comentario; // Si la cadena original tiene 500 caracteres o menos, se asigna tal cual
        }
        // $form_data = [
        //     'veN_CODIGO' => $codigo_ven, //codigo formato milton
        //     'usU_CODIGO' => strval($usuario_verifica[0]->cod_usuario),
        //     'veN_D_CODIGO' => $pedido[0]->iniciales, // codigo del asesor
        //     'clI_INS_CODIGO' => floatval($cli_ins_cod[0]->cli_ins_codigo),
        //     'tiP_veN_CODIGO' => $pedido[0]->tipo_venta,
        //     'esT_veN_CODIGO' => 2, // por defecto
        //     'veN_OBSERVACION' => $cadenaRecortada,
        //     'veN_VALOR' => $pedido[0]->total_venta,
        //     'veN_PAGADO' => 0.00, // por defecto
        //     'veN_ANTICIPO' => $setAnticipo,
        //     'veN_DESCUENTO' => $pedido[0]->descuento,
        //     'veN_FECHA' => $fecha_formato,
        //     'veN_CONVERTIDO' => '', // por defecto
        //     'veN_TRANSPORTE' => 0.00, // por defecto
        //     'veN_ESTADO_TRANSPORTE' => false, // por defecto
        //     'veN_FIRMADO' => 'DS', // por defecto
        //     'veN_TEMPORADA' => $pedido[0]->region_idregion == 1 ? 0 :1 ,
        //     'cueN_NUMERO' => strval($setNumCuenta)
        // ];
        //guardar en la tabla de temporadas
        $this->guardarContratoTemporada($codigo_ven,$institucion,$asesor_id,$temporada,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion);
        // try {
        //     $contrato = Http::post('http://186.4.218.168:9095/api/Contrato', $form_data);
        //     $json_contrato = json_decode($contrato, true);
        //  } catch (\Exception  $ex) {
        //     return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        // }
         //GUARDAR DETALLE DE VENTA
        //DETALLE DE VENTA
        $detalleVenta = $this->get_val_pedidoInfo($id_pedido);
        //Si no hay nada en detalle de venta
        if(empty($detalleVenta)){
            return ["status" => "0", "message" => "No hay ningun libro para el detalle de venta"];
        }
        // $iva = 0;
        // $descontar =0;
        // try {
        // for($i =0; $i< count($detalleVenta);$i++){
        //     $form_data_detalleVenta = [
        //         "VEN_CODIGO"            => $codigo_ven,
        //         "PRO_CODIGO"            => $detalleVenta[$i]["codigo_liquidacion"],
        //         "DET_VEN_CANTIDAD"      => intval($detalleVenta[$i]["valor"]),
        //         "DET_VEN_VALOR_U"       => floatval($detalleVenta[$i]["precio"]),
        //         "DET_VEN_IVA"           => floatval($iva),
        //         "DET_VEN_DESCONTAR"     => intval($descontar),
        //         "DET_VEN_INICIO"        => false,
        //         "DET_VEN_CANTIDAD_REAL" => intval($detalleVenta[$i]["valor"]),
        //     ];
        //     $detalle = Http::post('http://186.4.218.168:9095/api/DetalleVenta', $form_data_detalleVenta);
        //     $json_detalle = json_decode($detalle, true);
        // }
        // } catch (\Exception  $ex) {
        //     return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        // }
        //FIN GUARDAR DETALLE DE VENTA
        //si se guardo el contrato actualizo la secuencia
        if(empty($getSecuencia)){
            $sec = new PedidosSecuencia();
        }else{
            //editar de secuencia
            $sec                        = PedidosSecuencia::findOrFail($idSecuencia);
            $sec->asesor_id             = $asesor_id;
        }
        //guardar
            $sec->sec_ven_nombre        = $codigo_contrato;
            $sec->sec_ven_valor         = $secuencia;
            $sec->ven_d_codigo          = $iniciales;
            $sec->asesor_id             = $asesor_id;
            $sec->id_periodo            = $periodo;
            $sec->cli_ins_codigo        = $cli_ins_codigoAsesor;
            $sec->save();
        //ACTUALIZAR CONTRATO Y FECHA CREACION CONTRATO
        $query = "UPDATE `pedidos` SET `contrato_generado` = '$codigo_ven', `id_usuario_verif` = $usuario_fact,`fecha_generacion_contrato` = '$fechaActual' WHERE `id_pedido` = $id_pedido;";
        DB::SELECT($query);
        //ACTUALIZAR EN EL HISTORICO
        $queryHistorico = "UPDATE `pedidos_historico` SET `fecha_generar_contrato` = '$fechaActual', `estado` = '2' WHERE `id_pedido` = $id_pedido;";
        DB::UPDATE($queryHistorico);
        //ASIGNAR A TABLA CONVENIO HIJOS
        if($convenio_anios > 0){
             $getConvenio = $this->asignarToConvenio($institucion,$id_pedido,$codigo_ven,$convenio_anios,$periodo);
             $getConvenio;
        }
        //DEJAR NOTIFICACION REVIVISION A CERO
        $this->changeRevisonNotificacion($id_pedido,0);
        //COLOCAR CONTRATO A LAS PAGOS QUE NO TIENEN
        $this->asignarContratoToPagos($institucion,$periodo,$codigo_ven);
        return $codigo_ven;
        // return response()->json(['json_contrato' => $json_contrato, 'form_data' => $form_data]);
    }
    public function asignarContratoToPagos($institucion,$periodo,$codigo_ven){
        $query = $this->pagoRepository->getPagosSinContrato($institucion,$periodo);
        if(count($query) > 0){
            foreach($query as $key => $item){
                DB::table('1_4_documento_liq')
                ->where('doc_codigo',$item->doc_codigo)
                ->update([
                    'ven_codigo' => $codigo_ven
                ]);
            }
        }
    }

    public function asignarToConvenio($institucion,$id_pedido,$contrato,$convenio_anios,$periodo){
        $getConvenio = $this->getConvenioInstitucion($institucion);
        //asigno cuando hay convneio
        if(!empty($getConvenio)){
           $this->convenioRepository->registrarConvenioHijo($id_pedido,$getConvenio[0]->id,$contrato,$institucion,$periodo);
        }
        //si no hay un registro de convenio el asesor marco el convenio
        else{
            if($convenio_anios > 1){
                $global = new PedidoConvenio;
                $global->anticipo_global = 0;
                $global->convenio_anios  = $convenio_anios;
                $global->institucion_id  = $institucion;
                $global->periodo_id      = $periodo;
                $global->id_pedido       = $id_pedido;
                $global->user_created    = 0;
                $global->observacion     = null;
                $global->save();
                $this->convenioRepository->registrarConvenioHijo($id_pedido,$global->id,$contrato,$institucion,$periodo);
            }
        }
    }
    public function guardarContratoTemporada($contrato,$institucion,$asesor_id,$temporadas,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion){
        //validar que el contrato no existe
        $validate = DB::SELECT("SELECT * FROM temporadas t
        WHERE t.contrato = '$contrato'
        ");
        if(empty($validate)){
            $temporada = new Temporada();
            $temporada->contrato                = $contrato;
            $temporada->year                    = date("Y");
            $temporada->ciudad                  = $ciudad;
            $temporada->temporada               = $temporadas;
            $temporada->id_asesor               = $asesor_id;
            $temporada->cedula_asesor           = 0;
            $temporada->id_periodo              = $periodo;
            $temporada->id_profesor             = "0";
            $temporada->idInstitucion           = $institucion;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }else{
            $id_temporada                       = $validate[0]->id_temporada;
            $temporada                          = Temporada::findOrFail($id_temporada);
            $temporada->id_periodo              = $periodo;
            $temporada->idInstitucion           = $institucion;
            $temporada->id_asesor               = $asesor_id;
            $temporada->temporal_nombre_docente = $nombreDocente;
            $temporada->temporal_cedula_docente = $cedulaDocente;
            $temporada->temporal_institucion    = $nombreInstitucion;
            $temporada->nombre_asesor           = $asesor;
            $temporada->cedula_asesor           = $cedulaAsesor;
            $temporada->save();
            return $temporada;
        }
    }
    //api para cambiar el porcentja de anticipo
    //api:Get>>/changePorcentajeAnticipo
    public function changePorcentajeAnticipo(Request $request){
        DB::UPDATE("UPDATE periodoescolar set porcentaje_descuento = '$request->porcentaje_pedido'
            WHERE idperiodoescolar = '$request->id_periodo'
        ");
    }
    //APIS GET
    public function cargarClientesMilton(){ /// base de milton
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $consulta = Http::get('http://186.4.218.168:9095/api/Cliente');
        $jsonconsulta = json_decode($consulta, true);
        return $jsonconsulta;
    }
    public function cargarVendedoresMilton(){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $consulta = Http::get('http://186.4.218.168:9095/api/Vendedor');
        $jsonconsulta = json_decode($consulta, true);
        return $jsonconsulta;
    }
    //========METODOS DE HISTORICO DE CONTRATOS======
    //en este metodo se van a generar en la tabla historico el pedido
    public function pedidosConAnticipo(){
        $consulta = DB::SELECT("SELECT  p.*
        FROM pedidos p
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE p.ifanticipo = '1'
        AND pe.estado = '1'
        AND p.estado = '1'
        ");
        foreach($consulta as $key => $item){
            //validar que no ya no este creado el historico
            $validate = DB::SELECT("SELECT * FROM pedidos_historico ph
            WHERE ph.id_pedido ='$item->id_pedido'
            AND ph.tipo_pago ='0'
            ");
            if(empty($validate)){
                $historico = new PedidosHistorico();
                $historico->periodo_id              = $item->id_periodo;
                $historico->id_pedido               = $item->id_pedido;
                $historico->estado                  = 0;
                $historico->tipo_pago               = 0;
                $historico->fecha_creacion_pedido   = $item->fecha_creacion_pedido;
                $historico->save();
            }
        }
    }
    public function cambiarEstadoHistorico(Request $request){
        $fechaActual = "";
        if($request->fromDate){
            $fechaActual = $request->fromDate;
        }else{
            $fechaActual = date('Y-m-d H:i:s');
        }
        DB::UPDATE("UPDATE pedidos_historico
        SET `$request->campo` = '$fechaActual',
         `estado` = '$request->estado'
         WHERE `id_pedido` = '$request->id_pedido'
         ");
    }
    //CRUD PEDIDOS SECUENCIA
    public function getPedidoSecuencia( $id )
    {
        $dato = DB::table('pedidos_secuencia as ps')
        ->leftjoin('usuario as u', 'ps.asesor_id','=','u.idusuario')
        ->where('id_periodo',$id)
        ->select('ps.*','u.nombres','u.apellidos','u.idusuario as usu_id')
        ->get();
        return $dato;
    }
    public function storePedidoSecuencia(Request $request)
    {
        if($request->id >0){
            $dato               = PedidosSecuencia::find($request->id);
        }else{
            $dato               = new PedidosSecuencia();
        }
        $dato->asesor_id        = $request->asesor_id;
        $dato->sec_ven_nombre   = $request->sec_ven_nombre;
        $dato->id_periodo       = $request->id_periodo;
        $dato->sec_ven_valor    = $request->sec_ven_valor;
        $dato->ven_d_codigo     = $request->ven_d_codigo;
        $dato->cli_ins_codigo   = $request->cli_ins_codigo;
        $dato->save();
        return $dato;
    }
    public function deletePedidoSecuencia($id)
    {
        if($id >0){
            $dato =PedidosSecuencia::find($id);
            $dato->delete();
            return $dato;
        }
    }
    //FIN CRUD PEDIDOS SECUENCIA
    //APIS CONTABILIDAD
    public function getPedidosContabilidad(Request $request){

        $pedidos = DB::SELECT("SELECT p.id_pedido,p.imagen,p.doc_ruc,p.anticipo_aprobado,
        CONCAT(u.nombres,' ',u.apellidos) as responsable, u.cedula,
        i.nombreInstitucion, c.nombre AS nombre_ciudad,ph.fecha_aprobacion_anticipo_gerencia,
        ph.fecha_generar_contrato,ph.evidencia_pagare,ph.evidencia_cheque,
        ph.evidencia_cheque_sin_firmar,ph.estado as estadoHistorico
        FROM pedidos  p
        LEFT  JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE ph.estado = '$request->estado'
        AND p.anticipo_aprobado > 0
        AND pe.estado = '1'
        ORDER BY p.id_pedido DESC
        ");
        $datos = [];
        foreach($pedidos as $key => $item){
            $query = DB::SELECT("SELECT CONCAT(u.nombres,' ' ,u.apellidos) AS beneficiario,
            u.cedula
            FROM pedidos_beneficiarios b
            LEFT JOIN usuario u ON b.id_usuario = u.idusuario
            WHERE b.id_pedido = '$item->id_pedido'
            ");
            $files = DB::SELECT("SELECT * FROM pedidos_files pf
            WHERE pf.id_pedido = '$item->id_pedido'
            ");
            $datos[$key] = [
                "id_pedido"                             => $item->id_pedido,
                "imagen"                                => $item->imagen,
                "doc_ruc"                               => $item->doc_ruc,
                "anticipo_aprobado"                     => $item->anticipo_aprobado,
                "responsable"                           => $item->responsable,
                "cedula"                                => $item->cedula,
                "nombreInstitucion"                     => $item->nombreInstitucion,
                "nombre_ciudad"                         => $item->nombre_ciudad,
                "fecha_aprobacion_anticipo_gerencia"    => $item->fecha_aprobacion_anticipo_gerencia,
                "fecha_generar_contrato"                => $item->fecha_generar_contrato,
                "evidencia_cheque"                      => $item->evidencia_cheque,
                "evidencia_cheque_sin_firmar"           => $item->evidencia_cheque_sin_firmar,
                "evidencia_pagare"                      => $item->evidencia_pagare,
                "estadoHistorico"                       => $item->estadoHistorico,
                "beneficiarios"                         => array_unique($query,SORT_REGULAR),
                "files"                                 => $files
            ];
        }
        return $datos;
    }
    //FIN APIS CONTABILIDAD
    //API GERENCIA REPORTE
    public function getPedidosGerencia(Request $request){
        $pedidos = DB::SELECT("SELECT p.contrato_generado,p.fecha_creacion_pedido as f_creacionPedido, p.id_pedido,p.imagen,p.doc_ruc,p.anticipo_aprobado,
        CONCAT(u.nombres,' ',u.apellidos) as responsable, u.cedula,
        i.nombreInstitucion, c.nombre AS nombre_ciudad,ph.*
        FROM pedidos  p
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN pedidos_historico ph ON p.id_pedido = ph.id_pedido
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        WHERE p.anticipo_aprobado > 0
        AND pe.estado = '1'
        ORDER BY p.id_pedido DESC
        ");
        return $pedidos;
    }
    public function deletePedidoGuia($id)
    {
        $dato = Pedidos::find($id);
        $dato->delete();
        return $dato;
    }
    //pedidos gerencia
    public function listaPedidosGerencia()
    {
        $dato = DB::SELECT("SELECT p.id_pedido as pedido_id,
            p.ifagregado_anticipo_aprobado,phi.*,
            u.idusuario,u.nombres,u.apellidos,p.anticipo_aprobado,p.pendiente_liquidar,
            p.anticipo_solicitud_for_gerencia,p.anticipo_solicitud_observacion,
            p.anticipo_aprobado_gerencia,i.nombreInstitucion, c.nombre AS nombre_ciudad,
            p.fecha_creacion_pedido as fechaCreacionPedido,p.anticipo as anticipo_sugerido,
            p.convenio_anios,p.observacion,pe.periodoescolar as periodo,
            p.total_venta, p.total_series_basicas,p.descuento,i.codigo_institucion_milton,
            p.contrato_generado,pe.codigo_contrato,p.id_institucion
            FROM pedidos p
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            LEFT JOIN pedidos_historico phi ON p.id_pedido = phi.id_pedido
            LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            WHERE p.ifagregado_anticipo_aprobado = '2'
            AND ifanticipo = '1'
            -- AND pe.estado = '1'
            AND p.estado = '1'
            AND p.facturacion_vee = '1'
            AND pe.pedido_gerencia = '1'
            ORDER BY p.fecha_creacion_pedido DESC
        ");
        $datos = [];
        try {
            foreach($dato as $key => $item){
                //traer valores anteriores
                $cadena = $item->codigo_contrato;
                $nuevoCadena = substr($cadena,1);
                // $dato = Http::get("http://186.4.218.168:9095/api/f_ClienteInstitucion/Get_api3yearanteriorxinsCodigoyvenCodigo?insCodigo=".$item->codigo_institucion_milton."&venCodigo=".$nuevoCadena);
                // // $dato = Http::get("http://186.4.218.168:9095/api/f_ClienteInstitucion/Get_apipentahoxinsCodigo?insCodigo=".$item->codigo_institucion_milton);
                // $JsonDocumentos = json_decode($dato, true);
                $datos[$key] =[
                    "pedido_id"                         => $item->pedido_id,
                    "id_institucion"                    => $item->id_institucion,
                    "ifagregado_anticipo_aprobado"      => $item->ifagregado_anticipo_aprobado,
                    "id"                                => $item->id,
                    "periodo_id"                        => $item->periodo_id,
                    "id_pedido"                         => $item->id_pedido,
                    "estado"                            => $item->estado,
                    "fecha_creacion_pedido"             => $item->fecha_creacion_pedido,
                    "fecha_generar_contrato"            => $item->fecha_generar_contrato,
                    "fecha_aprobacion_anticipo_gerencia" => $item->fecha_aprobacion_anticipo_gerencia,
                    "fecha_rechazo_gerencia"            => $item->fecha_rechazo_gerencia,
                    "fecha_contabilidad_recibe"         => $item->fecha_contabilidad_recibe,
                    "fecha_contabilidad_sube_cheque_sin_firmar" => $item->fecha_contabilidad_sube_cheque_sin_firmar,
                    "fecha_subir_cheque"                => $item->fecha_subir_cheque,
                    "fecha_facturador_recibe_cheque"    => $item->fecha_facturador_recibe_cheque,
                    "fecha_envio_cheque_for_asesor"     => $item->fecha_envio_cheque_for_asesor,
                    "fecha_orden_firmada" =>            $item->fecha_orden_firmada,
                    "fecha_que_recibe_orden_firmada"    => $item->fecha_que_recibe_orden_firmada,
                    "fecha_que_recibe_orden_firmada_contabilidad" => $item->fecha_que_recibe_orden_firmada_contabilidad,
                    "tipo_pago"                         => $item->tipo_pago,
                    "evidencia_cheque_sin_firmar"       => $item->evidencia_cheque_sin_firmar,
                    "evidencia_cheque"                  => $item->evidencia_cheque,
                    "evidencia_pagare"                  => $item->evidencia_pagare,
                    "contador_anticipo"                 => $item->contador_anticipo,
                    "contador_liquidacion" =>           $item->contador_liquidacion,
                    "created_at"                        =>  $item->created_at,
                    "updated_at"                        =>  $item->updated_at,
                    "idusuario"                         => $item->idusuario,
                    "nombres"                           => $item->nombres,
                    "apellidos"                         => $item->apellidos,
                    "anticipo_aprobado"                 => $item->anticipo_aprobado,
                    "pendiente_liquidar"                => $item->pendiente_liquidar,
                    "anticipo_solicitud_for_gerencia"   => $item->anticipo_solicitud_for_gerencia,
                    "anticipo_solicitud_observacion"    => $item->anticipo_solicitud_observacion,
                    "anticipo_aprobado_gerencia"        => $item->anticipo_aprobado_gerencia,
                    "nombreInstitucion"                 => $item->nombreInstitucion,
                    "nombre_ciudad"                     => $item->nombre_ciudad,
                    "fechaCreacionPedido"               => $item->fechaCreacionPedido,
                    "anticipo_sugerido"                 => $item->anticipo_sugerido,
                    "convenio_anios"                    => $item->convenio_anios,
                    "observacion"                       => $item->observacion,
                    "periodo"                           => $item->periodo,
                    "total_venta"                       => $item->total_venta,
                    "total_series_basicas"              => $item->total_series_basicas,
                    "descuento"                         => $item->descuento,
                    "codigo_institucion_milton"         => $item->codigo_institucion_milton,
                    "contrato_generado"                 => $item->contrato_generado,
                    // "valoresAnteriores"                 => $JsonDocumentos,
                    "codigo_contrato"                   => $item->codigo_contrato
                ];
            }
            $convenios = DB::SELECT("SELECT p.*, p.id_pedido as pedido_id, co.valor_sugerido, co.observacion_sugerido, co.id AS idConvenio,
               u.idusuario,u.nombres,u.apellidos,i.nombreInstitucion, c.nombre AS nombre_ciudad,pe.periodoescolar as periodo
            FROM pedidos_convenios co
           LEFT JOIN pedidos p ON p.id_pedido = co.id_pedido
           LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
           LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
           LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
           LEFT JOIN usuario u ON p.id_asesor = u.idusuario
           WHERE co.convenio_aprobado = '1'
            AND pe.estado = '1'
             AND p.estado = '1'
            ");
            $mergedArray = array_merge($datos, $convenios);

            return $mergedArray;
        } catch (\Exception  $ex) {
        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor de facturación"];
        }
    }
    public function getPedidosAprobadosGerencia(Request $request){
        $dato = DB::SELECT("SELECT p.id_pedido as pedido_id,
            p.ifagregado_anticipo_aprobado,phi.*,
            u.idusuario,u.nombres,u.apellidos,p.anticipo_aprobado,p.pendiente_liquidar,
            p.anticipo_solicitud_for_gerencia,p.anticipo_solicitud_observacion,
            p.anticipo_aprobado_gerencia,i.nombreInstitucion, c.nombre AS nombre_ciudad,
            p.fecha_creacion_pedido as fechaCreacionPedido,p.anticipo as anticipo_sugerido,
            p.convenio_anios,p.observacion,pe.periodoescolar as periodo,
            p.total_venta, p.total_series_basicas,p.descuento,i.codigo_institucion_milton,
            p.contrato_generado,p.anticipo_aprobado_gerencia
            FROM pedidos p
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            LEFT JOIN pedidos_historico phi ON p.id_pedido = phi.id_pedido
            LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            WHERE p.ifagregado_anticipo_aprobado = '3'
            AND p.estado = '1'
            AND p.id_periodo = '$request->periodo_id'
            ORDER BY p.fecha_creacion_pedido DESC
        ");
        return $dato;
    }
    public function listaPedidosPeriodos($id)
    {
        //todos los pedidos filtrados por periodo
        $dato = DB::table('pedidos_historico as phi')
        ->leftjoin('pedidos as p','phi.id_pedido','=','p.id_pedido')
        ->leftjoin('usuario as u','p.id_asesor','=','u.idusuario')
        ->where('phi.periodo_id','=',$id)
        ->where('phi.estado','>',1)
        ->select('phi.*','u.idusuario','u.nombres','u.apellidos','p.anticipo_aprobado','p.pendiente_liquidar')
        ->get();
        return $dato;
    }
    public function aprobarPedidoGerencia(Request $request)
    {
        if($request->ifagregado_anticipo_aprobado == 2) {
         return $this->aprobarYRechazarPedido($request);
        }
        //aprobar solicitud
        if($request->op == 0){
            $dato = DB::table('pedidos')
            ->where('id_pedido',$request->pedido_id)
            ->update(['gerencia_acepta_solicitud'=> 1,'ifagregado_anticipo_aprobado' => 5,'gerencia_fecha_acepta_solicitud'=> date('Y-m-d H:i:s')]);
        }
        //rechazar solicitud
        if($request->op == 1){
            $dato = DB::table('pedidos')
            ->where('id_pedido',$request->pedido_id)
            ->update(['ifagregado_anticipo_aprobado'=> 4]);
            return 'Pedido rechazado';
        }
    }
    public function aprobarYRechazarPedido($datos){
        //aprobar
        if($datos->op == 0){
            DB::table('pedidos')
            ->where('id_pedido', $datos->pedido_id)
            ->update([
                'ifagregado_anticipo_aprobado' => 3,
                'anticipo_aprobado_gerencia'   => $datos->cantidadAprobar,
                'anticipo_aprobado'            => $datos->cantidadAprobar
            ]);
            //colocar como aprobado el pago en documento liq
            $this->aprobarAnticipoPedidoPago($datos->pedido_id,$datos->cantidadAprobar);
            //si tiene contrato guardo en facturacion
            //actualizar anticipo facturacion
            $contrato = $datos->contrato_generado;
            if($contrato == null || $contrato  == "null" || $contrato == ""){
            }else{
                // $form_data = [
                //     'venAnticipo'   => floatval($datos->cantidadAprobar),
                // ];
                // $dato = Http::post("http://186.4.218.168:9095/api/f_Venta/ActualizarVenanticipo?venCodigo=".$contrato,$form_data);
                // $prueba_get = json_decode($dato, true);
            }
            //HISTORICO ANTICIPOS
            $this->saveHistoricoAnticipos($datos);
            //HISTORICO PEDIDOS
            return $this->aprobarAnticipo($datos->pedido_id);
            return 'Pedido aprobado';
       }
       //rechazar
       if($datos->op == 1){
        DB::table('pedidos')
        ->where('id_pedido', $datos->pedido_id)
        ->update([
            'ifagregado_anticipo_aprobado' => 4
        ]);
          //HISTORICO
          $this->RechazarAnticipo($datos->pedido_id);
        return 'Pedido rechazado';
       }
    }
    //CAMBIOS IDS INSTITUCION MITLON CON VUESTRAS
    public function buscarCoincidenciaInstitucionMilton(Request $request){
        //listado de coincidencias de la tabla de milton
        $query = DB::SELECT("SELECT * FROM temp_anticipos tmp
        WHERE tmp.INSTITUCION LIKE '%$request->coincidencia%'
        ");
        if(empty($query)){
            return ["status" => "0", "message" => "No se encontraron datos"];
        }else{
            return $query;
        }
    }
    //api:post/guadarIdsMilton
    public function guadarIdsMilton(Request $request){
        $valores = explode(',',$request->codigosM);
        $cambio = Institucion::findOrFail($request->institucion_id);
        $cambio->codigo_institucion_milton = $request->valorPrimario;
        $cambio->codigo_mitlon_coincidencias = $request->codigosM;
        $cambio->save();
        // //traer el codigo institucion_cliente de base de milton
        // $getCli_Ins_Cod = DB::SELECT("SELECT * FROM temp_anticipos tmp
        // WHERE tmp.ID_INSTITUCION = '$request->valorPrimario'
        // ");
        // $CLI_INS_CODIGO = "";
        // $CLI_INS_CODIGO = $getCli_Ins_Cod[0]->CLI_INS_CODIGO;
        // //guardar codigo institucion_cliente de milton en la tabla pedidos_asesor_institucion_docente
        // $validate = DB::SELECT("SELECT * FROM pedidos_asesor_institucion_docente ci
        // WHERE cli_ins_codigo = '$CLI_INS_CODIGO'
        // ");
        // //si esta vacio en nuestra tabla lo creo
        // if(empty($validate)){
        //     $cliIns = new PedidosClienteInstitucion();
        // }else{
        //     $id = $validate[0]->id;
        //     $cliIns = PedidosClienteInstitucion::findOrFail($id);
        // }
        //     //validate si existe actualizar si fuera el caso de actualizar el asesor - iniciales
        //     $cliIns->cli_ins_codigo = $CLI_INS_CODIGO;
        //     $cliIns->id_asesor      = $request->cedulaAsesorIniciales;
        //     $cliIns->id_institucion = $request->valorPrimario;
        //     $cliIns->id_docente     = $request->cedulaAsesor;
        //     $cliIns->save();
        if($cambio){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    //api:get/getContratosPedidos
    public function getContratosPedidos(Request $request){
        // $query = DB::SELECT("SELECT p.*,
        // CONCAT(u.nombres,' ',u.apellidos) as asesor, u.cedula, u.iniciales,
        // CONCAT(uv.nombres,' ',uv.apellidos) as verificador, uv.cod_usuario,
        // i.nombreInstitucion,i.codigo_institucion_milton,
        // pe.region_idregion, c.nombre AS nombre_ciudad,pe.codigo_contrato
        // FROM pedidos p
        // LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        // LEFT  JOIN usuario u ON p.id_asesor = u.idusuario
        // LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        // LEFT  JOIN usuario uv ON p.id_usuario_verif = uv.idusuario
        // LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        // WHERE p.contrato_generado IS NOT NULL
        // AND pe.estado = '1'
        // AND p.estado = '1'
        // ORDER BY p.id_pedido DESC
        // ");
        $query = DB::SELECT("SELECT p.*,
        CONCAT(u.nombres,' ',u.apellidos) as asesor, u.cedula, u.iniciales,
        CONCAT(uv.nombres,' ',uv.apellidos) as verificador, uv.cod_usuario,
        i.nombreInstitucion,i.codigo_institucion_milton,
        pe.region_idregion, c.nombre AS nombre_ciudad,pe.codigo_contrato
        FROM pedidos p
        LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
        LEFT  JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT  JOIN usuario uv ON p.id_usuario_verif = uv.idusuario
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        WHERE p.tipo = '1'
        AND p.estado = '1'
        ORDER BY p.id_pedido DESC
        ");
        return $query;
    }
    //APIS ===MOSTRAR LO ANTICIPOS ANTERIORES
    public function mostrarAnticiposAnteriores(Request $request){
        try {
            $extractValues = explode(',',$request->codigosM);
            $temporada     = $request->temporada;
            $escuela = $extractValues[0];
            $dato = Http::get("http://186.4.218.168:9095/api/f_ClienteInstitucion/Get_api3yearanteriorxinsCodigoyvenCodigo?insCodigo=".$escuela.'&venCodigo='.$temporada);
            $JsonDocumentos = json_decode($dato, true);
            return $JsonDocumentos;
        } catch (\Exception  $ex) {
        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor de facturación"];
        }

        //  $extractValues = explode(',',$request->codigosM);
        // $datos = [];
        // foreach($extractValues as $key => $item){
        //     $query = DB::SELECT("SELECT tmp.*
        //       FROM temp_anticipos tmp
        //      WHERE tmp.ID_INSTITUCION = '$item'
        //      AND tmp.EST_VEN_CODIGO <> '3'
        //      ");
        //     $datos[$key] = $query;
        // }
        // return [
        //     "datos" => array_merge(...$datos)
        // ];

        //////////
        // $extractValues = explode(',',$request->codigosM);
        // $datos = [];
        // foreach($extractValues as $key => $item){
        //     $query = DB::SELECT("SELECT tmp.*
        //       FROM temp_anticipos tmp
        //      WHERE tmp.ID_INSTITUCION = '$item'
        //      AND tmp.EST_VEN_CODIGO <> '3'
        //      ");
        //     $datos[$key] = $query;
        // }
        // return [
        //     "datos" => array_merge(...$datos)
        // ];
    }
    //API GET>>/reporteVentaVendedor
    public function reporteVentaVendedor(Request $request){
        //obtener los vendedores que tienen pedidos
        $query = DB::SELECT("SELECT DISTINCT p.id_asesor ,
        CONCAT(u.nombres, ' ', u.apellidos) AS asesor, u.cedula,u.iniciales
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        WHERE p.id_asesor <> '68750'
        AND p.id_asesor <> '6698'
        ");
        $datos = [];
        $anio = date("Y");
        $menosUno = "";
        if($request->region == 'S'){
            $menosUno = "S".substr(($anio-1),-2);
        }else{
            $menosUno = "C".substr(($anio-1),-2);
        }
        foreach($query as $key => $item){
            //con contrato
            if($request->tipo == 0){
                $query2 = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
                SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
                FROM pedidos p
                WHERE p.id_asesor = '$item->id_asesor'
                AND p.id_periodo ='$request->periodo_id'
                AND p.contrato_generado IS NOT NULL
                AND p.estado = '1'
                ");
            }
            //sin contrato
            if($request->tipo == 1){
                $query2 = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
                SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
                FROM pedidos p
                WHERE p.id_asesor = '$item->id_asesor'
                AND p.id_periodo ='$request->periodo_id'
                AND p.contrato_generado IS NULL
                AND p.estado = '1'
                ");
            }
            //todos
            if($request->tipo == 2){
                $query2 = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
                SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
                FROM pedidos p
                WHERE p.id_asesor = '$item->id_asesor'
                AND p.id_periodo ='$request->periodo_id'
                AND p.estado = '1'
                ");
            }
            //VENTA BRUTA ANTERIOR
            $queryMenosUno = DB::SELECT("SELECT   t.VEN_VALOR,t.PERIODO,
            ( t.VEN_VALOR - ((t.VEN_VALOR * t.VEN_DESCUENTO)/100)) AS ven_neta
            FROM temp_reporte t
            WHERE t.VEN_D_CI = '$item->cedula'
            AND t.PERIODO = '$menosUno'
           ");
            $ventaBrutaActual = $query2[0]->ventaBrutaActual;
            $ven_neta_actual  = $query2[0]->ven_neta_actual;
            $datos[$key] = [
                "id_asesor"             => $item->id_asesor,
                "asesor"                => $item->asesor,
                "iniciales"             => $item->iniciales,
                "cedula"                => $item->cedula,
                "ventaBrutaActual"      => $ventaBrutaActual == null ? '0' :$ventaBrutaActual,
                "ven_neta_actual"       => $ven_neta_actual  == null ? '0' :$ven_neta_actual,
                "MenosUno"              => $queryMenosUno,
            ];
        }
        return $datos;
    }
    //api:get/reporteVentaInstituciones
    public function reporteVentaInstituciones(Request $request){
         $query = DB::SELECT("SELECT t.VENDEDOR, t.VEN_VALOR,t.PERIODO,
         ( t.VEN_VALOR - ((t.VEN_VALOR * t.VEN_DESCUENTO)/100)) AS ven_neta,
         t.INSTITUCION,t.INS_CIUDAD,t.CONTRATO
         FROM temp_reporte_instituciones t
         WHERE t.PERIODO = '$request->periodo_id'
        ");
        return $query;
    }
    //api:get/reporteVentaIndividual
    public function reporteVentaIndividual(Request $request){
        try {
            //asesores
            $teran = ["OT","OAT"];
            $galo  = ["EZ","EZP"];
            //buscar el codigo periodo
            $search = DB::SELECT("SELECT * FROM periodoescolar pe
            WHERE pe.idperiodoescolar = '$request->periodo_id'
            ");
            //buscar las iniciales asesor
            $search2 = DB::SELECT("SELECT  u.iniciales FROM usuario  u
            WHERE u.idusuario = '$request->idusuario'
            ");

            if(empty($search) || empty($search2) ){
                return ["status" => "0","message" => "No hay codigo de periodo o no hay codigo de asesor"];
            }
            $codPeriodo = $search[0]->codigo_contrato;
            $iniciales = $search2[0]->iniciales;
            //TERAN
            $valores     = [];
            $arrayAsesor = [];
            $JsonEnviar  = [];
            if($iniciales == 'OT' || $iniciales == 'EZ'){
                if($iniciales == 'OT') $arrayAsesor = $teran;
                if($iniciales == 'EZ') $arrayAsesor = $galo;
                foreach($arrayAsesor as $key => $item){
                    $test = Http::get('http://186.4.218.168:9095/api/f_ClienteInstitucion/Get_contratounificado?codasesor='.$item.'&periodo=C-'.$codPeriodo);
                    $json = json_decode($test, true);
                   $valores[$key] = $json;
                }
                $setearArray =  array_merge(...$valores);
                $JsonEnviar = array_unique($setearArray,SORT_REGULAR);
            }else{
                $test = Http::get('http://186.4.218.168:9095/api/f_ClienteInstitucion/Get_contratounificado?codasesor='.$iniciales.'&periodo=C-'.$codPeriodo);
                $json = json_decode($test, true);
                $JsonEnviar = $json;
            }
            //Función para filtrar los no convertidos
            $resultado = array_filter($JsonEnviar, function($p) {
                return $p["estVenCodigo"] != 3 && !str_starts_with($p["venConvertido"] , 'C');
                // print_r($p );
            });
            $renderSet = array_values($resultado);
            //enviar valores
            $dataFinally = array();
            $contador = 0;
            foreach($renderSet as $key => $item){
                //variables
                $venValor = $renderSet[$contador]["venValor"];
                $descuento = $renderSet[$contador]["venDescuento"];
                //proceso
                $obj = new stdClass();
                $obj->VEN_VALOR = $venValor;
                $obj->ven_neta =  ( $venValor - (($venValor * $descuento)/100));
                $obj->contrato = $renderSet[$contador]["venCodigo"];
                $obj->insNombre = $renderSet[$contador]["insNombre"];
                $obj->ciuNombre = $renderSet[$contador]["ciuNombre"];
                $obj->anticipo_aprobado =  $renderSet[$contador]["venAnticipo"];
                $obj->venFecha = $renderSet[$contador]["venFecha"];
                array_push($dataFinally,$obj);
                $contador++;
            }
            //===SIN CONTRATOS ===
            $query = DB::SELECT("SELECT SUM(p.total_venta)  as ventaBrutaActual,
            SUM(( p.total_venta - ((p.total_venta * p.descuento)/100))) AS ven_neta_actual
            FROM pedidos p
            WHERE p.id_asesor = '$request->idusuario'
            AND p.id_periodo = '$request->periodo_id'
            AND p.contrato_generado IS NULL
            AND p.estado = '1'
            ");
            $arraySinContrato = [];
            $ventaBrutaActual = $query[0]->ventaBrutaActual;
            $ven_neta_actual  = $query[0]->ven_neta_actual;
            $arraySinContrato[0] = [
                "ventaBrutaActual"      => $ventaBrutaActual == null ? '0' :$ventaBrutaActual,
                "ven_neta_actual"       => $ven_neta_actual  == null ? '0' :$ven_neta_actual,
            ];
            //traer la data de los pedidios en prolipa con facturacion
            $datosContratos = [];
            $contador = 0;
            foreach($dataFinally as $key => $item){
                $pedido = DB::SELECT("SELECT p.*,
                ph.estado as historicoEstado,pe.periodoescolar as periodo
                FROM pedidos p
                LEFT JOIN pedidos_historico  ph ON p.id_pedido = ph.id_pedido
                LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
                WHERE p.contrato_generado = '$item->contrato'
                ");
                if(empty($pedido)){
                    $verificaciones =  $this->getVerificaciones($item->contrato);
                    $datosContratos[$contador] = [
                        "VEN_VALOR"         => $item->VEN_VALOR,
                        "ven_neta"          => $item->ven_neta,
                        "contrato"          => $item->contrato,
                        "insNombre"         => $item->insNombre,
                        "ciuNombre"         => $item->ciuNombre,
                        "anticipo_aprobado" => $item->anticipo_aprobado,
                        "venFecha"          => $item->venFecha,
                        //prolipa
                        "id_pedido"         => null,
                        "id_institucion"    => null,
                        "verificaciones"    => sizeOf($verificaciones),
                        "estado_verificacion"=> 0,
                    ];
                }else{
                    $verificaciones = $this->getVerificaciones($pedido[0]->contrato_generado);
                    $datosContratos[$contador] = [
                        "VEN_VALOR"         => $item->VEN_VALOR,
                        "ven_neta"          => $item->ven_neta,
                        "contrato"          => $item->contrato,
                        "insNombre"         => $item->insNombre,
                        "ciuNombre"         => $item->ciuNombre,
                        "anticipo_aprobado" => $item->anticipo_aprobado,
                        "venFecha"          => $item->venFecha,
                        "id_institucion"    => $pedido[0]->id_institucion,
                        "id_pedido"         => $pedido[0]->id_pedido,
                        "periodo"           => $pedido[0]->periodo,
                        "id_periodo"        => $pedido[0]->id_periodo,
                        "contrato_generado" => $pedido[0]->contrato_generado,
                        "tipo_venta"        => $pedido[0]->tipo_venta,
                        "estado"            => $pedido[0]->estado,
                        "estado_verificacion"=> $pedido[0]->estado_verificacion,
                        "historicoEstado"   => $pedido[0]->historicoEstado,
                        "verificaciones"    => sizeOf($verificaciones),
                    ];
                }
                $contador++;
            }
            return [
                "contratos"     => $datosContratos,
                "sin_contratos" => $arraySinContrato
            ];
            } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
    }
    public function getPedidosXAsesorXPeriodo(Request $request){
        try {
            $idusuario  = $request->idusuario;
            $periodo_id = $request->periodo_id;
            $pedidos = DB::SELECT("SELECT p.*,
            ph.estado as historicoEstado,pe.periodoescolar as periodo,
            i.nombreInstitucion,c.nombre AS nombre_ciudad,
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
            (
            SELECT  SUM(v.valor_liquidacion) as totalLiquidacion
            FROM verificaciones v
            WHERE v.contrato = p.contrato_generado
            AND v.estado  ='0'
            ) as TotalLiquidaciones
            FROM pedidos p
            LEFT JOIN pedidos_historico  ph ON p.id_pedido = ph.id_pedido
            LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            WHERE p.id_asesor  = ?
            AND   p.id_periodo = ?
            AND   p.tipo       = '0'
            ORDER BY p.id_pedido DESC
            ",[$idusuario,$periodo_id]);
            $arreglo = [];
            foreach($pedidos as $key => $item){
                $verificaciones = $this->getVerificaciones($item->contrato_generado);
                $arreglo[$key] = (Object) [
                    "insNombre"             => $item->nombreInstitucion,
                    "nombreInstitucion"     => $item->nombreInstitucion,
                    "ciuNombre"             => $item->nombre_ciudad,
                    "anticipo_aprobado"     => $item->anticipo_aprobado,
                    "venFecha"              => $item->fecha_generacion_contrato,
                    "id_institucion"        => $item->id_institucion,
                    "id_pedido"             => $item->id_pedido,
                    "periodo"               => $item->periodo,
                    "id_periodo"            => $item->id_periodo,
                    "contrato"              => $item->contrato_generado,
                    "contrato_generado"     => $item->contrato_generado,
                    "tipo_venta"            => $item->tipo_venta,
                    "estado"                => $item->estado,
                    "estado_verificacion"   => $item->estado_verificacion,
                    "facturacion_vee"       => $item->facturacion_vee,
                    "tipo_venta_descr"      => $item->tipo_venta_descr,
                    "fecha_entrega"         => $item->fecha_entrega,
                    "fecha_envio"           => $item->fecha_envio,
                    "total_venta"           => $item->total_venta,
                    "descuento"             => $item->descuento,
                    "anticipo"              => $item->anticipo,
                    "id_asesor"             => $item->id_asesor,
                    "asesor"                => $item->asesor,
                    "observacion"           => $item->observacion,
                    "imagen"                => $item->imagen,
                    "convenio_anios"        => $item->convenio_anios,
                    "pedidos_convenios_id"  => $item->pedidos_convenios_id,
                    "ifanticipo"            => $item->ifanticipo,
                    "id_responsable"        => $item->id_responsable,
                    "historicoEstado"       => $item->historicoEstado,
                    "convenio_origen"       => $item->convenio_origen,
                    "deuda"                 => $item->deuda,
                    "verificaciones"        => sizeOf($verificaciones),
                    "TotalLiquidaciones"    => $item->TotalLiquidaciones,
                ];
            }
            // $datos = [];
            return $arreglo;
            // foreach($arreglo as $key => $item){
            //     $resultado  = [];
            //     $valores    = (array) $item;
            //     $pagos      = (array) $this->getPagos($item->contrato_generado);
            //     $resultado  = array_merge($valores,$pagos);
            //     $datos[$key] = [
            //        $resultado
            //     ];
            // }
            //quitar algunos []
            // return array_merge(...$datos);
            } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }
    }
    //API PARA GUARDAR LA DEUDA
    //api:post>>/guardarPedidoDeuda
    public function guardarPedidoDeuda(Request $request){
        $pedido = Pedidos::find($request->id_pedido);
        $anticipoDeudaIngresada                 = $request->anticipoDeudaIngresada;
        //si es 1 significa que la deuda ya ha sido ingresada
        if($pedido->anticipoDeudaIngresada == 1){
            return ["status" => "0", "message" => "La deuda ya ha sido ingresada"];
        }
        $pedido->ifanticipo                     = $request->ifanticipo;
        $pedido->deuda                          = $request->deuda;
        //$pedido->anticipo_aprobado              = $request->anticipo_aprobado;
        $pedido->periodo_deuda                  = $request->periodo_deuda;
        //$pedido->ifagregado_anticipo_aprobado   = $request->ifagregado_anticipo_aprobado;
        $pedido->save();
        if($pedido){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    //FIN APIS ===MOSTRAR LO ANTICIPOS ANTERIORES
    /// lista de contratos por periodo
    public function reportePedidosLibrosGuias( $id)
    {
        $dato = DB::table('pedidos as p')
        ->leftjoin('institucion as i','p.id_institucion','=','i.idInstitucion')
        ->leftjoin('usuario as u','p.id_asesor','=', 'u.idusuario')
        ->leftjoin('ciudad as c','i.ciudad_id','=','c.idciudad')
        ->leftjoin('pedidos_historico as ph','p.id_pedido','ph.id_pedido')
        ->where('p.id_periodo',$id)
        ->select('p.*','i.nombreInstitucion','u.nombres','u.apellidos','c.nombre as ciudad',
        'ph.id_pedido as ph_id_pedido','ph.estado as ph_estado','ph.fecha_creacion_pedido as ph_fecha_creacion_pedido','ph.fecha_generar_contrato as ph_fecha_generar_contrato','ph.fecha_aprobacion_anticipo_gerencia as ph_fecha_aprobacion_anticipo_gerencia','ph.fecha_rechazo_gerencia as ph_fecha_rechazo_gerencia','ph.fecha_subir_cheque as ph_fecha_subir_cheque','ph.fecha_envio_cheque_for_asesor as ph_fecha_envio_cheque_for_asesor','ph.fecha_orden_firmada as ph_fecha_orden_firmada','ph.fecha_que_recibe_orden_firmada as ph_fecha_que_recibe_orden_firmada','ph.tipo_pago as ph_tipo_pago'
        )
        ->get();
        return $dato;
    }
    public function reportePedidosGuiasBodega( $id)
    {
        $dato = DB::table('pedidos as p')
        ->leftjoin('institucion as i','p.id_institucion','=','i.idInstitucion')
        ->leftjoin('usuario as u','p.id_asesor','=', 'u.idusuario')
        ->leftjoin('usuario as fac','p.id_usuario_verif','=', 'fac.idusuario')
        ->leftjoin('ciudad as c','i.ciudad_id','=','c.idciudad')
        ->leftjoin('pedidos_historico as ph','p.id_pedido','ph.id_pedido')
        ->where('p.id_periodo',$id)
        ->where('p.tipo','1')
        ->select(DB::raw('CONCAT(u.nombres , " " , u.apellidos ) as asesor'),DB::raw('CONCAT(fac.nombres , " " , fac.apellidos ) as facturador'),'p.*','i.nombreInstitucion','u.nombres','u.apellidos','c.nombre as ciudad',
        'ph.id_pedido as ph_id_pedido','ph.estado as ph_estado',
        'p.created_at as ph_fecha_creacion_pedido',
        'ph.fecha_generar_contrato as ph_fecha_generar_contrato',
        'ph.fecha_aprobacion_anticipo_gerencia as ph_fecha_aprobacion_anticipo_gerencia',
        'ph.fecha_rechazo_gerencia as ph_fecha_rechazo_gerencia',
        'ph.fecha_subir_cheque as ph_fecha_subir_cheque',
        'ph.fecha_envio_cheque_for_asesor as ph_fecha_envio_cheque_for_asesor',
        'ph.fecha_orden_firmada as ph_fecha_orden_firmada',
        'ph.fecha_que_recibe_orden_firmada as ph_fecha_que_recibe_orden_firmada',
        'ph.tipo_pago as ph_tipo_pago'
        )
        ->get();
        return $dato;
    }
    //ver las notificaciones
    //api:get>>/verNotificacionPedidos
    public function verNotificacionPedidos(Request $request){
       $datos=[];
       $fecha       = date("Y-m-d");
       $Resta30dias = date("Y-m-d",strtotime($fecha."- 30 days"));
       //obtener los ids de los pedidos
       $queryids = DB::SELECT("SELECT   pc.id_pedido,p.id_asesor,
       i.nombreInstitucion, c.nombre AS ciudad,
       CONCAT(ase.nombres, ' ',ase.apellidos) AS asesor
       FROM pedidos_comentarios pc
       LEFT JOIN pedidos p ON pc.id_pedido = p.id_pedido
       LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
       LEFT JOIN ciudad c ON i.ciudad_id  = c.idciudad
       LEFT JOIN periodoescolar pe ON p.id_periodo = pe.idperiodoescolar
       LEFT JOIN usuario ase ON p.id_asesor = ase.idusuario
       WHERE pe.estado = '1'
       AND pc.created_at > '$Resta30dias'
       ORDER BY pc.created_at DESC
       ");
       $seTearArray = [];
       $seTearArray = array_unique($queryids,SORT_REGULAR);
       $contador =0;
       foreach($seTearArray as $key => $item){
        $query = DB::SELECT("SELECT pc.* ,
            CONCAT(u.nombres, ' ',u.apellidos) AS usuario
            FROM pedidos_comentarios pc
            LEFT JOIN usuario u ON pc.id_usuario = u.idusuario
            WHERE pc.id_pedido = '$item->id_pedido'
            ORDER BY pc.id DESC
        ");
        //PARA ver los vistos
        //si es asesor
        if($request->tipo == 0){
            $query2 = DB::SELECT("SELECT *
            FROM pedidos_comentarios pc
            WHERE pc.id_pedido = '$item->id_pedido'
            AND pc.id_group <> '11'
            AND pc.visto  = '1'
            ");
        }else{
            $query2 = DB::SELECT("SELECT *
            FROM pedidos_comentarios pc
            WHERE pc.id_pedido = '$item->id_pedido'
            AND pc.id_group = '11'
            AND pc.visto  = '1'
            ");
        }
        $datos[$contador] =
            [
                "id_pedido"         => $item->id_pedido,
                "nombreInstitucion" => $item->nombreInstitucion,
                "ciudad"            => $item->ciudad,
                "asesor"            => $item->asesor,
                "id_asesor"         => $item->id_asesor,
                "contadorMensajes"  => $query,
                "contadorVistos"    => count($query2)
            ];
        $contador++;
       }
       return $datos;
    }
    public function mostrarMensajesPedido(Request $request){
        $query = DB::SELECT("SELECT pc.* ,
        CONCAT(u.nombres, ' ',u.apellidos) AS usuario
        FROM pedidos_comentarios pc
        LEFT JOIN usuario u ON pc.id_usuario = u.idusuario
        WHERE pc.id_pedido = '$request->id_pedido'
        ");
        //para dejar en visto los mensajes
        $this->VistosMensajesPedidos($request->id_pedido,$request->id_group);
        return $query;
    }
    //METODOS PARA ALCANCE
    //api:post/>changeEstadoAlcance
    public function changeEstadoAlcance(Request $request){
        $alcance = PedidoAlcance::findOrFail($request->id);
        $alcance->estado_alcance = $request->estado_alcance;
        $alcance->save();
        if($alcance){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    //api:post//eliminarAlcance
    public function eliminarAlcance(Request $request){
        //eliminar el alcance
        $alcance = PedidoAlcance::findOrFail($request->id)->delete();
        //eliminar libros
        DB::DELETE("DELETE FROM pedidos_val_area WHERE id_pedido = '$request->id_pedido' AND alcance = '$request->id'");
    }
    //api:post/guardarValorAlcance
    public function guardarValorAlcance(Request $request){
        //guardar el alcance
        if($request->id == 0){
            // validar que no este un alcance abierto
            $query = DB::SELECT("SELECT * FROM pedidos_alcance a
            WHERE a.id_pedido = '$request->id_pedido'
            AND a.estado_alcance = '0'
            ");
            if(empty($query)){
                $alcance = new PedidoAlcance;
                $alcance->id_periodo            = $request->id_periodo;
                $alcance->id_pedido             = $request->id_pedido;
                $alcance->estado_alcance        = 0;
                $alcance->save();
                return $alcance;
            }else{
                return ["status" =>"0","message" =>  "No se puede crear un alcance porque existe un alcance abierto"];
            }
        }else{
            //guardar valores el asesor
            $alcance = PedidoAlcance::findOrFail($request->id);
            if($request->only_observacion){
                $alcance->observacion_asesor    = $request->observacion_asesor;
            }else{
                $alcance->venta_bruta           = $request->venta_bruta;
                $alcance->total_unidades        = $request->total_unidades;
                $alcance->pendiente_liquidar    = $request->pendiente_liquidar;
            }
            $alcance->save();
            if($alcance){
                return ["status" =>"1","message" =>  "Se guardo correctamente"];
            }else{
                return ["status" =>"0","message" =>  "No se pudo guardar"];
            }
        }

    }
    //api:post/AceptarAlcance
    public function AceptarAlcance(Request $request){
        $contrato       = $request->contrato;
        $id_pedido      = $request->id_pedido;
        $id_alcance     = $request->id_alcance;
        $ventaBruta     = $request->ventaBruta;
        $user_created   = $request->user_created;
        //validar que el pedido alcance este abierto
        $validateAbierto     = DB::SELECT("SELECT * FROM pedidos_alcance pa WHERE pa.id = '$id_alcance' AND pa.estado_alcance = '0'");
        if(empty($validateAbierto)){
            return ["status" => "0", "message" => "El alcance no se encuentra activo"];
        }
        //validar que no haya verificaciones
        $validateVerificacion = DB::SELECT("SELECT * FROM verificaciones v WHERE v.contrato = '$contrato'
        ");
        if(count($validateVerificacion) > 0){
            return ["status" => "0", "message" => "Ya existe verificaciones no se puede aprobar el alcance"];
        }
        try {
            //BUSCAR EL CONTRATO SI EXISTE
            // $dato = Http::get("http://186.4.218.168:9095/api/Contrato/".$contrato);
            // $JsonContrato = json_decode($dato, true);
            // if($JsonContrato == "" || $JsonContrato == null){
            //     return ["status" => "0", "message" => "No existe el contrato en facturación"];
            // }
            // $convertido             = "";
            // $convertido             = $JsonContrato["veN_CONVERTIDO"];
            // if($convertido == null || $convertido == ""){
            //     $convertido          = "";
            // }
            // $estado                 = $JsonContrato["esT_VEN_CODIGO"];
            // if($estado != 3 && !str_starts_with($convertido , 'C')){
                //===PROCESO======
                //ACTUALIZAR DETALLE DE VENTA
                $nuevoIngreso       = $this->get_val_pedidoInfo_alcance($id_pedido,$id_alcance);
                if(!empty($nuevoIngreso)){
                    // foreach($nuevoIngreso as $key => $item){
                    //     $proCodigo      = $item["codigo_liquidacion"];
                    //     $cantidad       = $item["valor"];
                    //     $precio         = $item["precio"];
                    //     $form_data      = [];
                    //     //consultar si hay codigo registrado en el contrato en el detalle de venta
                    //     $responseBook   = Http::get("http://186.4.218.168:9095/api/f_DetalleVenta/Busquedaxvencodyprocod?ven_codigo=".$contrato."&pro_codigo=".$proCodigo);
                    //     $JsonLibro      = json_decode($responseBook, true);
                    //     //si no  existe : creo
                    //     if(sizeOf($JsonLibro["detalle_venta"]) == 0){
                    //         $form_data = [
                    //             "venCodigo"             => $contrato,
                    //             "proCodigo"             => $proCodigo,
                    //             "detVenCantidad"        => intval($cantidad),
                    //             "detVenValorU"          => floatval($precio),
                    //             "detVenIva"             => 0,
                    //             "detVenDescontar"       => false,
                    //             "detVenInicio"          => false,
                    //             "detVenCantidadReal"    => intval($cantidad),
                    //         ];
                    //         $saveLibros     = Http::post('http://186.4.218.168:9095/api/f_DetalleVenta/CreateOrUpdateDetalleVenta',$form_data);
                    //         $JsonLibroSave  = json_decode($saveLibros, true);
                    //         //GUARDAR EN EL HISTORICO ALCANCE LIBROS
                    //         $this->saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,0,$cantidad,$user_created,1,$proCodigo);
                    //     }
                    //     //si existe : actualizo
                    //     else{
                    //         $cantidadAnterior = $JsonLibro["detalle_venta"][0]["detVenCantidad"];
                    //         //sumo la cantidad anterior + la nueva
                    //         $resultadoLibro = intvaL($cantidadAnterior) + intval($cantidad);
                    //         $form_data = [
                    //             'detVenCantidad'        => intval($resultadoLibro),
                    //             'detVenCantidadReal'    => intval($resultadoLibro),
                    //         ];
                    //         $updateLibros = Http::post('http://186.4.218.168:9095/api/f_DetalleVenta/UpdateDetallveVenta?venCodigo='.$contrato.'&proCodigo='.$proCodigo, $form_data);
                    //         $json_saveLibros = json_decode($updateLibros, true);
                    //         //GUARDAR EN EL HISTORICO ALCANCE LIBROS
                    //         $this->saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,$cantidadAnterior,$resultadoLibro,$user_created,1,$proCodigo);
                    //     }
                    // }
                    ////////=========
                    ///============PROCESO  DE GUARDADO TABLA VENTA====
                    //GUARDAR NUEVO VEN_VALOR VENTA
                    // $cantidad_anterior  = $JsonContrato["veN_VALOR"];
                    // $nueva_cantidad     = floatval($cantidad_anterior) + floatval($ventaBruta);
                    // $datos = [
                    //     "venValor"        => floatval($nueva_cantidad)
                    // ];
                    // $saveValor          = Http::post('http://186.4.218.168:9095/api/f_Venta/ActualizarVenvalor?venCodigo='.$contrato,$datos);
                    // //GUARDAR EN EL HISTORICO ALCANCE VEN_VALOR
                    // $this->saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,$cantidad_anterior,$nueva_cantidad,$user_created,0,null);
                    //CERRAR ALCANCE
                    $alcance = PedidoAlcance::findOrFail($id_alcance);
                    $alcance->estado_alcance = 1;
                    $alcance->save();
                    return $alcance;
                }else{
                    return ["status" => "0", "message" => "El alcance # $id_alcance del contrato $contrato no existe valores"];
                }

            // }else{
            //     return ["status" => "0", "message" => "El contrato $contrato esta anulado o pertenece a un ven_convertido"];
            // }

        } catch (\Exception  $ex) {
        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
    }
    public function saveHistoricoAlcance($id_alcance,$id_pedido,$contrato,$cantidad_anterior,$nueva_cantidad,$user_created,$tipo,$pro_codigo){
        if(empty($query)){
            $historico                      = new PedidoAlcanceHistorico();
            $historico->contrato            = $contrato;
            $historico->id_pedido           = $id_pedido;
            $historico->alcance_id          = $id_alcance;
            $historico->pro_codigo          = $pro_codigo;
            $historico->cantidad_anterior   = $cantidad_anterior;
            $historico->nueva_cantidad      = $nueva_cantidad;
            $historico->user_created        = $user_created;
            $historico->tipo                = $tipo;
            $historico->save();
        }
    }
    //listar alcaces pedido
    //api:get/>getAlcancePedido
    public function getAlcancePedido(Request $request){
        $query = DB::SELECT("SELECT pa.*,  i.nombreInstitucion, c.nombre AS ciudad,
        p.contrato_generado,
        CONCAT(u.nombres,' ',u.apellidos) as asesor,
        CONCAT(uf.apellidos, ' ',uf.nombres) as facturador
        FROM pedidos_alcance pa
        LEFT JOIN pedidos p ON pa.id_pedido = p.id_pedido
        LEFT JOIN usuario u ON p.id_asesor = u.idusuario
        LEFT JOIN usuario uf ON p.id_usuario_verif = uf.idusuario
        LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id  = c.idciudad
        WHERE pa.id_pedido = '$request->id_pedido'
        ORDER BY id DESC
        ");
        return $query;
    }
    //api:get/trazabilidadAlcance
    public function trazabilidadAlcance($id_pedido){
        $query = DB::SELECT("SELECT * FROM pedidos_alcance a
        WHERE a.id_pedido = '$id_pedido'
        ORDER BY a.id ASC
        ");
        if(empty($query)){
            return $query;
        }
        $datos      = [];
        $contador   = 0;
        foreach($query as $key => $item){
            //estado alcance => 0 = abierto; 1 = cerrado; 2 = rechazado;
            $estado_alcance = $item->estado_alcance;
            //si el alcance esta cerrado o aprobado
            $fecha_aprobacion = "";
            if($estado_alcance == 1){
                $historico = DB::SELECT("SELECT * FROM pedidos_alcance_historico ha
                WHERE ha.id_pedido = '$id_pedido'
                AND ha.alcance_id = '$item->id'
                ");
                $fecha_aprobacion = $historico[0]->created_at;
            }
            $datos[$key] = [
                "id"                    => $item->id,
                "id_periodo"            => $item->id_periodo,
                "id_pedido"             => $item->id_pedido,
                "estado_alcance"        => $item->estado_alcance,
                "venta_bruta"           => $item->venta_bruta,
                "total_unidades"        => $item->total_unidades,
                "pendiente_liquidar"    => $item->pendiente_liquidar,
                "fecha_creacion"        => $item->created_at,
                "fecha_aprobacion"      => $fecha_aprobacion
            ];
            $contador++;
        }
        return $datos;
    }
    //FIN METODOS PARA EL ALCACANCE
    //========================GUIAS=============================
    //Api para obtener las guias que tienen en prolipa
    public function getStockProlipa(Request $request){
        $query = DB::SELECT("SELECT pb.*,l.nombrelibro
        FROM pedidos_guias_bodega pb
        LEFT JOIN libros_series ls ON pb.pro_codigo = ls.codigo_liquidacion
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        WHERE pb.asesor_id = '$request->asesor_id'
        ");
        return $query;
    }
    public function getStockProlipaDevolucion(Request $request){
        $query = DB::SELECT("SELECT pb.*,l.nombrelibro
        FROM pedidos_guias_bodega pb
        LEFT JOIN libros_series ls ON pb.pro_codigo = ls.codigo_liquidacion
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        WHERE pb.asesor_id = '$request->asesor_id'
        ");
        //get devolucion
        $getIdDevolucion = 0;
        $query2 = DB::SELECT("SELECT * FROM pedidos_guias_devolucion pd
        WHERE pd.asesor_id = '$request->asesor_id'
        AND pd.periodo_id = '$request->periodo_id'
        AND pd.estado = '0'
        LIMIT 1
        ");
        if(count($query2)) {
            $getIdDevolucion = $query2[0]->id;
        }
        $datos = [];
        foreach($query as $key => $item){
            //traer la cantidad de devolucion si ya ha devuelto algo y quiere editar
            $cantidadD = 0;
            $query3 = DB::SELECT("SELECT * FROM pedidos_guias_devolucion_detalle gd
            WHERE gd.pro_codigo = '$item->pro_codigo'
            AND gd.pedidos_guias_devolucion_id = '$getIdDevolucion'
            LIMIT 1
            ");
            if(count($query3) >0){
                $cantidadD = $query3[0]->cantidad_devuelta;
            }
            $datos[$key] = [
                "id"            => $item->id,
                "asesor_id"     => $item->asesor_id,
                "pro_codigo"    => $item->pro_codigo,
                "pro_stock"     => $item->pro_stock,
                "created_at"    => $item->created_at,
                "nombrelibro"   => $item->nombrelibro,
                "formato"       => $cantidadD,
            ];
        }
        return $datos;
    }
    //api para mostrar las entregas de guias a instituciones
    public function getEntregasGuias(Request $request){
        $query = DB::SELECT("SELECT en.*, i.nombreInstitucion, c.nombre AS ciudad,
        pe.periodoescolar AS periodo, CONCAT(u.nombres,' ',u.apellidos) as responsable,
        u.cedula
        FROM pedidos_guias_entrega en
        LEFT JOIN usuario u ON en.asesor_id = u.idusuario
        LEFT JOIN institucion i ON en.institucion_id = i.idInstitucion
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        LEFT JOIN periodoescolar pe ON  en.periodo_id = pe.idperiodoescolar
        WHERE en.asesor_id  = '$request->asesor_id'
        ORDER BY en.id DESC
        ");
        $datos = [];
        foreach($query as $key => $item){
            $query2 = DB::SELECT("SELECT l.nombrelibro,de.pro_codigo,
            SUM(de.cantidad_entregada) AS cantidad_entregada,
              b.pro_stock,
              (
                   SELECT SUM(hde.cantidad_entregada) AS devolucion
                   FROM pedidos_guias_entrega_detalle hde
                   WHERE hde.pro_codigo = ls.codigo_liquidacion
                   AND hde.pedidos_guias_entrega_id = '$item->id'
                   AND tipo = '0'
               ) AS devolucion,de.pedidos_guias_entrega_id
              FROM pedidos_guias_entrega_detalle de
              LEFT JOIN libros_series ls ON de.pro_codigo = ls.codigo_liquidacion
              LEFT JOIN libro l ON ls.idLibro = l.idlibro
              LEFT JOIN pedidos_guias_bodega b ON de.pro_codigo = b.pro_codigo
              WHERE de.pedidos_guias_entrega_id = '$item->id'
              AND b.asesor_id = '$request->asesor_id'
              AND de.tipo = '1'
              GROUP BY l.nombrelibro,b.pro_stock
            ");
            $valores = [];
            foreach($query2 as $key2 => $item2){
                $valores[$key2] = [
                    "nombrelibro"           => $item2->nombrelibro,
                    "pro_codigo"            => $item2->pro_codigo,
                    "cantidad_entregada"    => intval($item2->cantidad_entregada),
                    "pro_stock"             => $item2->pro_stock,
                    "devolucion"            => $item2->devolucion == null ? 0 : $item2->devolucion,
                    "pedidos_guias_entrega_id"  => $item2->pedidos_guias_entrega_id,
                    "formato"               => null,
                ];
            }
            $datos[$key] = [
                "id"                => $item->id,
                "institucion_id"    => $item->institucion_id,
                "periodo_id"        => $item->periodo_id,
                "asesor_id"         => $item->asesor_id,
                "responsable"       => $item->responsable,
                "cedula"            => $item->cedula,
                "created_at"        => $item->created_at,
                "nombreInstitucion" => $item->nombreInstitucion,
                "ciudad"            => $item->ciudad,
                "periodo"           => $item->periodo,
                "entregas"          => $valores
            ];
        }
        return $datos;
    }
    public function getEntregasDevoluciones(Request $request){
        $query = DB::SELECT("SELECT de.*, l.nombrelibro, b.pro_stock
            FROM pedidos_guias_entrega_detalle de
            LEFT JOIN libros_series ls ON de.pro_codigo = ls.codigo_liquidacion
            LEFT JOIN libro l ON ls.idLibro = l.idlibro
            LEFT JOIN pedidos_guias_bodega b ON de.pro_codigo = b.pro_codigo
            WHERE de.pedidos_guias_entrega_id = '$request->id'
            AND b.asesor_id = '$request->asesor_id'
            AND de.pro_codigo = '$request->pro_codigo'
            ORDER BY de.id DESC
            ");
        return $query;
    }

    public function PedidoGuiaEntregas(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        $detalles  = json_decode($request->data_detalle);
        $asesor_id = $request->asesor_id;
        $tipo      = $request->tipo;
        //validar que la institucion y periodo si es el mismo no crear
        $validate = DB::SELECT("SELECT * FROM pedidos_guias_entrega pe
        WHERE institucion_id = '$request->institucion_id'
        AND periodo_id = '$request->periodo_id'
        ");
        if(empty($validate)){
            //save entrega
            $entrega = new PedidoGuiaEntrega();
            $entrega->institucion_id  = $request->institucion_id;
            $entrega->periodo_id      = $request->periodo_id;
            $entrega->asesor_id       = $asesor_id;
            $entrega->save();
        }else{
            $getId   = $validate[0]->id;
            $entrega = PedidoGuiaEntrega::findOrFail($getId);
        }
        foreach($detalles as $key => $item){
            $codigo     = $item->pro_codigo;
            $cantidad   = $item->formato;
            //GUARDAR DETALLE DE ENTREGA
            $this->savePedidoGuiaDetalle($item,$entrega,$tipo);
            //GUARDAR EL STOCK EN BODEGA DE PROLIPA
            //tipo  0 = suma; 1 = dismunuir stock
            $this->saveStockBodegaProlipa($tipo,$asesor_id,$codigo,$cantidad);
        }
        if($entrega){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    public function savePedidoGuiaDetalle($tr,$entrega,$tipo){
        $detalle = new PedidoGuiaEntregaDetalle();
        $detalle->pro_codigo                = $tr->pro_codigo;
        $detalle->cantidad_entregada        = $tr->formato;
        $detalle->pedidos_guias_entrega_id  = $entrega->id;
        $detalle->tipo                      = $tipo;
        $detalle->save();
    }
    //api:post//guardarGuiasBDMilton
    public function guardarGuiasBDMilton(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        try {
            //variables
            $asesor_id            = $request->asesor_id;
            $id_pedido            = $request->id_pedido;
            $id_periodo           = $request->id_periodo;
            $codigo_contrato      = $request->codigo_contrato;
            $cod_fact             = $request->codigo_usuario_fact;
            $usuario_fact         = $request->usuario_fact;
            $iniciales            = $request->iniciales;
            $total_venta          = 0;
            $observacion          = "";
            $anticipo             = 0;
            $descuento            = 0;
            $fecha_formato        = date("Y-m-d");
            $region_idregion      = $request->region_idregion;
            $cuenta               = "0";
            $fechaActual          = date("Y-m-d H:i:s");
            $cli_ins_codigo       = $request->cli_ins_codigo;
            $getcli_ins_codigo    = 0;
            //id general de prolipa para los vendedores
            $query = DB::SELECT("SELECT * FROM pedidos_secuencia s
            WHERE s.id_periodo  = '$request->id_periodo'
            AND s.asesor_id     = '$request->asesor_id'
            AND s.institucion_facturacion = '22926'
            ");
            if(empty($query)){
                if($cli_ins_codigo == "undefined" || $cli_ins_codigo == null || $cli_ins_codigo == ""){
                    return ["status" => "0", "message" => "No esta configurado el id de institucion en el usuario"];
                }
                //si tiene el cli_ins_codigo en el usuario bien creamos la secuencia
                $sec = new PedidosSecuencia();
                $sec->asesor_id             = $asesor_id;
                //guardar
                $sec->sec_ven_nombre        = $codigo_contrato;
                $sec->sec_ven_valor         = 0;
                $sec->ven_d_codigo          = $iniciales;
                $sec->asesor_id             = $asesor_id;
                $sec->id_periodo            = $id_periodo;
                $sec->cli_ins_codigo        = $cli_ins_codigo;
                $sec->save();
                $getcli_ins_codigo          = $cli_ins_codigo;
            }else{
                //validar que tenga el cli_inscodigo
                $getcli_ins_codigo = $query[0]->cli_ins_codigo;
            }

             if($getcli_ins_codigo == null || $getcli_ins_codigo == "null"){
                 return ["status" => "0", "message" => "No esta configurado el id de institucion de prolipa de facturacion"];
            }
            //get secuencia
            $secuencia = Http::get('http://186.4.218.168:9095/api/f_Configuracion');
            $json_secuencia_guia = json_decode($secuencia, true);
            $getSecuencia   = $json_secuencia_guia[22]["conValorNum"];
            // //VARIABLES
             $cod_institucion      = $getcli_ins_codigo;
            $secuencia = $getSecuencia;
            if( $secuencia < 10 ){
                $format_id_pedido = '000000' . $secuencia;
            }
            if( $secuencia >= 10 && $secuencia < 1000 ){
                $format_id_pedido = '00000' . $secuencia;
            }
            if( $secuencia > 1000 ){
                $format_id_pedido = '0000' . $secuencia;
            }
            $codigo_ven = 'A-' . $codigo_contrato . '-' .$cod_fact . '-'. $format_id_pedido;
            //===ENVIAR A TABLA DE VENTA DE MILTON LAS GUIAS
            $form_data = [
                'veN_CODIGO'            => $codigo_ven, //codigo formato milton
                'usU_CODIGO'            => strval($cod_fact),
                'veN_D_CODIGO'          => $iniciales, // codigo del asesor
                'clI_INS_CODIGO'        => floatval($cod_institucion),
                'tiP_veN_CODIGO'        => 2, //Venta por lista
                'esT_veN_CODIGO'        => 2, // por defecto
                'veN_OBSERVACION'       => null,
                'veN_VALOR'             => floatval($total_venta),
                'veN_PAGADO'            => 0.00, // por defecto
                'veN_ANTICIPO'          => floatval($anticipo),
                'veN_DESCUENTO'         => floatval($descuento),
                'veN_FECHA'             => $fecha_formato,
                'veN_CONVERTIDO'        => '', // por defecto
                'veN_TRANSPORTE'        => 0.00, // por defecto
                'veN_ESTADO_TRANSPORTE' => false, // por defecto
                'veN_FIRMADO'           => 'DS', // por defecto
                'veN_TEMPORADA'         => $region_idregion == 1 ? 0 :1 ,
                'cueN_NUMERO'           => strval($cuenta)
            ];
            $guias = Http::post('http://186.4.218.168:9095/api/Contrato', $form_data);
            $json_guias = json_decode($guias, true);
            // //ACTUALIZAR VEN CODIGO - FECHA APROBACION-
            $query = "UPDATE `pedidos` SET `ven_codigo` = '$codigo_ven', `id_usuario_verif` = $usuario_fact ,`fecha_aprobado_facturacion` = '$fechaActual', `estado_entrega` = '1' WHERE `id_pedido` = $id_pedido;";
            DB::UPDATE($query);
            //================SAVE DETALLE DE LAS GUIAS======================
            //obtener las guias por libros
            $detalleGuias = $this->get_val_pedidoInfo($request->id_pedido);
            //Si no hay nada en detalle de venta
            if(empty($detalleGuias)){
                return ["status" => "0", "message" => "No hay ningun libro para el detalle de venta"];
            }
            //variables
            $iva = 0;
            $precio = 0;
            $descontar =0;
            //GUARDAR DETALLE DE LAS GUIAS
            for($i =0; $i< count($detalleGuias);$i++){
                $form_data_detalleGuias = [
                    "VEN_CODIGO"            => $codigo_ven,
                    "PRO_CODIGO"            => "G".$detalleGuias[$i]["codigo_liquidacion"],
                    "DET_VEN_CANTIDAD"      =>  intval($detalleGuias[$i]["valor"]),
                    "DET_VEN_VALOR_U"       => floatval($precio),
                    "DET_VEN_IVA"           => floatval($iva),
                    "DET_VEN_DESCONTAR"     => intval($descontar),
                    "DET_VEN_INICIO"        => false,
                    "DET_VEN_CANTIDAD_REAL" => intval($detalleGuias[$i]["valor"]),
                ];
                $detalle = Http::post('http://186.4.218.168:9095/api/DetalleVenta', $form_data_detalleGuias);
                $json_detalle = json_decode($detalle, true);
            }
            //ACTUALIZAR EL ACTA DE LAS GUIAS
            //post leer y aumentar secuencia + 1
            $form_data_Secuencia = [
                "conCod"        => 23,
                "conNombre"     => "actas",
                "conValorNum"   => $getSecuencia + 1 ,
                "conValorStr"   => null,
            ];
            $post_Secuencia = Http::post('http://186.4.218.168:9095/api/f_Configuracion', $form_data_Secuencia);
            $json_secuencia = json_decode($post_Secuencia, true);
            //===ACTUALIZAR STOCK========
           return $this->actualizarStockFacturacion($detalleGuias,$codigo_ven);
            //return response()->json(['json_guias' => $json_guias, 'form_data' => $form_data]);
         } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }

    }
    //actualizar stock
    public function actualizarStockFacturacion($arregloCodigos,$codigo_ven){
        $contador = 0;
        foreach($arregloCodigos as $key => $item){
            $form_data_stock = [];
            $codigo         = $arregloCodigos[$contador]["codigo_liquidacion"];
            $codigoFact     = "G".$codigo;
            //get stock
            $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigoFact);
            $json_stock     = json_decode($getStock, true);
            $stockAnterior  = $json_stock["producto"][0]["proStock"];
            //post stock
            $valorNew       = $arregloCodigos[$contador]["valor"];
            $nuevoStock     = $stockAnterior - $valorNew;
            $form_data_stock = [
                "proStock"     => $nuevoStock,
            ];
            //test
           // $postStock = Http::post('http://186.4.218.168:9095/api/f_Producto/ActualizarStockProducto?proCodigo='.$codigoFact,$form_data_stock);
            //prod
            $postStock = Http::post('http://186.4.218.168:9095/api/f2_Producto/ActualizarStockProducto?proCodigo='.$codigoFact,$form_data_stock);
            $json_StockPost = json_decode($postStock, true);
            //save Historico
            $historico = new PedidoHistoricoActas();
            $historico->cantidad        = $valorNew;
            $historico->ven_codigo      = $codigo_ven;
            $historico->pro_codigo      = $codigo;
            $historico->stock_anterior  = $stockAnterior;
            $historico->nuevo_stock     = $nuevoStock;
            $historico->save();
            $contador++;
        }
        return "se guardo correctamente";
    }
    public function actualizarStockProlipa($acta,$asesor_id,$tipo){
        //obtener valores de las actas para sumar al stock de prolipa
        //tipo 0 = peticion; 1 = devolucion;
        $query = $this->getActas($acta,$tipo);
        if(count($query) > 0){
            foreach($query as $key => $item){
                $codigo         = $item->pro_codigo;
                $cantidad       = $item->cantidad;
                //GUARDAR EL STOCK EN BODEGA DE PROLIPA
                $this->saveStockBodegaProlipa($tipo,$asesor_id,$codigo,$cantidad);
            }
        }
        //actualizar acta que ha los codigos ya sumaron en la tabla de prolipa bodega
        DB::UPDATE("UPDATE pedidos_historico_actas SET ingresado_a_tabla_pedido_guias_bodega = '1' WHERE ven_codigo = '$acta' AND tipo = '$tipo'");
        return "se guardo correctamente";
    }
    public function getActas($acta,$tipo){
        $query = DB::SELECT("SELECT DISTINCT ha.ven_codigo,ha.pro_codigo,ha.cantidad
        FROM pedidos_historico_actas ha
        WHERE ha.ven_codigo = '$acta'
        AND ha.ingresado_a_tabla_pedido_guias_bodega = '0'
        AND ha.tipo = '$tipo'
        ");
        return $query;
    }
    public function saveStockBodegaProlipa($tipo,$asesor_id,$pro_codigo,$stockN){
        $query = DB::SELECT("SELECT * FROM pedidos_guias_bodega pb
        WHERE pb.asesor_id = '$asesor_id'
        AND pb.pro_codigo = '$pro_codigo'
        LIMIT 1
        ");
        $nuevoStock = 0;
        if(empty($query)){
            $stock = new PedidosGuiasBodega();
            $stock->asesor_id           = $asesor_id;
            $stock->pro_codigo          = $pro_codigo;
            $nuevoStock = $stockN;
        }else{
           //traer el id de bodega
           $getId = $query[0]->id;
           $stock = PedidosGuiasBodega::findOrFail($getId);
           $anteriorStock = $stock->pro_stock;
           //Solicitud de guias a bodega aumenta la cantidad que tiene el asesor
           //solicitud /suma
           if($tipo == 0) $nuevoStock =  $anteriorStock + $stockN;
           //Devolucion de guias a bodega resta la cantidad que tiene el asesor
           //devolucion / resta
           if($tipo == 1) $nuevoStock =  $anteriorStock - $stockN;
        }
        $stock->pro_stock                = $nuevoStock;
        $stock->save();
    }
    //====================FIN APIS GUIAS=======================
    //test2
    public function guardarGuiasBDMilton2(Request $request){
        set_time_limit(6000000);
        ini_set('max_execution_time', 6000000);
        try {
            //variables
            // $ven_codigo           = $request->ven_codigo;
            // $id_pedido            = $request->id_pedido;
            // $codigo_contrato      = $request->codigo_contrato;
            // $cod_fact             = "FR";
            // $usuario_fact         = 64394;
            // $iniciales            = $request->iniciales;
            // $total_venta          = 0;
            // $anticipo             = 0;
            // $descuento            = 0;
            // $fecha_formato        = date("Y-m-d");
            // $region_idregion      = $request->region_idregion;
            // $cuenta               = "0";
            // $fechaActual          = date("Y-m-d H:i:s");
            // $id_periodo           = $request->id_periodo;
            // $asesor_id            = $request->asesor_id;

            $ven_codigo           = "A-C24-FR-000053933";
            $id_pedido            = 658;
            $codigo_contrato      = "C24";
            $cod_fact             = "FR";
            $usuario_fact         = 64394;
            $iniciales            = "LJ";
            $total_venta          = 0;
            $anticipo             = 0;
            $descuento            = 0;
            $fecha_formato        = date("Y-m-d");
            $region_idregion      = 2;
            $cuenta               = "0";
            $fechaActual          = date("Y-m-d H:i:s");
            $id_periodo           = 24;
            $asesor_id            = 26087;

            $query = DB::SELECT("SELECT * FROM pedidos_secuencia s
            WHERE s.id_periodo = '$id_periodo'
            AND s.asesor_id = '$asesor_id'
            AND s.institucion_facturacion = '22926'
            ");
            if(empty($query)){
                return ["status" => "0", "message" => "No esta configurado el id de institucion de prolipa de facturacion"];
            }
            //validar que tenga el cli_inscodigo
            $getcli_ins_codigo = $query[0]->cli_ins_codigo;
            if($getcli_ins_codigo == null || $getcli_ins_codigo == "null"){
                return ["status" => "0", "message" => "No esta configurado el id de institucion de prolipa de facturacion"];
            }
            //get secuencia
            $secuencia = Http::get('http://186.4.218.168:9095/api/f_Configuracion');
            $json_secuencia_guia = json_decode($secuencia, true);
            $getSecuencia   = $json_secuencia_guia[22]["conValorNum"];
            // //VARIABLES
             $cod_institucion      = $query[0]->cli_ins_codigo;
            $secuencia = $getSecuencia;
            if( $secuencia < 10 ){
                $format_id_pedido = '000000' . $secuencia;
            }
            if( $secuencia >= 10 && $secuencia < 1000 ){
                $format_id_pedido = '00000' . $secuencia;
            }
            if( $secuencia > 1000 ){
                $format_id_pedido = '0000' . $secuencia;
            }
            // $codigo_ven = 'A-' . $codigo_contrato . '-' .$cod_fact . '-'. $format_id_pedido;
            $codigo_ven = $ven_codigo;
            //===ENVIAR A TABLA DE VENTA DE MILTON LAS GUIAS
            $form_data = [
                'veN_CODIGO'            => $codigo_ven, //codigo formato milton
                'usU_CODIGO'            => strval($cod_fact),
                'veN_D_CODIGO'          => $iniciales, // codigo del asesor
                'clI_INS_CODIGO'        => floatval($cod_institucion),
                'tiP_veN_CODIGO'        => 2, //Venta por lista
                'esT_veN_CODIGO'        => 2, // por defecto
                'veN_OBSERVACION'       => null,
                'veN_VALOR'             => floatval($total_venta),
                'veN_PAGADO'            => 0.00, // por defecto
                'veN_ANTICIPO'          => floatval($anticipo),
                'veN_DESCUENTO'         => floatval($descuento),
                'veN_FECHA'             => $fecha_formato,
                'veN_CONVERTIDO'        => '', // por defecto
                'veN_TRANSPORTE'        => 0.00, // por defecto
                'veN_ESTADO_TRANSPORTE' => false, // por defecto
                'veN_FIRMADO'           => 'DS', // por defecto
                'veN_TEMPORADA'         => $region_idregion == 1 ? 0 :1 ,
                'cueN_NUMERO'           => strval($cuenta)
            ];
            $guias = Http::post('http://186.4.218.168:9095/api/Contrato', $form_data);
            $json_guias = json_decode($guias, true);
            // //ACTUALIZAR VEN CODIGO - FECHA APROBACION-
            // $query = "UPDATE `pedidos` SET `ven_codigo` = '$codigo_ven', `id_usuario_verif` = $usuario_fact ,`fecha_aprobado_facturacion` = '$fechaActual', `estado_entrega` = '1' WHERE `id_pedido` = $id_pedido;";
            // DB::UPDATE($query);
            //================SAVE DETALLE DE LAS GUIAS======================
            //obtener las guias por libros
            $detalleGuias = $this->get_val_pedidoInfo($id_pedido);
            //Si no hay nada en detalle de venta
            if(empty($detalleGuias)){
                return ["status" => "0", "message" => "No hay ningun libro para el detalle de venta"];
            }
            //variables
            $iva = 0;
            $precio = 0;
            $descontar =0;
            //GUARDAR DETALLE DE LAS GUIAS
            for($i =0; $i< count($detalleGuias);$i++){
                $form_data_detalleGuias = [
                    "VEN_CODIGO"            => $codigo_ven,
                    "PRO_CODIGO"            => "G".$detalleGuias[$i]["codigo_liquidacion"],
                    "DET_VEN_CANTIDAD"      =>  intval($detalleGuias[$i]["valor"]),
                    "DET_VEN_VALOR_U"       => floatval($precio),
                    "DET_VEN_IVA"           => floatval($iva),
                    "DET_VEN_DESCONTAR"     => intval($descontar),
                    "DET_VEN_INICIO"        => false,
                    "DET_VEN_CANTIDAD_REAL" => intval($detalleGuias[$i]["valor"]),
                ];
                $detalle = Http::post('http://186.4.218.168:9095/api/DetalleVenta', $form_data_detalleGuias);
                $json_detalle = json_decode($detalle, true);
            }
            return $form_data;
            //ACTUALIZAR EL ACTA DE LAS GUIAS
            //post leer y aumentar secuencia + 1
            // $form_data_Secuencia = [
            //     "conCod"        => 23,
            //     "conNombre"     => "actas",
            //     "conValorNum"   => $getSecuencia + 1 ,
            //     "conValorStr"   => null,
            // ];
            // $post_Secuencia = Http::post('http://186.4.218.168:9095/api/f_Configuracion', $form_data_Secuencia);
            // $json_secuencia = json_decode($post_Secuencia, true);
            //===ACTUALIZAR STOCK========
        //    return $this->actualizarStockFacturacion($detalleGuias,$codigo_ven);
            //return response()->json(['json_guias' => $json_guias, 'form_data' => $form_data]);
         } catch (\Exception  $ex) {
            return ["status" => "0","message" => "Hubo problemas con la conexión al servidor".$ex];
        }

    }
    public function actualizarStockFacturacionTest($arregloCodigos,$codigo_ven){
        $contador = 0;
        foreach($arregloCodigos as $key => $item){
            $form_data_stock = [];
            $codigo         = $arregloCodigos[$contador]["codigo_liquidacion"];
            //get stock
            $getStock       = Http::get('http://186.4.218.168:9095/api/f2_Producto/Busquedaxprocodigo?pro_codigo='.$codigo);
            $json_stock     = json_decode($getStock, true);
            $stockAnterior  = $json_stock["producto"][0]["proStock"];
            //post stock
            $valorNew       = $arregloCodigos[$contador]["valor"];
            $nuevoStock     = $stockAnterior - $valorNew;
            $form_data_stock = [
                "proStock"     => $nuevoStock,
            ];
            //test
            $postStock = Http::post('http://186.4.218.168:9095/api/f_Producto/ActualizarStockProducto?proCodigo='.$codigo,$form_data_stock);
            //prod
            //$postStock = Http::post('http://186.4.218.168:9095/api/f2_Producto/ActualizarStockProducto?proCodigo='.$codigo,$form_data_stock);
            $json_StockPost = json_decode($postStock, true);
            //save Historico
            $historico = new PedidoHistoricoActas();
            $historico->cantidad        = $valorNew;
            $historico->ven_codigo      = $codigo_ven;
            $historico->pro_codigo      = $codigo;
            $historico->stock_anterior  = $stockAnterior;
            $historico->nuevo_stock     = $nuevoStock;
            $historico->save();
            $contador++;
        }
        return "se guardo correctamente";

    }
    public function getDetalle($id){
        $query = DB::SELECT("SELECT  pg.* , l.nombrelibro
        FROM pedidos_guias_devolucion_detalle pg
        LEFT JOIN libros_series ls ON pg.pro_codigo = ls.codigo_liquidacion
        LEFT JOIN libro l ON ls.idLibro = l.idlibro
        WHERE pg.pedidos_guias_devolucion_id = '$id'
        ORDER BY l.nombrelibro
        ");
        return $query;
    }
    //FIN METODOS GUIAS
    //API para obtener el contrato
    //api:get/contratoFacturacion{contrato}
    public function contratoFacturacion($contrato){
        // $data = '{
        //     "veN_CODIGO": "C-C23-0000024-JS",
        //     "usU_CODIGO": "VJR",
        //     "veN_D_CODIGO": "JS",
        //     "clI_INS_CODIGO": 32115,
        //     "tiP_VEN_CODIGO": 2,
        //     "esT_VEN_CODIGO": 2,
        //     "veN_OBSERVACION": "",
        //     "veN_VALOR": 11063.8,
        //     "veN_PAGADO": 0,
        //     "veN_ANTICIPO": 1500,
        //     "veN_DESCUENTO": 40,
        //     "veN_FECHA": "2022-10-21T10:12:09.093",
        //     "veN_CONVERTIDO": "",
        //     "veN_TRANSPORTE": 0,
        //     "veN_ESTADO_TRANSPORTE": true,
        //     "veN_FIRMADO": "DJS            ",
        //     "veN_TEMPORADA": 1,
        //     "cueN_NUMERO": "17"
        //     }'
        // ;
        // $sendQuery = json_decode($data,true);
        // return $sendQuery;
        try {
            $dato = Http::get("http://186.4.218.168:9095/api/Contrato/".$contrato);
            $JsonContrato = json_decode($dato, true);
            if($JsonContrato == "" || $JsonContrato == null){
                return ["status" => "0", "message" => "No existe el contrato en facturación"];
            }
            return $JsonContrato;
        } catch (\Exception  $ex) {
        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
    }
    //API PARA GUARDAR EL CONTRATO MANUALMENTE DEL SISTEMA DE FACTURACION
    //api:post/generarContratoFacturacion
    public function generarContratoFacturacion(Request $request){
        //validate si ya existe el contrato
        $validate = DB::SELECT("SELECT p.*, CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
            u.cedula,i.nombreInstitucion
            FROM pedidos p
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            WHERE contrato_generado = '$request->contrato'
        ");
        if(count($validate) > 0){
            return ["status" => "0","message" => "El contrato ya existe en prolipa"];
        }
        $validate2 = DB::SELECT("SELECT p.*, CONCAT(u.nombres, ' ', u.apellidos) AS asesor,
            u.cedula,i.nombreInstitucion
            FROM pedidos p
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            WHERE id_pedido = '$request->id_pedido'
        ");
        //obtener que tenga beneficiarios
        $query3 = DB::SELECT("SELECT  b.*,
          CONCAT(u.nombres, ' ', u.apellidos) AS docente,
          u.cedula
          FROM pedidos_beneficiarios b
          LEFT JOIN usuario u ON b.id_usuario = u.idusuario
          WHERE b.id_pedido = '$request->id_pedido'
        ");
        //traer el codigo de periodo
        $getPeriodo = Periodo::findOrFail($request->periodo_id);
        if($getPeriodo->codigo_contrato == null || $getPeriodo->codigo_contrato == "" || $getPeriodo->codigo_contrato == "null"){
            return ["status" => "0","message" => "El periodo no tiene código de contrato"];
        }
        $contrato           = $request->contrato;
        $id_pedido          = $request->id_pedido;
        $pedido             = Pedidos::findOrFail($id_pedido);
        $institucion        = $pedido->id_institucion;
        $asesor_id          = $request->asesor_id;
        $temporada          = substr($getPeriodo->codigo_contrato,0,1);
        $periodo            = $request->periodo_id;
        $ciudad             = $request->ciudad;
        $usuario_fact       = 64394;
        $fechaContrato      = $request->fechaContrato;
        $fecha_Gerencia     = $request->fecha_Gerencia;
        $asesor             = $validate2[0]->asesor;
        $nombreInstitucion  = $validate2[0]->nombreInstitucion;
        $cedulaAsesor       = $validate2[0]->cedula;
        $nombreDocente      = $query3[0]->docente;
        $cedulaDocente      = $query3[0]->cedula;
        $query = "UPDATE `pedidos` SET `contrato_generado` = '$contrato', `id_usuario_verif` = $usuario_fact,`fecha_generacion_contrato` = '$fechaContrato', `facturacion_vee` = '1' WHERE `id_pedido` = $id_pedido;";
        DB::UPDATE($query);
        //si tiene anticipo
        if($pedido->ifanticipo == 1){
            $query2 = "UPDATE `pedidos_historico` SET `estado` = '2', `fecha_generar_contrato` = '$fechaContrato',`fecha_aprobacion_anticipo_gerencia` = '$fecha_Gerencia' WHERE `id_pedido` = $id_pedido;";
            DB::UPDATE($query2);
        }
        $this->guardarContratoTemporada($contrato,$institucion,$asesor_id,$temporada,$periodo,$ciudad,$asesor,$cedulaAsesor,$nombreDocente,$cedulaDocente,$nombreInstitucion);
        return ["status" => "1","message" => "Se guardo correctamente"];
    }
    //api para ver los anticipos ya las liquidacion del contrato
    public function getLiquidacion($id){
        //buscar si hay  contrato
        $query = DB::SELECT("SELECT * FROM pedidos p WHERE p.id_pedido = '$id'");
        $contrato = $query[0]->contrato_generado;
        if($contrato == null || $contrato == "null" || $contrato == ""){
            return ["status" => "0", "message" => "No existe contrato en el pedido #$id"];
        }
        //=======PROCEDIMIENTO==========================
        try {
            $dato = Http::get("http://186.4.218.168:9095/api/Contrato/".$contrato);
            $JsonContrato = json_decode($dato, true);
            if($JsonContrato == "" || $JsonContrato == null){
                return ["status" => "0", "message" => "No existe el contrato en facturación"];
            }
            $covertido      = $JsonContrato["veN_CONVERTIDO"];
            $estado         = $JsonContrato["esT_VEN_CODIGO"];
            if($estado != 3 && !str_starts_with($covertido , 'C')){
                //===PROCESO======
                $dato2 = Http::get("http://186.4.218.168:9095/api/f_DocumentoLiq/Get_docliq_venta_x_vencod?venCodigo=".$contrato);
                $JsonDocumentos = json_decode($dato2, true);
                return $JsonDocumentos;
            }else{
                // return $dataFinally;
                return ["status" => "0", "message" => "El contrato $contrato esta anulado o pertenece a un ven_convertido"];
            }

        } catch (\Exception  $ex) {
        return ["status" => "0","message" => "Hubo problemas con la conexión al servidor"];
        }
    }
    //api:get/llenarInformacionContrato
    public function llenarInformacionContrato(Request $request){
        $query = DB::SELECT("SELECT p.id_pedido,p.contrato_generado,p.enviarMilton,p.id_asesor,
            CONCAT(u.nombres, ' ', u.apellidos) AS asesor,u.cedula,
            i.nombreInstitucion
            FROM pedidos p
            LEFT JOIN usuario u ON p.id_asesor = u.idusuario
            LEFT JOIN institucion i ON p.id_institucion = i.idInstitucion
            WHERE p.contrato_generado IS NOT NULL
            AND p.id_periodo <> '20'
            AND p.enviarMilton = '0'
        ");
        foreach($query as $key => $item){
            $contrato  = $item->contrato_generado;
            $id_pedido = $item->id_pedido;
            //si existe el contrato en temporadas lleno la informacion
            $query2 = DB::SELECT("SELECT * FROM temporadas t WHERE t.contrato = '$contrato'");
            if(count($query2) > 0){
                //obtener que tenga beneficiarios
                $query3 = DB::SELECT("SELECT  b.*,
                CONCAT(u.nombres, ' ', u.apellidos) AS docente,
                u.cedula
                FROM pedidos_beneficiarios b
                LEFT JOIN usuario u ON b.id_usuario = u.idusuario
                WHERE b.id_pedido = '$id_pedido'");
                if(count($query3) > 0){
                    $id_temporada                       = $query2[0]->id_temporada;
                    $temporada                          = Temporada::findOrFail($id_temporada);
                    $temporada->temporal_nombre_docente = $query3[0]->docente;
                    $temporada->temporal_cedula_docente = $query3[0]->cedula;
                    $temporada->temporal_institucion    = $item->nombreInstitucion;
                    $temporada->nombre_asesor           = $item->asesor;
                    $temporada->cedula_asesor           = $item->cedula;
                    $date = Carbon::now();
                    $temporada->ultima_fecha            = $date;
                    $temporada->save();
                    //actualizar el campo de pedido que ya se cambio ese contrato
                    DB::table('pedidos')
                    ->where('contrato_generado', '=',$contrato)
                    ->update(['enviarMilton' => 1]);
                }
            }
        }
        return "se guardo correctamente";
    }
    //api:post/asignarBeneficiarioPrincipal
    public function asignarBeneficiarioPrincipal(Request $request){
        $pedido                 = Pedidos::findOrFail($request->id_pedido);
        $pedido->id_responsable = $request->id_responsable;
        $pedido->save();
        $contrato = $pedido->contrato_generado;
        //actualizar en el la tabla temporada
        $query = DB::SELECT("SELECT * FROM temporadas t WHERE t.contrato = '$contrato'");
        //obtener datos del beneficiario
        $query2 = DB::SELECT("SELECT u.cedula,  CONCAT(u.nombres, ' ', u.apellidos) AS docente FROM  usuario u WHERE `idusuario` = ?", [$request->id_responsable]);
        $id_temporada                       = $query[0]->id_temporada;
        $temporada                          = Temporada::findOrFail($id_temporada);
        $temporada->temporal_nombre_docente = $query2[0]->docente;
        $temporada->temporal_cedula_docente = $query2[0]->cedula;
        $date = Carbon::now();
        $temporada->ultima_fecha            = $date;
        $temporada->save();
        //actualizar documentos
        $this->updateDocumentoAnterior($request->id_pedido,1);
        return $pedido->id_responsable;
    }
    //==APIS DOCUMENTOS ANTERIORES=========
    //api get/getTraerDocumentoDocente
    public function getTraerDocumentoDocente($id_pedido){
        ///validar que el pedido exista y este activo
        $query = DB::SELECT("SELECT p.*, u.cedula,
        CONCAT(u.nombres, ' ', u.apellidos) AS docente
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_responsable = u.idusuario
        WHERE p.id_pedido = '$id_pedido'
        AND (p.estado  = '0' OR  p.estado = '1')
        ");
        $datos = [];
        if(sizeOf($query) > 0){
            //variables
            $id_periodo                 = $query[0]->id_periodo;
            $institucion                = $query[0]->id_institucion;
            $cedulaDocente              = $query[0]->cedula;
            $docente                    = $query[0]->docente;
            $query2 = DB::SELECT("SELECT  pd.*,pe.periodoescolar
                FROM pedidos_documentos_docentes pd
                LEFT JOIN periodoescolar pe ON pd.id_periodo = pe.idperiodoescolar
                WHERE pd.institucion_id = '$institucion'
                ORDER BY pd.id DESC
            ");
            foreach($query2 as $key => $item){
                $datos[$key] = [
                    "id"                => $item->id,
                    "institucion_id"    => $item->institucion_id,
                    "doc_cedula"        => $item->doc_cedula,
                    "doc_ruc"           => $item->doc_ruc,
                    "docente"           => $docente,
                    "cedulaDocente"     => $cedulaDocente,
                    "periodoescolar"    => $item->periodoescolar,
                    "id_periodo"        => $item->id_periodo
                ];
            }
        }
        return $datos;
    }
    //api:Get/updateDocumentoAnterior/{id_pedido}/{withContrato}
    public function updateDocumentoAnterior($id_pedido,$withContrato){
        ///validar que el pedido exista y este activo
        $query = DB::SELECT("SELECT p.*, u.cedula,
        CONCAT(u.nombres, ' ', u.apellidos) AS docente
        FROM pedidos p
        LEFT JOIN usuario u ON p.id_responsable = u.idusuario
        WHERE p.id_pedido = '$id_pedido'
        AND p.tipo = '0'
        AND p.estado = '1'
        AND p.ifanticipo  = '1'
        AND p.imagen IS NOT NULL
        AND p.imagen <> 'undefined'
        ");
        if(sizeOf($query) > 0){
            //variables
            $institucion                = $query[0]->id_institucion;
            $id_periodo                 = $query[0]->id_periodo;
            $cedulaDocente              = $query[0]->cedula;
            $doc_cedula                 = $query[0]->imagen;
            $doc_ruc                    = $query[0]->doc_ruc;
            //Valido si existe un registro de documento de la institucion en el periodo
            $validate = $this->validateDocumento($institucion,$id_periodo);
            if(empty($validate)){
                //Guardar
                $documento              = new PedidoDocumentoDocente();
            }
            else{
                $id                     = $validate[0]->id;
                //Editar
                $documento              = PedidoDocumentoDocente::findOrFail($id);
            }
            $documento->institucion_id  = $institucion;
            $documento->cedula          = $cedulaDocente;
            $documento->doc_cedula      = $doc_cedula;
            $documento->doc_ruc         = $doc_ruc;
            $documento->id_periodo      = $id_periodo;
            $documento->save();
            if($documento){
                return ["status" => "1","message" => "Se guardo correctamente"];
            }else{
                return ["status" => "0","message" => "Se guardo correctamente"];
            }
        }
        return ["status" => "0","message" => "-"];
    }
    public function validateDocumento($institucion,$id_periodo){
        $validate = DB::SELECT("SELECT  * FROM pedidos_documentos_docentes pd
            WHERE pd.institucion_id = '$institucion'
            AND pd.id_periodo       = '$id_periodo'
            ORDER BY pd.id DESC
        ");
        return $validate;
    }
    //api:post/agregarDocumentosAnteriorPedido
    public function agregarDocumentosAnteriorPedido(Request $request){
        $pedido         = Pedidos::find($request->id_pedido);
        $fileName       = $request->doc_cedula;
        $fileNameRuc    = $request->doc_ruc;
        //CEDULA
        if($fileName == "null" || $fileName == null || $fileName == 'undefined'){
            $pedido->imagen             = null;
        }else{
            $pedido->imagen             = $fileName;
        }
        //RUC
        if($fileNameRuc == "null" || $fileNameRuc == null || $fileNameRuc == 'undefined'){
            $pedido->doc_ruc            = null;
        }else{
            $pedido->doc_ruc            = $fileNameRuc;
        }
        $pedido->save();
        if($pedido){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    //===FIN APIS DOCUMENTOS ANTERIORES====
    //===CARGAR FORMATO ANTERIORES VALORES=====
    public function cargarPeriodoFormatoPedidos(){
        $query = DB::SELECT("SELECT DISTINCT pf.id_periodo, pe.periodoescolar
        FROM pedidos_formato pf
        LEFT JOIN periodoescolar pe ON pf.id_periodo = pe.idperiodoescolar
        ORDER BY pe.periodoescolar
        ");
        return $query;
    }
    //===FIN CARGAR FORMATO ANTERIORES VALORES===
     //===METODOS ROOT===
    //API:POST/updateBeneficiarios
    public function updateBeneficiarios(Request $request){
        if($request->todo){
            set_time_limit(6000000);
            ini_set('max_execution_time', 6000000);
            $beneficiarios                = json_decode($request->beneficiarios);
            foreach($beneficiarios as $key => $item){
                $porcentaje = Beneficiarios::findOrFail($item->id_beneficiario_pedido);
                $porcentaje->porcentaje_real = $item->porcentajeReal;
                $porcentaje->comision_real   = $item->valorPorcentajeReal;
                $porcentaje->save();
            }
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }
    }
    //api:post/actualizarPedido
    public function actualizarPedido(Request $request){
        if($request->changeRevisonNotificacion){
            return $this->changeRevisonNotificacion($request->id_pedido,$request->notificados);
        }
        if($request->actualizarCampo){
            $pedido =  DB::UPDATE("UPDATE pedidos
            SET `$request->campo` = ?
            WHERE `id_pedido`     = ?
            ",[$request->valor,$request->id_pedido]);
        }
        if($request->actualizarDosCampo){
            $pedido =  DB::UPDATE("UPDATE pedidos
            SET `$request->campo` = ?,
            `$request->campo2` = ?
            WHERE `id_pedido`     = ?
            ",[$request->valor,$request->valor2,$request->id_pedido]);
        }
        $getPedido =  Pedidos::findOrFail($request->id_pedido);
        $contrato  =  $getPedido->contrato_generado;
        if($request->noCambios == "yes"){
        }
        //ingresar al historico
        $this->pedidosConAnticipo();
        if($request->campo == 'ifanticipo' && $request->valor == 1 && $contrato != null){
            $this->ingresarHistoricoAnticipo($request);
        }
        //guardar en historico
        $this->saveHistoricoCambios($request,$getPedido);
        return ["status" => "1", "message" =>"Se guardo correctamente"];
    }
    public function saveHistoricoCambios($request,$old_values){
        $historico = new PedidoHistoricoCambios();
        $historico->id_pedido       = $request->id_pedido;
        $historico->user_created    = $request->user_created;
        $historico->old_values      = $old_values;
        $historico->tipo            = $request->campo;
        $historico->save();
    }
    public function ingresarHistoricoAnticipo($request){
        $pedido                     = Pedidos::findOrFail($request->id_pedido);
        $fecha_generacion_contrato  = $pedido->fecha_generacion_contrato;
        DB::UPDATE("UPDATE pedidos_historico
        SET `fecha_generar_contrato` = '$fecha_generacion_contrato',
         `estado` = '2'
         WHERE `id_pedido` = '$request->id_pedido'
        ");
    }
    //===FIN METODOS ROOT==
    //api:post/changeRevisonNotificacion
    public function changeRevisonNotificacion($id_pedido,$notificados){
        $pedido                 = Pedidos::findOrFail($id_pedido);
        $pedido->notificados    = $notificados;
        $pedido->save();
        if($pedido){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
    }
    public function updateThePedido(Request $request){
        $id             = $request->id_pedido;
        //CAMPOS
        $campo          = $request->campo;
        $campo2         = $request->campo2;
        $campo3         = $request->campo3;
        $campo4         = $request->campo4;
        $campo5         = $request->campo5;
        $campo6         = $request->campo6;
        $campo7         = $request->campo7;
        //VALUES
        $valor          = $request->valor;
        $valor2         = $request->valor2;
        $valor3         = $request->valor3;
        $valor4         = $request->valor4;
        $valor5         = $request->valor5;
        $valor6         = $request->valor6;
        $valor7         = $request->valor7;
        //format
        $dataToUpdate   = [];
        //PROCESS====
        if($request->unCampo){
            $fieldsToUpdate = [$campo];
            $valuesToUpdate = [$valor];
        }
        if($request->actualizarDosCampo){
            $fieldsToUpdate = [$campo, $campo2,];
            $valuesToUpdate = [$valor, $valor2,];
        }
        if ($request->actualizarTresCampo) {
            $fieldsToUpdate = [$campo, $campo2, $campo3];
            $valuesToUpdate = [$valor, $valor2, $valor3];
        }
        if($request->actualizarSieteCampo){
            $fieldsToUpdate = [$campo, $campo2, $campo3, $campo4 ,$campo5 ,$campo6, $campo7];
            $valuesToUpdate = [$valor, $valor2, $valor3, $valor4, $valor5, $valor6, $valor7];
        }
        $dataToUpdate   = array_merge($dataToUpdate, array_combine($fieldsToUpdate, $valuesToUpdate));
        //aprobar anticipo
        if($request->anticipoAprobado) {
            $this->aprobarAnticipoPedidoPago($request->id_pedido,$request->valor);
            $this->aprobarAnticipo($request->id_pedido);
            $datos = [];
            $datos = (Object) [
                "anticipo_aprobado" => $request->valor,
                "id_pedido"         => $request->id_pedido,
                "user_created"      => $request->user_created,
                "id_group"          => $request->id_group
            ];
            $this->saveHistoricoAnticipos($datos);
        }
        DB::table('pedidos')
            ->where('id_pedido', $id)
            ->update($dataToUpdate);
        return ["status" => "1", "message" => "Se guardo correctamente"];
    }
}
