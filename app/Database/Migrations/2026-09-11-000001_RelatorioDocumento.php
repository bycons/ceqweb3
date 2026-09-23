<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Infraestrutura do novo tipo de saída "DOCUMENTO" do gerador de relatórios
 * configurável (`App\Controllers\Config\CfgRelatorio`), ver
 * docs/desenvolvimento (plano "Novo tipo de saída Documento no gerador de
 * relatórios").
 *
 * 100% aditivo — `rel_tipo_saida` tem default `'TABULAR'`, nenhum relatório
 * já cadastrado muda de comportamento:
 *  - `cfg_relatorios` ganha `rel_tipo_saida` / `rel_tabela_detalhe` /
 *    `rel_detalhe_campo_vinculo`; `rel_tabela_base` passa a aceitar NULL no
 *    banco (a obrigatoriedade continua garantida na validação do Model,
 *    `required_if[rel_tipo_saida,TABULAR,DOCUMENTO]` — ambos os tipos usam
 *    `rel_tabela_base`, então na prática permanece sempre obrigatório; o
 *    NULL no banco é só para não travar em cenários futuros).
 *  - 3 tabelas novas de colunas (`cfg_rel_camposcab`, `cfg_rel_colunas_doc`,
 *    `cfg_rel_textoslivres`), no mesmo padrão de `cfg_rel_colunas`
 *    (tabela|campo|tamanho|tipo no picker).
 *  - `cfg_rel_joins` ganha `rjo_grupo` (CABECALHO/TABELA) para não misturar
 *    os joins da tabela base com os da tabela de detalhe do Documento.
 *
 * IMPORTANTE: as tabelas `cfg_relatorios`/`cfg_rel_colunas`/`cfg_rel_filtros`/
 * `cfg_rel_joins`/`cfg_rel_permissao` já existem em produção SEM migration
 * própria no repositório — esta é a primeira migration que toca esse
 * conjunto de tabelas, por isso todo o up() é feito de forma idempotente
 * (confere existência de tabela/coluna antes de alterar), permitindo rodar
 * em qualquer ambiente independente do que já foi ou não aplicado
 * manualmente.
 *
 * Esta migration NÃO deve ser executada sem confirmação do usuário.
 */
class RelatorioDocumento extends Migration
{
    protected $DBGroup = 'default';

    public function up()
    {
        $this->alterCfgRelatorios();
        $this->criaCfgRelCamposCab();
        $this->criaCfgRelColunasDoc();
        $this->criaCfgRelTextosLivres();
        $this->alterCfgRelJoins();
    }

    public function down()
    {
        $db    = db_connect('default');
        $forge = \Config\Database::forge('default');

        $forge->dropTable('cfg_rel_textoslivres', true);
        $forge->dropTable('cfg_rel_colunas_doc', true);
        $forge->dropTable('cfg_rel_camposcab', true);

        if ($this->columnExists('default', 'cfg_rel_joins', 'rjo_grupo')) {
            $db->query('ALTER TABLE cfg_rel_joins DROP COLUMN rjo_grupo');
        }

        // rel_tipo_saida / rel_tabela_detalhe / rel_detalhe_campo_vinculo e a
        // liberação de NULL em rel_tabela_base não são revertidos no down() —
        // reverter apagaria configuração real de relatórios já cadastrados
        // como DOCUMENTO (mesmo critério já usado nas demais migrations deste
        // projeto: dado de configuração não é removido automaticamente).
    }

    // ─────────────────────────────────────────────────────────────────────
    //  cfg_relatorios
    // ─────────────────────────────────────────────────────────────────────

    private function alterCfgRelatorios()
    {
        $db    = db_connect('default');
        $forge = \Config\Database::forge('default');

        if (!$this->columnExists('default', 'cfg_relatorios', 'rel_tipo_saida')) {
            $forge->addColumn('cfg_relatorios', [
                'rel_tipo_saida' => [
                    'type'       => 'ENUM',
                    'constraint' => ['TABULAR', 'DOCUMENTO'],
                    'default'    => 'TABULAR',
                    'null'       => false,
                    'after'      => 'rel_nome',
                ],
            ]);
        }

        if (!$this->columnExists('default', 'cfg_relatorios', 'rel_tabela_detalhe')) {
            $forge->addColumn('cfg_relatorios', [
                'rel_tabela_detalhe' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'comment'    => 'Tabela da grade repetível do Documento (só rel_tipo_saida=DOCUMENTO)',
                ],
            ]);
        }

        if (!$this->columnExists('default', 'cfg_relatorios', 'rel_detalhe_campo_vinculo')) {
            $forge->addColumn('cfg_relatorios', [
                'rel_detalhe_campo_vinculo' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'comment'    => 'Coluna de rel_tabela_detalhe que == :id_registro (só DOCUMENTO)',
                ],
            ]);
        }

        // rel_tabela_base passa a aceitar NULL no banco — obrigatoriedade
        // real garantida via validationRules (required_if) no Model.
        $db->query('ALTER TABLE cfg_relatorios MODIFY COLUMN rel_tabela_base VARCHAR(64) NULL');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  cfg_rel_camposcab — aba "Cabeçalho" (escopo: rel_tabela_base)
    // ─────────────────────────────────────────────────────────────────────

    private function criaCfgRelCamposCab()
    {
        if ($this->tableExists('default', 'cfg_rel_camposcab')) {
            return;
        }

        $forge = \Config\Database::forge('default');

        $forge->addField([
            'rcc_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'rel_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'rcc_tabela' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rcc_campo' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rcc_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'rcc_tamanho' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'rcc_tipo_dado' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => '',
            ],
            'rcc_largura_col' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'col-3',
                'comment'    => 'Largura no grid do PDF/preview (ex.: col-3, col-6, col-12)',
            ],
            'rcc_ordem' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
        ]);

        $forge->addKey('rcc_id', true);
        $forge->addKey('rel_id');
        $forge->addForeignKey('rel_id', 'cfg_relatorios', 'rel_id', '', 'CASCADE', 'fk_rcc_rel');
        $forge->createTable('cfg_rel_camposcab', true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  cfg_rel_colunas_doc — aba "Tabela" (escopo: rel_tabela_detalhe)
    // ─────────────────────────────────────────────────────────────────────

    private function criaCfgRelColunasDoc()
    {
        if ($this->tableExists('default', 'cfg_rel_colunas_doc')) {
            return;
        }

        $forge = \Config\Database::forge('default');

        $forge->addField([
            'rct_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'rel_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'rct_tabela' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rct_campo' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rct_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'rct_tamanho' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'rct_tipo_dado' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => '',
            ],
            'rct_largura' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'rct_ordem' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
        ]);

        $forge->addKey('rct_id', true);
        $forge->addKey('rel_id');
        $forge->addForeignKey('rel_id', 'cfg_relatorios', 'rel_id', '', 'CASCADE', 'fk_rct_rel');
        $forge->createTable('cfg_rel_colunas_doc', true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  cfg_rel_textoslivres — aba "Textos Livres" (escopo: rel_tabela_base)
    // ─────────────────────────────────────────────────────────────────────

    private function criaCfgRelTextosLivres()
    {
        if ($this->tableExists('default', 'cfg_rel_textoslivres')) {
            return;
        }

        $forge = \Config\Database::forge('default');

        $forge->addField([
            'rtx_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'rel_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'rtx_tabela' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rtx_campo' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'rtx_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'rtx_ordem' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
        ]);

        $forge->addKey('rtx_id', true);
        $forge->addKey('rel_id');
        $forge->addForeignKey('rel_id', 'cfg_relatorios', 'rel_id', '', 'CASCADE', 'fk_rtx_rel');
        $forge->createTable('cfg_rel_textoslivres', true);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  cfg_rel_joins — rjo_grupo (CABECALHO/TABELA)
    // ─────────────────────────────────────────────────────────────────────

    private function alterCfgRelJoins()
    {
        if ($this->columnExists('default', 'cfg_rel_joins', 'rjo_grupo')) {
            return;
        }

        $forge = \Config\Database::forge('default');

        $forge->addColumn('cfg_rel_joins', [
            'rjo_grupo' => [
                'type'       => 'ENUM',
                'constraint' => ['CABECALHO', 'TABELA'],
                'default'    => 'CABECALHO',
                'null'       => false,
                'comment'    => 'A que grupo de joins pertence: cabeçalho (rel_tabela_base) ou tabela (rel_tabela_detalhe) do Documento. Tabular sempre usa CABECALHO (default, retrocompatível).',
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function tableExists(string $group, string $table): bool
    {
        return db_connect($group)->tableExists($table);
    }

    private function columnExists(string $group, string $table, string $column): bool
    {
        return db_connect($group)->fieldExists($column, $table);
    }
}
