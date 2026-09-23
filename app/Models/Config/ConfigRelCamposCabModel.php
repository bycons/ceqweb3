<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgRelCamposCab;
use App\Models\LogMonModel;

class ConfigRelCamposCabModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_rel_camposcab';
    protected $primaryKey       = 'rcc_id';

    protected $returnType       = EntCfgRelCamposCab::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rcc_id',
        'rel_id',
        'rcc_tabela',
        'rcc_campo',
        'rcc_label',
        'rcc_tamanho',
        'rcc_tipo_dado',
        'rcc_largura_col',
        'rcc_ordem',
    ];

    protected $validationRules = [
        'rel_id'      => 'required|integer',
        'rcc_tabela'  => 'required',
        'rcc_campo'   => 'required',
        // rcc_label opcional (usuário, 2026-09-23) — sem rótulo imprime só o valor
        'rcc_tamanho' => 'required|integer',
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

    // ─────────────────────────────────────────────────────────────────────────
    //  Leitura
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna todos os campos de cabeçalho de um relatório, ordenados.
     */
    public function getCamposCab(int $rel_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->where('rel_id', $rel_id);
        $builder->orderBy('rcc_ordem');

        return $builder->get()->getResult(EntCfgRelCamposCab::class);
    }

    /**
     * Retorna um campo de cabeçalho específico pelo ID.
     */
    public function getCampoCab(int $rcc_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->where('rcc_id', $rcc_id);

        return $builder->get()->getFirstRow(EntCfgRelCamposCab::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Gravação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Substitui todos os campos de cabeçalho de um relatório de uma vez.
     * Remove os existentes e regrava a lista completa.
     *
     * @param  int   $rel_id
     * @param  array $campos  Array de arrays com os campos de cada linha
     */
    public function sincronizarCamposCab(int $rel_id, array $campos): void
    {
        $db = db_connect('default');
        $db->table($this->table)->where('rel_id', $rel_id)->delete();

        foreach ($campos as $ordem => $c) {
            $c['rel_id']    = $rel_id;
            $c['rcc_ordem'] = $ordem + 1;
            $this->insert($c);
        }
    }

    /**
     * Retorna a próxima ordem disponível para um relatório.
     */
    public function getProximaOrdem(int $rel_id): int
    {
        return (int) ($this->selectMax('rcc_ordem')
            ->where('rel_id', $rel_id)
            ->first()
            ->rcc_ordem ?? 0) + 1;
    }
}
