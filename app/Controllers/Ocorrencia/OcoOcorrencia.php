<?php

namespace App\Controllers\Ocorrencia;

use App\Controllers\BaseController;
use App\Entities\Ocorrencia\EntOcoOcorrencia;
use App\Entities\Ocorrencia\EntOcoSubtOcorrencia;
use App\Entities\Ocorrencia\EntOcoTratativa;
use App\Libraries\MyCampo;
use App\Models\CommonModel;
use App\Models\Ocorre\OcorreOcorrenciaAcaoModel;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use App\Models\Ocorre\OcorreSubtOcorrenciaModel;
use App\Models\Ocorre\OcorreTipoOcorrenciaModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Traits\ForeignKeyUsageChecker;

class OcoOcorrencia extends BaseController
{
    use ForeignKeyUsageChecker;

    public $data = [];
    public $modelTipo;
    public $modelSubt;
    public $ocorrencia;
    public $ocorrenciaAcao;
    public $lote;
    public $common;
    public $permissao = '';

    public function __construct()
    {
        $this->data      = session()->get('dados_tela') ?? [];
        $this->permissao = $this->data['permissao'];

        $this->modelTipo  = new OcorreTipoOcorrenciaModel();
        $this->modelSubt  = new OcorreSubtOcorrenciaModel();
        $this->ocorrencia     = new OcorreOcorrenciaModel();
        $this->ocorrenciaAcao = new OcorreOcorrenciaAcaoModel();
        $this->lote           = new ProdutLoteModel();
        $this->common     = new CommonModel();

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
     * Tela de abertura
     * index
     *
     * @param mixed $id
     * @return void
     */
    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'oco_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        echo view('vw_lista', $this->data);
    }

    /**
     * Tela de listagem
     * lista
     *
     * @param mixed $id
     * @return void
     */
    public function lista()
    {
        $campos = montaColunasCampos($this->data, 'oco_id');
        $dados  = $this->ocorrencia->getListaCompleta();
        // Filtra por perfil
        $dados = filtrarPorPerfil($dados);
        // debug($dados, true);
        $base_url      = base_url('OcoTrataOcorrencia');
        $oco_ids_assoc = array_map(
            fn($o) => $o->oco_id,
            $dados
        );
        $logcriou = buscaLogTabelaFirst('oco_ocorrencia', $oco_ids_assoc);
        // debug($logcriou, true);
        foreach ($dados as $nov) {
            unset($nov->oco_ativo);

            // finalizado por e gerado por:
            $nov->acao_person = [];
            if ($nov->usu_nome == null) {
                $nov->usu_nome    = $logcriou[$nov->oco_id]['usua_alterou'] ?? '';
            }

            // Botão imprimir            // Botão imprimir
            if (trim($nov->stt_nome ?? '') !== 'Pendente') {
                $url_imp            = base_url('/CriamPdf2026/PrintOcorrencia/' . $nov->oco_id);
                $nov->acao_person[] = "
                     <button class='btn btn-outline-dark btn-sm border-0 mx-0 fs-0'
                         title='Imprimir'
                         onclick='openPDFModal(\"{$url_imp}\",\"Imprimir Ocorrência\")'>
                         <i class='fa-solid fa-print'></i>
                     </button>
                 ";
            }

            // Botão finalizar se pendente
            if (trim($nov->stt_nome ?? '') === 'Pendente') {
                $url_finalizar      = $base_url . '/finalizar/' . $nov->oco_id;
                $nov->acao_person[] = "
                    <button class='btn btn-outline-success btn-sm border-0 mx-0 fs-0'
                        title='Finalizar'
                        onclick='redireciona(\"$url_finalizar\")'>
                        <i class='fas fa-check'></i>
                    </button>
                ";
            }
        }
        // debug($dados, true);
        $ret                       = new \stdClass();
        $this->data['allconsulta'] = true;
        $listaFinal                = [];

        foreach ($dados as $index => $nov) {
            // SE TEM REQUISIÇÃO: NÃO PERMITE EDITAR e EXCLUIR
            if (! empty($nov->req_id)) {
                $this->data['exclusao'] = false;
                $this->data['edicao']   = false;
            } else {
                $this->data['exclusao'] = true;
                $this->data['edicao']   = true;
            }
            $linha        = montaListaColunasEnt($this->data, 'oco_id', [$nov], $campos[1]);
            $listaFinal[] = $linha[0];
        }

        $ret->data = $listaFinal;

        return $this->response->setJSON($ret);
    }

    /**
     * inclusão
     * add
     *
     * @param mixed $id
     * @return void
     */
    public function add()
    {
        // Instancia a entity
        $oco    = new EntOcoOcorrencia();
        $fields = $oco->campos;
        // Dados Gerais
        $this->data['title'] = 'Ocorrência';
        // $this->data['desc_metodo'] = 'Nova ';
        $this->data['secoes'] = ['Dados Gerais'];

        // define os campos
        $this->data['campos'] = [[
            $fields['oco_id'],
            $fields['tpo_id'],
            $fields['sut_id'],
            $fields['oco_descricao'],
            $fields['pro_id'],
            $fields['lot_id'],
            $fields['lot_lote'],
            $fields['pro_despro'],
            $fields['oco_qtd'],
            $fields['oco_data'],
        ]];
        $this->data['destino'] = 'store';

        // Renderiza a view
        echo view('vw_edicao', $this->data);
    }
    public function ativinativ($id, $tipo)
    {
        $ret = [];
        try {
            if ($tipo == 1) {
                $dad_atin = [
                    'oco_ativo' => 'A'
                ];
            } else {
                $dad_atin = [
                    'oco_ativo' => 'I'
                ];
                // $this->verificarUsoEmRelacionamentos('cfg_status', 'stt_id', (int) $id);
            }

            $this->ocorrencia->update($id, $dad_atin);
            $ret['erro'] = false;
            session()->setFlashdata('msg', 'Ocorrência Alterada com Sucesso');
            $ret['msg'] = 'Ocorrência Alterada com Sucesso';
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $ret['erro'] = true;
            // $ret['msg']  = 'Não foi possível Alterar o Status, Verifique!<br><br> ' . $e . message();
            $ret['msg']  = 14;
        } catch (\Exception $e) {
            log_message('error', 'Erro ao alterar status da Ocorrência: ' . $e->getMessage());
            $ret['erro'] = true;
            // $ret['msg']  = 'Não foi possível Alterar o Status, Verifique!<br><br> ' . $e . message;
            $ret['msg']  = 14; // ou código personalizado, se preferir
        }

        echo json_encode($ret);
    }

    /**
     * Visualização
     * show
     *
     * @param mixed $id
     * @return void
     */
    public function show($id)
    {
        $dados = $this->ocorrencia->getOcorrencia($id);
        // debug($dados, true);

        // Valida se a ocorrência existe
        if (! $dados) {
            throw new \Exception('Ocorrência não encontrada');
        }

        $log             = buscaLogTabela('oco_ocorrencia', [$id]);
        $dados->usu_nome = $log[$id]['usua_alterou'] ?? null;
        // Instancia a entity
        // debug($dados, true);

        // dados gerais
        $secao[0]  = 'Dados Gerais';
        $secao[1]  = 'Telas Aplicáveis';
        $secao[2]  = 'Ações';
        $campos = $this->showCabecalho($dados);
        // debug($campos);

        // BLOCO TELAS APLICÁVEIS
        $entity = new EntOcoSubtOcorrencia((array) $dados);
        $telas  = array_map(
            fn($item) => (array) $item,
            $this->modelSubt->getTOTelasAplicaveis($dados->sut_id)
        );
        // debug($telas, true);

        $campos[1] = [];
        if (! empty($telas)) {
            $telasResultado = [];
            $total          = count($telas);

            for ($c = 0; $c < $total; $c++) {
                $fields           = $entity->defCamposTelasAplicaveis($telas[$c], $c, $total, true);
                $telasResultado[] = $fields;
            }

            $campos[1][] = view(
                'partials/pw_telas_aplicaveis_ocorrencia',
                [
                    'telas'  => $telasResultado,
                    'oco_id' => $id,
                ]
            );
        }
        // BLOCO DAS AÇÕES — mesma fonte de finalizar() (oco_ocorrencia_acao,
        // com tpa_nome via join), sempre somente-leitura (forcaLeitura=true)
        // — tela de CONSULTA nunca pode renderizar checkbox/campo editável,
        // mesmo para ação ainda pendente.
        $entity = new EntOcoTratativa($dados, true);
        $acoes  = $this->ocorrenciaAcao->getAcoesComNome($id);
        $campos[2] = [];
        // debug($acoes, true);
        if (! empty($acoes)) {

            $acoesResultado = [];

            foreach ($acoes as $pos => $acao) {
                $acao                  = (object) $acao;
                $acao->somente_leitura = true;
                $camposAcao            = $entity->defCamposAcao($acao, $pos, true);
                $acoesResultado[]      = $camposAcao;
            }

            $campos[2][] = view(
                'partials/pw_acoes_ocorrencia',
                [
                    'acoes'  => $acoesResultado,
                    'oco_id' => $id,
                ]
            );
        }

        $this->data['desc_edicao'] = ' Req. Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT) . ' - ' . fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome, 1);
        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['destino']     = '';

        echo view('vw_edicao', $this->data);
    }

    /**
     * visualização cabeçalho
     * showCabecalho
     *
     * @param mixed $id
     * @return void
     */
    public function showCabecalho($dados)
    {
        $oco    = new EntOcoOcorrencia((array) $dados, true);
        $fields = $oco->campos;
        // define os campos
        $campos[] = [
            $fields['oco_id'],
            $fields['tpo_id'],
            $fields['sut_id'],
            $fields['oco_data'],
            $fields['usu_nome'],
            $fields['pro_id'],
            $fields['cod_erp'],
            $fields['cod_erp_show'],
            $fields['lot_id'],
            $fields['lot_lote'],
            $fields['fab_apeFab'],
            $fields['lot_validade_show'],
            $fields['lot_validade'],
            $fields['num_req_show'],
            $fields['req_tmo_nome_show'],
            $fields['pro_despro'],
            $fields['oco_qtd'],
            $fields['oco_descricao'],
            $fields['tel_nome'],
        ];

        return $campos;
    }

    /**
     * Edição
     * edit
     *
     * @param mixed $id
     * @return void
     */
    public function edit($id)
    {
        // Busca a ocorrência pelo ID
        $dados = $this->ocorrencia->getOcorrencia($id);

        // Valida se a ocorrência existe
        if (! $dados) {
            throw new \Exception('Ocorrência não encontrada');
        }

        // RN04.1 — bloqueio server-side: ocorrência já vinculada a uma
        // requisição/repartição não pode ser alterada (o botão "Alterar" já é
        // ocultado no front, mas isso não protege contra requisição forjada).
        if (!empty($dados->req_id) || !empty($dados->rep_id)) {
            // Bloqueante 1 (revisão 01) — não chamar getMensagem() no
            // controller; quem resolve o texto é o front (boxAlert()).
            throw new \Exception('Alteração não Permitida');
        }

        // Instancia a entity
        // debug($dados, true);
        $oco    = new EntOcoOcorrencia((array) $dados, false);
        $fields = $oco->campos;

        // dados gerais
        $this->data['secoes'] = ['Dados Gerais'];

        // define os campos
        $this->data['campos'] = [[
            $fields['oco_id'],
            $fields['tpo_id'],
            $fields['sut_id'],
            $fields['oco_descricao'],
            $fields['pro_id'],
            $fields['lot_id'],
            $fields['lot_lote'],
            $fields['pro_despro'],
            $fields['oco_qtd'],
            $fields['oco_data'],
        ]];

        $this->data['destino'] = "store";
        // $this->data['edicao']  = true;
        $this->data['title']       = 'Ocorrência';
        $this->data['desc_edicao'] = ' Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT);

        echo view('vw_edicao', $this->data);
    }

    /**
     * Model
     * addOutraTela
     *
     * @param mixed $id
     * @return void
     */
    public function addOutraTela()
    {
        $request = service('request');

        // Lê e decodifica o JSON recebido
        $json = $request->getJSON(true); // true = como array associativo
        // Agora você pode acessar normalmente:
        $proId   = $json['pro_id'] ?? null;
        $lotlote = $json['lot_lote'] ?? null;
        $reqId   = $json['req_id'] ?? null;
        $repId   = $json['rep_id'] ?? null;
        $telId   = $json['tel_id'] ?? null;

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
            [],
            $config
        );
        /** Busca a classe do Produto, pelo lote e salva
         * em memória pra usar na busca de Subtipo */
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
        $config['Pai']      = "tpo_id";
        $config['Urlbusca'] = base_url('Buscas/buscaSubtipoPorTipo');
        $config['FunChan']  = "buscaLoteProduto('lot_lote','" . base_url('/buscas/buscaProdutoporLote') . "')";

        $sut_id = criaSelectRelativo(
            'vw_oco_subt_ocorrencia_relac',
            'sut_id',
            'sut_nome',
            null,
            2,
            'oco_ocorrencia',
            ['tel_id' => ''],
            $config,
            'sut_id'
        );

        $desc              = new MyCampo('oco_ocorrencia', 'oco_descricao');
        $desc->valor       = (isset($dados['oco_descricao'])) ? $dados['oco_descricao'] : '';
        $desc->obrigatorio = true;
        $desc->dispForm    = 'col-6';
        $descreva          = $desc->crInput();

        $qtd              = new MyCampo('oco_ocorrencia', 'oco_qtd');
        $qtd->valor       = $dados['oco_qtd'] ?? 0;
        $qtd->dispForm    = 'col-6';
        $qtd->minimo      = 1;
        $qtd->largura     = 10;
        $qtd->size        = 4;
        $qtd->maximo      = 9999;
        $qtd->obrigatorio = true;
        $quantia          = $qtd->crInput();

        // Instancia a entity
        $dados['lot_lote'] = $lotlote;
        $dados['req_id']   = $reqId;
        $dados['rep_id']   = $repId;
        $oco               = new EntOcoOcorrencia($dados, true);

        // Dados Gerais
        $this->data['secoes'] = ['Dados Gerais'];

        // define os campos
        $this->data['campos'] = [[
            $tel_id,
            $toc_oc,
            $oco->campos['lot_id'],
            $oco->campos['lot_lote'],
            $sut_id,
            $oco->campos['pro_id'],
            $oco->campos['pro_despro'],
            $quantia,
            $oco->campos['oco_data'],
            $descreva,
        ]];
        $this->data['destino']     = 'storetmp';
        $this->data['desc_metodo'] = 'Nova Ocorrência';
        $this->data['hidden'][]    = [
            'name'  => 'req_id',
            'value' => $reqId,
        ];
        $this->data['hidden'][] = [
            'name'  => 'rep_id',
            'value' => $repId,
        ];

        // Renderiza a view
        echo view('vw_edicao_modal', $this->data);
    }

    /**
     * Gravação
     * store
     *
     * @return void
     */
    public function storetmp()
    {
        $postado = $this->request->getPost();
        // debug($postado, true);

        try {
            if (empty($postado['oco_id'])) {
                unset($postado['oco_id']);
            }

            // SUBTIPO / STATUS — RN03.1.6 / RN03.2.5
            $modModel = new OcorreSubtOcorrenciaModel();
            $subtipo  = $modModel->getSubtOcorrencia((int) $postado['sut_id'])[0] ?? null;
            // debug($subtipo, true);
            if ($subtipo) {
                $postado['tpo_id']  = $subtipo->tpo_id;
                $postado['stt_id']  = $modModel->getStatusInicial((int) $postado['sut_id']);

                if ($postado['stt_id'] === 29) {
                    // FINALIZA AUTOMÁTICO
                    $postado['tpa_id'] = null;
                    $postado['tmo_id'] = null;
                } else {
                    $acao = $modModel->getAcaoConfigurada((int) $postado['sut_id']);
                    // debug($acao);
                    $postado['tpa_id'] = $acao->tpa_id ?? null;
                    $postado['tmo_id'] = $acao->tmo_id ?? null;
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

            /** Verificar se ocorrência Gera Movimentação de Estoque e se Sim envia para o WS para acertar o cancelamento */
            $msg[0] = $postado['rep_id'];
            $msg[1] = $postado['oco_qtd'];
            envia_msg_ws($this->data['controler'], $msg, 'NovaOcorrencia', session()->get('usu_id'), 1);

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

    // public function getProdutoLote()
    // {
    //     // Recupera o código do lote enviado via POST
    //     $codLote = $this->request->getPost('codLote');
    //     // Valida se o lote foi informado
    //     if (!$codLote) {
    //         return $this->response->setJSON([
    //             'erro' => 'Lote não informado'
    //         ]);
    //     }

    //     $dados = $this->produtLoteModel->getLoteSearch($codLote);

    //     // Valida se o lote foi encontrado
    //     if (!$dados || empty($dados)) {
    //         return $this->response->setJSON([
    //             'erro' => 'Lote não encontrado'
    //         ]);
    //     }
    //     $lote = $dados[0];

    //     // Retorna a descrição do produto vinculada ao lote
    //     return $this->response->setJSON([
    //         'descpro' => $lote->pro_despro ?? '',
    //     ]);
    // }

    /**
     * Exclusão
     * delete
     *
     * @param mixed $id
     * @return void
     */
    public function delete($id)
    {
        try {

            $oco = $this->ocorrencia->getOcorrencia($id);

            if (! $oco) {
                throw new \Exception('Ocorrência não encontrada');
            }

            // RN05.1 — só pode excluir ocorrência Pendente (stt_id=28) e sem
            // vínculo com requisição (req_id).
            if (!empty($oco->req_id) || (int) $oco->stt_id !== 28) {
                return $this->response->setJSON([
                    'erro' => true,
                    'msg'  => 3,
                ]);
            }

            $this->ocorrencia->delete($id);

            return $this->response->setJSON([
                'erro' => false,
                'msg'  => 'Ocorrência Excluída com Sucesso',
            ]);
        } catch (\Exception $e) {

            return $this->response->setJSON([
                'erro' => true,
                'msg'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Finalização da tratativa
     * finalizar
     *
     * @param mixed $id
     * @return void
     */
    public function finalizar($id)
    {
        return redirect()->to('/OcoTrataOcorrencia/finalizar/' . $id);
    }

    /**
     * Gravação
     * store
     *
     * @param mixed $id
     * @return void
     */
    public function store()
    {
        $postado = $this->request->getPost();
        $ret     = [];
        // debug($postado, true);

        try {
            if (empty($postado['oco_id'])) {
                unset($postado['oco_id']);
            } else {
                // RN04.1 — bloqueio server-side: não permite alterar (update)
                // uma ocorrência já vinculada a requisição/repartição.
                $existente = $this->ocorrencia->getOcorrencia($postado['oco_id']);
                if ($existente && (!empty($existente->req_id) || !empty($existente->rep_id))) {
                    return $this->response->setJSON([
                        'erro' => true,
                        'msg'  => 15,
                    ]);
                }
            }

            // SUBTIPO / STATUS — RN03.1.6 / RN03.2.5
            $modModel          = new OcorreSubtOcorrenciaModel();
            $postado['stt_id'] = $modModel->getStatusInicial((int) $postado['sut_id']);

            if ($postado['stt_id'] != 29) {
                $acao = $modModel->getAcaoConfigurada((int) $postado['sut_id']);
                if ($acao) {
                    $postado['tpa_id'] = $acao->tpa_id ?? null;
                    $postado['tmo_id'] = $acao->tmo_id ?? null;
                }
            }

            // Tela de Origem — tela de onde a ocorrência está sendo criada
            // (ex.: a própria OcoOcorrencia, ou outra tela quando já vier
            // postada, como no fluxo addOutraTela/storetmp).
            if (empty($postado['tel_id'])) {
                $postado['tel_id'] = $this->data['tel_id'] ?? null;
            }
            // cria data se não veio do form
            if (empty($postado['oco_data'])) {
                $postado['oco_data'] = date('Y-m-d H:i:s');
            }
            $postado['usu_criou'] = session()->get('usu_id');

            // cria a entity
            $entity = new EntOcoOcorrencia($postado);
            // debug($entity, true);

            $__saveret = $this->ocorrencia->save($entity);
            if (! $__saveret) {
                $ret['erro'] = false;
                $ret['msg']  = $this->ocorrencia->errors();
            } else {
                // $postado['stt_id'] aqui é só o palpite inicial calculado
                // acima (getStatusInicial()/getAcaoConfigurada()) — quem
                // decide o stt_id real, com base na execução de fato das
                // ações (oco_ocorrencia_acao), é
                // OcoTrataOcorrencia::store() (resolveStatusOcorrencia()),
                // chamado sempre a seguir via processAfterSave(), e que
                // sobrescreve o registro via update() logo depois.
                if (! isset($postado['oco_id'])) {
                    $idoco = $this->ocorrencia->getInsertID();
                } else {
                    $idoco = $postado['oco_id'];
                }

                $data                 = $entity->toArray();
                $lote                 = $this->lote->getLoteId($postado['lot_lote'])[0];
                $data['oco_id']       = $idoco;
                $data['lot_lote']     = $lote->lot_lote;
                $data['lot_validade'] = $lote->lot_validade;
                // debug($data);
                $ocoService = service('ocorrenciaService');
                $ocoService->processAfterSave($data);

                $ret['erro'] = false;
                $ret['msg']  = 'Ocorrência gravada com sucesso!';
                session()->setFlashdata('msg', $ret['msg']);
                $ret['url'] = site_url($this->data['controler']);
            }
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }
        return $this->response->setJSON($ret);
    }
}
