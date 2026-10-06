<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Traits\ForeignKeyUsageChecker;
use App\Models\CommonModel;
use App\Models\Estoqu\EstoquComodatoModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Entities\Estoque\EntEstComodato;

class Comodato extends BaseController
{
    use ForeignKeyUsageChecker;

    public $permissao = '';
    public $comodato;
    public $produto;
    public $common;

    public array $data = [];

    /**
     * construct
     */
    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->comodato  = new EstoquComodatoModel();
        $this->produto   = new ProdutProdutoModel();
        $this->common    = new CommonModel();

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    /**
     * erro
     */
    function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * index
     */
    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'cmd_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }

    /**
     * lista
     *
     * @return void
     */
    public function lista()
    {
        $campos         = montaColunasCampos($this->data, 'cmd_id');
        $dados_comodato = $this->comodato->getComodato();
        $this->data['allconsulta'] = true;
        $comodatos = [
            'data' => montaListaColunasEnt($this->data, 'cmd_id', $dados_comodato, $campos[1]),
        ];

        echo json_encode($comodatos);
    }

    /**
     * add
     *
     * @return void
     */
    public function add()
    {
        $cmd = new EntEstComodato();

        $this->data['secoes']  = ['Dados Gerais'];
        $this->data['campos']  = [[
            $cmd->campos['cmd_id'],
            $cmd->campos['pro_codpro'],
            $cmd->campos['pro_despro'],
            $cmd->campos['cmd_identificador'],
        ]];
        $this->data['destino'] = 'store';

        echo view('vw_edicao', $this->data);
    }

    /**
     * Mostrar Registro
     *
     * @param mixed $id
     * @return void
     */
    public function show($id)
    {
        $this->edit($id, true);
    }

    /**
     * edit
     *
     * @param mixed $id
     * @return void
     */
    public function edit($id, $show = false)
    {
        $dados_cmd = $this->comodato->getComodato($id);

        if (! $dados_cmd) {
            return redirectWithError($this->data['controler'], 41);
        }
        $cmd = new EntEstComodato((array) $dados_cmd[0], $show);

        $this->data['secoes']  = ['Dados Gerais'];
        $this->data['campos']  = [[
            $cmd->campos['cmd_id'],
            $cmd->campos['pro_codpro'],
            $cmd->campos['pro_despro'],
            $cmd->campos['cmd_identificador'],
        ]];
        $this->data['destino']     = 'store';
        // $this->data['desc_edicao'] = $cmd->pro_codpro;
        $this->data['log']         = buscaLog('est_comodato', $id);

        echo view('vw_edicao', $this->data);
    }

    /**
     * store
     *
     * @return void
     */
    public function store()
    {
        $ret     = [];
        $postado = $this->request->getPost();

        $cmd_id = $postado['cmd_id'] ?? '';
        $codpro = strtoupper(trim($postado['pro_codpro'] ?? ''));
        $ident  = trim($postado['cmd_identificador'] ?? '');

        // sem código devolve todos os produtos, por isso o teste de vazio
        $produto = ($codpro !== '') ? $this->produto->getProdutoCod($codpro) : [];
        if (! $produto) {
            echo json_encode([
                'erro' => true,
                'msg'  => 'Código ERP não encontrado no cadastro de Produtos, Verifique!',
            ]);
            return;
        }

        // ID unico somente entre registros ativos
        $exists = $this->common->verificaUnico($this->comodato, 'cmd_identificador', $ident, 'cmd_id', $cmd_id, 'cmd_ativo', 'A');

        if (intval($exists) > 0) {
            $ret['erro'] = true;
            $ret['msg']  = 8;
        } else {
            $sql_cmd = [
                'pro_id'            => $produto[0]->pro_id,
                'cmd_identificador' => $ident,
            ];

            $this->comodato->transBegin();

            try {
                if ($cmd_id == '') {
                    $sql_cmd['cmd_ativo'] = 'A';
                    $sql_cmd['usu_id']    = session()->get('usu_id');  // usuário que cadastrou
                    $gravou = $this->comodato->insert($sql_cmd);
                } else {
                    $gravou = $this->comodato->update($cmd_id, $sql_cmd);
                }

                if (! $gravou) {
                    throw new \Exception(implode('<br>', $this->comodato->errors()));
                }

                $this->comodato->transCommit();
                cache()->clean();
                session()->setFlashdata('msg', 'Comodato gravado com Sucesso!');

                $ret = [
                    'erro' => false,
                    'msg'  => 'Comodato gravado com Sucesso!',
                    'url'  => site_url($this->data['controler']),
                ];
            } catch (\Throwable $e) {
                $this->comodato->transRollback();
                $ret = [
                    'erro' => true,
                    'msg'  => $e->getMessage() ?: 'Não foi possível gravar o Comodato, Verifique!',
                ];
            }
        }

        echo json_encode($ret);
    }

    /**
    * ativinativ
    *
    * @param mixed $id
    */
    public function ativinativ($id, $tipo)
    {
        $ret = [];
        try {
            if ($tipo == 1) {
                $dad_atin = [
                    'cmd_ativo' => 'A'
                ];
                $msg = "Comodato Ativado com Sucesso";
            } else {
                $dad_atin = [
                    'cmd_ativo' => 'I'
                ];
                $msg = "Comodato Inativado com Sucesso";
            }
            $this->comodato->update($id, $dad_atin);
            $ret['erro'] = false;
            session()->setFlashdata('msg', $msg);
            $ret['msg']  = $msg;
            cache()->clean();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        }
        echo json_encode($ret);
    }

    /**
     * delete
     *
     * @param mixed $id
     * @return void
     */
    public function delete($id)
    {
        $ret = [];

        try {
            // bloqueia se houver uso 
            $this->verificarUsoEmRelacionamentos('est_comodato', 'cmd_id', (int) $id);

            $this->comodato->delete($id);
            $ret['erro'] = false;
            $ret['msg']  = 'Comodato Excluído com Sucesso';
            session()->setFlashdata('msg', $ret['msg']);
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 3;
        }

        echo json_encode($ret);
    }

}