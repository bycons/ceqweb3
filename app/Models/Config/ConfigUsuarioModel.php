<?php

namespace App\Models\Config;

use App\Models\LogMonModel;
use CodeIgniter\Model;
use App\Entities\Config\EntCfgUsuario;

class ConfigUsuarioModel extends Model
{
    protected $DBGroup          = 'default';

    protected $table            = 'cfg_usuario';
    protected $view             = 'vw_cfg_usuario_relac';
    protected $primaryKey       = 'usu_id';
    protected $useAutoIncrement = true;

    protected $returnType       = EntCfgUsuario::class;
    protected $useSoftDeletes   = true;

    protected $allowedFields    = [
        'usu_id',
        'usu_nome',
        'usu_login',
        'usu_senha',
        'usu_email',
        'prf_id',
        'usu_status',
        'usu_dashboard',
        'usu_ativo'
    ];

    protected $deletedField  = 'usu_excluido';

    protected $skipValidation = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $afterInsert    = ['depoisInsert'];
    protected $afterUpdate    = ['depoisUpdate'];
    protected $afterDelete    = ['depoisDelete'];

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

    /**
     * usuLogonConfig
     * Validação do Login de Usuário de CONFIG
     *
     * @param mixed $data - WHERE MONTADO NA FUNÇÃO LOGON da Classe Login
     * @return array
     */
    public function usuLogonConfig($data)
    {
        $db = db_connect('default');
        $builder = $db->table('vw_cfg_usuario_relac');
        $builder->select('*');
        $builder->where($data);
        $builder->where('usu_ativo', 'A');
        $ret = $builder->get()->getResult();
        // debug($this->db->getLastQuery());

        return $ret;
    }

    /**
     * getUsuarioId
     *
     * Retorna os dados do Usuário de Config, pelo ID informado
     *
     * @param bool $id
     * @return array
     */
    public function getUsuarioId($id = false)
    {
        $db = db_connect('default');
        $builder = $db->table('vw_cfg_usuario_relac');
        $builder->select('*');
        if ($id) {
            $builder->where('usu_id', $id);
        }
        $builder->orderBy('usu_ativo');
        $ret = $builder->get()->getResult();
        // debug($this->db->getLastQuery(), false);
        return $ret;
    }

    /**
     * getUsuariosPorIds
     *
     * Retorna o nome dos usuários de Config, pelos IDs informados
     *
     * @param array $ids
     * @return array
     */
    public function getUsuariosPorIds(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        $db = db_connect('default');
        $builder = $db->table('vw_cfg_usuario_relac');
        $builder->select('usu_id, usu_nome');
        $builder->whereIn('usu_id', $ids);
        $rows = $builder->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['usu_id']] = $r['usu_nome'];
        }

        return $map;
    }

    /**
     * getUsuarioSearch
     *
     * Retorna os dados do Usuário de Config, pelo termo (nome) informado
     * Utilizado nas Seleções de Usuário
     *
     * @param mixed $termo
     * @return array
     */
    public function getUsuarioSearch($termo)
    {
        $array = ['usu_nome' => $termo . '%'];
        $db = db_connect('default');
        $builder = $db->table('vw_cfg_usuario_relac');
        $builder->select('*');
        $builder->like($array);
        $builder->orderBy('usu_ativo');
        $ret = $builder->get()->getResult();
        // debug($this->db->getLastQuery(), false);
        return $ret;
    }
}
