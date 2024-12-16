<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\_14ProductoStockHistorico;
use Illuminate\Http\Request;

class _14ProductoStockHistoricoController extends Controller
{
    public function GetProductoStockHistorico() {
        // Obtiene los registros de la tabla histórica
        $query = DB::SELECT("SELECT cpsh.*, concat(u.nombres, ' ', u.apellidos) as nombreeditor 
        from `1_4_cal_producto_stock_historico` cpsh
        left join usuario u on cpsh.user_created = u.idusuario 
        order by cpsh.psh_id asc ");
    
        // Procesa los resultados para reemplazar las barras invertidas en los valores JSON
        // $resultados = array_map(function ($registro) {
        //     $registro->psh_old_values = str_replace('\\', ' ', $registro->psh_old_values);
        //     $registro->psh_new_values = str_replace('\\', ' ', $registro->psh_new_values);
        //     return $registro;
        // }, $query);
    
        return $query;
    }
}
