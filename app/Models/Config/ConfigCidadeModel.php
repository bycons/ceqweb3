<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Models\LogMonModel;

class ConfigCidadeModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_cidades';
    protected $view             = 'cfg_cidades';
    protected $primaryKey       = 'cid_id';
    protected $useAutoIncremodt = true;

    protected $returnType       = 'array';

    protected $allowedFields    = [
        'est_id',
        'cid_nome',
        'cid_capital',
        'cid_ddd',
        'cid_latitude',
        'cid_longitude',
        'cid_fuso_horario',
    ];

    protected $validationRules = [
        'cid_nome' => 'required|min_length[3]',
        'est_id'   => 'required',
    ];

    protected $validationMessages = [
        'cid_nome' => [
            'required'   => 'O campo Nome da Cidade é Obrigatório',
            'min_length' => 'O campo Nome exige pelo menos 3 Caracteres.',
        ],
        'est_id' => [
            'required' => 'O campo Estado é Obrigatório',
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
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function depoisDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }

    public function getListaCidades($cid_id = false)
    {
        $db = db_connect('default');
        $builder = $db->table('cfg_cidades');
        $builder->select('*');
        if ($cid_id) {
            $builder->where('cid_id', $cid_id);
        }
        $builder->orderBy('cid_nome');
        return $builder->get()->getResult();
    }

    public function getCidadesEstado($cid_id = false)
    {
        $db = db_connect('default');
        $builder = $db->table('cfg_cidades');
        $builder->select(['cid_id', 'est_id', 'cid_nome']);
        if ($cid_id) {
            $builder->where('cid_id', $cid_id);
        }
        $builder->orderBy('cid_nome');
        return $builder->get()->getResult();
    }
}