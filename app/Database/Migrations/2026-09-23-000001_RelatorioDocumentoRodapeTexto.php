<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ajustes do tipo DOCUMENTO do gerador de relatórios
 * (`App\Controllers\Config\CfgRelatorio`) — usuário, 2026-09-23:
 *
 *  - A aba "Textos Livres" passa a se chamar "Rodapé" (é impressa depois da
 *    Tabela/detalhes). A tabela continua `cfg_rel_textoslivres`.
 *  - Cada linha do Rodapé pode ter um TEXTO DIGITADO (`rtx_texto`, novo) e/ou
 *    um campo vinculado (`rtx_tabela`/`rtx_campo`, que passam a aceitar NULL).
 *  - O Rótulo deixa de ser obrigatório no Cabeçalho (`rcc_label`) e no
 *    Rodapé (`rtx_label`).
 *
 * 100% aditivo/afrouxamento de NOT NULL — nenhum dado existente muda.
 * O MODIFY COLUMN reaplica o COLUMN_COMMENT atual (é de onde o MyCampo tira
 * o rótulo do campo na tela) para não perdê-lo.
 *
 * Migration separada das anteriores (já rodadas em dev) — migrations já
 * executadas não são reeditadas.
 *
 * Esta migration NÃO deve ser executada sem confirmação do usuário.
 */
class RelatorioDocumentoRodapeTexto extends Migration
{
    protected $DBGroup = 'default';

    public function up()
    {
        if (!$this->columnExists('cfg_rel_textoslivres', 'rtx_texto')) {
            $forge = \Config\Database::forge('default');
            $forge->addColumn('cfg_rel_textoslivres', [
                'rtx_texto' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'after'   => 'rtx_campo',
                    'comment' => 'Texto',
                ],
            ]);
        }

        $this->tornaNulo('cfg_rel_textoslivres', 'rtx_tabela', 'VARCHAR(64)', '');
        $this->tornaNulo('cfg_rel_textoslivres', 'rtx_campo', 'VARCHAR(64)', 'Campo');
        $this->tornaNulo('cfg_rel_textoslivres', 'rtx_label', 'VARCHAR(100)', 'Rótulo');
        $this->tornaNulo('cfg_rel_camposcab', 'rcc_label', 'VARCHAR(100)', 'Rótulo');
    }

    public function down()
    {
        // rtx_texto não é removido e as colunas não voltam a NOT NULL —
        // reverter apagaria/quebraria configuração real de relatórios (mesmo
        // critério de 2026-09-11-000001_RelatorioDocumento).
    }

    /**
     * MODIFY COLUMN para NULL preservando o COMMENT atual (usa $commentPadrao
     * só se a coluna ainda não tiver comentário).
     */
    private function tornaNulo(string $tabela, string $coluna, string $tipo, string $commentPadrao): void
    {
        $db  = db_connect('default');
        $row = $db->query(
            'SELECT COLUMN_COMMENT FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabela, $coluna]
        )->getRowArray();

        if (!$row) {
            return;
        }

        $comment = $row['COLUMN_COMMENT'] !== '' ? $row['COLUMN_COMMENT'] : $commentPadrao;

        $db->query("ALTER TABLE {$tabela} MODIFY COLUMN {$coluna} {$tipo} NULL COMMENT " . $db->escape($comment));
    }

    private function columnExists(string $table, string $column): bool
    {
        return db_connect('default')->fieldExists($column, $table);
    }
}
