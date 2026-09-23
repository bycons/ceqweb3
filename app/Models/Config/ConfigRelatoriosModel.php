<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgRelatorios;
use App\Models\LogMonModel;

class ConfigRelatoriosModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_relatorios';
    protected $view             = 'vw_cfg_relatorios_relac';
    protected $primaryKey       = 'rel_id';

    protected $returnType       = EntCfgRelatorios::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rel_id',
        'rel_nome',
        'mod_id',
        'tel_id',
        'rel_titulo',
        // *claude* só usada quando rel_tipo_saida=DOCUMENTO — tabela de origem
        // da coluna escolhida como Título (rel_tabela_base ou
        // rel_tabela_detalhe; ver CfgRelatorio::store()). No Tabular fica
        // sempre NULL/sem uso — rel_titulo ali continua texto livre.
        'rel_titulo_tabela',
        // *claude* tipo de saída do relatório: TABULAR (comportamento original,
        // gera SELECT dinâmico único) | DOCUMENTO (cabeçalho + tabela repetível +
        // textos livres, montado em tempo de impressão a partir de :id_registro).
        // Default 'TABULAR' no banco — aditivo, não muda relatórios existentes.
        'rel_tipo_saida',
        'rel_tabela_base',
        // *claude* só usados quando rel_tipo_saida=DOCUMENTO — tabela da grade
        // repetível (aba "Tabela") e a coluna dela que vincula com :id_registro.
        'rel_tabela_detalhe',
        'rel_detalhe_campo_vinculo',
        'rel_formato',
        'rel_tamanho_fonte',
        'rel_chars_por_linha',
        'rel_totalizar_registros',
        'rel_sql_gerado',
        'rel_ativo',
        'rel_criado_por',
        'rel_criado_em',
        'rel_atualizado_em',
    ];

    protected $validationRules = [
        'rel_id'            => 'permit_empty|integer',
        'rel_nome'          => 'required|min_length[5]|max_length[50]|is_unique[cfg_relatorios.rel_nome,rel_id,{rel_id}]',
        'rel_titulo'        => 'required|min_length[3]',
        'rel_tipo_saida'    => 'required|in_list[TABULAR,DOCUMENTO]',
        // *claude* rel_tabela_base é usada tanto pelo Tabular (tabela do SELECT
        // único) quanto pelo Documento (cabeçalho + textos livres) — por isso
        // continua obrigatória nos dois tipos. "required_if" NÃO existe no
        // conjunto de regras deste projeto (Config\Validation::$ruleSets usa
        // CodeIgniter\Validation\StrictRules\Rules, que não implementa
        // required_if — usá-lo quebra TODO save com "'required_if' is not a
        // valid rule"), por isso aqui é sempre "required" (vale pros dois
        // tipos, sem condicional).
        'rel_tabela_base'   => 'required',
        // *claude* regra de negócio nova (DOCUMENTO): a Tela é obrigatória só
        // nesse tipo — ela "ancora" rel_tabela_base (regra 2). Como
        // "required_if" não existe aqui (ver comentário acima), essa
        // obrigatoriedade condicional é validada em
        // CfgRelatorio::_validarRegrasDocumento(), não neste array. No
        // Tabular tel_id continua opcional (sem regra nenhuma aqui).
        'rel_formato'       => 'required|in_list[P,L]',
        // Fontes de 6 a 16pt
        'rel_tamanho_fonte' => 'required|integer|greater_than_equal_to[6]|less_than_equal_to[16]',
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
     * Retorna um ou todos os relatórios.
     *
     * @param  int|false $rel_id  ID específico ou false para todos
     * @param  int|null  $ativo   1 = ativos, 0 = inativos, null = todos
     */
    public function getRelatorios($rel_id = false, $ativo = null)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');

        if ($ativo !== null) {
            $builder->where('rel_ativo', $ativo);
        }

        if ($rel_id) {
            $builder->where('rel_id', $rel_id);
            return $builder->get()->getFirstRow(EntCfgRelatorios::class);
        }
        $builder->orderBy('rel_ativo');
        $ret = $builder->get()->getResult();
        // debug($db->getLastQuery());
        return $ret;
    }

    /**
     * Retorna relatórios filtrados por módulo.
     */
    public function getRelatoriosPorModulo(int $mod_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->where('mod_id', $mod_id);
        $builder->where('rel_ativo', 1);
        $builder->orderBy('rel_titulo');

        return $builder->get()->getResult(EntCfgRelatorios::class);
    }

    /**
     * Retorna relatórios filtrados por tela.
     */
    public function getRelatoriosPorTela(int $tel_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->where('tel_id', $tel_id);
        $builder->where('rel_ativo', 1);
        $builder->orderBy('rel_titulo');

        return $builder->get()->getResult(EntCfgRelatorios::class);
    }

    /**
     * Retorna relatórios ativos de um módulo, SEM tela vinculada (tel_id IS NULL),
     * liberados para o perfil informado. A própria view vw_cfg_relatorios_relac
     * já agrega as permissões em `prf_id` como GROUP_CONCAT(rp.prf_id, ',') —
     * por isso o teste é FIND_IN_SET, sem precisar de JOIN adicional aqui.
     * Usado pelo controller Utils/Relatorio::index() e por montaMenu() (app/Common.php).
     */
    public function getRelatoriosPorModuloPerfil(int $mod_id, int $perfil_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view . ' r');
        $builder->select('r.*');
        // ANTES (BKP 30/06/2026): $builder->join('cfg_rel_permissao rlp', 'rlp.rel_id = r.rel_id', 'inner');
        $builder->where('r.mod_id', $mod_id);
        $builder->where('r.rel_ativo', 1);
        // ANTES (BKP 30/06/2026): $builder->where('r.tel_id', null); // IS NULL - sem tela vinculada
        $builder->where('r.tel_id', 0); // a view traz 0 (não NULL) quando não há tela vinculada
        // ANTES (BKP 30/06/2026): $builder->where('rlp.prf_id', $perfil_id);
        $builder->where('FIND_IN_SET(' . $perfil_id . ', r.prf_id) >', 0, false);
        // ANTES (BKP 30/06/2026): $builder->groupBy('r.rel_id');
        $builder->orderBy('r.rel_titulo');

        $ret =  $builder->get()->getResult(EntCfgRelatorios::class);
        // $sql = $db->getLastQuery();
        // debug($sql);
        return $ret;
    }

    /**
     * Confirma que um relatório específico está liberado para o perfil
     * informado (sem tela vinculada, ativo, com permissão agregada em
     * `prf_id` na view — ver nota acima em getRelatoriosPorModuloPerfil()).
     * Usado pelo controller Utils/Relatorio em filtro()/gerar() para checagem
     * de segurança por relatório individual.
     */
    public function relatorioPermitido(int $rel_id, int $perfil_id): ?EntCfgRelatorios
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view . ' r');
        $builder->select('r.*');
        // ANTES (BKP 30/06/2026): $builder->join('cfg_rel_permissao rlp', 'rlp.rel_id = r.rel_id', 'inner');
        $builder->where('r.rel_id', $rel_id);
        $builder->where('r.rel_ativo', 1);
        // ANTES (BKP 30/06/2026): $builder->where('r.tel_id', null);
        $builder->where('r.tel_id', 0); // a view traz 0 (não NULL) quando não há tela vinculada
        // ANTES (BKP 30/06/2026): $builder->where('rlp.prf_id', $perfil_id);
        $builder->where('FIND_IN_SET(' . $perfil_id . ', r.prf_id) >', 0, false);

        return $builder->get()->getFirstRow(EntCfgRelatorios::class);
    }

    /**
     * Busca por título (autocomplete / search).
     */
    public function getRelatoriosSearch(string $termo)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->view);
        $builder->select('*');
        $builder->like('rel_titulo', $termo . '%');

        return $builder->get()->getResult(EntCfgRelatorios::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Gravação com cálculo automático
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula quantos caracteres cabem em uma linha do relatório
     * com base no formato (P/L) e no tamanho da fonte (Arial).
     *
     * Fórmula: largura útil em mm / (tamanho_pt × 0,353 × 0,45)
     */
    public function calcCharsPorLinha(string $formato, int $tamanhoFonte): int
    {
        $larguraUtilMm  = ($formato === 'L') ? 277 : 190;
        $larguraCharMm  = $tamanhoFonte * 0.353 * 0.45;

        return (int) floor($larguraUtilMm / $larguraCharMm);
    }

    /**
     * Persiste o cabeçalho do relatório, calculando chars_por_linha
     * automaticamente antes de gravar.
     *
     * @param  array $dados  Campos do formulário
     * @return int           ID gravado (insert) ou ID existente (update)
     */
    public function salvarRelatorio(array $dados): int
    {
        $dados['rel_chars_por_linha'] = $this->calcCharsPorLinha(
            $dados['rel_formato']      ?? 'P',
            (int)($dados['rel_tamanho_fonte'] ?? 10)
        );

        if (!empty($dados['rel_id'])) {
            $this->update($dados['rel_id'], $dados);
            return (int) $dados['rel_id'];
        }

        $dados['rel_criado_por'] = session()->get('usu_id');
        return (int) $this->insert($dados);
    }

    /**
     * Grava o SQL gerado no campo rel_sql_gerado.
     */
    public function gravarSqlGerado(int $rel_id, string $sql): void
    {
        $this->update($rel_id, ['rel_sql_gerado' => $sql]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Geração do SQL de consulta
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Monta o SQL de consulta completo com base nas configurações gravadas
     * nas tabelas filhas (filtros, colunas, joins) e o armazena em rel_sql_gerado.
     */
    public function gerarSQL(int $rel_id): string
    {
        $db = db_connect('default');
        $dicDados = new \App\Models\Config\ConfigDicDadosModel();

        $relatorio = $db->table('cfg_relatorios')
            ->where('rel_id', $rel_id)
            ->get()->getFirstRow();

        $colunas = $db->table('cfg_rel_colunas')
            ->where('rel_id', $rel_id)
            ->orderBy('rco_ordem')
            ->get()->getResult();

        $joins = $db->table('cfg_rel_joins')
            ->where('rel_id', $rel_id)
            ->orderBy('rjo_ordem')
            ->get()->getResult();

        $filtros = $db->table('cfg_rel_filtros')
            ->where('rel_id', $rel_id)
            ->orderBy('rfi_ordem')
            ->get()->getResult();

        $cacheSchema = [];
        $resolverSchema = function (string $tabela) use ($dicDados, &$cacheSchema): string {
            if (!isset($cacheSchema[$tabela])) {
                $info = $dicDados->getDbGroupAndSchema($tabela);
                $cacheSchema[$tabela] = !empty($info['schema']) ? $info['schema'] . '.' . $tabela : $tabela;
            }
            return $cacheSchema[$tabela];
        };

        $tabelaBase = $resolverSchema($relatorio->rel_tabela_base);

        // SELECT
        $sel = [];
        foreach ($colunas as $col) {
            $tabCol = $resolverSchema($col->rco_tabela);
            $sel[] = "{$tabCol}.{$col->rco_campo} AS '{$col->rco_label}'";
        }
        $sql = 'SELECT ' . implode(',' . PHP_EOL . '       ', $sel);

        // FROM
        $sql .= PHP_EOL . 'FROM ' . $tabelaBase;

        // JOINs
        foreach ($joins as $j) {
            $tabJoin = $resolverSchema($j->rjo_tabela_join);
            $alias   = $j->rjo_alias_join ? " AS {$j->rjo_alias_join}" : '';

            $condicao = $j->rjo_condicao_on;
            foreach ($cacheSchema as $tab => $tabCompleta) {
                $condicao = str_replace($tab . '.', $tabCompleta . '.', $condicao);
            }

            $sql .= PHP_EOL . "{$j->rjo_tipo_join} JOIN {$tabJoin}{$alias}"
                . " ON {$condicao}";
        }

        // ORDER BY na ordem das colunas definidas na aba Colunas
        $ord = [];
        foreach ($colunas as $col) {
            $tabOrd = $resolverSchema($col->rco_tabela);
            $ord[] = "{$tabOrd}.{$col->rco_campo}";
        }
        $sql .= PHP_EOL . 'ORDER BY ' . implode(', ', $ord);

        $this->gravarSqlGerado($rel_id, $sql);

        return $sql;
    }
}
