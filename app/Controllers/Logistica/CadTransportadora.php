<?php

namespace App\Controllers\Logistica;

use App\Controllers\BaseController;
use App\Entities\Logistica\EntLogCadTransportadora;
use App\Models\Logist\LogistCadTransportadoraModel;
use App\Models\CommonModel;

class CadTransportadora extends BaseController
{
    public $data = [];
    public $permissao = '';
    public $transportadora;
    public $common;

    public function __construct()
    {
        $this->data      = session()->get('dados_tela') ?? [];
        $this->permissao = $this->data['permissao'];

        $this->transportadora = new LogistCadTransportadoraModel();
        $this->common         = new CommonModel();

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
        $this->data['colunas']   = montaColunasLista($this->data, 'trp_id');
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
        $campos = montaColunasCampos($this->data, 'trp_id');
        $dados  = $this->transportadora->getListaCompleta();

        $ret         = new \stdClass();
        $listaFinal  = [];

        foreach ($dados as $index => $nov) {
            $this->data['exclusao'] = true;
            $this->data['edicao']   = true;

            // Converte 1/0 para A/I 
            $nov->trp_ativo = $nov->trp_ativo ? 'A' : 'I';

            $linha        = montaListaColunasEnt($this->data, 'trp_id', [$nov], $campos[1]);
            $listaFinal[] = $linha[0];
        }

        $ret->data = $listaFinal;

        return $this->response->setJSON($ret);
    }

    /**
     * Inclusão
     * add
     *
     * @param mixed $id
     * @return void
     */
    public function add()
    {
        $trp    = new EntLogCadTransportadora();
        $fields = $trp->campos;

        $camposHorario = $trp->defCamposHorario();

        // DIVS DOS BLOCOS
        $blocoAbre  = "<div class='col-4 float-start border-end pe-3 ps-0'>";
        $blocoFecha = "</div>";

        // LINHAS
        $linhaDiasAbre  = "<div class='linha-dias-semana' style='display:flex; flex-wrap:wrap; width:100%;'>";
        $linhaDiasFecha = "</div>";

        $this->data['title']    = 'Transportadora';
        $this->data['secoes']   = ['Dados Gerais', 'Horários'];
        $this->data['displ'][1] = 'tabela';

        $this->data['campos'] = [
            [
                $fields['trp_id'],
                $fields['trp_ativo'],
                $fields['trp_nome'],
                $fields['trp_apelido'],
                $fields['trp_telefone'],
            ],
            [
                0 => [
                    $camposHorario['tho_id'],
                    $camposHorario['trp_id'],

                    // BLOCO 1
                    $blocoAbre,
                    $camposHorario['cid_id'],
                    $blocoFecha,
                    
                    // BLOCO 2 
                    $blocoAbre,
                    $camposHorario['tit_dias_semana'],
                    $linhaDiasAbre,
                    $camposHorario['tho_domingo'],
                    $camposHorario['tho_segunda'],
                    $camposHorario['tho_terca'],
                    $camposHorario['tho_quarta'],
                    $camposHorario['tho_quinta'],
                    $camposHorario['tho_sexta'],
                    $camposHorario['tho_sabado'],
                    $camposHorario['tho_atende_feriado'],
                    $linhaDiasFecha,
                    $blocoFecha,
                    
                    // BLOCO 3
                    $blocoAbre,
                    $camposHorario['tit_previsao_horarios'],
                    $camposHorario['tho_hora_saida'],
                    $camposHorario['tho_hora_chegada'],
                    $blocoFecha,
                    
                    // BLOCO 4 
                    $blocoAbre,
                    $camposHorario['tho_despacho'],
                    $blocoFecha,
                    
                    $camposHorario['bt_addtho'],
                    $camposHorario['bt_deltho'],
                ],
            ],
        ];

        $this->data['destino'] = 'store';
        $this->data['script']  = "<script>
                    acerta_botoes_rep('horarios');
                    </script>";
        echo view('vw_edicao', $this->data);
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
        $dados = $this->transportadora->getTransportadora($id);
        if (! $dados) {
            throw new \Exception('Transportadora não encontrada');
        }

        $linhasHor = $this->transportadora->getHorariosPorTransportadora((int) $id);

        $trp    = new EntLogCadTransportadora((array) $dados, false);
        $fields = $trp->campos;

        // DIVS DOS BLOCOS
        $blocoAbre  = "<div class='col-4 float-start border-end pe-3 ps-0'>";
        $blocoFecha = "</div>";

        // LINHAS
        $linhaDiasAbre  = "<div class='linha-dias-semana' style='display:flex; flex-wrap:wrap; width:100%;'>";
        $linhaDiasFecha = "</div>";

        $this->data['secoes']   = ['Dados Gerais', 'Horários'];
        $this->data['displ'][1] = 'tabela';

        $blocosHorario = [];

        if (empty($linhasHor)) {
            $linhasHor = [(object) []];
        }

        foreach ($linhasHor as $pos => $linha) {
            $dadosHor      = (array) $linha;
            $camposHorario = $trp->defCamposHorario($dadosHor, false, $pos);

            $blocosHorario[$pos] = [
                $camposHorario['tho_id'],
                $camposHorario['trp_id'],

               // BLOCO 1
               $blocoAbre,
               $camposHorario['cid_id'],
               $blocoFecha,
               
               // BLOCO 2 
               $blocoAbre,
               $camposHorario['tit_dias_semana'],
               $linhaDiasAbre,
               $camposHorario['tho_domingo'],
               $camposHorario['tho_segunda'],
               $camposHorario['tho_terca'],
               $camposHorario['tho_quarta'],
               $camposHorario['tho_quinta'],
               $camposHorario['tho_sexta'],
               $camposHorario['tho_sabado'],
               $camposHorario['tho_atende_feriado'],
               $linhaDiasFecha,
               $blocoFecha,
               
               // BLOCO 3
               $blocoAbre,
               $camposHorario['tit_previsao_horarios'],
               $camposHorario['tho_hora_saida'],
               $camposHorario['tho_hora_chegada'],
               $blocoFecha,
               
               // BLOCO 4
               $blocoAbre,
               $camposHorario['tho_despacho'],
               $blocoFecha,
               
               $camposHorario['bt_addtho'],
               $camposHorario['bt_deltho'],
            ];
        }

        $this->data['campos'] = [
            [
                $fields['trp_id'],
                $fields['trp_ativo'],
                $fields['trp_nome'],
                $fields['trp_apelido'],
                $fields['trp_telefone'],
            ],
            $blocosHorario,
        ];

        $this->data['destino']     = "store";
        $this->data['title']       = 'Transportadora';
        $this->data['desc_edicao'] = ' Nº ' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->data['script']      = "<script>
                    acerta_botoes_rep('horarios');
                    </script>";

        echo view('vw_edicao', $this->data);
    }

    /**
     * Summary of addCampoHorario - Horário de Atendimento
     * @param mixed $ind
     * @return never
     */
    public function addCampoHorario($ind)
    {
        $trp = new EntLogCadTransportadora();

        // DIVS DOS BLOCOS
        $blocoAbre  = "<div class='col-4 float-start border-end pe-3 ps-0'>";
        $blocoFecha = "</div>";

        // LINHAS DOS BLOCOS
        $linhaDiasAbre  = "<div class='linha-dias-semana' style='display:flex; flex-wrap:wrap; width:100%;'>";
        $linhaDiasFecha = "</div>";

        $camposHorario = $trp->defCamposHorario([], false, $ind);
        $campo = [
            $camposHorario['tho_id'],
            $camposHorario['trp_id'],

            // BLOCO 1
            $blocoAbre,
            $camposHorario['cid_id'],
            $blocoFecha,
            
            // BLOCO 2
            $blocoAbre,
            $camposHorario['tit_dias_semana'],
            $linhaDiasAbre,
            $camposHorario['tho_domingo'],
            $camposHorario['tho_segunda'],
            $camposHorario['tho_terca'],
            $camposHorario['tho_quarta'],
            $camposHorario['tho_quinta'],
            $camposHorario['tho_sexta'],
            $camposHorario['tho_sabado'],
            $camposHorario['tho_atende_feriado'],
            $linhaDiasFecha,
            $blocoFecha,
            
            // BLOCO 3
            $blocoAbre,
            $camposHorario['tit_previsao_horarios'],
            $camposHorario['tho_hora_saida'],
            $camposHorario['tho_hora_chegada'],
            $blocoFecha,
            
            
            // BLOCO 4
            $blocoAbre,
            $camposHorario['tho_despacho'],
            $blocoFecha,
            
            $camposHorario['bt_addtho'],
            $camposHorario['bt_deltho'],
        ];

        echo json_encode($campo);
        exit;
    }

    /**
     * AtivInativ
     * Ativar e Inativar
     *
     * @param mixed $id
     * @return void
     */
    public function ativinativ($id, $tipo)
    {
        $ret = [];
        try {
            if ($tipo == 1) {
                $dad_atin = [
                    'trp_ativo' => 1,
                ];
                $msg = 'Transportadora Ativada com Sucesso';
            } else {
                $dad_atin = [
                    'trp_ativo' => 0,
                ];
                $msg = 'Transportadora Desativada com Sucesso';
            }

            $this->transportadora->update($id, $dad_atin);
            $ret['erro'] = false;
            session()->setFlashdata('msg', $msg);
            $ret['msg'] = $msg;
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $ret['erro'] = true;
            $ret['msg']  = 14;
        } catch (\Exception $e) {
            log_message('error', 'Erro ao alterar status da Transportadora: ' . $e->getMessage());
            $ret['erro'] = true;
            $ret['msg']  = 14;
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
        try {
            $trp = $this->transportadora->getTransportadora($id);

            if (! $trp) {
                throw new \Exception('Transportadora não encontrada');
            }

            $this->transportadora->delete($id);

            return $this->response->setJSON([
                'erro' => false,
                'msg'  => 'Transportadora Excluída com Sucesso',
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'erro' => true,
                'msg'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gravação
     * store
     *
     * @return void
     */
    public function store()
    {
        $ret     = ['erro' => false];
        $postado = $this->request->getPost();

        $dadosTrp = [
            'trp_nome'     => $postado['trp_nome'] ?? '',
            'trp_apelido'  => $postado['trp_apelido'] ?? '',
            'trp_telefone' => $postado['trp_telefone'] ?? '',
            'trp_ativo'    => $postado['trp_ativo'] ?? 1,
        ];

        $trpId  = empty($postado['trp_id'][0]) ? null : (int) $postado['trp_id'][0];
        $exists = $this->common->verificaUnico($this->transportadora, 'trp_nome', $dadosTrp['trp_nome'], 'trp_id', $trpId);

        if ($exists > 0) {
            return $this->response->setJSON([
                'erro' => true,
                'msg'  => 8,
            ]);
        }

        // Monta as linhas de horário
        $qtdLinhas = is_array($postado['cid_id'] ?? null) ? count($postado['cid_id']) : 0;
        $linhasHor = [];

        for ($i = 0; $i < $qtdLinhas; $i++) {
            if (empty($postado['cid_id'][$i])) {
                continue;
            }

            $linhasHor[] = [
                'tho_id'             => $postado['tho_id'][$i] ?? null,
                'cid_id'             => $postado['cid_id'][$i] ?? null,
                'tho_despacho'       => mb_substr($postado['tho_despacho'][$i] ?? '', 0, 1),
                'tho_hora_saida'     => $postado['tho_hora_saida'][$i] ?? null,
                'tho_hora_chegada'   => $postado['tho_hora_chegada'][$i] ?? null,
                'tho_domingo'        => isset($postado['tho_domingo'][$i]) ? 1 : 0,
                'tho_segunda'        => isset($postado['tho_segunda'][$i]) ? 1 : 0,
                'tho_terca'          => isset($postado['tho_terca'][$i]) ? 1 : 0,
                'tho_quarta'         => isset($postado['tho_quarta'][$i]) ? 1 : 0,
                'tho_quinta'         => isset($postado['tho_quinta'][$i]) ? 1 : 0,
                'tho_sexta'          => isset($postado['tho_sexta'][$i]) ? 1 : 0,
                'tho_sabado'         => isset($postado['tho_sabado'][$i]) ? 1 : 0,
                'tho_atende_feriado' => isset($postado['tho_atende_feriado'][$i]) ? 1 : 0,
            ];
        }

        $this->transportadora->transBegin();

        try {
            $trpId = $this->transportadora->salvarComHorarios($dadosTrp, $linhasHor, $trpId);

            if (! $trpId) {
                $this->transportadora->transRollback();
                $ret['erro'] = true;
                $ret['msg']  = implode('<br>', $this->transportadora->errors());
            } else {
                $this->transportadora->transCommit();

                $ret['erro'] = false;
                $ret['msg']  = 'Transportadora gravada com sucesso!';
                session()->setFlashdata('msg', $ret['msg']);
                $ret['url'] = site_url($this->data['controler']);
            }
        } catch (\Throwable $e) {
            $this->transportadora->transRollback();
            $ret = [
                'erro' => true,
                'msg'  => $e->getMessage() ?: 'Erro ao salvar Transportadora.',
            ];
        }

        return $this->response->setJSON($ret);
    }
}