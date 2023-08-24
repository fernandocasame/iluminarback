<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BibliotecaController extends Controller
{
    /**
     * Summary of getCategorias
     * @param \Illuminate\Http\Request $request
     * @param mixed $area
     * @return JsonResponse|mixed
     */
    public function getCategorias(Request $request, $area)
    {
        try {
            /* Query */
            $query = DB::select(
                "SELECT id, nombre, descripcion
                FROM area_categoria
                WHERE estado = '1'
                AND idarea = ?",
                [$area]
            );

            /* Response */
            return response()->json([
                'data' => $query
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function crearCategoria()
    {
        try {
            $input = request()->all();

            $data = DB::table('area_categoria')->insert([
                'idarea' => $input['idArea'],
                'nombre' => $input['nombre'],
                'descripcion' => $input['descripcion'],
                'estado' => '1'
            ]);

            return response()->json([
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getLibros(Request $request): JsonResponse
    {
        try {
            /* Params */
            $idArea = $request->query('idArea', 0);
            $idSerie = $request->query('idSerie', 0);

            /* Query */
            $query = DB::select(
                "SELECT l.idlibro, l.nombrelibro, l.asignatura_idasignatura as asignatura
                FROM libros_series ls
                LEFT JOIN series s ON ls.id_serie = s.id_serie
                LEFT JOIN libro l ON ls.idLibro = l.idlibro
                LEFT JOIN asignatura a ON l.asignatura_idasignatura = a.idasignatura
                LEFT JOIN area ar ON a.area_idarea = ar.idarea
                WHERE l.Estado_idEstado = '1'
                AND ar.estado = '1'
                AND ar.idarea = ?
                AND s.id_serie = ?
                ORDER BY l.nombrelibro;",
                [$idArea, $idSerie]
            );

            /* Response */
            return response()->json([
                'data' => $query
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getUnidades(Request $request, $libro): JsonResponse
    {
        try {
            /* Query */
            $query = DB::select(
                "SELECT ul.id_unidad_libro, CONCAT('UNIDAD', ' ',ul.unidad, ': ', ul.nombre_unidad) AS unidad, ul.unidad as numero_unidad
                FROM `libro` l
                INNER JOIN `unidades_libros` ul ON l.idlibro = ul.id_libro
                WHERE l.idlibro = ?
                ORDER BY ul.unidad;",
                [$libro]
            );

            /* Response */
            return response()->json([
                'data' => $query
            ], 200);
        } catch (\Throwable $th) {
            Log::error('BibliotecaController - getUnidades: ' . $th);
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
