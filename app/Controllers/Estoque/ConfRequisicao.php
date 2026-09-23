<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\Buscas;
use App\Controllers\BuscasSapiens;
use App\Controllers\Notifica;
use App\Entities\Estoque\EntConfRequisicao;
use App\Entities\Ocorrencia\EntOcoOcorrencia;
use App\Libraries\MyCampo;
use App\Models\CommonModel;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class ConfRequisicao extends BaseController
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
    public $tipomovimentacao;
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
        $this->tipomovimentacao  = new EstoquTipoMovimentacaoModel();
        $this->busca             = new BuscasSapiens();
        $this->deposito          = new EstoquDepositoModel();
        $this->lote              = new ProdutLoteModel();
        $this->ocorrencia        = new OcorreOcorrenciaModel();
        $this->common            = new CommonModel();

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
        $campos = montaColunasCampos($this->data, 'req_id');

        $dados_requis = $this->requisicao->getRequisicaoConferencia(false, [18, 21, 24]);
        // Filtra por perfil
        $dados_requis = filtrarPorPerfil($dados_requis);
        // Filtra por perfil do tipo de movimentação
        $dados_requis = filtrarPorPerfil($dados_requis, null, 'prf_id_tmo_c');

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
                $url_eti = base_url('/EtqMisturador/Etiqueta/' . $req->req_id);
                $url_con = $base_url . '/confere/' . $req->req_id;
                $url_imp = base_url('/CriamPdf2026/PrintRequisicaoEstoq/' . $req->req_id);

                $bt_con           = new MyCampo();
                $bt_con->id       = $bt_con->nome       = 'bt_confere';
                $bt_con->classep  = 'btn btn-outline-success btn-sm border-0 mx-0 fs-0';
                $bt_con->i_cone   = "<i class='fas fa-check'></i>";
                $bt_con->label    = '';
                $bt_con->place    = 'Conferência';
                $bt_con->funcChan = "redireciona('{$url_con}')";
                $btcon            = $bt_con->crBotao();

                $bt_etq           = new MyCampo();
                $bt_etq->id       = $bt_etq->nome       = 'bt_etiqueta';
                $bt_etq->classep  = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
                $bt_etq->i_cone   = "<i class='fas fa-tags fa-rotate-90'></i>";
                $bt_etq->label    = '';
                $bt_etq->place    = 'Etiquetas do Misturador';
                $bt_etq->funcChan = "redireciona('{$url_eti}')";
                $btetq            = $bt_etq->crBotao();

                // Gerar a ação do botão
                $btprn            = '';
                $req->acao_person = [
                    $btetq,
                    $btcon,
                    $btprn,
                ];
            }
        }

        $this->data['edicao']      = false;
        $this->data['allconsulta'] = true;

        // debug($dados_requis, true);
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
        $this->confere($id, $show = true);
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
    public function confere($id, $show = true)
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

        // Montar campos como no add()
        $ent    = new EntConfRequisicao((array) $requisicao, $show, 'conf');
        $fieldsini = $ent->campos;
        // debug($fields, true);

        $resultado   = (new Buscas())->buscaProdutosMergeEstoques($id, $requisicao->req_depdestino, 'conferencia');
        // debug($resultado, true);

        // debug(count($resultado));
        for ($p = 0; $p < count($resultado); $p++) {
            // foreach ($resultado as $p => $prod) {
            $prod = $resultado[$p];
            // debug($prod);
            $val_cancelada = $prod->rpa_cancelada ?? 0;
            $val_atendida  = $prod->rpa_atendida ?? 0;
            $val_conferida = $prod->rpa_conferida ?? 0;

            if (! isset($prod->pre_cbmisturador)) {
                $resultado[$p]->pre_cbmisturador  = 'N';
                $resultado[$p]->pre_undmisturador = 'N';
                $prod->pre_cbmisturador           = 'N';
                $prod->pre_undmisturador          = 'N';
            }

            if (! isset($prod->pre_cbfabricante)) {
                $resultado[$p]->pre_cbfabricante  = 'N';
                $resultado[$p]->pre_undfabricante = 'N';
                $resultado[$p]->pre_cblote        = 'N';
                $resultado[$p]->pre_undlote       = 'N';
                $prod->pre_cbfabricante           = 'N';
                $prod->pre_undfabricante          = 'N';
                $prod->pre_cblote                 = 'N';
                $prod->pre_undlote                = 'N';
            }
            // debug($prod->pre_gestaoestoque);
            if ($prod->cla_gestaoestoque == 'N') {
                $resultado[$p]->pre_cbfabricante  = 'N';
                $resultado[$p]->pre_undfabricante = 'N';
                $resultado[$p]->pre_cblote        = 'N';
                $resultado[$p]->pre_undlote       = 'N';
                $resultado[$p]->pre_cbmisturador  = 'N';
                $resultado[$p]->pre_undmisturador = 'N';
                $prod->pre_cbfabricante           = 'N';
                $prod->pre_undfabricante          = 'N';
                $prod->pre_cblote                 = 'N';
                $prod->pre_undlote                = 'N';
                $prod->pre_cbmisturador           = 'N';
                $prod->pre_undmisturador          = 'N';
            }
            // debug($prod, true);
            $btoco            = '';
            if ($prod->rpa_conferida > -1) {
                $bt_oco           = new MyCampo();
                $bt_oco->id       = $bt_oco->nome       = 'bt_ocorre';
                $bt_oco->classep  = 'btn btn-outline-warning btn-sm border-0 mx-0 fs-0';
                $bt_oco->i_cone   = "<i class='fa-solid fa-exclamation-triangle'></i>";
                $bt_oco->tipo     = 'button';
                $bt_oco->label    = '';
                $bt_oco->place    = 'Gerar Ocorrência';
                $bt_oco->funcChan = "gerarOcorrencia({$this->data['tel_id']}, {$prod->rep_id})";
                $btoco            = $bt_oco->crBotao();
            }
            $resultado[$p]->bt_ocorre      = $btoco;
            $resultado[$p]->prc_codbar     = $prod->prc_codbar ?? null;
            $resultado[$p]->cla_insvis     = $prod->cla_insvis ?? 'N';
            $resultado[$p]->cla_insvisconf = $prod->cla_insvisconf ?? 'N';
            $resultado[$p]->bt_insvis      = "";

            if ($prod->cla_insvis == 'S' && $prod->cla_insvisconf == 'S' && $requisicao->tmo_exigeinspecao == 'S' && $prod->rpa_conferida > -1) {
                $badge = "<span id='badgeinsp_{$prod->rep_id}' class='badgeinsp badge rounded-pill bg-black p-1 fs-7' style='max-height:16px'></span>";

                $bt_insvis           = new MyCampo();
                $bt_insvis->id       = $bt_insvis->nome       = "bt_insvis_$prod->rep_id";
                $bt_insvis->classep  = 'btn btn-outline-black btn-sm border-0 mx-0 fs-0 float-start';
                $bt_insvis->i_cone   = "<i class='fab fa-searchengin'></i>";
                $bt_insvis->label    = '';
                $bt_insvis->place    = 'Inspeção Visual';
                $bt_insvis->funcChan = "gerarInspecao(48, {$prod->rep_id})";
                $btinsvis            = $bt_insvis->crBotao() . $badge;

                $bt_ok              = new MyCampo();
                $bt_ok->id          = $bt_ok->nome          = "btok_$prod->rep_id";
                $bt_ok->dispForm    = 'float-end';
                $bt_ok->classep     = 'semmb';
                $bt_ok->label       = '';
                $bt_ok->valor       = 1;
                $bt_ok->selecionado = '';
                $bt_ok->place       = 'Inspeção OK';
                $bt_ok->funcChan    = "mostraOcultaCampo('btok_$prod->rep_id', '', 'bt_insvis_$prod->rep_id');";
                $btok               = $bt_ok->crCheckbox();

                $resultado[$p]->bt_insvis = "<div class='d-flex text-nowrap col-12 p-0 m-0'>" . $btinsvis . $btok . "</div>";
            }

            $fields = $ent->defCamposProdutoConf($prod);

            $resultado[$p]->rpa_id            = $prod->rpa_id;
            $resultado[$p]->rpa_cancelada     = $prod->rpa_cancelada;
            $resultado[$p]->rpa_atendida      = $fields['rpa_atendida'];
            $resultado[$p]->rpa_cancelada_val = $val_cancelada;
            $resultado[$p]->rpa_atendida_val  = $val_atendida;
            if ($prod->rpa_conferida > -1) {
                $resultado[$p]->rpa_conferida     = $fields['rpa_conferida'];
                $resultado[$p]->saldo             = intval($val_atendida) - intval($prod->rpa_conferida);
            } else {
                $resultado[$p]->rpa_conferida     = '';
                $resultado[$p]->saldo             = intval($val_atendida);
            }

            $filtro = [
                'pro_id'   => $prod->pro_id,
                'lot_lote' => $prod->lot_lote,
            ];
            $inspecoes = $this->common->getRegFiltro('dbEstoque', 'est_requisicao_produto_ocorrencia', ['rpo_id', 'rpo_qtd'], $filtro);
            if ($inspecoes) {
                $insp = 0;
                foreach ($inspecoes as $key => $value) {
                    $this->common->deleteReg('dbEstoque', 'est_requisicao_produto_ocorrencia', "rpo_id = " . $value['rpo_id']);
                }
            }
        }
        foreach ($resultado as &$prod) {

            // CONTEXTO
            $prod->req_id = $requisicao->req_id;
            $prod->rpa_id = $prod->rpa_id;

            // IDENTIFICAÇÃO DO PRODUTO
            $prod->pro_id     = $prod->pro_id ?? 0;
            $prod->pro_codpro = $prod->pro_codpro ?? '';
            $prod->pro_despro = $prod->pro_despro ?? '';
            $prod->pro_qtdemb = $prod->pro_qtdemb ?? 1;

            // FABRICANTE
            $prod->pro_codbar_fabricante = $prod->pro_codbar_fabricante ?? '';
            $prod->fab_apeFab            = $prod->fab_apeFab ?? '';

            // LOTE
            $prod->lot_codbar   = $prod->lot_codbar ?? '';
            $prod->lot_lote     = $prod->lot_lote ?? '';
            $prod->lot_validade = $prod->lot_validade ?? null;

            // PARAMETRIZAÇÕES (LF / LP / LM)
            $prod->pre_cbfabricante  = $prod->pre_cbfabricante ?? 'N';
            $prod->pre_undfabricante = $prod->pre_undfabricante ?? 'N';
            $prod->pre_cblote        = $prod->pre_cblote ?? 'N';
            $prod->pre_undlote       = $prod->pre_undlote ?? 'N';
            $prod->pre_cbmisturador  = $prod->pre_cbmisturador ?? 'N';
            $prod->pre_undmisturador = $prod->pre_undmisturador ?? 'N';

            // QUANTIDADES
            $prod->rep_id      = $prod->rep_id ?? 0;
            $prod->rep_quantia = $prod->rep_quantia ?? 0;
            if ($prod->rpa_cancelada_val > 0) {
                $qtcaixa         = ceil($prod->rpa_atendida_val / ($prod->pro_qtdemb ?: 1));
                $prod->qtd_caixa = $qtcaixa;
            } else {
                $prod->qtd_caixa = $prod->qtd_caixa ?? 0;
            }

            // ATENDIMENTO / CONFERÊNCIA
            $prod->rpa_atendida      = $prod->rpa_atendida ?? 0;
            $prod->rpa_cancelada     = $prod->rpa_cancelada ?? 0;
            $prod->rpa_conferida     = $prod->rpa_conferida ?? 0;
            $prod->rpa_atendida_val  = $prod->rpa_atendida_val ?? '';
            $prod->rpa_cancelada_val = $prod->rpa_cancelada_val ?? '';

            // SALDO
            $prod->saldo = $prod->saldo ?? (
                intval($prod->rpa_atendida) - intval($prod->rpa_conferida)
            );

            // BOTÕES (SEGURANÇA)
            $prod->bt_ocorre = $prod->bt_ocorre ?? '';
            $prod->bt_insvis = $prod->bt_insvis ?? '';
            // debug($prod, true);
        }
        unset($prod);

        $saldoTotal = array_sum(array_map(fn($p) => intval($p->saldo), $resultado));
        if ($saldoTotal === 0) {
            $this->data['forca_submit'] = true;
        }

        $secao[0]    = 'Produtos';
        $campos[0][] = $fieldsini['lot_codbar'];
        $campos[0][] =
            view('partials/pw_produtos_conferencia', ['produtos' => $resultado, 'maxHeig' => '60vh']); // mesma estrutura do add
        // $campos[0][] =
        //     view('partials/pw_produtos_conferencia', ['produtos' => $resultado]);
        // $campos[0][]               = '</>';
        $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT) . ' ' . fmtEtiquetaCor($requisicao->stt_cor, $requisicao->stt_nome, 1);
        $this->data['desc_metodo'] = ' ';
        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['destino']     = 'store';
        $this->data['scripts']     = 'my_requisicao';

        $this->data['script'] = "<SCRIPT>jQuery('#lot_codbar').focus();</SCRIPT>";
        echo view('vw_edicao', $this->data);
    }

    public function store()
    {
        $postado = $this->request->getPost();
        // debug($postado, true);
        envia_msg_ws($this->data['controler'], "Preparando dados recebidos", 'MsgServer', session()->get('usu_id'), 1);

        $requisicao = $this->requisicao->getRequisicao($postado['req_id'])[0];
        $mudastatus = true;
        if ($requisicao->stt_id == 21) {
            $mudastatus = false;
            $status     = 21;
        }

        $dadosAgrupados = [];
        $parcial        = false;
        $verOriginal    = true;
        $aprovada       = [];

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

                $atendida  = (int) ($dadosTemp->rpa_atendida ?? 0);
                $conferida = (int) ($dadosTemp->rpa_conferida ?? 0);

                if ($conferida > 0) {
                    $dadosAgrupados[$id] = $dadosTemp;
                } else {
                    if ($atendida > 0) {
                        $parcial = true;
                    }
                }
            }
        }

        if (count($dadosAgrupados) === 0 && $parcial) {
            session()->setFlashdata('msg', 7);
            $ret['url']  = site_url($this->data['controler']);
            $ret['erro'] = false;
            echo json_encode($ret);
            return;
        }
        // debug($dadosAgrupados, true);

        $db = \Config\Database::connect();
        $db->transBegin();

        $ret['erro'] = false;

        // ─── 1. GRAVA CONFERÊNCIAS ───────────────────────────────────────────────
        envia_msg_ws($this->data['controler'], "Gravando Conferência", 'MsgServer', session()->get('usu_id'), 1);
        $nreq       = str_pad($postado['req_id'], 6, '0', STR_PAD_LEFT);
        $ate = $this->requisicaoate->getRequisicaoProdutoAtendimento($postado['req_id']);
        $ate = indexarRequisicaoProdutos($ate);
        // debug($ate, true);
        $dataagora = date('Y-m-d H:i:s');
        foreach ($dadosAgrupados as $campo => $val) {

            if (!empty($ate[$val->repid][$val->proid]) && $ate[$val->repid][$val->proid]['rpa_data_conferencia'] === null && (int) $val->rpa_conferida > 0) {
                $sql_save = [
                    'rpa_conferida'        => $val->rpa_conferida,
                    'rpa_data_conferencia' => date('Y-m-d H:i:s'),
                ];

                $aprovaValido = isset($val->aprova) && $val->aprova !== '';

                if ($aprovaValido) {
                    $sql_save['rpa_aprovada']      = $val->aprova;
                    $sql_save['rpa_data_inspecao'] = date('Y-m-d H:i:s');
                }


                // ── Movimento antes do update ───────────────────────────────────
                if ($aprovaValido && (int) $val->rpa_conferida > 0) {
                    $movs = [[
                        'id'      => $postado['tmo_id'],
                        'qt'      => $val->rpa_conferida,
                        'msg'     => 'Conclusão na Conferência da Requisição Nº ' . $nreq,
                        'pro_id'  => $val->proid,
                        'rep_id'  => $val->repid,
                        'reserva' => "O",
                    ]];
                    envia_msg_ws($this->data['controler'], "Gerando Movimentos de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                    $movim = geraMovimentoRequisicoes($movs, $this->data['controler']);
                    if ($movim['status'] === 'Erro') {
                        $ret['msg']  = $movim['mensagem'];
                        continue;
                    }
                    $aprovada[] = $val->repid;
                } elseif (! $aprovaValido && $val->rpa_conferida != $val->rpa_atendida) {
                    $qtia = (int) ($val->rpa_atendida - $val->rpa_conferida);
                    if ($qtia > 0) {
                        $movs = [[
                            'id'      => $postado['tmo_id'],
                            'qt'      => $qtia,
                            'msg'     => 'Divergência na Conferência da Requisição Nº ' . $nreq,
                            'pro_id'  => $val->proid,
                            'rep_id'  => $val->repid,
                            'reserva' => "O",
                            'tipomov' => "R",
                        ]];
                        envia_msg_ws($this->data['controler'], "Gerando Movimentos de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                        $movim = geraMovimentoRequisicoes($movs, $this->data['controler']);
                        if ($movim['status'] === 'Erro') {
                            $ret['msg']  = $movim['mensagem'];
                            continue;
                        }
                        #TODO gerar a ocorrência
                        $sutid = 56;
                        $classes = (new OcorreSubtOcorrenciaModel())->getClassePorSubtipo($sutid) ?? [];
                        $idsClasses = array_column($classes, 'cla_id');
                        $lote = (new ProdutLoteModel())->getLoteClasse($val->lotlote, $idsClasses);

                        $loteDados = [];
                        if (!empty($lote)) {
                            $loteDados = [
                                'proid'    => $lote[0]->pro_id,
                                'lotid'    => $lote[0]->lot_id,
                                'despro'   => $lote[0]->pro_despro,
                                'validade' => $lote[0]->lot_validade ?? '',
                                'Fabrica'  => $lote[0]->fab_nomFab ?? '',
                                'codErp'   => $lote[0]->lot_codpro ?? '',
                            ];
                        }

                        $sql_oco = [
                            'tpo_id'        => 3, // tipo
                            'sut_id'        => $sutid, // subtipo
                            'tel_id'        => 48, // tela de ocorrencia
                            'req_id'        => $postado['req_id'],
                            'rep_id'        => $val->repid,
                            'rpo_descricao' => 'Divergência na Conferência da Requisição Nº ' . $nreq,
                            'pro_id'        => $val->proid,
                            'lot_id'        => $loteDados['lotid'],
                            'rpo_qtd'       => $qtia,
                            'rpo_data'      => date('Y-m-d H:i:s'),
                        ];

                        $oco_id = $this->common->insertReg('dbEstoque', 'est_requisicao_produto_ocorrencia', $sql_oco);
                        session()->setFlashdata('msg', 'Ocorrência gravada com sucesso!');
                    }
                }
                // debug($sql_save, true);
                // ── Update primeiro ──────────────────────────────────────────────
                $atualizar = $this->requisicaoate->update($val->rpaid, $sql_save);
                if (! $atualizar) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao gravar a Conferência.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
            }
        }
        $db->transCommit();

        // ─── 3. PRODUTOS SEM INSPEÇÃO ────────────────────────────────────────────
        envia_msg_ws($this->data['controler'], "Finalizando produtos sem Inspeção", 'MsgServer', session()->get('usu_id'), 1);
        $produtosreq = $this->requisicao->getRequisicaoProdutos($postado['req_id']);
        $tipomov = $this->tipomovimentacao->getTipoMovimentacao($postado['tmo_id'])[0];

        $db->transBegin();
        foreach ($produtosreq as $val) {
            if (! in_array($val->rep_id, $aprovada)) {
                if (($val->cla_insvis == 'N' && $val->rpa_id != null) || $tipomov->tmo_exigeinspecao == 'N') {
                    // ── Update primeiro ──────────────────────────────────────────
                    $sql_save = [
                        'rpa_aprovada'      => -1,
                        'rpa_data_inspecao' => date('Y-m-d H:i:s'),
                    ];
                    // debug($sql_save, true);

                    // ── Movimento depois do update ───────────────────────────────
                    if ((int) $val->rpa_conferida > 0) {
                        if ($val->rpa_data_conferencia >= $dataagora) {
                            $movs = [[
                                'id'      => $postado['tmo_id'],
                                'qt'      => $val->rpa_conferida,
                                'msg'     => 'Conclusão na Conferência da Requisição Nº ' . $nreq,
                                'pro_id'  => $val->pro_id,
                                'rep_id'  => $val->rep_id,
                                'reserva' => "O",
                            ]];
                            envia_msg_ws($this->data['controler'], "Gerando Movimentos de Estoque", 'MsgServer', session()->get('usu_id'), 1);
                            $movim = geraMovimentoRequisicoes($movs, $this->data['controler']);
                            if ($movim['status'] === 'Erro') {
                                $ret['msg']  = $movim['mensagem'];
                                continue;
                            }
                        }
                    }
                    $this->requisicaoate->where('rpa_id', $val->rpa_id)->set($sql_save)->update();
                }
            }
        }
        $db->transCommit();


        // ─── 4. ATUALIZA STATUS DA REQUISIÇÃO ────────────────────────────────────
        if ($mudastatus) {
            envia_msg_ws($this->data['controler'], "Atualizando Status Requisição", 'MsgServer', session()->get('usu_id'), 1);
            $status    = 25;
            $nreq      = str_pad($postado['req_id'], 6, '0', STR_PAD_LEFT);
            $destNotif = 'Preproces/InspecaoProd';
            $msgsocket = "A Requisição Nº " . $nreq . " foi Conferida!";

            $pendencias = $this->requisicaoate->getRequisicaoPendencias($postado['req_id']);

            if ($pendencias[0]['pendente_conferencia'] > 0) {
                $status    = 24;
                $msgsocket = "A Requisição Nº " . $nreq . " foi Conferida Parcial!";
            } else {
                $insvis = retornaInsVis($postado['req_id']);
                if (! $insvis->temS || $insvis->temN || $tipomov->tmo_exigeinspecao == 'N') {
                    $status      = 5;
                    $destNotif   = 'Estoque\Requisicao';
                    $msgsocket   = "A Requisição Nº " . $nreq . " foi Concluída!";
                    $verOriginal = true;
                }
            }
            $db->transBegin();
            $salvareq = $this->requisicao->update($postado['req_id'], ['stt_id' => $status]);
            if (! $salvareq) {
                $ret['erro'] = true;
                $ret['msg']  = 'Erro ao gravar a Conferência.';
                $db->transRollback();
                echo json_encode($ret);
                return;
            }
            $db->transCommit();

            $notif = new Notifica();
            $notif->gravaNotifica($destNotif, session()->get('usu_id'), $postado['req_id'], $msgsocket, 'C');
        }

        // ─── 5. PROCESSA OCORRÊNCIAS ─────────────────────────────────────────────
        envia_msg_ws($this->data['controler'], "Processando Ocorrências", 'MsgServer', session()->get('usu_id'), 1);
        $res = service('ocorrenciaService')->gerarOcorrencias($produtosreq, $postado['req_id']);


        // ─── 6. COMMIT FINAL ─────────────────────────────────────────────────────
        $ret['erro'] = false;
        $ret['msg'] = 'Conferência gravada com sucesso!';
        $ret['url'] = site_url($this->data['controler']);
        session()->setFlashdata('msg', $ret['msg']);

        if ($verOriginal) {
            tratarOriginal($postado['req_id']);
        }

        echo json_encode($ret);
    }
}
