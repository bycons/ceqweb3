<?php

namespace App\Entities\Ocorrencia;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Models\Ocorre\OcorreTipoAcaoModel;
use App\Models\Ocorre\OcorreTipoOcorrenciaModel;

class EntOcoTipoAcao extends Entity
{
    protected $attributes = [
        'tpa_id'       => null,
        'tpa_nome'     => null,
        'tpa_ativo'    => null,
        'tpa_tipo'     => null,
        'tpa_excluido' => null,
    ];

    protected $casts = [
        'tpa_id'    => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }


    public function defCampos($dados = false, $show = false)
    {
        $ret = [];

        // ID do Tipo de Ação
        $mid            = new MyCampo('oco_tipo_acao', 'tpa_id');
        $mid->valor     = (isset($dados['tpa_id'])) ? $dados['tpa_id'] : '';
        $ret['tpa_id']   = $mid->crOculto();

        // Tipo de Ação
        $nome           =  new MyCampo('oco_tipo_acao', 'tpa_nome');
        $nome->valor    = (isset($dados['tpa_nome'])) ? $dados['tpa_nome'] : '';
        $nome->obrigatorio = true;
        $nome->leitura  = $show;
        $ret['tpa_nome'] = $nome->crInput();

        // Opções de Tipo da Ação
        $opcex['1'] = 'Justificar';
        $opcex['2'] = 'Listar Telas';
        $opcex['3'] = 'Listar Movimentações';
        $opcex['4'] = 'Listar Status';
        $opcex['5'] = 'Notificação de Fornecedor';
        $opcex['6'] = 'Cancelar Atendimento';
        $opcex['7'] = 'Cancelar Conferência';
        $opcex['8'] = 'Cancelar Inspeção';

        // Tipo da Ação
        $tipo           =  new MyCampo('oco_tipo_acao', 'tpa_tipo');
        $tipo->valor    = (isset($dados['tpa_tipo'])) ? $dados['tpa_tipo'] : '';
        $tipo->selecionado    = $tipo->valor;
        $tipo->opcoes   = $opcex;
        $tipo->dispForm     = 'col-4';
        $tipo->classep     = 'mb-2';
        $ret['tpa_tipo'] = $tipo->crRadio();

        // Retorna os campos do Tipo de Ação
        return $ret;
    }
}
