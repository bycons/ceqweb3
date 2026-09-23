<?php

namespace App\Controllers\Produto;

use App\Controllers\BaseController;
use App\Controllers\Ws\WsCeqweb;
use App\Entities\Produto\EntProdutos;
use App\Entities\Produto\EntProdutProduto;
use App\Models\CommonModel;
use App\Models\Produt\ProdutClasseModel;
use App\Models\Produt\ProdutIngredienteModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Traits\ForeignKeyUsageChecker;
use Config\Database;

class Produto extends BaseController
{
    use ForeignKeyUsageChecker;

    public $data      = [];
    public $permissao = '';
    public $produtos;
    public $classe;
    public $common;
    public $ingrediente;
    public $lote;

    /**
     * Construtor da Produto
     * construct
     */
    public function __construct()
    {
        $this->data        = session()->getFlashdata('dados_tela');
        $this->permissao   = $this->data['permissao'];
        $this->produtos    = new ProdutProdutoModel();
        $this->ingrediente = new ProdutIngredienteModel();
        $this->classe      = new ProdutClasseModel();
        $this->lote        = new ProdutLoteModel();
        $this->common      = new CommonModel();

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
        // $integ = new WsCeqweb();
        // $integ->integraProduto();

        $this->data['colunas']   = montaColunasLista($this->data, 'pro_id');
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
        $campos                 = montaColunasCampos($this->data, 'pro_id');
        $dados_produto          = $this->produtos->getListaProduto();
        $dados_produto          = filtrarPorPerfil($dados_produto);
        $this->data['exclusao'] = false;
        $produto                = [
            'data' => montaListaColunasEnt($this->data, 'pro_id', $dados_produto, $campos[1]),
        ];
        cache()->save('produto', $produto, 60000);

        echo json_encode($produto);
    }

    /**
     * Aprovação
     * aprova
     *
     * @param mixed $id
     * @return void
     */
    public function aprova($dados_produtos)
    {
        if ($dados_produtos instanceof \stdClass) {
            $dados_produtos = (array) $dados_produtos;
        }

        $entity = new EntProdutProduto($dados_produtos, true);
        $fields = $entity->campos;

        $this->data['secoes']    = [];
        $this->data['secoes'][0] = 'Dados Gerais';

        $this->data['campos']    = [];
        $this->data['campos'][0] = [];

        $this->data['campos'][0][] = $fields['pro_codpro'];
        $this->data['campos'][0][] = $fields['ori_codOri'];
        $this->data['campos'][0][] = $fields['pro_id'];
        $this->data['campos'][0][] = $fields['fam_codFam'];
        $this->data['campos'][0][] = $fields['pro_ctrlot'];
        $this->data['campos'][0][] = $fields['pro_despro'];
        $this->data['campos'][0][] = $fields['fab_codFab'];
        $this->data['campos'][0][] = $fields['cla_id'];
        $this->data['campos'][0][] = $fields['pro_codbar_fabricante'];
        $this->data['campos'][0][] = $fields['ing_id'];
        $this->data['campos'][0][] = $fields['pro_informacoes'];

        $this->data['desc_metodo'] = 'Aprovação de Produto';
        $this->data['title']       = '';
        $this->data['destino']     = 'storeaprova';

        return view('vw_aprovacao', $this->data);
    }

    /**
     * Consulta
     * show
     *
     * @param mixed $id
     * @return void
     */
    public function show($id)
    {
        return $this->edit($id, true);
    }

    /**
     * Edição
     * edit
     *
     * @param mixed $id
     * @return void
     */
    public function edit($id, $show = false)
    {
        $dados = $this->produtos->getListaProduto($id)[0] ?? null;

        if (! $dados || ($dados->stt_edicao == 'N' && $show == false)) {
            return redirectWithError($this->data['controler'], 41);
        }

        // Se produto está em status inicial, envia para aprovação
        if (in_array($dados->stt_id, [1, 2])) {
            return $this->aprova($dados);
        }

        $entity = new EntProdutProduto((array) $dados);
        $fields = $entity->defCampos((array) $dados, true);

        // debug($fields['cla_id']);
        $secao  = ['Dados Gerais'];
        $campos = [[]];

        $campos[0][] = $fields['pro_codpro'];
        $campos[0][] = $fields['ori_codOri'];
        $campos[0][] = $fields['pro_id'];
        $campos[0][] = $fields['fam_codFam'];
        $campos[0][] = $fields['pro_ctrlot'];
        $campos[0][] = $fields['pro_despro'];
        $campos[0][] = $fields['fab_codFab'];
        $campos[0][] = $fields['cla_id'];
        $campos[0][] = $fields['pro_codbar_fabricante'];
        $campos[0][] = $fields['ing_id'];
        $campos[0][] = $fields['pro_informacoes'];

        // Dados Estoque
        $dados_ceq_produto = $this->produtos->getProdutoCeqweb($id)[0] ?? (object) ['prc_cplpro' => $dados->pro_cplpro];

        $dados_ceq_produto->produto = $dados;

        $entityCeq = new EntProdutProduto((array) $dados_ceq_produto);
        $fieldceq  = $entityCeq->defCamposCeqweb((array) $dados_ceq_produto, $show);

        $secao[1]  = 'Dados Estoque';
        $campos[1] = [
            $fieldceq['prc_id'],
            $fieldceq['pro_cplpro'],
            $fields['pro_qtdemb'],
            $fieldceq['prc_qtdemb_ceq'],

            $fieldceq['prc_conf_req'],
            $fieldceq['prc_pedido_caixa'],

            "<div class='col-6 float-start d-inline-grid'>",
            $fieldceq['prc_etiq_misturador'],
            $fieldceq['prc_codbar'],
            $fieldceq['prc_cor_etiqueta_mist'],
            "</div>",
            "<div class='col-6 float-start d-inline-grid'>",
            $fieldceq['prc_etiq_produto'],
            $fieldceq['prc_cor_etiqueta_prod'],
            "</div>",
            $fieldceq['prc_deposito'],
        ];

        $displ = [];

        // Gestão de Estoque
        if ($dados->cla_gestaoestoque === 'S') {

            $secao[2]  = 'Gestão de Estoque';
            $displ[2]  = 'tabela';
            $campos[2] = [];

            $dados_est_produto = $this->produtos->getProdutoEstoque($id);
            // debug($dados_est_produto, true);

            if (count($dados_est_produto) === 0) {
                $dados_est_produto[] = $dados;
            }

            foreach ($dados_est_produto as $index => $estoque) {

                $entityEst = new EntProdutProduto((array) $estoque);
                $fieldest  = $entityEst->defCamposEstoque((array) $estoque, $index, $show);

                $linha = [];

                $linha[] = $fieldest['pre_id'];
                $linha[] = $fieldest['dep_codDep'];
                $linha[] = $fieldest['pre_gestaoestoque'];

                // Estoque Mínimo
                $linha[] = "<div id='div_est_minimo[$index]' class='border border-2 col-6 d-inline-block mt-4 float-start pb-1' style='clear: left'>";
                $linha[] = "<span class='col-4 bg-white border border-1 px-1 d-block position-relative' style='top: -12px;margin-bottom: -10px;left: 10px;'>Estoque Mínimo</span>";
                $linha[] = "<div class='border-0'>";
                $linha[] = $fieldest['pre_mindiaanterior'];
                $linha[] = $fieldest['pre_minimo'];
                $linha[] = "</div>";
                $linha[] = "</div>";

                // Estoque Máximo
                $linha[] = "<div id='div_est_maximo[$index]' class='border border-2 col-6 d-inline-block mt-4 float-start pb-1'>";
                $linha[] = "<span class='col-4 bg-white border border-1 px-1 d-block position-relative' style='top: -12px;margin-bottom: -10px;left: 10px;'>Estoque Máximo</span>";
                $linha[] = "<div class='border-0'>";
                $linha[] = $fieldest['pre_maxdiaanterior'];
                $linha[] = $fieldest['pre_porcmaximo'];
                $linha[] = $fieldest['pre_maximo'];
                $linha[] = "</div>";
                $linha[] = "</div>";

                // Outros campos
                $linha[] = "<div class='border border-0 col-12 d-inline-block mt-4 float-start pb-1'>";
                $linha[] = $fieldest['pre_sugerida'];
                $linha[] = $fieldest['pre_cbfabricante'];
                $linha[] = $fieldest['pre_cblote'];
                $linha[] = $fieldest['pre_cbmisturador'];
                $linha[] = $fieldest['pre_estdataatual'];
                $linha[] = $fieldest['pre_undfabricante'];
                $linha[] = $fieldest['pre_undlote'];
                $linha[] = $fieldest['pre_undmisturador'];
                $linha[] = "</div>";

                // Botões
                $linha[] = $show ? '' : $fieldest['bt_add'];
                $linha[] = $show ? '' : $fieldest['bt_del'];

                $campos[2][$index] = $linha;
            }
        }

        // Script JS
        $script = "<script>
        acerta_botoes_rep('gestao_de_estoque');
        mostraOcultaCampo('prc_etiq_misturador','S','prc_cor_etiqueta_mist,prc_codbar');
        mostraOcultaCampo('prc_etiq_produto','S','prc_cor_etiqueta_prod');

        mostraOcultaCampoTodos('pre_mindiaanterior','N','pre_minimo');

            mostraOcultaCampoTodos('pre_maxdiaanterior','S','pre_porcmaximo');
            mostraOcultaCampoTodos('pre_maxdiaanterior','N','pre_maximo');

            mostraOcultaCampoTodos('pre_cbfabricante','S','pre_undfabricante');
            mostraOcultaCampoTodos('pre_cblote','S','pre_undlote');
            mostraOcultaCampoTodos('pre_cbmisturador','S','pre_undmisturador');
            mostraOcultaCampoTodos('pre_gestaoestoque','S','pre_sugerida,pre_estdataatual');

            mostraOcultaDivTodos('pre_gestaoestoque','S','div_est_minimo,div_est_maximo');
        </script>";

        $this->data['secoes']      = $secao;
        $this->data['campos']      = $campos;
        $this->data['displ']       = $displ;
        $this->data['destino']     = 'store';
        $this->data['script']      = $script;
        $this->data['desc_edicao'] = $dados->pro_codpro . " - " . $dados->pro_despro . " - " . fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome, 1);

        $this->data['log'] = buscaLog('pro_sap_produto', $id);

        return view('vw_edicao', $this->data);
    }

    public function ativinativ($id, $tipo)
    {
        $ret = [];
        try {
            if ($tipo == 1) {
                $dad_atin = [
                    'pro_ativo' => 'A',
                    'stt_id'    => 3,
                ];
                $msg = "Produto Ativado com Sucesso";
            } else {
                $dad_atin = [
                    'pro_ativo' => 'I',
                    'stt_id'    => 20,
                ];
                $msg = "Produto Inativado com Sucesso";
                // $this->verificarUsoEmRelacionamentos('pro_sap_produto', 'pro_id', (int) $id);
            }
            $this->produtos->update($id, $dad_atin);
            $ret['erro'] = false;
            session()->setFlashdata('msg', $msg);
            $ret['msg'] = $msg;
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14; // ou código personalizado, se preferir
        }
        echo json_encode($ret);
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

        // $ret = [];
        // try {
        //     $this->produtos->delete($id);
        //     $ret['erro'] = false;
        //     cache()->clean();
        //     session()->setFlashdata('msg', 'Produto Excluído com Sucesso');
        // } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        //     $ret['erro'] = true;
        //     $ret['msg']  = 'Não foi possível Excluir esse Produto Verifique!<br><br>';
        // }
        // echo json_encode($ret);
    }

    /**
     * Summary of addCampo
     * @param mixed $ind
     * @return never
     */
    public function addCampo($ind)
    {
        $entity   = new EntProdutProduto();
        $fieldest = $entity->defCamposEstoque(false, $ind);

        $campo                = [];
        $campo[count($campo)] = $fieldest['pre_id'];
        $campo[count($campo)] = $fieldest['dep_codDep'];
        $campo[count($campo)] = $fieldest['pre_gestaoestoque'];
        $campo[count($campo)] = "<div id='div_est_minimo[$ind]' class='border border-2 col-6 d-inline-block mt-4 float-start pb-1' style='clear: left'>";
        $campo[count($campo)] = "<span class='col-3 bg-white border border-1 px-1 d-block position-relative' style='top: -12px;margin-bottom: -10px;left: 10px;'>Estoque Mínimo</span>";
        $campo[count($campo)] = "<div class='border-0'>";
        $campo[count($campo)] = $fieldest['pre_mindiaanterior'];
        $campo[count($campo)] = $fieldest['pre_minimo'];
        $campo[count($campo)] = "</div>";
        $campo[count($campo)] = "</div>";
        $campo[count($campo)] = "<div id='div_est_maximo[$ind]' class='border border-2 col-6 d-inline-block mt-4 float-start pb-1'>";
        $campo[count($campo)] = "<span class='col-3 bg-white border border-1 px-1 d-block position-relative' style='top: -12px;margin-bottom: -10px;left: 10px;'>Estoque Máximo</span>";
        $campo[count($campo)] = "<div class='border-0'>";
        $campo[count($campo)] = $fieldest['pre_maxdiaanterior'];
        $campo[count($campo)] = $fieldest['pre_porcmaximo'];
        $campo[count($campo)] = $fieldest['pre_maximo'];
        $campo[count($campo)] = "</div>";
        $campo[count($campo)] = "</div>";
        $campo[count($campo)] = "<div class='border border-0 col-12 d-inline-block mt-4 float-start pb-1'>";
        $campo[count($campo)] = $fieldest['pre_sugerida'];
        $campo[count($campo)] = $fieldest['pre_cbfabricante'];
        $campo[count($campo)] = $fieldest['pre_cblote'];
        $campo[count($campo)] = $fieldest['pre_cbmisturador'];
        $campo[count($campo)] = $fieldest['pre_estdataatual'];
        $campo[count($campo)] = $fieldest['pre_undfabricante'];
        $campo[count($campo)] = $fieldest['pre_undlote'];
        $campo[count($campo)] = $fieldest['pre_undmisturador'];
        $campo[count($campo)] = "</div>";

        $campo[count($campo)] = $fieldest['bt_add'];
        $campo[count($campo)] = $fieldest['bt_del'];

        echo json_encode($campo);
        exit;
    }

    public function storeaprova()
    {
        $ret     = ['erro' => false];
        $postado = $this->request->getPost();

        $pro_id     = intval($postado['pro_id'] ?? 0);
        $pro_codpro = $postado['pro_codpro'];
        $cla_id     = $postado['cla_id'] ?? null;
        $ing_id     = $postado['ing_id'] ?? null;
        $aprova     = intval($postado['aprova'] ?? 0);

        // Verificação obrigatória para aprovação
        if ($aprova === 3 && empty($cla_id)) {
            echo json_encode([
                'erro' => true,
                'msg'  => 24, // ID da mensagem no cadastro
            ]);
            return;
        }

        $db = Database::connect();
        $db->transBegin();

        $ativo = 'I';
        if ($aprova === 3) {
            $ativo = 'A';
        }
        try {
            $sql_aprova = [
                'pro_id'                => $pro_id,
                'stt_id'                => $aprova,
                'pro_ativo'             => $ativo,
                'cla_id'                => $cla_id,
                'pro_codbar_fabricante' => $postado['pro_codbar_fabricante'] ?? null,
                'pro_informacoes'       => $postado['pro_informacoes'] ?? null,
            ];

            if (! $this->produtos->save($sql_aprova)) {
                throw new \Exception('Erro ao atualizar o status do produto.');
            }
            if ($aprova === 3 && ! empty($cla_id)) {
                $dadosclasse = $this->classe->getClasse($cla_id);
                if ($dadosclasse && $dadosclasse->cla_micro === 'N') {
                    $sql_lote = ['stt_id' => 9];
                    $this->common->updateReg('dbProduto', 'pro_sap_lote', "lot_codpro = '" . $pro_codpro . "'", $sql_lote);
                }
            }
            // Atualiza ou insere relacionamento com ingrediente, se houver
            if (! empty($ing_id)) {
                $data_atu = date('Y-m-d H:i:s');
                $sql_ing  = [
                    'ing_id'         => $ing_id,
                    'cla_id'         => $cla_id,
                    'pro_id'         => $pro_id,
                    'inp_atualizado' => $data_atu,
                ];

                $temIngrediente = $this->ingrediente->getProdutoIngrediente($pro_id);

                if ($temIngrediente) {
                    $this->common->updateReg('dbProduto', 'pro_ing_produto', 'pro_id = ' . $pro_id, $sql_ing);
                } else {
                    $this->common->insertReg('dbProduto', 'pro_ing_produto', $sql_ing);
                }
            }

            $db->transCommit();

            // Mensagens e redirecionamento
            if ($aprova === 3) {
                $ret['msg'] = 'Produto Aprovado!!!';
                $ret['url'] = site_url($this->data['controler'] . '/edit/' . $pro_id);
            } else {
                $ret['msg'] = 'Produto Reprovado!!!';
                $ret['url'] = site_url($this->data['controler']);
            }

            session()->setFlashdata('msg', $ret['msg']);
            cache()->clean();
        } catch (\Throwable $e) {
            $db->transRollback();
            $ret['erro'] = true;
            $ret['msg']  = 'Erro ao processar aprovação do produto: ' . $e->getMessage();
        }

        echo json_encode($ret);
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
        // debug($postado, true);

        // Verificação de duplicidade de depósitos
        if (isset($postado['dep_codDep']) && is_array($postado['dep_codDep'])) {
            $depositos = array_filter($postado['dep_codDep']);
            if (count($depositos) !== count(array_unique($depositos))) {
                $ret['erro'] = true;
                $ret['msg']  = 22;
                echo json_encode($ret);
                return;
            }
        }

        $ent = new EntProdutos($postado);
        $ent->fill($postado);

        $exists = 0;
        if (intval($exists) > 0) {
            $ret['erro'] = true;
            $ret['msg']  = 8;
        } else {
            $this->produtos->transBegin();

            $salva = false;

            try {
                // debug($ent, true);
                $salva = $this->produtos->save($ent);
                // debug($this->produtos->getLastQuery(), true);
            } catch (\Throwable $e) {
                $this->produtos->transRollback();
                $ret['erro'] = true;
                $ret['msg']  = 'Erro ao salvar produto: ' . $e->getMessage();
                // // debug($ret);
                // debug($e, true);
            }

            if (! $salva) {
                $ret['erro'] = true;
                $ret['msg']  = implode('<br>', $this->produtos->errors());
                // debug($ret, true);
                // return;
            } else {
                if ($postado['pro_id'] == '') {
                    $postado['pro_id'] = $this->produtos->getInsertId();
                }
                // Grava prc (propriedades do produto)
                $postado['prc_deposito'] = array_merge(...$postado['prc_deposito']);
                $postado['prc_deposito'] = isset($postado['prc_deposito'])
                    ? implode(',', (array) $postado['prc_deposito'])
                    : null;

                $sql_prc = [
                    'pro_id'                => $postado['pro_id'],
                    'prc_cplpro'            => $postado['prc_cplpro'] ?? null,
                    'prc_qtdemb_ceq'        => $postado['prc_qtdemb_ceq'] ?? null,
                    'prc_conf_req'          => $postado['prc_conf_req'] ?? null,
                    'prc_pedido_caixa'      => $postado['prc_pedido_caixa'] ?? null,
                    'prc_etiq_misturador'   => $postado['prc_etiq_misturador'] ?? null,
                    'prc_codbar'            => $postado['prc_codbar'] ?? null,
                    'prc_cor_etiqueta_mist' => $postado['prc_cor_etiqueta_mist'] ?? null,
                    'prc_etiq_produto'      => $postado['prc_etiq_produto'] ?? null,
                    'prc_cor_etiqueta_prod' => $postado['prc_cor_etiqueta_prod'] ?? null,
                    'prc_deposito'          => $postado['prc_deposito'],
                ];

                $salva = ! empty($postado['prc_id'])
                    ? $this->common->updateReg('dbProduto', 'pro_ceq_produto', 'prc_id = ' . $postado['prc_id'], $sql_prc)
                    : $this->common->insertReg('dbProduto', 'pro_ceq_produto', $sql_prc);

                if (! $salva) {
                    $ret['erro'] = true;
                    $ret['msg']  = 'Erro ao salvar dados adicionais do produto.';
                    // debug($ret, true);
                }

                $pro_id   = (int) $postado['pro_id'];
                $data_atu = date('Y-m-d H:i:s');

                // Ingrediente
                if (! empty($postado['ing_id'])) {
                    $sql_ingrediente = [
                        'ing_id'         => $postado['ing_id'],
                        'cla_id'         => $postado['cla_id'],
                        'pro_id'         => $pro_id,
                        'inp_atualizado' => $data_atu,
                    ];

                    $temIngrediente = $this->ingrediente->getProdutoIngrediente($pro_id);

                    if ($temIngrediente) {
                        $this->common->updateReg('dbProduto', 'pro_ing_produto', 'pro_id = ' . $pro_id, $sql_ingrediente);
                    } else {
                        $this->common->insertReg('dbProduto', 'pro_ing_produto', $sql_ingrediente);
                    }

                    $this->common->deleteReg("dbProduto", "pro_ing_produto", "pro_id = $pro_id AND inp_atualizado != '$data_atu'");
                }

                // Grava depósitos
                if (isset($postado['dep_codDep'])) {

                    // garante array
                    $depCodDep = is_array($postado['dep_codDep'])
                        ? $postado['dep_codDep']
                        : [$postado['dep_codDep']];
                    // debug($depCodDep);

                    $this->common->deleteReg(
                        "dbProduto",
                        "pro_est_produto",
                        "pro_id = " . $pro_id
                    );

                    foreach ($depCodDep as $key => $dep) {
                        $pre_cbfabricante  = $postado['pre_cbfabricante'][$key] ?? null;
                        $pre_undfabricante = ($pre_cbfabricante === 'N') ? 'N' : ($postado['pre_undfabricante'][$key] ?? null);

                        $pre_cblote  = $postado['pre_cblote'][$key] ?? null;
                        $pre_undlote = ($pre_cblote === 'N') ? 'N' : ($postado['pre_undlote'][$key] ?? null);

                        $pre_cbmisturador  = $postado['pre_cbmisturador'][$key] ?? null;
                        $pre_undmisturador = ($pre_cbmisturador === 'N') ? 'N' : ($postado['pre_undmisturador'][$key] ?? null);

                        $sql_dep = [
                            'pro_id'             => $pro_id,
                            'dep_codDep'         => $dep,
                            'pre_mindiaanterior' => $postado['pre_mindiaanterior'][$key] ?? null,
                            'pre_minimo'         => $postado['pre_minimo'][$key] ?? null,
                            'pre_maxdiaanterior' => $postado['pre_maxdiaanterior'][$key] ?? null,
                            'pre_porcmaximo'     => $postado['pre_porcmaximo'][$key] ?? null,
                            'pre_maximo'         => $postado['pre_maximo'][$key] ?? null,
                            'pre_sugerida'       => $postado['pre_sugerida'][$key] ?? null,
                            'pre_cbfabricante'   => $pre_cbfabricante,
                            'pre_undfabricante'  => $pre_undfabricante,
                            'pre_cblote'         => $pre_cblote,
                            'pre_undlote'        => $pre_undlote,
                            'pre_cbmisturador'   => $pre_cbmisturador,
                            'pre_undmisturador'  => $pre_undmisturador,
                            'pre_estdataatual'   => $postado['pre_estdataatual'][$key] ?? null,
                            'pre_gestaoestoque'  => $postado['pre_gestaoestoque'][$key] ?? null,
                        ];
                        // debug($sql_dep);
                        $dep_id = $this->common->insertReg('dbProduto', 'pro_est_produto', $sql_dep);
                        // debug($dep_id);

                        if (! $dep_id) {
                            $ret['erro'] = true;
                            $ret['msg']  = 'Erro ao salvar depósitos do produto.';
                            // debug($ret, true);
                            echo json_encode($ret);
                        }
                    }
                }
            }
            // Finaliza com sucesso
            $this->produtos->transCommit();
            cache()->clean();
            session()->setFlashdata('msg', 'Dados do Produto gravado com Sucesso!!!');

            // $ret['msg'] = 'Dados do Produto gravado com Sucesso!!!';
            $ret['url'] = site_url($this->data['controler']);
        }
        echo json_encode($ret);
    }
}
