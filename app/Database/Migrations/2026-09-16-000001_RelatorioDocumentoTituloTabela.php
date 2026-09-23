<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Título selecionável no tipo DOCUMENTO do gerador de relatórios
 * (`App\Controllers\Config\CfgRelatorio`) — byarq/usuário, 2026-09-16.
 *
 * `rel_titulo` deixa de guardar só texto livre (comportamento que o Tabular
 * mantém 100% intocado) e, no Documento, passa a guardar o NOME DA COLUNA
 * escolhida (igual `rcc_campo`); esta migration adiciona `rel_titulo_tabela`
 * (igual `rcc_tabela`) pra registrar de qual tabela essa coluna vem
 * (rel_tabela_base ou rel_tabela_detalhe) — mesmo padrão já usado em
 * cfg_rel_camposcab/cfg_rel_colunas_doc/cfg_rel_textoslivres.
 *
 * 100% aditivo — `rel_titulo_tabela` é NULL por padrão, e só é preenchida
 * (e só é lida) quando `rel_tipo_saida = DOCUMENTO` (ver CfgRelatorio::
 * store()/previewDocumento(), CriamPdf2026::_montarDadosDocumento()). Para
 * relatórios TABULAR, `rel_titulo` continua sendo o texto livre de sempre e
 * `rel_titulo_tabela` fica sempre NULL, sem uso.
 *
 * Migration separada da anterior (2026-09-11-000001_RelatorioDocumento, já
 * rodada em dev) — migrations já executadas não são reeditadas.
 *
 * Esta migration NÃO deve ser executada sem confirmação do usuário.
 */
class RelatorioDocumentoTituloTabela extends Migration
{
    protected $DBGroup = 'default';

    public function up()
    {
        if ($this->columnExists('default', 'cfg_relatorios', 'rel_titulo_tabela')) {
            return;
        }

        $forge = \Config\Database::forge('default');

        $forge->addColumn('cfg_relatorios', [
            'rel_titulo_tabela' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'rel_titulo',
                'comment'    => 'Tabela de origem da coluna escolhida como Título (rel_tabela_base ou rel_tabela_detalhe) — só DOCUMENTO. NULL/sem uso no Tabular, onde rel_titulo continua texto livre.',
            ],
        ]);
    }

    public function down()
    {
        if (!$this->columnExists('default', 'cfg_relatorios', 'rel_titulo_tabela')) {
            return;
        }

        $db = db_connect('default');
        $db->query('ALTER TABLE cfg_relatorios DROP COLUMN rel_titulo_tabela');
    }

    private function columnExists(string $group, string $table, string $column): bool
    {
        return db_connect($group)->fieldExists($column, $table);
    }
}
