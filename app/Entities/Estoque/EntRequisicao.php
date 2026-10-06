<?php

namespace App\Entities\Estoque;

use App\Libraries\MyCampo;
use CodeIgniter\Entity\Entity;
use DateTime;

class EntRequisicao extends Entity
{
    protected $attributes = [
        'req_id'              => null,
        'req_data'            => null,
        'req_dataentrega'     => null,
        'tmo_id'              => null,
        'req_deporigem'       => null,
        'req_depdestino'      => null,
        'req_consdiaanterior' => 'S',
        'req_medconsumodias'  => 'N',
        'req_meddias'         => 2,
        'req_repetedias'      => 0,
        'req_percseguranca'   => 0,
        'req_observacao'      => null,
        'stt_id'              => null,
        'usu_nome'            => null,
        'stt_nome'            => null,
        'stt_cor'             => null,
    ];

    protected $datamap = [];
    protected $dates   = [];
    protected $casts   = [];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false, $tipo = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($this, $show, $tipo);
    }

    /**
     * defCampos
     * Lógica mantida 100% igual ao Model
     */
    public function defCampos(?object $dados = null, bool $show = false, $tipo = false)
    {
        $ret         = [];
        $simnao['S'] = 'Sim';
        $simnao['N'] = 'Não';

        // $ret['pro_id'] = EntProdutos::campoSelectProduto($dados->pro_id ?? '', $show, 'pro_produto');

        // ID da requisição
        $id            = new MyCampo('est_requisicao', 'req_id', false);
        $id->valor     = (isset($dados->req_id)) ? $dados->req_id : '';
        $id->leitura   = $show;
        $ret['req_id'] = $id->crOculto();

        // Número visual da requisição
        $num               = new MyCampo('est_requisicao', 'req_id', true);
        $num->valor        = (isset($dados->req_id)) ? str_pad($dados->req_id, 6, '0', STR_PAD_LEFT) : '';
        $num->label        = 'Requisição Nº';
        $num->leitura      = true;
        $num->dispForm     = 'col-6';
        $num->classep      = 'mb3';
        $ret['req_numero'] = $num->crInput();

        // Data de criação da requisição
        $hoje            = new DateTime();
        $data            = new MyCampo('est_requisicao', 'req_data', false);
        $data->valor     = (isset($dados->req_data)) ? $dados->req_data : $hoje->format('Y-m-d H:i:s');
        $data->leitura   = true;
        $data->dispForm  = 'col-6';
        $data->classep   = 'mb3';
        $ret['req_data'] = $data->crInput();

        // $prev = new DateTime('+1 days');
        $data          = new DateTime();
        $entr          = new MyCampo('est_requisicao', 'req_dataentrega', false);
        $entr->valor   = (isset($dados->req_dataentrega)) ? $dados->req_dataentrega : '';
        $entr->leitura = $show;
        if ($tipo && $tipo == 'copy') {
            $entr->leitura = false;
        }
        $entr->datamin     = $data->format('Y-m-d');
        $entr->obrigatorio = true;
        $entr->dispForm    = 'col-6';
        $entr->classep     = 'mb3';
        if (! $show) {
            $entr->funcBlur = 'validaDataMinima(this)';
        }
        $ret['req_dataentrega'] = $entr->crInput();

        if (! $show) {
            $config['FunChan'] = "buscaTipoMovimentacao(this,'req_deporigem','req_depdestino','req_deppadrao')";
        }
        $config['Label']    = 'Tipo de Movimentação';
        $config['DispForm'] = 'col-6';
        $config['Largura']  = 50;
        $config['Leitura']  = $show;

        // MOVIMENTAÇÃO
        $perfilId = session()->get('usu_perfil_id');

        $fil['tmo_requisicao'] = 'S';
        $ret['tmo_id']         = criaSelectRelativo(
            'vw_est_tipo_movimentacao_relac_inclusao',
            'tmo_id',
            'tmo_nome',
            $dados->tmo_id ?? '',
            1,
            'est_requisicao',
            $fil,
            $config
        );

        // Quantidade de dias de repetição automática da requisição
        $mudi                  = new MyCampo('est_requisicao', 'req_repetedias', false);
        $mudi->valor           = (isset($dados->req_repetedias)) ? $dados->req_repetedias : 0;
        $mudi->leitura         = false;
        $mudi->minimo          = 0;
        $mudi->step            = 1;
        $mudi->maximo          = 10;
        $mudi->size            = 2;
        $mudi->classep         = 'mb2';
        $mudi->dispForm        = 'col-6';
        $ret['req_repetedias'] = $mudi->crInput();

        if (isset($dados->req_deporigem)) {
            $config['Leitura'] = true;
        }
        $config['Label']   = 'Depósito de Origem';
        $config['Leitura'] = true;
        $config['Largura'] = 50;
        $config['FunChan'] = '';
        // DEPÓSITO ORIGEM
        // debug($dados->req_deporigem, true);
        $ret['req_deporigem'] = criaSelectRelativo(
            'est_sap_deposito',
            'dep_codDep',
            'dep_codDescricao',
            $dados->req_deporigem ?? '',
            1,
            'est_requisicao',
            [],
            $config,
            'req_deporigem'
        );

        // Depósito padrão
        $depr                 = new MyCampo('est_requisicao', 'req_deporigem', false);
        $depr->id             = $depr->nome             = 'req_deppadrao';
        $depr->valor          = (isset($dados->req_deppadrao)) ? $dados->req_deppadrao : '';
        $ret['req_deppadrao'] = $depr->crOculto();

        // Depósito de destino
        if (isset($dados->req_depdestino)) {
            $config['Leitura'] = true;
        }
        $config['Label'] = 'Depósito de Destino';

        $ret['req_depdestino'] = criaSelectRelativo(
            'est_sap_deposito',
            'dep_codDep',
            'dep_codDescricao',
            $dados->req_depdestino ?? '',
            1,
            'est_requisicao',
            [],
            $config,
            'req_depdestino'
        );

        // Considerar consumo do dia anterior / Média de consumo por dias
        $diaAnteriorValor  = (isset($dados->req_consdiaanterior)) ? $dados->req_consdiaanterior : 'S';
        $mediaConsumoValor = (isset($dados->req_medconsumodias)) ? $dados->req_medconsumodias : 'N';

        if ($show) {
            // Tela de consulta/edição de acompanhamento: mantém os 2 campos exatamente como antes
            $coda              = new MyCampo('est_requisicao', 'req_consdiaanterior', false);
            $coda->valor       = $diaAnteriorValor;
            $coda->leitura     = $show;
            $coda->opcoes      = $simnao;
            $coda->selecionado = $coda->valor;
            $coda->classep     = 'mb2';
            $coda->dispForm    = 'col-6';
            $ret['req_consdiaanterior'] = $coda->cr2opcoes();

            $mcdi              = new MyCampo('est_requisicao', 'req_medconsumodias', false);
            $mcdi->valor       = $mediaConsumoValor;
            $mcdi->leitura     = $show;
            $mcdi->opcoes      = $simnao;
            $mcdi->selecionado = $mcdi->valor;
            $mcdi->classep     = 'mb2';
            $mcdi->dispForm    = 'col-6';
            $ret['req_medconsumodias'] = $mcdi->cr2opcoes();
        } else {
            // Tela de inclusão: os 2 campos reais ficam ocultos (o Store continua recebendo
            // req_consdiaanterior/req_medconsumodias com 'S'/'N' normalmente); na tela aparece
            // só um cr2opcoes "Tipo de Consumo", sincronizado via JS (trocaTipoConsumo()).
            $coda        = new MyCampo('est_requisicao', 'req_consdiaanterior', false);
            $coda->valor = $diaAnteriorValor;
            $ret['req_consdiaanterior'] = $coda->crOculto();

            $mcdi        = new MyCampo('est_requisicao', 'req_medconsumodias', false);
            $mcdi->valor = $mediaConsumoValor;
            $ret['req_medconsumodias'] = $mcdi->crOculto();

            $tpco              = new MyCampo();
            $tpco->nome        = $tpco->id = 'req_tipoconsumo';
            $tpco->label       = 'Tipo de Consumo';
            $tpco->opcoes      = ['DIA' => 'Dia Anterior', 'MEDIA' => 'Média de Consumo'];
            $tpco->valor       = ($diaAnteriorValor === 'N' && $mediaConsumoValor === 'S') ? 'MEDIA' : 'DIA';
            $tpco->selecionado = $tpco->valor;
            $tpco->classep     = 'mb2';
            $tpco->dispForm    = 'col-3';
            $tpco->funcChan    = 'trocaTipoConsumo(this);';
            $ret['req_tipoconsumo'] = $tpco->cr2opcoes();
        }

        // Quantidade de dias para cálculo de média
        $medi               = new MyCampo('est_requisicao', 'req_meddias', false);
        $medi->valor        = (isset($dados->req_meddias)) ? $dados->req_meddias : 2;
        $medi->leitura      = $show;
        $medi->minimo       = 2;
        $medi->step         = 1;
        $medi->maximo       = 30;
        $medi->size         = 2;
        $medi->classep      = 'mb2';
        $medi->dispForm     = 'col-3';
        $ret['req_meddias'] = $medi->crInput();

        // Percentual de segurança aplicado ao cálculo
        $pseg                     = new MyCampo('est_requisicao', 'req_percseguranca', false);
        $pseg->valor              = (isset($dados->req_percseguranca)) ? $dados->req_percseguranca : 0;
        $pseg->leitura            = $show;
        $pseg->obrigatorio        = true;
        $pseg->minimo             = 0;
        $pseg->step               = 1;
        $pseg->maximo             = 100;
        $pseg->size               = 3;
        $pseg->classep            = 'mb2';
        $pseg->dispForm           = 'col-3';
        $ret['req_percseguranca'] = $pseg->crInput();

        $obsv                  = new MyCampo('est_requisicao', 'req_observacao', false);
        $obsv->valor           = (isset($dados->req_observacao)) ? $dados->req_observacao : '';
        $obsv->leitura         = $show;
        if ($tipo && $tipo == 'copy') {
            $obsv->leitura = false;
        }
        $obsv->classep         = 'mb2';
        $obsv->dispForm        = 'col-6';
        $ret['req_observacao'] = $obsv->crInput();

        // PRODUTO
        $config['Leitura']     = $show;
        $config['Obrigatorio'] = false;
        $config['Label']       = 'Produto';
        $config['DispForm']    = 'col-6';
        $config['Largura']     = 60;
        $config['Pai']         = 'req_depdestino';
        $config['Urlbusca']    = base_url('buscas/buscaProdutoDeposito');

        $ret['pro_id'] = criaSelectRelativo(
            '',
            '',
            '',
            '',
            4,
            'est_requisicao_produto',
            [],
            $config,
            'pro_id'
        );

        // Botão de carregamento de produtos
        $btca               = new MyCampo();
        $btca->nome         = "bt_carregar";
        $btca->id           = "bt_carregar";
        $btca->i_cone       = "<i class='fas fa-cart-flatbed fs-3'></i> <scan class='mx-3'>Carregar Produtos</scan>";
        $btca->label        = $btca->place        = "Carregar Produtos";
        $btca->classep      = "btn-outline-success btn-sm align-items-center d-flex m-3";
        $btca->funcChan     = "carregarProdutos('" . base_url("Requisicao/produtos/") . "','produtos',this); ";
        $ret['bt_carregar'] = $btca->crBotao();

        $codb           = new MyCampo('pro_sap_lote', 'lot_codbar', false);
        $codb->valor    = '';
        $codb->leitura  = false;
        $codb->classep  = 'mb2';
        $codb->dispForm = 'col-6';
        // $codb->tamanho  = 50;
        $codb->largura  = 50;
        $codb->size     = 50;
        $codb->funcBlur = 'validaCodBar(this)';
        $ret['lot_codbar'] = $codb->crInput();

        // Usuário responsável pela requisição
        $usua            = new MyCampo();
        $usua->valor     = (isset($dados->usu_nome)) ? $dados->usu_nome : '';
        $usua->id        = $usua->nome        = 'usu_nome';
        $usua->label     = 'Usuário';
        $usua->size      = 50;
        $usua->largura   = 50;
        $usua->dispForm  = 'col-6';
        $usua->leitura   = true;
        $ret['usu_nome'] = $usua->crInput();

        // Status atual da requisição
        $stat        = new MyCampo();
        $stat->valor = (isset($dados->stt_nome)) ? fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome) : '';
        $stat->id    = $stat->nome    = 'stt_nome';
        $stat->label = 'Status';
        // $stat->size           = 50;
        // $stat->largura        = 50;
        // $stat->dispForm       = 'col-4';
        $stat->leitura   = true;
        $ret['stt_nome'] = $stat->crShow();

        return $ret;
    }

    public function defCamposProdutoAte(object $dados, bool $show = false)
    {
        $ret = [];
        // debug($dados);
        $rpaid          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_id', false);
        $rpaid->valor   = (isset($dados->rpa_id)) ? $dados->rpa_id : '';
        $rpaid->leitura = $show;
        $ret['rpa_id']  = $rpaid->crOculto();

        // Campo para quantidade cancelada
        $canc          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_cancelada', false);
        $canc->id      = $canc->nome      = "rpa_cancelada_" . $dados->rep_id;
        $canc->valor   = $dados->rpa_cancelada ?? 0;
        $canc->label   = '';
        $canc->leitura = false;
        if (intval($dados->rep_quantia) == (intval($dados->rpa_atendida) + intval($dados->rpa_cancelada))) {
            $canc->leitura = true;
        }
        $canc->classep = 'semmb';
        $canc->minimo  = 0;
        $canc->maximo  = $dados->rep_quantia;
        // $canc->dispForm       = 'col-12';
        $canc->funcChan       = 'acertaSaldoReq(this)';
        $canc->size           = 4;
        $canc->largura        = 15;
        $ret['rpa_cancelada'] = $canc->crInput();

        // Campo para quantidade atendida
        $aten          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_atendida', false);
        $aten->id      = $aten->nome      = "rpa_atendida_" . $dados->rep_id;
        $aten->valor   = $dados->rpa_atendida ? $dados->rpa_atendida : 0;
        $aten->label   = '';
        $aten->size    = 4;
        $aten->leitura = true;
        if ($dados->pre_cbfabricante == 'N' && $dados->pre_undfabricante == 'N' && $dados->pre_cblote == 'N' && $dados->pre_undlote == 'N') {
            $aten->leitura = false;
        }
        if (intval($dados->rep_quantia) == (intval($dados->rpa_atendida) + intval($dados->rpa_cancelada))) {
            $aten->leitura = true;
        }
        $aten->classep = 'semmb';
        // $aten->dispForm      = 'col-12';
        $aten->minimo        = 0;
        $aten->maximo        = $dados->rep_quantia;
        $aten->funcChan      = 'acertaSaldoReq(this)';
        $aten->largura       = 15;
        $ret['rpa_atendida'] = $aten->crInput();

        $aten              = new MyCampo('est_requisicao_produto_atendimento', 'rpa_data', false);
        $aten->valor       = (isset($dados->rpa_data)) ? $dados->rpa_data : '';
        $aten->leitura     = true;
        $aten->obrigatorio = false;
        // $aten->dispForm    = 'col-6';
        $aten->classep   = 'mb3';
        $ret['rpa_data'] = $aten->crInput();

        return $ret;
    }

    public function defCamposProdutoConf(object $dados, bool $show = false)
    {
        $ret            = [];
        $rpaid          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_id', false);
        $rpaid->valor   = (isset($dados->rpa_id)) ? $dados->rpa_id : '';
        $rpaid->leitura = $show;
        $ret['rpa_id']  = $rpaid->crOculto();

        // Quantidade atendida
        // Campo para quantidade atendida
        $aten          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_atendida', false);
        $aten->id      = $aten->nome      = "rpa_atendida_" . $dados->rep_id;
        $aten->valor   = $dados->rpa_atendida ? $dados->rpa_atendida : 0;
        $aten->label   = '';
        $aten->size    = 4;
        $aten->leitura = true;
        $aten->classep = 'semmb';
        // $aten->dispForm = 'col-12';
        // $aten->funcChan       = 'acertaSaldoReq(this)';
        $aten->largura       = 12;
        $ret['rpa_atendida'] = $aten->crInput();

        // Quantidade conferida
        $conf          = new MyCampo('est_requisicao_produto_atendimento', 'rpa_conferida', false);
        $conf->id      = $conf->nome      = "rpa_conferida_" . $dados->rep_id;
        $conf->valor   = $dados->rpa_conferida ?? 0;
        $conf->label   = '';
        $conf->leitura = true;
        $conf->classep = 'semmb';
        // $conf->dispForm = 'col-12';
        $conf->minimo   = 0;
        $conf->maximo   = intval($dados->rpa_atendida);
        $conf->size     = 4;
        $conf->funcChan = 'acertaSaldoConf(this)';
        $conf->largura  = 12;
        if ($dados->pre_cbfabricante == 'N' && $dados->pre_undfabricante == 'N' && $dados->pre_cblote == 'N' && $dados->pre_undlote == 'N' && $dados->pre_cbmisturador == 'N' && $dados->pre_undmisturador == 'N') {
            $conf->leitura = false;
        }
        $ret['rpa_conferida'] = $conf->crInput();

        $dtcf                        = new MyCampo('est_requisicao_produto_atendimento', 'rpa_data_conferencia', false);
        $dtcf->valor                 = (isset($dados->rpa_data_conferencia)) ? $dados->rpa_data_conferencia : '';
        $dtcf->leitura               = true;
        $dtcf->obrigatorio           = false;
        $dtcf->dispForm              = 'col-6';
        $dtcf->classep               = 'mb3';
        $ret['rpa_data_conferencia'] = $dtcf->crInput();

        return $ret;
    }
}
