<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CodigosLibros;
use App\Models\CodigosLibrosDevolucionSon;
use App\Models\LibroSerie;
use App\Traits\Codigos\TraitCodigosGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ComboController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    use TraitCodigosGeneral;
    //api:get/copmbos
    public function index()
    {
        return "hola mundo";
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
    //api:post/combos
    public function store(Request $request)
    {
        if($request->has('AsignarCombo'))                 { return $this->AsignarCombo($request); }
        if($request->has('AsignarComboCodigosLibrosSon')) { return $this->AsignarComboCodigosLibrosSon($request); }
    }

    //api:post/combos?AsignarCombo=1
    public function AsignarCombo(Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $id_usuario             = $request->id_usuario;
        $periodo_id             = $request->periodo_id;
        $codigosConProblemas    = [];
        // Validación del request
        $request->validate([
            'data_codigos' => 'required|json',
        ]);

        $codigos = json_decode($request->data_codigos);

        DB::beginTransaction();
        try {
            // Obtener los códigos a buscar
            $codigosBuscar = array_column($codigos, 'codigo');

            // Buscar códigos en la base de datos
            $busqueda = CodigosLibros::whereIn('codigo', $codigosBuscar)->get();

            // Extraer los códigos encontrados
            $codigosEncontrados = $busqueda->pluck('codigo')->toArray();

            // Determinar los códigos que no fueron encontrados
            $codigosNoEncontrados = array_values(array_diff($codigosBuscar, $codigosEncontrados));

            $contadorEditados = 0;

            foreach ($codigos as $item) {
                $codigo     = $busqueda->firstWhere('codigo', $item->codigo);
                $combo      = $item->combo;
                if ($codigo) {

                    //validra si el combo existe
                    $validateCombo = LibroSerie::where('codigo_liquidacion', $combo)->first();
                    if ($validateCombo) {
                        $comboAnterior  = $codigo->combo;
                        $getCodigoUnion = $codigo->codigo_union;
                        //si el combo es nulo vacio o es igual al combo anterior guardar
                        if ($comboAnterior == "" || $combo == $comboAnterior) {
                            // Si el código tiene `codigo_union`, actualizar
                            if ($getCodigoUnion && $getCodigoUnion != "") {
                                $codigoUnion = CodigosLibros::where('codigo', $getCodigoUnion)->first();
                                if ($codigoUnion) {
                                    $this->actualizarComboYGuardarHistorico($codigoUnion, $combo, $periodo_id, $id_usuario, "Se asigna el combo $combo");
                                }
                            }
                            // Actualizar el código principal
                            $this->actualizarComboYGuardarHistorico($codigo, $combo, $periodo_id, $id_usuario, "Se asigna el combo $combo");

                            $contadorEditados++;
                        }else{
                            //error porque el comobo ya existe y es distinto al anterior
                            $item->error = "El código ya existe con el combo $comboAnterior";
                            $codigosConProblemas[] = $item;
                        }
                    }else{
                        //error porque el combo no existe
                        $item->error = "El combo $combo no existe";
                        $codigosConProblemas[] = $item;
                    }
                }
            }

            DB::commit();

            return response()->json([
                "status"                => 1,
                "message"               => "Operación exitosa",
                "codigoNoExiste"        => $codigosNoEncontrados,
                "cambiados"             => $contadorEditados,
                "codigosConProblemas"   => $codigosConProblemas
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"  => 0,
                "message" => $e->getMessage()
            ], 200); // Status de error
        }
    }
    //api:post/combos?AsignarComboCodigosLibrosSon=1
    public function AsignarComboCodigosLibrosSon(Request $request)
    {
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        $codigosConProblemas    = [];
        // Validación del request
        $request->validate([
            'data_codigos' => 'required|json',
        ]);

        $codigos = json_decode($request->data_codigos);

        DB::beginTransaction();
        try {
            // Obtener los códigos a buscar
            $codigosBuscar = array_column($codigos, 'codigo');

            // Buscar códigos en la base de datos
            $busqueda = CodigosLibrosDevolucionSon::whereIn('codigo', $codigosBuscar)->get();
            // Extraer los códigos encontrados
            $codigosEncontrados = $busqueda->pluck('codigo')->toArray();

            // Determinar los códigos que no fueron encontrados
            $codigosNoEncontrados = array_values(array_diff($codigosBuscar, $codigosEncontrados));

            $contadorEditados = 0;

            foreach ($codigos as $item) {
                $codigo     = $busqueda->firstWhere('codigo', $item->codigo);
                $combo      = $item->combo;
                if ($codigo) {
                    $estado     = $codigo->estado;
                    if($estado == 0){
                         //validar si el combo existe
                         $validateCombo = LibroSerie::where('codigo_liquidacion', $combo)->first();
                         if ($validateCombo) {
                             $comboAnterior  = $codigo->combo;
                             //si el combo es nulo vacio o es igual al combo anterior guardar
                             if ($comboAnterior == "" || $combo == $comboAnterior) {
                                // Si el código tiene `codigo_union`, actualizar
                                //Actualizar codigslibroson si tiene estado 0
                                $getCodigosLibrosSon = CodigosLibrosDevolucionSon::where('codigo', $item->codigo)
                                ->where('estado', '0');

                                if ($getCodigosLibrosSon->exists()) {
                                    $getCodigosLibrosSon->update(['combo' => $combo]);
                                    $contadorEditados++;
                                }else{
                                    //error porque el codigo ya no esta en estado creado
                                    $item->error = "El código ya no esta en en estado creado";
                                    $codigosConProblemas[] = $item;
                                }

                             }else{
                                //error porque el comobo ya existe y es distinto al anterior
                                $item->error = "El código ya existe con el combo $comboAnterior";
                                $codigosConProblemas[] = $item;
                             }
                         }else{
                             //error porque el combo no existe
                             $item->error = "El combo $combo no existe";
                             $codigosConProblemas[] = $item;
                         }
                    }
                    else{
                        //error porque el codiog ya no esta en estado creado
                        $item->error = "El código ya no esta en en estado creado";
                        $codigosConProblemas[] = $item;
                    }
                }
            }

            DB::commit();

            return response()->json([
                "status"                => 1,
                "message"               => "Operación exitosa",
                "codigoNoExiste"        => $codigosNoEncontrados,
                "cambiados"             => $contadorEditados,
                "codigosConProblemas"   => $codigosConProblemas
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status"  => 0,
                "message" => $e->getMessage()
            ], 200); // Status de error
        }
    }
    /**
     * Actualiza el combo y guarda en el histórico.
     */
    private function actualizarComboYGuardarHistorico($codigo, $combo, $periodo_id, $id_usuario, $comentario)
    {
        $oldValues = $codigo->getAttributes(); // Capturar valores actuales
        $oldValues = json_encode($oldValues);
        $codigo->combo = $combo; // Actualizar combo
        if ($codigo->save()) {
            $this->GuardarEnHistorico(0, 0, $periodo_id, $codigo->codigo, $id_usuario, $comentario, $oldValues, json_encode($codigo->getAttributes()) );
        }
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
