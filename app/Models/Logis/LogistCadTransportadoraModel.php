<?php

namespace App\Models\Logis;

use CodeIgniter\Model;
use App\Models\LogMonModel;
use App\Entities\Logistica\EntCadTransportadora;

class LogistCadTransportadoraModel extends Model
{
    protected $DBGroup    = 'dbLogistica';
    protected $table      = 'log_transportadora';
    protected $tableHor   = 'log_transportadora_horarios';
    protected $primaryKey = 'trp_id';

    protected $returnType = EntCadTransportadora::class;

    protected $allowedFields = [
        'trp_nome',
        'trp_apelido',
        'trp_telefone',
        'trp_ativo'
    ];

    protected $camposHorario = [
        'trp_id',
        'cid_id',
        'tho_domingo',
        'tho_segunda',
        'tho_terca',
        'tho_quarta',
        'tho_quinta',
        'tho_sexta',
        'tho_sabado',
        'tho_atende_feriado',
        'tho_despacho',
        'tho_hora_saida',
        'tho_hora_chegada'
    ];

    protected $validationRules = [
        'trp_nome' => 'required|max_length[50]|min_length[3]',
    ];


    protected $validationMessages = [
        'trp_nome'   => [
            'required'    => 'O Nome da Transportadora é obrigatório',
            'max_length'  => 'O Nome deve conter no Máximo 50 Caracteres',
            'min_length'  => 'O Nome deve conter no Mínimo 3 Caracteres',
        ],
    ];

    // Callbacks
    protected $allowCallbacks = true;

    protected $afterInsert   = ['depoisInsert'];
    protected $afterUpdate   = ['depoisUpdate'];
    protected $afterDelete   = ['depoisDelete'];

    protected $logdb;

    protected function depoisInsert(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Incluído', $data['id'], $data['data']);
        return $data;
    }

    protected function depoisUpdate(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Alteração', $data['id'][0], $data['data']);
        return $data;
    }

    protected function depoisDelete(array $data)
    {
        (new LogMonModel())->insertLog($this->table, 'Excluído', $data['id'][0], $data['data']);
        return $data;
    }


    public function getTransportadora($id)
    {
        $db = db_connect('dbLogistica');

        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->where('trp_id', $id);

        return $builder->get()->getRow();
    }

    public function getListaCompleta()
    {
        $db = db_connect('dbLogistica');

        $builder = $db->table($this->table);
        $builder->select('*');
        $builder->orderBy('trp_ativo DESC, trp_nome ASC');

        return $builder->get()->getResult();
    }

    public function getHorarioPorTransportadora($trp_id)
    {
        $db = db_connect('dbLogistica');

        $builder = $db->table($this->tableHor);
        $builder->select('*');
        $builder->where('trp_id', $trp_id);

        return $builder->get()->getRow();
    }

    public function salvarHorario($trp_id, array $dados)
    {
        $db = db_connect('dbLogistica');

        $builder = $db->table($this->tableHor);

        $dados = array_intersect_key($dados, array_flip($this->camposHorario));
        $dados['trp_id'] = $trp_id;

        $existente = $this->getHorarioPorTransportadora($trp_id);

        if ($existente) {
            $builder->where('tho_id', $existente->tho_id);
            $builder->update($dados);

            return $existente->tho_id;
        }

        $builder->insert($dados);

        return $db->insertID();
    }

    public function salvarComHorario(array $dadosTrp, array $dadosHor, $trp_id = false)
    {
        if ($trp_id) {
            $this->update($trp_id, $dadosTrp);
        } else {
            $trp_id = $this->insert($dadosTrp);
        }

        $this->salvarHorario($trp_id, $dadosHor);

        return $trp_id;
    }
}