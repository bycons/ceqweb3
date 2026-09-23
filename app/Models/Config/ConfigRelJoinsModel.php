<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgRelJoins;
use App\Models\LogMonModel;

class ConfigRelJoinsModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_rel_joins';
    protected $primaryKey       = 'rjo_id';

    protected $returnType       = EntCfgRelJoins::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rjo_id',
        'rel_id',
        'rjo_tipo_join',
        'rjo_tabela_join',
        'rjo_alias_join',
        'rjo_condicao_on',
        'rjo_ordem',
        // *claude* grupo de joins — distingue os joins da tabela base (CABECALHO,
        // usado também pelo Tabular, valor default retrocompatível) dos joins da
        // tabela de detalhe (TABELA) do relatório tipo Documento — ver
        // sincronizarJoins()/CfgRelatorio::_sincronizarJoins().
        'rjo_grupo',
    ];

    protected $validationRules = [
        'rel_id'          => 'required|integer',
        'rjo_tipo_join'   => 'required|in_list[INNER,LEFT,RIGHT]',
        'rjo_tabela_join' => 'required',
        'rjo_condicao_on' => 'required',
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
     * Retorna todos os JOINs de um relatório, na ordem de execução.
     *
     * @param  int         $rel_id
     * @param  string|null $grupo  'CABECALHO'|'TABELA' — null retorna os dois
     *                             grupos juntos (uso do Tabular, que só tem
     *                             CABECALHO, permanece idêntico ao de antes).
     */
    public function getJoins(int $rel_id, ?string $grupo = null)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->where('rel_id', $rel_id);
        if ($grupo !== null) {
            $builder->where('rjo_grupo', $grupo);
        }
        $builder->orderBy('rjo_ordem');

        return $builder->get()->getResult(EntCfgRelJoins::class);
    }

    /**
     * Retorna um JOIN específico pelo ID.
     */
    public function getJoin(int $rjo_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->where('rjo_id', $rjo_id);

        return $builder->get()->getFirstRow(EntCfgRelJoins::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Gravação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Substitui todos os JOINs de um relatório de uma vez, DENTRO DO GRUPO
     * informado. Remove os existentes do grupo e regrava a lista completa —
     * não toca nos joins do OUTRO grupo (ex.: regravar os joins da Tabela do
     * Documento não pode apagar os joins do Cabeçalho, e vice-versa).
     *
     * @param  int    $rel_id
     * @param  array  $joins  Array de arrays com os campos de cada JOIN
     * @param  string $grupo  'CABECALHO' (default — retrocompatível com o
     *                        Tabular, que só usa este grupo) | 'TABELA'
     */
    public function sincronizarJoins(int $rel_id, array $joins, string $grupo = 'CABECALHO'): void
    {
        $db = db_connect('default');
        $db->table($this->table)
            ->where('rel_id', $rel_id)
            ->where('rjo_grupo', $grupo)
            ->delete();

        foreach ($joins as $ordem => $j) {
            $j['rel_id']    = $rel_id;
            $j['rjo_ordem'] = $ordem + 1;
            $j['rjo_grupo'] = $grupo;
            $this->insert($j);
        }
    }

    /**
     * Retorna a próxima ordem disponível para um relatório.
     */
    public function getProximaOrdem(int $rel_id): int
    {
        return (int) ($this->selectMax('rjo_ordem')
            ->where('rel_id', $rel_id)
            ->first()
            ->rjo_ordem ?? 0) + 1;
    }
}
