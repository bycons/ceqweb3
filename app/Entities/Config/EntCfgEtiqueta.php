<?php

namespace App\Entities\Config;

use App\Libraries\MyCampo;
use App\Models\CommonModel;
use CodeIgniter\Entity\Entity;
use App\Entities\Config\EntCfgTela;
use App\Models\Config\ConfigTelaModel;
use App\Entities\Config\EntCfgLayoutEtiq;
use App\Models\Config\ConfigDicDadosModel;

class EntCfgEtiqueta extends Entity
{
    protected $attributes = [
        'etq_id'    => null,
        'etq_nome'  => null,
        'let_id'    => null,
        'mod_id'    => null,
        'tel_id'    => null,
        'etq_ativo' => 'A',
    ];

    public array $campos = [];
    public array $camposCfg = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }


    public function defCampos($show = false, $view = '')
    {
        $dados = $this->toArray();
        $ret = [];

        $opcoes         = new CommonModel();
        $simnao['S']    = 'Sim';
        $simnao['N']    = 'Não';

        // ID
        $etq_id                 = new MyCampo('cfg_etiqueta', 'etq_id');
        $etq_id->valor          = (isset($dados['etq_id'])) ? $dados['etq_id'] : '';
        $ret['etq_id']          = $etq_id->crOculto();

        // Nome
        $nome                   =  new MyCampo('cfg_etiqueta', 'etq_nome');
        $nome->valor            = (isset($dados['etq_nome'])) ? $dados['etq_nome'] : '';
        $nome->obrigatorio      = true;
        $nome->leitura          = $show;
        $nome->dispForm         = "col-12";
        $nome->largura          = 50;
        $ret['etq_nome']        = $nome->crInput();

        // Layout
        $config = [];
        // $config['Label']     = $show;
        $config['Leitura']     = $show;
        $config['Obrigatorio'] = true;
        $config['Largura']     = 50;
        $config['DispForm']    = 'col-6';

        $ret['let_id'] = criaSelectRelativo(
            'cfg_layout_etiqueta',
            'let_id',
            'let_nome',
            $dados['let_id'] ?? '',
            1,
            'cfg_etiqueta',
            [],
            $config
        );
        // ['let_ativo' => 'A'],

        // Módulo
        $ret['mod_id'] = criaSelectRelativo(
            'cfg_modulo',
            'mod_id',
            'mod_nome',
            $dados['mod_id'] ?? '',
            1,
            'cfg_etiqueta',
            [],
            $config
        );


        // Tela

        $config['Pai']         = 'mod_id';
        $config['Urlbusca']    = base_url('buscas/busca_tela_modulo');

        $ret['tel_id'] = criaSelectRelativo(
            'cfg_tela',
            'tel_id',
            'tel_nome',
            $dados['tel_id'] ?? '',
            2,
            'cfg_etiqueta',
            [],
            $config
        );
        return $ret;
    }

    public function defCamposCfg($dados = false, $show = false, $pos = 0)
    {
        $simnao['S']    = 'Sim';
        $simnao['N']    = 'Não';

        $tela = new ConfigTelaModel();
        $admDados = new ConfigDicDadosModel();

        $opc_dic = [];
        if (isset($dados['tel_id'])) {
            $telas = $tela->getTelaId($dados['tel_id'])[0];
            if (isset($telas['tel_model']) && $telas['tel_model'] != null) {
                $model = $telas['tel_model'];
                $compl_model = substr($model, 0, 6);
                $pasta = "App\\Models\\" . $compl_model . "\\";
                $model_atual = model($pasta . $model);
                $view   = $model_atual->view;
                if (isset($model_atual->viewoutra)) {
                    $view   = $model_atual->viewoutra;
                }
                $campos_tab = $admDados->getCampos($view);
                // debug($campos_tab);
                $campos_lis = array_column($campos_tab, 'NOME_COMPLETO', 'COLUMN_NAME');
                // $campos_tab = $this->admDados->getCampos($view);
                // $campos_lis = array_column($campos_tab, 'NOME_COMPLETO', 'COLUMN_NAME');
                if (sizeof($campos_lis) <= 0) {
                    $opc_dic[0]['id'] = '-1';
                    $opc_dic[0]['text'] = 'Campos não encontrados...';
                } else {
                    $c = 0;
                    foreach ($campos_lis as $key => $value) {
                        $opc_dic[$c]['id']      = $key;
                        $opc_dic[$c]['text']    = $value;
                        $c++;
                    }
                }
            }
        }
        $base_url = base_url('/CriaEtiquetaZPL/previewEtiquetaViaAjax');

        $etc_campo              = new MyCampo('cfg_etiqueta_campo', 'etc_campo');
        $etc_campo->valor       = (isset($dados['etc_campo'])) ? $dados['etc_campo'] : '';
        $etc_campo->selecionado = $etc_campo->valor;
        $etc_campo->obrigatorio = true;
        $etc_campo->opcoes      = $opc_dic;
        $etc_campo->ordem       = $pos;
        $etc_campo->leitura     = $show;
        $etc_campo->urlbusca    = base_url(('buscas/busca_campo_tela'));
        $etc_campo->pai         = 'tel_id';
        $etc_campo->dispForm    = "col-5";
        $etc_campo->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_campo']       = $etc_campo->crDepende();

        $etc_codbar             = new MyCampo('cfg_etiqueta_campo', 'etc_codbar');
        $etc_codbar->valor      = (isset($dados['etc_codbar'])) ? $dados['etc_codbar'] : 'N';
        $etc_codbar->leitura    = $show;
        $etc_codbar->selecionado    = $etc_codbar->valor;
        $etc_codbar->opcoes     = $simnao;
        $etc_codbar->leitura    = $show;
        $etc_codbar->ordem       = $pos;
        $etc_codbar->dispForm   = "col-4 mb-4";
        $etc_codbar->funcChan    = "mostraOcultaCampo(this, 'N','etc_rotulo[$pos],etc_caracteres[$pos],etc_linhas[$pos],etc_colunas[$pos],etc_fonte[$pos],etc_tamanho[$pos],etc_alinhamento[$pos],etc_negrito[$pos],etc_italico[$pos],etc_sublinhado[$pos]');prevEtiqueta('" . $base_url . "')";
        $ret['etc_codbar']      = $etc_codbar->cr2opcoes();

        $etc_rotulo             =  new MyCampo('cfg_etiqueta_campo', 'etc_rotulo');
        $etc_rotulo->valor      = (isset($dados['etc_rotulo'])) ? $dados['etc_rotulo'] : 'Sem Rótulo';
        $etc_rotulo->obrigatorio = true;
        $etc_rotulo->ordem       = $pos;
        $etc_rotulo->leitura    = $show;
        $etc_rotulo->largura     = 30;
        $etc_rotulo->dispForm    = "col-4";
        $etc_rotulo->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_rotulo']      = $etc_rotulo->crInput();

        $etc_caract             =  new MyCampo('cfg_etiqueta_campo', 'etc_caracteres');
        $etc_caract->valor      = (isset($dados['etc_caracteres'])) ? $dados['etc_caracteres'] : '';
        $etc_caract->obrigatorio = true;
        $etc_caract->leitura     = $show;
        $etc_caract->ordem       = $pos;
        $etc_caract->largura     = 10;
        $etc_caract->size        = 3;
        $etc_caract->maximo      = 999;
        $etc_caract->dispForm    = "col-4";
        $etc_caract->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_caracteres']  = $etc_caract->crInput();

        $etc_linhas             =  new MyCampo('cfg_etiqueta_campo', 'etc_linhas');
        $etc_linhas->valor      = (isset($dados['etc_linhas'])) ? $dados['etc_linhas'] : '1';
        $etc_linhas->minimo        = 1;
        $etc_linhas->step        = 1;
        $etc_linhas->size        = 3;
        $etc_linhas->maximo      = 999;
        // $etc_linhas->maxLength   = 1;
        $etc_linhas->largura        = 10;
        $etc_linhas->obrigatorio = true;
        $etc_linhas->leitura    = $show;
        $etc_linhas->ordem       = $pos;
        $etc_linhas->dispForm    = "col-4";
        $etc_linhas->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_linhas']      = $etc_linhas->crInput();

        $etc_colunas            =  new MyCampo('cfg_etiqueta_campo', 'etc_colunas');
        $etc_colunas->valor     = (isset($dados['etc_colunas'])) ? $dados['etc_colunas'] : '50';
        // $etc_colunas->tipo        = 'inteiro';
        $etc_colunas->minimo        = 5;
        $etc_colunas->maximo        = 100;
        $etc_colunas->step        = 5;
        $etc_colunas->size        = 3;
        $etc_colunas->largura        = 8;
        $etc_colunas->obrigatorio = true;
        $etc_colunas->leitura     = $show;
        $etc_colunas->ordem       = $pos;
        $etc_colunas->dispForm    = "col-3";
        $etc_colunas->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_colunas']     = $etc_colunas->crInput();


        $opc_fonte['Arial'] = "Arial";
        $opc_fonte['Helvetica'] = "Helvetica";
        $opc_fonte['Times'] = "Times New Roman";
        $opc_fonte['Courier'] = "Verdana";
        $opc_fonte['Courier'] = "Courier New";

        $etc_fonte              = new MyCampo('cfg_etiqueta_campo', 'etc_fonte', false);
        $etc_fonte->valor       = (isset($dados['etc_fonte'])) ? $dados['etc_fonte'] : 'Arial';
        $etc_fonte->selecionado = $etc_fonte->valor;
        $etc_fonte->opcoes      = $opc_fonte;
        $etc_fonte->leitura     = $show;
        $etc_fonte->largura     = 20;
        $etc_fonte->ordem       = $pos;
        $etc_fonte->dispForm    = "col-4";
        $etc_fonte->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_fonte']       = $etc_fonte->crSelect();

        $etc_taman              =  new MyCampo('cfg_etiqueta_campo', 'etc_tamanho');
        $etc_taman->valor       = (isset($dados['etc_tamanho'])) ? $dados['etc_tamanho'] : '12';
        // $etc_taman->tipo        = 'nteiro';
        $etc_taman->minimo        = 4;
        $etc_taman->maximo        = 99;
        $etc_taman->step        = 1;
        $etc_taman->size        = 2;
        $etc_taman->obrigatorio = true;
        $etc_taman->leitura     = $show;
        $etc_taman->largura        = 10;
        $etc_taman->ordem       = $pos;
        $etc_taman->dispForm    = "col-4";
        $etc_taman->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_tamanho']     = $etc_taman->crInput();

        $opc_alinhamento['L'] = 'Esquerda';
        $opc_alinhamento['C'] = 'Centralizado';
        $opc_alinhamento['R'] = 'Direita';
        $opc_alinhamento['J'] = 'Justificado';
        $etc_alinh              = new MyCampo('cfg_etiqueta_campo', 'etc_alinhamento', false);
        $etc_alinh->valor       = (isset($dados['etc_alinhamento'])) ? $dados['etc_alinhamento'] : 'E';
        $etc_alinh->selecionado = $etc_alinh->valor;
        $etc_alinh->opcoes      = $opc_alinhamento;
        $etc_alinh->leitura     = $show;
        $etc_alinh->obrigatorio     = true;
        $etc_alinh->largura     = 25;
        $etc_alinh->ordem       = $pos;
        $etc_alinh->dispForm    = "col-4";
        $etc_alinh->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_alinhamento'] = $etc_alinh->crSelect();

        $etc_negrito            = new MyCampo('cfg_etiqueta_campo', 'etc_negrito', false);
        $etc_negrito->valor     = (isset($dados['etc_negrito'])) ? $dados['etc_negrito'] : 'N';
        $etc_negrito->leitura   = $show;
        $etc_negrito->selecionado    = $etc_negrito->valor;
        $etc_negrito->opcoes    = $simnao;
        $etc_negrito->obrigatorio    = true;
        $etc_negrito->ordem       = $pos;
        $etc_negrito->classep        = 'mb2';
        $etc_negrito->dispForm    = "col-4";
        $etc_negrito->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_negrito']     = $etc_negrito->cr2opcoes();

        $etc_italico            = new MyCampo('cfg_etiqueta_campo', 'etc_italico');
        $etc_italico->valor     = (isset($dados['etc_italico'])) ? $dados['etc_italico'] : 'N';
        $etc_italico->leitura   = $show;
        $etc_italico->selecionado    = $etc_italico->valor;
        $etc_italico->opcoes    = $simnao;
        $etc_italico->leitura   = $show;
        $etc_italico->ordem       = $pos;
        $etc_italico->dispForm    = "col-4";
        $etc_italico->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_italico']     = $etc_italico->cr2opcoes();

        $etc_sublinhado            = new MyCampo('cfg_etiqueta_campo', 'etc_sublinhado');
        $etc_sublinhado->valor     = (isset($dados['etc_sublinhado'])) ? $dados['etc_sublinhado'] : 'N';
        $etc_sublinhado->leitura   = $show;
        $etc_sublinhado->selecionado    = $etc_sublinhado->valor;
        $etc_sublinhado->opcoes    = $simnao;
        $etc_sublinhado->leitura   = $show;
        $etc_sublinhado->ordem       = $pos;
        $etc_sublinhado->dispForm    = "col-4";
        $etc_sublinhado->funcChan    = "prevEtiqueta('" . $base_url . "')";
        $ret['etc_sublinhado']     = $etc_sublinhado->cr2opcoes();

        $atrib['data-index'] = $pos;
        $add            = new MyCampo();
        $add->attrdata  = $atrib;
        $add->tipo      = "button";
        $add->dispForm  = '2col';
        $add->nome      = "bt_add[$pos]";
        $add->id        = "bt_add[$pos]";
        $add->i_cone    = "<i class='fas fa-plus'></i>";
        $add->place     = "Adicionar Campo";
        $add->classep   = "btn-outline-success btn-sm bt-repete";
        $add->funcChan  = "addCampo('" . base_url("CfgEtiqueta/addCampo/") . "','campos_para_etiqueta',this)";
        $ret['bt_add']   = $add->crBotao();

        $del            = new MyCampo();
        $del->attrdata  = $atrib;
        $del->tipo      = "button";
        $del->dispForm  = '2col';
        $del->nome      = "bt_del[$pos]";
        $del->id        = "bt_del[$pos]";
        $del->i_cone    = "<i class='fas fa-trash'></i>";
        $del->classep   = "btn-outline-danger btn-sm bt-exclui";
        $del->funcChan  = "exclui_campo('campos_para_etiqueta',this)";
        $del->place     = "Excluir Campo";
        $ret['bt_del']   = $del->crBotao();

        $this->camposCfg = $ret;

        return $ret;
    }
}
