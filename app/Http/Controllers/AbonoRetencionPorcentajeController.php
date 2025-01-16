<?php

namespace App\Http\Controllers;

use App\Models\AbonoRetencionPorcentaje;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbonoRetencionPorcentajeController extends Controller
{
    public function GetPorcentajeRetencion() {
        $query = DB:: SELECT("SELECT * FROM abono_retencion_porcentaje arp 
        WHERE arp.arp_estado = 1
        ORDER BY arp.arp_valor ASC
        ");
        return $query;
    }
}
