<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EstComodato extends Migration
{
    public function up()
    {
        if ($this->tableExists('dbEstoque', 'est_comodato')) {
            return;
        }

        $forge = \Config\Database::forge('dbEstoque');

        $forge->addField([
            'cmd_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'comment'        => 'Chave primária do cadastro de Comodato',
            ],
            'pro_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'Referência lógica a dbProduto.pro_sap_produto.pro_id (RN03.1) - sem FK física, bancos distintos',
            ],
            'cmd_identificador' => [
                'type'       => 'VARCHAR',
                'constraint' => 15,
                'comment'    => 'ID / código de barras do item em comodato (RN03.3)',
            ],
            'cmd_ativo' => [
                'type'       => 'CHAR',
                'constraint' => 1,
                'default'    => 'A',
                'comment'    => "A = Ativo, I = Inativo (RN05.1)",
            ],
            'cmd_excluido' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Soft delete (deletedField do Model) - RN06.2',
            ],
        ]);

        $forge->addKey('cmd_id', true);
        $forge->addKey('pro_id');
        $forge->addKey('cmd_identificador');

        $forge->createTable('est_comodato', true);
    }

    public function down()
    {
        $db = db_connect('dbEstoque');
        $db->dropTable('est_comodato', true);
    }

    private function tableExists(string $group, string $table): bool
    {
        $db = db_connect($group);
        return in_array($table, $db->listTables(), true);
    }
}
