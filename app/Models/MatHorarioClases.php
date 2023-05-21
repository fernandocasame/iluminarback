<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatHorarioClases extends Model
{
    use HasFactory;
    protected $table = 'mat_horario_clases';
    protected $primaryKey = 'id';
}
