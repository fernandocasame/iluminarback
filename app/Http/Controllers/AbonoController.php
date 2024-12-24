<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\AbonoHistorico;
use App\Models\Cheque;
use App\Rules\UniqueAbonoDocument;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AbonoController extends Controller
{
    public function abono_registro(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'notasOfactura' => 'required|in:0,1',
            'abono_valor' => 'required|numeric',
            'abono_totalNotas' => 'required|numeric',
            'abono_totalFacturas' => 'required|numeric',
            'user_created' => 'required',
            'periodo' => 'required',
            'abono_tipo' => 'required',
            'abono_documento' => 'required',
            'abono_cuenta' => 'required',
            'abono_fecha' => 'required|date',
            'abono_empresa' => 'required',
            'abono_concepto' => 'required',
            'abono_ruc_cliente' => 'required',
        ]);

        // 'abono_documento' => 'required|unique:abono,abono_documento,' . $request->abono_id . ',abono_id',

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

        // Validación personalizada para el número de documento
        $existingAbono = DB::table('abono')
            ->where('abono_documento', $request->abono_documento)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existingAbono) {
            // Si el estado del abono existente es 0, no se permite guardar
            if ($existingAbono->abono_estado == 0) {
                // Obtener información del cliente para el mensaje de error
                $usuario = DB::table('usuario')
                    ->where('cedula', $existingAbono->abono_ruc_cliente)
                    ->select('nombres', 'apellidos')
                    ->first();

                $fullName = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'desconocido';

                return response()->json([
                    'status' => 0,
                    'message' => "El valor del campo documento ya está en uso con el Cliente $fullName.",
                ]);
            }
            // else{
            //     return ['si existe', $existingAbono];
            // }
        }

        \DB::beginTransaction();

        try {
            $abono = new Abono();
            $abono->abono_facturas = $request->notasOfactura == 0 ? $request->abono_valor : 0;
            $abono->abono_notas = $request->notasOfactura == 1 ? $request->abono_valor : 0;
            $abono->abono_totalNotas = round($request->abono_totalNotas, 2);
            $abono->abono_totalFacturas = round($request->abono_totalFacturas, 2);
            $abono->user_created = $request->user_created;
            $abono->abono_periodo = $request->periodo;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_cuenta = $request->abono_cuenta;
            $abono->abono_fecha = $request->abono_fecha;
            $abono->abono_empresa = $request->abono_empresa;
            $abono->abono_concepto = $request->abono_concepto;
            $abono->abono_ruc_cliente = $request->abono_ruc_cliente;
            $abono->idClientePerseo = $request->idClientePerseo;
            $abono->clienteCodigoPerseo = $request->clienteCodigoPerseo;

            if (!$abono->save()) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al guardar el abono',
                ]);
            }
            $this->guardarAbonoHistorico($abono, 0, $request->user_created);

            \DB::commit();

            // Responder con éxito
            return response()->json([
                'status' => 1,
                'message' => 'Se guardó correctamente',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'status' => 0,
                'message' => 'Error al guardar el abono: ' . $e->getMessage(),
            ]);
        }
    }
    public function abono_pedido(Request $request){
        $query = DB::SELECT("SELECT SUM(CASE WHEN ab.abono_facturas <> 0.00 AND ab.abono_estado = 0 THEN ab.abono_facturas ELSE 0 END) AS abonofacturas,
            COUNT(CASE WHEN ab.abono_facturas <> 0.00 THEN 1 ELSE NULL END) AS totalAbonoFacturas,
            SUM(CASE WHEN ab.abono_notas <> 0.00 AND ab.abono_estado = 0 THEN ab.abono_notas ELSE 0 END) AS abononotas,
            COUNT(CASE WHEN ab.abono_notas <> 0.00 THEN 1 ELSE NULL END) AS totalAbonoNotas,
            SUM(CASE WHEN ab.abono_tipo = 3 AND ab.abono_valor_retencion <> 0.00 THEN ab.abono_valor_retencion ELSE 0 END) AS retencionValor,
            COUNT(CASE WHEN ab.abono_tipo = 3 AND ab.abono_valor_retencion <> 0.00 THEN 1 ELSE NULL END) AS totalRetencionValor
            -- FROM abono ab WHERE ab.abono_institucion = '$request->institucion'
            FROM abono ab WHERE ab.abono_periodo = '$request->periodo'
            AND ab.abono_empresa = '$request->empresa'
            AND ab.abono_ruc_cliente ='$request->cliente'
            GROUP BY ab.abono_ruc_cliente
            HAVING SUM(ab.abono_facturas) <> 0 OR SUM(ab.abono_notas) <> 0;");
        return $query;
    }

    public function obtenerAbonos(Request $request)
    {
        $abonoNotas = DB::SELECT("SELECT bn.*, cp.cue_pag_nombre FROM abono bn
            LEFT JOIN 1_1_cuenta_pago cp ON cp.cue_pag_codigo = bn.abono_cuenta
            WHERE bn.abono_notas > 0
            AND bn.abono_facturas = 0
            AND bn.idClientePerseo ='$request->cliente'
            -- AND bn.abono_institucion = $request->institucion
            AND bn.abono_periodo = $request->periodo
            AND bn.abono_empresa = '$request->empresa'
            ORDER BY bn.created_at DESC");
        $abonosConNotas = $abonoNotas;

        $abonoFacturas = DB::SELECT("SELECT bn.*, cp.cue_pag_nombre FROM abono bn
            LEFT JOIN 1_1_cuenta_pago cp ON cp.cue_pag_codigo = bn.abono_cuenta
            WHERE bn.abono_facturas > 0
            AND bn.abono_notas = 0
            AND bn.idClientePerseo ='$request->cliente'
            -- AND bn.abono_institucion = $request->institucion
            AND bn.abono_periodo = $request->periodo
            AND bn.abono_empresa = $request->empresa
            ORDER BY bn.created_at DESC");
        $abonosConFacturas = $abonoFacturas;

        $abonosAll = DB::SELECT("SELECT bn.*, cp.cue_pag_nombre FROM abono bn
            LEFT JOIN 1_1_cuenta_pago cp ON cp.cue_pag_codigo = bn.abono_cuenta
            WHERE bn.idClientePerseo ='$request->cliente'
            -- AND bn.abono_institucion = $request->institucion
            AND bn.abono_periodo = $request->periodo
            AND bn.abono_empresa = $request->empresa
            ORDER BY bn.created_at DESC");

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
            'abonos_con_notas' => $abonosConNotas,
            'abonos_all' => $abonosAll
        ];
    }
    private function guardarAbonoHistorico($abono, $tipo, $usuario)
    {
        $tipoAbono = '';
        if ($tipo == 3) {
            $tipoAbono = 'retencion';
        } elseif ($tipo == 2) {
            $tipoAbono = 'Create Abono Cheque';
        } elseif ($tipo == 0) {
            $tipoAbono = 'Create Abono';
        } elseif ($tipo == 1) {
            $tipoAbono = 'Delete Abono';
        } elseif ($tipo == 4) {
            $tipoAbono = 'Edit Abono';
        } elseif ($tipo == 5) {
            $tipoAbono = 'Cancellation Abono';
        }

        $datosAbono = [
            'notasOfactura' => $abono->abono_notas > 0 ? 'nota' : 'factura',
            'tipo' => $tipoAbono,
            'responsable' => $usuario,
        ];

        // Añadir propiedades del objeto $abono al array $datosAbono
        $datosAbono = array_merge($datosAbono, get_object_vars($abono));

        $abonoHistorico = new AbonoHistorico();
        $abonoHistorico->abono_id = $abono->abono_id;
        $abonoHistorico->ab_histotico_tipo = $tipo;
        $abonoHistorico->ab_historico_values = json_encode($datosAbono);
        $abonoHistorico->user_created = $abono->user_created;

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

            $this->guardarAbonoHistorico($abono, 1,$request->usuario);
            $abono->delete();

            \DB::commit();

            return response()->json(['message' => 'Abono eliminado correctamente'], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el abono: ' . $e->getMessage()], 500);
        }
    }
    public function anularAbono(Request $request)
    {
        // Validar los datos de entrada
        $validatedData = $request->validate([
            'abono_id' => 'required',
            'usuario' => 'required',
        ]);

        \DB::beginTransaction();

        try {
            // Buscar el abono en la base de datos
            $abono = Abono::findOrFail($validatedData['abono_id']);

            // Cambiar el estado del abono a anulado (asumiendo que 1 es el estado "anulado")
            $abono->abono_estado = 1;

            // Guardar en histórico (aquí asumimos que este método existe y funciona correctamente)
            $this->guardarAbonoHistorico($abono, 5, $validatedData['usuario']);

            // Guardar los cambios en la base de datos
            $abono->save();

            // Confirmar la transacción
            \DB::commit();

            // Responder con éxito
            return response()->json(['message' => 'Abono anulado correctamente'], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            \DB::rollBack();

            // Registrar el error (opcional, para depuración)
            \Log::error('Error al anular el abono: ' . $e->getMessage());

            // Responder con un mensaje de error genérico
            return response()->json(['error' => 'Error al anular el abono'], 500);
        }
    }



    // public function retencion_registro(Request $request)
    // {
    //     return 'ESTA EN PRUEBAS';
    //     $validator = Validator::make($request->all(), [
    //         'abono_fecha' => 'required|date',
    //         'abono_porcentaje' => 'required',
    //         'abono_valor_retencion' => 'required|numeric',
    //         'institucion' => 'required',
    //         'periodo' => 'required',
    //         'abono_tipo' => 'required',
    //         'user_created' => 'required',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Error en la validación de datos',
    //             'errors' => $validator->errors(),
    //         ]);
    //     }

    //     \DB::beginTransaction();

    //     try {
    //         $abono = new Abono();
    //         $abono->abono_fecha = $request->abono_fecha;
    //         $abono->abono_institucion = $request->institucion;
    //         $abono->abono_periodo = $request->periodo;
    //         $abono->abono_documento = $request->abono_documento;
    //         $abono->abono_tipo = $request->abono_tipo;
    //         $abono->abono_valor_retencion = $request->abono_valor_retencion;
    //         $abono->abono_porcentaje = $request->abono_porcentaje;
    //         $abono->user_created = $request->user_created;
    //         if (!$abono->save()) {
    //             \DB::rollBack();
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Error al guardar el abono',
    //             ]);
    //         }
    //         $this->guardarAbonoHistorico($abono, 3);

    //         \DB::commit();

    //         // Responder con éxito
    //         return response()->json([
    //             'status' => 1,
    //             'message' => 'Se guardó correctamente',
    //         ]);
    //     } catch (\Exception $e) {
    //         \DB::rollBack(); // Revertir la transacción en caso de excepción
    //         return response()->json([
    //             'status' => 0,
    //             'message' => 'Error al guardar el abono: ' . $e->getMessage(),
    //         ]);
    //     }
    // }

    public function retencion_registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'abono_fecha' => 'required|date',
            'abono_porcentaje' => 'required',
            'abono_valor_retencion' => 'required|numeric',
            'abono_periodo' => 'required',
            'abono_tipo' => 'required',
            'user_created' => 'required',
            'abono_clientePerseo' => 'required',
            'clienteCodigoPerseo' => 'required',
            'abono_ruc_cliente' => 'required',

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
            // $abono->abono_institucion = $request->institucion;
            $abono->abono_periodo = $request->periodo;
            // $abono->abono_documento = $request->abono_documento;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_valor_retencion = $request->abono_valor_retencion;
            $abono->abono_porcentaje = $request->abono_porcentaje;
            $abono->idClientePerseo = $request->idClientePerseo;
            $abono->abono_empresa = $request->abono_empresa;
            $abono->abono_periodo = $request->abono_periodo;
            
            $abono->clienteCodigoPerseo = $request->clienteCodigoPerseo;
            $abono->abono_ruc_cliente = $request->abono_ruc_cliente;

            $abono->user_created = $request->user_created;
            if (!$abono->save()) {
                \DB::rollBack();
                return response()->json([
                    'status' => 0,
                    'message' => 'Error al guardar el abono',
                ]);
            }
            $this->guardarAbonoHistorico($abono, 3,$request->user_created);

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
            'abono_concepto' => 'required',
            'abono_documento' => 'required|unique:abono,abono_documento,' . $request->abono_id . ',abono_id',
            'abono_valor' => 'required|numeric',
            'abono_cheque_numero' => 'required|numeric',
            'abono_cheque_cuenta' => 'required|numeric',
            'abono_empresa' => 'required',
            // 'institucion' => 'required',
            'periodo' => 'required',
            'user_created' => 'required',
            'estado' => 'required',
            'abono_ruc_cliente' => 'required',
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
            $abono->abono_concepto = $request->abono_concepto;
            $abono->idClientePerseo = $request->idClientePerseo;
            $abono->clienteCodigoPerseo = $request->clienteCodigoPerseo;
            $abono->abono_ruc_cliente = $request->abono_ruc_cliente;

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
            $this->guardarAbonoHistorico($abono, 2,$request->user_created);

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
        AND fv.ruc_cliente REGEXP '$request->cliente'
        AND fv.est_ven_codigo <> 3");
        return $query;
    }
    public function get_facturasNotasAll(Request $request){
        // $query = DB::SELECT("SELECT fv.* FROM f_venta fv
        // WHERE fv.institucion_id='$request->institucion'
        // AND fv.periodo_id='$request->periodo'
        // AND fv.id_empresa='$request->empresa'
        // AND fv.clientesidPerseo ='$request->cliente'
        // AND fv.est_ven_codigo <> 3");
        $query = DB::SELECT("SELECT fv.*, ep.descripcion_corta
        FROM f_venta fv
        LEFT JOIN empresas ep ON ep.id = fv.id_empresa
        WHERE fv.periodo_id='$request->periodo'
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
            AND fv.periodo_id = '$request->id_periodo'
            AND fv.est_ven_codigo <> 3"); }
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
    public function traerCobros(Request $request){
        $cobros = DB::SELECT("SELECT * FROM abono ab
        WHERE ab.abono_periodo = $request->periodo");
        $totalCobros = DB::SELECT("SELECT SUM(ab.abono_facturas) AS total_facturas, SUM(ab.abono_notas) AS total_notas,
        COUNT(CASE WHEN ab.abono_notas <> '0.00' THEN 1 END) AS numero_notas, COUNT(CASE WHEN ab.abono_facturas <> '0.00' THEN 1 END) AS numero_facturas
        FROM abono ab WHERE ab.abono_periodo = $request->periodo");
        return [
            'datosCobros' => $cobros,
            'totalCobros' => $totalCobros
        ];
    }

    public function getClienteLocalDocumentos(Request $request)
    {
        // Validar el request
        $request->validate([
            'cedula' => 'required|string',
        ]);

        // Obtener el valor de 'cedula'
        $cedula = $request->cedula;
        $periodo = $request->periodo;
        $empresa = $request->empresa;

        // Realizar las consultas
        $resultados = \DB::table('f_venta')
            ->where('est_ven_codigo', '<>', 3)
            ->whereIn('idtipodoc', [2, 4])
            ->where('ruc_cliente', $cedula)
            ->where('periodo_id', $periodo)
            ->where('id_empresa', $empresa)
            ->get();

        // Retornar los resultados como JSON
        return response()->json($resultados);
    }
    public function getClienteLocal(Request $request)
    {
        // Validar el request
        $request->validate([
            'cedula' => 'required|string',
        ]);

        // Obtener el valor de 'cedula'
        $cedula = $request->input('cedula');

        // Realizar las consultas
        $resultados = DB::table('usuario')
            ->where('cedula', $cedula)
            ->first(); // Si esperas solo un resultado, usa ->first()

        // Verificar si se encontraron resultados
        if ($resultados) {
            // Retornar los resultados como JSON
            return response()->json($resultados, 200);
        } else {
            // Retornar un mensaje de error si no se encontraron resultados
            return response()->json(['message' => 'No se encontraron resultados'], 404);
        }
    }
    public function modificarAbono(Request $request)
    {
        // Validar la solicitud
        $validator = Validator::make($request->all(), [
            'abono_fecha' => 'required|date',
            'abono_facturas' => 'nullable|numeric',
            'abono_tipo' => 'required|integer|in:0,1,2,3',
            'abono_documento' => 'nullable|string|max:100',
            'abono_concepto' => 'nullable|string|max:500',
            'abono_cuenta' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()
            ], 422);
        }

        // Iniciar transacción
        DB::beginTransaction();

        try {
             // Buscar el abono
             $abono = Abono::findOrFail($request->abono_id);

             if ($abono->abono_documento != $request->abono_documento) {
                 $existingAbono = DB::table('abono')
                     ->where('abono_documento', $request->abono_documento)
                     ->where('abono_id', '!=', $request->abono_id)
                     ->where('abono_estado', 0)
                     ->orderBy('created_at', 'desc')
                     ->first();

                 if ($existingAbono) {
                     return response()->json([
                         'status' => 0,
                         'message' => 'El número de documento ya está en uso por otro abono.',
                     ]);
                 }
             }

            // Actualizar los campos
            $abono->abono_fecha = $request->abono_fecha;
            $abono->abono_facturas = $request->abono_facturas;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_concepto = $request->abono_concepto;
            $abono->abono_cuenta = $request->abono_cuenta;
            // Aquí puedes añadir cualquier otro campo que desees actualizar

            // Guardar los cambios
            $abono->save();
            $this->guardarAbonoHistorico($abono, 4, $request->user_created);
            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Abono actualizado correctamente.',
                'data' => $abono
            ]);
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción
            DB::rollback();
            return response()->json([
                'status' => 0,
                'message' => 'Error al actualizar el abono: ' . $e->getMessage()
            ], 500);
        }
    }
    public function modificarAbonoNotas(Request $request)
    {
        // Validar la solicitud
        $validator = Validator::make($request->all(), [
            'abono_fecha' => 'required|date',
            'abono_notas' => 'nullable|numeric',
            'abono_tipo' => 'required|integer|in:0,1,2,3',
            'abono_documento' => 'required|string|max:100',
            'abono_concepto' => 'nullable|string|max:500',
            'abono_cuenta' => 'nullable|integer',
            'abono_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()
            ], 422);
        }

        // Iniciar transacción
        DB::beginTransaction();

        try {
            // Buscar el abono
            $abono = Abono::findOrFail($request->abono_id);

            if ($abono->abono_documento != $request->abono_documento) {
                $existingAbono = DB::table('abono')
                ->where('abono_documento', $request->abono_documento)
                ->where('abono_id', '!=', $request->abono_id)
                ->where('abono_estado', 0)
                ->orderBy('created_at', 'desc')
                ->first();

                if ($existingAbono) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'El número de documento ya está en uso por otro abono.',
                    ]);
                }
            }

            // Actualizar los campos
            $abono->abono_fecha = $request->abono_fecha;
            $abono->abono_notas = $request->abono_notas;
            $abono->abono_tipo = $request->abono_tipo;
            $abono->abono_documento = $request->abono_documento;
            $abono->abono_concepto = $request->abono_concepto;
            $abono->abono_cuenta = $request->abono_cuenta;
            // Aquí puedes añadir cualquier otro campo que desees actualizar

            // Guardar los cambios
            $abono->save();

            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'status' => 1,
                'message' => 'Abono actualizado correctamente.',
                'data' => $abono
            ]);
        } catch (\Exception $e) {
            // En caso de error, revertir la transacción
            DB::rollback();
            return response()->json([
                'status' => 0,
                'message' => 'Error al actualizar el abono: ' . $e->getMessage()
            ], 500);
        }
    }
    public function reporteAbonoVentas(Request $request) {
        // Ejecutar la primera consulta
        $reporte = DB::select("SELECT
                        fv.idtipodoc,
                        ft.tdo_id,
                        ft.tdo_nombre,
                        ep.descripcion_corta AS empresa,
                        ep.id AS id_empresa,
                        i.nombreInstitucion,
                        CONCAT(usu.nombres,' ',usu.apellidos) AS asesor,
                        i.idInstitucion,
                        i.punto_venta,
                        fv.institucion_id,
                        fv.ruc_cliente,
                        ROUND(SUM(fv.ven_subtotal), 2) AS subtotal_total,
                        ROUND(SUM(fv.ven_descuento), 2) AS descuento_total,
                        ROUND(SUM(fv.ven_valor), 2) AS valor_total
                    FROM f_tipo_documento ft
                    INNER JOIN f_venta fv ON fv.idtipodoc = ft.tdo_id
                    INNER JOIN institucion i ON i.idInstitucion = fv.institucion_id
                    INNER JOIN empresas ep ON ep.id = fv.id_empresa
                    LEFT JOIN usuario usu on usu.idusuario= i.asesor_id
                    WHERE fv.est_ven_codigo <> 3
                    AND fv.periodo_id = ?
                    GROUP BY
                        i.nombreInstitucion,
                        ft.tdo_nombre,
                        i.idInstitucion,
                        fv.institucion_id,
                        fv.idtipodoc,
                        ft.tdo_id,
                        fv.ruc_cliente,
                        ep.descripcion_corta,
                        i.punto_venta,
                        ep.id;", [$request->periodo]);

        // Recorrer cada registro del reporte para añadir el abono_total
        foreach ($reporte as $key => $registro) {
            // Construir la consulta de abono con condiciones específicas
            $abono_tipo = DB::select("SELECT
                                        CASE
                                            WHEN ? = 4 AND ? = 0 THEN COALESCE(SUM(ab.abono_notas), 0)
                                            WHEN ? = 3 AND ? = 0 THEN COALESCE(SUM(ab.abono_notas), 0)
                                            WHEN ? = 1 AND ? = 0 THEN COALESCE(SUM(ab.abono_facturas), 0)
                                            WHEN ? = 3 AND ? = 1 THEN COALESCE(SUM(ab.abono_notas), 0)
                                            WHEN ? = 4 AND ? = 1 THEN COALESCE(SUM(ab.abono_notas), 0)
                                            WHEN ? = 1 AND ? = 1 THEN COALESCE(SUM(ab.abono_facturas), 0)
                                            ELSE 0
                                        END AS abono_total
                                    FROM abono ab
                                    WHERE ab.abono_ruc_cliente = ?
                                    AND ab.abono_periodo = ?
                                    AND ab.abono_empresa = ?
                                    AND ab.abono_estado = 0", [
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->idtipodoc, $registro->punto_venta,
                                        $registro->ruc_cliente, $request->periodo,
                                        $registro->id_empresa
                                    ]);

            // Añadir el abono_total al registro
            $reporte[$key]->abono_total = $abono_tipo[0]->abono_total ?? 0;

            // //Devolucion por el Detalle Venta
            // $devolucion = DB::select("SELECT ROUND(SUM(dv.det_ven_dev * dv.det_ven_valor_u), 2) AS devolucion FROM f_detalle_venta dv
            // INNER JOIN f_venta fv ON fv.ven_codigo = dv.ven_codigo AND fv.id_empresa = dv.id_empresa
            // WHERE fv.ruc_cliente = '$registro->ruc_cliente'
            // AND dv.id_empresa = '$registro->id_empresa';");

            // Devolución por el Detalle Venta
            $devolucion = DB::select("SELECT
                    ROUND(SUM(
                        (dv.det_ven_dev * dv.det_ven_valor_u) * (1 - (fv.ven_desc_por / 100))
                    ), 2) AS devolucion
                FROM f_detalle_venta dv
                INNER JOIN f_venta fv ON fv.ven_codigo = dv.ven_codigo
                    AND fv.id_empresa = dv.id_empresa
                WHERE fv.ruc_cliente = '$registro->ruc_cliente'
                    AND dv.id_empresa = '$registro->id_empresa'
                    AND dv.det_ven_dev > 0
            ");

            // // Añadir la devolucion al registro
            $reporte[$key]->devolucion = $devolucion[0]->devolucion ?? 0;
        }
        // SELECT
        // fv.ruc_cliente, i.nombreInstitucion, ep.descripcion_corta AS empresa, fv.ven_tipo_inst,
        // ROUND(SUM(fv.ven_subtotal), 2) AS subtotal_total,
        // ROUND(SUM(fv.ven_descuento), 2) AS descuento_total, ROUND(SUM(fv.ven_valor), 2) AS valor_total,
        // ROUND(SUM(COALESCE(a.abono_facturas, 0) + COALESCE(a.abono_notas, 0)), 2) AS abono_total
        // FROM  f_venta fv
        // LEFT JOIN abono a ON a.abono_ruc_cliente = fv.ruc_cliente
        // LEFT JOIN institucion i ON fv.institucion_id = i.idInstitucion
        // LEFT JOIN empresas ep ON ep.id = fv.id_empresa
        // WHERE (fv.est_ven_codigo <> 3 AND fv.periodo_id = '$request->periodo')
        // OR (a.abono_estado = 0 AND a.abono_periodo = '$request->periodo')
        // GROUP BY fv.ruc_cliente, i.nombreInstitucion, ep.descripcion_corta, fv.ven_tipo_inst;

        return $reporte;
    }

    public function reporteAbonoVentasXD(Request $request)
    {
        // Paso 1: Obtener el reporte principal de f_venta
        $reporte = DB::table('f_tipo_documento as ft')
            ->join('f_venta as fv', 'fv.idtipodoc', '=', 'ft.tdo_id')
            ->join('institucion as i', 'i.idInstitucion', '=', 'fv.institucion_id')
            ->join('empresas as ep', 'ep.id', '=', 'fv.id_empresa')
            ->leftJoin('usuario as usu', 'usu.idusuario', '=', 'i.asesor_id')
            ->where('fv.est_ven_codigo', '<>', 3)
            ->where('fv.periodo_id', '=', $request->periodo)
            ->where('fv.ven_desc_por', '<',100)
            ->select(
                'fv.idtipodoc',
                'ft.tdo_id',
                'ft.tdo_nombre',
                'ep.descripcion_corta AS empresa',
                'ep.id AS id_empresa',
                'i.nombreInstitucion',
                DB::raw("CONCAT(usu.nombres, ' ', usu.apellidos) AS asesor"),
                'i.idInstitucion',
                'i.punto_venta',
                'fv.institucion_id',
                'fv.ruc_cliente',
                DB::raw('ROUND(SUM(fv.ven_subtotal), 2) AS subtotal_total'),
                DB::raw('ROUND(SUM(fv.ven_descuento), 2) AS descuento_total'),
                DB::raw('ROUND(SUM(fv.ven_valor), 2) AS valor_total'),
                DB::raw('GROUP_CONCAT(fv.ven_codigo) AS todos_los_documentos')
            )
            ->groupBy(
                'ft.tdo_id',
                'ft.tdo_nombre',
                'ep.id',
                'i.idInstitucion',
                'i.nombreInstitucion',
                'fv.institucion_id',
                'fv.idtipodoc',
                'fv.ruc_cliente',
                'ep.descripcion_corta',
                'i.punto_venta'
            )
            ->get();

        // Paso 2: Procesar cada registro para obtener el abono y devoluciones
        foreach ($reporte as $key => $registro) {
            // Obtener el abono total para cada registro
            $abono_tipo = DB::table('abono as ab')
                ->select(DB::raw("CASE
                    WHEN {$registro->idtipodoc} = 4 AND {$registro->punto_venta} = 0 THEN COALESCE(SUM(ab.abono_notas), 0)
                    WHEN {$registro->idtipodoc} = 3 AND {$registro->punto_venta} = 0 THEN COALESCE(SUM(ab.abono_notas), 0)
                    WHEN {$registro->idtipodoc} = 1 AND {$registro->punto_venta} = 0 THEN COALESCE(SUM(ab.abono_facturas), 0)
                    WHEN {$registro->idtipodoc} = 3 AND {$registro->punto_venta} = 1 THEN COALESCE(SUM(ab.abono_notas), 0)
                    WHEN {$registro->idtipodoc} = 4 AND {$registro->punto_venta} = 1 THEN COALESCE(SUM(ab.abono_notas), 0)
                    WHEN {$registro->idtipodoc} = 1 AND {$registro->punto_venta} = 1 THEN COALESCE(SUM(ab.abono_facturas), 0)
                    ELSE 0
                END AS abono_total"))
                ->where('ab.abono_ruc_cliente', '=', $registro->ruc_cliente)
                ->where('ab.abono_periodo', '=', $request->periodo)
                ->where('ab.abono_empresa', '=', $registro->id_empresa)
                ->where('ab.abono_estado', '=', 0)
                ->first();

            // Asignar el resultado al objeto
            $reporte[$key]->abono_total = $abono_tipo->abono_total*1 ?? 0;

            // Paso 3: Obtener el detalle de devolución
            $todos_los_documentos = explode(',', $registro->todos_los_documentos);
            $documentosDevoluciones = [];
            $valorTotalDevolucion = 0;

            foreach ($todos_los_documentos as $documento) {
                $detallesDevolucion = $this->obtenerDetallesDevolucionXD($documento, $registro->id_empresa);
                // Verificamos si obtenemos un arreglo de devoluciones
                if (is_array($detallesDevolucion) && !empty($detallesDevolucion)) {
                    // Fusionamos los detalles de devolución en el arreglo principal
                    $documentosDevoluciones = array_merge($documentosDevoluciones, $detallesDevolucion);
                    // Sumar el valor de "ValorConDescuento" de cada detalle de devolución
                    foreach ($detallesDevolucion as $devolucion) {
                        // Verificar si la propiedad ValorConDescuento existe antes de sumarla
                        if (isset($devolucion->ValorConDescuento)) {
                            $valorTotalDevolucion += $devolucion->ValorConDescuento;
                        }
                    }
                }
                // $detallesDevolucion = DB::table('codigoslibros_devolucion_header as cdh')
                //     ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
                //     ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')
                //     ->where('cls.documento', '=', $documento)
                //     ->groupBy('cls.documento', 'cls.id_empresa')
                //     ->select(
                //         'cls.documento',
                //         'cls.id_empresa',
                //         DB::raw('ROUND(SUM(cls.precio), 2) as total_precio')
                //     )
                //     ->get();

                //     foreach ($detallesDevolucion as $item) {
                //         // Consultar las ventas relacionadas con el documento
                //         $fVentas = DB::table('f_venta as fv')
                //             ->join('f_detalle_venta as fdv', function($join) {
                //                 $join->on('fdv.ven_codigo', '=', 'fv.ven_codigo')
                //                     ->on('fdv.id_empresa', '=', 'fv.id_empresa');
                //             })
                //             ->where('fv.ven_codigo', '=', $item->documento)
                //             ->where('fv.id_empresa', '=', $item->id_empresa)
                //             ->where('fv.est_ven_codigo', '<>', 3)
                //             ->where('fdv.det_ven_dev', '>', 0)  // Solo tomar los detalles con devolución
                //             ->first();  // Usamos `first()` para obtener el primer resultado

                //         // Si se encuentra una venta y tiene detalles de devolución
                //         if ($fVentas) {
                //             $descuento = $fVentas->ven_desc_por;
                //             $valorConDescuento = round(($item->total_precio - (($item->total_precio * $descuento) / 100)), 2);
                //             $devolucion_total += $valorConDescuento;  // Sumar el valor con descuento
                //         }
                //         // Si no hay detalles de devolución (fdv.det_ven_dev <= 0), no se hace nada
                //     }
            }

            // Asignar el total de la devolución
            // $reporte[$key]->devolucion = round($devolucion_total, 2);
            $reporte[$key]->devolucion = round($valorTotalDevolucion,2);
            $reporte[$key]->devolucion_todas = $documentosDevoluciones;
        }

        return $reporte;
    }
    public function obtenerDetallesDevolucionXD($documento, $empresa)
{
    // Ejecutamos la consulta en la base de datos para obtener los detalles de devolución
    $detallesDevolucion = DB::table('codigoslibros_devolucion_header as cdh')
        ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
        ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')  // LEFT JOIN para traer nombreInstitucion
        ->whereRaw('cdh.estado','<>', 0)
        ->where('cls.documento', '=', $documento)
        ->where('cls.id_empresa', '=', $empresa)
        ->groupBy('cdh.codigo_devolucion', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion')
        ->select('cdh.codigo_devolucion', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion', DB::raw('ROUND(SUM(cls.precio), 2) as total_precio'))
        ->get();

    // Convertimos la colección a un arreglo
    $detallesDevolucionArray = $detallesDevolucion->toArray();

    // Recorrer cada uno de los detalles de devolución
    foreach ($detallesDevolucionArray as $key => $item) {
        // Consultar las ventas relacionadas con el documento
        $fVentas = DB::table('f_venta as fv')
            ->join('f_detalle_venta as fdv', function($join) {
                $join->on('fdv.ven_codigo', '=', 'fv.ven_codigo')
                     ->on('fdv.id_empresa', '=', 'fv.id_empresa');
            })
            ->where('fv.ven_codigo', '=', $item->documento)
            ->where('fv.id_empresa', '=', $item->id_empresa)
            ->where('fv.est_ven_codigo', '<>', 3)
            ->where('fdv.det_ven_dev', '>', 0)
            ->first();  // Usamos `first()` para obtener el primer resultado

        // Verificamos si se obtuvo un resultado de la venta
        if ($fVentas) {
            $detallesDevolucionArray[$key]->descuento = $fVentas->ven_desc_por;
            // Calcular el valor con descuento para este detalle de devolución
            $detallesDevolucionArray[$key]->ValorConDescuento = round($item->total_precio - (($item->total_precio * $fVentas->ven_desc_por) / 100), 2);
        } else {
            // Si no hay una venta asociada, asignamos 0 al descuento y el valor con descuento
            $detallesDevolucionArray[$key]->descuento = 0;
            $detallesDevolucionArray[$key]->total_precio = round($item->total_precio, 2);
        }
    }

    // Retornamos los detalles de devolución como un arreglo
    return $detallesDevolucionArray;
}





    public function reporteVariacionVentas(Request $request) {
        $query = DB::SELECT("SELECT fv.ven_codigo, fv.ruc_cliente,  fv.institucion_id, fv.ven_tipo_inst, i.nombreInstitucion AS nombre, e.descripcion_corta AS empresa
        FROM f_venta fv
        INNER JOIN institucion i ON i.idInstitucion = fv.institucion_id
        INNER JOIN empresas e ON e.id = fv.id_empresa
        WHERE fv.est_ven_codigo <> 3
        AND fv.periodo_id = '$request->periodo'");
        return $query;
    }
    public function clientesAbonoNoDocumentos(Request $request) {
        // Validar el periodo recibido en la solicitud
        $request->validate([
            'periodo' => 'required|string',
        ]);

        // Primera consulta: obtener datos de abonos
        $query = DB::SELECT("SELECT
                    ab.abono_ruc_cliente AS `Ruc/Ci Cliente`,
                    e.nombre AS `Empresa`,
                    ab.abono_empresa,
                    COUNT(CASE WHEN ab.abono_notas > 0 THEN 1 END) AS `Abono(Notas)`,
                    COUNT(CASE WHEN ab.abono_facturas > 0 THEN 1 END) AS `Abono(Facturas)`,
                    COUNT(CASE WHEN f.idtipodoc IN (3, 4) THEN 1 END) AS `Documento(Notas)`,
                    COUNT(CASE WHEN f.idtipodoc = 1 THEN 1 END) AS `Documento(Prefacturas)`
                FROM
                    abono ab
                LEFT JOIN
                    f_venta f ON ab.abono_ruc_cliente = f.ruc_cliente
                              AND ab.abono_empresa = f.id_empresa
                INNER JOIN
                    empresas e ON ab.abono_empresa = e.id
                WHERE
                    ab.abono_estado = 0
                AND
                    ab.abono_periodo = ?
                GROUP BY
                    ab.abono_ruc_cliente, e.nombre, ab.abono_empresa
                HAVING
                    COUNT(f.ven_codigo) = 0;", [$request->periodo]);

        // Obtener RUC/CIs de los clientes
        $rucClientes = array_column($query, 'Ruc/Ci Cliente');
        $rucClientesStr = implode(',', array_map(function($ruc) {
            return "'" . $ruc . "'";
        }, $rucClientes));

        // Segunda consulta: obtener nombres y apellidos de los usuarios
        $usuarios = DB::SELECT("SELECT
                    c.cedula,
                    CONCAT(c.nombres, ' ', c.apellidos) AS `Nombres y Apellidos Cliente`
                FROM
                    usuario c
                WHERE
                    c.cedula IN ($rucClientesStr);");

        // Crear un arreglo asociativo para facilitar la combinación de resultados
        $usuariosAssoc = [];
        foreach ($usuarios as $usuario) {
            $usuariosAssoc[$usuario->cedula] = $usuario->{'Nombres y Apellidos Cliente'};
        }

        // Combinar resultados
        foreach ($query as &$item) {
            $item->{'Nombres y Apellidos Cliente'} = $usuariosAssoc[$item->{'Ruc/Ci Cliente'}] ?? null;
        }

        // Devolver la respuesta
        return response()->json($query);
    }


    public function getSalesAndPayments(Request $request)
    {
        $request->validate([
            'institucion' => 'required|integer',
            'periodo' => 'required|integer',
        ]);

        $institucionId = $request->institucion;
        $periodoId = $request->periodo;
        $ventas = DB::table('f_venta as fv')
            ->where('fv.institucion_id', $institucionId)
            ->where('fv.periodo_id', $periodoId)
            ->where('fv.est_ven_codigo','<>', 3)
            ->get();

        $result = [];
        $valorVentaNeta = 0;
        $valorVentaBruta = 0;
        $valorAbonoTotal = 0;
        $descuentoPorcentaje = 0;

        $rucs = [];
        $descuentos = [];
        $result['documentos'] = [];

        foreach ($ventas as $venta) {
            $valorVentaNeta += round($venta->ven_valor, 2);
            $valorVentaBruta += round($venta->ven_subtotal, 2);

            if ($venta->ven_desc_por) {
                if($venta->idtipodoc==1||$venta->idtipodoc==3||$venta->idtipodoc==4){
                    $descuentos[] = $venta->ven_desc_por;
                }
            }

            if (!in_array($venta->ruc_cliente, $rucs)) {
                $rucs[] = $venta->ruc_cliente;
            }

            $result['documentos'][] = [
                'ven_codigo' => $venta->ven_codigo,
                'ruc_cliente' => $venta->ruc_cliente,
                'ven_subtotal' => $venta->ven_subtotal,
                'ven_valor' => $venta->ven_valor,
                'descuento_porcentaje' => $venta->ven_desc_por,
                'valor_desvuento' => $venta->ven_descuento,
                'idtipodoc'=> $venta->idtipodoc,
            ];
        }

        foreach ($rucs as $ruc) {
            $valorAbono = DB::table('abono as ab')
                ->where('ab.abono_ruc_cliente', $ruc)
                ->sum(DB::raw('ab.abono_facturas + ab.abono_notas'));

            $valorAbono = round($valorAbono, 2);
            $valorAbonoTotal += $valorAbono;
        }

        $descuentosUnicos = array_unique($descuentos);

        $result['total_venta'] = $valorVentaNeta;
        $result['total_ventaBruta'] = $valorVentaBruta;
        $result['total_abono'] = $valorAbonoTotal;
        $result['porcentaje_descuento'] = !empty($descuentosUnicos) ? $descuentosUnicos[0] : 0;


        return response()->json($result);
    }

    public function obtenerVentas(Request $request)
    {
        $periodo = $request->input('periodo');

        // Ejecuta la consulta SQL
        $ventas = DB::table('f_venta as fv')
        ->leftJoin('1_4_tipo_venta as tv', 'tv.tip_ven_codigo', '=', 'fv.tip_ven_codigo')
        ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'fv.institucion_id')
        ->leftJoin('empresas as e', 'e.id', '=', 'fv.id_empresa')
        ->leftJoin('f_tipo_documento as ft', 'ft.tdo_id', '=', 'fv.idtipodoc')
        ->leftJoin('usuario as u', 'u.idusuario', '=', 'fv.ven_cliente')
        ->leftJoin('f_detalle_venta as fdv', function($join) {
            $join->on('fdv.ven_codigo', '=', 'fv.ven_codigo')
                ->on('fdv.id_empresa', '=', 'fv.id_empresa');
        })
        ->select(
            'fv.ven_codigo as codigo',
            'ft.tdo_nombre as documento',
            'tv.tip_ven_nombre as tipo_venta',
            'fv.ven_idproforma as proforma',
            'fv.ven_subtotal as valor_bruto',
            'i.nombreInstitucion as Lugar_despacho_documento',
            'e.descripcion_corta as empresa',
            DB::raw("CONCAT(u.nombres, ' ', u.apellidos) as cliente_documento"),
            DB::raw("SUM(fdv.det_ven_cantidad) as cantidad_detalles")
        )
        ->where('fv.est_ven_codigo', '<>', 3)
        ->where('fv.periodo_id', $periodo)
        ->groupBy(
            'fv.ven_codigo',
            'ft.tdo_nombre',
            'tv.tip_ven_nombre',
            'fv.ven_idproforma',
            'fv.ven_subtotal',
            'i.nombreInstitucion',
            'e.descripcion_corta',
            'u.nombres',
            'u.apellidos'
        )
        ->get();

        // Retorna los resultados en formato JSON
        return response()->json($ventas);
    }

    // public function obtenerDetallesDevolucion(Request $request)
    // {
    //     // Validamos que el parámetro 'documento' esté presente
    //     $request->validate([
    //         'documento' => 'required|string',
    //     ]);

    //     // Recuperamos el documento desde el parámetro de la solicitud
    //     $documento = $request->input('documento');
    //     $empresa = $request->input('empresa');

    //    // Ejecutamos la consulta en la base de datos para obtener los detalles de devolución
    //     $detallesDevolucion = DB::table('codigoslibros_devolucion_header as cdh')
    //     ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
    //     ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')  // LEFT JOIN para traer nombreInstitucion
    //     ->where('cdh.estado','<>', 0)
    //     ->where('cls.tipo_codigo', '=', 0)
    //     ->where('cls.documento', '=', $documento)
    //     ->where('cls.id_empresa', '=', $empresa)
    //     ->groupBy('cdh.codigo_devolucion', 'cdh.estado', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion')
    //     ->select('cdh.id', 'cdh.codigo_devolucion','cdh.estado', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion', DB::raw('ROUND(SUM(cls.precio), 2) as total_precio'))
    //     ->get();

    //     // Convertimos la colección a un arreglo
    //     $detallesDevolucionArray = $detallesDevolucion->toArray();

    //     // Recorrer cada uno de los detalles de devolución
    //     foreach ($detallesDevolucionArray as $key => $item) {
    //         // Consultar las ventas relacionadas con el documento
    //         $fVentas = DB::table('f_venta as fv')
    //             ->join('f_detalle_venta as fdv', function($join) {
    //                 $join->on('fdv.ven_codigo', '=', 'fv.ven_codigo')
    //                     ->on('fdv.id_empresa', '=', 'fv.id_empresa');
    //             })
    //             ->where('fv.ven_codigo', '=', $item->documento)
    //             ->where('fv.id_empresa', '=', $empresa)
    //             ->where('fv.est_ven_codigo', '<>', 3)
    //             ->where('fdv.det_ven_dev', '>', 0)
    //             ->first();  // Usamos `first()` para obtener el primer resultado

    //         // Verificamos si se obtuvo un resultado de la venta
    //         if ($fVentas) {
    //             $detallesDevolucionArray[$key]->descuento = $fVentas->ven_desc_por;
    //             // Calcular el valor con descuento para este detalle de devolución
    //             $detallesDevolucionArray[$key]->ValorConDescuento = round($item->total_precio - (($item->total_precio * $fVentas->ven_desc_por) / 100), 2);
    //         } else {
    //             // Si no hay una venta asociada, asignamos 0 al descuento y el valor con descuento
    //             $detallesDevolucionArray[$key]->descuento = 0;
    //             $detallesDevolucionArray[$key]->total_precio = round($item->total_precio, 2);
    //         }
    //     }
    //     $detallesDevolucionArray =collect($detallesDevolucionArray);

    //     foreach ($detallesDevolucionArray as $key => $item) {
    //         // Obtener los códigos relacionados con la devolución
    //         $codigos = DB::table('codigoslibros_devolucion_son as cls')
    //             ->join('codigoslibros_devolucion_header as cdh', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
    //             ->where('cdh.estado','<>', 0)
    //             ->where('cls.tipo_codigo', '=', 0)
    //             ->where('cls.codigoslibros_devolucion_id', '=', $item->id)
    //             ->where('cls.documento', '=', $item->documento)
    //             ->select('cls.codigo', 'cls.codigo_union','cls.pro_codigo', 'cls.tipo_codigo')
    //             ->get();

    //         $detallesDevolucionArray[$key]->codigos = $codigos;

    //         // Filtrar códigos únicos por pro_codigo (eliminamos duplicados)
    //         $codigosUnicos = $codigos->unique('pro_codigo');

    //         // Inicializamos un arreglo vacío para almacenar los detalles de venta
    //         $detalleVenta = [];

    //         // Iteramos sobre los códigos únicos para obtener los detalles de venta
    //         foreach ($codigosUnicos as $codigo) {
    //             // Consultamos el detalle de venta para cada código de producto
    //             $detallesDeVentaPorCodigo = DB::table('f_detalle_venta as fdv')
    //                 ->where('fdv.ven_codigo', '=', $item->documento)
    //                 ->where('fdv.id_empresa', '=', $item->id_empresa)
    //                 ->where('fdv.pro_codigo', '=', $codigo->pro_codigo)
    //                 ->select('fdv.pro_codigo','fdv.det_ven_cantidad', 'fdv.det_ven_dev', 'fdv.det_ven_valor_u')
    //                 ->get();

    //             // Agregar los detalles de venta encontrados a detalleVenta
    //             $detalleVenta = array_merge($detalleVenta, $detallesDeVentaPorCodigo->toArray());
    //         }

    //         // Asignamos todos los detalles de venta encontrados a la propiedad detalleVenta
    //         $detallesDevolucionArray[$key]->detalleVenta = $detalleVenta;
    //     }

    //     // Retornamos los detalles de devolución como un arreglo
    //     return $detallesDevolucionArray;
    // }

    public function obtenerDetallesDevolucion(Request $request)
    {
        // Validamos que el parámetro 'documento' esté presente
        $request->validate([
            'documento' => 'required|string',
        ]);

        // Recuperamos el documento desde el parámetro de la solicitud
        $documento = $request->input('documento');
        $empresa = $request->input('empresa');

        //tipo combo
        $detallesDevolucionTipo1 = DB::table('codigoslibros_devolucion_header as cdh')
            ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
            ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')  // LEFT JOIN para traer nombreInstitucion
            ->where('cdh.estado', '<>', 0)
            ->where('cls.documento', '=', $documento)
            ->where('cls.id_empresa', '=', $empresa)
            ->where('cls.tipo_codigo', '=', 1)  // Solo tipo_codigo = 1
            ->groupBy('cdh.codigo_devolucion', 'cdh.estado', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion')
            ->select(
                'cdh.id',
                'cdh.codigo_devolucion',
                'cdh.estado',
                'cls.documento',
                'cls.id_empresa',
                'cls.id_cliente',
                'i.nombreInstitucion',
                DB::raw('ROUND(SUM(cls.precio * cls.combo_cantidad_devuelta), 2) as total_precio') // Multiplicamos por combo_cantidad_devuelta
            )
            ->get();

        //tipo normal
        $detallesDevolucionTipo0 = DB::table('codigoslibros_devolucion_header as cdh')
            ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
            ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')  // LEFT JOIN para traer nombreInstitucion
            ->where('cdh.estado', '<>', 0)
            ->where('cls.documento', '=', $documento)
            ->where('cls.id_empresa', '=', $empresa)
            ->where('cls.tipo_codigo', '=', 0)  // Solo tipo_codigo = 0
            ->whereNull('cls.combo')
            ->groupBy('cdh.codigo_devolucion', 'cdh.estado', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion')
            ->select(
                'cdh.id',
                'cdh.codigo_devolucion',
                'cdh.estado',
                'cls.documento',
                'cls.id_empresa',
                'cls.id_cliente',
                'i.nombreInstitucion',
                DB::raw('ROUND(SUM(cls.precio), 2) as total_precio') // No multiplicamos, solo usamos el precio
            )
            ->get();
            
        $detallesDevolucion = $detallesDevolucionTipo1->merge($detallesDevolucionTipo0);



        // Convertimos la colección a un arreglo
        $detallesDevolucionArray = $detallesDevolucion->toArray();

        // Recorrer cada uno de los detalles de devolución
        foreach ($detallesDevolucionArray as $key => $item) {
            // Consultar las ventas relacionadas con el documento
            $fVentas = DB::table('f_venta as fv')
                ->join('f_detalle_venta as fdv', function($join) {
                    $join->on('fdv.ven_codigo', '=', 'fv.ven_codigo')
                        ->on('fdv.id_empresa', '=', 'fv.id_empresa');
                })
                ->where('fv.ven_codigo', '=', $item->documento)
                ->where('fv.id_empresa', '=', $empresa)
                ->where('fv.est_ven_codigo', '<>', 3)
                ->where('fdv.det_ven_dev', '>', 0)
                ->first();  // Usamos `first()` para obtener el primer resultado

            // Verificamos si se obtuvo un resultado de la venta
            if ($fVentas) {
                $detallesDevolucionArray[$key]->descuento = $fVentas->ven_desc_por;
                // Calcular el valor con descuento para este detalle de devolución
                $detallesDevolucionArray[$key]->ValorConDescuento = round(round($item->total_precio, 2) - round((round($item->total_precio, 2) * $fVentas->ven_desc_por) / 100, 2), 2);
            } else {
                // Si no hay una venta asociada, asignamos 0 al descuento y el valor con descuento
                $detallesDevolucionArray[$key]->descuento = 0;
                $detallesDevolucionArray[$key]->total_precio = round($item->total_precio, 2);
            }
        }
        $detallesDevolucionArray = collect($detallesDevolucionArray);

        foreach ($detallesDevolucionArray as $key => $item) {
            // Obtener los códigos relacionados con la devolución
            $codigos = DB::table('codigoslibros_devolucion_son as cls')
                ->join('codigoslibros_devolucion_header as cdh', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
                ->where('cdh.estado', '<>', 0)
                ->where('cls.codigoslibros_devolucion_id', '=', $item->id)
                ->where('cls.documento', '=', $item->documento)
                ->select('cls.codigo', 'cls.codigo_union', 'cls.pro_codigo', 'cls.tipo_codigo', 'cls.combo_cantidad_devuelta')
                ->get();

            // Aquí es donde ajustamos la lógica para repetir los códigos
            $codigosModificados = collect();

            foreach ($codigos as $codigo) {
                // Verificamos si el tipo de código es 1, y si es así, lo repetimos
                if ($codigo->tipo_codigo == 1) {
                    // Repetir el código por la cantidad de devuelta
                    for ($i = 0; $i < $codigo->combo_cantidad_devuelta; $i++) {
                        $codigosModificados->push([
                            'codigo' => $codigo->codigo,
                            'codigo_union' => $codigo->codigo_union,
                            'pro_codigo' => $codigo->pro_codigo,
                            'tipo_codigo' => $codigo->tipo_codigo
                        ]);
                    }
                } else {
                    // Si no es tipo_codigo 1, simplemente agregamos el código sin cambios
                    $codigosModificados->push([
                        'codigo' => $codigo->codigo,
                        'codigo_union' => $codigo->codigo_union,
                        'pro_codigo' => $codigo->pro_codigo,
                        'tipo_codigo' => $codigo->tipo_codigo
                    ]);
                }
            }

            // Asignamos los códigos modificados a la propiedad `codigos`
            $detallesDevolucionArray[$key]->codigos = $codigosModificados;

            // Filtrar códigos únicos por pro_codigo (eliminamos duplicados)
            $codigosUnicos = $codigosModificados->unique('pro_codigo');

            // Inicializamos un arreglo vacío para almacenar los detalles de venta
            $detalleVenta = [];

            // Iteramos sobre los códigos únicos para obtener los detalles de venta
            foreach ($codigosUnicos as $codigo) {
                // Verificar que el código es un objeto válido y tiene la propiedad 'pro_codigo'
                if (isset($codigo->pro_codigo)) {
                    // Consultamos el detalle de venta para cada código de producto
                    $detallesDeVentaPorCodigo = DB::table('f_detalle_venta as fdv')
                        ->where('fdv.ven_codigo', '=', $item->documento)
                        ->where('fdv.id_empresa', '=', $item->id_empresa)
                        ->where('fdv.pro_codigo', '=', $codigo->pro_codigo)
                        ->select('fdv.pro_codigo', 'fdv.det_ven_cantidad', 'fdv.det_ven_dev', 'fdv.det_ven_valor_u')
                        ->get();
            
                    // Agregar los detalles de venta encontrados a detalleVenta
                    $detalleVenta = array_merge($detalleVenta, $detallesDeVentaPorCodigo->toArray());
                }
            }

            // Asignamos todos los detalles de venta encontrados a la propiedad detalleVenta
            $detallesDevolucionArray[$key]->detalleVenta = $detalleVenta;
        }

        // Retornamos los detalles de devolución como un arreglo
        return $detallesDevolucionArray;
    }



    public function verifyCode(Request $request)
    {
        $codigo = $request->input('code');
        $referer = $request->input('referer');

        if (!$codigo) {
            return response()->json([
                'success' => false,
                'message' => 'El código no fue proporcionado.'
            ]);
        }

        if (!$referer) {
            return response()->json([
                'success' => false,
                'message' => 'El código no fue proporcionado.'
            ]);
        }

        $datosCodigoUrl = DB::table('librosinstituciones as li')
        ->where('li.li_codigo', $codigo)
        ->select('li_url')
        ->first();

        if (!$datosCodigoUrl) {
            return response()->json([
                'success' => false,
                'message' => 'El código no es válido.'
            ]);
        }

        if (!$datosCodigoUrl->li_url && $referer) {
            DB::table('librosinstituciones as li')
                ->where('li.li_codigo', $codigo)
                ->update([
                    'li_url' => $referer,
                ]);
        }else{

            if ($datosCodigoUrl->li_url !== $referer) {
                DB::table('librosinstituciones as li')
                    ->where('li.li_codigo', $codigo)
                    ->update([
                        'li_url_variacion' => $referer,
                    ]);
                return response()->json([
                    'success' => false,
                    'message' => 'La URL proporcionada es diferente, variación registrada.',
                ]);
            }

            DB::table('librosinstituciones as li')
            ->where('li.li_codigo', $codigo)
            ->where('li.li_url', $referer)
            ->increment('li_entradas');
        }

        $datosCodigo = DB::table('librosinstituciones as li')
            ->where('li.li_codigo', $codigo)
            ->where('li.li_url', $referer)
            ->where('p.estado', '=', '1')
            ->leftJoin('periodoescolar as p', 'p.idperiodoescolar', '=', 'li.li_periodo')
            ->select('li.li_idInstitucion', 'li.li_periodo')
            ->first();

        if ($datosCodigo) {
            return response()->json([
                'success' => true,
                'message' => 'Código verificado exitosamente.',
                'datos' => $datosCodigo,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'El código no es válido.',
            ]);
        }
    }

    public function getClienteDocumentos(Request $request){
        $cliente = $request->input('cliente');

        // Consulta los IDs de la institución para el cliente
        $clientesBase = DB::select("SELECT DISTINCT fv.institucion_id FROM f_venta fv
        WHERE fv.ruc_cliente = ?", [$cliente]);

        $descuentos = [];

        foreach($clientesBase as $key => $item){
            // Consulta los detalles de devoluciones con la validación
            $detallesDocumentoDevolucion = DB::table('codigoslibros_devolucion_header as cdh')
                ->join('codigoslibros_devolucion_son as cls', 'cdh.id', '=', 'cls.codigoslibros_devolucion_id')
                ->leftJoin('institucion as i', 'i.idInstitucion', '=', 'cdh.id_cliente')
                ->where('cls.id_cliente', '=', $item->institucion_id)
                // Validación de documento: null, vacío o 0
                ->where(function ($query) {
                    $query->whereNull('cls.documento')
                        ->orWhere('cls.documento', '')
                        ->orWhere('cls.documento', 0);
                })
                // Validación de id_empresa: null, vacío o 0
                ->where(function ($query) {
                    $query->whereNull('cls.id_empresa')
                        ->orWhere('cls.id_empresa', '')
                        ->orWhere('cls.id_empresa', 0);
                })
                // Agrupar los resultados
                ->groupBy('cdh.codigo_devolucion', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion')
                ->select('cdh.codigo_devolucion', 'cls.documento', 'cls.id_empresa', 'cls.id_cliente', 'i.nombreInstitucion', DB::raw('ROUND(SUM(cls.precio), 2) as total_precio'))
                ->get();

            // Almacenar los resultados
            $descuentos[$key] = $detallesDocumentoDevolucion;
        }

        // Retornar los descuentos
        return $descuentos;
    }

    public function getVentasComil(Request $request)
    {
        // Realizar la primera consulta para obtener los códigos
        $resultados = DB::select('SELECT DISTINCT
            f.institucion_id,
            i.nombreInstitucion,
            GROUP_CONCAT(DISTINCT CASE WHEN f.id_empresa = 1 THEN f.ven_codigo END) AS todos_ven_codigos_prolipa,
            GROUP_CONCAT(DISTINCT CASE WHEN f.id_empresa = 3 THEN f.ven_codigo END) AS todos_ven_codigos_calmed,
            GROUP_CONCAT(DISTINCT fv.pro_codigo) AS todos_pro_codigos,
            GROUP_CONCAT(DISTINCT cldh.codigo_devolucion) AS todos_codigos_devolucion
        FROM
            f_detalle_venta fv
        LEFT JOIN
            f_venta f ON f.ven_codigo = fv.ven_codigo AND f.id_empresa = fv.id_empresa
        LEFT JOIN
            institucion i ON i.idInstitucion = f.institucion_id
        LEFT JOIN
            libros_series ls ON ls.codigo_liquidacion = fv.pro_codigo
        LEFT JOIN
            libro l ON ls.idLibro = l.idlibro
        LEFT JOIN
            asignatura a ON l.asignatura_idasignatura = a.idasignatura
        LEFT JOIN
            series s ON ls.id_serie = s.id_serie
        LEFT JOIN
            codigoslibros_devolucion_son clds ON FIND_IN_SET(f.ven_codigo, clds.documento) > 0  AND fv.pro_codigo = clds.pro_codigo
        LEFT JOIN
            codigoslibros_devolucion_header cldh ON cldh.id = clds.codigoslibros_devolucion_id
        WHERE
            s.id_serie = 19
        AND
            f.periodo_id = 25
        AND
            (a.idasignatura IN (1138, 1139, 1140, 1141, 1142, 1143, 1144, 1145, 1146, 1147, 1149, 1151))
        GROUP BY
            f.institucion_id, i.nombreInstitucion');

        // Array para almacenar todos los detalles de todas las instituciones
        $todosDetallesFinales = [];
        $totalVentasProlipa = 0; // Sumar los detalles de Prolipa
        $totalVentasCalmed = 0;  // Sumar los detalles de Calmed
        $totalVentasGeneral = 0; // Sumar el total general (para todas las instituciones)

        // Procesar los resultados obtenidos
        foreach ($resultados as $resultado) {
            // Convertir los valores de ven_codigos en arreglos
            $prolipaVenCodigos = explode(',', $resultado->todos_ven_codigos_prolipa);
            $calmedVenCodigos = explode(',', $resultado->todos_ven_codigos_calmed);
            $proCodigos = explode(',', $resultado->todos_pro_codigos);

            // Inicializar los detalles para prolipa
            $prolipaDetalles = DB::table('f_detalle_venta as fv')
                ->select(
                    'fv.det_ven_codigo',
                    'fv.ven_codigo',
                    'fv.id_empresa',
                    'fv.pro_codigo',
                    'fv.det_ven_cantidad',
                    'fv.det_ven_valor_u',
                    'fv.det_ven_cantidad_despacho',
                    'fv.idProforma',
                    'fv.det_ven_dev',
                    DB::raw('(fv.det_ven_cantidad * fv.det_ven_valor_u) as total')
                )
                ->whereIn('fv.ven_codigo', $prolipaVenCodigos)
                ->where('fv.id_empresa', 1)
                ->whereIn('fv.pro_codigo', $proCodigos)
                ->get();

            // Inicializar los detalles para calmed
            $calmedDetalles = DB::table('f_detalle_venta as fv')
                ->select(
                    'fv.det_ven_codigo',
                    'fv.ven_codigo',
                    'fv.id_empresa',
                    'fv.pro_codigo',
                    'fv.det_ven_cantidad',
                    'fv.det_ven_valor_u',
                    'fv.det_ven_cantidad_despacho',
                    'fv.idProforma',
                    'fv.det_ven_dev',
                    DB::raw('(fv.det_ven_cantidad * fv.det_ven_valor_u) as total')
                )
                ->whereIn('fv.ven_codigo', $calmedVenCodigos)
                ->where('fv.id_empresa', 3)
                ->whereIn('fv.pro_codigo', $proCodigos)
                ->get();

            // Combinar ambos resultados para esta institución
            $todosDetalles = $prolipaDetalles->merge($calmedDetalles);

            // Sumar los valores de total para cada empresa
            foreach ($prolipaDetalles as $detalle) {
                $totalVentasProlipa += $detalle->total;
            }

            foreach ($calmedDetalles as $detalle) {
                $totalVentasCalmed += $detalle->total;
            }

            // Calcular el total general (acumulado)
            $totalVentasGeneral += $totalVentasProlipa + $totalVentasCalmed;

            // Agregar los detalles al array final
            $todosDetallesFinales[] = [
                'institucion' => $resultado->nombreInstitucion,
                'detalles' => $todosDetalles,
                'totalVentasProlipa' => $totalVentasProlipa, // Total de Prolipa
                'totalVentasCalmed' => $totalVentasCalmed,   // Total de Calmed
            ];

            // Resetear las sumas para la siguiente institución
            $totalVentasProlipa = 0;
            $totalVentasCalmed = 0;
        }

        // Agregar el total general a la respuesta final, fuera de los detalles de cada institución
        $response = [
            'totalVentasGeneral' => $totalVentasGeneral,
            'instituciones' => $todosDetallesFinales,
        ];

        // Devuelve todos los detalles de todas las instituciones y el total general
        return response()->json($response);
    }








}
