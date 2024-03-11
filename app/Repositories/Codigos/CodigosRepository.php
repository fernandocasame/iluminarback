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
    public function procesoUpdateGestionBodega($numeroProceso,$codigo,$union,$request,$factura,$paquete=null,$tipoBodega=null){
        $venta_estado = $request->venta_estado;
        $campoInstitucion = "";
        if($venta_estado == 1) { $campoInstitucion = "bc_institucion"; }
        else{                    $campoInstitucion = "venta_lista_institucion";  }
        $fecha                   = date("Y-m-d H:i:s");
        $arrayResutado           = [];
        $arraySave               = [];
        $arrayUnion              = [];
        $arrayPaquete            = [];
        $arrayPaqueteInstitucion = [];
        $arrayPaqueteVentaEstado = [];
        //paquete
        if($paquete == null){ $arrayPaquete = []; }else{ $arrayPaquete  = [ 'codigo_paquete' => $paquete, 'fecha_registro_paquete'    => $fecha]; }
        //codigo de union
        if($union == null){ $arrayUnion  = [];    } else{ $arrayUnion  = [ 'codigo_union' => $union ]; }
        //para import de gestion de paquetes si envia la institucion
        if($request->institucion_id) { $arrayPaqueteInstitucion = [ 'bc_institucion' => $request->institucion_id, 'bc_periodo' => $request->periodo_id ];   }
        //para import de gestion de paquetes si el estado de venta directa
        if($venta_estado == 1)       { $arrayPaqueteVentaEstado = [ 'venta_estado' => $request->venta_estado ]; }
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
        //guia
        if($numeroProceso == '4'){
            $arraySave  = [
                'factura'               => $factura,
                $campoInstitucion       => $request->institucion_id,
                'bc_periodo'            => $request->periodo_id,
                'venta_estado'          => $venta_estado,
                'estado_liquidacion'    => 4,
            ];
        }
        //solo regalado
        //regalado sin institucion
        if($numeroProceso == '5'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado_liquidacion'    => '2',
            ];
            $arraySave = array_merge($arraySave, $arrayPaqueteInstitucion,$arrayPaqueteVentaEstado);
        }
        //regalados y bloqueados sin institucion
        if($numeroProceso == '6'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado'                => '2',
                'estado_liquidacion'    => '2',
            ];
            $arraySave = array_merge($arraySave, $arrayPaqueteInstitucion,$arrayPaqueteVentaEstado);
        }
        //bloqueado sin institucion
        if($numeroProceso == '7'){
            $arraySave  = [
                'factura'               => $factura,
                'bc_estado'             => '1',
                'estado'                => '2',
            ];
            $arraySave = array_merge($arraySave, $arrayPaqueteInstitucion,$arrayPaqueteVentaEstado);
        }
        //guia sin institucion
        if($numeroProceso == '8'){
            $arraySave  = [
                'factura'               => $factura,
                'estado_liquidacion'    => 4,
            ];
            $arraySave = array_merge($arraySave, $arrayPaqueteInstitucion,$arrayPaqueteVentaEstado);
        }
        //fusionar todos los arrays
        $arrayResutado = array_merge($arraySave, $arrayUnion,$arrayPaquete);
        //actualizar el primer codigo
        $codigo = DB::table('codigoslibros')
        ->where('codigo', '=', $codigo)
        ->where('estado_liquidacion','<>', '0')
        ->update($arrayResutado);
        return $codigo;
    }
    public function updateActivacion($codigo,$codigo_union,$objectCodigoUnion,$ifOmitirA=false,$todo){
        //si es regalado guia o bloqueado no se actualiza
        $arrayCombinar = [];
        if($ifOmitirA) { return 1; }
        $withCodigoUnion = 1;
        $estadoIngreso   = 0;
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($codigo_union == '0') $withCodigoUnion = 0;
        else                     $withCodigoUnion = 1;
        $arrayLimpiar  = [
            'idusuario'                 => "0",
            'id_periodo'                => "0",
            'id_institucion'            => '',
            'bc_estado'                 => '1',
            'estado'                    => '0',
            'estado_liquidacion'        => '1',
            'venta_estado'              => '0',
            'bc_periodo'                => '',
            'bc_institucion'            => '',
            'bc_fecha_ingreso'          => null,
            'contrato'                  => '',
            'verif1'                    => null,
            'verif2'                    => null,
            'verif3'                    => null,
            'verif4'                    => null,
            'verif5'                    => null,
            'verif6'                    => null,
            'verif7'                    => null,
            'verif8'                    => null,
            'verif9'                    => null,
            'verif10'                   => null,
            'venta_lista_institucion'   => '0',
            'porcentaje_descuento'      => '0',
            'factura'                   => null,
            'liquidado_regalado'        => '0'
        ];
        $arrayPaquete = [
            'codigo_paquete'            => null,
            'fecha_registro_paquete'    => null,
        ];
        if($todo == 1) { $arrayCombinar = array_merge($arrayLimpiar, $arrayPaquete); }
        else           { $arrayCombinar = $arrayLimpiar;}

        //si hay codigo de union lo actualizo
        if($withCodigoUnion == 1){
            //VALIDO SI NO EXISTE EL CODIGO DE UNION LO MANDO COMO ERROR
            if(count($objectCodigoUnion) == 0){
                //no se ingreso
                return 2;
            }
            $codigoU = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo_union)
            ->where('estado_liquidacion',   '<>', '0')
            // ->where('bc_estado',            '=', '1')
            ->update($arrayCombinar);
            //si el codigo de union se actualiza actualizo el codigo
            //if($codigoU){
                //actualizar el primer codigo
                $codigo = DB::table('codigoslibros')
                ->where('codigo', '=', $codigo)
                ->where('estado_liquidacion',   '<>', '0')
                // ->where('bc_estado',            '=', '1')
                ->update($arrayCombinar);
            //}
        }else{
            //actualizar el primer codigo
            $codigo = DB::table('codigoslibros')
            ->where('codigo', '=', $codigo)
            ->where('estado_liquidacion',   '<>', '0')
            // ->where('bc_estado',            '=', '1')
            ->update($arrayCombinar);
        }
        //con codigo union
        ///estadoIngreso => 1 = ingresado; 2 = no se puedo ingresar el codigo de union;
        if($withCodigoUnion == 1){
            if($codigo && $codigoU)  $estadoIngreso = 1;
            else                     $estadoIngreso = 1;
        }
        //si no existe el codigo de union
        if($withCodigoUnion == 0){
            if($codigo)              $estadoIngreso = 1;
        }
        return $estadoIngreso;
    }
}
