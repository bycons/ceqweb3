<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgModulo;
use App\Models\LogMonModel;

class ConfigModuloModel extends Model
{
    protected $table            = 'cfg_modulo';
    protected $primaryKey       = 'mod_id';

    protected $returnType       = EntCfgModulo::class;
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'mod_excluido';

    protected $allowedFields = [
        'mod_nome',
        'mod_icone',
        'mod_dbgroup',
        'mod_ativo'
    ];

    protected $validationRules = [
        'mod_nome' => 'required',
    ];

    protected $validationMessages = [
        'mod_nome' => [
            'required'      => 'O campo Nome do Módulo é Obrigatório',
        ],
    ];

    protected $afterInsert = ['logInsert'];
    protected $afterUpdate = ['logUpdate'];
    protected $afterDelete = ['logDelete'];

    protected function logInsert(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Incluído', $data['id'], $data['data']);
        return $data;
    }

    protected function logUpdate(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function logDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }


    // Métodos de domínio
    public function getModulo(?int $id = null)
    {
        // Se for informado um ID, retorna o módulo correspondente
        return $id
            ? $this->find($id)
            : $this->where('mod_excluido', null)
            ->orderBy('mod_nome')
            ->findAll();
    }

    public function getModulosSearch(string $termo)
    {
        // Seleciona apenas os campos necessários
        return $this->select('mod_id, mod_nome, mod_icone')
            ->like('mod_nome', $termo, 'after')
            ->where('mod_excluido', null)
            ->findAll();
    }
}
