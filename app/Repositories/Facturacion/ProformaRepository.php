<?php
namespace App\Repositories\Facturacion;

use App\Models\_14Producto;
use App\Models\Proforma;
use App\Models\VentasHistoricoNotasMove;
use App\Repositories\BaseRepository;
use DB;
use Exception;

class  ProformaRepository extends BaseRepository
{
    public function __construct(Proforma $proforma)
    {
        parent::__construct($proforma);
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $institucion
     */
    public function listadoProformasAgrupadas($institucion){
        $query = DB::SELECT("SELECT * FROM f_venta v
        WHERE v.institucion_id = ?
        AND v.est_ven_codigo <> '3'
        AND v.doc_intercambio IS NULL
        ",[$institucion]);
        return $query;
    }
    public function listadoInstitucionesXVenta($periodo, $empresa, $tipoInstitucion)
    {
        // Usamos Query Builder para una mejor legibilidad y seguridad
        $query = DB::table('f_venta as v')
            ->distinct()  // Evitamos repetir resultados
            ->join('institucion as i', 'v.institucion_id', '=', 'i.idInstitucion')  // LEFT JOIN equivalente
            ->where('v.est_ven_codigo', '<>', '3')  // Aseguramos que no sea '3'
            ->whereNull('v.doc_intercambio')  // Aseguramos que sea NULL
            ->where('v.periodo_id', $periodo)  // Usamos los parámetros correctamente
            ->where('v.id_empresa', $empresa)
            ->where('i.punto_venta', $tipoInstitucion)  // Filtro adicional de la tabla 'institucion'
            ->select('v.institucion_id', 'i.nombreInstitucion')  // Campos que deseas obtener
            ->orderBy('i.nombreInstitucion', 'asc')  // Ordenamos por nombre
            ->get();  // Obtenemos el resultado

        return $query;
    }

    public function listadoDocumentosVenta($periodo, $empresa, $tipoInstitucion, $institucion, $tipoDocumento = [1])
    {
        // Usamos el Query Builder para mayor legibilidad y seguridad
        $query = DB::table('f_detalle_venta as v')
            ->join('f_venta as v2', function ($join) use ($empresa) {
                $join->on('v2.ven_codigo', '=', 'v.ven_codigo')
                    ->where('v.id_empresa', '=', $empresa);
            })
            ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'v2.institucion_id')  
            ->leftJoin('libros_series as ls', 'ls.codigo_liquidacion', '=', 'v.pro_codigo')
            ->leftJoin('series as s', 's.id_serie', '=', 'ls.id_serie')
            ->where('v2.institucion_id', '=', $institucion)
            ->where('v2.est_ven_codigo', '<>', '3')
            ->whereNull('v2.doc_intercambio')
            ->where('i.punto_venta', '=', $tipoInstitucion)
            ->where('i.idInstitucion', '=', $institucion)
            ->where('v2.id_empresa', '=', $empresa)
            ->where('v2.periodo_id', '=', $periodo)
            ->whereIn('v2.idtipodoc', $tipoDocumento)
            ->select('v.*','s.nombre_serie')  // Seleccionamos los campos necesarios
            ->orderBy('v.pro_codigo', 'asc')
            ->get();  // Ejecutamos la consulta y obtenemos los resultados

        return $query;
    }
    public function listadoDocumentosAgrupado($periodo, $empresa, $tipoInstitucion, $institucion)
    {
        $query = DB::table('f_detalle_venta_agrupado as v')
        ->select('v.*','s.nombre_serie')
        ->leftJoin('f_venta_agrupado as v2', function ($join) {
            $join->on('v2.id_factura', '=', 'v.id_factura')
                 ->on('v.id_empresa', '=', 'v2.id_empresa');
        })
        ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'v2.institucion_id')
        ->leftJoin('libros_series as ls', 'ls.codigo_liquidacion', '=', 'v.pro_codigo')
        ->leftJoin('series as s', 's.id_serie', '=', 'ls.id_serie')
        ->where('v2.institucion_id', '=', $institucion)
        ->where('i.punto_venta', '=', $tipoInstitucion)
        ->where('i.idInstitucion', '=', $institucion)
        ->where('v2.periodo_id', '=', $periodo)
        ->where('v2.estadoPerseo', '=', 1)
        ->where('v2.est_ven_codigo', '=', 0)
        ->where('v2.id_empresa', '=', $empresa)
        ->orderBy('v.pro_codigo', 'asc')
        ->get();

        return $query;
    }


    public function prefacturaValidaForDevolver($preFactura,$empresa){
        $getPreproforma    = DB::SELECT("SELECT * FROM f_venta v
        WHERE v.ven_codigo = '$preFactura'
        AND v.id_empresa   ='$empresa'
        ");
        if(empty($getPreproforma))    { return []; }
        foreach($getPreproforma as $key => $item){
            $query = DB::SELECT("SELECT * FROM f_venta_agrupado v
            WHERE v.id_factura = ?
            AND v.id_empresa = ?
            ",[$item->id_factura,$item->id_empresa]);
            // $query = DB::SELECT("SELECT * FROM f_venta_agrupado v
            // WHERE v.id_factura = ?
            // AND v.estadoPerseo = '1'
            // AND v.id_empresa = ?
            // ",[$item->id_factura,$item->id_empresa]);
            if(count($query) > 0){
                $getPreproforma[$key]->ifPedidoPerseo = 1;
            }else{
                $getPreproforma[$key]->ifPedidoPerseo = 0;
            }
        }
        $resultado = collect($getPreproforma);
        //filtrar por ifPedidoPerseo igual a 1
        $resultado = $resultado->where('ifPedidoPerseo','1')->all();
        return $resultado;
    }
    public function getNumeroDocumento($empresa){
        if($empresa == 1){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_Prolipa as cod from f_tipo_documento where tdo_nombre='PRE-FACTURA'");
        }else if ($empresa==3){
            $query1 = DB::SELECT("SELECT tdo_letra, tdo_secuencial_calmed as cod from f_tipo_documento where tdo_nombre='PRE-FACTURA'");
        }
        $getSecuencia = 1;
        if(!empty($query1)){
            $pre= $query1[0]->tdo_letra;
            $codi=$query1[0]->cod;
            $getSecuencia=(int)$codi+1;
            if($getSecuencia>0 && $getSecuencia<10){
                $secuencia = "000000".$getSecuencia;
            } else if($getSecuencia>9 && $getSecuencia<100){
                $secuencia = "00000".$getSecuencia;
            } else if($getSecuencia>99 && $getSecuencia<1000){
                $secuencia = "0000".$getSecuencia;
            }else if($getSecuencia>999 && $getSecuencia<10000){
                $secuencia = "000".$getSecuencia;
            }else if($getSecuencia>9999 && $getSecuencia<100000){
                $secuencia = "00".$getSecuencia;
            }else if($getSecuencia>99999 && $getSecuencia<1000000){
                $secuencia = "0".$getSecuencia;
            }else if($getSecuencia>999999 && $getSecuencia<10000000){
                $secuencia = $getSecuencia;
            }
        }

        return $secuencia;
    }
    public function saveHistoricoNotasMove($datos){
        $VentasHistoricoNotasMove                   = new VentasHistoricoNotasMove();
        $VentasHistoricoNotasMove->descripcion      = $datos->descripcion;
        $VentasHistoricoNotasMove->tipo             = $datos->tipo;
        $VentasHistoricoNotasMove->nueva_prefactura = $datos->nueva_prefactura;
        $VentasHistoricoNotasMove->cantidad         = $datos->cantidad;
        $VentasHistoricoNotasMove->id_periodo       = $datos->id_periodo;
        $VentasHistoricoNotasMove->id_empresa       = $datos->id_empresa;
        $VentasHistoricoNotasMove->observacion      = $datos->observacion;
        $VentasHistoricoNotasMove->user_created     = $datos->user_created;
        $VentasHistoricoNotasMove->save();
    }
    //aumentar stock en las notas y disminuir en las prefacturas
    public function sumaStock($datos, $noAfectarReserva = 0)
    {
        try {
            $codigo_liquidacion         = $datos->codigo_liquidacion;
            $proforma_empresa           = $datos->proforma_empresa;
            $valorNew                   = $datos->cantidad;
            $documentoPrefactura        = $datos->documentoPrefactura;

            // Obtener stock
            $getStock                   = _14Producto::obtenerProducto($codigo_liquidacion);
            if (!$getStock) {
                throw new Exception('Producto no encontrado');
            }
            $stockAnteriorReserva       = $getStock->pro_reservar;

            // Prolipa
            if ($proforma_empresa == 1) {
                if ($documentoPrefactura == 0) {
                    $stockEmpresa = $getStock->pro_stock;
                }
                if ($documentoPrefactura == 1) {
                    $stockEmpresa = $getStock->pro_deposito;
                }
            }

            // Calmed
            if ($proforma_empresa == 3) {
                if ($documentoPrefactura == 0) {
                    $stockEmpresa = $getStock->pro_stockCalmed;
                }
                if ($documentoPrefactura == 1) {
                    $stockEmpresa = $getStock->pro_depositoCalmed;
                }
            }

            $nuevoStockReserva          = $stockAnteriorReserva + $valorNew;
            $nuevoStockEmpresa          = $stockEmpresa + $valorNew;

            // Actualizar stock en la tabla de productos
            if ($noAfectarReserva == 1) {
                _14Producto::updateStockNoReserva($codigo_liquidacion, $proforma_empresa, $nuevoStockEmpresa, $documentoPrefactura);
            } else {
                _14Producto::updateStock($codigo_liquidacion, $proforma_empresa, $nuevoStockReserva, $nuevoStockEmpresa, $documentoPrefactura);
            }

        } catch (Exception $e) {
            // Manejar la excepción, logearla o lanzar una nueva
            throw new Exception('Error al procesar la suma de stock: ' . $e->getMessage());
        }
    }

    public function restaStock($datos, $noAfectarReserva = 0)
    {
        try {
            $codigo_liquidacion         = $datos->codigo_liquidacion;
            $proforma_empresa           = $datos->proforma_empresa;
            $valorNew                   = $datos->cantidad;
            $documentoPrefactura        = $datos->documentoPrefactura;

            // Obtener stock
            $getStock                   = _14Producto::obtenerProducto($codigo_liquidacion);
            if (!$getStock) {
                throw new Exception('Producto no encontrado');
            }
            $stockAnteriorReserva       = $getStock->pro_reservar;

            // Prolipa
            if ($proforma_empresa == 1) {
                if ($documentoPrefactura == 0) {
                    $stockEmpresa = $getStock->pro_stock;
                }
                if ($documentoPrefactura == 1) {
                    $stockEmpresa = $getStock->pro_deposito;
                }
            }

            // Calmed
            if ($proforma_empresa == 3) {
                if ($documentoPrefactura == 0) {
                    $stockEmpresa = $getStock->pro_stockCalmed;
                }
                if ($documentoPrefactura == 1) {
                    $stockEmpresa = $getStock->pro_depositoCalmed;
                }
            }

            $nuevoStockReserva          = $stockAnteriorReserva - $valorNew;
            $nuevoStockEmpresa          = $stockEmpresa - $valorNew;

            // Actualizar stock en la tabla de productos
            if ($noAfectarReserva == 1) {
                _14Producto::updateStockNoReserva($codigo_liquidacion, $proforma_empresa, $nuevoStockEmpresa, $documentoPrefactura);
            } else {
                _14Producto::updateStock($codigo_liquidacion, $proforma_empresa, $nuevoStockReserva, $nuevoStockEmpresa, $documentoPrefactura);
            }

        } catch (Exception $e) {
            // Aquí puedes manejar la excepción, logearla o incluso lanzar una nueva
            throw new Exception('Error al procesar la resta de stock: ' . $e->getMessage());
        }
    }

}
?>
