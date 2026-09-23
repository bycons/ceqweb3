<?php

/**
 * Summary of namespace App\Models\Config
 * Classe para tratamento das Tabelas e Campos do Sistema
 * Trabalha com várias bases de dados ao mesmo tempo
 * 
 * Criada por: Douglas Junior Ferreira
 * Dezembro/2023
 */

namespace App\Models\Config;

use CodeIgniter\Model;

class ConfigDicDadosModel extends Model
{
    protected $DBGroup          = 'default';

    protected $table            = 'information_schema.tables';
    protected $primaryKey       = 'table_name';
    protected $returnType       = 'array';

    protected $allowedFields    = [
        'table_name',
        'table_rows',
        'table_comment',
    ];

    /**
     * Lista central de dbGroups conhecidos do sistema, usada por
     * getTabelas()/getTabelasPorDbGroup()/getViewsPorPrefixo() — evita
     * repetir (e desatualizar) essa lista em cada método. Inclui
     * 'dbLogistica', ausente antes deste ciclo (ver
     * docs/desenvolvimento/notificacoes-sms-tipos-alerta-dev.md, seção 4.2).
     */
    protected array $dbGroupsConhecidos = ['default', 'dbEstoque', 'dbProduto', 'dbOcorrencia', 'dbLogistica'];

    /**
     * Summary of getTabelas
     * Retorna as Tabelas do Sistema com seus Detalhes
     * @param mixed $nome_tabela
     * @param mixed $grupo
     * @return array
     */
    public function getTabelas($nome_tabela = false)
    {
        $dbGroups = $this->dbGroupsConhecidos;

        $ret = [];

        foreach ($dbGroups as $dbGroup) {
            $this->DBGroup = $dbGroup;
            $db      = db_connect($dbGroup);
            // Usa o schema real da conexão (app/Config/Database.php), em vez de
            // adivinhar dev_/prd_ pela base_url() — isso falha fora de requests HTTP
            // (CLI, jobs, cron) e retorna 0 linhas silenciosamente.
            $schema  = $db->getDatabase();

            $builder = $db->table('information_schema.tables');
            $builder->select(['table_schema', 'table_name', 'table_rows', 'table_comment', 'table_type']);

            if ($nome_tabela) {
                $builder->where('table_name', $nome_tabela);
            }
            $builder->where('table_schema', $schema);
            $builder->orderBy('table_name', 'ASC');

            foreach ($builder->get()->getResultArray() as $reg) {
                $ret[] = $reg;
            }
        }
        $this->DBGroup = 'default';

        return $ret;
    }

    /**
     * Summary of getTabelaSearch
     * Retorna os detalhes da Tabela informada
     * @param mixed $nome_tabela
     * @return array
     */
    public function getTabelaSearch($nome_tabela)
    {
        $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
        $db      = db_connect($dbGrSche['dbGroup']);
        $builder = $db->table($this->table);
        $array = ['table_name' => $nome_tabela . '%'];
        $builder
            ->select(['table_name', 'table_rows', 'table_comment'])
            ->like($array);

        $builder->where('table_schema', $dbGrSche['schema']);
        $builder->orderBy('table_name', 'ASC');

        $ret = $builder->get()->getResultArray();
        return $ret;
    }

    /**
     * Summary of getRelacionamentos
     * Retorna os Relacionamentos da Tabela informada
     * @param mixed $nome_tabela
     * @return array
     */
    // public function getRelacionamentos($nome_tabela)
    // {
    //     $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
    //     $array = ['kc.table_name' => $nome_tabela];
    //     $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
    //     $db      = db_connect($dbGrSche['dbGroup']);
    //     $builder = $db->table('information_schema.KEY_COLUMN_USAGE kc');

    //     // $builder = $this->builder('information_schema.KEY_COLUMN_USAGE kc');
    //     $builder->select('CONSTRAINT_NAME, 
    //                         kc.TABLE_NAME, 
    //                         kc.COLUMN_NAME, 
    //                         kc.REFERENCED_TABLE_NAME, 
    //                         kc.REFERENCED_COLUMN_NAME,
    //                         tb.TABLE_COMMENT');
    //     $builder->join('information_schema.TABLES tb', 'tb.TABLE_NAME = kc.REFERENCED_TABLE_NAME', 'inner');
    //     $builder->where($array);
    //     $builder->where('kc.table_schema', $dbGrSche['schema']);
    //     $builder->where('REFERENCED_TABLE_SCHEMA IS NOT NULL');

    //     // debug($builder->getCompiledSelect(), true);
    //     $ret = $builder->get()->getResultArray();
    //     return $ret;
    // }
    public function getRelacionamentos($nome_tabela, $completo = 0)
    {
        $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
        $db = db_connect($dbGrSche['dbGroup']);

        // Relacionamentos diretos (tabela base aponta para → pais)
        $relacionamentos_base = $this->_obterRelacionamentosTabela(
            $db,
            $nome_tabela,
            $dbGrSche['schema']
        );

        // Relacionamentos reversos (filhas → apontam para a tabela base via constraint)
        $relacionamentos_filhas = $this->_obterRelacionamentosReversos(
            $db,
            $nome_tabela,
            $dbGrSche['schema']
        );

        // Potenciais relacionamentos sem constraint (por padrão de nomenclatura)
        $relacionamentos_potenciais = $this->_obterRelacionamentosPotenciais(
            $db,
            $nome_tabela,
            $dbGrSche['schema'],
            $relacionamentos_base,
            $dbGrSche['dbGroup']
        );

        // Relacionamentos reversos potenciais (filhas sem constraint, por convenção de nome)
        $todosJaEncontrados = array_merge($relacionamentos_base, $relacionamentos_filhas, $relacionamentos_potenciais);
        $relacionamentos_filhas_potenciais = $this->_obterRelacionamentosReversosPotenciais(
            $db,
            $nome_tabela,
            $dbGrSche['schema'],
            $todosJaEncontrados
        );

        // Mescla todos os tipos de relacionamentos
        $relacionamentos_completos = array_merge($todosJaEncontrados, $relacionamentos_filhas_potenciais);

        // Se completo = 0, retorna apenas os relacionamentos da tabela base
        if ($completo == 0) {
            return [
                'relacionamentos' => $relacionamentos_completos
            ];
        }

        // Se completo = 1, expande para incluir relacionamentos das tabelas relacionadas
        $todos_relacionamentos = $relacionamentos_completos;
        $tabelas_processadas = [$nome_tabela];

        foreach ($relacionamentos_completos as $rel) {
            $tabela_ref = $rel['REFERENCED_TABLE_NAME'];

            if (!in_array($tabela_ref, $tabelas_processadas)) {
                $tabelas_processadas[] = $tabela_ref;

                // Obtém o schema da tabela referenciada
                $dbGrSche_ref = $this->getDbGroupAndSchema($tabela_ref);
                $db_ref = db_connect($dbGrSche_ref['dbGroup']);

                $rels_base = $this->_obterRelacionamentosTabela(
                    $db_ref,
                    $tabela_ref,
                    $dbGrSche_ref['schema']
                );

                $rels_potenciais = $this->_obterRelacionamentosPotenciais(
                    $db_ref,
                    $tabela_ref,
                    $dbGrSche_ref['schema'],
                    $rels_base,
                    $dbGrSche_ref['dbGroup']
                );

                $rels_expandidos = array_merge($rels_base, $rels_potenciais);

                // Adiciona todos os relacionamentos à array final
                $todos_relacionamentos = array_merge($todos_relacionamentos, $rels_expandidos);
            }
        }

        return [
            'tabela_base' => $nome_tabela,
            'relacionamentos' => $todos_relacionamentos
        ];
    }

    /**
     * *claude* Verifica se existem duas tabelas relacionadas entre si por FK
     * (real ou "potencial", por convenção de nome — getRelacionamentos() já
     * cobre os dois casos), em QUALQUER direção (A→B ou B→A), 1 hop.
     *
     * Usado pelas regras de negócio do tipo DOCUMENTO do gerador de
     * relatórios (CfgRelatorio) — ver CfgRelatorio::_validarRegrasDocumento().
     *
     * Nota de implementação: getRelacionamentos($tabelaA) já devolve, no
     * mesmo array 'relacionamentos', tanto os relacionamentos "diretos" (A tem
     * FK apontando pra outra tabela — TABLE_NAME=A, REFERENCED_TABLE_NAME=a
     * outra tabela) quanto os "reversos" (uma tabela filha tem FK apontando
     * pra A — nesse caso o método já devolve REFERENCED_TABLE_NAME = a tabela
     * filha, ver _obterRelacionamentosReversos()). Por isso comparar só
     * REFERENCED_TABLE_NAME já cobre as duas direções a partir de uma única
     * chamada; ainda assim, por segurança, confere nos dois sentidos
     * (getRelacionamentos($tabelaA) e getRelacionamentos($tabelaB)).
     */
    public function tabelasRelacionadas(string $tabelaA, string $tabelaB): bool
    {
        if ($tabelaA === '' || $tabelaB === '') {
            return false;
        }

        if ($tabelaA === $tabelaB) {
            return true;
        }

        $relsA = $this->getRelacionamentos($tabelaA);
        foreach ($relsA['relacionamentos'] as $r) {
            if (($r['REFERENCED_TABLE_NAME'] ?? '') === $tabelaB || ($r['TABLE_NAME'] ?? '') === $tabelaB) {
                return true;
            }
        }

        $relsB = $this->getRelacionamentos($tabelaB);
        foreach ($relsB['relacionamentos'] as $r) {
            if (($r['REFERENCED_TABLE_NAME'] ?? '') === $tabelaA || ($r['TABLE_NAME'] ?? '') === $tabelaA) {
                return true;
            }
        }

        return false;
    }

    /**
     * *claude* Nome da coluna de $tabelaDetalhe que é FK (real ou potencial)
     * para a PK de $tabelaBase, ou null se não existir nenhuma.
     *
     * Usado pelo tipo DOCUMENTO do gerador de relatórios (CfgRelatorio) pra
     * calcular rel_detalhe_campo_vinculo automaticamente — deixou de ser
     * escolha livre do admin (ver CfgRelatorio::_storeDocumento()).
     *
     * Só olha o sentido "direto" (TABLE_NAME === $tabelaDetalhe &&
     * REFERENCED_TABLE_NAME === $tabelaBase) — é o único sentido em que
     * $tabelaDetalhe de fato TEM a FK (a coluna que vai em WHERE
     * tabela_detalhe.<vinculo> = :id_registro precisa existir em
     * $tabelaDetalhe, nunca em $tabelaBase). Se houver mais de uma FK
     * candidata (raro), retorna a primeira em ordem alfabética de
     * COLUMN_NAME — determinístico, sem UI de desambiguação nesta primeira
     * versão.
     */
    public function buscarCampoVinculo(string $tabelaDetalhe, string $tabelaBase): ?string
    {
        if ($tabelaDetalhe === '' || $tabelaBase === '') {
            return null;
        }

        $rels = $this->getRelacionamentos($tabelaDetalhe);

        $candidatos = [];
        foreach ($rels['relacionamentos'] as $r) {
            if (
                ($r['TABLE_NAME'] ?? '') === $tabelaDetalhe
                && ($r['REFERENCED_TABLE_NAME'] ?? '') === $tabelaBase
                && !empty($r['COLUMN_NAME'])
            ) {
                $candidatos[] = $r['COLUMN_NAME'];
            }
        }

        if (empty($candidatos)) {
            return null;
        }

        $candidatos = array_unique($candidatos);
        sort($candidatos);

        return $candidatos[0];
    }

    /**
     * Obtém relacionamentos com constraint definido no banco
     */
    private function _obterRelacionamentosTabela($db, $nome_tabela, $schema)
    {
        $builder = $db->table('information_schema.KEY_COLUMN_USAGE kc');

        $builder->select(
            'CONSTRAINT_NAME, 
        kc.TABLE_NAME, 
        kc.COLUMN_NAME, 
        kc.REFERENCED_TABLE_NAME, 
        kc.REFERENCED_COLUMN_NAME,
        tb.TABLE_COMMENT,
        "constraint" as tipo_relacao'
        );

        $builder->join(
            'information_schema.TABLES tb',
            'tb.TABLE_NAME = kc.REFERENCED_TABLE_NAME AND tb.TABLE_SCHEMA = kc.REFERENCED_TABLE_SCHEMA',
            'inner'
        );

        $builder->where('kc.TABLE_NAME', $nome_tabela);
        $builder->where('kc.TABLE_SCHEMA', $schema);
        $builder->where('kc.REFERENCED_TABLE_SCHEMA IS NOT NULL', null, false);

        $result = $builder->get();

        return $result ? $result->getResultArray() : [];
    }

    /**
     * Obtém relacionamentos reversos (tabelas filhas que referenciam a tabela informada).
     */
    private function _obterRelacionamentosReversos($db, $nome_tabela, $schema)
    {
        $builder = $db->table('information_schema.KEY_COLUMN_USAGE kc');

        $builder->select(
            'CONSTRAINT_NAME,
            kc.TABLE_NAME,
            kc.COLUMN_NAME,
            kc.TABLE_NAME AS REFERENCED_TABLE_NAME,
            kc.COLUMN_NAME AS REFERENCED_COLUMN_NAME,
            tb.TABLE_COMMENT,
            "constraint" as tipo_relacao'
        );

        $builder->join(
            'information_schema.TABLES tb',
            'tb.TABLE_NAME = kc.TABLE_NAME AND tb.TABLE_SCHEMA = kc.TABLE_SCHEMA',
            'inner'
        );

        $builder->where('kc.REFERENCED_TABLE_NAME', $nome_tabela);
        $builder->where('kc.REFERENCED_TABLE_SCHEMA', $schema);

        $result = $builder->get();

        return $result ? $result->getResultArray() : [];
    }

    /**
     * Descobre recursivamente toda a rede de tabelas relacionadas.
     *
     * Regras:
     *  - Campo _id que é PRI → busca tabelas filhas que possuem esse campo (mesmo schema)
     *  - Campo _id que NÃO é PRI (MUL/FK ou sem chave) → busca tabela pai onde é PK (cross-schema)
     *  - Tabelas cfg_ não expandem filhas (são config, teriam muitas)
     *  - Só BASE TABLE, sem views
     *  - Recursivo até esgotar a rede
     *
     * @return array Lista de nomes de tabelas relacionadas (sem a base)
     */
    /**
     * @return array ['filhas' => [...], 'pais' => [...]]
     *   filhas = descendentes (encontradas via expansão de PK) — todos os campos
     *   pais   = tabelas referenciadas via FK — apenas campos _nome
     */
    public function getRedeRelacionamentos(string $tabelaBase): array
    {
        $processadas = [];
        $filhas      = [];
        $pais        = [];

        // Fila: [tabela, tipo] — tipo 'filha' ou 'pai'
        $fila = [[$tabelaBase, 'base']];

        while (!empty($fila)) {
            [$tabela, $origem] = array_shift($fila);
            if (isset($processadas[$tabela])) continue;
            $processadas[$tabela] = true;

            if ($origem === 'filha') $filhas[] = $tabela;
            if ($origem === 'pai')   $pais[]   = $tabela;

            $dbGrSche = $this->getDbGroupAndSchema($tabela);
            if (empty($dbGrSche['schema'])) continue;

            $db     = db_connect($dbGrSche['dbGroup']);
            $campos = $this->getCampos($tabela);

            foreach ($campos as $col) {
                $nome = $col['COLUMN_NAME'];
                if (!str_ends_with($nome, '_id')) continue;
                if (str_ends_with($nome, '_excluido')) continue;

                $isPK = ($col['COLUMN_KEY'] === 'PRI');

                if ($isPK && !str_starts_with($tabela, 'cfg_')) {
                    // PK → buscar descendentes que possuem este campo
                    $descend = $this->_buscarTabelasComColuna($db, $nome, $dbGrSche['schema'], $tabela);
                    foreach ($descend as $f) {
                        if (!isset($processadas[$f])) {
                            $fila[] = [$f, 'filha'];
                        }
                    }
                } elseif (!$isPK) {
                    // FK → buscar tabela pai onde este campo é PK (cross-schema)
                    $encontrados = $this->_procurarTabelasCorrespondentes(
                        $db,
                        $nome,
                        $dbGrSche['schema'],
                        $tabela,
                        $dbGrSche['dbGroup']
                    );
                    foreach ($encontrados as $p) {
                        $nomeTab = $p['TABLE_NAME'];
                        if (!isset($processadas[$nomeTab])) {
                            $fila[] = [$nomeTab, 'pai'];
                        }
                    }
                }
            }
        }

        return ['filhas' => $filhas, 'pais' => $pais];
    }

    /**
     * Busca tabelas (BASE TABLE, sem views) que possuem a coluna informada.
     */
    private function _buscarTabelasComColuna($db, string $coluna, string $schema, string $excluirTabela): array
    {
        $builder = $db->table('information_schema.COLUMNS c');
        $builder->distinct();
        $builder->select('c.TABLE_NAME');
        $builder->join('information_schema.TABLES t', 't.TABLE_NAME = c.TABLE_NAME AND t.TABLE_SCHEMA = c.TABLE_SCHEMA', 'inner');
        $builder->where('c.COLUMN_NAME', $coluna);
        $builder->where('c.TABLE_SCHEMA', $schema);
        $builder->where('c.TABLE_NAME !=', $excluirTabela);
        $builder->where('t.TABLE_TYPE', 'BASE TABLE');

        $result = $builder->get();
        $rows   = $result ? $result->getResultArray() : [];

        return array_column($rows, 'TABLE_NAME');
    }

    /**
     * Obtém relacionamentos reversos potenciais (sem constraint).
     * Busca tabelas no mesmo schema que possuem a PK da tabela base como coluna.
     * Ex: est_requisicao (PK req_id) → encontra est_requisicao_produto que tem req_id.
     */
    private function _obterRelacionamentosReversosPotenciais($db, $nome_tabela, $schema, $jaEncontrados)
    {
        $tabelasJaIncluidas = [$nome_tabela];
        foreach ($jaEncontrados as $rel) {
            if (!empty($rel['REFERENCED_TABLE_NAME'])) {
                $tabelasJaIncluidas[] = $rel['REFERENCED_TABLE_NAME'];
            }
        }

        $builderPK = $db->table('information_schema.COLUMNS');
        $builderPK->select('COLUMN_NAME');
        $builderPK->where('TABLE_NAME', $nome_tabela);
        $builderPK->where('TABLE_SCHEMA', $schema);
        $builderPK->where('COLUMN_KEY', 'PRI');
        $result = $builderPK->get();
        $pks = $result ? $result->getResultArray() : [];

        if (empty($pks)) {
            return [];
        }

        $pkName = $pks[0]['COLUMN_NAME'];

        $builder = $db->table('information_schema.COLUMNS c');
        $builder->select('c.TABLE_NAME, c.COLUMN_NAME, t.TABLE_COMMENT');
        $builder->join('information_schema.TABLES t', 't.TABLE_NAME = c.TABLE_NAME AND t.TABLE_SCHEMA = c.TABLE_SCHEMA', 'inner');
        $builder->where('c.COLUMN_NAME', $pkName);
        $builder->where('c.TABLE_SCHEMA', $schema);
        $builder->where('c.TABLE_NAME !=', $nome_tabela);
        $builder->where('t.TABLE_TYPE', 'BASE TABLE');

        $result = $builder->get();
        $filhas = $result ? $result->getResultArray() : [];

        $ret = [];
        foreach ($filhas as $f) {
            if (in_array($f['TABLE_NAME'], $tabelasJaIncluidas)) {
                continue;
            }
            $ret[] = [
                'CONSTRAINT_NAME'       => null,
                'TABLE_NAME'            => $f['TABLE_NAME'],
                'COLUMN_NAME'           => $pkName,
                'REFERENCED_TABLE_NAME' => $f['TABLE_NAME'],
                'REFERENCED_COLUMN_NAME' => $pkName,
                'TABLE_COMMENT'         => $f['TABLE_COMMENT'],
                'tipo_relacao'          => 'potencial',
            ];
        }

        return $ret;
    }

    /**
     * Obtém potenciais relacionamentos por padrão de nomenclatura
     * (campos que terminam com _id e tabelas correspondentes existem)
     */
    private function _obterRelacionamentosPotenciais($db, $nome_tabela, $schema, $relacionamentos_com_constraint, $dbGroup)
    {
        // Campos já mapeados por constraint
        $campos_com_constraint = [];
        foreach ($relacionamentos_com_constraint as $rel) {
            $campos_com_constraint[] = $rel['COLUMN_NAME'];
        }

        // Obter informações das colunas da tabela
        $builder = $db->table('information_schema.COLUMNS');
        $builder->select('COLUMN_NAME, COLUMN_TYPE');
        $builder->where('TABLE_NAME', $nome_tabela);
        $builder->where('TABLE_SCHEMA', $schema);
        $colunas = $builder->get()->getResultArray();

        $relacionamentos_potenciais = [];

        foreach ($colunas as $coluna) {
            $nome_coluna = $coluna['COLUMN_NAME'];

            // Verifica se é um campo *_id e não tem constraint
            if (
                preg_match('/^(.+)_id$/i', $nome_coluna, $matches) &&
                !in_array($nome_coluna, $campos_com_constraint)
            ) {

                $prefixo = $matches[1];

                // ✅ PASSA O NOME COMPLETO DO CAMPO E A TABELA BASE
                $tabelas_candidatas = $this->_procurarTabelasCorrespondentes(
                    $db,
                    $nome_coluna,  // Passa stt_id completo
                    $schema,
                    $nome_tabela,  // Tabela base para não retornar ela mesma
                    $dbGroup
                );

                foreach ($tabelas_candidatas as $tabela_cand) {
                    $nome_tabela_ref = $tabela_cand['TABLE_NAME'];
                    $comentario_tabela = $tabela_cand['TABLE_COMMENT'];

                    // Como achamos a tabela por ser chave primária, 
                    // sabemos que a coluna de referência é a própria coluna
                    $coluna_ref = $nome_coluna;

                    $relacionamentos_potenciais[] = [
                        'CONSTRAINT_NAME' => null,
                        'TABLE_NAME' => $nome_tabela,
                        'COLUMN_NAME' => $nome_coluna,
                        'REFERENCED_TABLE_NAME' => $nome_tabela_ref,
                        'REFERENCED_COLUMN_NAME' => $coluna_ref,
                        'TABLE_COMMENT' => $comentario_tabela,
                        'tipo_relacao' => 'potencial'
                    ];
                }
            }
        }
        return $relacionamentos_potenciais;
    }

    /**
     * Procura pela tabela que possui o campo como chave primária
     * Para campo stt_id, procura qual tabela tem stt_id como PK
     * 
     * @param Database $db - Conexão do banco
     * @param string $nome_coluna - Nome da coluna (ex: stt_id)
     * @param string $schema - Schema para buscar primeiro
     * @param string $nome_tabela_base - Tabela base (para não retornar ela mesma)
     * @param string $dbGroup - Grupo de banco para buscar em múltiplos schemas
     * @return array - Tabelas encontradas que possuem este campo como PK
     */
    private function _procurarTabelasCorrespondentes($db, $nome_coluna, $schema, $nome_tabela_base, $dbGroup)
    {
        $tabelas_validas = [];

        // Primeiro tenta no mesmo schema
        $tabelas_encontradas = $this->_buscarColunaEmSchema(
            $db,
            $nome_coluna,
            $schema,
            $nome_tabela_base
        );

        if (!empty($tabelas_encontradas)) {
            return $tabelas_encontradas;
        }

        // Se não encontrou no mesmo schema, procura em outros schemas do mesmo banco
        $db_info = db_connect($dbGroup);
        $builder = $db_info->table('information_schema.SCHEMATA');
        $builder->select('SCHEMA_NAME');
        $schemas = $builder->get()->getResultArray();

        foreach ($schemas as $sch) {
            $schema_name = $sch['SCHEMA_NAME'];

            // Pula o schema original e schemas do sistema
            if ($schema_name === $schema || in_array($schema_name, ['information_schema', 'mysql', 'performance_schema'])) {
                continue;
            }

            $tabelas_encontradas = $this->_buscarColunaEmSchema(
                $db_info,
                $nome_coluna,
                $schema_name,
                $nome_tabela_base
            );

            if (!empty($tabelas_encontradas)) {
                return $tabelas_encontradas;
            }
        }

        return $tabelas_validas;
    }

    /**
     * Busca por colunas em um schema específico
     * 
     * @param Database $db - Conexão do banco
     * @param string $nome_coluna - Nome da coluna a buscar
     * @param string $schema - Schema onde buscar
     * @param string $nome_tabela_base - Tabela base para não retornar ela mesma
     * @return array - Tabelas encontradas
     */
    private function _buscarColunaEmSchema($db, $nome_coluna, $schema, $nome_tabela_base)
    {
        $tabelas_validas = [];

        // Procura em todas as tabelas do schema qual delas tem este campo como PK
        $builder = $db->table('information_schema.COLUMNS col');
        $builder->select('col.TABLE_NAME, tbl.TABLE_COMMENT');
        $builder->join(
            'information_schema.TABLES tbl',
            'tbl.TABLE_NAME = col.TABLE_NAME AND tbl.TABLE_SCHEMA = col.TABLE_SCHEMA',
            'inner'
        );
        $builder->where('col.TABLE_SCHEMA', $schema);
        $builder->where('col.COLUMN_NAME', $nome_coluna);
        $builder->where('col.TABLE_NAME !=', $nome_tabela_base); // 🔴 NÃO RETORNA A TABELA BASE

        $colunas = $builder->get()->getResultArray();

        foreach ($colunas as $coluna) {
            $nome_tabela_ref = $coluna['TABLE_NAME'];

            // Verifica se este campo é chave primária
            $builder = $db->table('information_schema.KEY_COLUMN_USAGE');
            $builder->where('TABLE_SCHEMA', $schema);
            $builder->where('TABLE_NAME', $nome_tabela_ref);
            $builder->where('COLUMN_NAME', $nome_coluna);
            $builder->where('CONSTRAINT_NAME', 'PRIMARY');
            $eh_pk = $builder->get()->getNumRows() > 0;

            // Se for PK, adiciona à lista de válidas
            if ($eh_pk) {
                $tabelas_validas[] = [
                    'TABLE_NAME' => $nome_tabela_ref,
                    'TABLE_COMMENT' => $coluna['TABLE_COMMENT'],
                    'eh_chave_primaria' => true
                ];
            }
        }

        return $tabelas_validas;
    }

    /**
     * Summary of getCampos
     * Retorna os Campos  da Tabela informada
     * @param mixed $nome_tabela
     * @return array
     */
    public function getCampos($nome_tabela)
    {
        $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
        $db = db_connect($dbGrSche['dbGroup']);
        $query = $db->query("SELECT TABLE_NAME, 
                            COLUMN_NAME, 
                            IS_NULLABLE, 
                            DATA_TYPE, 
                            COALESCE(`CHARACTER_MAXIMUM_LENGTH`,NUMERIC_PRECISION) AS COLUMN_SIZE, 
                            NUMERIC_SCALE, 
                            COLUMN_COMMENT, 
                            COLUMN_KEY,
                            CONCAT(COLUMN_COMMENT,' - ',COLUMN_NAME) AS NOME_COMPLETO
                            FROM information_schema.columns
                            WHERE TABLE_NAME = '" . $nome_tabela . "'
                            AND TABLE_SCHEMA = '" . $dbGrSche['schema'] . "'");
        $ret = $query->getResultArray();
        $lq = $query = $db->getLastQuery();
        // debug($lq);
        // debug($ret);
        return $ret;
    }

    /**
     * Summary of getDetalhesCampo
     * Retorna os detalhes do Campo Informado,  da Tabela informada
     * @param mixed $nome_tabela
     * @param mixed $nome_campo
     * @return array
     */
    public function getDetalhesCampo($nome_tabela, $nome_campo)
    {
        $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
        $db = db_connect($dbGrSche['dbGroup']);
        // debug($db);
        $consulta = "SELECT TABLE_NAME, 
                            COLUMN_NAME, 
                            IS_NULLABLE, 
                            DATA_TYPE, 
                            COALESCE(`CHARACTER_MAXIMUM_LENGTH`,NUMERIC_PRECISION) AS COLUMN_SIZE, 
                            NUMERIC_SCALE, 
                            COLUMN_COMMENT, 
                            COLUMN_KEY,
                            CONCAT(COLUMN_COMMENT,' - ',COLUMN_NAME) AS NOME_COMPLETO
                            FROM information_schema.columns
                            WHERE TABLE_NAME = '" . $nome_tabela . "'
                            AND TABLE_SCHEMA = '" . $dbGrSche['schema'] . "' ";
        if (gettype($nome_campo) == 'array') {
            $str_nome_campo = '';
            for ($n = 0; $n < count($nome_campo); $n++) {
                $str_nome_campo .= "'" . $nome_campo[$n] . "',";
            }
            $str_nome_campo = rtrim($str_nome_campo, ",");
            $consulta .= "AND column_name IN ($str_nome_campo) ";
        } else {
            $consulta .= "AND column_name = '" . $nome_campo . "' ";
        }
        // debug($consulta);

        $query = $db->query($consulta);
        // debug($query, true);
        $ret = $query->getResultArray();
        $lq = $query = $db->getLastQuery();
        // debug($ret, true);
        return $ret;
    }

    /**
     * Summary of getCampoChave
     * Retorna os Campos Chaves da Tabela informada
     * @param mixed $nome_tabela
     * @return array
     */
    public function getCampoChave($nome_tabela)
    {
        $dbGrSche = $this->getDbGroupAndSchema($nome_tabela);
        // debug($dbGrSche['dbGroup']);
        $array = ['table_name' => $nome_tabela];
        // $db = db_connect();
        $db      = db_connect($dbGrSche['dbGroup']);
        // debug($db);
        // *claude* $builder = $db (sem ->table()) não funciona: BaseConnection
        // não tem select()/where()/get() — só QueryBuilder tem, obtido via
        // ->table(). Sem isso, essa chamada sempre estourava
        // "Call to undefined method ...Connection::select()".
        $builder = $db->table('information_schema.columns');
        $builder->select('TABLE_NAME, COLUMN_NAME,
                                IS_NULLABLE, 
                                DATA_TYPE, 
                                COALESCE(`CHARACTER_MAXIMUM_LENGTH`, NUMERIC_PRECISION) AS COLUMN_SIZE, 
                                COLUMN_COMMENT, 
                                COLUMN_KEY');
        $builder->where($array);
        $builder->where('COLUMN_KEY', 'PRI');
        $builder->where('table_schema', $dbGrSche['schema']);

        // debug($builder);
        $ret = $builder->get()->getResultArray();
        // debug($db->getLastQuery());
        // debug($ret);
        return $ret;
    }

    /**
     * Retorna as tabelas de um banco específico pelo dbGroup do módulo.
     * @param string $dbGroup      Ex: 'dbEstoque', 'dbProduto', 'dbOcorrencia', 'default'
     * @param bool   $includeViews Se true, inclui views além das tabelas base
     * @return array
     */
    public function getTabelasPorDbGroup(string $dbGroup, bool $includeViews = false, bool $tabelabase = true): array
    {
        $gruposValidos = $this->dbGroupsConhecidos;

        if (!in_array($dbGroup, $gruposValidos, true)) {
            return [];
        }

        $db      = db_connect($dbGroup);
        // Schema real da conexão (app/Config/Database.php), em vez de adivinhar
        // dev_/prd_ pela base_url() — falha fora de requests HTTP (CLI, jobs, cron).
        $schema  = $db->getDatabase();
        $builder = $db->table('information_schema.tables');
        $builder->select(['table_schema', 'table_name', 'table_rows', 'table_comment']);
        $builder->where('table_schema', $schema);

        if (!$includeViews) {
            $builder->where('table_type', 'BASE TABLE');
        }

        $builder->orderBy('table_name', 'ASC');

        $ret = $builder->get()->getResultArray();

        if ($tabelabase) {
            $all_names = array_column($ret, 'table_name');
            $ret = array_values(array_filter($ret, function ($row) use ($all_names) {
                foreach ($all_names as $other) {
                    if ($other !== $row['table_name'] && str_starts_with($row['table_name'], $other . '_')) {
                        return false;
                    }
                }
                return true;
            }));
        }

        return $ret;
    }

    /**
     * Lista as views que começam com o prefixo informado, em todos os
     * dbGroups conhecidos do sistema ($dbGroupsConhecidos). Usada pelo tipo
     * de alerta 'consulta' de Notificações SMS (nsc_view_consulta), ver
     * docs/desenvolvimento/notificacoes-sms-tipos-alerta-dev.md, seção 4.2.
     *
     * @return array<int, array{dbGroup: string, schema: string, view: string}>
     */
    public function getViewsPorPrefixo(string $prefixo): array
    {
        $ret = [];
        foreach ($this->dbGroupsConhecidos as $dbGroup) {
            $db     = db_connect($dbGroup);
            $schema = $db->getDatabase();

            $builder = $db->table('information_schema.tables');
            $builder->select(['table_schema', 'table_name']);
            $builder->where('table_schema', $schema);
            $builder->where('table_type', 'VIEW');
            $builder->like('table_name', $prefixo, 'after');

            foreach ($builder->get()->getResultArray() as $row) {
                $ret[] = ['dbGroup' => $dbGroup, 'schema' => $row['table_schema'], 'view' => $row['table_name']];
            }
        }
        return $ret;
    }

    function getDbGroupAndSchema(?string $nome_tabela): array
    {
        // Mapeia o prefixo do nome da tabela para o dbGroup correspondente.
        $groupMap = [
            'vw_est' => 'dbEstoque',
            'est'    => 'dbEstoque',
            'vw_oco' => 'dbOcorrencia',
            'oco'    => 'dbOcorrencia',
            'vw_pro' => 'dbProduto',
            'pro'    => 'dbProduto',
            'vw_cfg' => 'default',
            'cfg'    => 'default',
            'vw_log' => 'dbLogistica',
            'log'    => 'dbLogistica',
        ];

        // Ordem de verificação: prefixos maiores primeiro para evitar conflito (ex: vw_est vs est)
        $prefixes = ['vw_est', 'vw_oco', 'vw_pro', 'vw_cfg', 'vw_log', 'est', 'oco', 'pro', 'cfg', 'log'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($nome_tabela, $prefix)) {
                $dbGroup = $groupMap[$prefix];

                // Schema real da conexão (app/Config/Database.php), em vez de adivinhar
                // dev_/prd_ pela base_url() — falha fora de requests HTTP (CLI, jobs, cron).
                return [
                    'dbGroup' => $dbGroup,
                    'schema'  => db_connect($dbGroup)->getDatabase(),
                ];
            }
        }

        // Retorna nulo caso nenhum prefixo conhecido seja encontrado
        return ['dbGroup' => null, 'schema' => null];
    }

    /**
     * Summary of getSchemaEstruturado
     * Monta um array estruturado do schema (colunas + FKs) das tabelas
     * informadas, reaproveitando getCampos() e getRelacionamentos().
     * Resolve sozinho o dbGroup/schema de cada tabela pelo prefixo.
     *
     * Criada por: Claude / Dezembro 2025 (gerador de relatórios em linguagem natural)
     *
     * @param string[] $tabelas Lista de tabelas a incluir
     * @return array<string, array>
     */
    public function getSchemaEstruturado(array $tabelas): array
    {
        $schema = [];

        foreach ($tabelas as $tabela) {
            $dbGrSche = $this->getDbGroupAndSchema($tabela);

            // Pula tabela com prefixo desconhecido (não sabemos o banco)
            if ($dbGrSche['schema'] === null) {
                continue;
            }

            $campos = $this->getCampos($tabela);
            $fks    = $this->getRelacionamentos($tabela);

            $schema[$tabela] = [
                'dbGroup' => $dbGrSche['dbGroup'],
                'schema'  => $dbGrSche['schema'],
                'columns' => [],
                'foreign_keys' => [],
            ];

            foreach ($campos as $c) {
                $schema[$tabela]['columns'][] = [
                    'name'  => $c['COLUMN_NAME'],
                    'type'  => $c['DATA_TYPE'],
                    'label' => $c['COLUMN_COMMENT'] ?: null, // seu rótulo
                    'key'   => $c['COLUMN_KEY'],             // PRI, MUL, UNI
                ];
            }

            foreach ($fks as $fk) {
                $schema[$tabela]['foreign_keys'][] = [
                    'column'     => $fk['COLUMN_NAME'],
                    'ref_table'  => $fk['REFERENCED_TABLE_NAME'],
                    'ref_column' => $fk['REFERENCED_COLUMN_NAME'],
                ];
            }
        }

        return $schema;
    }

    /**
     * Summary of getSchemaContext
     * Monta o texto legível (com os rótulos/COMMENT) que será injetado
     * no prompt da LLM para a geração de SQL.
     *
     * @param string[] $tabelas Lista de tabelas a incluir
     * @return string
     */
    public function getSchemaContext(array $tabelas): string
    {
        $schema = $this->getSchemaEstruturado($tabelas);
        $out    = [];

        foreach ($schema as $tabela => $info) {
            // Qualifica com o schema, pois o sistema é multi-banco
            $out[] = "Tabela: {$info['schema']}.{$tabela}";

            foreach ($info['columns'] as $col) {
                $line = "  - {$col['name']} ({$col['type']})";
                if ($col['label']) {
                    $line .= ": {$col['label']}";
                }
                if ($col['key'] === 'PRI') {
                    $line .= " [chave primária]";
                }
                $out[] = $line;
            }

            foreach ($info['foreign_keys'] as $fk) {
                $out[] = "  - FK: {$fk['column']} referencia "
                    . "{$fk['ref_table']}.{$fk['ref_column']}";
            }

            $out[] = '';
        }

        return implode("\n", $out);
    }
}
