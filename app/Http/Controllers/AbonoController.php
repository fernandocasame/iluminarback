<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\AbonoHistorico;
use App\Models\Cheque;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AbonoController extends Controller
{
    public function abono_registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notasOfactura' => 'required|in:0,1',
            'abono_valor' => 'required|numeric',
            'abono_totalNotas' => 'required|numeric',
            'abono_totalFacturas' => 'required|numeric',
            'user_created' => 'required',
            // 'institucion' => 'required',
            'periodo' => 'required',
            'abono_tipo' => 'required',
            'abono_documento' => 'required|unique:abono,abono_documento,' . $request->abono_id . ',abono_id',
            'abono_cuenta' => 'required',
            'abono_fecha' => 'required|date',
            'abono_empresa' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $message = '';
            foreach ($errors->all() as $error) {
                $message .= $error . ' ';
            }
            return response()->json([
                'status' => 0,
                'message' => $message,
                'errors' => $errors,
            ]);
        }

        \DB::beginTransaction();

        try {
            $abono = new Abono();
            $abono->abono_facturas = $request->notasOfactura == 0 ? $request->abono_valor : 0;
            $abono->abono_notas = $request->notasOfactura == 1 ? $request->abono_valor : 0;
            $abono->abono_totalNotas = round($request->abono_totalNotas, 2);
            $abono->abono_totalFacturas = round($request->abono_totalFacturas, 2);
            $abono->user_created = $request->user_created;
            // $abono->abono_institucion = $request->institucion;
            $abono->abono_periodo = $request->periodo;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_cuenta = $request->abono_cuenta;
            $abono->abono_fecha = $request->abono_fecha;
            $abono->abono_empresa = $request->abono_empresa;
            $abono->idClientePerseo = $request->idClientePerseo;
            $abono->clienteCodigoPerseo = $request->clienteCodigoPerseo;
            // $abono->tipo = $request->tipo;
            if (!$abono->save()) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al guardar el abono',
                ]);
            }
            $this->guardarAbonoHistorico($abono, 0);

            \DB::commit();

            // Responder con éxito
            return response()->json([
                'status' => 1,
                'message' => 'Se guardó correctamente',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack(); // Revertir la transacción en caso de excepción
            return response()->json([
                'status' => 0,
                'message' => 'Error al guardar el abono: ' . $e->getMessage(),
            ]);
        }
    }
    public function abono_pedido(Request $request){
        $query = DB::SELECT("SELECT SUM(CASE WHEN ab.abono_facturas <> 0.00 THEN ab.abono_facturas ELSE 0 END) AS abonofacturas,
            COUNT(CASE WHEN ab.abono_facturas <> 0.00 THEN 1 ELSE NULL END) AS totalAbonoFacturas,
            SUM(CASE WHEN ab.abono_notas <> 0.00 THEN ab.abono_notas ELSE 0 END) AS abononotas,
            COUNT(CASE WHEN ab.abono_notas <> 0.00 THEN 1 ELSE NULL END) AS totalAbonoNotas
            -- FROM abono ab WHERE ab.abono_institucion = '$request->institucion'
            FROM abono ab WHERE ab.abono_periodo = '$request->periodo'
            AND ab.abono_empresa = '$request->empresa'
            AND ab.idClientePerseo ='$request->cliente'
            GROUP BY ab.idClientePerseo
            HAVING SUM(ab.abono_facturas) <> 0 OR SUM(ab.abono_notas) <> 0;");
        return $query;
    }

    public function obtenerAbonos(Request $request)
    {
        $abonoNotas = DB::SELECT("SELECT * FROM abono bn
            WHERE bn.abono_notas > 0
            AND bn.abono_facturas = 0
            AND bn.idClientePerseo ='$request->cliente'
            -- AND bn.abono_institucion = $request->institucion
            AND bn.abono_periodo = $request->periodo
            AND bn.abono_empresa = '$request->empresa'");
        $abonosConNotas = $abonoNotas;

        $abonoFacturas = DB::SELECT("SELECT * FROM abono bn
            WHERE bn.abono_facturas > 0
            AND bn.abono_notas = 0
            AND bn.idClientePerseo ='$request->cliente'
            -- AND bn.abono_institucion = $request->institucion
            AND bn.abono_periodo = $request->periodo
            AND bn.abono_empresa = $request->empresa");
        $abonosConFacturas = $abonoFacturas;
        
        // abonos con abono_facturas > 0 y abono_notas = 0
        // $abonosConFacturas = Abono::where('abono_facturas', '>', 0)
        //                         ->where('abono_notas', '=', 0)
        //                         ->where('abono_institucion', $request->institucion)  // Aquí ajustamos el uso de $request->pedido
        //                         ->where('abono_periodo', $request->periodo)
        //                         ->get();

        // abonos con abono_notas > 0 y abono_facturas = 0
        // $abonosConNotas = Abono::where('abono_notas', '>', 0)
        //                     ->where('abono_facturas', '=', 0)
        //                     ->where('abono_institucion', $request->pedido)  // Aquí también ajustamos el uso de $request->pedido
        //                     ->where('abono_periodo', $request->periodo)
        //                     ->get();

        return [
            'abonos_con_facturas' => $abonosConFacturas,
            'abonos_con_notas' => $abonosConNotas
        ];
    }
    private function guardarAbonoHistorico($abono, $tipo)
    {
        if ($tipo == 3) {
            $abonoHistorico = new AbonoHistorico();
            $abonoHistorico->abono_id = $abono->abono_id;
            $abonoHistorico->ab_histotico_tipo = $tipo;
            $abonoHistorico->ab_historico_values = json_encode([
                'abono_fecha' => $abono->abono_fecha,
                'abono_porcentaje' => $abono->abono_porcentaje,
                'abono_documento' => $abono->abono_documento,
                'abono_valor_retencion' => $abono->abono_valor_retencion,
                'abono_tipo' => $abono->abono_tipo,
                // 'institucion' => $abono->abono_institucion,
                'cliente' => $abono->idClientePerseo,
                'periodo' => $abono->abono_periodo,
                'empresa' => $abono->abono_empresa,
                'user_created' => $abono->user_created,
            ]);
            $abonoHistorico->user_created = $abono->user_created;
        }else if ($tipo == 2) {
            $abonoHistorico = new AbonoHistorico();
            $abonoHistorico->abono_id = $abono->abono_id;
            $abonoHistorico->ab_histotico_tipo = $tipo;
            $abonoHistorico->ab_historico_values = json_encode([
                'abono_id' => $abono->abono_id,
                'notasOfactura' => $abono->abono_notas > 0 ? 'nota' : 'factura',
                'abono_valor' => $abono->abono_facturas + $abono->abono_notas,
                'abono_cheque_numero' => $abono->abono_cheque_numero,
                'abono_tipo' => $abono->abono_tipo,
                'abono_cheque_cuenta' => $abono->abono_cheque_cuenta,
                // 'institucion' => $abono->abono_institucion,
                'cliente' => $abono->idClientePerseo,
                'periodo' => $abono->abono_periodo,
                'empresa' => $abono->abono_empresa,
                'user_created' => $abono->user_created,
                // 'pedido' => $abono->abono_pedido,
            ]);
            $abonoHistorico->user_created = $abono->user_created;
        }else if($tipo == 0 || $tipo == 1){
            $abonoHistorico = new AbonoHistorico();
            $abonoHistorico->abono_id = $abono->abono_id;
            $abonoHistorico->ab_histotico_tipo = $tipo;
            $abonoHistorico->ab_historico_values = json_encode([
                'abono_id' => $abono->abono_id,
                'notasOfactura' => $abono->abono_notas > 0 ? 'nota' : 'factura',
                'abono_valor' => $abono->abono_facturas + $abono->abono_notas,
                'abono_totalNotas' => $abono->abono_totalNotas,
                'abono_tipo' => $abono->abono_tipo,
                'abono_totalFacturas' => $abono->abono_totalFacturas,
                // 'institucion' => $abono->abono_institucion,
                'cliente' => $abono->idClientePerseo,
                'periodo' => $abono->abono_periodo,
                'empresa' => $abono->abono_empresa,
                'user_created' => $abono->user_created,
                // 'pedido' => $abono->abono_pedido,
            ]);
            $abonoHistorico->user_created = $abono->user_created;
        }
       

        if (!$abonoHistorico->save()) {
            throw new \Exception('Error al guardar el registro histórico');
        }
    }
    public function eliminarAbono(Request $request)
    {
        \DB::beginTransaction();

        try {
            $abono = Abono::findOrFail($request->abono_id);

            $cheque = Cheque::where('chq_numero', $abono->abono_cheque_numero)
                        ->where('chq_cuenta', $abono->abono_cheque_cuenta)
                        ->first();

            if ($cheque) {
                $cheque->chq_estado = 2;
            }

            $this->guardarAbonoHistorico($abono, 1);
            $abono->delete();

            \DB::commit();

            return response()->json(['message' => 'Abono eliminado correctamente'], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el abono: ' . $e->getMessage()], 500);
        }
    }
    public function retencion_registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'abono_fecha' => 'required|date',
            'abono_porcentaje' => 'required',
            'abono_valor_retencion' => 'required|numeric',
            'institucion' => 'required',
            'periodo' => 'required',
            'abono_tipo' => 'required',
            'user_created' => 'required',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Error en la validación de datos',
                'errors' => $validator->errors(),
            ]);
        }

        \DB::beginTransaction();

        try {
            $abono = new Abono();
            $abono->abono_fecha = $request->abono_fecha;
            $abono->abono_institucion = $request->institucion;
            $abono->abono_periodo = $request->periodo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_valor_retencion = $request->abono_valor_retencion;
            $abono->abono_porcentaje = $request->abono_porcentaje;
            $abono->user_created = $request->user_created;
            if (!$abono->save()) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al guardar el abono',
                ]);
            }
            $this->guardarAbonoHistorico($abono, 3);

            \DB::commit();

            // Responder con éxito
            return response()->json([
                'status' => 1,
                'message' => 'Se guardó correctamente',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack(); // Revertir la transacción en caso de excepción
            return response()->json([
                'status' => 0,
                'message' => 'Error al guardar el abono: ' . $e->getMessage(),
            ]);
        }
    }

    public function cobro_cheque_registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notasOfactura' => 'required|in:0,1',
            'abono_fecha' => 'required|date',
            'abono_tipo' => 'required',
            'abono_cuenta' => 'required',
            'abono_documento' => 'required|unique:abono,abono_documento,' . $request->abono_id . ',abono_id',
            'abono_valor' => 'required|numeric',
            'abono_cheque_numero' => 'required|numeric',
            'abono_cheque_cuenta' => 'required|numeric',
            'abono_empresa' => 'required',           
            // 'institucion' => 'required',
            'periodo' => 'required',
            'user_created' => 'required',
            'estado' => 'required',
        ]);
    
        if ($validator->fails()) {
            $errors = $validator->errors();
            $message = '';
            foreach ($errors->all() as $error) {
                $message .= $error . ' ';
            }
            return response()->json([
                'status' => 0,
                'message' => $message,
                'errors' => $errors,
            ]);
        }
    
        \DB::beginTransaction();
    
        try {
            // Crear instancia de Abono
            $abono = new Abono();
            $abono->abono_fecha = $request->abono_fecha;
            // $abono->abono_institucion = $request->institucion;
            $abono->abono_periodo = $request->periodo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_empresa = $request->abono_empresa;
            $abono->user_created = $request->user_created;
            $abono->abono_facturas = $request->notasOfactura == 0 ? $request->abono_valor : 0;
            $abono->abono_notas = $request->notasOfactura == 1 ? $request->abono_valor : 0;
            $abono->abono_cuenta = $request->abono_cuenta;
            $abono->abono_cheque_numero = $request->abono_cheque_numero;
            $abono->abono_cheque_cuenta = $request->abono_cheque_cuenta;
            $abono->abono_cheque_banco = $request->abono_cheque_banco;            
            $abono->idClientePerseo = $request->idClientePerseo;
            $abono->clienteCodigoPerseo = $request->clienteCodigoPerseo;

            $cheque = Cheque::where('chq_numero', $request->abono_cheque_numero)
                            ->where('chq_cuenta', $request->abono_cheque_cuenta)
                            ->first();
    
            if (!$cheque) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'El cheque no existe o no se encontró con el número y cuenta proporcionados',
                ]);
            }
    
            // Guardar el abono
            if (!$abono->save()) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al guardar el abono',
                ]);
            }
    
            
            $cheque->chq_id_abono = $abono->abono_id;
            $cheque->chq_estado = $request->estado;
            $cheque->save();
    
            // Guardar historial de abono
            $this->guardarAbonoHistorico($abono, 2);
    
            \DB::commit();
    
            // Responder con éxito
            return response()->json([
                'status' => 1,
                'message' => 'Se guardó correctamente',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack(); // Revertir la transacción en caso de excepción
            return response()->json([
                'status' => 0,
                'message' => 'Error al guardar el abono: ' . $e->getMessage(),
            ]);
        }
    }
    public function estado_cheque(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cheque_id' => 'required|numeric',
            'estado' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Error en la validación de datos',
                'errors' => $validator->errors(),
            ]);
        }

        try {
            // Obtener el cheque por su ID
            $cheque = Cheque::findOrFail($request->cheque_id);

            // Cambiar el estado del cheque
            $cheque->chq_estado = $request->estado;
            $cheque->save();

            return response()->json([
                'status' => 1,
                'message' => 'Estado del cheque actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Error al actualizar el estado del cheque: ' . $e->getMessage(),
            ]);
        }
    }
    public function get_facturasNotasxParametro(Request $request){
        // $query = DB::SELECT("SELECT fv.* FROM f_venta fv
        // WHERE fv.institucion_id='$request->institucion'
        // AND fv.periodo_id='$request->periodo'
        // AND fv.id_empresa='$request->empresa'
        // AND fv.clientesidPerseo ='$request->cliente'
        // AND fv.est_ven_codigo <> 3");
        $query = DB::SELECT("SELECT fv.* FROM f_venta fv
        WHERE fv.periodo_id='$request->periodo'
        AND fv.id_empresa='$request->empresa'
        AND fv.estadoPerseo = 1
        AND fv.clientesidPerseo ='$request->cliente'
        AND fv.est_ven_codigo <> 3");
        return $query;
    }
    public function getClienteCobranzaxInstitucion(Request $request){
        
        $query = DB::SELECT("SELECT DISTINCT usu.* FROM f_venta fv
            LEFT JOIN usuario usu ON fv.ven_cliente  = usu.idusuario
            WHERE fv.id_ins_depacho = '$request->institucion'
            AND fv.periodo_id = '$request->periodo' ");
        return $query;
    }
    public function InstitucionesXCobranzas(Request $request)
    {
        $busqueda   = $request->busqueda;
        $id_periodo = $request->id_periodo;
        $query = $this->tr_getPuntosVenta($busqueda);
        //traer datos de la tabla f_formulario_proforma por id_periodo
        foreach($query as $key => $item){ 
            $query[$key]->datosClienteInstitucion = DB::SELECT("SELECT DISTINCT usu.cedula, CONCAT(usu.nombres,' ', usu.apellidos) nombres  
            FROM f_venta fv LEFT JOIN usuario usu ON fv.ven_cliente  = usu.idusuario
            WHERE fv.id_ins_depacho = '$item->idInstitucion'
            OR fv.institucion_id = '$item->idInstitucion'
            AND fv.periodo_id = '$request->id_periodo'"); }
        return $query;

        // $lista = DB::SELECT("SELECT i.idInstitucion, i.nombreInstitucion,i.punto_venta
        // FROM pedidos p
        // INNER JOIN institucion i ON i.idInstitucion = p.id_institucion
        // WHERE p.contrato_generado IS NOT NULL
        // AND i.nombreInstitucion LIKE '%$request->busqueda%'
        // AND p.id_periodo = '$request->id_periodo'");

        // $lista = DB::SELECT("SELECT i.idInstitucion, i.nombreInstitucion
        // FROM f_venta fv
        // INNER JOIN institucion i ON i.idInstitucion = fv.institucion_id
        // WHERE fv.clientesidPerseo = '$request->cliente'
        // AND fv.id_empresa = '$request->empresa'
        // AND fv.periodo_id = '$request->periodo'
        // GROUP BY i.idInstitucion,i.nombreInstitucion");

        // return $lista;
    }
    public function tr_getPuntosVenta($busqueda){

        $query = DB::SELECT("SELECT  i.idInstitucion, i.nombreInstitucion,i.ruc,i.email,i.telefonoInstitucion,
        i.direccionInstitucion,  c.nombre as ciudad
        FROM institucion i
        LEFT JOIN ciudad c ON i.ciudad_id = c.idciudad
        WHERE i.nombreInstitucion LIKE '%$busqueda%'");
        return $query;
    }
}
