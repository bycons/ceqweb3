<?php

namespace App\Entities\Fornecedores;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;

/**
 * T43 — aba "Ações" (oco_notif_evento_acao, prefixo nac_). RN03.21/RN03.22.
 * Mirror de App\Entities\Ocorrencia\EntOcoSubtOcorrencia::defCamposAcao() —
 * mesmo padrão de campo condicional por tpa_tipo, reaproveitando
 * verificaTipoAcao() (my_fields.js) sem alteração. Diferenças: aqui não há
 * mod_id/tel_id (T43 não usa "Abrir Tela"/tpa_tipo=2 do mesmo jeito — mantido
 * por paridade de campo condicional, mas RN03.22 só executa de fato
 * "Gerar Movimentação"/tpa_tipo=3 no store()); adiciona nac_ordem (RN03.22 —
 * ordem de execução sequencial).
 */
class EntOcoNotifEventoAcao extends Entity
{
    protected $attributes = [
        'nac_id'    => null,
        'nev_id'    => null,
        'tpa_id'    => null,
        'nac_ordem' => null,
        'stt_id'    => null,
        'tmo_id'    => null,
    ];

    protected $casts = [
        'nac_id'    => 'integer',
        'nev_id'    => 'integer',
        'tpa_id'    => 'integer',
        'nac_ordem' => 'integer',
        'stt_id'    => 'integer',
        'tmo_id'    => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, int $pos = 0, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCamposAcao($this->toArray(), $pos, $show);
    }

    public function defCamposAcao($dados = false, $pos = 0, $show = false)
    {
        $dados = (array) $dados;
        $ret   = [];

        // RN03.21 — dropdown de ações T8 (criaSelectRelativo, mesma função
        // helper que EntOcoSubtOcorrencia::defCamposAcao() usa em T9/T12).
        $config = [];
        $config['Label']    = 'Ação';
        $config['Leitura']  = $show;
        $config['DispForm'] = 'col-5';
        $config['Largura']  = 50;
        $config['Ordem']    = $pos;
        $config['FunChan']  = 'verificaTipoAcao(this)';
        // Ação não é obrigatória na inclusão da notificação (só Dados Gerais).
        $config['Obrigatorio'] = false;

        $ret['tpa_id'] = criaSelectRelativo(
            'oco_tipo_acao',
            'tpa_id',
            'tpa_nome',
            $dados['tpa_id'] ?? '',
            1,
            'oco_notif_evento_acao',
            [],
            $config
        );

        // tipo de movimentação (visível quando tpa_tipo = 3, via verificaTipoAcao())
        unset($config['Obrigatorio']); // mantém o comportamento anterior de tmo_id/stt_id
        $config['Label']    = 'Tipo de Movimentação';
        $config['FunChan']  = '';
        $config['DispForm'] = 'col-12';
        $ret['tmo_id'] = criaSelectRelativo(
            'est_tipo_movimentacao',
            'tmo_id',
            'tmo_nome',
            $dados['tmo_id'] ?? '',
            1,
            'oco_notif_evento_acao',
            [],
            $config,
            'tmo_id_tpa'
        );

        // status (visível quando tpa_tipo = 4, via verificaTipoAcao())
        $config['Label']    = 'Status';
        $config['DispForm'] = 'col-12';
        $config['Largura']  = 50;
        $ret['stt_id'] = criaSelectRelativo(
            'cfg_status',
            'stt_id',
            'stt_nome',
            $dados['stt_id'] ?? '',
            1,
            'oco_notif_evento_acao',
            [],
            $config,
            'stt_id_tpa'
        );

        // RN03.22 — ordem de execução (nac_ordem = ordem de cadastro; campo
        // oculto, preenchido automaticamente pela posição da linha no submit)
        $ordem              = new MyCampo();
        $ordem->nome        = $ordem->id = "nac_ordem[{$pos}]";
        $ordem->valor       = $dados['nac_ordem'] ?? $pos;
        $ordem->objeto      = 'oculto';
        $ret['nac_ordem'] = $ordem->crOculto();

        // Botões adicionar/excluir linha — mesmo padrão bt-repete/bt-exclui/
        // addCampo já usado em T9 (EntOcoSubtOcorrencia::defCamposAcao()).
        $atrib['data-index'] = $pos;
        $add            = new MyCampo();
        $add->attrdata  = $atrib;
        $add->dispForm  = '2col';
        $add->nome      = "bt_addac[$pos]";
        $add->id        = "bt_addac[$pos]";
        $add->i_cone    = "<i class='fas fa-plus'></i>";
        $add->place     = 'Adicionar Ação';
        $add->classep   = 'btn-outline-success btn-sm bt-repete';
        $add->funcChan  = "addCampo('" . base_url('NotifEvento/addCampoAcao/') . "','acoes_notif',this)";
        $ret['bt_addac'] = $add->crBotao();

        $del            = new MyCampo();
        $del->attrdata  = $atrib;
        $del->dispForm  = '2col';
        $del->nome      = "bt_delac[$pos]";
        $del->id        = "bt_delac[$pos]";
        $del->i_cone    = "<i class='fas fa-trash'></i>";
        $del->classep   = 'btn-outline-danger btn-sm bt-exclui';
        $del->funcChan  = "exclui_campo('acoes_notif',this)";
        $del->place     = 'Excluir Ação';
        $ret['bt_delac'] = $del->crBotao();

        return $ret;
    }
}
