<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgRelColunasDoc;
use App\Models\LogMonModel;

class ConfigRelColunasDocModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_rel_colunas_doc';
    protected $primaryKey       = 'rct_id';

    protected $returnType       = EntCfgRelColunasDoc::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rct_id',
        'rel_id',
        'rct_tabela',
        'rct_campo',
        'rct_label',
        'rct_tamanho',
        'rct_tipo_dado',
        'rct_largura',
        'rct_ordem',
    ];

    protected $validationRules = [
        'rel_id'      => 'required|integer',
        'rct_tabela'  => 'required',
        'rct_campo'   => 'required',
        'rct_label'   => 'required',
        'rct_tamanho' => 'required|integer',
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
     * Retorna todas as colunas da grade "Tabela" de um relatório, ordenadas.
     */
    public function getColunasDoc(int $rel_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->where('rel_id', $rel_id);
        $builder->orderBy('rct_ordem');

        return $builder->get()->getResult(EntCfgRelColunasDoc::class);
    }

    /**
     * Retorna uma coluna específica pelo ID.
     */
    public function getColunaDoc(int $rct_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->where('rct_id', $rct_id);

        return $builder->get()->getFirstRow(EntCfgRelColunasDoc::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Gravação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Substitui todas as colunas da grade "Tabela" de um relatório de uma vez.
     * Remove as existentes e regrava a lista completa.
     *
     * @param  int   $rel_id
     * @param  array $colunas  Array de arrays com os campos de cada coluna
     */
    public function sincronizarColunasDoc(int $rel_id, array $colunas): void
    {
        $db = db_connect('default');
        $db->table($this->table)->where('rel_id', $rel_id)->delete();

        foreach ($colunas as $ordem => $col) {
            $col['rel_id']    = $rel_id;
            $col['rct_ordem'] = $ordem + 1;
            $this->insert($col);
        }
    }

    /**
     * Retorna a próxima ordem disponível para um relatório.
     */
    public function getProximaOrdem(int $rel_id): int
    {
        return (int) ($this->selectMax('rct_ordem')
            ->where('rel_id', $rel_id)
            ->first()
            ->rct_ordem ?? 0) + 1;
    }
}
