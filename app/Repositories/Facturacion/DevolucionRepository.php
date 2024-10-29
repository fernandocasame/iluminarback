<?php
namespace App\Repositories\Facturacion;

use App\Models\CodigosLibrosDevolucionHeader;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
class  DevolucionRepository extends BaseRepository
{
    public function __construct(CodigosLibrosDevolucionHeader $devolucionHeader)
    {
        parent::__construct($devolucionHeader);
    }
    public function getDisponibilidadCodigoPrefactura($request){
        $pro_codigo     = $request->pro_codigo;
        $id_institucion = $request->id_institucion;
        $id_periodo     = $request->id_periodo;
        $id_empresa     = $request->id_empresa;
        // Obtener la cantidad facturada
        $facturado = \DB::table('f_detalle_venta_agrupado as dg')
            ->join('f_venta_agrupado as dv', 'dg.id_factura', '=', 'dv.id_factura')
            ->where('dg.pro_codigo', '=', $pro_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dg.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.periodo_id', $id_periodo)
            ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad), 0) as cantidad')
            ->value('cantidad');
        // Obtener la cantidad disponible en prefactura
        $disponiblePrefactura = \DB::table('f_detalle_venta as dg')
            ->join('f_venta as dv', 'dg.ven_codigo', '=', 'dv.ven_codigo')
            ->where('dg.pro_codigo', $pro_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dg.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.periodo_id', $id_periodo)
            ->where('dv.idtipodoc', '1')
            ->where('dv.estadoPerseo','0')
            ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad - dg.det_ven_dev), 0) as cantidad')
            ->value('cantidad') ?? 0;

        // Obtener la cantidad reservada creada
        $cantidadReservadaCreada = \DB::table('codigoslibros_devolucion_son as cs')
            ->join('f_venta as v', 'cs.documento', '=', 'v.ven_codigo')
            ->where('cs.id_empresa', $id_empresa)
            ->where('cs.id_cliente', $id_institucion)
            ->where('cs.id_periodo', $id_periodo)
            ->where('cs.pro_codigo', $pro_codigo)
            ->where('cs.estado', '0')
            ->where('v.idtipodoc', '1')
            ->count();
        // Realizar la operación
        $resultado = abs($disponiblePrefactura - $facturado) - $cantidadReservadaCreada;
        return $resultado;
    }
    public function getFacturaAvailable($request){
        $pro_codigo      = $request->pro_codigo;
        $id_institucion  = $request->id_institucion;
        $id_periodo      = $request->id_periodo;
        $id_empresa      = $request->id_empresa;
        // Obtener la cantidad facturada
        $disponiblePrefactura = \DB::table('f_detalle_venta as dg')
            ->join('f_venta as dv', 'dg.ven_codigo', '=', 'dv.ven_codigo')
            ->where('dg.pro_codigo', $pro_codigo)
            ->where('dv.id_empresa', $id_empresa)
            ->where('dg.id_empresa', $id_empresa)
            ->where('dv.institucion_id', $id_institucion)
            ->where('dv.periodo_id', $id_periodo)
            ->where('dv.idtipodoc', '1')
            ->where('dv.estadoPerseo', '0')
            ->selectRaw('COALESCE(SUM(dg.det_ven_cantidad - dg.det_ven_dev), 0) as cantidad, dv.ven_codigo, dg.pro_codigo')
            ->groupBy('dv.ven_codigo') // Necesario para usar agregados
            ->first();

        return $disponiblePrefactura;

    }
}
