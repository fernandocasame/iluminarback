<?php
namespace App\Repositories\Evaluaciones;

use App\Models\SalleEvaluaciones;
use App\Models\SallePreguntasEvaluacion;
use App\Repositories\BaseRepository;
use DB;
class  SalleRepository extends BaseRepository
{
    public function __construct(SalleEvaluaciones $salleEvaluaciones)
    {
        parent::__construct($salleEvaluaciones);
    }
    public function updateCalificacionFinal($id_evaluacion)
    {
        DB::beginTransaction(); // Inicia la transacción

        try {
            $calificacionFinal  = 0;
            $totalPuntaje       = 0;
            $calificacionTotal  = 0;
            // Obtener el tipo de calificación
            $getTipoCalificacion = SalleEvaluaciones::where('id_evaluacion', $id_evaluacion)->first();
            if (!$getTipoCalificacion) {
                throw new \Exception("No existe esa evaluación.");
            }

            $tipo_calificacion = $getTipoCalificacion->tipo_calificacion;

            // Calificación tipo 0: versión anterior
            if ($tipo_calificacion == 0) {

            } else {
                // Calificación tipo 1: solo puntaje por pregunta
                $query = SallePreguntasEvaluacion::where('id_evaluacion', $id_evaluacion)->get();

                foreach ($query as $value) {
                    $calificacionFinal += $value->calificacion_final;
                }
            }
            //total puntaje preguntas
            $query2 = DB::SELECT("SELECT DISTINCT * FROM salle_preguntas_evaluacion p
            LEFT JOIN salle_preguntas pp ON pp.id_pregunta = p.id_pregunta
            WHERE p.id_evaluacion = ?
            ",[$id_evaluacion]);
            foreach ($query2 as $key => $value) {
                $totalPuntaje += $value->puntaje_pregunta;
            }
            // Si el puntaje total es 0, evitar división por cero
            if ($totalPuntaje == 0) {
                throw new \Exception("El puntaje total no puede ser cero.");
            }

            // Calcular la calificación total
            $calificacionTotal = ($calificacionFinal / $totalPuntaje) * 100;
            // Actualizar la evaluación con la calificación final
            $salleEvaluaciones = SalleEvaluaciones::find($id_evaluacion);
            if (!$salleEvaluaciones) {
                throw new \Exception("Evaluación no encontrada.");
            }

            $salleEvaluaciones->calificacion_total = $calificacionTotal;
            $salleEvaluaciones->save();

            DB::commit(); // Si todo va bien, confirma la transacción

            return $calificacionTotal;

        } catch (\Exception $e) {
            DB::rollBack(); // Si ocurre un error, revierte la transacción

            // Lanza la excepción con el mensaje de error
            throw new \Exception('Error al actualizar la calificación final: ' . $e->getMessage());
        }
    }
}
?>
