<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Seminarios;
use App\Models\Usuario;
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

    // public function index(): JsonResponse
    public function index()
    {
        try {
            $periodo = request()->query("periodo", null);
            $time = $this->getTimeProps(request()->query("tiempo", null)); // today, week, month, by dates
            $startDate = request()->query("filtro_fecha_ini", $time["startDate"]);
            $endDate = request()->query("filtro_fecha_fin", $time["endDate"]);
            $capacitador = request()->query("capacitador", null); // id del capacitador
            $tipo = request()->query("tipo", null); // 0: presencial, 1: virtual

            $capacitaciones = Seminarios::with([
                'institucion' => function ($query) {
                    $query->with(['ciudad']);
                },
                'asesor',
                'periodo',
                'capacitadores'
            ])
            ->Where('estado','1')
            ->Where('tipo_webinar','2')
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
            ->orderBy('fecha_inicio', 'desc')->get();

            return response()->json($capacitaciones);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getCapacitadoresDisponibles(): JsonResponse
    {
        try {
            $fecha = request()->query("fecha", null);
            $horaInicio = request()->query("hora_inicio", null);
            $horaFin = request()->query("hora_fin", null);

            // obtener usuarios que no tengan seminarios en ese rango de fechas
            $usuarios = Usuario::whereDoesntHave('seminarios', function ($query) use ($fecha, $horaInicio, $horaFin) {
                // fecha_inicio like fecha
                $query->where('fecha_inicio', 'like', $fecha . '%')
                    ->where(function ($query) use ($horaInicio, $horaFin) {
                        // hora_inicio entre hora_inicio y hora_fin
                        $query->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                            // hora_fin entre hora_inicio y hora_fin
                            ->orWhereBetween('hora_fin', [$horaInicio, $horaFin]);
                    });
            })
                ->where('capacitador', 1)
                ->get();

            return response()->json($usuarios);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function asignarCapacitadores($id): JsonResponse
    {
        try {
            $seminario = Seminarios::findOrFail($id);

            $capacitadores = request()->input("capacitadores", []);

            $seminario->capacitadores()->sync($capacitadores);

            $seminario->save();

            $seminario->load(['capacitadores']);

            return response()->json($seminario);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reporteCapacitaciones(): JsonResponse
    {
        try {
            $periodo = request()->query("periodo", null);

            $time = $this->getTimeProps(request()->query("tiempo", null)); // today, week, month, by dates

            $startDate = request()->query("filtro_fecha_ini", $time["startDate"]);
            $endDate = request()->query("filtro_fecha_fin", $time["endDate"]);

            $capacitador = request()->query("capacitador", null); // id del capacitador

            $tipo = request()->query("tipo", null); // 0: presencial, 1: virtual
            /* DB::SELECT("SELECT u.idusuario,
                   CONCAT(u.nombres, ' ',u.apellidos) AS capacitador
                   FROM
                  usuario u
                  WHERE u.capacitador = '1'
                  AND u.estado_idEstado = '1'
                  AND u.idusuario <> '70102'
                  AND u.idusuario <> '70102'
                  "); */

            $capacitadores = Usuario::where('capacitador', '1')->where('estado_idEstado', '1')->where('idusuario', '<>', '70102')->where('idusuario', '<>', '70102')->get();

            // get capacitaciones con el nombre de usuario como key y el valor tue o false si tiene o no capacitaciones
            $capacitaciones = Seminarios::with([
                'institucion' => function ($query) {
                    $query->with(['ciudad']);
                },
                'asesor',
                'periodo',
                'capacitadores' => function ($query) use ($capacitadores) {
                    // return custom value
                    return $capacitadores->map(function ($capacitador) use ($query) {
                        // return all capacitadores with true or false if they have capacitaciones
                        return [
                            "capacitador" => $capacitador->nombres . " " . $capacitador->apellidos,
                            "has_capacitaciones" => $query->where('seminarios_capacitador.idusuario', $capacitador->idusuario)->exists()
                        ];
                    });
                }
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
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json($capacitaciones);
        } catch (\Throwable $th) {
            return response()->json(["error" => $th->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
