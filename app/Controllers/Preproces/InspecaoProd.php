<?php

namespace App\Controllers\Preproces;

use App\Controllers\BaseController;
use App\Controllers\Estoque\Requisicao;
use App\Controllers\Notifica;
use App\Entities\Ocorrencia\EntOcoOcorrencia;
use App\Entities\Preproces\EntInspecaoProd;
use App\Libraries\MyCampo;
use App\Models\CommonModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class InspecaoProd extends BaseController
{
    public $data      = [];
    public $permissao = '';
    public $requisicao;
    public $requisicaoproduto;
    public $requisicaoate;
    public $classes;
    public $produtos;
    public $ocorrencia;
    public $common;
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
        $this->requisicaoate = new EstoquRequisicaoProdutoAtendimentoModel();
        $this->classes       = new ProdutClasseModel();
        $this->produtos      = new ProdutProdutoModel();
        $this->ocorrencia    = new OcorreOcorrenciaModel();
        $this->common        = new CommonModel();
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
        $dados_requis = $this->requisicao->getRequisicaoListaInsp(false, [21, 24, 25, 26]);
        // Filtra por perfil
        $dados_requis = filtrarPorPerfil($dados_requis);
        // Filtra por perfil do tipo de movimentação
        $dados_requis = filtrarPorPerfil($dados_requis, null, 'prf_id_tmo');
        // debug($dados_requis, true);
        if ($dados_requis) {
            $req_ids_assoc = array_map(fn($r) => $r->req_id, $dados_requis);

            $log      = buscaLogTabelaFirst('est_requisicao', $req_ids_assoc);
            $base_url = base_url($this->data['controler']);
            foreach ($dados_requis as $req) {
                if ($req->req_id) {
                    $req->usu_nome = $log[$req->req_id]['usua_alterou'] ?? '';

                    $url_ins = $base_url . '/edit/' . $req->req_id;
                    // Gerar a ação do botão
                    $bt_ins           = new MyCampo();
                    $bt_ins->id       = $bt_ins->nome       = 'bt_inspecao';
                    $bt_ins->classep  = 'btn btn-outline-success btn-sm border-0 mx-0 fs-0';
                    $bt_ins->i_cone   = "<i class='fab fa-searchengin'></i>";
                    $bt_ins->label    = '';
                    $bt_ins->place    = 'Inpeção de Produtos';
                    $bt_ins->funcChan = "redireciona('{$url_ins}')";
                    $btins            = $bt_ins->crBotao();

                    $req->acao_person = [
                        $btins,
                    ];
                }
            }
        }
        // debug($dados_requis, true);
        $this->data['edicao']      = false;
        $this->data['allconsulta'] = true;
        $requis                    = [
            'data' => montaListaColunasEnt($this->data, 'req_id', $dados_requis, $campos[1]),
        ];
        // cache()->save('requis', $requis, 60000);
        // }

        echo json_encode($requis);
    }

    public function show($id)
    {
        return redirect()->to('/Requisicao/show/' . $id);
    }

    public function edit($id, $show = true)
    {
        $requisicao = $this->requisicao->getRequisicao($id);

        if (! $requisicao) {
            return redirectWithError($this->data['controler'], 41);
        }
        $requisicao           = $requisicao[0];
        $log                  = buscaLog('est_requisicao', $id);
        $requisicao->usu_nome = $log['usua_alterou'] ?? '';
        // debug($requisicao, true);
        $contRequis = new Requisicao();
        $secao[1]   = 'Dados Gerais';
        $campos[1]  = $contRequis->showCabecalho($requisicao);

        $entRequisicao = new EntInspecaoProd((array) $requisicao, $show, 'conf');

        $produtos = $this->requisicao->getRequisicaoProdutos($id);
        // debug($produtos);
        $filtrado = array_filter($produtos, function ($item) {
            return ($item->rpa_atendida + $item->rpa_cancelada) == $item->rep_quantia && $item->rpa_conferida > 0 && $item->rpa_data_inspecao == null;
        });
        // debug($filtrado, true);
        // $produtos = $filtrado;

        $resultado = array_values($filtrado);

        $verinsp = '';
        // Loop de tratamento visual e funcional de cada produto
        foreach ($resultado as $key => $value) {
            $prod = $resultado[$key];

            // Inicializa flags padrão de conferência
            $prod->pre_cbmisturador ??= 'N';
            $prod->pre_undmisturador ??= 'N';
            $prod->pre_cbfabricante ??= 'N';
            $prod->pre_undfabricante ??= 'N';
            $prod->pre_cblote ??= 'N';
            $prod->pre_undlote ??= 'N';

            $prod->bt_insvis = '';
            $prod->bt_ok     = '';
            if ($prod->cla_insvis == 'S' || $prod->cla_insvisconf == 'S') {
                $badge = "<div class='m-0' style='display:inline-block; width:50%;max-height: 1rem;'><span id='badgeinsp_{$prod->rep_id}' class='badgeinsp badge rounded-pill bg-warning p-1 fs-7'></span>";

                $bt_insvis           = new MyCampo();
                $bt_insvis->id       = $bt_insvis->nome       = "bt_insvis_$prod->rep_id";
                $bt_insvis->classep  = 'btn btn-outline-warning btn-sm border-0 m-0 fs-0 float-start';
                $bt_insvis->i_cone   = "<i class='fab fa-searchengin'></i>";
                $bt_insvis->label    = '';
                $bt_insvis->place    = 'Inspeção';
                $bt_insvis->funcChan = "gerarInspecao({$this->data['tel_id']}, {$prod->rep_id})";
                $prod->bt_insvis     = $badge . $bt_insvis->crBotao() . "</div>";

                $bt_ok              = new MyCampo();
                $bt_ok->id          = $bt_ok->nome          = "btok_$prod->rep_id";
                $bt_ok->dispForm    = 'float-end m-0';
                $bt_ok->classep     = 'semmb';
                $bt_ok->label       = '';
                $bt_ok->valor       = 1;
                $bt_ok->selecionado = '';
                $bt_ok->place       = 'Inspeção OK';
                $bt_ok->funcChan    = "mostraOcultaCampo('btok_$prod->rep_id', '', 'bt_insvis_$prod->rep_id');";
                $prod->bt_ok        = $bt_ok->crCheckbox();
            }
            $prod->rpa_data             = data_br($prod->rpa_data);
            $prod->rpa_data_conferencia = data_br($prod->rpa_data_conferencia);
            $prod->rpa_aprovada         = (int) $prod->rpa_conferida;
            $prod->saldo                = (int) $prod->rpa_atendida - (int) $prod->rpa_conferida;

            $filtro = [
                'pro_id'   => $prod->pro_id,
                'lot_lote' => $prod->lot_lote,
            ];
            $inspecoes = $this->common->getRegFiltro('dbEstoque', 'est_requisicao_produto_ocorrencia', ['rpo_id', 'rpo_qtd'], $filtro);
            if ($inspecoes) {
                $insp = 0;
                foreach ($inspecoes as $key => $value) {
                    $this->excluiInspecao($value['rpo_id']);
                }
            }
            // $verinsp .= "verificaInspecao(" . $prod->rep_id . "," . $prod->req_id . "," . $prod->pro_id . ",'" . $prod->lot_lote . "');";
        }
        // debug($resultado, true);
        $secao[0]    = 'Produtos';
        $campos[0][] = view('partials/pw_produtos_inspecao', ['produtos' => array_map(fn($p) => (array) $p, $resultado), 'maxHeig' => '65vh']);

        // Define dados principais da tela
        $this->data['desc_metodo'] = '';
        $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT) . ' ' . fmtEtiquetaCor($requisicao->stt_cor, $requisicao->stt_nome, 1);
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_requisicao';
        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;

        // Define foco inicial no campo de código de barras
        echo view('vw_edicao', $this->data);
    }

    public function verificainspecao()
    {
        $ret['insp'] = 0;
        $requisicao  = $_REQUEST['requisicao'];
        $produto     = $_REQUEST['produto'];
        $lote        = $_REQUEST['lote'];
        $filtro      = [
            'pro_id'   => $produto,
            'lot_lote' => $lote,
        ];
        $inspecoes = $this->common->getRegFiltro('dbEstoque', 'est_requisicao_produto_ocorrencia', ['rpo_id', 'rpo_qtd'], $filtro);
        if ($inspecoes) {
            $insp = 0;
            foreach ($inspecoes as $key => $value) {
                $insp += $value['rpo_qtd'];
            }
            $ret['insp'] = $insp;
        }
        echo json_encode($ret);
    }

    public function excluiInspecao($id)
    {

        $filtro = [
            'rpo_id' => $id,
        ];
        $inspecao = $this->common->getRegFiltro('dbEstoque', 'vw_est_requisicao_produto_ocorrencia_relac', ['rep_id', 'req_id', 'pro_id', 'lot_lote'], $filtro);
        // debug($inspecao, true);
        $msg[0] = $inspecao[0]['rep_id'];
        $msg[1] = $inspecao[0]['req_id'];
        $msg[2] = $inspecao[0]['pro_id'];
        $msg[3] = $inspecao[0]['lot_lote'];

        $exclui      = $this->common->deleteReg('dbEstoque', 'est_requisicao_produto_ocorrencia', "rpo_id = $id");
        $ret['erro'] = false;
        $ret['msg']  = '';

        envia_msg_ws($this->data['controler'], $msg, 'NovaInspecao', session()->get('usu_id'), 1);
        echo json_encode($ret);
    }
    /**
     * Atenndimento
     * atende
     *
     * @param mixed $id
     * @return void
     */
    public function inspeciona(string $controler = 'Inspeção')
    {
        $request = service('request');

        // Lê e decodifica o JSON recebido
        $json = $request->getJSON(true); // true = como array associativo
        // Agora você pode acessar normalmente:
        $proId   = $json['pro_id'] ?? null;
        $lotlote = $json['lot_lote'] ?? null;
        $reqId   = $json['req_id'] ?? null;
        $telId   = $json['tel_id'] ?? null;
        $indice  = $json['indice'] ?? null;
        $qtde    = $json['qtdade'] ?? 0;

        $config['Leitura']  = true;
        $config['Label']    = "Tela";
        $config['DispForm'] = "col-6";

        $tel_id = criaSelectRelativo(
            'cfg_tela',
            'tel_id',
            'tel_nome',
            $telId,
            1,
            'oco_ocorrencia',
            ['tel_id' => $telId],
            $config
        );
        $modProd   = new ProdutProdutoModel();
        $dadosProd = $modProd->getProduto($proId)[0];
        session()->set('cla_id', $dadosProd->cla_id);
        session()->set('tel_id', $telId);

        $config['Label']   = "Tipo de Ocorrência";
        $config['Leitura'] = false;
        $filtros           = [
            'tel_id' => [$telId],
            'cla_id' => [$dadosProd->cla_id],
        ];
        $toc_oc = criaSelectRelativo(
            'vw_oco_tpo_ocorrencia_relac',
            'tpo_id',
            'tpo_nome',
            null,
            1,
            'oco_ocorrencia',
            $filtros,
            $config
        );

        $config['Label']    = "Subtipo de Ocorrência";
        $config['Leitura']  = false;
        $config['Pai']      = "tpo_id";
        $config['Urlbusca'] = base_url('Buscas/buscaSubtipoPorTipo');
        $config['FunChan']  = "buscaLoteProduto('lot_lote','" . base_url('/buscas/buscaProdutoporLote') . "')";

        $filtros = [];
        // debug($filtros, true);
        $mod_oc = criaSelectRelativo(
            'vw_oco_subt_ocorrencia_relac',
            'sut_id',
            'sut_nome',
            null,
            2,
            'oco_ocorrencia',
            $filtros,
            $config
        );

        $ind        = new MyCampo();
        $ind->valor = $indice;
        $ind->id    = $ind->nome    = 'rep_id';
        $rep_id     = $ind->crOculto();

        $req        = new MyCampo();
        $req->valor = $reqId;
        $req->id    = $req->nome    = 'req_id';
        $req_id     = $req->crOculto();

        $desc        = new MyCampo('oco_ocorrencia', 'oco_descricao');
        $desc->valor = 'Não Conformidade na Inspeção';
        $descreva    = $desc->crOculto();

        $qtd              = new MyCampo('oco_ocorrencia', 'oco_qtd');
        $qtd->valor       = $dados['oco_qtd'] ?? 0;
        $qtd->dispForm    = 'col-6';
        $qtd->minimo      = 1;
        $qtd->largura     = 10;
        $qtd->size        = 4;
        $qtd->maximo      = $qtde;
        $qtd->obrigatorio = true;
        $quantia          = $qtd->crInput();

        // Instancia a entity
        $dados['lot_lote'] = $lotlote;
        $dados['req_id']   = $reqId;
        $oco               = new EntOcoOcorrencia($dados, true);

        $ocorretxt = '';
        $filtro    = [
            'req_id'   => $reqId,
            'pro_id'   => $proId,
            'lot_lote' => $lotlote,
        ];
        $jaregistradas = $this->common->getRegFiltro('dbEstoque', 'vw_est_requisicao_produto_ocorrencia_relac', ['rpo_id', 'oco_data', 'oco_qtd', 'sut_nome'], $filtro);

        // $jaregistradas = $this->ocorrencia->getOcorrenciaReqProd($reqId, $proId, $lotlote);
        if ($jaregistradas) {
            $ocorretxt = "<b>Inspeções já Registradas</b><br>";
            // debug($jaregistradas, true);
            $base_url = base_url($this->data['controler']);
            foreach ($jaregistradas as $key => $value) {

                $url_del          = $base_url . '/excluiInspecao/' . $value['rpo_id'];
                $bt_del           = new MyCampo();
                $bt_del->id       = $bt_del->nome       = "btexclui_$key";
                $bt_del->classep  = 'btn btn-outline-danger btn-sm border-0 mx-0 fs-0';
                $bt_del->i_cone   = "<i class='far fa-trash-alt'></i>";
                $bt_del->label    = '';
                $bt_del->place    = 'Excluir Inspeção';
                $bt_del->funcChan = "excluiInspecao('{$url_del}')";
                $btdel            = $bt_del->crBotao();
                $ocorretxt        .= $btdel . ' - ' . data_br($value['oco_data']) . ' - Qtia: ' . $value['oco_qtd'] . ' - ' . $value['sut_nome'] . "<br>";
            }
            // echo $ocorretxt;
            $this->data['campos'][] = $ocorretxt;
        }
        // Dados Gerais
        $this->data['secoes'] = ['Dados Gerais'];

        // define os campos
        $this->data['campos'] = [[
            $tel_id,
            $toc_oc,
            $oco->campos['lot_id'],
            $oco->campos['lot_lote'],
            $mod_oc,
            $oco->campos['pro_id'],
            $oco->campos['pro_despro'],
            $quantia,
            $oco->campos['oco_data'],
            $descreva,
            $ocorretxt,
            $req_id,
            $rep_id,
        ]];

        $this->data['destino']     = 'storeocor';
        $this->data['desc_metodo'] = 'Inspeção de Produtos';
        // $this->data['script']      = "<script>
        //                             var elemento = document.getElementById('lot_lote');
        //                             buscaLoteProduto(elemento,'" . base_url('/buscas/buscaProdutoporLote') . "')
        //                         </script>";
        // $this->data['hidden'][] = [
        //     'name'  => 'req_id',
        //     'value' => $reqId
        // ];

        // Renderiza a view
        echo view('vw_edicao_modal', $this->data);
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

    /**
     * Gravação
     * store
     *
     * @return void
     */
    public function storeocor()
    {
        $postado = $this->request->getPost();
        // debug($postado, true);

        try {
            if (empty($postado['oco_id'])) {
                unset($postado['oco_id']);
            }

            // SUBTIPO / STATUS
            $modModel = new OcorreSubtOcorrenciaModel();
            $subtipo  = $modModel->getSubtOcorrencia((int) $postado['sut_id'])[0] ?? null;
            // debug($subtipo, true);
            if ($subtipo) {
                $postado['tpo_id'] = $subtipo->tpo_id;
                if ($subtipo->sut_fina === 'S') {
                    // FINALIZA AUTOMÁTICO
                    $postado['stt_id'] = 29;
                    $postado['tpa_id'] = null;
                    $postado['tmo_id'] = null;
                } else {
                    $acao = $modModel->getAcaoConfigurada((int) $postado['sut_id']);
                    // debug($acao);
                    if (! $acao) {
                        $postado['stt_id'] = 29;
                    } elseif ((int) $acao->tpa_id === 12) {
                        $postado['stt_id'] = 29;
                    } else {
                        $postado['stt_id'] = 28;
                    }
                    // if ($acao) {
                    $postado['tpa_id'] = $acao->tpa_id ?? null;
                    $postado['tmo_id'] = $acao->tmo_id ?? null;
                    // }
                }
            } else {
                return $this->response->setJSON([
                    'erro' => true,
                    'msg'  => 'Subtipo de Ocorrência não encontrado',
                ]);
            }
            // cria data se não veio do form
            if (empty($postado['oco_data'])) {
                $postado['oco_data'] = date('Y-m-d H:i:s');
            }

            $sql_oco = [
                'tpo_id'        => $postado['tpo_id'],
                'sut_id'        => $postado['sut_id'],
                'tel_id'        => $postado['tel_id'],
                'req_id'        => $postado['req_id'],
                'rep_id'        => $postado['rep_id'],
                'tpa_id'        => $postado['tpa_id'],
                'rpo_descricao' => $postado['oco_descricao'],
                'pro_id'        => $postado['pro_id'],
                'lot_id'        => $postado['lot_id'],
                'rpo_qtd'       => $postado['oco_qtd'],
                'rpo_data'      => $postado['oco_data'],
                'tmo_id'        => $postado['tmo_id'],
            ];

            $oco_id = $this->common->insertReg('dbEstoque', 'est_requisicao_produto_ocorrencia', $sql_oco);
            session()->setFlashdata('msg', 'Ocorrência gravada com sucesso!');
            $msg[0] = $postado['rep_id'];
            $msg[1] = $postado['req_id'];
            $msg[2] = $postado['pro_id'];
            $msg[3] = $postado['lot_lote'];
            envia_msg_ws($this->data['controler'], $msg, 'NovaInspecao', session()->get('usu_id'), 1);

            return $this->response->setJSON([
                'erro' => false,
                'msg'  => 'Ocorrência gravada com sucesso!',
                'url'  => site_url($this->data['controler']),
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'erro' => true,
                'msg'  => $e->getMessage(),
            ]);
        }
    }


    public function store()
    {
        $postado    = $this->request->getPost();
        // debug($postado, true);

        $requisicao = $this->requisicao->getRequisicao($postado['req_id'])[0];
        $mudastatus = true;

        if ($requisicao->stt_id == 21 || $requisicao->stt_id == 24) {
            $mudastatus = false;
            $status     = $requisicao->stt_id;
        }

        $dadosAgrupados = [];
        $parcial        = false;
        $verOriginal    = true;

        foreach ($postado as $key => $value) {
            if (preg_match('/^repid_(\d+)$/', $key, $matches)) {
                $id               = $matches[1];
                $dadosTemp        = new \stdClass();
                $dadosTemp->repid = $value;

                foreach ($postado as $campo => $val) {
                    if (str_ends_with($campo, "_$id") && $campo !== "repid_$id") {
                        $nomeCampo             = substr($campo, 0, -strlen("_$id"));
                        $dadosTemp->$nomeCampo = $val;
                    }
                    if ($campo === "req_id") {
                        $dadosTemp->req_id = $val;
                    }
                    if ($campo === "tmo_id") {
                        $dadosTemp->tmo_id = $val;
                    }
                    if ($campo === "rpa_id") {
                        $dadosTemp->rpa_id = $val;
                    }
                }

                $aprovada = (int) ($dadosTemp->aprova ?? -1);

                if ($aprovada > -1) {
                    $dadosAgrupados[$id] = $dadosTemp;
                } else {
                    $parcial = true;
                }
            }
        }

        if (count($dadosAgrupados) === 0) {
            session()->setFlashdata('msg', 7);
            // $ret['url']  = site_url($this->data['controler']);
            $ret['erro'] = true;
            $ret['msg']  = 'Nenhum Produto inspecionado.';
            echo json_encode($ret);
            return;
        }
        // debug($dadosAgrupados, true);
        $db = \Config\Database::connect();
        $db->transBegin();

        $ret['erro'] = false;
        $ate = $this->requisicaoate
            ->getRequisicaoProdutoAtendimento($postado['req_id']);
        $ate = indexarRequisicaoProdutos($ate);
        // debug($ate, true);

        // ─── 1. GRAVA INSPEÇÕES ──────────────────────────────────────────────────
        foreach ($dadosAgrupados as $campo => $val) {

            if (!empty($ate[$val->repid][$val->proid]) && $ate[$val->repid][$val->proid]['rpa_data_inspecao'] === null && (int) $val->aprova > -1) {

                // ── Prepara Update primeiro ──────────────────────────────────────────────
                $sql_save = [
                    'rpa_aprovada'      => $val->aprova,
                    'rpa_data_inspecao' => date('Y-m-d H:i:s'),
                ];


                // ── Movimento antes do update ───────────────────────────────────
                if ((int) $val->aprova > 0) {
                    $movs = [[
                        'id'      => $postado['tmo_id'],
                        'qt'      => $val->aprova,
                        'msg'     => 'Inspeção de Requisição Nº ' . str_pad($val->req_id, 6, '0', STR_PAD_LEFT),
                        'pro_id'  => $val->proid,
                        'rep_id'  => $val->repid,
                        'reserva' => "O",
                    ]];

                    envia_msg_ws($this->data['controler'], "Gerando Movimentos de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                    $movim = geraMovimentoRequisicoes($movs, $this->data['controler']);
                    if ($movim['status'] === 'Erro') {
                        $ret['erro'] = true;
                        $ret['msg']  = $movim['mensagem'];
                        $db->transRollback();
                        echo json_encode($ret);
                        return;
                    }
                }
                if (! $this->requisicaoate->update($val->rpaid, $sql_save)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao gravar a Inspeção.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
            }
        }
        // ─── 4. COMMIT FINAL ─────────────────────────────────────────────────────
        $db->transCommit();

        // ─── 2. ATUALIZA STATUS DA REQUISIÇÃO ────────────────────────────────────
        if ($mudastatus) {
            $status = 5; // Inspeção Parcial
            $pendencias = $this->requisicaoate->getRequisicaoPendencias($postado['req_id']);
            if ($pendencias[0]['pendente_inspecao'] > 0) {
                $status = 26; // Inspeção Parcial
            }
            $db->transBegin();
            $salvareq = $this->requisicao->update($postado['req_id'], ['stt_id' => $status]);

            if (! $salvareq) {
                $ret['erro'] = true;
                $ret['msg']  = 'Erro ao gravar a Inspeção.';
                $db->transRollback();
                echo json_encode($ret);
                return;
            }

            $db->transCommit();
            if ($status == 5) {
                $verOriginal = true;
                $nreq        = str_pad($postado['req_id'], 6, '0', STR_PAD_LEFT);
                $notif       = new Notifica();
                $notif->gravaNotifica('Estoque\Requisicao', session()->get('usu_id'), $postado['req_id'], "A Requisição Nº {$nreq} foi Concluída!", 'C');
            }
        }

        // ─── 3. PROCESSA OCORRÊNCIAS ─────────────────────────────────────────────
        envia_msg_ws($this->data['controler'], "Processando Ocorrências", 'MsgServer', session()->get('usu_id'), 1);
        $produtosreq = $this->requisicao->getRequisicaoProdutos($postado['req_id']);
        $res = service('ocorrenciaService')->gerarOcorrencias($produtosreq, $postado['req_id']);

        $ret['erro'] = false;
        $ret['msg'] = 'Inspeção gravada com sucesso!';
        $ret['url'] = site_url($this->data['controler']);
        session()->setFlashdata('msg', $ret['msg']);

        if ($verOriginal) {
            tratarOriginal($postado['req_id']);
        }

        echo json_encode($ret);
    }
}
