<?php
namespace App\Repositories\Codigos;

use App\Models\CodigosLibros;
use App\Repositories\BaseRepository;
use DB;
class  CodigosRepository extends BaseRepository
{
    public function __construct(CodigosLibros $CodigoRepository)
    {
        parent::__construct($CodigoRepository);
    }
    public function procesoUpdateGestionBodega($numeroProceso,$codigo,$union,$request,$factura,$paquete=null){
        $venta_estado = $request->venta_estado;
        $campoInstitucion = "";
        if($venta_estado == 1) { $campoInstitucion = "bc_institucion"; }
        else{                    $campoInstitucion = "venta_lista_institucion";  }
        $fecha         = date("Y-m-d H:i:s");
        $arrayResutado = [];
        $arraySave     = [];
        $arrayUnion    = [];
        $arrayPaquete  = [];
        //paquete
        if($paquete == null){
            $arrayPaquete = [];
        }else{
            $arrayPaquete  = [ 'codigo_paquete' => $paquete, 'fecha_registro_paquete'    => $fecha];
        }
        //codigo de union
        if($union == null){
            $arrayUnion  = [];
        }else{
            $arrayUnion  = [ 'codigo_union' => $union ];
        }
        //Usan y liquidan
        if($numeroProceso == '0'){
            $arraySave = [
                'factura'           => $factura,
                $campoInstitucion   => $request->institucion_id,
                'bc_periodo'        => $request->periodo_id,
                'venta_estado'      => $request->venta_estado,
            ];
        }
        //regalado
        if($numeroProceso == '1'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado_liquidacion'    => '2',
                $campoInstitucion       => $request->institucion_id,
                'bc_periodo'            => $request->periodo_id,
                'venta_estado'          => $venta_estado,
            ];
        }
        //regalado y bloqueado
         if($numeroProceso == '2'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado'                => '2',
                'estado_liquidacion'    => '2',
                $campoInstitucion       => $request->institucion_id,
                'bc_periodo'            => $request->periodo_id,
                'venta_estado'          => $venta_estado,
            ];
        }
        //bloqueado
        if($numeroProceso == '3'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado'                => '2',
                $campoInstitucion       => $request->institucion_id,
                'bc_periodo'            => $request->periodo_id,
                'venta_estado'          => $venta_estado,
            ];
        }
        if($numeroProceso == '4'){
            $arraySave  = [
                'factura'               => $factura,
                $campoInstitucion       => $request->institucion_id,
                'bc_periodo'            => $request->periodo_id,
                'venta_estado'          => $venta_estado,
                'estado_liquidacion'    => 4,
            ];
        }
        //fusionar todos los arrays
        $arrayResutado = array_merge($arraySave, $arrayUnion,$arrayPaquete);
        //actualizar el primer codigo
        $codigo = DB::table('codigoslibros')
        ->where('codigo', '=', $codigo)
        ->where('estado','<>', '2')
        ->where('estado_liquidacion','<>', '0')
        ->update($arrayResutado);
        return $codigo;
    }
}
