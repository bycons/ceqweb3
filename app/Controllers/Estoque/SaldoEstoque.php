<?php

namespace App\Controllers\Estoque;

use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Entities\Estoque\EntSaldoEstoque;
use App\Models\Estoqu\EstoquDepositoModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Produt\ProdutProdutoModel;

class SaldoEstoque extends BaseController
{
    public $data = [];
    public $permissao = '';
    public $deposito;
    public $produto;
    public $lote;

    /**
     * Construtor da Tela
     * construct
     */
    public function __construct()
    {
        $this->data      = session()->getFlashdata('dados_tela');
        $this->permissao = $this->data['permissao'];
        $this->deposito  = new EstoquDepositoModel();
        $this->produto   = new ProdutProdutoModel();
        $this->lote      = new ProdutLoteModel();

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
        $entity = new EntSaldoEstoque();
        $fields = $entity->campos;

        $secao[0] = 'Buscar';
        $campos[0][] = $fields->sal_depo;
        $campos[0][] = $fields->sal_code;
        $campos[0][] = $fields->sal_lote;
        $campos[0][] = $fields->sal_btbu;

        $colunas = ['Depósito', 'CodErp', 'Produto', 'Lote', 'Validade', 'Saldo', 'Und', 'Entrada'];

        $this->data['secoes']  = $secao;
        $this->data['campos']  = $campos;
        $this->data['colunas'] = $colunas;
        $this->data['destino'] = 'lista';

        echo view('vw_filtro', $this->data);
    }

    /**
     * Listagem
     * lista
     */
    public function lista()
    {
        $vars = $_REQUEST;
        $dep = trim($vars['codDep']);
        $pro = trim($vars['codPro']);
        $lot = trim($vars['codLot']);
        // debug($dep, true);
        $estoques = [];

        if (empty($pro) && !empty($lot)) {
            $lotesLocais = $this->lote->getLoteCodproLote(false, $lot);
            if ($lotesLocais) {
                $pro = trim($lotesLocais[0]->lot_codpro);
            }
        }

        // se houver mais de 1 depósito (separados por vírgula), busca no Sapiens
        // sem filtro de depósito (traz todos) e filtra o resultado aqui
        $depsFiltro = str_contains($dep, ',') ? array_map('trim', explode(',', $dep)) : [];
        $depBusca   = $depsFiltro ? '' : $dep;

        // $produt = new SoapSapiens();
        $busca = new BuscasSapiens();
        if (!empty($pro)) {
            $saldoest = $busca->buscaEstoqueProduto($pro, $lot, $depBusca);
            // debug($saldoest);
            $estoques = $this->formataEstoquePorProduto($saldoest);
            // debug($estoques, true);
        } else {
            $saldoest = $busca->buscaEstoqueDeposito($depBusca, $pro);
            $estoques = $this->formataEstoquePorDeposito($saldoest);
        }

        if ($depsFiltro) {
            $estoques = array_values(array_filter(
                $estoques,
                fn($e) => in_array((string) $e['codDep'], $depsFiltro, true)
            ));
        }

        echo json_encode($estoques);
    }

    /**
     * Formata o retorno do EstoqueporDeposito (busca por depósito), que vem
     * como um único objeto ou uma lista de objetos, para o formato plano
     * esperado pela tela.
     * formataEstoquePorDeposito
     */
    private function formataEstoquePorDeposito($saldoest)
    {
        $estoques = [];

        if (is_object($saldoest)) {
            // Converte objeto em array
            $dep = $saldoest;
            debug($dep, true);
            if (($dep->codigoLote == 'N/A' && $dep->estoqueDeposito > 0) ||
                ($dep->codigoLote != 'N/A' && $dep->quantidadeEstoque > 0)
            ) {
                if ($this->produto->getProdutoCod($dep->codigoProduto)) {
                    $lote = [
                        'codDep'      => $dep->codigoDeposito,
                        'Coderp'      => $dep->codigoProduto,
                        'DescProduto' => $dep->descricaoProduto,
                        'Produto'     => $dep->codigoProduto . ' - ' . $dep->descricaoProduto,
                        'lote'        => $dep->codigoLote,
                        'validade'    => $dep->validade,
                        'validadeord' => data_db($dep->validade),
                        'entrada'     => $dep->entrada,
                        'entradaord'  => data_db($dep->entrada),
                        'und'         => $dep->unidmedida,
                    ];
                    if ($dep->codigoLote == 'N/A') {
                        $lote['saldo'] = $dep->estoqueDeposito;
                        $lote['validade']    = '';
                        $lote['validadeord'] = '';
                        $lote['entrada']     = '';
                        $lote['entradaord']  = '';
                    } else {
                        $lote['saldo'] = $dep->quantidadeEstoque;
                    }
                    array_push($estoques, $lote);
                }
            }
        } else {
            for ($d = 0; $d < sizeof($saldoest); $d++) {
                $dep = $saldoest[$d];
                if ($this->produto->getProdutoCod($dep->codigoProduto)) {
                    if (($dep->codigoLote == 'N/A' && $dep->estoqueDeposito > 0) ||
                        ($dep->codigoLote != 'N/A' && $dep->quantidadeEstoque > 0)
                    ) {
                        $lote = [
                            'codDep'      => $dep->codigoDeposito,
                            'Coderp'      => $dep->codigoProduto,
                            'DescProduto' => $dep->descricaoProduto,
                            'Produto'     => $dep->codigoProduto . ' - ' . $dep->descricaoProduto,
                            'lote'        => $dep->codigoLote,
                            'validade'    => $dep->validade,
                            'validadeord' => data_db($dep->validade),
                            'entrada'     => $dep->entrada,
                            'entradaord'  => data_db($dep->entrada),
                            'und'         => $dep->unidmedida,
                        ];
                        if ($dep->codigoLote == 'N/A') {
                            $lote['saldo'] = $dep->estoqueDeposito;
                            $lote['validade']    = '';
                            $lote['validadeord'] = '';
                            $lote['entrada']     = '';
                            $lote['entradaord']  = '';
                        } else {
                            $lote['saldo'] = $dep->quantidadeEstoque;
                        }
                        array_push($estoques, $lote);
                    }
                }
            }
        }

        return $estoques;
    }

    /**
     * Formata o retorno do ConsultarEstoque (busca por produto), que vem
     * aninhado (produto > deposito > lote), para o formato plano esperado
     * pela tela (mesmos campos usados na busca por depósito).
     * formataEstoquePorProduto
     */
    private function formataEstoquePorProduto($saldoest)
    {
        $estoques = [];

        if (empty($saldoest)) {
            return $estoques;
        }

        if (is_object($saldoest)) {
            $saldoest = [$saldoest];
        }

        foreach ($saldoest as $produtoSap) {
            $codPro = $produtoSap->codPro ?? '';

            $despro = '';
            $prods = $this->produto->getProdutoCod($codPro);
            if ($prods) {
                $despro = $prods[0]->pro_despro;
            }

            $depositos = $produtoSap->derivacoes->depositos ?? [];
            if (is_object($depositos)) {
                $depositos = [$depositos];
            }
            foreach ($depositos as $deposito) {
                if (!empty($deposito->lotes)) {
                    $lotesSap = $deposito->lotes;
                    if (is_object($lotesSap)) {
                        $lotesSap = [$lotesSap];
                    }
                    foreach ($lotesSap as $lotSap) {
                        $saldo = $lotSap->qtdEst ?? 0;
                        if ($saldo <= 0) {
                            continue;
                        }

                        $validade = '';
                        $entrada  = '';
                        $lotesLocais = $this->lote->getLoteCodproLote($codPro, $lotSap->codLot);
                        if ($lotesLocais) {
                            $validade = $this->formataDataLote($lotesLocais[0]->lot_validade);
                            $entrada  = $this->formataDataLote($lotesLocais[0]->lot_entrada);
                        } else {
                            $validade = $this->formataDataLote('');
                            $validade['data'] = 'Lote não Encontrado';
                            $entrada  = $this->formataDataLote('');
                        }

                        $estoques[] = [
                            'codDep'      => $deposito->codDep,
                            'Coderp'      => $codPro,
                            'DescProduto' => $despro,
                            'Produto'     => $codPro . ' - ' . $despro,
                            'lote'        => $lotSap->codLot,
                            'validade'    => $validade['data'],
                            'validadeord' => $validade['ord'],
                            'entrada'     => $entrada['data'],
                            'entradaord'  => $entrada['ord'],
                            'und'         => 'UN',
                            'saldo'       => $saldo,
                        ];
                    }
                } else {
                    $saldo = $deposito->qtdEst ?? 0;
                    if ($saldo <= 0) {
                        continue;
                    }

                    $estoques[] = [
                        'codDep'      => $deposito->codDep,
                        'Coderp'      => $codPro,
                        'DescProduto' => $despro,
                        'Produto'     => $codPro . ' - ' . $despro,
                        'lote'        => '',
                        'validade'    => '',
                        'validadeord' => '',
                        'entrada'     => '',
                        'entradaord'  => '',
                        'und'         => 'UN',
                        'saldo'       => $saldo,
                    ];
                }
            }
        }

        return $estoques;
    }

    /**
     * Converte uma data vinda do banco local (ISO ou já em d/m/Y) para o par
     * de valores usado na tela: exibição (d/m/Y) e ordenação (Y-m-d).
     * formataDataLote
     */
    private function formataDataLote($data)
    {
        $data = trim((string) $data);
        if ($data === '') {
            return ['data' => '', 'ord' => ''];
        }

        if (strpos($data, '/') !== false) {
            return ['data' => $data, 'ord' => data_db($data)];
        }

        $timestamp = strtotime($data);
        if ($timestamp === false) {
            return ['data' => $data, 'ord' => ''];
        }

        return ['data' => date('d/m/Y', $timestamp), 'ord' => date('Y-m-d', $timestamp)];
    }
}
