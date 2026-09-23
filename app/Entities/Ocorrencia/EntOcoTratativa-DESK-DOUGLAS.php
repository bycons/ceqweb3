<?php

namespace App\Entities\Ocorrencia;

use CodeIgniter\Entity\Entity;
use App\Libraries\MyCampo;
use App\Entities\Estoque\EntTipoMovimentacao;
use App\Entities\Produto\EntLote;
use App\Models\Ocorre\OcorreModOcorrenciaModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;

class EntOcoTratativa extends Entity
{
    protected $attributes = [
        'oco_id'        => null,
        'tpo_id'        => null,
        'tpa_id'        => null,
        'lot_lote'      => null,
        'oco_descricao' => null,
        'pro_despro'    => null,
        'oco_qtd'       => null,
        'oco_data'      => null,
        'stt_id'        => null,
        'tmo_id'        => null,
        'oco_justi'     => null,
        'usu_nome'      => null,
        'tel_id'        => null,
    ];

    protected $casts = [
        'oco_id' => 'integer',
        'tpo_id' => 'integer',
        'tpa_id' => 'integer',
        'stt_id' => 'integer',
        'tmo_id' => 'integer',
        'tel_id' => 'integer',
    ];

    public array $campos = [];

    public function __construct(object|array|null $data = null)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }
        parent::__construct((array) ($data ?? []));
        $this->campos = $this->defCampos($data ?? new \stdClass());
    }

    public function defCampos(object $dados)
          
    // DADOS GERAIS
    {
      $dados = (array) $dados;
      $ret = [];

      // TIPO DE OCORRÊNCIA
      $config = [];
        $config['Label'] = 'Tipo de Ocorrência';
        $config['Leitura'] = true;

        $ret['tpo_id'] = criaSelectRelativo(
            'oco_tipo_ocorrencia',
            'tpo_id',
            'tpo_nome',
            $dados['tpo_id'] ?? null, 
            1,
            'oco_ocorrencia',
            [],
            $config
        );

      // TIPO DE AÇÃO
      $config = [];
      $config['Label'] = 'Ação';
      $config['Leitura'] = true;
  
      $config['Pai'] = 'tpo_id';
      $config['Urlbusca'] = base_url('Buscas/buscaAcoesPorTipo');
        $ret['tpa_id'] = criaSelectRelativo(
            'oco_tipo_acao',          
            'tpa_id',
            'tpa_nome',
            $dados['tpa_id'] ?? null,
            2,                        
            'oco_ocorrencia',
            [],
            $config
        );

      // USUÁRIO 
      $usu           = new MyCampo('oco_ocorrencia', 'usu_nome');
      $usu->valor    = (isset($dados['usu_nome'])) ? $dados['usu_nome'] : '';
      $usu->objeto   = '';
      $usu->label    = 'Usuário';
      $usu->dispForm = '2col';
      $usu->size     = 40;
      $usu->leitura  = true;  
      $ret['usu_nome'] = $usu->crInput();

      // DESCRIÇÃO
      $desc              = new MyCampo('oco_ocorrencia', 'oco_descricao');  
      $desc->nome        = 'oco_descricao';
      $desc->valor       = (isset($dados['oco_descricao'])) ? $dados['oco_descricao'] : '';
      $desc->leitura     = true;
      $desc->label       = 'Descrição';
      $desc->linhas      = 3;
      $desc->colunas     = 56;
      $desc->dispForm    = '2col';
      $ret['oco_descricao'] = $desc->crTexto();

      // LOTE
      $lotid              = new MyCampo('oco_ocorrencia', 'lot_id');
      $lotid->valor       = (isset($dados['lot_id'])) ? $dados['lot_id'] : '';
      $ret['lot_id'] = $lotid->crOculto();  

      $lote              = new MyCampo('pro_sap_lote', 'lot_lote');
      $lote->valor       = (isset($dados['lot_lote'])) ? $dados['lot_lote'] : '';
      $lote->leitura     = true;
      $lote->label       = 'Lote';
      $lote->size        = 54;
      $lote->funcBlur    = "buscaLoteProduto(this,'" . base_url('/buscas/buscaProdutoporLote') . "')";
      $ret['lot_lote']   = $lote->crInput();
  
      // PRODUTO 
      $produto           = new MyCampo('pro_sap_produto', 'pro_despro');
      $produto->valor    = (isset($dados['pro_despro'])) ? $dados['pro_despro'] : '';
      $produto->dispForm = '2col';
      $produto->label    = ' ';
      $produto->size     = 54;
      $produto->leitura  = true;
      $ret['pro_despro'] = $produto->crInput();
  
  
      // QUANTIDADE
      $qtd               = new MyCampo('oco_ocorrencia', 'oco_qtd'); 
      $qtd->valor        = (isset($dados['oco_qtd'])) ? $dados['oco_qtd'] : '';
      $qtd->label        = 'Quantidade';
      $qtd->dispForm     = '2col';
      $qtd->largura      = 5;
      $qtd->leitura      = true;  
      $ret['oco_qtd'] = $qtd->crInput();
  
  
      // DATA
      $data              = new MyCampo('oco_ocorrencia', 'oco_data');
      $data->valor       = $dados->oco_data ?? date('Y-m-d\TH:i');
      $data->label       = 'Data da Ocorrência';
      $data->dispForm    = '2col';
      $data->leitura     = true;
      $data->largura     = 30;
  
      $ret['oco_data'] = $data->crInput();
  
      return $ret;
    }

    

    public function defCamposAcao(object $dados): array
    {
        $ret = [];
        $dados->tpa_id = $dados->tpa_id ?? null;
        $tmo_id = $dados->acao_tmo_id ?? null;
        $stt_id = $dados->acao_stt_id ?? null;
        $tel_id = $dados->acao_tel_id ?? null;
    
        // TIPO DE AÇÃO
        $acao = new MyCampo('oco_tipo_ocorrencia_acao', 'tpa_nome');
        $acao->valor    = $dados->tpa_nome ?? '';
        $acao->label    = 'Ação';
        $acao->leitura  = true;
        $acao->size     = 50;
        $acao->dispForm = '2col';
        
        $ret['tpa_nome'] = $acao->crInput();
    
        // JUSTIFICAR
        if ((int)$dados->tpa_id === 6) {
            $justi = new MyCampo('oco_ocorrencia', 'oco_justi');
            $justi->valor       = $dados->oco_justi ?? '';
            $justi->label       = 'Justificar';
            $justi->obrigatorio = true;
            $justi->dispForm    = '2col';
            $justi->linhas      = 3;
            $justi->colunas     = 56;
    
            $ret['oco_justi'] = $justi->crTexto();
        }
    
        // MOVIMENTAÇÃO
        if ((int)$tpa_id === 3) {
    
            $modelMod = new OcorreModOcorrenciaModel();
            $tmo_id = $modelMod->getMovimentacaoByTpoTpa(
                $dados->tpo_id,
                $dados->tpa_id
            );
    
            $tmoModel = new EstoquTipoMovimentacaoModel();
            $opc_tmo = [];
    
            foreach ($tmoModel->asObject()->findAll() as $tmo) {
                $opc_tmo[$tmo->tmo_id] = $tmo->tmo_nome;
            }
    
            $movNome = new MyCampo('oco_tpo_acao', 'tmo_nome');
            $movNome->valor    = $opc_tmo[$tmo_id] ?? '';
            $movNome->label    = 'Movimentação';
            $movNome->leitura  = true;
            $movNome->dispForm = '2col';
            $movNome->size     = 50; 
            
            $ret['tmo_id'] = $movNome->crInput();
        }
    
        // STATUS
        if ((int)$dados->tpa_id === 7) {
    
            $statModel = new OcorreModOcorrenciaModel();
            $stt_id = $statModel->getStatusByTpoTpa(
                $dados->tpo_id,
                $dados->tpa_id
            );
    
            $opc = [];
            foreach ($statModel->getStatus() as $stt) {
                $opc[$stt->stt_id] = $stt->stt_nome;
            }
    
            $statu = new MyCampo('', 'stt_id');
            $statu->valor    = $opc[$stt_id] ?? '';
            $statu->label    = 'Status';
            $statu->leitura  = true;
            $statu->dispForm = '2col';
            $statu->size     = 50; 
            
            $ret['stt_id'] = $statu->crInput();
        }
    
        // TELA
        if ((int)$dados->tpa_id === 4) {
    
            $mod = new OcorreModOcorrenciaModel();
            $opc = [];
    
            foreach ($mod->getTelas() as $tel) {
                $opc[$tel->tel_id] = $tel->tel_nome;
            }
    
            $tel_id = $mod->getTelaByTpoTpa(
                $dados->tpo_id,
                $dados->tpa_id
            );
    
            $tela = new MyCampo('', 'tel_id');
            $tela->valor    = $opc[$tel_id] ?? '';
            $tela->label    = 'Tela';
            $tela->leitura  = true;
            $tela->dispForm = '2col';
            $tela->size     = 60; 
            
            $ret['tel_id'] = $tela->crInput();
        }
    
        return $ret;
    }
}
