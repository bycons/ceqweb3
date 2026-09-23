<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Controllers\Estoque\Requisicao;
use App\Controllers\Notifica;
use App\Entities\Estoque\EntAteRequisicao;
use App\Entities\Ocorrencia\EntOcoOcorrencia;
use App\Libraries\MyCampo;
use App\Models\CommonModel;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;
use Config\Services;

class AteRequisicao extends BaseController
{
    public $data      = [];
    public $permissao = '';
    public $requisicao;
    public $reqproduto;
    public $tipomovimentacao;
    public $reqprodutoate;
    public $classes;
    public $produtos;
    public $lote;
    public $ocorrencia;
    public $common;
    public $busca;
    public $deposito;
    public $bt_envia;

    /**
     * Construtor da Classe
     * construct
     */
    public function __construct()
    {
        $this->data             = session()->getFlashdata('dados_tela');
        $this->permissao        = $this->data['permissao'];
        $this->requisicao       = new EstoquRequisicaoModel();
        $this->reqproduto       = new EstoquRequisicaoProdutoModel();
        $this->reqprodutoate    = new EstoquRequisicaoProdutoAtendimentoModel();
        $this->classes          = new ProdutClasseModel();
        $this->produtos         = new ProdutProdutoModel();
        $this->busca            = new BuscasSapiens();
        $this->deposito         = new EstoquDepositoModel();
        $this->lote             = new ProdutLoteModel();
        $this->tipomovimentacao = new EstoquTipoMovimentacaoModel();
        $this->ocorrencia       = new OcorreOcorrenciaModel();
        $this->common           = new CommonModel();

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
        $dados_requis = $this->requisicao->getRequisicaoLista(false, [4, 21]);
        // Filtra por perfil
        $dados_requis = filtrarPorPerfil($dados_requis);
        // Filtra por perfil do tipo de movimentação
        $dados_requis = filtrarPorPerfil($dados_requis, null, 'prf_id_tmo_a');

        $req_ids_assoc = array_map(
            fn($r) => $r->req_id,
            $dados_requis
        );

        $log = buscaLogTabelaFirst('est_requisicao', $req_ids_assoc);

        $base_url = base_url($this->data['controler']);
        foreach ($dados_requis as $req) {
            // Verificar se o log já está disponível para esse req_id
            if ($req->req_id) {
                $req->usu_nome = buscaUsuarioLog($log[$req->req_id]);

                // Concatenar o URL de forma mais eficiente
                $url_eti = base_url('/EtqProduto/Etiqueta/' . $req->req_id);
                $url_ate = $base_url . '/atende/' . $req->req_id;
                $url_imp = base_url('/CriamPdf2026/PrintRequisicaoEstoq/' . $req->req_id);

                $bt_ate           = new MyCampo();
                $bt_ate->id       = $bt_ate->nome       = 'bt_atende';
                $bt_ate->classep  = 'btn btn-outline-success btn-sm border-0 mx-0 fs-0';
                $bt_ate->i_cone   = "<i class='fas fa-bell-concierge'></i>";
                $bt_ate->label    = '';
                $bt_ate->place    = 'Atendimento';
                $bt_ate->funcChan = "redireciona('{$url_ate}')";
                $btate            = $bt_ate->crBotao();

                $bt_etq           = new MyCampo();
                $bt_etq->id       = $bt_etq->nome       = 'bt_etiqueta';
                $bt_etq->classep  = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
                $bt_etq->i_cone   = "<i class='fas fa-tag'></i>";
                $bt_etq->label    = '';
                $bt_etq->place    = 'Etiquetas de Produtos';
                $bt_etq->funcChan = "redireciona('{$url_eti}')";
                $btetq            = $bt_etq->crBotao();

                // Botão imprimir
                $btprn = '';
                if (trim($req->stt_impressao) === 'S') {
                    $bt_prn           = new MyCampo();
                    $bt_prn->id       = $bt_prn->nome       = 'bt_print';
                    $bt_prn->classep  = 'btn btn-outline-dark btn-sm border-0 mx-0 fs-0';
                    $bt_prn->i_cone   = "<i class='fa-solid fa-print'></i>";
                    $bt_prn->label    = '';
                    $bt_prn->place    = 'Imprimir Requisição';
                    $bt_prn->funcChan = "openPDFModal('{$url_imp}','Imprimir Requisição')";
                    $btprn            = $bt_prn->crBotao();
                }
                // Ações personalizadas
                $req->acao_person = [
                    $btate,
                    $btetq,
                    $btprn,
                ];
            }
        }

        // debug($dados_requis, true);
        $this->data['edicao'] = false;

        // MOSTRA CONSULTA EM TODOS OS STATUS
        $this->data['allconsulta'] = true;

        $requis = [
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

    public function edit($id, $show = false)
    {
        $this->atende($id, $show = false);
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
        $ret = [];

        $requis = $this->requisicao->getRequisicao($id);

        if (! $requis) {
            return redirectWithError($this->data['controler'], 41);

            // return view('errors/vw_semregistro', [
            //     'mensagem' => 'Requisição não encontrada'
            // ]);
        }
        $status = $requis[0]->stt_id;

        // if ($status == 6) {
        try {
            // Soft delete
            $this->requisicao->delete($id);
            $ret['erro'] = false;
            $ret['msg']  = 'Requisição Excluída com Sucesso';
            session()->setFlashdata('msg', 'Requisição Excluída com Sucesso');
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 3;
        }
        // } else {
        //     $ret['erro'] = true;
        //     $ret['msg']  = 3;
        // }

        echo json_encode($ret);
    }
    /**
     * Atenndimento
     * atende
     *
     * @param mixed $id
     * @return void
     */
    public function atende($id, $show = true)
    {
        $requisicao = $this->requisicao->getRequisicao($id);

        if (! $requisicao) {
            return redirectWithError($this->data['controler'], 41);
            // session()->setFlashdata('erromsg', 'Requisição não encontrada.');
            // return redirect()->to(site_url($this->data['controler']));
        }
        $requisicao = $requisicao[0];

        $log                  = buscaLog('est_requisicao', $id);
        $requisicao->usu_nome = $log['usua_alterou'] ?? '';

        $contRequis = new Requisicao();
        $secao[1]   = 'Dados Gerais';
        $campos[1]  = $contRequis->showCabecalhoSimples($requisicao);
        $entReq     = new EntAteRequisicao();

        $produtos = $this->requisicao->getRequisicaoProdutos($id);

        // array_column mudando para o OBJ
        $pro_ids = array_map(
            fn(object $p): int => $p->pro_id,
            $produtos
        );
        // debug($pro_ids);

        $dados_est_produto = $this->produtos->getProdutoEstoque($pro_ids, $requisicao->req_deporigem);
        // debug($produtos, false);
        // debug($dados_est_produto, true);

        // Transformar $produtos em um array indexado por pro_id
        $produtosIndexado = [];
        foreach ($produtos as $param) {
            $produtosIndexado[$param->pro_id][$param->lot_lote] = $param;
        }
        // debug($produtosIndexado, true);
        // Indexa os dados de estoque por pro_id para facilitar busca
        $estoqueIndexado = [];
        foreach ($dados_est_produto as $itemEstoque) {
            $estoqueIndexado[$itemEstoque->pro_id] = $itemEstoque;
        }

        $semsaldo = false;

        if ($semsaldo) {
            $ret['erro'] = true;
            $ret['msg']  = 37;
            session()->setFlashdata('msg', 37);
            return redirect()->to(site_url($this->data['controler']));
        } else {
            // Resultado final com todos os produtos
            $resultado = [];

            foreach ($produtosIndexado as $pro_id => $lotes) {
                foreach ($lotes as $produto) {
                    if (isset($estoqueIndexado[$pro_id])) {
                        // Mescla produto com dados de estoque (OBJ para array temporário)
                        $resultado[] = array_merge((array) $produto, (array) $estoqueIndexado[$pro_id]);
                    } else {
                        // Apenas dados do produto
                        $resultado[] = (array) $produto;
                    }
                }
            }
            // debug($resultado, true);Q

            // envia_msg_ws($this->data['controler'], "Buscando estoque de origem " . $requisicao->req_deporigem, 'MsgServer', session()->get('usu_id'), 1);
            // ═══════════════════════════════════════════════════════════════
            // TEMPORÁRIO (2026-07-14): busca real de saldo via SOAP desabilitada.
            // Atribui saldo fictício de 10000 para todos os produtos.
            // Reverter removendo este bloco e descomentando o original abaixo.
            // ═══════════════════════════════════════════════════════════════
            // $montaSaldoFicticioTemp = function () use ($resultado): array {
            //     $fake = [];
            //     foreach ($resultado as $r) {
            //         $r    = (object) $r;
            //         $lote = trim($r->lot_lote ?? '');
            //         $fake[] = (object) [
            //             'codigoProduto'     => $r->pro_codpro,
            //             'codigoLote'        => $lote !== '' ? $lote : 'SEM_LOTE',
            //             'quantidadeEstoque' => 10000,
            //         ];
            //     }
            //     return $fake;
            // };

            $estoqueOrigem = $this->busca->buscaEstoqueDeposito(
                $requisicao->req_deporigem
            );
            // $estoqueOrigem = $montaSaldoFicticioTemp();
            $estoqueOrigem = indexarEstoque($estoqueOrigem);
            // debug($estoqueOrigem['EM042'], true);
            for ($p = 0; $p < count($resultado); $p++) {
                $prod = (object) $resultado[$p];

                $estoqueEncontrado = 0;
                if (trim($prod->lot_lote) == '') {
                    $prod->lot_lote = 'Sem Lote';
                }
                $estoqProd = $estoqueOrigem[$prod->pro_codpro][trim($prod->lot_lote)][0] ?? null;

                $estoqueEncontrado = $estoqProd
                    ? (int) str_replace('.', '', (string) $estoqProd->quantidadeEstoque)
                    : 0;
                $prod->estoque_origem = $estoqueEncontrado;                // debug($prod, true);

                // envia_msg_ws($this->data['controler'], "Definindo os Campos", 'MsgServer', session()->get('usu_id'), 1);
                if (! isset($prod->pre_cbfabricante)) {
                    // debug($prod);
                    $resultado[$p]['pre_cbfabricante']  = 'N';
                    $resultado[$p]['pre_undfabricante'] = 'N';
                    $resultado[$p]['pre_cblote']        = 'N';
                    $resultado[$p]['pre_undlote']       = 'N';

                    $prod->pre_cbfabricante  = 'N';
                    $prod->pre_undfabricante = 'N';
                    $prod->pre_cblote        = 'N';
                    $prod->pre_undlote       = 'N';
                }

                $fields = $entReq->defCamposProdutoAte($prod);

                $url = base_url("OcoOcorrencia/addOutraTela/" . $this->data['tel_id'] . "/" . $id . "/" . $prod->pro_id);

                $bt_oco          = new MyCampo();
                $bt_oco->id      = $bt_oco->nome      = 'bt_ocorre';
                $bt_oco->classep = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
                $bt_oco->i_cone  = "<i class='fa-solid fa-exclamation-triangle'></i>";
                $bt_oco->label   = '';
                // $bt_oco->attrdata = ['data-id' => ];
                $bt_oco->place    = 'Gerar Ocorrência';
                $bt_oco->funcChan = "gerarOcorrencia({$this->data['tel_id']}, {$prod->rep_id})";
                $btoco            = $bt_oco->crBotao();

                $resultado[$p]['bt_ocorre'] = $btoco;

                $resultado[$p]['rpa_cancelada']     = $fields['rpa_cancelada'];
                $resultado[$p]['rpa_atendida']      = $fields['rpa_atendida'];
                $resultado[$p]['rpa_cancelada_val'] = $prod->rpa_cancelada;
                $resultado[$p]['rpa_atendida_val']  = $prod->rpa_atendida;
                $resultado[$p]['saldo']             =
                    intval($resultado[$p]['rep_quantia']) -
                    (intval($prod->rpa_cancelada) + intval($prod->rpa_atendida));
                $resultado[$p]['estoque_origem'] = $prod->estoque_origem;
            }
            // debug('fim', true);
            // $secao[1] = 'Produtos';
            // envia_msg_ws($this->data['controler'], "Preparando a tela", 'MsgServer', session()->get('usu_id'), 1);

            $fields      = $entReq->defCampos($requisicao, $show);
            $dispositivo = Services::device();

            // if ($dispositivo->isMobile()) {
            // if (1 == 1) {
            $secao[0]    = 'Produtos';
            $campos[0][] = $fields['lot_codbar'];
            $campos[0][] =
                view('partials/pw_produtos_requisicao', ['produtos' => $resultado, 'maxHeig' => '60vh']); // mesma estrutura do add
            // } else {
            //     $campos[0][] = $fields['lot_codbar'];
            //     $campos[0][] =
            //         view('partials/pw_produtos_requisicao', ['produtos' => $resultado, 'maxHeig' => '49vh']); // mesma estrutura do add
            // }

            $scripti = "<SCRIPT>jQuery('#lot_codbar').focus();</SCRIPT>";

            $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT) . ' ' . fmtEtiquetaCor($requisicao->stt_cor, $requisicao->stt_nome, 1);
            $this->data['desc_metodo'] = ' ';
            $this->data['secoes']      = $secao;
            $this->data['campos']      = $campos;
            $this->data['destino']     = 'store';
            $this->data['scripts']     = 'my_requisicao';

            $this->data['script'] = $scripti;
            echo view('vw_edicao', $this->data);
        }
    }

    public function store()
    {
        $postado = $this->request->getPost();

        $dadosAgrupados = [];
        $verOriginal    = false;

        // envia_msg_ws($this->data['controler'], "Preparando dados recebidos", 'MsgServer', session()->get('usu_id'), 1);

        foreach ($postado as $key => $value) {
            if (preg_match('/^repid_(\d+)$/', $key, $matches)) {
                $id        = $matches[1];
                $dadosTemp = ['repid' => $value];

                foreach ($postado as $campo => $val) {
                    if (str_ends_with($campo, "_$id") && $campo !== "repid_$id") {
                        $nomeCampo             = substr($campo, 0, -strlen("_$id"));
                        $dadosTemp[$nomeCampo] = $val;
                    }
                    if ($campo === "req_id") {
                        $dadosTemp[$campo] = $val;
                    }
                    if ($campo === "tmo_id") {
                        $dadosTemp[$campo] = $val;
                    }
                }

                $qtia       = (int) ($dadosTemp['repqtia'] ?? 0);
                $cancelada  = (int) ($dadosTemp['rpa_cancelada'] ?? 0);
                $atendida   = (int) ($dadosTemp['rpa_atendida'] ?? 0);

                if ($qtia === $cancelada + $atendida) {
                    $dadosAgrupados[$id] = $dadosTemp;
                }
            }
        }

        if (count($dadosAgrupados) === 0) {
            session()->setFlashdata('msg', 7);
            $ret['url']  = site_url($this->data['controler']);
            $ret['erro'] = false;
            echo json_encode($ret);
            return;
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        $ret['erro'] = false;
        $errosMov = 0;

        // ─── 1. GRAVA ATENDIMENTOS ───────────────────────────────────────────────
        // envia_msg_ws($this->data['controler'], "Gravando Atendimento", 'MsgServer', session()->get('usu_id'), 1);
        $nreq       = str_pad($postado['req_id'], 6, '0', STR_PAD_LEFT);
        // debug($ate, true);
        $dataagora = date('Y-m-d H:i:s');
        foreach ($dadosAgrupados as $campo => $val) {

            // Só registra se ainda não houver atendimento
            $ate = $this->reqprodutoate->getProdutoRequisicaoAtendimento($val['repid'], $val['proid']);
            if ($ate) {
                continue;
            }

            // Movimento somente se houver quantidade atendida
            // Se falhar, o transRollback desfaz a inserção acima
            if ((int) $val['rpa_atendida'] > 0) {
                // envia_msg_ws($this->data['controler'], "Gerando Movimentação de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                $movim = geraMovimentoRequisicoes([[
                    'id'      => $postado['tmo_id'],
                    'qt'      => $val['rpa_atendida'],
                    'msg'     => 'Atendimento da Requisição Nº ' . $nreq,
                    'pro_id'  => $val['proid'],
                    'rep_id'  => $val['repid'],
                    'reserva' => "D",
                ]], $this->data['controler']);

                if ($movim['status'] === 'Erro') {
                    // $db->transRollback();
                    // $ret['erro'] = true;
                    $ret['msg']  = $movim['mensagem'];
                    $errosMov++;
                    // echo json_encode($ret);
                    continue;
                }
            }
            $sql_save = [
                'rep_id'        => $val['repid'],
                'pro_id'        => $val['proid'],
                'rpa_cancelada' => $val['rpa_cancelada'],
                'rpa_atendida'  => $val['rpa_atendida'],
                'rpa_data'      => date('Y-m-d H:i:s'),
            ];

            // Cancelado sem atendimento: marca conferida e aprovada, sem movimento
            if ((int) $val['rpa_cancelada'] > 0 && (int) $val['rpa_atendida'] === 0) {
                $sql_save['rpa_conferida']        = -1;
                $sql_save['rpa_data_conferencia'] = date('Y-m-d H:i:s');
                $sql_save['rpa_aprovada']         = -1;
                $sql_save['rpa_data_inspecao']    = date('Y-m-d H:i:s');
            }

            // Grava o atendimento primeiro
            $salva = $this->reqprodutoate->insert($sql_save);
            if (! $salva) {
                $ret['erro'] = true;
                $ret['msg']  = 'Erro ao gravar o Atendimento.';
                $db->transRollback();
                echo json_encode($ret);
                return;
            }
        }

        // ─── 2. DETERMINA STATUS PELA CONSULTA DE PENDÊNCIAS ────────────────────
        // envia_msg_ws($this->data['controler'], "Acertando Status", 'MsgServer', session()->get('usu_id'), 1);
        $pendencias = $this->reqprodutoate->getRequisicaoPendencias($postado['req_id']);

        $nreq       = str_pad($postado['req_id'], 6, '0', STR_PAD_LEFT);
        $destNotif  = 'Estoque\ConfRequisicao';
        $msgsocket  = "A Requisição Nº " . $nreq . " foi Atendida!";

        if ($pendencias[0]['tudo_cancelado'] === 'S') {
            $status    = 7;
            $destNotif = 'Estoque\Requisicao';
            $msgsocket = "A Requisição Nº " . $nreq . " foi Cancelada!";
        } elseif ($pendencias[0]['pendente_atendimento'] > 0) {
            $status    = 21;
            $msgsocket = "A Requisição Nº " . $nreq . " foi Atendida Parcial!";
        } else {
            $status  = 18;
            $insvis  = retornaInsVis($postado['req_id']);
            $tipomov = $this->tipomovimentacao->getTipoMovimentacao($postado['tmo_id'])[0];
            if ($tipomov->tmo_conferencia == 'N') {
                $status    = 25;
                $destNotif = 'Preproces/InspecaoProd';
                $msgsocket = "A Requisição Nº " . $nreq . " foi Conferida!";
                if ($insvis->temN || $tipomov->tmo_exigeinspecao == 'N') {
                    $status    = 5;
                    $destNotif = 'Estoque\Requisicao';
                    $msgsocket = "A Requisição Nº " . $nreq . " foi Concluída!";
                    $verOriginal    = true;
                }
            }
        }

        $produtosreq = $this->requisicao->getRequisicaoProdutos($postado['req_id']);

        // ─── 3. MOVIMENTOS DE CONCLUSÃO AUTOMÁTICA (status 5) ───────────────────
        if ($status == 5) {
            foreach ($produtosreq as $val) {
                if ($val->rpa_data >= $dataagora) {
                    // envia_msg_ws($this->data['controler'], "Gerando Movimentações de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                    $movim = geraMovimentoRequisicoes([[
                        'id'      => $postado['tmo_id'],
                        'qt'      => $val->rpa_atendida,
                        'msg'     => 'Conclusão no Atendmento da Requisição Nº ' . $nreq,
                        'pro_id'  => $val->pro_id,
                        'rep_id'  => $val->rep_id,
                        'reserva' => "O",
                    ]], $this->data['controler']);

                    if ($movim['status'] === 'Erro') {
                        // $db->transRollback();
                        // $ret['erro'] = true;
                        $ret['msg']  = $movim['mensagem'];
                        // echo json_encode($ret);
                        $errosMov++;
                        continue;
                    }
                }
            }
        }

        // ─── 4. ATUALIZA STATUS DA REQUISIÇÃO ────────────────────────────────────
        $salvareq = $this->requisicao->update($postado['req_id'], ['stt_id' => $status]);
        if (! $salvareq) {
            $ret['erro'] = true;
            $ret['msg']  = 'Erro ao gravar Atendimento de Requisição.';
            $db->transRollback();
            echo json_encode($ret);
            return;
        }

        // ─── 5. NOTIFICAÇÃO ──────────────────────────────────────────────────────
        $notif = new Notifica();
        $notif->gravaNotifica($destNotif, session()->get('usu_id'), $postado['req_id'], $msgsocket, 'C');
        $db->transCommit();

        // ─── 6. OCORRÊNCIAS ──────────────────────────────────────────────────────
        // envia_msg_ws($this->data['controler'], "Processando Ocorrências", 'MsgServer', session()->get('usu_id'), 1);
        $res = service('ocorrenciaService')->gerarOcorrencias($produtosreq, $postado['req_id']);

        // ─── 7. COMMIT FINAL ─────────────────────────────────────────────────────
        $ret['erro'] = false;
        $ret['msg'] = 'Atendimento gravado com sucesso!';
        $ret['url'] = site_url($this->data['controler']);
        session()->setFlashdata('msg', $ret['msg']);
        if ($verOriginal) {
            tratarOriginal($postado['req_id']);
        }

        echo json_encode($ret);
    }
}
