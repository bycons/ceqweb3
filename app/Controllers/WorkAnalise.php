<?php

namespace App\Controllers;

use App\Models\Microb\MicrobAnaliseModel;
use App\Models\Produt\ProdutProdutoModel;
use App\Models\Produt\ProdutLoteModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Libraries\SoapSapiens;
use App\Controllers\BaseController;
use App\Controllers\BuscasSapiens;
use App\Libraries\Notificacao;

class WorkAnalise extends BaseController
{
    public function index()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $inicio = date('d/m/Y H:i:s');
        // debug("$inicio Iniciando WorkAnalise... \n");
        echo "$inicio Iniciando WorkAnalise... \n";

        $analise        = new MicrobAnaliseModel();
        $produto        = new ProdutProdutoModel();
        $lote           = new ProdutLoteModel();
        $tipomovimento  = new EstoquTipoMovimentacaoModel();
        $busca          = new BuscasSapiens();
        $notifica       = new Notificacao();

        $saldoestObjs = $busca->buscaEstoqueDeposito('QUA', '');
        $saldoest = is_array($saldoestObjs) ? $saldoestObjs : iterator_to_array($saldoestObjs);
        if (empty($saldoest)) {
            // debug("Sem saldo encontrado. \n");
            echo "Sem saldo encontrado. \n";
            return;
        }

        $saldoestFiltrado = array_filter($saldoest, function ($item) {
            return !(($item->codigoLote === 'N/A' && $item->estoqueDeposito == 0) ||
                ($item->codigoLote !== 'N/A' && $item->quantidadeEstoque == 0));
        });
        // debug(count($saldoestFiltrado) . " Produtos no Estoque Quarentena... \n");
        echo count($saldoestFiltrado) . " Produtos no Estoque Quarentena... \n";

        $saldoestArr = array_map(fn($obj) => (array) $obj, array_values($saldoestFiltrado));
        $codigoProdutoArray = array_column($saldoestArr, 'codigoProduto');
        $codigoLoteArray    = array_column($saldoestArr, 'codigoLote');

        $prodsArr = (array) $produto->getProdutoCodLista($codigoProdutoArray, 'S');
        $lotesArr = (array) $lote->getLoteIn($codigoLoteArray);
        $analises = (array) $analise->getAnaliseCod();
        $movimData = (array) $tipomovimento->getTipoMovimentacao(5);
        $movim = $movimData[0] ?? null;

        $prods = array_column($prodsArr, null, 'pro_codpro');
        $lotes = array_column($lotesArr, null, 'lot_lote');

        $analisesAssoc = [];
        foreach ($analises as $a) {
            $a = (array) $a;
            $analisesAssoc[$a['pro_codpro'] . '-' . $a['lot_lote']] = $a;
        }

        $analisesToSave = [];
        $lotesToUpdate  = [];

        foreach ($saldoestArr as $saldo) {
            $prodproc   = $saldo['codigoProduto'];
            $loteproc   = $saldo['codigoLote'];
            $quantidade = str_replace(['.', ','], '', $saldo['quantidadeEstoque']);
            // debug(" Produto " . $prodproc . " Lote " . $loteproc . " ...\n");
            echo " Produto " . $prodproc . " Lote " . $loteproc . " Saldo " . $saldo['quantidadeEstoque'] . " ...\n";

            if (!isset($prods[$prodproc]) || $prods[$prodproc]->cla_micro !== 'S') {
                continue;
            }

            $prod = $prods[$prodproc];
            $loteInfo = $lotes[$loteproc] ?? (object) [
                'lot_lote'     => $loteproc,
                'lot_entrada'  => $saldo['entrada'],
                'lot_validade' => $saldo['validade'],
                'stt_id'       => null,
            ];
            // debug('LoteInfo:');
            // debug($loteInfo);
            // debug($loteInfo->stt_id);
            $loteInfo->lot_entrada = $saldo['entrada'];

            $analiseKey = $prodproc . '-' . $loteproc;
            $analis = $analisesAssoc[$analiseKey] ?? null;
            $geramovimentacao = false;
            // debug("Análise");
            // debug($analis);

            if ($loteInfo->stt_id == 8) {
                if (is_null($analis) || $analis['stt_id'] == 16) {
                    $analisesToSave[] = [
                        'pro_id'   => $prod->pro_id,
                        'lot_id'   => $loteInfo->lot_id,
                        'ana_qtde' => $quantidade,
                        'ana_data' => date('Y-m-d'),
                        'stt_id'   => 10,
                    ];
                }
            } elseif ($loteInfo->stt_id == 9) {
                if (is_null($analis) || in_array($analis['stt_id'], [13, 16])) {
                    $analisesToSave[] = [
                        'pro_id'   => $prod->pro_id,
                        'lot_id'   => $loteInfo->lot_id,
                        'ana_qtde' => $quantidade,
                        'ana_data' => date('Y-m-d'),
                        'stt_id'   => 10,
                    ];
                    $lotesToUpdate[] = [
                        'lot_id' => $loteInfo->lot_id,
                        'stt_id' => 8,
                    ];
                } else {
                    $geramovimentacao = true;
                }
            }
            if ($geramovimentacao) {
                if ($movim) {
                    (new SoapSapiens())->transfProdutosSapiens(
                        $prod->pro_codpro,
                        $movim->tmm_transacao,
                        $movim->dep_codorigem,
                        date('d/m/Y'),
                        $quantidade,
                        $loteInfo->lot_lote,
                        $movim->dep_coddestino,
                        data_br($loteInfo->lot_validade)
                    );
                    echo " Movimentado $quantidade de $movim->dep_codorigem para $movim->dep_coddestino ...\n";
                }
                $lotesToUpdate[] = [
                    'lot_id' => $loteInfo->lot_id,
                    'stt_id' => 9,
                ];
            }
        }

        // debug(count($analisesToSave) . " Análises criadas \n");

        $msgsocket = "";

        if (!empty($analisesToSave)) {
            if (method_exists($analise, 'saveBatch')) {
                $analise->saveBatch($analisesToSave);
            } else {
                foreach ($analisesToSave as $data) {
                    $analise->save($data);
                }
            }
            $msgsocket  = count($analisesToSave) . " Análises criadas";

            // echo count($analisesToSave) . " análises salvas.\n";
        }

        echo "\n" . count($analisesToSave) . " Análises criadas \n";
        echo count($lotesToUpdate) . " Lotes Atualizados \n";
        if (!empty($lotesToUpdate)) {
            if (method_exists($lote, 'updateBatch')) {
                $lote->updateBatch($lotesToUpdate, 'lot_id');
            } else {
                foreach ($lotesToUpdate as $data) {
                    $lote->save($data);
                }
            }
            // echo count($lotesToUpdate) . " lotes atualizados.\n";
        }

        $final = date('d/m/Y H:i:s');
        echo "$final WorkAnalise finalizado \n\n";

        if ($msgsocket != "") {
            $notifica->gravaNotifica('Micro\Analise', '', $msgsocket, 'C');
        }
    }
}
