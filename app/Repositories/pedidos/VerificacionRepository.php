<?php
namespace App\Repositories\pedidos;

use App\Models\Verificacion;
use App\Repositories\BaseRepository;
use DB;
class  VerificacionRepository extends BaseRepository
{
    public function __construct(Verificacion $VerificacionRepository)
    {
        parent::__construct($VerificacionRepository);
    }

}
