<?php

namespace App\Models\Microb;

use App\Libraries\MyCampo;
use App\Models\ArquivoMonModel;
use App\Models\CommonModel;
// use App\Models\Config\ConfigCorModel;
// use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\LogMonModel;
// use App\Models\Produt\ProdutClasseModel;
// use App\Models\Produt\ProdutFabricanteModel;
// use App\Models\Produt\ProdutFamiliaModel;
// use App\Models\Produt\ProdutIngredienteModel;
// use App\Models\Produt\ProdutOrigemModel;
// use App\Models\Produt\ProdutProdutoModel;
use CodeIgniter\Model;

class MicrobAnaliseModel extends Model
{
    protected $DBGroup          = 'dbProduto';
    protected $table            = 'pro_mic_analise';
    protected $view             = 'vw_pro_mic_analise_relac_v2';
    protected $primaryKey       = 'ana_id';
    // protected $useAutoIncremodt = false;

    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'ana_id',
        'pro_id',
        'lot_id',
        'ana_qtde',
        'ana_qtde_micro',
        'ana_data',
        'ana_laudo',
        'ana_obs',
        'ana_data_result',
        'ana_usu_id_result',
        'stt_id',
        'ana_liberarsemmicro',
        'ana_lotemb',
        'ana_datalotemb',
        'ana_descmetodo',
        'req_id',
        'ana_liberar',
        'ana_reprovar',
        'tmo_id',
        'tmo_id_rep'

    ];

    // Callbacks
    protected $allowCallbacks = true;

    protected $afterInsert   = ['depoisInsert'];
    protected $afterUpdate   = ['depoisUpdate'];
    protected $afterDelete   = ['depoisDelete'];

    protected $logdb;

    /**
     * This method saves the session "usu_id" value to "created_by" and "updated_by" array
     * elements before the row is inserted into the database.
     *
     */
    protected function depoisInsert(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'];
        $log = $logdb->insertLog($this->table, 'Incluído', $registro, $data['data']);
        return $data;
    }

    /**
     * This method saves the session "usu_id" value to "updated_by" array element before
     * the row is inserted into the database.
     *
     */
    protected function depoisUpdate(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'][0];
        $log = $logdb->insertLog($this->table, 'Alteração', $registro, $data['data']);
        return $data;
    }

    /**
     * This method saves the session "usu_id" value to "deletede_by" array element before
     * the row is inserted into the database.
     *
     */
    protected function depoisDelete(array $data)
    {
        $logdb = new LogMonModel();
        $registro = $data['id'][0];
        $log = $logdb->insertLog($this->table, 'Excluído', $registro, $data['data']);
        return $data;
    }

    public function getListaAnalise($ana_id = false, $stt_id = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($ana_id) {
            $builder->where('ana_id', $ana_id);
        }
        if ($stt_id) {
            $builder->where('stt_id', $stt_id);
        }
        $builder->orderBy('stt_ordem, pro_despro');
        $ret = $builder->get()->getResultArray();
        // debug($this->db->getLastQuery());
        return $ret;
    }

    public function getListaAnaliseSemReq($ana_id = false, $stt_id = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($ana_id) {
            $builder->where('ana_id', $ana_id);
        }
        if ($stt_id) {
            $builder->where('stt_id', $stt_id);
        }
        $builder->groupStart();
        $builder->where('req_id', null);
        $builder->orWhere('req_id', 0);
        $builder->groupEnd();
        $builder->orderBy('stt_ordem, pro_despro');
        $ret = $builder->get()->getResultArray();
        // debug($this->db->getLastQuery());
        return $ret;
    }

    public function getListaAnaliseComReq($req_id)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        $builder->where('req_id', $req_id);
        // $builder->where('stt_id', 14); // somente status realizada
        $builder->orderBy('stt_ordem, pro_despro');
        $ret = $builder->get()->getResultArray();
        // debug($this->db->getLastQuery());
        return $ret;
    }

    public function atualizaEvento()
    {
        $db = db_connect('dbProduto');
        $db->query('ALTER EVENT atualiza_produto_mic_analise_mv ON SCHEDULE AT NOW()');
        return;
    }


    public function getAnalise($pro_id = false, $fields = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        if (!$fields) {
            $builder->select('*');
        } else {
            $builder->select($fields);
        }
        if ($pro_id) {
            $builder->where('pro_id', $pro_id);
        }
        $builder->orderBy('stt_ordem, lot_entrada DESC');
        // $builder->where('pro_ativo', 'A');
        // $builder->where('stt_id >', 2);
        // $sql = $builder->getCompiledSelect();
        // debug($sql, true);
        return $builder->get()->getResultArray();
    }

    public function getAnaliseLotemb($lote = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($lote != '') {
            $builder->where('TRIM(ana_lotemb)', trim($lote));
        } else {
            $builder->groupStart();
            $builder->where('TRIM(ana_lotemb)', trim($lote));
            $builder->orWhere('ana_lotemb', NULL);
            $builder->groupEnd();
        }
        $builder->where('req_id', NULL);
        $builder->where('stt_id', 14);

        $ret = $builder->get()->getResultArray();
        $sql = $this->db->getLastQuery();
        // debug($sql, true);

        return $ret;

        // $builder->where('pro_ativo', 'A');
    }

    public function getAnaliseCod($pro_cod = false, $lot_id = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($pro_cod) {
            $builder->where('TRIM(pro_codpro)', trim($pro_cod));
        }
        if ($lot_id) {
            $builder->where('lot_id', $lot_id);
        }
        $ret = $builder->get()->getResultArray();
        $sql = $this->db->getLastQuery();
        // debug($sql, true);

        return $ret;

        // $builder->where('pro_ativo', 'A');
    }

    public function getAnaliseCodIn($pro_cod = false, $lot_id = false)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($pro_cod) {
            $builder->whereIn('TRIM(pro_codpro)', $pro_cod);
        }
        if ($lot_id) {
            $builder->whereIn('lot_id', $lot_id);
        }
        $ret = $builder->get()->getResultArray();
        $sql = $this->db->getLastQuery();
        // debug($sql, true);

        return $ret;

        // $builder->where('pro_ativo', 'A');
    }

    public function getAnaliseClasse($classe)
    {
        $db = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');

        $builder->select('*');
        if ($classe) {
            $builder->where('cla_id', $classe);
        }
        $builder->where('pro_ativo', 'A');
        // $builder->where('stt_id >', 2);
        $builder->groupStart();
        $builder->where('ing_id IS NULL');
        $builder->orWhere('cla_ing_id = cla_id');
        $builder->groupEnd();
        // $sql = $builder->getCompiledSelect();
        // debug($sql, true);
        return $builder->get()->getResultArray();
    }

    public function defCampos($dados = false, $show = false)
    {
        $opcoes         = new CommonModel();
        $ret = [];


        $id           =  new MyCampo('pro_mic_analise', 'ana_id', false);
        $id->valor    = $dados['ana_id'];
        $ret['ana_id']    = $id->crOculto();

        $sttid           =  new MyCampo('pro_mic_analise', 'stt_id', false);
        $sttid->valor    = $dados['stt_id'];
        $ret['stt_id']    = $sttid->crOculto();

        $entr           =  new MyCampo('pro_sap_lote', 'lot_entrada');
        $entr->valor    = $dados['lot_entrada'] ?? '';
        $entr->leitura  = true;
        $entr->largura    = 20;
        $ret['lot_entrada'] = $entr->crInput();

        $opc_produ      = $opcoes->getListaOpcoes('dbProduto', 'pro_sap_produto', ['pro_despro', 'pro_id'], 'pro_id = ' . $dados['pro_id']);
        // $produtos       =  new ProdutProdutoModel();
        // $lst_produ      = $produtos->getProduto($dados['pro_id']);
        // $opc_produ       = array_column($lst_produ,'pro_despro','pro_id');

        $proid             = new MyCampo('pro_mic_analise', 'pro_id', true);
        $proid->valor = $proid->selecionado  = $dados['pro_id'] ?? '';
        $proid->leitura     = true;
        $proid->largura    = 60;
        $proid->opcoes      = $opc_produ;
        $ret['pro_id'] = $proid->crSelect();


        $opc_fabr      = $opcoes->getListaOpcoes('dbProduto', 'pro_sap_fabricante', ['fab_apeFab', 'fab_codFab'], 'fab_codFab = ' . $dados['fab_codFab']);
        // $fabricantes        =  new ProdutFabricanteModel();
        // $lst_fabrics    = $fabricantes->getFabricante($dados['fab_codFab']);
        // $opc_fabr       = array_column($lst_fabrics,'fab_apeFab','fab_codFab');
        // debug($opc_fabr, true);
        $fabr           =  new MyCampo('pro_sap_fabricante', 'fab_apeFab');
        $fabr->valor = $fabr->selecionado = $dados['fab_codFab'] ?? '';
        $fabr->leitura     = true;
        $fabr->largura     = 60;
        $fabr->label       = 'Fabricante';
        $fabr->opcoes      = $opc_fabr;
        $ret['fab_apeFab'] = $fabr->crSelect();

        $lote           =  new MyCampo('pro_sap_lote', 'lot_lote');
        $lote->valor    = $dados['lot_lote'] ?? '';
        $lote->leitura  = true;
        $lote->largura    = 20;
        $ret['lot_lote'] = $lote->crInput();

        $loti           =  new MyCampo('pro_sap_lote', 'lot_id');
        $loti->valor    = $dados['lot_id'] ?? '';
        $ret['lot_id'] = $loti->crOculto();

        $vali           =  new MyCampo('pro_sap_lote', 'lot_validade');
        $vali->valor    = $dados['lot_validade'] ?? '';
        $vali->leitura  = false;
        $vali->largura    = 20;
        $ret['lot_validade'] = $vali->crInput();


        $aqtd               =  new MyCampo('pro_mic_analise', 'ana_qtde');
        $aqtd->valor        = $dados['ana_qtde'] ?? 0;
        $aqtd->maximo       = 99999;
        $aqtd->leitura      = $show;
        $aqtd->largura      = 30;
        $aqtd->tamanho      = 40;
        $ret['ana_qtde']    = $aqtd->crInput();

        $aqtm               =  new MyCampo('pro_mic_analise', 'ana_qtde_micro');
        $aqtm->valor        = ($dados['ana_qtde_micro'] != '') ? $dados['ana_qtde_micro'] : 0;
        $aqtm->largura      = 30;
        $aqtm->tamanho      = 40;
        $aqtm->minimo       = 1;
        $aqtm->maximo       = 99;
        $aqtm->leitura      = $show;
        $aqtm->obrigatorio  = true;
        $ret['ana_qtde_micro']  = $aqtm->crInput();

        $final          = new MyCampo();
        $final->nome    = 'bt_finalizar';
        $final->id      = 'bt_finalizar';
        $final->i_cone  = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">
                            <i class="fa-solid fa-check" style="font-size: 2rem;" aria-hidden="true"></i></div>';
        $final->i_cone  .= '<div class="align-items-start txt-bt-manut">Finalizar</div>';
        $final->place    = 'Finalizar a Análise';
        $final->funcChan = 'submeter(\'/Analise/finalizar/\')';
        $final->classep  = 'btn-secondary bt-manut btn-sm mb-2 float-end';
        $ret['bt_finalizar'] = $final->crBotao();

        return $ret;
    }

    public function defCamposAnalise($dados = false, $show = false)
    {
        $ret = [];
        $simnao['S'] = 'Sim';
        $simnao['N'] = 'Não';

        $met           =  new MyCampo('pro_classe', 'cla_metodanalise', false);
        $met->valor = $met->selecionado    = $dados['cla_metodanalise'];
        $met->leitura  = true;
        $met->opcoes   = $simnao;
        $met->funcChan       = "mostraOcultaCampo(this,'S','ana_lotemb');mostraOcultaCampo(this,'N','ana_descmetodo')";
        $met->dispForm = '2col';
        $ret['cla_metodanalise']    = $met->cr2opcoes();

        $lsm           =  new MyCampo('pro_mic_analise', 'ana_liberarsemmicro', false);
        $lsm->valor    = $lsm->selecionado    = isset($dados['ana_liberarsemmicro']) ? $dados['ana_liberarsemmicro'] : 'N';
        $lsm->leitura  = $show;
        $lsm->opcoes   = $simnao;
        $lsm->dispForm = '2col';
        $lsm->funcChan = "mostraOcultaCampo(this,'N','ana_lotemb,ana_laudo,ana_arqlaudo,ana_liberar,ana_reprovar')";
        $ret['ana_liberarsemmicro']    = $lsm->cr2opcoes();

        $dlt           =  new MyCampo('pro_mic_analise', 'ana_datalotemb', false);
        $dlt->valor    = isset($dados['ana_datalotemb']) ? $dados['ana_datalotemb'] : date('Y-m-d');
        $ret['ana_datalotemb']    = $dlt->crOculto();

        $info = isset($dados['ana_datalotemb']) ? date('dmY', strtotime($dados['ana_datalotemb'])) : date('dmY');

        $lmb           =  new MyCampo('pro_mic_analise', 'ana_lotemb', false);
        $lmb->valor = $lmb->selecionado    = isset($dados['ana_lotemb']) ? $dados['ana_lotemb'] : '';
        $lmb->tipo     = 'sonumero';
        $lmb->leitura  = $show;
        $lmb->maxLength = 9;
        $lmb->largura   = 100;
        $lmb->size      = 9;
        $lmb->infotexto   = 'formato XX-DDMMAA';
        $lmb->obrigatorio = true;
        $ret['ana_lotemb']    = $lmb->crInput();

        $met           =  new MyCampo('pro_mic_analise', 'ana_descmetodo', false);
        $met->valor = $met->selecionado    = isset($dados['ana_descmetodo']) ? $dados['ana_descmetodo'] : '';
        $met->leitura  = $show;
        $met->obrigatorio = true;
        $ret['ana_descmetodo']    = $met->crInput();

        $lau            =  new MyCampo('pro_mic_analise', 'ana_laudo', false);
        $lau->valor     = isset($dados['ana_laudo']) ? $dados['ana_laudo'] : '';
        $lau->leitura  = $show;
        // $lau->obrigatorio = true;
        $ret['ana_laudo']    = $lau->crInput();

        $arq            =  new MyCampo();
        $arq->id = $arq->nome     = 'ana_arqlaudo';
        $arq->label     = 'Laudo PDF';
        $arq->size      = 300;
        $arq->tamanho      = 300;
        $arq->valor      = '';
        if (isset($dados['ana_id'])) {
            $arqdb       = new ArquivoMonModel();
            $dados_files = $arqdb->getArquivos('Analisa', 'ArqLaudo', $dados['ana_id']);
            // debug($dados_files, true);
            if (count($dados_files) > 0) {
                $arqlaudo = $dados_files[0];
                // debug($arqlaudo, true);
                $arquivo = buscaTipoArquivo($arqlaudo);
                $nome_arq = (isset($arqlaudo->arq_nome)) ? $arqlaudo->arq_nome : '';
                $id         = (string)$arqlaudo->_id;
                $link              = base_url("/Showfile/" . $id);
                $redir             = "redirec_blank('$link', event)";
                $arq->funcChan     = $redir;
                $arq->valor        = $nome_arq;
                $arq->selecionado  = $arquivo;
                $arq->classep      = 'btn-outline-success';
                // $arq->i_cone  = '<div class="align-items-center py-1 text-start float-start font-weight-bold" style="">'
                // <i class="fa-solid fa-file" style="font-size: 2rem;" aria-hidden="true"></i></div>';
                if ($dados['stt_id'] == 15) {
                    $arq->i_cone = "<div id='view_img_" . $id . "' class='show img-thumbnail border border-1' >";
                    $arq->i_cone .= "<img id='img_" . $id . "' src='" . $arquivo . "'  class='img-thumbnail sempadding' alt='' style='width:" . $arq->size . "px;' />";
                    $arq->i_cone .= "</div>";
                    $arq->i_cone  .= '<div class="align-items-start txt-bt-manut">Ver Arquivo do Laudo<br>' . $nome_arq . '</div>';
                    $arq->place = '';
                    $ret['ana_arqlaudo']    = $arq->crLabel() . $arq->crBotao();
                } else if ($dados['stt_id'] != 15) {
                    $arq->leitura  = $show;
                    $arq->tipoArq  = '.pdf';
                    $arq->valor        = $nome_arq;
                    $arq->selecionado  = $arquivo;
                    $arq->funcChan    = "validarArquivoPorAccept(this);readURL(this, 'img_$arq->id', $arq->size, $arq->tamanho)";
                    $arq->funcBlur    = $redir;
                    $ret['ana_arqlaudo'] = $arq->crArquivo();
                }
                // debug($arq);
            } else {
                $arq->leitura  = $show;
                $arq->tipoArq  = '.pdf';
                $arq->selecionado  = "/assets/uploads/tipo_arquivo/vazio.png";
                $arq->funcChan    = "validarArquivoPorAccept(this);readURL(this, 'img_$arq->id', $arq->size, $arq->tamanho)";
                $ret['ana_arqlaudo']    = $arq->crArquivo();
            }
        }

        return $ret;
    }

    public function defCamposAcoes($dados = false, $show = false)
    {
        $opcoes         = new CommonModel();
        $ret = [];
        $simnao['S'] = 'Sim';
        $simnao['N'] = 'Não';

        $liberar = 'N';
        if (isset($dados['ana_liberar'])) {
            $liberar = $dados['ana_liberar'];
        } else if ($dados['stt_id'] == 12) { // EM ANDAMENTO
            $liberar = 'S';
        }
        $lib           =  new MyCampo('pro_mic_analise', 'ana_liberar', false);
        // $lib->id = $lib->nome = 'ana_liberar';
        // $lib->label    = 'Liberar';
        $lib->valor    = $lib->selecionado    = $liberar;
        $lib->leitura  = $show;
        $lib->opcoes   = $simnao;
        $lib->funcChan = "reprovar(this,'ana_reprovar');reprovar(this,'ana_liberarsemmicro');mostraOcultaCampo('ana_liberar','S','tmo_id');mostraOcultaCampo('ana_liberar','N','tmo_id_rep');mudaObrigatorio(this,'S','tmo_id')";
        $lib->dispForm = '2col';
        $ret['ana_liberar']    = $lib->cr2opcoes();

        $reprovar = 'N';
        if (isset($dados['ana_reprovar'])) {
            $reprovar = $dados['ana_reprovar'];
        } else if ($dados['stt_id'] == 16) { // EM ANDAMENTO
            $reprovar = 'S';
        }
        $rep           =  new MyCampo('pro_mic_analise', 'ana_reprovar', false);
        // $rep->id = $rep->nome = 'ana_reprovar';
        // $rep->label    = 'Reprovar';
        $rep->valor    = $rep->selecionado    = $reprovar;
        $rep->leitura  = $show;
        $rep->opcoes   = $simnao;
        $rep->dispForm = '2col';
        $rep->funcChan = "reprovar(this,'ana_liberar');reprovar(this,'ana_liberarsemmicro');mostraOcultaCampo('ana_reprovar','S','tmo_id_rep');mostraOcultaCampo('ana_reprovar','N','tmo_id');mudaObrigatorio(this,'S','tmo_id_rep')";
        $ret['ana_reprovar']    = $rep->cr2opcoes();
        // debug($dados['tmo_id']);

        $opc_mov           = $opcoes->getListaOpcoes('dbEstoque', 'est_tipo_movimentacao', ['tmo_nome', 'tmo_id']);
        $movi               =  new MyCampo('pro_mic_analise', 'tmo_id');
        $movi->valor        = $movi->selecionado = ($liberar == 'S') ? isset($dados['tmo_id']) ? $dados['tmo_id'] : '' : '';
        $movi->leitura      = $show;
        $movi->largura      = 60;
        $movi->opcoes       = $opc_mov;
        $movi->obrigatorio  = false;
        $ret['tmo_id']      = $movi->crSelect();

        $opc_mov_rep           = $opcoes->getListaOpcoes('dbEstoque', 'est_tipo_movimentacao', ['tmo_nome', 'tmo_id']);
        $movi               =  new MyCampo('pro_mic_analise', 'tmo_id_rep');
        $movi->valor        = $movi->selecionado = $reprovar == 'S' ? isset($dados['tmo_id_rep']) ? $dados['tmo_id_rep'] : '' : '';
        $movi->leitura      = $show;
        $movi->largura      = 60;
        $movi->opcoes       = $opc_mov_rep;
        $movi->obrigatorio  = false;
        $ret['tmo_id_rep']      = $movi->crSelect();

        return $ret;
    }

    /**
     * Busca análises filtrando por Produto (múltiplos pro_id), Lote (parcial)
     * e período de entrada do lote — usado na tela AnaliseMP (busca combinada).
     */
    public function getAnaliseFiltro($proIds = [], $lote = '', $dtIni = null, $dtFim = null, $ordenarPorNome = false)
    {
        $db      = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');
        $builder->select('*');

        if (! empty($proIds)) {
            $builder->whereIn('pro_id', $proIds);
        }
        if ($lote !== '') {
            $builder->like('lot_lote', $lote);
        }
        if ($dtIni && $dtFim) {
            $builder->where('ana_data >=', $dtIni);
            $builder->where('ana_data <=', $dtFim);
        }

        if ($ordenarPorNome) {
            $builder->orderBy('pro_despro', 'ASC');
        } else {
            $builder->orderBy('ana_data', 'DESC');
        }

        $ret = $builder->get()->getResultArray();
        // debug($db->getLastQuery());
        return $ret;
    }

    public function getStatusPorIds(array $sttIds)
    {
        if (empty($sttIds)) {
            return [];
        }

        $db      = db_connect('dbProduto');
        $builder = $db->table('vw_pro_mic_analise_relac_v2');
        $builder->select('stt_id, stt_nome, stt_cor');
        $builder->whereIn('stt_id', $sttIds);
        $builder->groupBy('stt_id');

        $rows = $builder->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['stt_id']] = $r;
        }

        return $map;
    }
}
