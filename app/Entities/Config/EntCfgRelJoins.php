<?php

namespace App\Entities\Config;

use App\Libraries\MyCampo;
use CodeIgniter\Entity\Entity;

class EntCfgRelJoins extends Entity
{
    protected $attributes = [
        'rjo_id'          => null,
        'rel_id'          => null,
        'rjo_tipo_join'   => 'LEFT',
        'rjo_tabela_join' => null,
        'rjo_alias_join'  => null,
        'rjo_condicao_on' => null,
        'rjo_ordem'       => 0,
        // *claude* CABECALHO (joins da tabela base) | TABELA (joins da tabela
        // de detalhe) — só relevante para relatórios rel_tipo_saida=DOCUMENTO;
        // Tabular sempre grava/lê CABECALHO (default retrocompatível).
        'rjo_grupo'       => 'CABECALHO',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }

    public function defCampos(bool $show = false): array
    {
        $dados = $this->toArray();
        $ret   = [];

        // ── Ocultos ──────────────────────────────────────────────────────────
        $ret['rjo_id'] = (new MyCampo('cfg_rel_joins', 'rjo_id'))
            ->setValor($dados['rjo_id'] ?? '')
            ->crOculto();

        $ret['rel_id'] = (new MyCampo('cfg_rel_joins', 'rel_id'))
            ->setValor($dados['rel_id'] ?? '')
            ->crOculto();

        $ret['rjo_ordem'] = (new MyCampo('cfg_rel_joins', 'rjo_ordem'))
            ->setValor($dados['rjo_ordem'] ?? 0)
            ->crOculto();

        // ── Tipo do JOIN ──────────────────────────────────────────────────────
        $ret['rjo_tipo_join'] = (new MyCampo('cfg_rel_joins', 'rjo_tipo_join'))
            ->setValor($dados['rjo_tipo_join'] ?? 'LEFT')
            ->setSelecionado($dados['rjo_tipo_join'] ?? 'LEFT')
            ->setOpcoes(['LEFT' => 'LEFT JOIN', 'INNER' => 'INNER JOIN', 'RIGHT' => 'RIGHT JOIN'])
            ->setDispForm('col-2')
            ->setLeitura($show)
            ->crSelect();

        // ── Tabela do JOIN ────────────────────────────────────────────────────
        $ret['rjo_tabela_join'] = (new MyCampo('cfg_rel_joins', 'rjo_tabela_join'))
            ->setValor($dados['rjo_tabela_join'] ?? '')
            ->setObrigatorio()
            ->setDispForm('col-3')
            ->setLeitura($show)
            ->crInput();

        // ── Alias da tabela (opcional) ────────────────────────────────────────
        $ret['rjo_alias_join'] = (new MyCampo('cfg_rel_joins', 'rjo_alias_join'))
            ->setValor($dados['rjo_alias_join'] ?? '')
            ->setDispForm('col-2')
            ->setLeitura($show)
            ->crInput();

        // ── Condição ON ───────────────────────────────────────────────────────
        $ret['rjo_condicao_on'] = (new MyCampo('cfg_rel_joins', 'rjo_condicao_on'))
            ->setValor($dados['rjo_condicao_on'] ?? '')
            ->setObrigatorio()
            ->setDispForm('col-5')
            ->setLeitura($show)
            ->crInput();

        return $ret;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers de exibição
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Monta o fragmento SQL completo deste JOIN.
     * Ex.: "LEFT JOIN tabela_x AS tx ON tabela_x.rel_id = cfg_relatorios.rel_id"
     */
    public function toJoinFragment(): string
    {
        $alias = $this->rjo_alias_join ? " AS {$this->rjo_alias_join}" : '';

        return "{$this->rjo_tipo_join} JOIN {$this->rjo_tabela_join}{$alias}"
             . " ON {$this->rjo_condicao_on}";
    }
}
