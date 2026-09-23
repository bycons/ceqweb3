<?php

namespace App\Controllers\Config;

use App\Models\CommonModel;
use App\Controllers\BaseController;
use App\Traits\ForeignKeyUsageChecker;
use App\Entities\Config\EntCfgTela;
use App\Entities\Config\EntCfgTelaLista;
use App\Models\Config\ConfigTelaModel;
use App\Models\Config\ConfigModuloModel;
use App\Models\Config\ConfigUsuarioModel;
use App\Models\Config\ConfigDicDadosModel;
use App\Models\Config\ConfigTelaListaModel;

class CfgTela extends BaseController
{
    use ForeignKeyUsageChecker;

    public $data = [];
    public $permissao = '';
    public $tela;
    public $telalista;
    public $dicionario;
    public $logs;
    public $usuario;
    public $modulo;
    public $common;
    public $model_atual;

    private $tel_id;
    private $tel_modulo;
    private $tel_nome;
    private $tel_icon;
    private $tel_cont;
    private $tel_txtb;
    private $tel_desc;
    private $tel_tabela;
    private $tel_view;
    private $tel_regg;
    private $tel_regc;
    private $tel_camp;
    private $tel_camp_view;
    private $tel_trel;
    private $tel_meto;
    private $tel_codi;
    private $tel_mode;

    /**
     * Construtor da Tela
     * construct
     */
    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->tela       = new ConfigTelaModel();
        $this->telalista    = new ConfigTelaListaModel();
        $this->dicionario = new ConfigDicDadosModel();
        $this->usuario   = new ConfigUsuarioModel();
        $this->modulo    = new ConfigModuloModel();
        $this->common    = new CommonModel();
        $this->model_atual = $this->tela;

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
        $this->data['colunas'] = montaColunasLista($this->data, 'tel_id');
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
        // if (!$telas = cache('telas')) {
        $campos = montaColunasCampos($this->data, 'tel_id');
        $dados_tela = $this->tela->getTelaId();
        $telas = [
            'data' => montaListaColunasEnt($this->data, 'tel_id', $dados_tela, $campos[3]),
        ];
        cache()->save('telas', $telas, 60000);
        // }

        echo json_encode($telas);
    }

    /**
     * Inclusão
     * add
     *
     * @return void
     */
    public function add()
    {
        $this->model_atual = $this->tela;

        // Entity (OBJ)
        $ent = new EntCfgTela();
        $fields = $ent->defCampos(false);

        $secao[0] = 'Dados Gerais';
        $campos[0][0] = $fields['tel_id'];
        $campos[0][1] = $fields['mod_id'];
        $campos[0][2] = $fields['tel_nome'];
        $campos[0][3] = $fields['tel_icon'];
        $campos[0][4] = $fields['tel_txtb'];
        $campos[0][5] = $fields['tel_ident'];
        $campos[0][6] = 'vazio2';
        $campos[0][7] = $fields['tel_cont'];
        $campos[0][8] = $fields['tel_mode'];
        $campos[0][9] = $fields['tel_desc'];

        $secao[1] = 'Regras Gerais';
        $campos[1][0] = $fields['tel_regg'];

        $secao[2] = 'Regras do Cadastro';
        $campos[2][0] = $fields['tel_regc'];

        $this->data['secoes']  = $secao;
        $this->data['campos']  = $campos;
        $this->data['destino'] = 'store';

        echo view('vw_edicao', $this->data);
    }


    public function show($id)
    {
        $this->edit($id, true);
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
        $dados_tela = (array) $this->tela->getTelaId($id)[0];
        $this->model_atual = $this->tela;
        // debug($dados_tela['tel_model']);
        if (isset($dados_tela['tel_model']) && $dados_tela['tel_model'] != null) {
            $model = $dados_tela['tel_model'];
            $compl_model = substr($model, 0, 6);
            $pasta = "App\\Models\\" . $compl_model . "\\";
            // debug($pasta . $model);
            $nomeModel = $pasta . $model;
            $this->model_atual = new $nomeModel();

            // $this->model_atual = new model($pasta . $model);
            // var_dump($this->model_atual);  
        }
        // debug($this->model_atual);
        $tabela = $this->model_atual->table;
        $view   = $this->model_atual->view;
        if (isset($this->model_atual->viewlista)) {
            $view   = $this->model_atual->viewlista;
        }
        // debug($view, true);
        $ent = new EntCfgTela($dados_tela);
        $fields = $ent->defCampos($show, $tabela, $view);
        // debug($fields, true);

        $secao[0] = 'Dados Gerais';
        $campos[0][] = $fields['tel_id'];
        $campos[0][] = $fields['mod_id'];
        $campos[0][] = $fields['tel_nome'];
        $campos[0][] = $fields['tel_icon'];
        $campos[0][] = $fields['tel_txtb'];
        $campos[0][] = $fields['tel_ident'];
        $campos[0][] = 'vazio2';
        $campos[0][] = $fields['tel_cont'];
        $campos[0][] = $fields['tel_mode'];
        $campos[0][] = $fields['tel_desc'];

        $secao[1] = 'Regras Gerais';
        $campos[1][] = $fields['tel_regg'];

        $secao[2] = 'Regras do Cadastro';
        $campos[2][] = $fields['tel_regc'];

        $secao[3] = 'Base de Dados';
        $campos[3][] = $fields['tel_tabela'];
        $campos[3][] = $fields['tel_view'];
        $campos[3][] = $fields['tel_camp'];
        $campos[3][] = $fields['tel_trel'];
        $campos[3][] = $fields['tel_camp_view'];

        $secao[4] = 'Listagem';
        $displ[4] = 'tabela';
        $dados_lista = $this->telalista->getListagem($id);
        // debug($dados_lista, true);
        // $entLista = new EntCfgTelaLista();

        if (count($dados_lista) > 0) {
            for ($c = 0; $c < count($dados_lista); $c++) {
                $fieldslis = $ent->defCamposLista($dados_lista[$c], $show, $c, $view);
                // $this->defCamposLista($dados_lista[$c], $c, $show, $tabela, $view);
                $campos[4][$c][0] = $fieldslis['lis_id'];
                $campos[4][$c][1] = $fieldslis['lis_campo'];
                $campos[4][$c][2] = $fieldslis['lis_rotulo'];
                if (!$show) {
                    $campos[4][$c][3] = $fieldslis['bt_add'];
                    $campos[4][$c][4] = $fieldslis['bt_del'];
                } else {
                    $campos[4][$c][3] = '';
                    $campos[4][$c][4] = '';
                }
            }
        } else {
            $c = 0;
            $fieldslis = $ent->defCamposLista($dados_tela, $show, $c, $view);
            // $this->defCamposLista($dados_tela, 0, $show, $tabela, $view);
            $campos[4][$c][0] = $fieldslis['lis_id'];
            $campos[4][$c][1] = $fieldslis['lis_campo'];
            $campos[4][$c][2] = $fieldslis['lis_rotulo'];
            if (!$show) {
                $campos[4][$c][3] = $fieldslis['bt_add'];
                $campos[4][$c][4] = $fieldslis['bt_del'];
            } else {
                $campos[4][$c][3] = '';
                $campos[4][$c][4] = '';
            }
        }

        $secao[5] = 'Funções';
        $campos[5][0] = $fields['tel_meto'];

        $secao[6] = 'Código Fonte';
        $campos[6][0] = $fields['tel_codi'];

        $this->data['desc_edicao'] = $dados_tela['tel_nome'];

        $this->data['displ'] = $displ;
        $this->data['secoes'] = $secao;
        $this->data['campos'] = $campos;
        $this->data['destino'] = 'store';
        $this->data['script'] = "<script>acerta_botoes_rep('listagem')</script>";

        // BUSCAR DADOS DO LOG
        $this->data['log'] = buscaLog('cfg_tela', $id);

        echo view('vw_edicao', $this->data);
    }

    /**
     * Summary of addCampoLis
     * @param mixed $ind
     * @return never
     */
    public function addCampoLista($id, $ind)
    {
        $dados_tela = (array) $this->tela->getTelaId($id)[0];
        // debug($dados_tela);
        $this->model_atual = $this->tela;
        // debug($dados_tela['tel_model']);
        if (isset($dados_tela['tel_model']) && $dados_tela['tel_model'] != null) {
            $model = $dados_tela['tel_model'];
            $compl_model = substr($model, 0, 6);
            $pasta = "App\\Models\\" . $compl_model . "\\";
            $this->model_atual = model($pasta . $model);
        }
        $tabela = $this->model_atual->table;
        $view   = $this->model_atual->view;
        if (isset($this->model_atual->viewlista)) {
            $view   = $this->model_atual->viewlista;
        }

        $lista['tel_id'] = $dados_tela['tel_id'];

        $ent = new EntCfgTela($dados_tela);
        // $fields = $ent->defCampos(false, $tabela, $view);

        $fieldslis = $ent->defCamposLista($lista, false, $ind, $view);

        $campo[0] = $fieldslis['lis_id'];
        $campo[1] = $fieldslis['lis_campo'];
        $campo[2] = $fieldslis['lis_rotulo'];
        $campo[3] = $fieldslis['bt_add'];
        $campo[4] = $fieldslis['bt_del'];

        echo json_encode($campo);
        exit;
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
        //     $this->tela->delete($id);
        //     $ret['erro'] = false;
        //     session()->setFlashdata('msg', 'Tela Excluída com Sucesso');
        // } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
        //     $ret['erro'] = true;
        //     $ret['msg']  = 'Não foi possível Excluir a Tela, Verifique!<br><br>';
        //     $ret['msg'] .= 'Esta Tela possui relacionamentos em outros cadastros!';
        // }
        // echo json_encode($ret);

        $ret = [];

        try {
            // Checa uso do status em outros bancos
            $this->verificarUsoEmRelacionamentos('cfg_tela', 'tel_id', (int) $id);

            // Soft delete
            $this->tela->delete($id);
            $ret['erro'] = false;
            session()->setFlashdata('msg', 'Tela Excluída com Sucesso');
        } catch (\Exception $e) {
            $ret['erro'] = true;
            $ret['msg']  = 3;
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
        $ret = [];
        $dados = $this->request->getPost();
        // debug($dados);
        $tel_lista = '';
        if (isset($dados['tel_lista'])) {
            $tel_lista = implode(',', $dados['tel_lista']);
        }

        $dados_clas = [
            'tel_id'           => $dados['tel_id'],
            'mod_id'           => $dados['mod_id'],
            'tel_nome'         => $dados['tel_nome'],
            'tel_ident'        => $dados['tel_ident'],
            'tel_icone'        => $dados['tel_icone'],
            'tel_controler'    => $dados['tel_controler'],
            'tel_texto_botao'  => $dados['tel_texto_botao'],
            'tel_model'        => $dados['tel_model'],
            'tel_descricao'    => $dados['tel_descricao'],
            // 'tel_lista'        => $tel_lista,
            // 'tel_filtros'       => $tel_filtros,
            'tel_regras_gerais'       => $dados['tel_regras_gerais'],
            'tel_regras_cadastro'       => $dados['tel_regras_cadastro'],
        ];
        // debug($dados_clas);
        if ($this->tela->save($dados_clas)) {
            $tel_id = $this->tela->getInsertID();
            if ($dados['tel_id'] != '') {
                $tel_id = $dados['tel_id'];
            }
            if (isset($dados['lis_id'])) {
                $data_atu = date('Y-m-d H:i');
                $lis_exc = $this->telalista->excluiCampoLista($tel_id);
                foreach ($dados['lis_id'] as $key => $value) {
                    $dados_lis = [
                        // 'lis_id'             => $dados['lis_id'][$lis],
                        'tel_id'             => $tel_id,
                        'lis_campo'          => $dados['lis_campo'][$key],
                        'lis_rotulo'         => $dados['lis_rotulo'][$key],
                        'lis_atualizado'     => $data_atu,
                    ];
                    $lis_id = $this->telalista->save($dados_lis);
                    if (!$lis_id) {
                        $ret['erro'] = true;
                        $ret['msg'] = 'Não foi possível gravar os Campos da Listagem, Verifique!';
                        break;
                    }
                }
            }
            cache()->clean();
            $ret['erro'] = false;
            $ret['msg'] = 'Tela gravada com Sucesso!!!';
            session()->setFlashdata('msg', $ret['msg']);
            $ret['url'] = site_url($this->data['controler']);
        } else {
            $ret['erro'] = true;
            $ret['msg'] = 'Não foi possível gravar a Tela, Verifique!';
        }
        echo json_encode($ret);
    }
}
