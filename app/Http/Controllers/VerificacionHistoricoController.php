<?php

namespace App\Http\Controllers;
use App\Models\VerificacionHistorico;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

class VerificacionHistoricoController extends Controller
{
    public function dventaxvencodigo($vencodigo){
        $DetalleVenta = DB::SELECT("SELECT * FROM verificaciones_detalleventa_historico WHERE vencodigo LIKE '%$vencodigo%' AND tipo = '1' AND accion = '1'");
        return["DetalleVenta" => $DetalleVenta];
    }

    public function dverificacionxvencodigo($vencodigo){
        $DetalleVerificacion = DB::SELECT("SELECT * FROM verificaciones_detalleventa_historico WHERE vencodigo LIKE '%$vencodigo%' AND tipo = '2' AND ( accion = '1' || accion = '2')");
        return["DetalleVerificacion" => $DetalleVerificacion];
    }

    public function historicoverificacionsinparametros(){
        $Historico = DB::SELECT("SELECT * FROM verificaciones_detalleventa_historico");
        return["Historico" => $Historico];
    }
}
