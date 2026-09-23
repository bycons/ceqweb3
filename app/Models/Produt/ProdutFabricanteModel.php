<?php

namespace App\Models\Produt;

use App\Models\LogMonModel;
use CodeIgniter\Model;
use App\Entities\Produto\EntFabricante;

class ProdutFabricanteModel extends Model
{
    protected $DBGroup          = 'dbProduto';
    protected $table            = 'pro_sap_fabricante';
    protected $view             = 'pro_sap_fabricante';
    protected $primaryKey       = 'fab_codFab';
    // protected $useAutoIncremodt = false;a

    protected $returnType       = EntFabricante::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [ 'fab_codFab',
                                    'fab_nomFab',
                                    'fab_apeFab',
                                    ];

    // Callbacks
    protected $allowCallbacks = true;

    protected $afterInsert   = ['depoisInsert'];
    protected $afterUpdate   = ['depoisUpdate'];
    protected $afterDelete   = ['depoisDelete'];

    protected $logdb;

    protected function depoisInsert(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Incluído', $data['id'], $data['data']);
        return $data;
    }

    protected function depoisUpdate(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function depoisDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }

    public function getFabricante($codFab = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('pro_sap_fabricante');
        $builder->select('*');
        if ($codFab) {
            $builder->where('fab_codFab', $codFab);
        }
    
        return $builder->get()->getResult(); 
    }
    
    
    public function getFabricanteSearch($termo)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('pro_sap_fabricante');
        $builder->select('*');
        $builder->like('fab_nomFab', $termo . '%');
    
        return $builder->get()->getResult(); 
    }
}
