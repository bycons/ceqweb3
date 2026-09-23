<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Controllers\Notifica;
use App\DTOs\ProdutoMontado;
use App\Entities\Estoque\EntRequisicao;
use App\Libraries\MyCampo;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Traits\ForeignKeyUsageChecker;
use Config\Services;

class Requisicao extends BaseController
{
    use ForeignKeyUsageChecker;

    public $data = [];
    public $permissao = '';
    public $requisicao;
    public $requisicaoproduto;
    public $reqprodutoate;
    public $classes;
    public $produtos;
    public $tipomovimentacao;
    public $lote;
    public $busca;
    public $deposito;
    public $bt_envia;

    /**
     * Construtor da Classe
     * construct.
     */
    public function __construct()
    {
        $this->data = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->requisicao = new EstoquRequisicaoModel();
        $this->requisicaoproduto = new EstoquRequisicaoProdutoModel();
        $this->classes = new ProdutClasseModel();
        $this->produtos = new ProdutProdutoModel();
        $this->busca = new BuscasSapiens();
        $this->deposito = new EstoquDepositoModel();
        $this->lote = new ProdutLoteModel();
        $this->tipomovimentacao = new EstoquTipoMovimentacaoModel();
        $this->reqprodutoate = new EstoquRequisicaoProdutoAtendimentoModel();

        if ($this->data['erromsg'] != '') {
            $this->__erro();
        }
    }

    /**
     * Erro de Acesso
     * erro.
     */
    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * Tela de Abertura
     * index.
     */
    public function index()
    {
        $this->data['colunas'] = montaColunasLista($this->data, 'req_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }

    /**
     * Listagem
     * lista.
     *
     * @return void
     */
    public function lista()
    {
        // Monta colunas
        $campos = montaColunasCampos($this->data, 'req_id');
        // Busca requisições (AGORA retorna Entity)
        $dados_requis = $this->requisicao->getRequisicaoLista(false);
        // debug(count($dados_requis));
        // Filtra por perfil
        $dados_requis = filtrarPorPerfil($dados_requis);
        // Filtra por perfil do tipo de movimentação
        $dados_requis = filtrarPorPerfil($dados_requis, null, 'prf_id_tmo');
        // Base URL
        $base_url = base_url($this->data['controler']);
        // IDs para log (Entity -> array_map)
        $req_ids_assoc = array_map(
            fn($r) => $r->req_id,
            $dados_requis
        );

        $perfilId = session()->get('usu_perfil_id');
        // Busca logs
        $log = buscaLogTabelaFirst('est_requisicao', $req_ids_assoc);
        // debug($log);
        // Percorre entidades

        foreach ($dados_requis as $req) {
            if ($req->req_id) {
                $req->usu_nome = buscaUsuarioLog($log[$req->req_id]);

                $dataBanco = new \DateTime($req->req_data);
                $dataLimite = new \DateTime();
                $dataLimite->modify('-1 month');

                // URLs

                // Se não pode editar e se o usuário tem acesso a esse tipo de movimentação
                if (trim($req->stt_edicao) === 'N' && str_contains($req->prf_id_tmo, $perfilId) && $req->req_original == null) {
                    // Botão copiar (somente se dentro do prazo)
                    if ($dataBanco > $dataLimite) {
                        $url_cop = $base_url . '/copy/' . $req->req_id;

                        $bt_cop = new MyCampo();
                        $bt_cop->id = $bt_cop->nome = 'bt_copy';
                        $bt_cop->classep = 'btn btn-outline-primary btn-sm border-0 mx-0 fs-0';
                        $bt_cop->i_cone = "<i class='fas fa-copy'></i>";
                        $bt_cop->label = '';
                        $bt_cop->place = 'Copiar Requisição';
                        $bt_cop->funcChan = "redireciona('{$url_cop}',event)";
                        $btcop = $bt_cop->crBotao();
                        $req->acao_person[] = $btcop;
                    }
                }

                // se o usuário não tem acesso a esse tipo de movimentação não pode editar
                if (!str_contains($req->prf_id_tmo, $perfilId)) {
                    $req->stt_edicao = 'N';
                    $req->stt_exclusao = 'N';
                }
                // Botão imprimir
                if (trim($req->stt_impressao) === 'S') {
                    // $url_imp = base_url('/CriaPdf2025/PrintRequisicaoEstoq/' . $req->req_id);
                    $url_imp = base_url('/CriamPdf2026/PrintRequisicaoEstoq/' . $req->req_id);

                    $bt_prn = new MyCampo();
                    $bt_prn->id = $bt_prn->nome = 'bt_print';
                    $bt_prn->classep = 'btn btn-outline-dark btn-sm border-0 mx-0 fs-0';
                    $bt_prn->i_cone = "<i class='fa-solid fa-print'></i>";
                    $bt_prn->label = '';
                    $bt_prn->place = 'Imprimir Requisição';
                    $bt_prn->funcChan = "openPDFModal('{$url_imp}','Imprimir Requisição')";
                    $btprn = $bt_prn->crBotao();
                    $req->acao_person[] = $btprn;
                }
            }
        }

        // MOSTRA CONSULTA EM TODOS OS STATUS
        $this->data['allconsulta'] = true;
        // Retorno para DataTables
        $requis = ['data' => montaListaColunasEnt($this->data, 'req_id', $dados_requis, $campos[1])];

        // Cache
        cache()->save('requis', $requis, 60000);

        // JSON final
        echo json_encode($requis);
    }

    /**
     * Inclusão
     * add.
     *
     * @return void
     */
    public function add()
    {
        $ent = new EntRequisicao();
        $fields = $ent->defCampos();

        $secao[0] = 'Dados Gerais';

        $campos[0][] = $fields['req_id'];
        $campos[0][] = $fields['req_data'];
        $campos[0][] = $fields['req_dataentrega'];
        $campos[0][] = $fields['tmo_id'];
        $campos[0][] = $fields['req_repetedias'];
        $campos[0][] = $fields['req_deporigem'];
        $campos[0][] = $fields['req_deppadrao'];
        $campos[0][] = $fields['req_depdestino'];
        $campos[0][] = "<div class='row col-12'>";
        $campos[0][] = $fields['req_consdiaanterior'];
        $campos[0][] = $fields['req_medconsumodias'];
        $campos[0][] = $fields['req_meddias'];
        $campos[0][] = $fields['req_percseguranca'];
        $campos[0][] = '</div>';
        $campos[0][] = $fields['pro_id'];
        $campos[0][] = $fields['req_observacao'];
        $campos[0][] = $fields['bt_carregar'];

        $secao[1] = 'Produtos';
        $campos[1][0] = '';

        $envr = new MyCampo();
        $envr->nome = 'bt_envia';
        $envr->id = 'bt_envia';
        $envr->i_cone = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">
                            <i class="fa-regular fa-paper-plane" style="font-size: 2rem;" aria-hidden="true"></i></div>';
        $envr->i_cone .= '<div class="align-items-start txt-bt-manut">Enviar Requisição</div>';
        $envr->place = 'Enviar Requisição';
        $envr->funcChan = 'enviarRequisicoes(event, 1)';
        $envr->classep = 'btn-success bt-manut btn-sm mb-2 float-end';
        $this->bt_envia = $envr->crBotao();

        $this->data['botao'] = $this->bt_envia;
        $this->data['title'] = 'Requisição';
        $this->data['secoes'] = $secao;
        $this->data['campos'] = $campos;
        $this->data['destino'] = 'store';
        $this->data['scripts'] = 'my_requisicao';

        $this->data['script'] = "<script>mostraOcultaCampo('req_consdiaanterior', 'N', 'req_medconsumodias,req_meddias');mudaCheck2opcoes('req_consdiaanterior', 'req_medconsumodias');atualizarEstadoBotaoSalvar();</script>";

        echo view('vw_edicao', $this->data);
    }

    /**
     * Copiar Etiquetas
     * copy.
     *
     * @return void
     */
    public function copy($id)
    {
        $requisicao = $this->requisicao->getRequisicao($id);
        if (!$requisicao) {
            $ret['erro'] = true;
            $ret['msg'] = 41; // ou código personalizado, se preferir
            session()->setFlashdata('msg', 41);

            return redirect()->to(site_url($this->data['controler']));
        }
        $requisicao = $requisicao[0];

        $tmo_id = $requisicao->tmo_id;
        $tipos_mov = $this->tipomovimentacao->getTipoMovimentacaoParaRequisicao($tmo_id);
        $tipos_mov = filtrarPorPerfil($tipos_mov);
        if (count($tipos_mov) == 0) {
            $ret['erro'] = true;
            $ret['msg'] = 36; // ou código personalizado, se preferir
            session()->setFlashdata('msg', 36);

            return redirect()->to(site_url($this->data['controler']));
        } else {
            $requisicao->req_data = date('Y-m-d H:i:s');
            $requisicao->req_dataentrega = '';

            // Montar campos como no add()
            $ent = new EntRequisicao((array) $requisicao, true, 'copy');
            $fields = $ent->campos;

            $secao[0] = 'Dados Gerais';
            $campos[0][] = $fields['req_id'];
            $campos[0][] = $fields['req_data'];
            $campos[0][] = $fields['req_dataentrega'];
            $campos[0][] = $fields['tmo_id'];
            $campos[0][] = $fields['req_repetedias'];
            $campos[0][] = $fields['req_deporigem'];
            $campos[0][] = $fields['req_deppadrao'];
            $campos[0][] = $fields['req_depdestino'];
            $campos[0][] = $fields['req_consdiaanterior'];
            $campos[0][] = $fields['req_medconsumodias'];
            $campos[0][] = $fields['req_meddias'];
            $campos[0][] = $fields['req_percseguranca'];
            // $campos[0][] = $fields['pro_id'];
            $campos[0][] = $fields['req_observacao'];
            $campos[0][] = "<div class='d-none'>{$fields['bt_carregar']}</div>";

            $secao[1] = 'Produtos';
            $campos[1][0] = ''; // mesma estrutura do add()

            $envr = new MyCampo();
            $envr->nome = 'bt_envia';
            $envr->id = 'bt_envia';
            $envr->i_cone = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">
                                <i class="fa-regular fa-paper-plane" style="font-size: 2rem;" aria-hidden="true"></i></div>';
            $envr->i_cone .= '<div class="align-items-start txt-bt-manut">Enviar Requisição</div>';
            $envr->place = 'Enviar Requisição';
            $envr->funcChan = 'enviarRequisicoes(event, 1)';
            $envr->classep = 'btn-success bt-manut btn-sm mb-2 float-end';
            $this->bt_envia = $envr->crBotao();

            $this->data['botao'] = $this->bt_envia;

            $this->data['metodo'] = ' ';
            // $this->data['title']         = '';
            $this->data['desc_metodo'] = ' Cópia de ';
            $this->data['desc_edicao'] = ' Req Original: ' . str_pad($id, 6, '0', STR_PAD_LEFT);
            $this->data['secoes'] = $secao;
            $this->data['campos'] = $campos;
            $this->data['destino'] = 'store'; // ou 'update' se você for criar
            $this->data['scripts'] = 'my_requisicao';
            $this->data['script'] = "<script>
                                            jQuery('#bt_carregar').trigger('click');
                                            mostraOcultaCampo('req_consdiaanterior', 'N', 'req_medconsumodias,req_meddias');
                                            mudaCheck2opcoes('req_consdiaanterior', 'req_medconsumodias');
                                            jQuery('#req_id').val('');
                                            jQuery('#form1').attr('data-alter', true);</script>";

            echo view('vw_edicao', $this->data);
        }
    }

    /**
     * Consulta
     * show.
     *
     * @return void
     */

    public function show($id)
    {
        $requisicao = $this->requisicao->getRequisicao($id);

        if (!$requisicao) {
            return redirectWithError(previous_url(), 41);
        }
        $requisicao = $requisicao[0];

        $log = buscaLog('est_requisicao', $id);
        $requisicao->usu_nome = $log['usua_alterou'] ?? '';

        $secao[0] = 'Dados Gerais';
        $campos[0] = $this->showCabecalho($requisicao);

        $produtosreq = $this->requisicao->getRequisicaoProdutos($id);

        $colunas = [
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote <br>Validade',
            'Saldo <br>Origem',
            'Caixas',
            'Multiplica',
            '% Seg',
            'Requisitado',
            'Cancelado',
            'Atendido',
            'Data Atend.',
            'Conferido',
            'Data Confer.',
            'Aprovado',
            'Data Inspec.',
        ];

        $alinha = [
            'center',
            'start',
            'start',
            'center',
            'end',
            'end',
            'end',
            'end',
            'end',
            'end',
            'end',
            'center',
            'end',
            'center',
            'end',
            'center',
        ];

        $produtos = [$id];

        if (count($produtosreq) > 0) {

            envia_msg_ws(
                $this->data['controler'],
                "Buscando estoque de origem " . $requisicao->req_deporigem,
                'MsgServer',
                session()->get('usu_id'),
                1
            );

            $estoqueOrigem = indexarEstoque(
                $this->busca->buscaEstoqueDeposito($requisicao->req_deporigem)
            );

            foreach ($produtosreq as $prod) {
                $prod = (object) $prod;

                $lote = trim($prod->lot_lote ?? '') ?: 'Sem Lote';
                $prod->lot_lote = $lote;

                $estoqProd = $estoqueOrigem[$prod->pro_codpro][$lote][0] ?? null;
                $prod->estoque_origem = $estoqProd
                    ? (int) str_replace('.', '', (string) $estoqProd->quantidadeEstoque)
                    : 0;

                [$conferida, $dataConferencia] = $this->normalizaCampoAtendimento(
                    $prod->rpa_conferida,
                    $prod->rpa_data_conferencia
                );

                [$aprovada, $dataInspecao] = $this->normalizaCampoAtendimento(
                    $prod->rpa_aprovada,
                    $prod->rpa_data_inspecao
                );

                $atendida = ($prod->rpa_atendida == 0 && $prod->rpa_data === null)
                    ? ''
                    : ($prod->rpa_atendida ?? null);

                $produtos[] = [
                    $prod->rep_id            ?? null,
                    $prod->pro_codpro        ?? null,
                    $prod->pro_despro        ?? null,
                    $prod->fab_apeFab        ?? null,
                    $lote . '<br>' . (isset($prod->lot_validade) ? data_br($prod->lot_validade) : ''),
                    $prod->estoque_origem,
                    $prod->qtd_caixa         ?? null,
                    $prod->rep_multiplicador ?? null,
                    isset($prod->rep_seguranca) ? $prod->rep_seguranca . '%' : '',
                    $prod->rep_quantia       ?? null,
                    $prod->rpa_cancelada     ?? null,
                    $atendida,
                    data_br($prod->rpa_data) ?? null,
                    $conferida,
                    $dataConferencia,
                    $aprovada,
                    $dataInspecao,
                    ($prod->rep_quantia ?? 0) - (($prod->rpa_atendida ?? 0) + ($prod->rpa_cancelada ?? 0)),
                ];
            }
        }

        $data = [
            'show'     => true,
            'colunas'  => $colunas,
            'alinha'   => $alinha,
            'produtos' => $produtos,
        ];

        $dispositivo = Services::device();
        if ($dispositivo->isMobile()) {
            $data['maxHeig'] = '70vh';
            $secao[1]    = 'Produtos';
            $campos[1][] = view('partials/pw_show_produtos_req', $data);
        } else {
            $data['maxHeig'] = '49vh';
            $campos[0][]     = view('partials/pw_show_produtos_req', $data);
        }

        $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT)
            . ' ' . fmtEtiquetaCor($requisicao->stt_cor, $requisicao->stt_nome, 1);
        $this->data['secoes'] = $secao;
        $this->data['campos'] = $campos;
        $this->data['destino'] = '';
        $this->data['scripts'] = 'my_requisicao';

        echo view('vw_edicao', $this->data);
    }

    private function normalizaCampoAtendimento($valor, $data): array
    {
        if ($valor < 0) {
            return ['NA', 'NA'];
        }

        $valorNorm = ($valor == 0 && $data === null) ? '' : $valor;
        $dataNorm  = ($data === null) ? null : data_br($data);

        return [$valorNorm, $dataNorm];
    }

    public function showCabecalho($requisicao)
    {
        $ent = new EntRequisicao((array) $requisicao, true);
        $fields = $ent->campos;

        $campos[] = $fields['req_id'];
        $campos[] = $fields['req_data'];
        $campos[] = $fields['req_dataentrega'];
        $campos[] = $fields['tmo_id'];
        $campos[] = $fields['req_depdestino'];
        $campos[] = $fields['req_consdiaanterior'];
        $campos[] = $fields['req_percseguranca'];

        if ($requisicao->req_consdiaanterior == 'N') {
            $campos[] = $fields['req_medconsumodias'];
            $campos[] = $fields['req_meddias'];
        }

        $campos[] = $fields['usu_nome'];
        $campos[] = $fields['req_observacao'];

        return $campos;
    }

    public function showCabecalhoSimples($requisicao)
    {
        $ent = new EntRequisicao((array) $requisicao, true);
        $fields = $ent->campos;

        $campos[] = $fields['req_id'];
        $campos[] = $fields['req_data'];
        $campos[] = $fields['req_dataentrega'];
        $campos[] = $fields['tmo_id'];
        $campos[] = $fields['req_depdestino'];
        $campos[] = $fields['usu_nome'];
        $campos[] = $fields['req_observacao'];

        return $campos;
    }

    /**
     * Edição
     * edit.
     *
     * @return void
     */
    public function edit($id)
    {
        // debug($id);
        $requis = $this->requisicao->getRequisicao($id);

        if (!$requis) {
            return redirectWithError($this->data['controler'], 41);

            // return view('errors/vw_semregistro', [
            //     'mensagem' => 'Requisição não encontrada'
            // ]);
        }

        // debug((array) $requis);
        // Montar campos como no add()
        $ent = new EntRequisicao((array) $requis[0], true);

        $fields = $ent->campos;

        $secao[0] = 'Dados Gerais';
        $campos[0][] = $fields['req_id'];
        $campos[0][] = $fields['req_data'];
        $campos[0][] = $fields['req_dataentrega'];
        $campos[0][] = $fields['tmo_id'];
        $campos[0][] = $fields['req_repetedias'];
        $campos[0][] = $fields['req_deporigem'];
        $campos[0][] = $fields['req_deppadrao'];
        $campos[0][] = $fields['req_depdestino'];
        $campos[0][] = $fields['req_consdiaanterior'];
        $campos[0][] = $fields['req_medconsumodias'];
        $campos[0][] = $fields['req_meddias'];
        $campos[0][] = $fields['req_percseguranca'];
        // $campos[0][] = $fields['pro_id'];
        $campos[0][] = $fields['req_observacao'];
        $campos[0][] = "<div class='d-none'>{$fields['bt_carregar']}</div>";

        $secao[1] = 'Produtos';
        $campos[1][0] = ''; // mesma estrutura do add()

        $envr = new MyCampo();
        $envr->nome = 'bt_envia';
        $envr->id = 'bt_envia';
        $envr->i_cone = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">
                            <i class="fa-regular fa-paper-plane" style="font-size: 2rem;" aria-hidden="true"></i></div>';
        $envr->i_cone .= '<div class="align-items-start txt-bt-manut">Enviar Requisição</div>';
        $envr->place = 'Enviar Requisição';
        $envr->funcChan = 'enviarRequisicoes(event, 1)';
        $envr->classep = 'btn-success bt-manut btn-sm mb-2 float-end';
        $this->bt_envia = $envr->crBotao();

        $this->data['botao'] = $this->bt_envia;

        // $this->data['title']     = ' Requisição No. ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['desc_edicao'] = 'Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['secoes'] = $secao;
        $this->data['campos'] = $campos;
        $this->data['destino'] = 'store'; // ou 'update' se você for criar
        $this->data['scripts'] = 'my_requisicao';
        $this->data['script'] = "<script>
            jQuery('#bt_carregar').trigger('click');
            mostraOcultaCampo('req_consdiaanterior', 'N', 'req_medconsumodias,req_meddias');
            mudaCheck2opcoes('req_consdiaanterior', 'req_medconsumodias');
        </script>";

        echo view('vw_edicao', $this->data);
    }

    /**
     * Exclusão
     * delete.
     *
     * @return void
     */
    public function delete($id)
    {
        $ret = [];

        $requis = $this->requisicao->getRequisicao($id);

        if (!$requis) {
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
            $this->requisicaoproduto->where('req_id', $id)->delete();
            $ret['erro'] = false;
            $ret['msg'] = 'Requisição Excluída com Sucesso';
            session()->setFlashdata('msg', 'Requisição Excluída com Sucesso');
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg'] = 3;
        }
        // } else {
        //     $ret['erro'] = true;
        //     $ret['msg']  = 3;
        // }

        echo json_encode($ret);
    }

    /**
     * Carga de Produtos da Requisição
     * produtos.
     *
     * @return void
     */
    public function produtos()
    {
        $req = (object) request()->getVar();
        // debug($req, true);
        // === Parâmetros recebidos ===
        $reqid = trim($req->reqid ?? '');
        $proid = $req->proid ?? '';
        $deporigem = trim($req->deporigem ?? '');
        $depdestino = trim($req->depdestino ?? '');
        $tipomovim = trim($req->tipomovim ?? '');
        $multiplica = trim($req->multiplica ?? '');
        $diaanterior = trim($req->diaanterior ?? '');
        $mediaconsumo = trim($req->mediaconsumo ?? '');
        $pct_seguranca = trim($req->seguranca ?? '');
        $meddias = trim($req->meddias ?? '');

        // PRODUTOS JÁ EXISTENTES NA REQ
        $prodreq = [];

        if ($reqid !== '') {
            $prodreq = $this->requisicaoproduto->getRequisicaoProdutos($reqid);
            // debug($prodreq, true);

            // indexa por pro_id + lote
            $produtosIndexado = [];
            foreach ($prodreq as $p) {
                $produtosIndexado[$p['pro_codpro']][$p['lot_lote']] = $p;
            }
            // debug($produtosIndexado, true);
        }

        // RETORNO BASE
        $ret = (object) [
            'deporigem' => $deporigem,
            'depdestino' => $depdestino,
            'deppadrao' => '',
            'diaanterior' => $diaanterior,
            'mediaconsumo' => $mediaconsumo,
            'meddias' => $meddias,
            'classe' => [],
        ];

        envia_msg_ws($this->data['controler'], 'Buscando Transação', 'MsgServer', session()->get('usu_id'), 1);

        $tipomov = (new EstoquTipoMovimentacaoModel())
            ->getTipoMovimentacao($tipomovim)[0];

        envia_msg_ws($this->data['controler'], 'Carregando produtos', 'MsgServer', session()->get('usu_id'), 1);

        // debug($proid, true);
        $listaProdutos = $this->produtos->getProdutoRequisicao(
            $depdestino,
            $proid ?? false
        );
        // debug($listaProdutos, true);

        // ═══════════════════════════════════════════════════════════════════
        // TEMPORÁRIO (2026-07-14): busca real de saldo via SOAP desabilitada.
        // Atribui saldo fictício de 10000 para todos os produtos.
        // Reverter removendo este bloco e descomentando o original abaixo.
        // ═══════════════════════════════════════════════════════════════════
        // $montaSaldoFicticioTemp = function () use ($listaProdutos): array {
        //     $fake = [];
        //     foreach ($listaProdutos as $p) {
        //         $lote = trim($p->lot_lote ?? '');
        //         $fake[] = (object) [
        //             'codigoProduto'     => $p->pro_codpro,
        //             'codigoLote'        => $lote !== '' ? $lote : 'SEM_LOTE',
        //             'quantidadeEstoque' => 10000,
        //         ];
        //     }
        //     return $fake;
        // };

        // ESTOQUES
        envia_msg_ws($this->data['controler'], 'Buscando estoque de origem', 'MsgServer', session()->get('usu_id'), 1);
        $estorig = (array) $this->busca->buscaEstoqueDeposito($deporigem) ?? [];
        // $estorig = $montaSaldoFicticioTemp();
        // debug($estorig, true);
        // $estorig = [$estorig];
        $estoqueOrigem = indexarEstoque($estorig);
        // debug($estoqueOrigem, true);

        envia_msg_ws($this->data['controler'], 'Buscando estoque de destino', 'MsgServer', session()->get('usu_id'), 1);
        $estdest = (array) $this->busca->buscaEstoqueDeposito($depdestino) ?? [];
        // $estdest = $montaSaldoFicticioTemp();
        // $estdest = [$estdest];
        $estoqueDestino = indexarEstoque($estdest);
        // debug($estoqueDestino, true);
        $estoquePadrao = [];

        // debug($tipomov);
        if (!empty($tipomov->tmo_estoquepadrao) && strtoupper($tipomov->tmo_estoquepadrao) === 'S') {
            envia_msg_ws($this->data['controler'], 'Obtendo depósito padrão', 'MsgServer', session()->get('usu_id'), 1);
            $depPadraoObj = $this->deposito->getDepositoPadrao()[0];
            $deppadrao = $depPadraoObj->dep_codDep ?? '';
            // debug($deppadrao);

            if ($deppadrao && $deppadrao !== $deporigem && $deppadrao !== $depdestino) {
                $ret->deppadrao = $deppadrao;
                envia_msg_ws($this->data['controler'], 'Buscando estoque padrão', 'MsgServer', session()->get('usu_id'), 1);
                $estoquePadrao = indexarEstoque(
                    (array) $this->busca->buscaEstoqueDeposito($deppadrao) ?? []
                );
                // $estoquePadrao = indexarEstoque($estoquePadrao);
                // debug($estoquePadrao, true);
            } else {
                envia_msg_ws($this->data['controler'], 'Depósito padrão inválido (igual à origem ou destino)', 'MsgServer', session()->get('usu_id'), 1);
            }
        }

        // CONSUMO
        $dias = ($mediaconsumo === 'S' && $meddias > 0) ? $meddias : 1;
        $inicio = date('Y-m-d', strtotime("-{$dias} days"));
        $ontem = date('Y-m-d', strtotime('-1 day'));
        envia_msg_ws($this->data['controler'], "Consumo de {$inicio} até {$ontem}", 'MsgServer', session()->get('usu_id'), 1);

        $consumo = [
            'insumos' => [],
            'equipos' => [],
            'bolsas' => [],
        ];

        foreach (
            [
                'insumos' => 'api_tot_insumos.php',
                'equipos' => 'api_tot_equipo.php',
                'bolsas' => 'api_tot_bolsa.php',
            ] as $tipo => $endpoint
        ) {
            envia_msg_ws($this->data['controler'], 'Buscando ' . ucfirst($tipo), 'MsgServer', session()->get('usu_id'), 1);

            $res = api_request(
                LINK_CEQWEB2 . $endpoint,
                ['inicio' => $inicio, 'final' => $ontem],
                'get'
            );

            if ($res) {
                // $consumo[$tipo] = indexarConsumo($res);
                $consumo[$tipo] = array_column($res, null, 'codigo_erp');
            }
        }

        // AGRUPAR PRODUTOS POR CLASSE
        $classesTemp = [];
        $produtoAnterior = [];
        $codproant = '';
        // debug($estoquePadrao);
        foreach ($listaProdutos as $prod) {
            $codPro = $prod->pro_codpro;
            $lotePro = trim($prod->lot_lote);
            $claId = $prod->cla_id;
            $claNome = $prod->cla_nome;

            $keyLote = $lotePro === 'Sem Lote' ? '' : $lotePro;
            // debug($codPro);
            // debug($keyLote);
            $produtoreq = $produtosIndexado[$codPro][$keyLote] ?? [];
            // if ($codPro === 'MP046' && $produtoreq) {
            //     debug($produtoreq, true);
            // }

            $ori = $estoqueOrigem[$codPro][trim($lotePro)] ?? [];
            // debug($ori);
            $des = $estoqueDestino[$codPro][$lotePro] ?? [];
            $pad = $ret->deppadrao !== '' ? ($estoquePadrao[$codPro][$lotePro] ?? []) : [];
            // debug($pad, true);

            // converte produto base para ARRAY apenas para cálculo
            $prodArr = (array) $prod;

            $prodArr['pro_consumo'] = 0;
            $prodArr['tmo_gestaoestoque'] = $tipomov->tmo_estoquepadrao;
            $prodArr['pro_multiplica'] = $produtoreq['rep_multiplicador'] ?? 1;
            $prodArr['pct_seguranca'] = $produtoreq['rep_seguranca'] ?? $pct_seguranca;
            $prodArr['pro_seguranca'] = 0;
            $prodArr['pro_meddias'] = $meddias;
            $prodArr['pro_sugestao'] = 0;
            $prodArr['pro_diaanterior'] = $diaanterior;
            $prodArr['pro_mediaconsumo'] = $mediaconsumo;
            $prodArr['pro_requisicao'] = $produtoreq['rep_quantia'] ?? 0;
            $prodArr['pro_primeiro'] = 1; // sempre é o primeiro lote do produtos

            // SE O PRODUTO ANTERIOR ERA O MESMO DO ATUAL E FICARIA NEGATIVO DESCONTANDO O CONSUMO,
            // DESCONTA O RESTANTE DO CONSUMO DO PRODUTO ATUAL
            if ($codPro === $codproant) {
                $prodArr['pro_primeiro'] = 2; // não é mais o primeiiro lote do produto
                if (isset($produtoAnterior['pro_consumo_proximo']) && (int) $produtoAnterior['pro_consumo_proximo'] > 0) {
                    $prodArr['pro_consumo_proximo'] = $produtoAnterior['pro_consumo_proximo'];
                } else {
                    $prodArr['pro_consumo_proximo'] = 0;
                }
            }
            // if ($codPro === 'MP046' && $prodArr && $produtoreq) {
            //     debug($produtoreq, true);
            // }
            $proEstDispon = ($ori[0]->quantidadeEstoque ?? 0) + ($pad[0]->quantidadeEstoque ?? 0);
            $proEstDestin = ($des[0]->quantidadeEstoque ?? 0);
            // debug($proEstDispon);
            // debug($proEstDestin);
            if ((int) $proEstDispon <= 0 && (int) $proEstDestin <= 0) {
                // se não tem saldo nem na Origem nem no Destino, pula
                continue;
            }
            if ($codPro === 'MP046' && $produtoreq) {
                // debug($prodArr);
            }
            $produtoFinal = $this->montarProduto(
                $prodArr,
                $ori,
                $des,
                $pad,
                $consumo
            );

            if (count($produtoFinal) > 0) {
                // debug('achou');
                // debug($produtoFinal);
                $produtoAnterior = $produtoFinal;
                // debug($produtoFinal['pro_codpro'] . ' ' . $produtoFinal['lot_lote'] . ' ' . $produtoFinal['pro_consumo']);
                // debug('Produto Anterior');
                $codproant = $codPro;
                if (!isset($classesTemp[$claId])) {
                    $classesTemp[$claId] = (object) [
                        'id' => $claId,
                        'nome' => $claNome,
                        'prod' => [],
                    ];
                }

                $classesTemp[$claId]->prod[] = $produtoFinal;
            }
        }

        $ret->classe = array_values($classesTemp);

        echo json_encode($ret);
    }

    public function buscarProdutoReq(array $dados, string $codpro, string $lot_pro): array
    {
        // debug('Prod procurado '.$codpro.' Lote procurado '.$lot_pro);
        foreach ($dados as $item) {
            // debug('Prod '.$item['pro_codpro'].' Lote '.$item['lot_lote']);
            if ($item['pro_codpro'] == $codpro) {
                if ($lot_pro === '' || trim($item['lot_lote']) == trim($lot_pro)) {
                    return $item;
                }
            }
        }

        return [];
    }



    private function montarProduto(array $prod, array $ori, array $des, array $padr, array $consumo): array
    {
        // if ($prod['pre_mindiaanterior'] != 'S') {
        // debug($prod);
        // }
        $produto = new ProdutoMontado($prod);

        // === Preencher lote de origem ===
        if ($oriItem = $ori[0] ?? null) {
            $produto->loteori->lote_origem = $oriItem->codigoLote ?? '';
            $produto->loteori->validade_origem = $oriItem->validade ?? '';
            $produto->loteori->pro_estorigem = $oriItem->quantidadeEstoque ?? 0;
        }

        // === Preencher lote de destino ===
        if ($desItem = $des[0] ?? null) {
            $produto->lotedes->lote_destino = $desItem->codigoLote ?? '';
            $produto->lotedes->validade_destino = $desItem->validade ?? '';
            $produto->lotedes->pro_estdestino = $desItem->quantidadeEstoque ?? 0;
        }

        // === Preencher lote padrão ===
        foreach ($padr as $item) {
            if ($item->quantidadeEstoque > 0) {
                $produto->lotepad->lote_padrao = $item->codigoLote ?? '';
                $produto->lotepad->validade_padrao = $item->validade ?? '';
                $produto->lotepad->pro_estpadrao = $item->quantidadeEstoque ?? 0;
                break;
            }
        }

        // === Validação de estoque origem/padrão ===
        $proEstDestin = $produto->lotedes->pro_estdestino;
        $proEstOrigem = $produto->loteori->pro_estorigem;
        $proEstPadrao = $produto->lotepad->pro_estpadrao;

        // debug($consumo);
        $proEstDispon = $proEstPadrao + $proEstOrigem;
        // debug($proEstDispon);
        if ((int) $proEstDispon <= 0 && (int) $proEstDestin <= 0) {
            // debug($produto);
            // se não tem saldo nem na Origem nem no Destino, pula
            return [];
        }

        // === Verifica se deve calcular consumo ===
        if ($prod['pro_diaanterior'] === 'S' || $prod['pro_mediaconsumo'] === 'S') {
            // debug($prod);
            $dash = strtolower($prod['cla_dash_consumo']);
            $codPro = trim($prod['pro_codpro']);
            // === Buscar consumo ===
            if ($prod['tmo_gestaoestoque'] == 'S' && $prod['pre_gestaoestoque'] === 'S' && !empty($dash)) {
                // debug($consumo[$dash][$codPro]);
                $produto->pro_consumo_medio = $consumo[$dash][$codPro]['media'] ?? 0;
                $produto->pro_consumo_diaant = $consumo[$dash][$codPro]['consumido'] ?? 0;
            } else {
                $produto->pro_consumo_medio = 0;
                $produto->pro_consumo_diaant = 0;
            }

            // === Base de consumo ===
            // debug($prod);
            if ($prod['pro_primeiro'] === 1) {
                $produto->pro_consumo = ($prod['pro_meddias'] > 0)
                    ? $produto->pro_consumo_medio
                    : $produto->pro_consumo_diaant;
                $produto->pro_consumo_proximo = 0;
            } else {
                // debug('Não sou o Primeiro ' . $codPro);
                $produto->pro_consumo = $prod['pro_consumo'];
                $produto->pro_consumo_proximo = $prod['pro_consumo_proximo'];
            }
            // === Ajuste no estoque de destino (condicional) ===
            if ($prod['tmo_gestaoestoque'] == 'S' && $prod['pre_gestaoestoque'] === 'S' && $prod['pre_estdataatual'] === 'N') {
                if ($prod['pro_primeiro'] === 1) {
                    $estdestino = $produto->lotedes->pro_estdestino - $produto->pro_consumo;
                } else {
                    $estdestino = $produto->lotedes->pro_estdestino - $produto->pro_consumo_proximo;
                }
                // debug('EstDestino ' . $estdestino);

                if ($estdestino < 0) {
                    $produto->pro_consumo_proximo = abs($estdestino); // transforma em positivo
                    $produto->lotedes->pro_estdestino = 0;
                } else {
                    $produto->lotedes->pro_estdestino = $estdestino;
                    $produto->pro_consumo_proximo = 0;
                }
            }
            // debug($produto->pro_consumo);

            // === Segurança e sugestão bruta (sempre calculadas) ===
            $produto->pro_seguranca = ceil($produto->pro_consumo * ($prod['pct_seguranca'] / 100));
            $sugestaoBruta = ($produto->pro_consumo + $produto->pro_seguranca) - $produto->lotedes->pro_estdestino;

            // === Aplicar faixa de mínimo e máximo (somente se gestão de estoque) ===
            if ($prod['tmo_gestaoestoque'] == 'S' && $prod['pre_gestaoestoque'] === 'S') {
                $produto->pro_minimo = ($prod['pre_mindiaanterior'] === 'S')
                    ? $produto->pro_consumo_diaant
                    : $prod['pre_minimo'];
                $produto->pro_consumo = ($prod['pre_mindiaanterior'] === 'S')
                    ? $produto->pro_consumo
                    : $prod['pre_sugerida'];

                $produto->pro_seguranca = ceil(floatval($produto->pro_consumo * ($prod['pct_seguranca'] / 100)));

                // debug('Produto ' . $produto->pro_codpro);
                // debug('Consumo ' . $produto->pro_consumo);
                // debug(('Segurança ' . $produto->pro_seguranca));
                // debug('Est Destino ' . $produto->lotedes->pro_estdestino);
                $sugestaoBruta = $produto->pro_consumo + $produto->pro_seguranca - $produto->lotedes->pro_estdestino;
                // debug('Sugest Bruta ' . $sugestaoBruta);
                if ($prod['pre_maxdiaanterior'] === 'S') {
                    $percentual = floatval($prod['pre_porcmaximo']) / 100;
                    $produto->pro_maximo = ceil($produto->pro_consumo * (1 + $percentual));
                } else {
                    $produto->pro_maximo = $prod['pre_maximo'];
                }
                // === Definir sugestão respeitando faixa
                if ($sugestaoBruta <= 0) {
                    // debug('Sugestão Menor ou igual a 0');
                    $produto->pro_sugestao = 0;
                } elseif ($produto->pro_minimo == 0 && $produto->pro_maximo == 0) {
                    // debug('Mínimo e Maximo = 0');
                    $produto->pro_sugestao = $sugestaoBruta;
                } elseif ($produto->pro_minimo > $produto->pro_maximo) {
                    // debug('Mínimo > Maximo');
                    $produto->pro_sugestao = min($sugestaoBruta, $produto->pro_maximo);
                } else {
                    $minimo = $produto->pro_minimo - $produto->lotedes->pro_estdestino;
                    $maximo = $produto->pro_maximo - $produto->lotedes->pro_estdestino;
                    $produto->pro_sugestao = max(
                        0, // Nunca menor que 0
                        min(
                            $maximo,                     // Nunca maior que o máximo
                            max($minimo, $sugestaoBruta) // Nunca menor que o mínimo
                        )
                    );
                }
            } else {
                // === Sem gestão de estoque: usar sugestão bruta diretamente
                $sugestaoBruta = ($produto->pro_consumo + $produto->pro_seguranca) - $produto->lotedes->pro_estdestino;
                $produto->pro_minimo = 0;
                $produto->pro_maximo = 0;
                $produto->pro_sugestao = max(0, $sugestaoBruta);
            }

            $produto->pro_sugestao = min($proEstDispon, $produto->pro_sugestao);
            // $produto->pro_requisicao = $produtoreq['rep_quantia'] ?? 0;
        } else {
        }
        // debug('REQUISIÇÃO ' . $produto->pro_requisicao);

        return $produto->toArray();
    }


    public function store()
    {
        $postado     = $this->request->getPost();
        // debug($postado, true);
        $ret['erro'] = false;
        $requisicoes = json_decode($postado['json_requisicoes'], true);

        $db = \Config\Database::connect();
        $db->transBegin();

        // ─── PARÂMETROS INICIAIS ─────────────────────────────────────────────────
        $status      = ($postado['req_status'] ?? 0) == 1 ? 4 : 6;
        $hoje        = new \DateTime();
        $dataEntrega = \DateTime::createFromFormat('Y-m-d', $postado['req_dataentrega']);
        $repete      = (int) $postado['req_repetedias'];
        $vezes       = ($status == 4) ? max(1, $repete + 1) : 1;
        if ($status == 4) {
            $repete = 0;
        }

        // ─── PRÉ-CARREGA TIPO DE MOVIMENTAÇÃO E DEPÓSITO PADRÃO ─────────────────
        $tipomov    = null;
        $tmo_id_pad = null;

        if ($status == 4) {
            $tipomov = $this->tipomovimentacao->getTipoMovimentacao($postado['tmo_id'])[0];

            if (!empty($postado['req_deppadrao']) && $postado['req_deppadrao'] != $postado['req_deporigem']) {
                $tmos = $this->tipomovimentacao->getTipoMovimentacaoDepositos(
                    $postado['req_deppadrao'],
                    $postado['req_deporigem']
                );
                if (empty($tmos)) {
                    $ret['erro'] = true;
                    $ret['msg']  = "Não existe Tipo de Movimentação: {$postado['req_deppadrao']} X {$postado['req_deporigem']}";
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
                $tmo_id_pad = $tmos[0]->tmo_id;
            }
        }

        // ─── PRÉ-CARREGA PRODUTOS E LOTES EM MEMÓRIA ────────────────────────────
        envia_msg_ws($this->data['controler'], 'Preparando Produtos da Requisição', 'MsgServer', session()->get('usu_id'), 1);

        [$produtosIndexados, $lotesIndexados] = $this->preCarregarProdutosLotes($requisicoes);

        // ─── VALIDA TODOS OS PRODUTOS E LOTES ANTES DE GRAVAR ───────────────────
        $erroValidacao = $this->validarProdutosLotes($requisicoes, $produtosIndexados, $lotesIndexados);
        if ($erroValidacao !== null) {
            $ret['erro'] = true;
            $ret['msg']  = $erroValidacao;
            $db->transRollback();
            echo json_encode($ret);
            return;
        }

        // ─── LOOP POR DIAS DE REPETIÇÃO ──────────────────────────────────────────
        envia_msg_ws($this->data['controler'], 'Preparando Gravação da Requisição', 'MsgServer', session()->get('usu_id'), 1);

        for ($i = 0; $i < $vezes; $i++) {
            if ($i > 0) {
                $dataEntrega->modify('+1 day');
            }

            // ── Grava ou atualiza a requisição ───────────────────────────────────
            $dadosReq = [
                'req_data'            => $hoje->format('Y-m-d H:i:s'),
                'req_dataentrega'     => $dataEntrega->format('Y-m-d'),
                'tmo_id'              => $postado['tmo_id'],
                'req_deporigem'       => $postado['req_deporigem'],
                'req_depdestino'      => $postado['req_depdestino'],
                'req_consdiaanterior' => $postado['req_consdiaanterior'],
                'req_medconsumodias'  => $postado['req_medconsumodias'],
                'req_meddias'         => $postado['req_meddias'] ?? null,
                'req_repetedias'      => $repete,
                'req_percseguranca'   => $postado['req_percseguranca'],
                'req_observacao'      => $postado['req_observacao'],
                'stt_id'              => $status,
            ];

            if ($postado['req_id'] != '' && $i == 0) {
                if (!$this->requisicao->update($postado['req_id'], $dadosReq)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao atualizar a Requisição.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
                $req_id = $postado['req_id'];
                $this->requisicaoproduto->excluir($req_id);
            } else {
                if (!$this->requisicao->insert($dadosReq)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao inserir a Requisição.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
                $req_id = $this->requisicao->getInsertID();
            }

            // ── Monta batches de produtos usando dados pré-carregados ─────────────
            [$repBatch, $repPad, $dadosReqPad] = $this->montarBatchProdutos(
                $requisicoes,
                $produtosIndexados,
                $lotesIndexados,
                $req_id,
                $status,
                $postado,
                $tmo_id_pad,
                $hoje,
                $dataEntrega
            );

            if ($repBatch === null) {
                // montarBatchProdutos detectou erro (saldo insuficiente sem dep padrão)
                $ret['erro'] = true;
                $ret['msg']  = 'Saldo da Origem insuficiente para a requisição.';
                $db->transRollback();
                echo json_encode($ret);
                return;
            }

            // ── Batch insert dos produtos (sem movimento — sem risco) ─────────────
            if (!empty($repBatch)) {
                if (!$this->requisicaoproduto->insertBatch($repBatch)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao inserir itens na requisição de produto.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
            }

            // ── Requisição do depósito padrão (saldo complementar) ───────────────
            if (!empty($dadosReqPad)) {
                envia_msg_ws($this->data['controler'], 'Requisição Padrão', 'MsgServer', session()->get('usu_id'), 1);

                if (!$this->requisicao->insert($dadosReqPad)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Não foi possível criar a Requisição para o depósito padrão.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }

                $req_idPad   = $this->requisicao->getInsertID();
                $repPadBatch = array_map(fn($r) => array_merge($r, ['req_id' => $req_idPad]), $repPad);

                if (!$this->requisicaoproduto->insertBatch($repPadBatch)) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao inserir itens na requisição do depósito padrão.';
                    $db->transRollback();
                    echo json_encode($ret);
                    return;
                }
            }
        }

        // ─── ATENDIMENTO AUTOMÁTICO (status 4 com tmo_atendeautomatico) ──────────
        if ($status == 4) {
            $insvis    = retornaInsVis($req_id);
            $nreq      = str_pad($req_id, 6, '0', STR_PAD_LEFT);
            $statusAuto = $this->determinarStatusAutomatico($tipomov, $insvis, (int) $postado['tmo_id'], $nreq);

            // Atualiza status e notifica
            $this->requisicao->update($req_id, ['stt_id' => $statusAuto['status']]);
            $notif = new Notifica();
            $notif->gravaNotifica($statusAuto['destNotif'], session()->get('usu_id'), $req_id, $statusAuto['msgsocket'], 'C');

            // Processa atendimento automático por produto (insert + movimento individuais)
            if ($statusAuto['status'] != 4) {
                $produtosreq = $this->requisicao->getRequisicaoProdutos($req_id);
                $erro        = $this->processarAtendimentoAutomatico(
                    $produtosreq,
                    $statusAuto['status'],
                    $postado['tmo_id'],
                    $db
                );

                if ($erro !== null) {
                    $ret['erro'] = true;
                    $ret['msg']  = $erro;
                    echo json_encode($ret);
                    return;
                }
            }
        }

        // ─── COMMIT FINAL ─────────────────────────────────────────────────────────
        $db->transCommit();
        $ret['erro'] = false;
        $ret['msg'] = "{$vezes} Requisição(ões) gravada(s) com sucesso!";
        $ret['url'] = site_url($this->data['controler']);
        session()->setFlashdata('msg', $ret['msg']);
        echo json_encode($ret);
        cache()->clean();
    }

    // ════════════════════════════════════════════════════════════════════════════
    // FUNÇÕES AUXILIARES
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Pré-carrega produtos e lotes em memória indexados por código,
     * eliminando consultas repetidas dentro dos loops.
     * Retorna [produtosIndexados, lotesIndexados]
     */
    private function preCarregarProdutosLotes(array $requisicoes): array
    {
        $produtosIndexados = [];
        $lotesIndexados    = [];

        // ── Coleta todos os códigos e pares cod|lote únicos ──────────────────
        $codigos = [];
        $pares   = []; // [cod => [lote, lote2, ...]]

        foreach ($requisicoes as $item) {
            $cod  = trim($item['cod_erp']);
            $lote = trim($item['lote']);
            $codigos[$cod] = true;
            $pares[$cod][] = $lote;
        }

        // ── 1 query para todos os produtos ───────────────────────────────────
        $lista   = array_keys($codigos);

        $rows = $this->produtos->getProdutosByLista($lista);

        foreach ($rows as $row) {
            $produtosIndexados[trim($row->pro_codpro)] = $row;
        }

        // ── 1 query para todos os lotes ──────────────────────────────────────
        // Monta lista de todos os lotes únicos (exceto "Sem Lote")
        $todosLotes = [];
        foreach ($pares as $cod => $lotes) {
            foreach ($lotes as $lote) {
                if ($lote !== 'Sem Lote') {
                    $todosLotes[] = $lote;
                }
            }
        }
        $todosLotes = array_unique($todosLotes);

        if (!empty($todosLotes)) {
            $loteRows = $this->produtos->getLotesByLista($lista, $todosLotes);

            foreach ($loteRows as $row) {
                $chave = trim($row->lot_codpro) . '|' . trim($row->lot_lote);
                $lotesIndexados[$chave] = $row;
            }
        }

        // ── Marca "Sem Lote" sem query ───────────────────────────────────────
        foreach ($requisicoes as $item) {
            $lote  = trim($item['lote']);
            $chave = trim($item['cod_erp']) . '|' . $lote;
            if ($lote === 'Sem Lote' && !isset($lotesIndexados[$chave])) {
                $lotesIndexados[$chave] = (object) ['lot_id' => null];
            }
        }

        return [$produtosIndexados, $lotesIndexados];
    }


    /**
     * Valida todos os produtos e lotes antes de qualquer gravação.
     * Retorna mensagem de erro ou null se tudo ok.
     */
    private function validarProdutosLotes(array $requisicoes, array $produtosIndexados, array $lotesIndexados): ?string
    {
        foreach ($requisicoes as $item) {
            $cod   = $item['cod_erp'];
            $lote  = trim($item['lote']);
            $chave = $cod . '|' . $lote;

            if (empty($produtosIndexados[$cod]) || !isset($produtosIndexados[$cod]->pro_id)) {
                return "Produto não encontrado: {$cod}";
            }

            if (empty($lotesIndexados[$chave])) {
                return "Lote não encontrado: {$item['lote']} para o produto {$cod}";
            }
        }

        return null;
    }

    /**
     * Monta os arrays de produtos para insert em batch.
     * Retorna [repBatch, repPad, dadosReqPad] ou [null, [], []] em caso de erro.
     */
    private function montarBatchProdutos(
        array $requisicoes,
        array $produtosIndexados,
        array $lotesIndexados,
        int|string $req_id,
        int $status,
        array $postado,
        ?int $tmo_id_pad,
        \DateTime $hoje,
        \DateTime $dataEntrega
    ): array {
        $repBatch    = [];
        $repPad      = [];
        $dadosReqPad = [];

        foreach ($requisicoes as $item) {
            $cod     = $item['cod_erp'];
            $lote    = trim($item['lote']);
            $chave   = $cod . '|' . $lote;
            $produto = $produtosIndexados[$cod];
            $loteObj = $lotesIndexados[$chave];

            // Verifica saldo e monta requisição padrão (somente status 4)
            if ($status == 4) {
                $resultados = [];
                foreach (['saldo_origem', 'saldo_padrao', 'saldo_destino'] as $prefixo) {
                    foreach ($item as $chaveItem => $valor) {
                        if (strpos($chaveItem, $prefixo) === 0) {
                            $resultados[$prefixo] = $valor;
                            break;
                        }
                    }
                }

                if ($resultados['saldo_origem'] < $item['requisicao']) {
                    if (empty($postado['req_deppadrao'])) {
                        return [null, [], []]; // sinaliza erro de saldo
                    }

                    // Cabeçalho da req padrão — montado uma única vez por iteração
                    if (empty($dadosReqPad)) {
                        $dadosReqPad = [
                            'req_data'            => $hoje->format('Y-m-d H:i:s'),
                            'req_dataentrega'     => $dataEntrega->format('Y-m-d'),
                            'tmo_id'              => $tmo_id_pad,
                            'req_deporigem'       => $postado['req_deppadrao'],
                            'req_depdestino'      => $postado['req_deporigem'],
                            'req_consdiaanterior' => $postado['req_consdiaanterior'],
                            'req_medconsumodias'  => $postado['req_medconsumodias'],
                            'req_meddias'         => $postado['req_meddias'] ?? null,
                            'req_repetedias'      => 0,
                            'req_percseguranca'   => $postado['req_percseguranca'],
                            'req_observacao'      => $postado['req_observacao'],
                            'stt_id'              => $status,
                            'req_original'        => $req_id,
                        ];
                    }

                    $repPad[] = [
                        'pro_id'            => $produto->pro_id,
                        'lot_id'            => $loteObj->lot_id ?? null,
                        'rep_multiplicador' => $item['multiplica'],
                        'rep_seguranca'     => $item['seguranca'],
                        'rep_quantia'       => $item['requisicao'] - $resultados['saldo_origem'],
                    ];
                }
            }

            $repBatch[] = [
                'req_id'            => $req_id,
                'pro_id'            => $produto->pro_id,
                'lot_id'            => $loteObj->lot_id ?? null,
                'rep_multiplicador' => $item['multiplica'],
                'rep_seguranca'     => $item['seguranca'],
                'rep_quantia'       => $item['requisicao'],
            ];
        }

        return [$repBatch, $repPad, $dadosReqPad];
    }

    /**
     * Determina o status final da requisição no atendimento automático,
     * junto com destino da notificação e mensagem.
     */
    private function determinarStatusAutomatico(object $tipomov, object $insvis, int $tmo_id, string $nreq): array
    {
        $result = [
            'status'    => 4,
            'destNotif' => 'Estoque\AteRequisicao',
            'msgsocket' => 'Foi gerada a Requisição Nº ' . $nreq,
        ];

        if ($tipomov->tmo_atendeautomatico !== 'S') {
            return $result;
        }

        $result = [
            'status'    => 18,
            'destNotif' => 'Estoque\ConfRequisicao',
            'msgsocket' => 'A Requisição Nº ' . $nreq . ' foi Atendida!',
        ];

        if ($tipomov->tmo_conferencia !== 'N') {
            return $result;
        }

        $result = [
            'status'    => 25,
            'destNotif' => 'Preproces/InspecaoProd',
            'msgsocket' => 'A Requisição Nº ' . $nreq . ' foi Conferida!',
        ];

        if ($insvis->temN || $tipomov->tmo_exigeinspecao == 'N') {
            $result = [
                'status'    => 5,
                'destNotif' => 'Estoque\Requisicao',
                'msgsocket' => 'A Requisição Nº ' . $nreq . ' foi Concluída!',
            ];
        }

        return $result;
    }

    /**
     * Processa o atendimento automático produto a produto.
     * Insert primeiro, movimento depois — se o movimento falhar,
     * só o insert daquele produto é revertido pelo transRollback.
     * Retorna mensagem de erro ou null se tudo ok.
     */
    private function processarAtendimentoAutomatico(
        array $produtosreq,
        int $status,
        int|string $tmo_id,
        $db
    ): ?string {
        $msgs = [
            18 => 'Atendimento Automático da Requisição Nº ' . str_pad($produtosreq[0]->req_id, 6, '0', STR_PAD_LEFT),
            25 => 'Conferência Automática de Requisição Nº ' . str_pad($produtosreq[0]->req_id, 6, '0', STR_PAD_LEFT),
            5  => 'Conclusão Automática de Requisição Nº ' . str_pad($produtosreq[0]->req_id, 6, '0', STR_PAD_LEFT),
        ];
        $msg = $msgs[$status] ?? '';

        foreach ($produtosreq as $val) {
            $sqlSave = [
                'rep_id'        => $val->rep_id,
                'pro_id'        => $val->pro_id,
                'rpa_cancelada' => 0,
                'rpa_atendida'  => $val->rep_quantia,
                'rpa_data'      => date('Y-m-d H:i:s'),
            ];

            if ($status >= 25) {
                $sqlSave['rpa_conferida']        = $val->rep_quantia;
                $sqlSave['rpa_data_conferencia'] = date('Y-m-d H:i:s');
            }

            if ($status == 5 && $val->cla_insvis == 'N' && $val->rpa_id != null) {
                $sqlSave['rpa_aprovada']      = -1;
                $sqlSave['rpa_data_inspecao'] = date('Y-m-d H:i:s');
            }

            // ── Insert primeiro ──────────────────────────────────────────────────
            if (!$this->reqprodutoate->insert($sqlSave)) {
                $db->transRollback();
                return 'Erro ao gravar atendimento automático.';
            }

            // ── Movimento após o insert — se falhar, reverte só este item ────────
            $mov = [[
                'id'      => $tmo_id,
                'qt'      => $val->rep_quantia,
                'msg'     => $msg,
                'pro_id'  => $val->pro_id,
                'rep_id'  => $val->rep_id,
                'reserva' => ($status == 5) ? '' : 'D',
            ]];

            envia_msg_ws($this->data['controler'], 'Gerando Movimentos de Estoque', 'MsgServer', session()->get('usu_id'), 1);
            $movim = geraMovimentoRequisicoes($mov, $this->data['controler']);

            if ($movim['status'] == 'Erro') {
                $db->transRollback();
                return $movim['mensagem'];
            }
        }

        return null;
    }
}
