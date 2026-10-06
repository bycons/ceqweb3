<?php

namespace App\Entities\Estoque;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;

class EntEstComodato extends Entity
{
    protected $attributes = [
        'cmd_id'            => null,
        'pro_id'            => null,
        'cmd_identificador' => null,
        'cmd_ativo'         => 'A',
        'cmd_excluido'      => null,
        // Campos exibidos, vindos de dbProduto.pro_sap_produto (não gravados)
        'pro_codpro'        => null,
        'pro_despro'        => null,
    ];

    protected $datamap  = [];
    protected $dates    = ['cmd_excluido'];
    protected $casts    = [];

    public array $campos = [];

    public function __construct(?array $data = null, bool $show = false)
    {
        parent::__construct($data);
        $this->campos = $this->defCampos($show);
    }

    public function defCampos(bool $show = false): array
    {
        $dados = $this->toArray();
        $ret   = [];

        // ID do Comodato (oculto)
        $mid = new MyCampo('est_comodato', 'cmd_id');
        $mid->valor    = $dados['cmd_id'] ?? '';
        $ret['cmd_id'] = $mid->crOculto();

        // COD ERP
        $cod = new MyCampo('pro_sap_produto', 'pro_codpro');
        $cod ->valor    = (isset($dados['pro_codpro'])) ? $dados['pro_codpro'] : '';
        $cod->largura     = 22;
        $cod->size        = 22;
        $cod->maxLength   = 5;
        $cod->dispForm    = 'col-6';
        $cod->funcChan    = "buscaProdutoComodato(this, '" . site_url('Buscas/buscaProdutoCod') . "')";
        $cod->obrigatorio = true;
        $cod->leitura     = $show;
        $ret['pro_codpro'] = $cod->crInput();

        // Descrição 
        $des = new MyCampo('pro_sap_produto', 'pro_despro');
        $des->valor    = (isset($dados['pro_despro'])) ? $dados['pro_despro'] : '';
        // $des->label    = 'Descrição';
        $des->largura  = 40;
        $des->size     = 40;
        $des->dispForm = 'col-6';
        $des->leitura  = true;
        $ret['pro_despro'] = $des->crInput();

        // código de barras - ID
        $idt = new MyCampo('est_comodato', 'cmd_identificador');
        $idt->valor       = (isset($dados['cmd_identificador'])) ? $dados['cmd_identificador'] : '';
        $idt->place       = 'Escaneie o ID';
        $idt->largura     = 22;
        $idt->size        = 22;
        $idt->maxLength   = 15;
        $idt->obrigatorio = true;
        $idt->leitura     = $show;
        $ret['cmd_identificador'] = $idt->crInput();

        return $ret;
    }
}