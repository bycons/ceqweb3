<?php

namespace App\Controllers\Config;

use App\Controllers\BaseController;
use App\Libraries\Campos;
use App\Models\Config\ConfigDicDadosModel;

class CfgDicionario extends BaseController
{
    public $data = [];
    public $dicionario;
    private $permissao;

    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->dicionario  = new ConfigDicDadosModel();
        $this->permissao = $this->data['permissao'];

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    public function index()
    {
        $this->data['colunas'] = ['Tabela', 'Banco', 'Tabela', 'Registros', 'Descrição',  'Ação'];
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');

        echo view('vw_lista', $this->data);
    }

    public function lista()
    {
        $result = [];
        $dados_tab = $this->dicionario->getTabelas();
        // debug($dados_tab, false);
        for ($p = 0; $p < sizeof($dados_tab); $p++) {
            $tabela = $dados_tab[$p];
            $detalhes = '';
            if (strpbrk($this->permissao, 'C')) {
                $detalhes = anchor(
                    $this->data['controler'] . "/show/" . $tabela['table_name'],
                    '<i class="far fa-eye"></i>',
                    [
                        'class' => 'btn btn-outline-info btn-sm mx-1',
                        'data-mdb-toggle' => 'tooltip',
                        'data-mdb-placement' => 'top',
                        'title' => 'Detalhes da Tabela',
                    ]
                );
            }
            // $dados_tab[$p]['table_name'] = anchor($this->data['controler'] . "/show/" . $tabela['table_name'],$tabela['table_name']);
            $dados_tab[$p]['acao'] = $detalhes;
            $tabela = $dados_tab[$p];
            $result[] = [
                $tabela['table_name'],
                $tabela['table_schema'],
                $tabela['table_name'],
                $tabela['table_rows'],
                $tabela['table_comment'],
                $tabela['acao'],
            ];
        }
        $classs = [
            'data' => $result,
        ];

        echo json_encode($classs);
    }

    public function add()
    {
        // Use os nomes reais das suas tabelas de requisição de estoque:
        $contexto = $this->dicionario->getSchemaContext(['est_requisicao', 'est_requisicao_produto', 'pro_sap_produto', 'pro_sap_lote']);

        return $this->response->setContentType('text/plain')
            ->setBody($contexto);
    }


    public function show($table)
    {
        $dados_tabela = $this->dicionario->getTabelaSearch($table)[0];
        $this->def_campos($dados_tabela);

        $secao[0] = 'Dados Gerais';
        $campos[0][0] = $this->tab_id;
        $campos[0][1] = $this->tab_nome;
        $campos[0][2] = $this->tab_desc;
        $campos[0][3] = $this->tab_regi;

        $this->def_campos_campos($table);

        $secao[1] = 'Campos';
        $campos[1][0] = $this->tab_camp;

        $secao[2] = 'Relacionamentos';
        $campos[2][0] = $this->tab_trel;
        // debug($campos);
        // 
        $this->data['desc_edicao'] = $dados_tabela['table_name'];
        $this->data['secoes'] = $secao;
        $this->data['campos'] = $campos;
        $this->data['destino'] = 'store';

        echo view('vw_edicao', $this->data);
    }

    public function def_campos($dados = false)
    {
        $id = new Campos();
        $id->objeto = 'oculto';
        $id->nome = 'table_name';
        $id->id = 'table_name';
        $id->valor = isset($dados['table_name']) ? $dados['table_name'] : '';
        $this->tab_id = $id->create();

        $nome = new Campos();
        $nome->objeto = 'input';
        $nome->tipo = 'text';
        $nome->nome = 'table_name';
        $nome->id = 'table_name';
        $nome->label = 'Nome da Tabela';
        $nome->place = 'Nome da Tabela';
        $nome->leitura = true;
        $nome->size = 30;
        $nome->tamanho = 30;
        $nome->valor = isset($dados['table_name'])
            ? $dados['table_name']
            : '';
        $this->tab_nome = $nome->create();

        $desc = new Campos();
        $desc->objeto = 'texto';
        $desc->nome = 'table_comment';
        $desc->id = 'table_comment';
        $desc->label = 'Descrição';
        $desc->place = 'Descrição';
        $desc->leitura = true;
        $desc->size     = 70;
        $desc->max_size = 3;
        $desc->tamanho  = 80;
        $desc->valor = isset($dados['table_comment'])
            ? $dados['table_comment']
            : '';
        $this->tab_desc = $desc->create();

        $regi = new Campos();
        $regi->objeto = 'input';
        $regi->tipo = 'inteiro';
        $regi->nome = 'table_rows';
        $regi->id = 'table_rows';
        $regi->label = 'Registros';
        $regi->place = 'Registros';
        $regi->leitura = true;
        $regi->size = 10;
        $regi->tamanho = 12;
        $regi->valor = isset($dados['table_rows'])
            ? $dados['table_rows']
            : '';
        $this->tab_regi = $regi->create();
    }

    public function def_campos_campos($tabela)
    {
        $campos = $this->dicionario->getCampos($tabela);
        $relac = $this->dicionario->getRelacionamentos($tabela);
        // debug($relac, false);
        for ($r = 0; $r < count($relac['relacionamentos']); $r++) {
            $cprel = [];
            $table = $relac['relacionamentos'][$r]['REFERENCED_TABLE_NAME'];
            $cprel = $this->dicionario->getCampos($table);
            // debug($cprel, false);
            for ($c = 0; $c < count($cprel); $c++) {
                array_push($campos, $cprel[$c]);
            }
        }
        // debug($campos, true);

        $camp = new Campos();
        $camp->objeto = 'show';
        $camp->id = 'dic_campos';
        $camp->label = 'Campos';
        $camp->size = 'auto';
        $camp->tamanho = 'auto';
        $camp->valor = '';
        $camp->valor = campos_tabela($campos);
        $this->tab_camp = $camp->create();


        $trel = new Campos();
        $trel->objeto = 'show';
        $trel->id = 'dic_relac';
        $trel->label = 'Tabelas Relacionadas';
        $trel->size = 'auto';
        $trel->tamanho = 'auto';
        $trel->valor = '';
        $trel->valor = relacion_tabela($relac['relacionamentos']);
        $this->tab_trel = $trel->create();
    }
}
