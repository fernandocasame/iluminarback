<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use App\Models\Cheque;

use DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BancoController extends Controller
{
    public function GetBanco_todo(){
        $query = DB::SELECT("SELECT * FROM 1_1_bancos");
        return $query;
    }
    public function GetCuentasXBanco(Request $request){
        $query = DB::SELECT("SELECT * FROM 1_1_cuenta_pago cpg
    INNER JOIN 1_1_bancos ban ON ban.ban_codigo = cpg.ban_codigo  
    WHERE cpg.ban_codigo = $request->banco");
        return $query;
    }

    public function GetCuentasAll(Request $request){
        $query = DB::SELECT("SELECT * FROM 1_1_cuenta_pago cpg");
        return $query;
    }

    public function GetCheque_todo(Request $request){
        $query = DB::SELECT("SELECT chq.* FROM cheque chq 
        -- INNER JOIN 1_1_bancos ban ON ban.ban_codigo = chq.ban_codigo
        -- WHERE chq_institucion = $request->institucion
        WHERE chq_cliente = $request->cliente
        AND chq_periodo = $request->periodo
        AND chq_empresa = $request->empresa
        ORDER BY chq.created_at DESC ");
        return $query;
    }

    public function PostRegistrar_modificar_Banco(Request $request)
    {
        // Validación de los datos recibidos
        $validator = Validator::make($request->all(), [
            'ban_nombre' => 'required|string|max:255',
            'ban_telefono' => 'required|string|max:20',
            'ban_direccion' => 'nullable|string|max:255',
            'user_created' => 'nullable|integer',
            'cuentas' => 'nullable|array',  // Asumiendo que 'cuentas' es un arreglo de datos de cuentas bancarias
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Utilizar transacción para asegurar la integridad de los datos
        DB::beginTransaction();

        try {
            // Crear o actualizar el banco
            $bancoData = [
                'ban_nombre' => $request->ban_nombre,
                'ban_telefono' => $request->ban_telefono,
                'ban_direccion' => $request->ban_direccion,
                'user_created' => $request->user_created,
            ];

            if ($request->has('ban_codigo')) {
                // Editar banco existente
                $banco = Banco::find($request->ban_codigo);
                if (!$banco) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Banco no encontrado'
                    ], 404);
                }
                $banco->update($bancoData);
            } else {
                // Crear nuevo banco
                $banco = Banco::create($bancoData + ['ban_estado' => 0]);
            }

            // Manejar las cuentas bancarias si se proporcionan
            if ($request->has('cuentas')) {
                CuentaBancaria::where('ban_codigo', $banco->ban_codigo)->delete();
                foreach ($request->cuentas as $cuenta) {
                    CuentaBancaria::create([
                        'ban_codigo' => $banco->ban_codigo,
                        'cue_pag_numero' => $cuenta['cue_pg_numero'],
                        'cue_pag_descripcion' => $cuenta['cue_pg_descripcion'],
                        'cue_pag_nombre' => $cuenta['cue_pag_nombre'],
                    ]);
                }
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json(['message' => 'Banco guardado o actualizado correctamente', 'banco' => $banco], 200);

        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json([
                'error' => true,
                'message' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cheque_registro(Request $request)
    {
        // Validación de los datos recibidos
        $request->validate([
            'ban_codigo' => 'required|integer',
            'chq_tipo' => 'required|integer',
            'chq_valor' => 'required|numeric|min:0',
            'chq_cuenta' => 'required',
            'chq_referenca' => 'required',
            'chq_numero' => 'required|integer',
            // 'chq_institucion' => 'required|string',
            'chq_periodo' => 'required|string',
            'chq_empresa' => 'required',
            'chq_cliente' => 'required',
            'chq_nombrebanco' => 'required',
            'user_created' => 'required',
        ]);

        // Verificar si el número de cheque ya existe
        $chequeExistente = Cheque::where('chq_numero', $request->chq_numero)
                                ->where('chq_cuenta', $request->chq_cuenta)
                                ->exists();

        if ($chequeExistente) {
            return response()->json([
                'status' => 0,
                'message' => 'El número de cheque ' . $request->chq_numero . ' ya está registrado para la cuenta ' . $request->chq_cuenta,
            ]);
        }

        // Crear una instancia del modelo Cheque con los datos validados
        $cheque = Cheque::create([
            'ban_codigo' => $request->ban_codigo,
            'chq_tipo' => $request->chq_tipo,
            'chq_valor' => $request->chq_valor,
            'chq_fecha_cobro' => $request->chq_fecha_cobro,
            'chq_cuenta' => $request->chq_cuenta,
            'chq_referenca' => $request->chq_referenca,
            'chq_numero' => $request->chq_numero,
            // 'chq_institucion' => $request->chq_institucion,
            'chq_periodo' => $request->chq_periodo,
            'chq_empresa' => $request->chq_empresa,
            'chq_cliente' => $request->chq_cliente,
            'user_created' => $request->user_created,
            'chq_nombrebanco' => $request->chq_nombrebanco
        ]);

         // Registrar el historial
         $this->registrarHistorial($cheque, 0, json_encode($cheque->toArray()), json_encode($cheque->toArray())); // Tipo 0 para 'crear'

        // Retornar una respuesta JSON adecuada
        return response()->json([
            'status' => 1,
            'message' => 'Cheque registrado correctamente',
            'cheque' => $cheque
        ]);
    }
    private function registrarHistorial(Cheque $cheque, $tipo, $new_value, $oldValue)
    {
        DB::table('cheque_historico')->insert([
            'chq_id' => $cheque->chq_id,
            'tipo' => $tipo,
            'old_value' => $oldValue,
            'new_value' => $new_value,
            'changed_at' => now(),
            'changed_by' => $cheque->user_created,
        ]);
    }
    public function cheque_eliminar(Request $request)
        {
            // Validación de los datos recibidos
            $request->validate([
                'chequeid' => 'required|integer',
            ]);

            // Buscar el cheque a eliminar
            $cheque = Cheque::find($request->chequeid);

            // Registrar el historial antes de eliminar
            $this->registrarHistorial($cheque, 1, json_encode($cheque->toArray()), json_encode($cheque->toArray())); // Tipo 1 para 'eliminar'

            // Eliminar el cheque
            $cheque->delete();

            // Retornar una respuesta JSON adecuada
            return response()->json([
                'status' => 1,
                'message' => 'Cheque eliminado correctamente'
            ]);
        }

    public function cuenta_registro(Request $request)
    {
        // Validación de los datos recibidos
        $request->validate([
            'ban_codigo' => 'required|integer',
            'cue_pag_numero' => 'required|integer',
            'cue_pag_nombre' => 'required|string',
            'cue_pag_tipo_cuenta' => 'required|integer',
        ]);
    
        if ($request->has('editar') && $request->editar === 'yes') {
            // Actualizar la cuenta existente
            $cuentaExistente = CuentaBancaria::where('cue_pag_codigo', $request->cue_pag_codigo)
                                            ->first();
            if ($cuentaExistente) {
                $cuentaExistente->update([
                    'cue_pag_descripcion' => $request->cue_pag_descripcion,
                    'cue_pag_nombre' => $request->cue_pag_nombre,
                    'cue_pag_numero' => $request->cue_pag_numero,
                    'ban_codigo' => $request->ban_codigo,
                    'cue_pag_tipo_cuenta' => $request->cue_pag_tipo_cuenta,
                ]);
    
                return response()->json([
                    'status' => 1,
                    'message' => 'Cuenta actualizada correctamente',
                    'cuenta' => $cuentaExistente
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Cuenta no encontrada',
                ]);
            }
        } else {
            // Verificar si la cuenta ya existe
            $cuentaExistente = CuentaBancaria::where('cue_pag_numero', $request->cue_pag_numero)
                                            ->where('ban_codigo', $request->ban_codigo)
                                            ->exists();
    
            if ($cuentaExistente) {
                return response()->json([
                    'status' => 0,
                    'message' => 'La cuenta ' . $request->cue_pag_numero . ' ya está registrada para el banco ' . $request->ban_codigo,
                ]);
            } else {
                // Crear una nueva cuenta
                $cuenta = CuentaBancaria::create([
                    'ban_codigo' => $request->ban_codigo,
                    'cue_pag_numero' => $request->cue_pag_numero,
                    'cue_pag_descripcion' => $request->cue_pag_descripcion,
                    'cue_pag_nombre' => $request->cue_pag_nombre,
                    'cue_pag_tipo_cuenta' => $request->cue_pag_tipo_cuenta,
                    'user_created' => $request->user_created,
                ]);
    
                return response()->json([
                    'status' => 1,
                    'message' => 'Cuenta registrada correctamente',
                    'cuenta' => $cuenta
                ]);
            }
        }
    }

    public function GetCuentasBanco(Request $request){
        $query = DB::SELECT("SELECT * FROM 1_1_cuenta_pago cpg");
        return $query;
    }
    public function desactivar_activar_cuenta(Request $request)
    {
        $cue_pag_codigo = $request->cue_pag_codigo;
        $cue_pag_estado = $request->cue_pag_estado;

        $cuenta = CuentaBancaria::find($cue_pag_codigo);

        if ($cuenta) {
            $cuenta->cue_pag_estado = $cue_pag_estado;
            $cuenta->save();

            return response()->json(['mensaje' => 'Cambio de estado exitoso'], 200);
        } else {
            return response()->json(['mensaje' => 'No se encontró la cuenta'], 404);
        }
    }

    public function eliminar_cuenta(Request $request)
    {
        $cue_pag_codigo = $request->cue_pag_codigo;

        try {
            \DB::beginTransaction();

            $cuenta = CuentaBancaria::find($cue_pag_codigo);

            if ($cuenta) {
                $cuenta->delete();
            } else {
                throw new \Exception('No se encontró la cuenta');
            }

            \DB::commit();

            return response()->json(['message' => 'Cuenta eliminada correctamente'], 200);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json(['error' => 'Error al eliminar la cuenta: ' . $e->getMessage()], 500);
        }
    }
    
    
    public function obtenerCuentasPago()
    {
        $cuentasPago = DB::table('1_1_cuenta_pago as cp')
            ->where('cp.cue_pag_estado', 0)
            ->select('cp.cue_pag_codigo', 'cp.cue_pag_numero', 'cp.cue_pag_nombre', 'cp.cue_pag_tipo_cuenta')
            ->get();

        return response()->json($cuentasPago);
    }

    public function obtenerAbonosCuentasNotas(Request $request)
    {
        $fechaInicio = $request->fecha_inicio;
        $fechaFinal = $request->fecha_final;
        $cuenta = $request->cuenta;
    
        $abonos = DB::table('abono as a')
            ->join('1_1_cuenta_pago as cp', 'cp.cue_pag_codigo', '=', 'a.abono_cuenta')
            ->join('usuario as usu', 'usu.cedula', '=', 'a.abono_ruc_cliente')
            ->select('cp.cue_pag_numero', 'cp.cue_pag_nombre', DB::raw('concat(usu.nombres, " ", usu.apellidos) as cliente'), 'a.*')
            ->where('cp.cue_pag_tipo_cuenta', 1)
            ->where('cp.cue_pag_codigo', $cuenta)
            ->where('a.abono_estado','<>',1)
            ->whereBetween(DB::raw('DATE(a.abono_fecha)'), [$fechaInicio, $fechaFinal])
            ->get();
        foreach ($abonos as $key => $value) {
            $institucion = DB::table('f_venta as v')
                ->join('institucion as i', 'i.idInstitucion', '=', 'v.institucion_id')
                ->select('i.idInstitucion', 'i.nombreInstitucion')
                ->where('v.ruc_cliente', $value->abono_ruc_cliente)
                ->first();
                $abonos[$key]->institucion = $institucion->nombreInstitucion;
        }
    
        return response()->json($abonos);
    }
    
    public function obtenerAbonosCuentas(Request $request)
    {
        $fechaInicio = $request->fecha_inicio;
        $fechaFinal = $request->fecha_final;
        $cuenta = $request->cuenta;
        $busqueda = $request->busqueda;
        
        // Inicia la consulta base
        $abonos = DB::table('abono as a')
            ->join('1_1_cuenta_pago as cp', 'cp.cue_pag_codigo', '=', 'a.abono_cuenta')
            ->join('usuario as usu', 'usu.cedula', '=', 'a.abono_ruc_cliente')
            ->select('cp.cue_pag_numero', 'cp.cue_pag_nombre', DB::raw('concat(usu.nombres, " ", usu.apellidos) as cliente'), 'a.*')
            ->where('cp.cue_pag_codigo', $cuenta)
            ->whereBetween(DB::raw('DATE(a.abono_fecha)'), [$fechaInicio, $fechaFinal]);

        // Condicionales para el estado de abono
        if ($busqueda == 'Pendientes') {
            $abonos->where('a.abono_estado', 0);
        }

        if ($busqueda == 'Verificados') {
            $abonos->where('a.abono_estado', 2);
        }

        // Ejecuta la consulta
        $abonos = $abonos->get();

        // Recorre los resultados para agregar la institución
        foreach ($abonos as $key => $value) {
            $institucion = DB::table('f_venta as v')
                ->join('institucion as i', 'i.idInstitucion', '=', 'v.institucion_id')
                ->select('i.idInstitucion', 'i.nombreInstitucion')
                ->where('v.ruc_cliente', $value->abono_ruc_cliente)
                ->first();

            // Si existe una institución, se asigna
            if ($institucion) {
                $abonos[$key]->institucion = $institucion->nombreInstitucion;
            }
        }

        return response()->json($abonos);
    }
    public function cambioEstadoCuentas(Request $request)
    {
        // Verifica si el array de abonos seleccionados está presente
        $abonosSeleccionados = $request->input('cuentas');
        $estado = $request->estado;
        $usuario = $request->usuario;
        
        if (empty($abonosSeleccionados)) {
            return response()->json(['message' => 'No se seleccionaron abonos'], 400);
        }

        try {
            // Realiza el update en la tabla 'abono' para los abonos seleccionados
            DB::table('abono')
            ->whereIn('abono_id', $abonosSeleccionados)
            ->update([
                'abono_estado' => $estado, 
                'usuario_verificador' => $usuario, 
                'fecha_verificacion' => now()
            ]); // Establece el estado a 'Verificado Gerencia' (2)

            // Si todo va bien, retorna una respuesta exitosa
            return response()->json(['message' => 'Estado de los abonos actualizado correctamente'], 200);
        } catch (\Exception $e) {
            // Si ocurre un error, devuelve un mensaje de error
            return response()->json(['message' => 'Error al actualizar los abonos', 'error' => $e->getMessage()], 500);
        }
    }


}
