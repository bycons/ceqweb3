<?php

namespace App\Entities\Logistica;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;

class EntLogCadTransportadora  extends Entity
{
    protected $attributes = [
        'trp_id'       => null,
        'trp_nome'     => null,
        'trp_apelido'  => null,
        'trp_telefone' => null,
        'trp_ativo'    => 1,
    ];

    protected $casts = [
        'trp_id'    => 'integer',
        'trp_ativo' => 'integer',
    ];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }

    public function defCampos(bool $show = false): array
    {
        $dados = $this->toArray();
        $ret = [];

        // OCULTO TRANSPORTADORA
        $trpid              = new MyCampo('log_transportadora', 'trp_id');
        $trpid->valor       = (isset($dados['trp_id'])) ? $dados['trp_id'] : '';
        $ret['trp_id'] = $trpid->crOculto();

        // OCULTO ATIVO
        $ativo              = new MyCampo('log_transportadora', 'trp_ativo');
        $ativo->valor       = (isset($dados['trp_ativo'])) ? $dados['trp_ativo'] : 1;
        $ret['trp_ativo'] = $ativo->crOculto();

        // NOME
        $nome              = new MyCampo('log_transportadora', 'trp_nome');
        $nome->valor       = (isset($dados['trp_nome'])) ? $dados['trp_nome'] : '';
        $nome->place       = 'Digite o nome da transportadora';
        $nome->obrigatorio = true;
        $nome->leitura     = $show;
        $nome->dispForm    = 'col-8';
        $ret['trp_nome'] = $nome->crInput();

        // APELIDO
        $apelido           = new MyCampo('log_transportadora', 'trp_apelido');
        $apelido->valor    = (isset($dados['trp_apelido'])) ? $dados['trp_apelido'] : '';
        $apelido->place    = 'Apelido / nome fantasia';
        $apelido->leitura  = $show;
        $apelido->dispForm = 'col-8';
        $ret['trp_apelido'] = $apelido->crInput();

        // TELEFONE
        $tel              = new MyCampo('log_transportadora', 'trp_telefone');
        $tel->valor       = (isset($dados['trp_telefone'])) ? $dados['trp_telefone'] : '';
        $tel->place       = '(00) 00000-0000';
        $tel->tipo        = 'celular';
        $tel->leitura     = $show;
        $tel->dispForm    = 'col-6';
        $ret['trp_telefone'] = $tel->crInput();

        return $ret;
    }

    /**
     * Campos da linha de horário/atendimento de log_transportadora_horarios.
     *
     * @param array $dados dados vindos de log_transportadora_horarios
     */
    function defCamposHorario(array $dados = [], bool $show = false, int $pos = 0): array
    {
        $ret = [];
    
        // OCULTO ID HORÁRIOS
        $thoid              = new MyCampo('log_transportadora_horarios', 'tho_id');
        $thoid->valor       = (isset($dados['tho_id'])) ? $dados['tho_id'] : '';
        $thoid->ordem       = $pos;
        $ret['tho_id'] = $thoid->crOculto();
        
        // OCULTO ID TRANSPORTADORA 
        $trpid              = new MyCampo('log_transportadora_horarios', 'trp_id');
        $trpid->valor       = (isset($dados['trp_id'])) ? $dados['trp_id'] : '';
        $trpid->ordem       = $pos;
        $ret['trp_id'] = $trpid->crOculto();
    
        // CIDADE
        $config = [];
        $config['Label']    = 'Cidade';
        $config['Leitura']  = $show;
        $config['Largura']  = 34;
        
        $ret['cid_id'] = criaSelectRelativo(
            'vw_cfg_cidades_uf', 
            'cid_id',            
            'cid_nome_uf',        
            $dados['cid_id'] ?? null,
            1,
            'log_transportadora_horarios',
            [],
            $config,
            "cid_id[$pos]"
        );
    
       // DESPACHO
       $desp              = new MyCampo('log_transportadora_horarios', 'tho_despacho');
       $desp->label       = 'Despacho';
       $desp->leitura     = $show;
       $desp->dispForm    = 'col-12';
       $desp->classep     = 'w-auto';
       $desp->ordem       = $pos;
       $desp->opcoes      = [
           'A' => 'Agência',
           'C' => 'Carro',
           'E' => 'Encomendas',
       ];
       $desp->selecionado = (isset($dados['tho_despacho'])) ? $dados['tho_despacho'] : 'C';
       $ret['tho_despacho'] = $desp->crRadio();

       // TITULO PREVOSÃO DE HORÁRIOS
       $titHorarios           = new MyCampo();
       $titHorarios->label    = 'Previsão de Horários:';
       $titHorarios->dispForm = 'col-12';
       $ret['tit_previsao_horarios'] = $titHorarios->crTitulo();
       
       // HORA SAÍDA
       $saida              = new MyCampo('log_transportadora_horarios', 'tho_hora_saida');
       $saida->valor       = (isset($dados['tho_hora_saida'])) ? $dados['tho_hora_saida'] : '';
       $saida->tipo        = 'time';
       $saida->maxLength   = 5;
       $saida->leitura     = $show;
       $saida->dispForm    = 'col-6';
       $saida->ordem       = $pos;
       $ret['tho_hora_saida'] = $saida->crInput();
       
       // HORA CHEGADA
       $chegada              = new MyCampo('log_transportadora_horarios', 'tho_hora_chegada');
       $chegada->valor       = (isset($dados['tho_hora_chegada'])) ? $dados['tho_hora_chegada'] : '';
       $chegada->tipo        = 'time';
       $chegada->maxLength   = 5;
       $chegada->leitura     = $show;
       $chegada->dispForm    = 'col-6';
       $chegada->ordem       = $pos;
       $ret['tho_hora_chegada'] = $chegada->crInput();
       
        // DIAS DA SEMANA
        $titDias           = new MyCampo();
        $titDias->label    = 'Dias da Semana:';
        $titDias->dispForm = 'col-12';
        $ret['tit_dias_semana'] = $titDias->crTitulo();
        
        $dias = [
            'tho_domingo' => 'Domingo',
            'tho_segunda' => 'Segunda',
            'tho_terca'   => 'Terça',
            'tho_quarta'  => 'Quarta',
            'tho_quinta'  => 'Quinta',
            'tho_sexta'   => 'Sexta',
            'tho_sabado'  => 'Sábado',
            'tho_atende_feriado' => 'Feriado',
        ];
    
        foreach ($dias as $campo => $label) {
            $dia              = new MyCampo('log_transportadora_horarios', $campo);
            $dia->valor       = 1;
            $dia->label       = $label;
            $dia->leitura     = $show;
            $dia->dispForm    = 'col-4';
            $dia->classep     = 'semmb mb-3';
            $dia->ordem       = $pos;
            $dia->selecionado = (isset($dados[$campo])) ? $dados[$campo] : 1;
            $ret[$campo] = $dia->crCheckbox(false);
        }
    
        // BOTÕES ADICIONAR E EXCLUIR
        // botão add 
        $atrib['data-index'] = $pos;
        $add            = new MyCampo();
        $add->attrdata  = $atrib;
        $add->dispForm  = 'col-3';
        $add->nome      = "bt_addtho[$pos]";
        $add->id        = "bt_addtho[$pos]";
        $add->i_cone    = "<i class='fas fa-plus'></i>";
        $add->place     = "Adicionar Horário";
        $add->classep   = "btn-outline-success btn-sm bt-repete";
        $add->funcChan  = "addCampo('" . base_url("CadTransportadora/addCampoHorario/") . "','horarios',this); setTimeout(function(){acerta_botoes_rep('horarios');}, 50)";
        
        $ret['bt_addtho'] = $add->crBotao();
        
        // botão excluir
        $del            = new MyCampo();
        $del->attrdata  = $atrib;
        $del->dispForm  = 'col-3';
        $del->nome      = "bt_deltho[$pos]";
        $del->id        = "bt_deltho[$pos]";
        $del->i_cone    = "<i class='fas fa-trash'></i>";
        $del->classep   = "btn-outline-danger btn-sm bt-exclui";
        $del->funcChan  = "exclui_campo('horarios',this)";
        $del->place     = "Excluir Horário";
        $ret['bt_deltho'] = $del->crBotao();
        
        return $ret;
    }
}