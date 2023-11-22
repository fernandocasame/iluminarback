<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Models\Distribuidor\DistribuidorTemporada;
use App\Models\Models\Pagos\DistribuidorHistorico;
use App\Models\Models\Pagos\VerificacionHistorico;
use App\Models\Models\Pagos\VerificacionPago;
use App\Models\Models\Pagos\VerificacionPagoDetalle;
use App\Models\Temporada;
use App\Models\Verificacion;
use App\Repositories\PedidosPagosRepository;
use DB;
use Illuminate\Http\Request;

class PedidosPagosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public $pagoRepository;
    public function __construct(PedidosPagosRepository $repositorio)
    {
     $this->pagoRepository = $repositorio;
    }
    //api:get/pedigo_Pagos
    public function index(Request $request)
    {
        //Para traer el listado de pagos
        if($request->ListadoListaPagos)     { return $this->ListadoListaPagos($request); }
        //para traer los valores de los pagos
        if($request->listadoPagos)          { return $this->listadoPagos($request); }
        //actualizar Valor Lista Pago
        if($request->actualizarValorPago)   { return $this->actualizarValorPago($request->verificacion_pago_id); }
        //validar si no hay un pago pendiente por aprobar
        if($request->validatePagoAbierto)   { return $this->validatePagoAbierto($request->contrato); }
        //traer los tipos de pagos facturacion
        if($request->tipoPagosFacturacion)  { return $this->pagoRepository->tipoPagosFacturacion(); }
    }
    public function ListadoListaPagos($request){
        $query = VerificacionPago::Where('contrato',$request->contrato)
        ->OrderBy('verificacion_pago_id','DESC')
        ->get();
        return $query;
    }
    public function listadoPagos($request){
        $query = $this->pagoRepository->getPagosXID($request->verificacion_pago_id);
        return $query;
    }
    public function actualizarValorPago($verificacion_pago_id){
        $valorActualizar = 0;
        $query = VerificacionPagoDetalle::Where('verificacion_pago_id',$verificacion_pago_id)->get();
        if(count($query) == 0) {  $valorActualizar = 0; }
        else{
            foreach($query as $key => $item){  $valorActualizar = $valorActualizar + $item->valor; }
        }
        $pago = VerificacionPago::findOrFail($verificacion_pago_id);
        $pago->valor_pago   = $valorActualizar;
        $pago->save();
        return $pago;
    }
    public function validatePagoAbierto($contrato){
        $query = VerificacionPago::Where('contrato',$contrato)
        ->Where('estado','0')->get();
        if(count($query) == 0){
            return ["status" => "1", "message" => "Se puede generar otro pago"];
        }else{
            return ["status" => "0", "message" =>  "Existe pagos pendientes por aprobar"];
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
    //api:post/pedigo_Pagos
    public function store(Request $request)
    {
        if($request->saveInfoPago){
            return $this->saveInfoPago($request);
        }
        if($request->aprobarPagoVerificacion){
            return $this->aprobarPagoVerificacion($request);
        }
    }
    public function saveInfoPago($request){
        //validar si ya se pago
        $temporada = Temporada::Where('contrato',$request->contrato)->Where('estado','1')->get();
        if(count($temporada) == 0)                      return ["status" => "0" ,"message" => "No existe el contrato en temporadas"];
        $periodo_id = $temporada[0]->id_periodo;
        if($periodo_id == null || $periodo_id == "")    return ["status" => "0" ,"message" => "No existe el período en temporadas"];
        //PROCESO
        $fecha = date("Y-m-d H:i:s");
        if($request->id > 0){
            $pago = VerificacionPago::findOrFail($request->id);
            $getEstadoPago = $pago->estado;
            if($getEstadoPago == 1) { return ["status" => "0", "message" => "El registro de pago ya esta aprobado no se puede realizar cambios"]; }
            if($getEstadoPago == 2) { return ["status" => "0", "message" => "El registro de pago esta desactivado no se puede realizar cambios"]; }
            $info = VerificacionPago::findOrFail($request->id);
        }else{
            $info = new VerificacionPago();
        }
        $info->contrato             = $request->contrato;
        $info->tipo_pago            = $request->tipo_pago;
        $info->fecha_pago           = $fecha;
        $info->observacion          = $request->observacion == null || $request->observacion == "null" ? null :$request->observacion;
        $info->user_created         = $request->user_created;
        $info->periodo_id           = $periodo_id;
        $info->nombres              = $request->nombres;
        $info->apellidos            = $request->apellidos;
        $info->email                = $request->email == null || $request->email == "null" ? null : $request->email;
        $info->ruc                  = $request->ruc   == null || $request->ruc   == "null" ? null : $request->ruc;
        $info->banco                = $request->banco;
        $info->tipo_cuenta          = $request->tipo_cuenta;
        $info->num_cuenta           = $request->num_cuenta;
        $info->save();
        if($info){
            return $info;
        }else{
            return ["status" => "0", "message" => "No se pudo guardar"];
        }
        return $info;
    }

    public function aprobarPagoVerificacion($request){
        $contrato               = $request->contrato;
        $user_created           = $request->user_created;
        $periodo_id             = $request->periodo_id;
        $tipo_pago              = $request->tipo_pago;
        $observacion            = "Aprobación de pago";
        $verificacion_pago_id   = $request->verificacion_pago_id;
        $info = VerificacionPago::findOrFail($verificacion_pago_id);
        $cantidadPago           = floatval($info->valor_pago);
        //0 => sin pagar ; 1 => pagado
        $EstadoPago             = $info->estado;
        if($EstadoPago == 1) { return ["status" => "0", "message" => "El pago ya ha sido aprobado anteriormente"]; }
        if($EstadoPago == 2) { return ["status" => "0", "message" => "El pago ha sido desactivado anteriormente"]; }
        //obtener los valores pendientes
        //estado_pago => 0 = nada; 1 = deuda; 2 por pagar; 3 = pagado;
        $query = Verificacion::Where('contrato',$contrato)
        ->Where('estado_pago','2')
        ->OrderBy('id','ASC')
        ->get();
        if(count($query) == 0) return ["status" => "0", "message" => "No hay valores para pagar"];
        foreach($query as $key => $item){
            $verificacion_id    = $item->id;
            $valor_liquidacion  = $item->valor_liquidacion;
            $valor_pendiente    = 0;
            if($cantidadPago > 0){
                $valor_pendiente = round(floatval($item->valor_liquidacion),2) -  round(floatval($item->abonado),2);
                $valorAbonado   = 0;
                $totalAbonado   = 0;
                $valorAbonado = $item->abonado;
                //Si el (valor_pendiente) a pagar es menor al valor del pago restante
                //coloco el valor pendiente como 0 ya pagado
                if($valor_pendiente < $cantidadPago){
                    $totalAbonado = $valorAbonado + $valor_pendiente;
                    $ingreso = DB::table('verificaciones')
                    ->where('id', $verificacion_id)
                    ->update([
                        "abonado"         => $totalAbonado,
                    ]);
                    if($ingreso){
                        $this->verificaciones_historico($contrato,$user_created,$observacion,$verificacion_id,$valor_pendiente,$valor_liquidacion,$periodo_id,$item);
                    }
                    $cantidadPago = $cantidadPago - $valor_pendiente;
                }
                //Si el (valor_pendiente) a pagar es mayor al valor del pago restante
                //Coloco la cantidad de pago a 0
                else{
                    $totalAbonado   = $valorAbonado + $cantidadPago;
                    $ingreso = DB::table('verificaciones')
                    ->where('id', $verificacion_id)
                    ->update([
                        "abonado"         => $totalAbonado,
                    ]);
                    if($ingreso){
                        $this->verificaciones_historico($contrato,$user_created,$observacion,$verificacion_id,$cantidadPago,$valor_liquidacion,$periodo_id,$item);
                    }
                    $cantidadPago   = 0;
                }
            }
        }
        //COLOR EL PAGO COMO PAGADO
        $info->estado = 1;
        $info->save();
        //SI EL PAGO ES DISTRIBUIDOR DESCUENTO AL DISTRIBUIDOR EL PAGO
        if($tipo_pago == 4){ $this->DescontarDistribuidor($request); }
        if($info){
            return ["status" => "1", "message" => "Se guardo correctamente"];
        }
    }
    public function DescontarDistribuidor($request){
        $contrato               = $request->contrato;
        $user_created           = $request->user_created;
        $verificacion_pago_id   = $request->verificacion_pago_id;
        $periodo_id             = $request->periodo_id;
        $query = $this->pagoRepository->getPagosXID($verificacion_pago_id);
        foreach($query as $key => $item){
            $saldo_anterior  = $item->saldo_actual;
            $saldo_nuevo     = $item->saldo_actual - $item->valor;
            $distribuidor_id = $item->distribuidor_temporada_id;
            $distribuidorT = DistribuidorTemporada::findOrFail($item->distribuidor_temporada_id);
            $distribuidorT->saldo_actual = $saldo_nuevo;
            $distribuidorT->save();
            if($distribuidorT){
                //HISTORICO DISTRIBUIDOR
                $this->saveHistoricoDistribuidor($distribuidor_id,$periodo_id,$saldo_anterior,$saldo_nuevo,$contrato,$user_created);
            }
        }
    }
    public function saveHistoricoDistribuidor($distribuidor_id,$periodo_id,$saldo_anterior,$saldo_nuevo,$contrato,$user_created){
        $historico = new DistribuidorHistorico();
        $historico->distribuidor_id = $distribuidor_id;
        $historico->periodo_id      = $periodo_id;
        $historico->saldo_anterior  = $saldo_anterior;
        $historico->saldo_actual    = $saldo_nuevo;
        $historico->contrato        = $contrato;
        $historico->user_created    = $user_created;
        $historico->save();
    }
    public function verificaciones_historico($contrato,$user_created,$observacion,$verificacion_id,$valor_abonado,$valor_liquidacion,$periodo_id,$old_values){
        $historico = new VerificacionHistorico();
        $historico->contrato            = $contrato;
        $historico->user_created        = $user_created;
        $historico->observacion         = $observacion;
        $historico->verificacion_id     = $verificacion_id;
        $historico->valor_abonado       = $valor_abonado;
        $historico->valor_liquidacion   = $valor_liquidacion;
        $historico->periodo_id          = $periodo_id;
        $historico->old_values          = $old_values;
        $historico->save();
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        //
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
}

//MANUAL DISTRIBUIDOR
//======== PROCESO   REGRESAR UN VALOR DESPUES DE AVER APROBADO=============================

//COLOQUE EL CAMPO "ESTADO" A CERO COMO NO PAGADO (TABLA VERIFICACIONES_PAGO CAMPO ESTADO)
//EL ABONO EN VERIFICACION DEL CONTRATO REGRESELO A LO ANTERIOR(TABLA VERIFICACIONES)
//EL VALOR ACTUAL DEL DISTRIBUIDOR REGRESELO A LO ANTERIOR(TABLA DISTRIBUIDOR_TEMPORADA)
//ELIMINE DEL HISTORICO (ELIMINE DISTRIBUIDOR HISTORICO)
//ELIMINE DEL HISTORICO (VERIFICACIONES_HISTORICO)
/******FIN PROCESO */
