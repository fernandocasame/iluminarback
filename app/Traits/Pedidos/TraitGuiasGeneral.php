<?php

namespace App\Traits\Pedidos;

use App\Models\Pedidos;
use DB;
use Illuminate\Support\Facades\Http;
trait TraitGuiasGeneral
{
    public function tr_obtenerSecuenciaGuia($id){
        $secuencia = DB::SELECT("SELECT  * FROM f_tipo_documento d
        WHERE d.tdo_id = ?",[$id]);
        return $secuencia;
    }
}
