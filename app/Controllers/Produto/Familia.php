<?php

namespace App\Controllers\Produto;

use App\Controllers\BaseController;
use App\Controllers\Ws\WsCeqweb;
use App\Models\Produt\ProdutFamiliaModel;

class Familia extends BaseController
{
    public $data = [];
    public $permissao = '';
    public $common;
    public $familias;

    /**
     * Construtor da Tela
     * construct
     */
    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->familias = new ProdutFamiliaModel();

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    /**
     * Erro de Acesso
     * erro
     */
    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * Tela de Abertura
     * index
     */
    public function index()
    {
        $this->data['temacao']   = false; // não tem ação
        $this->data['colunas'] = montaColunasLista($this->data, 'fam_codFam,');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }
    /**
     * Listagem
     * lista
     */
    public function lista()
    {
        // $integ = new WsCeqweb();
        // $integ->integraFamilia();

        $campos = montaColunasCampos($this->data, 'fam_codFam');
        $dados_tela = $this->familias->getFamilia();

        $this->data['edicao'] = false;
        $this->data['exclusao'] = false;
        $this->data['temacao'] = false; // não tem ação
        $familias = [
            'data' => montaListaColunasEnt($this->data, 'fam_codFam', $dados_tela, $campos[1]),
        ];
        // cache()->save('familias', $familias, 60000);
        echo json_encode($familias);
    }

    // public function show($id){
    //     $integ = new WsCeqweb();
    //     $integ->integraFamilia();

    // 	$dados_familias = $this->familias->getFamilia($id);
    //     $fields = $this->familias->defCampos($dados_familias[0], true);

    //     $secao[0] = 'Dados Gerais'; 
    //     $campos[0][0] = $fields['fam_codFam']; 
    //     $campos[0][1] = $fields['fam_desFam'];
    //     $campos[0][2] = $fields['fam_codDescricao'];
    //     $campos[0][3] = $fields['ori_codOri'];

    // 	$this->data['secoes']     = $secao;
    //     $this->data['campos']     = $campos;
    //     $this->data['destino']    = 'store';
    //     // BUSCAR DADOS DO LOG
    //     $this->data['log'] = buscaLog('pro_sap_familia', $id);

    //     echo view('vw_edicao', $this->data);
    // }

}
