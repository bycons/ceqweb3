<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Entities\Estoque\EntRequisicao;
use App\Libraries\MyCampo;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class EtqMisturador extends BaseController
{
    public $data      = [];
    public $permissao = '';
    public $requisicao;
    public $reqproduto;
    public $reqprodutoate;
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
        $this->data          = session()->getFlashdata('dados_tela');
        $this->permissao     = $this->data['permissao'];
        $this->requisicao    = new EstoquRequisicaoModel();
        $this->reqproduto    = new EstoquRequisicaoProdutoModel();
        $this->reqprodutoate = new EstoquRequisicaoProdutoAtendimentoModel();
        $this->classes       = new ProdutClasseModel();
        $this->produtos      = new ProdutProdutoModel();
        $this->busca         = new BuscasSapiens();
        $this->deposito      = new EstoquDepositoModel();
        $this->lote          = new ProdutLoteModel();

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
        $dados_requis = $this->requisicao->getRequisicaoLista(false, [24, 25]);
        // Filtra por perfil
        $dados_requis = filtrarPorPerfil($dados_requis);
        // Filtra por perfil do tipo de movimentação
        $dados_requis = filtrarPorPerfil($dados_requis, null, 'prf_id_tmo');

        $req_ids_assoc = array_column($dados_requis, 'req_id');
        $log           = buscaLogTabela('est_requisicao', $req_ids_assoc);

        $base_url = base_url($this->data['controler']);
        foreach ($dados_requis as &$req) {
            // Verificar se o log já está disponível para esse req_id
            if ($req->req_id) {
                $req->usu_nome = $log[$req->req_id]['usua_alterou'] ?? '';
                // Concatenar o URL de forma mais eficiente
                // $url_eti = $base_url .'/EtqProduto/' . $req['req_id'];
                $url_eti = $base_url . '/Etiqueta/' . $req->req_id;
                // Gerar a ação do botão
                $req->acao_person = [
                    "<button type='button' class='btn btn-outline-warning btn-sm border-0 mx-0 fs-0'
                    data-mdb-toggle='tooltip' data-mdb-placement='top'
                    title='Etiquetas do Misturador' onclick='redireciona(\"$url_eti\")'>
                    <i class='fas fa-tags fa-rotate-90'></i></button>",
                ];
            }
        }
        // debug($dados_requis, true);
        $this->data['edicao']   = false;
        $this->data['consulta'] = false;
        $requis                 = [
            'data' => montaListaColunasEnt($this->data, 'req_id', $dados_requis, $campos[1]),
        ];
        cache()->save('requis', $requis, 60000);
        // }

        echo json_encode($requis);
    }
    /**
     * Impressão de Etiquetas de Produtos
     * EtqProduto
     *
     * @param mixed $id
     * @return void
     */
    public function Etiqueta($id)
    {
        $requisicao = $this->requisicao->getRequisicao($id);

        if (! $requisicao) {
            return redirectWithError($this->data['controler'], 41);
            // session()->setFlashdata('erromsg', 'Requisição não encontrada.');
            // return redirect()->to(site_url($this->data['controler']));
        }
        $requisicao = $requisicao[0];
        $ent        = new EntRequisicao((array) $requisicao, true);

        $fields                       = $ent->campos;
        $secao[0]                     = 'Dados Gerais';
        $campos[0][0]                 = $fields['req_id'];
        $campos[0][count($campos[0])] = $fields['req_data'];
        $campos[0][count($campos[0])] = $fields['req_dataentrega'];
        $campos[0][count($campos[0])] = $fields['tmo_id'];
        $campos[0][count($campos[0])] = "<div class='col-6'>.</div>";
        // $campos[0][count($campos[0])] = $fields['lot_codbar'];

        $produtosreq = $this->requisicao->getRequisicaoProdutos($id);
        // debug($produtosreq, true);

        $colunas = [
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote',
            'Qtde.Requerida',
            'Qtde.Imprimir',
            'Coloração Etiqueta',
            'Imprimir',
        ];
        $alinha = [
            'center',
            'start',
            'start',
            'start',
            'end',
            'center d-flex justify-content-center',
            'center',
            'center',
        ];
        $produtos    = [];
        $produtos[0] = $id;
        if (count($produtosreq) > 0) {
            for ($p = 0; $p < count($produtosreq); $p++) {
                $prod = $produtosreq[$p];
                if ($prod->prc_etiq_misturador != 'S') {
                    continue; // Pula produtos que não precisam de etiqueta de misturador
                }

                $rep_id   = $prod->rep_id;
                $qtia     = $prod->rep_quantia;
                $url_ati  = base_url($this->data['controler'] . '/GeraEtiqueta/' . $rep_id);
                $imprimir =
                    "<button type='button' class='btn btn-outline-dark btn-sm border-0 mx-0 fs-0' data-mdb-toggle='tooltip'
                    data-mdb-placement='top' title='Imprimir Etiqueta' onclick='geraEiquetaProd(this, \"" . $url_ati . "\",\"rep_quantia_$p\")'><i class='fas fa-print'></i></button>";
                $qtd              = new MyCampo();
                $qtd->valor       = $prod->rep_quantia;
                $qtd->tipo        = 'number';
                $qtd->label       = '';
                $qtd->id          = $qtd->nome          = "rep_quantia_" . $p;
                $qtd->dispForm    = 'col-6';
                $qtd->minimo      = 1;
                $qtd->step        = 1;
                $qtd->largura     = 20;
                $qtd->size        = 3;
                $qtd->maximo      = $prod->rep_quantia;
                $qtd->obrigatorio = true;
                $qtd->classep     = 'semmb';
                $quantia          = $qtd->crInput();

                $item                       = [];
                $item[0]                    = $rep_id;
                $item[count($item)]         = $prod->pro_codpro;
                $item[count($item)]         = $prod->pro_despro;
                $item[count($item)]         = $prod->fab_apeFab;
                $item[count($item)]         = $prod->lot_lote;
                $item[count($item)]         = $prod->rep_quantia;
                $item[count($item)]         = $quantia;
                $item[count($item)]         = $prod->etiq_cor;
                $item[count($item)]         = $imprimir;
                $produtos[count($produtos)] = $item;
            }
        }
        // debug($produtos, true);
        $data = [
            'show'     => true,
            'colunas'  => $colunas,
            'alinha'   => $alinha,
            'produtos' => $produtos,
        ];

        $campos[0][count($campos[0])] = view('partials/pw_show_produtos_req', $data); // mesma estrutura do add()

        $this->data['desc_metodo'] = ' '; // ou 'update' se você for criar
        $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['destino']     = ''; // ou 'update' se você for criar
        $this->data['scripts']     = 'my_requisicao';

        echo view('vw_edicao', $this->data);
    }

    // public function GeraEtiqueta($id, $qtia)
    // {
    //     $produtos = $this->requisicao->getRequisicaoRep($id);
    //     // debug($produtos);
    //     // $produtosreq = array_fill(0, $qtia, $produtos[0]);
    //     $produtosreq = array_fill(0, 1, $produtos[0]);
    //     // debug($produtosreq);
    //     $chave = uniqid('etq_');
    //     // debug($chave);
    //     cache()->save($chave, $produtosreq, 300); // 5 minutos

    //     $link = base_url('/CriaEtiquetaZPL/emiteEtiqueta');

    //     $ret['link']  = $link;
    //     $ret['chave'] = $chave;

    //     return json_encode($ret);
    // }

    public function GeraEtiqueta(int $id, int $qtia): string
    {
        $redis = \Config\Services::redis();
        $sessionId = session_id();

        // 🔑 chave única por sessão + produto + quantidade
        $chave = "etq:{$sessionId}:" . md5($id . '_' . $qtia);

        // 🔍 tenta recuperar do Redis
        $cached = $redis->get($chave);

        if (!$cached) {
            // 🔄 busca dados apenas se não existir
            $produtos = $this->requisicao->getRequisicaoRep($id);

            if (empty($produtos)) {
                return json_encode([
                    'erro' => 'Produto não encontrado'
                ]);
            }

            $produtosreq = array_fill(0, $qtia, $produtos[0]);

            // 💾 salva no Redis com TTL de 15 minutos (900 segundos)
            $redis->setex($chave, 900, json_encode($produtosreq));

            // 🧠 opcional: rastrear chaves da sessão
            $redis->sAdd("etq_session:{$sessionId}", $chave);
        }

        return json_encode([
            'link'  => base_url('/CriaEtiquetaZPL/emiteEtiqueta'),
            'chave' => $chave,
        ]);
    }

    /**
     * Gravação
     * store
     *
     * @return void
     */
    public function store() {}
}
