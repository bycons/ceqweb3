<?php

namespace App\Models\Config;

use CodeIgniter\Model;
use App\Entities\Config\EntCfgRelTextos;
use App\Models\LogMonModel;

class ConfigRelTextosModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cfg_rel_textoslivres';
    protected $primaryKey       = 'rtx_id';

    protected $returnType       = EntCfgRelTextos::class;
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'rtx_id',
        'rel_id',
        'rtx_tabela',
        'rtx_campo',
        'rtx_texto',
        'rtx_label',
        'rtx_ordem',
    ];

    // Aba "Rodapé" (usuário, 2026-09-23): cada linha tem texto digitado
    // (rtx_texto) e/ou campo vinculado (rtx_tabela/rtx_campo); rótulo
    // opcional. "Ao menos um dos dois" é garantido em
    // CfgRelatorio::_extrairTextosPost() (linha vazia é descartada).
    protected $validationRules = [
        'rel_id'     => 'required|integer',
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
     * Retorna todos os textos livres de um relatório, ordenados.
     */
    public function getTextos(int $rel_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->where('rel_id', $rel_id);
        $builder->orderBy('rtx_ordem');

        return $builder->get()->getResult(EntCfgRelTextos::class);
    }

    /**
     * Retorna um texto livre específico pelo ID.
     */
    public function getTexto(int $rtx_id)
    {
        $db      = db_connect('default');
        $builder = $db->table($this->table);
        $builder->where('rtx_id', $rtx_id);

        return $builder->get()->getFirstRow(EntCfgRelTextos::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Gravação
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Substitui todos os textos livres de um relatório de uma vez.
     * Remove os existentes e regrava a lista completa.
     *
     * @param  int   $rel_id
     * @param  array $textos  Array de arrays com os campos de cada linha
     */
    public function sincronizarTextosLivres(int $rel_id, array $textos): void
    {
        $db = db_connect('default');
        $db->table($this->table)->where('rel_id', $rel_id)->delete();

        foreach ($textos as $ordem => $t) {
            $t['rel_id']    = $rel_id;
            $t['rtx_ordem'] = $ordem + 1;
            $this->insert($t);
        }
    }

    /**
     * Retorna a próxima ordem disponível para um relatório.
     */
    public function getProximaOrdem(int $rel_id): int
    {
        return (int) ($this->selectMax('rtx_ordem')
            ->where('rel_id', $rel_id)
            ->first()
            ->rtx_ordem ?? 0) + 1;
    }
}
