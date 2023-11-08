<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Seminarios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReporteCapacitacionController extends Controller
{

    private function getTimeProps($param)
    {
        $startDate = null;
        $endDate = null;
        // posible params: hoy,  ésta semana, éste mes, por fechas
        Log::info("param: " . $param);
        switch ($param) {
            case "hoy":
                $startDate = date("Y-m-d");
                $endDate = date("Y-m-d");
                break;
            case "ésta semana":
                $startDate = date("Y-m-d", strtotime("last monday"));
                // next sunday from start date
                $endDate = date("Y-m-d", strtotime("+6 days", strtotime($startDate)));
                break;
            case "éste mes":
                $startDate = date("Y-m-d", strtotime("first day of this month"));
                $endDate = date("Y-m-d", strtotime("last day of this month"));
                break;
            default:
                $startDate = null;
                $endDate = null;
                break;
        }
        return [
            "startDate" => $startDate,
            "endDate" => $endDate
        ];
    }

    public function index(): JsonResponse
    {
        try {
            $periodo = request()->query("periodo", null);

            $time = $this->getTimeProps(request()->query("tiempo", null)); // today, week, month, by dates

            $startDate = request()->query("filtro_fecha_ini", $time["startDate"]);
            $endDate = request()->query("filtro_fecha_fin", $time["endDate"]);

            $capacitador = request()->query("capacitador", null); // id del capacitador

            $tipo = request()->query("tipo", null); // 0: presencial, 1: virtual
            Log::info("tipo: " . 1);

            $capacitaciones = Seminarios::with([
                'institucion' => function ($query) {
                    $query->with(['ciudad']);
                },
                'asesor',
                'periodo',
                'capacitadores'
            ])
                ->whereHas('periodo', function ($query) {
                    $query->where('estado', '1');
                })
                ->when($periodo, function ($query) use ($periodo) {
                    $query->where('periodo_id', $periodo);
                })
                ->when($startDate, function ($query) use ($startDate) {
                    $query->where('fecha_inicio', '>=', $startDate);
                })
                ->when($endDate, function ($query) use ($endDate) {
                    $query->where('fecha_inicio', '<=', $endDate);
                })
                ->when($tipo != null, function ($query) use ($tipo) {
                    $query->where('tipo', $tipo);
                })
                ->when($capacitador, function ($query) use ($capacitador) {
                    $query->whereHas('capacitadores', function ($query) use ($capacitador) {
                        $query->where('seminarios_capacitador.idusuario', $capacitador);
                    });
                })
                ->orderBy('created_at', 'desc')->get();

            return response()->json($capacitaciones);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json(["error" => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
