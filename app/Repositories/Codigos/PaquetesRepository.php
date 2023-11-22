<?php
namespace App\Repositories\Codigos;

use App\Models\CodigosPaquete;
use App\Repositories\BaseRepository;
use DB;
class  PaquetesRepository extends BaseRepository
{
    public function __construct(CodigosPaquete $PaqueteRepository)
    {
        parent::__construct($PaqueteRepository);
    }
    public function validateGestion($tipoProceso,$estadoPaquete,$validarA,$validarD,$item,$codigoActivacion,$codigoDiagnostico,$request){
        $periodo_id                  = $request->periodo_id;
        $errorA                      = 1;
        $errorD                      = 1;
        //====Activacion=====
        //validar si el codigo ya esta liquidado
        $ifLiquidadoA                = $validarA[0]->estado_liquidacion;
        //validar si el codigo no este bloqueado
        $ifBloqueadoA                = $validarA[0]->estado;
        //validar que el codigo de paquete sea nulo
        $ifcodigo_paqueteA           = $validarA[0]->codigo_paquete;
        //codigo de union
        $codigo_unionA               = strtoupper($validarA[0]->codigo_union);
        //liquidado regalado
        $ifliquidado_regaladoA       = $validarA[0]->liquidado_regalado;
        //validar que el periodo del estudiante sea 0 o sea igual al que se envia
        $ifid_periodoA               = $validarA[0]->id_periodo;
        //validar si el codigo no este leido
        $ifBcEstadoA                 = $validarA[0]->bc_estado;
        ///*//////===================Diagnostico=======*/////
        //validar si el codigo ya esta liquidado
        $ifLiquidadoD                = $validarD[0]->estado_liquidacion;
        //validar si el codigo no este bloqueado
        $ifBloqueadoD                = $validarD[0]->estado;
        //validar que el codigo de paquete sea nulo
        $ifcodigo_paqueteD           = $validarD[0]->codigo_paquete;
        //codigo de union
        $codigo_unionD               = strtoupper($validarD[0]->codigo_union);
        //liquidado regalado
        $ifliquidado_regaladoD       = $validarD[0]->liquidado_regalado;
        //validar que el periodo del estudiante sea 0 o sea igual al que se envia
        $ifid_periodoD               = $validarD[0]->id_periodo;
        //validar si el codigo no este leido
        $ifBcEstadoD                 = $validarD[0]->bc_estado;
        //obtener la factura si no envian nada le dejo lo mismo
        $facturaA                     = $validarA[0]->factura;
        if($request->factura == null || $request->factura == "")   $factura = $facturaA;
        else  $factura = $request->factura;
        //===PRIMERA VALIDACION====
        //error 0 => no hay error; 1 hay error
        //==SI EL PAQUETE ESTA CERRADO VALIDAR QUE SEA EL MISMO PAQUETE
        //estadoPaquete => 0 = utilizado; 1 abierto;
        if($estadoPaquete == 0){
            if( $ifcodigo_paqueteA == $item->codigoPaquete )  $errorA = 0;
            if( $ifcodigo_paqueteD == $item->codigoPaquete )  $errorD = 0;

        }else{
            if( $ifcodigo_paqueteA == null )                  $errorA = 0;
            if( $ifcodigo_paqueteD == null )                  $errorD = 0;
        }
        //si pasa la validacion voy la validacion por cada botton
        if($errorA == 0 && $errorD == 0){
            $errorA = 1;
            $errorD = 1;
            //=====USAN Y LIQUIDAN=========================
            if($tipoProceso == '0'){
                if(($ifid_periodoA  == $periodo_id || $ifid_periodoA == 0 ||  $ifid_periodoA == null  ||  $ifid_periodoA == "")  && ($ifBcEstadoA == '1')  && $ifLiquidadoA == '1' && $ifBloqueadoA !=2 &&  (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0"))  && $ifliquidado_regaladoA == '0') $errorA = 0;
                if(($ifid_periodoD  == $periodo_id || $ifid_periodoD == 0 ||  $ifid_periodoD == null  ||  $ifid_periodoD == "") && ($ifBcEstadoD == '1')  && $ifLiquidadoD == '1' && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && $ifliquidado_regaladoD == '0')     $errorD = 0;
                return ["errorA" => $errorA, "errorD" => $errorD, "factura" => $factura];
            }
            //======REGALADO NO ENTRA A LA LIQUIDACION============
            if($tipoProceso == '1'){
                if( $ifLiquidadoA == '1' && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') )  $errorA = 0;
                if( $ifLiquidadoD == '1' && (($codigo_unionD == $codigoActivacion)  || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')  ) $errorD = 0;
                return ["errorA" => $errorA, "errorD" => $errorD, "factura" => $factura];
            }
            //======REGALADO Y BLOQUEADO(No usan y no liquidan)=============
            if($tipoProceso == '2' ){
                if( $ifLiquidadoA !='0' && $ifBloqueadoA !=2 && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') )  $errorA = 0;
                if( $ifLiquidadoD !='0' && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion)  || ($codigo_unionD == null || $codigo_unionD == "" || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')  ) $errorD = 0;
                return ["errorA" => $errorA, "errorD" => $errorD, "factura" => $factura];
            }
            //===== BLOQUEADO(No usan y no liquidan)=============
            if($tipoProceso == '3'){
                if( $ifLiquidadoA !='0' && $ifBloqueadoA !=2 && (($codigo_unionA == $codigoDiagnostico) || ($codigo_unionA == null || $codigo_unionA == "" || $codigo_unionA == "0")) && ($ifliquidado_regaladoA == '0') )  $errorA = 0;
                if( $ifLiquidadoD !='0' && $ifBloqueadoD !=2 && (($codigo_unionD == $codigoActivacion) || ($codigo_unionD == null || $codigo_unionD == ""  || $codigo_unionD == "0")) && ($ifliquidado_regaladoD == '0')  ) $errorD = 0;
                return ["errorA" => $errorA, "errorD" => $errorD, "factura" => $factura];
            }
        }else{
                return ["errorA" => $errorA, "errorD" => $errorD, "factura" => $factura];
        }
    }
    public function procesoGestionBodega($numeroProceso,$codigo,$codigo_union,$request,$factura,$codigoPaquete){
        //numero proceso => 0 = usan y liquidan; 1 = venta lista; 2 = regalado; 3 regalado y bloqueado
        $estadoIngreso   = 0;
        //USAN Y LIQUIDAN
        if($numeroProceso == 0){
            $codigoU             = $this->updateCodigoUsanLiquidan($codigo_union,$codigo,$request,$factura,$codigoPaquete);
            if($codigoU) $codigo = $this->updateCodigoUsanLiquidan($codigo,$codigo_union,$request,$factura,$codigoPaquete);
        }
        //regalado
        if($numeroProceso == 1){
            $codigoU             = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo_union,$request,$factura,$codigoPaquete);
            if($codigoU) $codigo = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo,$request,$factura,$codigoPaquete);
        }
        //regalado y bloqueado
        if($numeroProceso == 2){
            $codigoU             = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo_union,$request,$factura,$codigoPaquete);
            if($codigoU) $codigo = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo,$request,$factura,$codigoPaquete);
        }
        //bloqueado
        if($numeroProceso == 3){
            $codigoU             = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo_union,$request,$factura,$codigoPaquete);
            if($codigoU) $codigo = $this->updateCodigotoRegaladoBloqueado($numeroProceso,$codigo,$request,$factura,$codigoPaquete);
        }
        if($codigo && $codigoU)  $estadoIngreso = 1;
        else                     $estadoIngreso = 2;
        return $estadoIngreso;
    }
    public function updateCodigoUsanLiquidan($codigo,$union,$request,$factura,$codigoPaquete){
        $fecha = date("Y-m-d H:i:s");
        $venta_estado = $request->venta_estado;
        $campoInstitucion = "";
        if($venta_estado == 1) { $campoInstitucion = "bc_institucion"; }
        else{                    $campoInstitucion = "venta_lista_institucion";  }
        $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion', '=', '1')
            ->where('bc_estado', '=', '1')
            //(SE QUITARA PARA AHORA EL ESTUDIANTE YA ENVIA LEIDO) ->where('bc_estado', '=', '1')
            ->update([
                'factura'                   => $factura,
                $campoInstitucion           => $request->institucion_id,
                'bc_periodo'                => $request->periodo_id,
                'venta_estado'              => $request->venta_estado,
                'codigo_union'              => $union,
                'codigo_paquete'            => $codigoPaquete,
                'fecha_registro_paquete'    => $fecha,
            ]);
        return $codigo;
    }
    public function updateCodigotoRegaladoBloqueado($numeroProceso,$codigo,$request,$factura,$codigoPaquete){
        $fecha = date("Y-m-d H:i:s");
        $venta_estado = $request->venta_estado;
        $campoInstitucion = "";
        if($venta_estado == 1) { $campoInstitucion = "bc_institucion"; }
        else{                    $campoInstitucion = "venta_lista_institucion";  }
        $arraySave = [];
        //regalado
        if($numeroProceso == '1'){
            $arraySave  = [
                'factura'                   => $factura,
                'bc_estado'                 => '1',
                'estado_liquidacion'        => '2',
                $campoInstitucion           => $request->institucion_id,
                'bc_periodo'                => $request->periodo_id,
                'venta_estado'              => $venta_estado,
                'codigo_paquete'            => $codigoPaquete,
                'fecha_registro_paquete'    => $fecha,
            ];
        }
        //regalado y bloqueado
         if($numeroProceso == '2'){
            $arraySave  = [
                'factura'                   => $factura,
                'bc_estado'                 => '1',
                'estado'                    => '2',
                'estado_liquidacion'        => '2',
                $campoInstitucion           => $request->institucion_id,
                'bc_periodo'                => $request->periodo_id,
                'venta_estado'              => $venta_estado,
                'codigo_paquete'            => $codigoPaquete,
                'fecha_registro_paquete'    => $fecha,
            ];
        }
        //bloqueado
        if($numeroProceso == '3'){
            $arraySave  = [
                'factura'                   => $factura,
                'bc_estado'                 => '1',
                'estado'                    => '2',
                $campoInstitucion           => $request->institucion_id,
                'bc_periodo'                => $request->periodo_id,
                'venta_estado'              => $venta_estado,
                'codigo_paquete'            => $codigoPaquete,
                'fecha_registro_paquete'    => $fecha,
            ];
        }

        //actualizar el primer codigo
        $codigo = DB::table('codigoslibros')
        ->where('codigo', '=', $codigo)
        ->where('bc_estado', '1')
        ->where('estado','<>', '2')
        ->where('estado_liquidacion','<>', '0')
        ->update($arraySave);
        return $codigo;
    }
}
?>
