<?php

namespace App\Controllers\Fornecedores;

use App\Controllers\BaseController;
use App\Entities\Fornecedores\EntOcoNotifDesvio;
use App\Models\Fornec\FornecNotifDesvioModel;

/**
 * T42 — Fornecedores > Desvio de Qualidade (oco_notif_desvio).
 *
 * Espelha a estrutura de App\Controllers\Ocorrencia\OcoOcorrencia. Não existe
 * método add(): o registro é criado automaticamente pela integração com T11
 * (RN02.3 — ver App\Controllers\Ocorrencia\OcoTrataOcorrencia::gerarNotificacaoDesvio()),
 * sempre com status "Pendente". O usuário só edita (Local/Descreva) e salva;
 * o Salvar já grava e conclui na mesma transação (RN03.15 / decisão 3.3 do
 * documento de desenvolvimento).
 */
class NotifDesvio extends BaseController
{
    public $data = [];
    public $permissao = '';
    public $model;

    public function __construct()
    {
        $this->data      = session()->get('dados_tela') ?? session()->getFlashdata('dados_tela') ?? [];
        $this->permissao = $this->data['permissao'] ?? '';
        $this->model     = new FornecNotifDesvioModel();

        if (($this->data['erromsg'] ?? '') != '') {
            $this->__erro();
        }
    }

    public function __erro()
    {
        echo view('vw_semacesso', $this->data);
    }

    /**
     * index
     */
    public function index()
    {
        $this->data['colunas']   = montaColunasLista($this->data, 'ndv_id');
        $this->data['url_lista'] = base_url($this->data['controler'] . '/lista');
        $this->data['scripts']   = 'my_fornecedores';
        echo view('vw_lista', $this->data);
    }

    /**
     * lista
     */
    public function lista()
    {
        $campos = montaColunasCampos($this->data, 'ndv_id');
        $dados  = $this->model->getListaCompleta();

        $this->data['allconsulta'] = true;

        // RN03.5 — "Usuário" vem de T11 (quem gerou a ocorrência de
        // origem). `vw_oco_ocorrencia_completa_relac` não expõe usu_nome
        // (conferido via `php spark db:table ... --metadata` em 2026-07-12);
        // resolvido pelo log de auditoria de oco_ocorrencia, mesmo padrão de
        // OcoOcorrencia::lista().
        $ocoIds   = array_map(fn($n) => $n->oco_id, $dados);
        $logCriou = buscaLogTabelaFirst('oco_ocorrencia', $ocoIds);

        foreach ($dados as $nov) {
            $nov->usu_nome    = $logCriou[$nov->oco_id]['usua_alterou'] ?? '';
            $nov->acao_person = [];

            // Botão Imprimir (etiqueta) — sempre disponível, não bloqueante
            // (RN03.15 / decisão 3.1 do documento de desenvolvimento)
            $urlEtiqueta        = base_url($this->data['controler'] . '/GeraEtiqueta/' . $nov->ndv_id . '/1');
            $nov->acao_person[] = "
                <button class='btn btn-outline-dark btn-sm border-0 mx-0 fs-0'
                    title='Imprimir'
                    onclick='geraEiquetaGenerico(this, \"{$urlEtiqueta}\")'>
                    <i class='fa-solid fa-print'></i>
                </button>
            ";

            // Edição/exclusão já são controladas pelo mecanismo genérico via
            // stt_edicao/stt_exclusao (montaListaColunasEnt(), a partir da
            // VIEW vw_oco_notif_desvio_relac) — sem necessidade de lógica
            // adicional aqui.
        }

        return $this->response->setJSON([
            'data' => montaListaColunasEnt($this->data, 'ndv_id', $dados, $campos[1]),
        ]);
    }

    /**
     * show
     */
    public function show($id)
    {
        $dados = $this->model->getNotifDesvio($id);
        if (!$dados) {
            throw new \Exception('Desvio de Qualidade não encontrado');
        }

        // RN03.5 — mesmo raciocínio de lista(): usu_nome vem do log de
        // oco_ocorrencia (T11), por oco_id — não existe na VIEW nem é o
        // usuário que criou o registro de T42.
        $log             = buscaLogTabela('oco_ocorrencia', [$dados->oco_id]);
        $dados->usu_nome = $log[$dados->oco_id]['usua_alterou'] ?? null;
        $entity = new EntOcoNotifDesvio((array) $dados, true);

        $colunas = [
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote <br>Validade',
            'Quantidade',
        ];

        $alinha = [
            'center',
            'start',
            'start',
            'center',
            'end',
        ];
        // debug($dados, true);

        $prods[] = [
            $dados->pro_codpro,
            $dados->pro_despro,
            $dados->fab_apeFab,
            $dados->lot_lote . ' <br>' . data_br($dados->lot_validade),
            $dados->oco_qtd
        ];

        $data = [
            'id'    => $id,
            'show'     => true,
            'colunas'  => $colunas,
            'alinha'   => $alinha,
            'produtos' => $prods,
            'maxHeig'  => '20vh'
        ];

        // debug($data, true);

        $campos[0] = [];
        $campos[0][]     = $entity->campos['ndv_id'];
        $campos[0][]     = $entity->campos['oco_id'];
        $campos[0][]     = $entity->campos['ndv_numero'];
        $campos[0][]     = $entity->campos['tpo_nome'];
        $campos[0][]     = $entity->campos['sut_nome'];
        $campos[0][]     = $entity->campos['oco_data_show'];
        $campos[0][]     = $entity->campos['usu_nome'];
        $campos[0][]     = $entity->campos['tel_nome'];
        $campos[0][]     = $entity->campos['ndv_local'];
        $campos[0][]     = $entity->campos['ndv_descreva'];
        // debug($campos, true);

        $campos[0][]     = view('partials/pw_show_produtos', $data);
        // debug($campos, true);

        $this->data['desc_edicao'] = 'Desvio n° ' . str_pad($id, 6, '0', STR_PAD_LEFT)
            . ' - ' . fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome, 1);
        $this->data['secoes']  = ['Dados Gerais'];
        $this->data['campos']  = $campos;
        $this->data['destino'] = '';

        echo view('vw_edicao', $this->data);
    }

    /**
     * edit
     */
    public function edit($id)
    {
        $dados = $this->model->getNotifDesvio($id);

        if (!$dados) {
            throw new \Exception('Desvio de Qualidade não encontrado');
        }

        // Bloqueio server-side: só edita enquanto Pendente (mesmo padrão de
        // OcoOcorrencia::edit() para req_id/rep_id) — o botão "Alterar" já é
        // ocultado no front pelo stt_edicao da view, isso cobre requisição forjada.
        if (trim($dados->stt_nome ?? '') !== 'Pendente') {
            throw new \Exception('Alteração não Permitida');
        }

        $entity = new EntOcoNotifDesvio((array) $dados, false);

        $colunas = [
            'Cód ERP',
            'Descrição',
            'Fabricante',
            'Lote <br>Validade',
            'Quantidade',
        ];

        $alinha = [
            'center',
            'start',
            'start',
            'center',
            'end',
        ];

        $prods[] = [
            $dados->pro_codpro,
            $dados->pro_despro,
            $dados->fab_apeFab,
            $dados->lot_lote . ' <br>' . data_br($dados->lot_validade),
            $dados->oco_qtd
        ];

        $data = [
            'id'    => $id,
            'show'     => true,
            'colunas'  => $colunas,
            'alinha'   => $alinha,
            'produtos' => $prods,
            'maxHeig'  => '20vh'
        ];


        $campos[0] = [];
        $campos[0][]     = $entity->campos['ndv_id'];
        $campos[0][]     = $entity->campos['oco_id'];
        $campos[0][]     = $entity->campos['ndv_numero'];
        $campos[0][]     = $entity->campos['tpo_nome'];
        $campos[0][]     = $entity->campos['sut_nome'];
        $campos[0][]     = $entity->campos['oco_data_show'];
        $campos[0][]     = $entity->campos['usu_nome'];
        $campos[0][]     = $entity->campos['tel_nome'];
        $campos[0][]     = $entity->campos['ndv_local'];
        $campos[0][]     = $entity->campos['ndv_descreva'];

        $campos[0][]     = view('partials/pw_show_produtos', $data);

        $this->data['desc_edicao'] = 'Desvio n° ' . str_pad($id, 6, '0', STR_PAD_LEFT)
            . ' - ' . fmtEtiquetaCor($dados->stt_cor, $dados->stt_nome, 1);
        $this->data['secoes']  = ['Dados Gerais'];
        $this->data['campos']  = $campos;
        $this->data['destino']     = 'store';
        $this->data['desc_edicao'] = 'Desvio n° ' . str_pad($id, 6, '0', STR_PAD_LEFT);

        echo view('vw_edicao', $this->data);
    }

    /**
     * store — Salvar já grava e conclui na mesma transação (RN03.15 / decisão 3.3)
     */
    public function store()
    {
        $postado = $this->request->getPost();
        $ret     = [];

        try {
            if (empty($postado['ndv_id'])) {
                throw new \Exception('Registro de Desvio de Qualidade não informado');
            }

            $existente = $this->model->getNotifDesvio($postado['ndv_id']);
            if (!$existente) {
                throw new \Exception('Desvio de Qualidade não encontrado');
            }

            // Bloqueio server-side — só grava enquanto Pendente
            if (trim($existente->stt_nome ?? '') !== 'Pendente') {
                return $this->response->setJSON([
                    'erro' => true,
                    'msg'  => 15,
                ]);
            }

            $db = \Config\Database::connect('dbOcorrencia');
            $db->transStart();

            // Atualiza apenas os campos editáveis + status — preserva
            // usu_criou original (não reenviado pelo form, ver RN03.7/RN03.14).
            $dadosUpdate = [
                'ndv_local'    => $postado['ndv_local'] ?? '',
                'ndv_descreva' => $postado['ndv_descreva'] ?? '',
                'stt_id'       => $this->model->getStatusId('Concluído'),
            ];

            if (!$this->model->update($postado['ndv_id'], $dadosUpdate)) {
                $db->transRollback();
                $ret['erro'] = true;
                $ret['msg']  = $this->model->errors();
                return $this->response->setJSON($ret);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                $ret['erro'] = true;
                $ret['msg']  = 'Não foi possível concluir o Desvio de Qualidade, verifique!';
                return $this->response->setJSON($ret);
            }

            $ret['erro'] = false;
            $ret['msg']  = 'Desvio de Qualidade gravado e concluído com sucesso!';
            session()->setFlashdata('msg', $ret['msg']);
            $ret['url'] = site_url($this->data['controler']);
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = $e->getMessage();
        }

        return $this->response->setJSON($ret);
    }

    /**
     * delete — RN não prevê exclusão manual (registro é sempre gerado por
     * integração automática de T11); bloqueado também no server-side, além
     * de stt_exclusao='N' já ocultar o botão no front.
     */
    public function delete($id)
    {
        return $this->response->setJSON([
            'erro' => true,
            'msg'  => 'Exclusão não permitida para Desvio de Qualidade',
        ]);
    }

    /**
     * GeraEtiqueta — mirror exato de Estoque\EtqProduto::GeraEtiqueta().
     * Consumido pelo JS genérico gerarEtiquetaZPL() (ver
     * public/assets/jscript/my_fornecedores.js).
     */
    public function GeraEtiqueta(int $id, int $qtia): string
    {
        $redis     = \Config\Services::redis();
        $sessionId = session_id();

        $chave = "etq:{$sessionId}:" . md5('ndv_' . $id . '_' . $qtia);

        $cached = $redis->get($chave);

        if (!$cached) {
            $dados = $this->model->getNotifDesvio($id);

            if (!$dados) {
                return json_encode([
                    'erro' => 'Desvio de Qualidade não encontrado',
                ]);
            }

            $etiquetas = array_fill(0, $qtia, $dados);

            $redis->setex($chave, 900, json_encode($etiquetas));
            $redis->sAdd("etq_session:{$sessionId}", $chave);
        }

        return json_encode([
            'link'  => base_url('/CriaEtiquetaZPL/emiteEtiqueta'),
            'chave' => $chave,
        ]);
    }
}
