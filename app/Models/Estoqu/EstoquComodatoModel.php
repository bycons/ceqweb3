<?php

namespace App\Models\Estoqu;

use App\Entities\Estoque\EntEstComodato;
use App\Models\LogMonModel;
use CodeIgniter\Model;

class EstoquComodatoModel extends Model
{
    protected $DBGroup     = 'dbEstoque';
    protected $table       = 'est_comodato';
    protected $view        = 'vw_est_comodato_relac_lista';
    protected $viewlista   = 'vw_est_comodato_relac_lista';
    protected $primaryKey  = 'cmd_id';

    protected $returnType       = EntEstComodato::class;
    protected $useSoftDeletes   = true;

    protected $allowedFields    = [
        'pro_id',
        'cmd_identificador',
        'cmd_ativo',
        'usu_id',
    ];

    protected $deletedField  = 'cmd_excluido';

    // RN03.1 / RN03.3 / RN03.5
    protected $validationRules = [
        'pro_id'            => 'required|is_natural_no_zero',
        'cmd_identificador' => 'required|max_length[15]',
    ];

    protected $validationMessages = [
        'pro_id' => [
            'required'           => 'O campo CÓD ERP é Obrigatório.',
            'is_natural_no_zero' => 'Produto não encontrado para o CÓD ERP informado.',
        ],
        'cmd_identificador' => [
            'required'   => 'O campo ID é Obrigatório.',
            'max_length' => 'O ID deve ter no máximo 15 Caracteres.',
        ],
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
        $id = is_array($data['id']) ? $data['id'][0] : $data['id'];

        (new LogMonModel())->insertLog($this->table, 'Alteração', $id, $data['data']);
        return $data;
    }

    protected function depoisDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }

    public function getComodato($cmd_id = false)
    {
        $db = db_connect('dbEstoque');
        $builder = $db->table($this->view);
        $builder->select('*');

        if ($cmd_id) {
            $builder->where('cmd_id', $cmd_id);
        }
        $builder->orderBy('cmd_ativo, cmd_id DESC');

        return $builder->get()->getResult();
    }

    public function getComodatoSearch($termo)
    {
        $array = ['cmd_identificador' => $termo . '%'];    // Busca pelo ID / código de barras

        $db = db_connect('dbEstoque');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->where('cmd_ativo', 'A');
        $builder->like($array);

        return $builder->get()->getResult();
    }
}