<?php

namespace App\Models\Estoqu;

use App\Models\LogMonModel;
use CodeIgniter\Model;

class EstoquRequisicaoProdutoModel extends Model
{
    protected $DBGroup          = 'dbEstoque';
    protected $table            = 'est_requisicao_produto';
    protected $view             = '';
    protected $primaryKey       = 'rep_id';
    protected $useAutoIncremodt = true;


    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'rep_id',
        'req_id',
        'pro_id',
        'lot_id',
        'rep_multiplicador',
        'rep_seguranca',
        'rep_quantia',
    ];

    // protected $deletedField  = 'req_excluido';

    // Callbacks
    protected $allowCallbacks = true;

    protected $afterInsert   = ['depoisInsert'];
    protected $afterUpdate   = ['depoisUpdate'];
    protected $afterDelete   = ['depoisDelete'];

    protected $logdb;


    /**
     * This method saves the session "usu_id" value to "created_by" and "updated_by" array
     * elements before the row is inserted into the database.
     *
     */
    protected function depoisInsert(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'];
        $log = $logdb->insertLog($this->table, 'Incluído', $registro, $data['data']);
        return $data;
    }

    /**
     * This method saves the session "usu_id" value to "updated_by" array element before
     * the row is inserted into the database.
     *
     */
    protected function depoisUpdate(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'][0];
        $log = $logdb->insertLog($this->table, 'Alteração', $registro, $data['data']);
        return $data;
    }

    /**
     * This method saves the session "usu_id" value to "deletede_by" array element before
     * the row is inserted into the database.
     *
     */
    protected function depoisDelete(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'][0]??null;
        $log = $logdb->insertLog($this->table, 'Excluído', $registro, $data['data']);
        return $data;
    }

    public function getRequisicaoProdutos($req_id = false)
    {
        $db = db_connect('dbEstoque');
        $builder = $db->table('vw_est_requisicao_produto_relac');
        $builder->select('*');
        if ($req_id) {
            $builder->where('req_id', $req_id);
        }
        $ret = $builder->get()->getResultArray();
        // debug($db->getLastQuery(), true);

        return $ret;
    }

    public function excluir($req_id)
    {
        $db = db_connect('dbEstoque');
        $builder = $db->table($this->table);
        $builder->where('req_id', $req_id);
        $ret = $builder->delete();
        // debug($this->db->getLastQuery(), false);
        return $ret;
    }



    public function getProdutoRequisicao($produto)
    {
        $db = db_connect('dbEstoque');
        $builder = $db->table('est_requisicao_produto');
        $builder->select('*');
        $builder->where('pro_id', $produto);
        return $builder->get()->getResultArray();
    }

    public function getRepId($req_id, $pro_id)
    {
        $db = db_connect('dbEstoque');
        $builder = $db->table($this->table);
        $builder->select('rep_id');
        $builder->where('req_id', $req_id);
        $builder->where('pro_id', $pro_id);
        $row = $builder->get()->getRowArray();

        return $row['rep_id'] ?? null;
    }

}
