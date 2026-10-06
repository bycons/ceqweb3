<?php
namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Entities\Estoque\EntInspecao;
use App\Libraries\MyCampo;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class Inspecao extends BaseController
{
    public $data      = [];
    public $permissao = '';
    public $requisicao;
    public $requisicaoproduto;
    public $requisicaoate;
    public $classes;
    public $produtos;
    public $lote;
    public $busca;
    public $deposito;
    public $bt_envia;

    /**
     * Construtor da Classe
     * construct
     */
    public function __construct()
    {
        $this->data              = session()->getFlashdata('dados_tela');
        $this->permissao         = $this->data['permissao'];
        $this->requisicao        = new EstoquRequisicaoModel();
        $this->requisicaoproduto = new EstoquRequisicaoProdutoModel();
        $this->requisicaoate     = new EstoquRequisicaoProdutoAtendimentoModel();
        $this->classes           = new ProdutClasseModel();
        $this->produtos          = new ProdutProdutoModel();
        $this->busca             = new BuscasSapiens();
        $this->deposito          = new EstoquDepositoModel();
        $this->lote              = new ProdutLoteModel();

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
        $this->data['colunas']   = montaColunasLista($this->data, 'req_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }
    /**
     * Listagem
     * lista
     *
     * @return void
     */
    public function lista()
    {
        // if (!$requis = cache('requis')) {
        $campos       = montaColunasCampos($this->data, 'req_id');
        $dados_requis = $this->requisicao->getRequisicaoLista(false, [25]);
        $dados_requis = filtrarRequisicoesPorPerfil($dados_requis);
        // debug($dados_requis, true);
        if ($dados_requis) {
            $req_ids_assoc = array_map(fn($r) => $r->req_id, $dados_requis);

            $log      = buscaLogTabela('est_requisicao', $req_ids_assoc);
            $base_url = base_url($this->data['controler']);
            foreach ($dados_requis as $req) {
                if ($req->req_id) {
                    $req->usu_nome = $log[$req->req_id]['usua_alterou'] ?? '';

                    $url_con = $base_url . '/inspprodutos/' . $req->req_id;

                    $req->acao_person = [
                        "<button type='button' class='btn btn-outline-success btn-sm border-0 mx-0 fs-0'
                            title='Conferência'
                            onclick='redireciona(\"$url_con\")'>
                            <i class='fas fa-check'></i>
                        </button>",
                    ];
                }
            }
        }
        // debug($dados_requis, true);
        $this->data['edicao'] = false;
        $requis               = [
            'data' => montaListaColunasEnt($this->data, 'req_id', $dados_requis, $campos[1]),
        ];
        cache()->save('requis', $requis, 60000);
        // }

        echo json_encode($requis);
    }

    public function show($id)
    {
        return redirect()->to('/Requisicao/show/' . $id);
    }

    public function inspprodutos($id, $show = true)
    {
        // Busca a requisição no banco e pega o primeiro registro retornado
        $requisicao = $this->requisicao->getRequisicao($id);

        if (! $requisicao) {
            return redirectWithError($this->data['controler'], 41);
            // session()->setFlashdata('erromsg', 'Requisição não encontrada.');
            // return redirect()->to(site_url($this->data['controler']));
        }
        $requisicaoRaw = $requisicao[0];

        $requisicao = new EntInspecao((array) $requisicaoRaw, $show, 'conf');

        // OBJETOS
        $secao  = new \stdClass();
        $campos = new \stdClass();

        // Montar campos como no add()
        $fields = $requisicao->campos;

        // Define a primeira seção da tela
        $secao->{0}    = 'Dados Gerais';
        $campos->{0}   = [];
        $campos->{0}[] = $fields['req_id'];
        $campos->{0}[] = $fields['req_numero'];
        $campos->{0}[] = $fields['req_data'];
        $campos->{0}[] = $fields['req_dataentrega'];
        $campos->{0}[] = $fields['tmo_id'];
        $campos->{0}[] = "<div class='col-6'>.</div>";

        $produtos = $this->requisicao->getRequisicaoProdutos($id);

        $filtrado = array_filter($produtos, function ($item) {
            return ($item->rpa_atendida + $item->rpa_cancelada) == $item->rep_quantia;
        });
        $produtos = $filtrado;
        $pro_ids  = array_unique(array_map(fn($p) => $p->pro_id, $produtos));

        $resultado = $produtos;

        // Loop de tratamento visual e funcional de cada produto
        for ($p = 0; $p < count($resultado); $p++) {
            $prod = $resultado[$p];

            // Inicializa flags padrão de conferência
            $prod->pre_cbmisturador ??= 'N';
            $prod->pre_undmisturador ??= 'N';
            $prod->pre_cbfabricante ??= 'N';
            $prod->pre_undfabricante ??= 'N';
            $prod->pre_cblote ??= 'N';
            $prod->pre_undlote ??= 'N';

            $prod->bt_insvis = '';
            if ($prod->cla_insvis == 'S' && $prod->cla_insvisconf == 'S') {
                $bt_insvis           = new MyCampo();
                $bt_insvis->id       = $bt_insvis->nome       = 'bt_insvis';
                $bt_insvis->classep  = 'btn btn-outline-black btn-sm border-0 mx-0 fs-0';
                $bt_insvis->i_cone   = "<i class='fa-solid fa-magnifying-glass-arrow-right'></i>";
                $bt_insvis->label    = '';
                $bt_insvis->place    = 'Inspeção';
                $bt_insvis->funcChan = "gerarInspecao({$this->data['tel_id']}, {$prod->rep_id})";
                $prod->bt_insvis     = $bt_insvis->crBotao();
            }
            $fieldsAten = $requisicao->defCamposProdutoAte($prod);
            $fieldsConf = $requisicao->defCamposProdutoConf($prod);

            $prod->rpa_data             = data_br($prod->rpa_data);
            $prod->rpa_data_conferencia = data_br($prod->rpa_data_conferencia);
            $prod->saldo                = (int) $prod->rpa_atendida - (int) $prod->rpa_conferida;
        }
        // debug($resultado, true);
        $campos->{0}[] = view('partials/pw_produtos_inspecao', ['produtos' => array_map(fn($p) => (array) $p, $resultado)]);

        // Define dados principais da tela
        // $this->data['title']       = ' Requisição No. ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['metodo']      = 'index';
        $this->data['desc_edicao'] = ' Requisição No. ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_requisicao';
        $this->data['secoes']      = (array) $secao;
        $this->data['campos']      = array_map(function ($linha) {
            return is_array($linha)
                ? array_map(fn($v) => is_object($v) ? (array) $v : $v, $linha)
                : $linha;
        }, (array) $campos);

        // Define foco inicial no campo de código de barras
        $this->data['script'] = "<SCRIPT>jQuery('#lot_codbar').focus();</SCRIPT>";
        echo view('vw_edicao', $this->data);
    }

    /**
     * Exclusão
     * delete
     *
     * @param mixed $id
     * @return void
     */
    public function delete($id)
    {
        // TODO implementar

    }

    public function inspeciona($id, $show = true)
    {
        // Busca a requisição no banco e pega o primeiro registro retornado
        $requisicao = $this->requisicao->getRequisicao($id);

        if (! $requisicao) {
            return redirectWithError($this->data['controler'], 41);
            // session()->setFlashdata('erromsg', 'Requisição não encontrada.');
            // return redirect()->to(site_url($this->data['controler']));
        }
        $requisicaoRaw = $requisicao[0];

        $requisicao = new EntInspecao((array) $requisicaoRaw, $show, 'conf');

        // OBJETOS
        $secao  = new \stdClass();
        $campos = new \stdClass();

        // Montar campos como no add()
        $fields = $requisicao->campos;

        // Define a primeira seção da tela
        $secao->{0}    = 'Dados Gerais';
        $campos->{0}   = [];
        $campos->{0}[] = $fields['req_id'];
        $campos->{0}[] = $fields['req_numero'];
        $campos->{0}[] = $fields['req_data'];
        $campos->{0}[] = $fields['req_dataentrega'];
        $campos->{0}[] = $fields['tmo_id'];
        $campos->{0}[] = "<div class='col-6'>.</div>";

        $this->data['metodo']      = 'index';
        $this->data['desc_edicao'] = ' Requisição No. ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_requisicao';
        $this->data['secoes']      = (array) $secao;
        $this->data['campos']      = array_map(function ($linha) {
            return is_array($linha)
                ? array_map(fn($v) => is_object($v) ? (array) $v : $v, $linha)
                : $linha;
        }, (array) $campos);

        // Define foco inicial no campo de código de barras
        $this->data['script'] = "<SCRIPT>jQuery('#lot_codbar').focus();</SCRIPT>";
        echo view('vw_edicao_modal', $this->data);
    }
    /**
     * Gravação
     * store
     *
     * @return void
     */
    public function store()
    {
        $postado = $this->request->getPost();
        $ret     = [];

        try {
            // AGRUPA DADOS
            $dadosAgrupados = [];
            foreach ($postado as $key => $value) {

                if (preg_match('/^repid_(\d+)$/', $key, $matches)) {

                    $id = $matches[1];

                    $dadosTemp = [
                        'repid'  => $value,
                        'req_id' => $postado['req_id'] ?? null,
                        'tmo_id' => $postado['tmo_id'] ?? null,
                    ];
                    foreach ($postado as $campo => $val) {
                        if (str_ends_with($campo, "_$id") && $campo !== "repid_$id") {
                            $nomeCampo             = substr($campo, 0, -strlen("_$id"));
                            $dadosTemp[$nomeCampo] = $val;
                        }
                    }
                    $dadosTemp['rpa_conferida'] = (int) ($dadosTemp['rpa_conferida'] ?? 0);

                    if ($dadosTemp['rpa_conferida'] > 0) {
                        $dadosAgrupados[$id] = $dadosTemp;
                    }
                }
            }

            // NADA PARA SALVAR
            if (count($dadosAgrupados) === 0) {
                throw new \Exception('Nenhum item pendente de conferência.');
            }

            // TRANSAÇÃO
            $this->requisicaoate->transStart();

            foreach ($dadosAgrupados as $val) {
                $sql = [
                    'rpa_conferida'        => $val['rpa_conferida'],
                    'rpa_data_conferencia' => date('Y-m-d H:i:s'),
                ];
                if (! $this->requisicaoate->update($val['repid'], $sql)) {
                    throw new \Exception('Erro ao gravar a conferência.');
                }
            }

            $this->requisicao->transStart();

            if (! $this->requisicao->update($postado['req_id'], ['stt_id' => 5])) {
                throw new \Exception('Erro ao atualizar status da requisição.');
            }
            $this->requisicaoate->transCommit();
            $this->requisicao->transCommit();

            // SUCESSO (IGUAL AO PADRÃO)
            $ret['erro'] = false;
            $ret['msg']  = 'Conferência gravada com sucesso!';
            session()->setFlashdata('msg', $ret['msg']);
            $ret['url'] = site_url($this->data['controler']);
        } catch (\Exception $e) {
            $this->requisicaoate->transRollback();
            $this->requisicao->transRollback();

            $ret['erro'] = true;
            $ret['msg']  = 'Erro ao gravar a conferência:<br><br>' . $e->getMessage();
        }

        return $this->response->setJSON($ret);
    }
}
