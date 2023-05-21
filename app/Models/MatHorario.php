<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatHorario extends Model
{
    use HasFactory;
    protected $table = 'mat_horario';
    protected $primaryKey = 'id';
}
