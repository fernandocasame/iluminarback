<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paralelos extends Model
{
    use HasFactory;
    protected $table = "mat_paralelos";
    protected $primaryKey = 'paralelo_id';
    public $timestamps = false;
}
