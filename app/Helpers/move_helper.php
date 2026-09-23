<?php

use App\Controllers\BuscasSapiens;
use App\Libraries\SoapSapiens;
use App\Models\CommonModel;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Estoqu\EstoquRequisicaoProdutoAtendimentoModel;
use App\Models\Estoqu\EstoquTipoMovimentacaoModel;
use App\Models\Produt\ProdutProdutoModel;

function geraMovimentoRequisicoes($movimentos, $controller)
{
    // ← NOVO: Proteção contra execução duplicada
    $lockKey = 'mov_lock_' . md5(json_encode($movimentos) . session()->get('usu_id'));

    // Cache seguro - verifica se já foi processado
    $cached = cache($lockKey);
    if ($cached !== null) {
        log_message('warning', 'Movimento duplicado detectado e bloqueado: ' . $lockKey);
        return ['status' => 'OK', 'mensagem' => 'Movimento já processado'];
    }

    // Lock por 5 segundos
    cache()->save($lockKey, true, 5);
    // Lock por 5 segundos (tempo máximo de execução)

    $mdproduto     = new ProdutProdutoModel();
    $tipomovimento = new EstoquTipoMovimentacaoModel();
    $common        = new CommonModel();
    $requisicao    = new EstoquRequisicaoModel();
    $soaptrf       = new SoapSapiens();

    if (empty($movimentos)) {
        return ['status' => 'OK', 'mensagem' => 'Nenhum movimento a processar'];
    }

    // Estrutura: [ ['sub_movimentos' => [...]] ]
    $movimentosRealizados = [];

    for ($m = 0; $m < count($movimentos); $m++) {
        $mov     = $movimentos[$m];
        $lote    = $mov['lot_lote'] ?? '';
        $loteval = $mov['lot_validade'] ?? '';

        if ($mov['rep_id'] != null) {
            $reqlote = $requisicao->getRequisicaoRep($mov['rep_id'])[0];
            $lote    = $reqlote->lot_lote;
            $loteval = $reqlote->lot_validade;
        }

        $msg     = $mov['msg'] ?? '';
        $produto = (array) $mdproduto->getProduto($mov['pro_id'], false)[0];
        $codpro  = $produto['pro_codpro'];
        $msg = (trim((string) $msg) !== '' ? $msg . '<br />' : '')
            . 'Produto ' . $codpro
            . ' Lote ' . (trim((string) $lote) !== '' ? $lote : 'Sem Lote');

        // envia_msg_ws($controller, $msg, 'MsgServer', session()->get('usu_id'), 1);

        $datmov = date('d/m/Y');
        $codlot = trim($lote);
        $qtdmov = str_replace(['.', ','], '', $mov['qt']);
        $valida = data_br($loteval);

        // BUSCA TODOS OS TIPOS DE MOVIMENTO CADASTRADOS
        $movim = (array) $tipomovimento->getTipoMovimentacaoMovim($mov['id']);
        // debug($movim, true);

        $subMovimentosRealizados = [];
        $erroEncontrado          = false;
        $mensagemErro            = '';

        // ── LOOP INTERNO: itera cada transação do tipo de movimentação ──
        $primeiroMovimento = true;

        foreach ($movim as $tipoMov) {
            // $codtns = $primeiroMovimento
            //     ? $tipoMov->tmo_transacao_erp
            //     : $tipoMov->tmo_transacao_erp_saida;
            $codtns = $tipoMov->tmm_transacao;

            $primeiroMovimento = false;
            $depori = $tipoMov->dep_codorigem;
            $depdes = $tipoMov->dep_coddestino;

            if (isset($mov['tipomov']) && $mov['tipomov'] == 'R') {
                $depori = $tipoMov->dep_coddestino;
                $depdes = $tipoMov->dep_codorigem;
            }

            if ($tipoMov->dep_reserva != '') {
                $reserva = strtoupper($mov['reserva']);
                if ($reserva === 'D') {
                    $depdes = $tipoMov->dep_reserva;
                } elseif ($reserva === 'O') {
                    $depori = $tipoMov->dep_reserva;
                }
            }

            log_message('info', 'Sub-movimento (transação ' . $codtns . ') ' . json_encode($tipoMov));
            log_message('info', 'Depósito Origem ' . $depori);
            log_message('info', 'Depósito Destino ' . $depdes);

            if ($depdes == null || $depdes == '') {
                log_message('info', 'Sem depósito de Destino, Somente Saída');
                $movimenta = $soaptrf->movimProdutosSapiens(
                    $codpro,
                    $codtns,
                    $depori,
                    $datmov,
                    $qtdmov,
                    $codlot,
                    $depdes,
                    $valida,
                    $msg
                );
            } else {
                log_message('info', 'Com depósito de Destino, Transferência');
                $movimenta = $soaptrf->transfProdutosSapiens(
                    $codpro,
                    $codtns,
                    $depori,
                    $datmov,
                    $qtdmov,
                    $codlot,
                    $depdes,
                    $valida,
                    $msg
                );
            }

            if ($movimenta['status'] == 'Erro') {
                log_message('error', 'Erro no sub-movimento (transação ' . $codtns . '): ' . ($movimenta['mensagem'] ?? ''));
                $erroEncontrado = true;
                $mensagemErro   = $movimenta['mensagem'] ?? 'Erro ao gerar movimento de estoque';
                break; // interrompe o loop interno
            }

            // Guarda dados do sub-movimento para reversão futura
            $subMovimentosRealizados[] = [
                'codpro' => $codpro,
                'codtns' => $codtns,
                'depori' => $depori,
                'depdes' => $depdes,
                'qtdmov' => $qtdmov,
                'codlot' => $codlot,
                'valida' => $valida,
                'msg'    => $msg,
            ];
        }

        // ── REVERSÃO em caso de erro ──
        if ($erroEncontrado) {

            // 1) Reverte sub-movimentos do movimento atual (os que passaram antes do erro)
            if (!empty($subMovimentosRealizados)) {
                log_message('info', 'Revertendo ' . count($subMovimentosRealizados) . ' sub-movimento(s) do movimento atual (índice ' . $m . ')');

                foreach (array_reverse($subMovimentosRealizados) as $sv) {
                    $msgRv  = 'REVERSÃO - Produto ' . $sv['codpro'] . ' Lote ' . $sv['codlot'] . ' Transação ' . $sv['codtns'];
                    // envia_msg_ws($controller, $msgRv, 'MsgServer', session()->get('usu_id'), 1);

                    $reverte = $soaptrf->transfProdutosSapiens(
                        $sv['codpro'],
                        $sv['codtns'],
                        $sv['depdes'], // inverte: destino vira origem
                        date('d/m/Y'),
                        $sv['qtdmov'],
                        $sv['codlot'],
                        $sv['depori'], // inverte: origem vira destino
                        $sv['valida'],
                        $msgRv
                    );

                    if ($reverte['status'] == 'Erro') {
                        log_message('error', 'Falha ao reverter sub-movimento do produto ' . $sv['codpro'] . ': ' . ($reverte['mensagem'] ?? ''));
                    }
                }
            }

            // 2) Reverte todos os movimentos anteriores já realizados (ordem inversa)
            if (!empty($movimentosRealizados)) {
                log_message('info', 'Revertendo ' . count($movimentosRealizados) . ' movimento(s) anteriores já realizados');

                foreach (array_reverse($movimentosRealizados) as $rv) {
                    foreach (array_reverse($rv['sub_movimentos']) as $sv) {
                        $msgRv = 'REVERSÃO - Produto ' . $sv['codpro'] . ' Lote ' . $sv['codlot'] . ' Transação ' . $sv['codtns'];
                        // envia_msg_ws($controller, $msgRv, 'MsgServer', session()->get('usu_id'), 1);

                        $reverte = $soaptrf->transfProdutosSapiens(
                            $sv['codpro'],
                            $sv['codtns'],
                            $sv['depdes'], // inverte
                            date('d/m/Y'),
                            $sv['qtdmov'],
                            $sv['codlot'],
                            $sv['depori'], // inverte
                            $sv['valida'],
                            $msgRv
                        );

                        if ($reverte['status'] == 'Erro') {
                            log_message('error', 'Falha ao reverter movimento do produto ' . $sv['codpro'] . ': ' . ($reverte['mensagem'] ?? ''));
                        }
                    }
                }
            }

            return [
                'status'   => 'Erro',
                'mensagem' => $mensagemErro,
            ];
        }

        // Movimento concluído com sucesso — registra todos os sub-movimentos
        $movimentosRealizados[] = [
            'sub_movimentos' => $subMovimentosRealizados,
        ];
    }

    return ['status' => 'OK', 'mensagem' => 'Movimentos realizados com sucesso'];
}

function indexarEstoque(array|object|null $estoque): array
{
    if ($estoque instanceof \stdClass) {
        $estoque = [$estoque];
    } elseif (! is_array($estoque)) {
        $estoque = [];
    }

    $indexado = [];

    foreach ($estoque as $item) {
        if (! is_object($item)) {
            continue;
        }

        if (isset($item->quantidadeEstoque)) {
            $item->quantidadeEstoque = str_replace(
                ['.', ','],
                ['', '.'],
                (string) $item->quantidadeEstoque
            );
        }

        $produto = $item->codigoProduto ?? null;
        $lote    = $item->codigoLote    ?? 'SEM_LOTE';

        if ($produto === null) {
            continue;
        }

        $indexado[$produto][$lote][] = $item;
    }

    return $indexado;
}

function indexarRequisicaoProdutos(array|null $requisicaoProdutos): array
{
    if (! is_array($requisicaoProdutos)) {
        $requisicaoProdutos = [];
    }

    $indexado = [];

    foreach ($requisicaoProdutos as $item) {
        if (! is_array($item)) {
            continue;
        }

        $repId = $item['rep_id'] ?? null;
        $proId = $item['pro_id'] ?? null;

        if ($repId === null || $proId === null) {
            continue;
        }

        $indexado[$repId][$proId] = $item;
    }

    return $indexado;
}
function indexarConsumo(array|object|null $consumo): array
{
    // 🔒 Normalização (igual fizemos antes)
    if ($consumo instanceof \stdClass) {
        $consumo = [$consumo];
    } elseif (!is_array($consumo)) {
        $consumo = [];
    }

    $indexado = [];

    foreach ($consumo as $item) {
        // Proteção: garante formato válido
        if (is_object($item)) {
            $item = (array) $item;
        }

        if (!is_array($item)) {
            continue;
        }

        $codigo = $item['codigo_erp'] ?? null;

        if ($codigo === null) {
            continue;
        }

        $indexado[$codigo] = $item;
    }

    return $indexado;
}
function indexarArray(array|object|null $items, array $campos, bool $multiplos = false): array
{
    if ($items instanceof \stdClass) {
        $items = [$items];
    } elseif (! is_array($items)) {
        $items = [];
    }

    $indexado = [];

    foreach ($items as $item) {
        if (! is_object($item)) {
            continue;
        }

        $ref = &$indexado;

        foreach ($campos as $campo) {
            $valor = trim((string) ($item->$campo ?? 'SEM_' . strtoupper($campo)));

            if (! isset($ref[$valor])) {
                $ref[$valor] = [];
            }

            $ref = &$ref[$valor];
        }

        if ($multiplos) {
            $ref[] = $item;
        } else {
            $ref = $item;
        }

        unset($ref);
    }

    return $indexado;
}

function tratarOriginal(int|string $reqId): bool
{
    $requisicao     = new EstoquRequisicaoModel();
    $requisicaoate  = new EstoquRequisicaoProdutoAtendimentoModel();
    $busca          = new BuscasSapiens();

    $reqDerivadaLista = $requisicao->getRequisicao($reqId);

    if (empty($reqDerivadaLista)) {
        return false;
    }

    $reqderivada = $reqDerivadaLista[0];
    log_message('info', 'Derivada ' . json_encode($reqderivada));

    if (
        ! isset($reqderivada->req_original)
        || trim((string) $reqderivada->req_original) === ''
    ) {
        return false;
    }

    $produtosDerivada = $requisicao->getRequisicaoProdutos($reqderivada->req_id);

    $reqOriginalLista = $requisicao->getRequisicao($reqderivada->req_original);

    if (empty($reqOriginalLista)) {
        return false;
    }

    $reqoriginal  = $reqOriginalLista[0];
    $produtosOrig = $requisicao->getRequisicaoProdutos($reqoriginal->req_id);

    log_message('info', 'Tem Original ' . $reqoriginal->req_id);

    $produtosOrigIndexados = indexarArray($produtosOrig, ['pro_id', 'lot_id']);

    $totalAtendidos = 0;
    $db = \Config\Database::connect();
    $db->transBegin();

    foreach ($produtosDerivada as $prodDerivada) {
        $proId = trim((string) $prodDerivada->pro_id);
        $lotId = trim((string) ($prodDerivada->lot_id ?? 'SEM_LOT_ID'));

        $prod = $produtosOrigIndexados[$proId][$lotId] ?? null;

        if ($prod === null) {
            continue;
        }

        if (is_null($prodDerivada->rpa_data_conferencia) || is_null($prodDerivada->rpa_data_inspecao)) {
            continue;
        }

        $qtdDerivada = $prodDerivada->rpa_aprovada > -1
            ? $prodDerivada->rpa_aprovada
            : ($prodDerivada->rpa_conferida > -1
                ? $prodDerivada->rpa_conferida
                : $prodDerivada->rpa_atendida);

        log_message('info', 'Produto Derivada ' . $prodDerivada->pro_codpro);
        log_message('info', 'Lote Derivada ' . $prodDerivada->lot_lote);
        log_message('info', 'Quantidade Derivada ' . $qtdDerivada);
        log_message('info', 'Solicitada Original ' . $prod->rep_quantia);
        log_message('info', 'Produto Original ' . $prod->pro_codpro);
        log_message('info', 'Lote Original ' . $prod->lot_lote);

        $atendida   = (int) $prod->rep_quantia;
        $cancelada  =  0;
        if ((int) $qtdDerivada !== (int) $prod->rep_quantia || (int) $qtdDerivada == 0) {
            if ((int) $qtdDerivada > 0) {
                // busca o saldo do produto no depósito de origem da req Original
                $estoqueOrigem = indexarEstoque(
                    $busca->buscaEstoqueDeposito($reqoriginal->req_deporigem, $prod->pro_codpro)
                );
                $lote = trim($prod->lot_lote ?? '') ?: 'Sem Lote';

                $estoqProd = $estoqueOrigem[$prod->pro_codpro][$lote][0] ?? null;
                $prod->estoque_origem = $estoqProd
                    ? (int) str_replace('.', '', (string) $estoqProd->quantidadeEstoque)
                    : 0;
                if ($prod->estoque_origem == 0) { // se o saldo no Sapiens for 0 não atende
                    continue;
                }
                // gera atendimento automático com o saldo, e cancela a diferença na original
                if ((int) $prod->estoque_origem < (int) $prod->rep_quantia) {
                    // se o saldo no Sapiens for menor q a quantia solicitada
                    $atendida   = (int) $prod->estoque_origem;
                    $cancelada  = (int) $prod->rep_quantia - (int) $prod->estoque_origem;
                }
            } else { // se a quantidade aprovada na derivada for 0 não atende
                continue;
            }
        }

        $ate = $requisicaoate->getProdutoRequisicaoAtendimento($prod->rep_id, $prod->pro_id);
        if ($ate) {
            continue;
        }

        // envia_msg_ws('AteRequisicao', 'Gerando Movimentação de Estoque', 'MsgServer', session()->get('usu_id'), 1);
        $movim = geraMovimentoRequisicoes([[
            'id'      => $reqoriginal->tmo_id,
            'qt'      => $atendida,
            'msg'     => 'Atendimento da Requisição Original Nº ' . str_pad($reqoriginal->req_id, 6, '0', STR_PAD_LEFT),
            'pro_id'  => $prod->pro_id,
            'rep_id'  => $prod->rep_id,
            'reserva' => 'D',
        ]], 'AteRequisicao');

        if ($movim['status'] === 'Erro') {
            continue;
        }

        $salva = $requisicaoate->insert([
            'rep_id'        => $prod->rep_id,
            'pro_id'        => $prod->pro_id,
            'rpa_cancelada' => $cancelada,
            'rpa_atendida'  => $atendida,
            'rpa_data'      => date('Y-m-d H:i:s'),
        ]);

        if (! $salva) {
            $db->transRollback();
            return false;
        }

        $totalAtendidos++;
    }
    $db->transCommit();

    // ─── Acerta status da requisição original ────────────────────────────
    if ($totalAtendidos > 0) {
        $pendencias = $requisicaoate->getRequisicaoPendencias($reqoriginal->req_id);
        log_message('info', 'Pendências ' . json_encode($pendencias) ?? '');

        if (isset($pendencias[0]) && $pendencias[0]['pendente_atendimento'] == 0) {
            $status = 18; // totalmente atendida
        } else {
            $status = 21; // parcialmente atendida
        }

        $db->transBegin();
        $salvareq = $requisicao->update($reqoriginal->req_id, ['stt_id' => $status]);

        if (! $salvareq) {
            $db->transRollback();
            return false;
        }
        $db->transCommit();
    }

    return $db->transStatus();
}

function resolveCodigoProduto(object $produto): string|int
{
    return $produto->codigoProduto
        ?? $produto->codPro
        ?? $produto->pro_codigo
        ?? $produto->pro_id;
}

function resolveCodigoLote(object $produto): string
{
    return $produto->codigoLote
        ?? $produto->lotePro
        ?? $produto->lot_codigo
        ?? $produto->lot_id
        ?? 'SEM_LOTE';
}
