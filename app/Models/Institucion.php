<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Institucion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    
    protected $table = "institucion";
    protected $primaryKey = 'idInstitucion';
    protected $fillable = [
        'nombreInstitucion', 'telefonoInstitucion', 'direccionInstitucion', 'fecha_registro', 'solicitudInstitucion', 'vendedorInstitucion', 'imgenInstitucion', 'ciudad_id', 'region_idregion', 'estado_idEstado', 'idcreadorinstitucion', 'ideditor', 'periodoescolar', 'updated_at', 'created_at'
    ];
	public $timestamps = false;
}
