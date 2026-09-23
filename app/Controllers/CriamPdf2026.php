<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\MymPdf2026;
use App\Models\Estoqu\EstoquRequisicaoModel;
use App\Models\Microb\MicrobAnaRequisicaoModel;
use App\Models\Ocorre\OcorreOcorrenciaModel;
use CodeIgniter\HTTP\ResponseInterface;

class CriamPdf2026 extends BaseController
{
    public $data;
    public $anarequisicao;
    public $requisicao;
    public MymPdf2026 $pdf;
    public $ocorrencia;
    public $busca;

    public function __construct()
    {
        $this->data          = session()->getFlashdata('dados_classe');
        $this->anarequisicao = new MicrobAnaRequisicaoModel();
        $this->requisicao    = new EstoquRequisicaoModel();
        $this->ocorrencia    = new OcorreOcorrenciaModel();
        $this->busca         = new BuscasSapiens();
    }

    /**
     * Relatório de análise de requisição. 
     *
     * $saida:
     * - inline   = abre no navegador
     * - download = força download
     * - save     = salva em disco e retorna JSON com caminho
     * - base64   = retorna JSON {pdf: "..."} para AJAX
     */
    public function PrintAnaRequisicao($req_id, string $saida = 'base64')
    {
        $requis = $this->anarequisicao->getListaRequisicao($req_id);

        if (! $requis) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['erro' => 'Requisição não encontrado']);
        }

        $req       = (object) $requis[0];
        $this->pdf = new MymPdf2026(false, false, 'A4', 'P');
        $this->pdf->titulo('Requisição Nº: ' . $req->req_id);

        $html = $this->htmlAnaRequisicao($req, $requis);
        $this->pdf->html($html);

        return $this->saidaPdf($this->pdf, 'reqanalise_' . $req_id . '.pdf', $saida);
    }

    /**
     * Relatório de requisição de estoque.
     */
    public function PrintRequisicaoEstoq($req_id, string $saida = 'base64')
    {
        $requisicao = $this->requisicao->getRequisicao($req_id);

        if (! $requisicao) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['erro' => 'Requisição não encontrada']);
        }

        $req = $requisicao[0];
        $req_ids_assoc = array_map(
            fn($r) => $r->req_id,
            $requisicao
        );
        $log = buscaLogTabelaFirst('est_requisicao', $req_ids_assoc);
        $req->usu_nome = buscaUsuarioLog($log[$req->req_id]);

        $estoqueOrigem = indexarEstoque(
            $this->busca->buscaEstoqueDeposito($req->req_deporigem)
        );

        $produtosreq = $this->requisicao->getRequisicaoProdutos($req->req_id);

        $this->pdf = new MymPdf2026(false, false, 'A4', 'L');
        $this->pdf->titulo('Requisição Nº: ' . str_pad($req->req_id, 6, '0', STR_PAD_LEFT));

        $html = $this->htmlRequisicaoEstoque($req, $produtosreq, $estoqueOrigem);
        $this->pdf->html($html);

        return $this->saidaPdf($this->pdf, 'requisicao_' . $req_id . '.pdf', $saida);
    }

    /**
     * Relatório de ocorrência.
     */
    public function PrintOcorrencia($oco_id, string $saida = 'base64')
    {
        $ocorrencias = $this->ocorrencia->getOcorrenciaPdf($oco_id);

        if (! $ocorrencias) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['erro' => 'Ocorrência não encontrada']);
        }

        $oco       = $ocorrencias[0];
        $this->pdf = new MymPdf2026(false, false, 'A4', 'P');
        $this->pdf->titulo('Ocorrência Nº: ' . $oco->oco_id);

        $html = $this->htmlOcorrencia($oco);
        $this->pdf->html($html);

        return $this->saidaPdf($this->pdf, 'ocorrencia_' . $oco_id . '.pdf', $saida);
    }

    protected function htmlAnaRequisicao(object $req, array $requis): string
    {
        $logo = $this->logoBack();
        $rows = '';

        foreach ($requis as $item) {
            $reqi = (object) $item;
            $rows .= '
                <tr>
                    <td style="width:75mm;">' . $this->e($reqi->pro_despro ?? '') . '</td>
                    <td style="width:60mm;">' . $this->e($reqi->fab_apeFab ?? '') . '</td>
                    <td style="width:25mm;">' . $this->e($reqi->lot_lote ?? '') . '</td>
                    <td style="width:25mm;">' . $this->e(data_br($reqi->lot_validade ?? '')) . '</td>
                </tr>';
        }

        $loteOuMetodo = '';
        if (($req->req_lotemb ?? '') !== '') {
            $loteOuMetodo = '<span class="label">Lote:</span> ' . $this->e($req->req_lotemb);
        } else {
            $loteOuMetodo = '<span class="label">Método:</span> ' . $this->e($req->ana_descmetodo ?? '');
        }

        return $this->pdf->cssBase() . '
        <div class="pdf-page">
            <table class="header-box">
                <tr>
                    <td style="width:20mm; padding-left:1mm; vertical-align:middle;"><img src="' . $this->e($logo) . '" class="logo"></td>
                    <td style="text-align:right; padding-right:2mm; vertical-align:middle; font-size:11pt;">
                        <strong>' . $this->e($req->cla_cabecalho ?? '') . '</strong><br>
                        N° ' . $this->e((string) $req->req_id) . '
                    </td>
                </tr>
            </table>

            <div class="box" style="min-height:25mm;">
                <table>
                    <tr>
                        <td style="width:50%;">' . $loteOuMetodo . '</td>
                        <td style="width:50%;"></td>
                    </tr>
                    <tr>
                        <td><span class="label">Data:</span> ' . $this->e(substr(data_br($req->req_data ?? ''), 0, 10)) . '</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td><span class="label">Responsável:</span> ' . $this->e($req->usu_login ?? '') . '</td>
                        <td><span class="label">Horário:</span> ' . $this->e(substr(data_br($req->req_data ?? ''), 11, 5)) . '</td>
                    </tr>
                </table>
            </div>

            <table class="table-border small" autosize="1">
                <thead>
                    <tr>
                        <th style="width:75mm; text-align:left;">Produto</th>
                        <th style="width:60mm; text-align:left;">Fabricante</th>
                        <th style="width:25mm; text-align:left;">Lote</th>
                        <th style="width:25mm; text-align:left;">Validade</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>

            <div class="box small" style="min-height:10mm;">
                ' . nl2br($this->e($req->cla_rodape ?? '')) . '
            </div>
        </div>';
    }

    protected function htmlRequisicaoEstoque(object $req, array $produtosreq, array $estoqueOrigem): string
    {
        $rows = '';

        foreach ($produtosreq as $reqi) {
            $estoqProd = $estoqueOrigem[$reqi->pro_codpro][$reqi->lot_lote][0] ?? null;
            $estoqueOrigemQtd = $estoqProd
                ? (int) str_replace('.', '', (string) $estoqProd->quantidadeEstoque)
                : 0;

            $conferencia     = '';
            $dataConferencia = data_br($reqi->rpa_data_conferencia ?? '');
            if (($reqi->rpa_conferida ?? 0) == -1) {
                $conferencia     = 'NA';
                $dataConferencia = 'NA';
            } elseif (($reqi->rpa_conferida ?? 0) != 0) {
                $conferencia = (string) $reqi->rpa_conferida;
            }

            $aprovada     = '';
            $dataInspecao = data_br($reqi->rpa_data_inspecao ?? '');
            if (($reqi->rpa_aprovada ?? 0) == -1) {
                $aprovada     = 'NA';
                $dataInspecao = 'NA';
            } elseif (($reqi->rpa_aprovada ?? 0) != 0) {
                $aprovada = (string) $reqi->rpa_aprovada;
            }

            $rows .= '
            <tr>
                <td class="text-center nowrap">' . $this->e($reqi->pro_codpro ?? '') . '</td>
                <td>' . $this->e($reqi->pro_despro ?? '') . '</td>
                <td>' . $this->e($reqi->fab_apeFab ?? '') . '</td>
                <td class="text-center">' . $this->e(($reqi->lot_lote ?? '') . ' ' . data_br($reqi->lot_validade ?? '')) . '</td>
                <td class="text-right">' . $this->e((string) $estoqueOrigemQtd) . '</td>
                <td class="text-right">' . $this->e((string) ($reqi->qtd_caixa ?? '')) . '</td>
                <td class="text-right">' . $this->e((string) ($reqi->rep_multiplicador ?? '')) . '</td>
                <td class="text-right">' . $this->e((string) ($reqi->rep_seguranca ?? '')) . '%</td>
                <td class="text-right">' . $this->e((string) ($reqi->rep_quantia ?? '')) . '</td>
                <td class="text-right">' . $this->e((string) ($reqi->rpa_cancelada ?? '')) . '</td>
                <td class="text-right">' . $this->e((string) ($reqi->rpa_atendida ?? '')) . '</td>
                <td class="text-center">' . $this->e(data_br($reqi->rpa_data ?? '')) . '</td>
                <td class="text-right">' . $this->e($conferencia) . '</td>
                <td class="text-center">' . $this->e($dataConferencia) . '</td>
                <td class="text-right">' . $this->e($aprovada) . '</td>
                <td class="text-center">' . $this->e($dataInspecao) . '</td>
            </tr>';
        }

        $repetedias = '';
        if (($req->req_repetedias ?? 0) > 0) {
            $repetedias = 'Requisição repetida para ' . $req->req_repetedias . ' dia(s)';
        }

        return $this->pdf->cssBase() . '
        <style>
            body { font-size: 7.2pt; }
            .header-title { font-size: 14pt; font-weight:bold; }
            .info td { padding: 0.8mm 0; font-size: 9pt; }
            .req-table th { font-size: 6.4pt; height: 18mm; padding: 0.6mm; }
            .req-table td { font-size: 6.2pt; padding: 0.7mm; }

            @page {
                header: requisicao-header;
                margin-top: 18mm; /* ajuste conforme a altura do seu header-box */
            }
        </style>

        <htmlpageheader name="requisicao-header">
            <table class="header-box">
                <tr>
                    <td style="width:20mm; padding-left:1mm; vertical-align:middle;"><img src="' . $this->e($this->logoBack()) . '" class="logo"></td>
                    <td class="text-right header-title" style="vertical-align:middle; padding-right:2mm;">
                        Requisição N° ' . $this->e(str_pad($req->req_id, 6, '0', STR_PAD_LEFT)) . '
                    </td>
                </tr>
            </table>
        </htmlpageheader>

        <div class="box-info" style="padding-top: 3mm;">
        <table class="info">
            <tr>
                <td><span class="label">Data da Requisição:</span> ' . $this->e(data_br($req->req_data ?? '')) . '</td>
                <td class="text-right"><span class="label">Data para Entrega:</span> ' . $this->e(substr(data_br($req->req_dataentrega ?? ''), 0, 10)) . '</td>
            </tr>
            <tr>
                <td><span class="label">Tipo de Movimentação:</span> ' . $this->e($req->tmo_nome ?? '') . '</td>
                <td class="text-right">' . $this->e($repetedias) . '</td>
            </tr>
            <tr>
                <td><span class="label">Depósito de Origem:</span> ' . $this->e($req->desdeporigem ?? '') . '</td>
                <td class="text-right"><span class="label">Consumo dia Anterior:</span> ' . (($req->req_consdiaanterior ?? '') === 'S' ? 'Sim' : 'Não') . '</td>
            </tr>
            <tr>
                <td><span class="label">Depósito de Destino:</span> ' . $this->e($req->desdepdestino ?? '') . '</td>
                <td class="text-right"><span class="label">Percentual de Segurança (%):</span> ' . $this->e((string) ($req->req_percseguranca ?? '')) . '%</td>
            </tr>
            <tr>
                <td><span class="label">Status:</span> ' . $this->e($req->stt_nome ?? '') . '</td>
                <td class="text-right"><span class="label">Usuário:</span> ' . $this->e($req->usu_nome ?? '') . '</td>
            </tr>
            <tr>
                <td colspan="2"><span class="label">Observações:</span><br>' . nl2br($this->e($req->req_observacao ?? '')) . '</td>
            </tr>
        </table>
        </div>

        <table class="table-border req-table" autosize="1">
            <thead>
                <tr>
                    <th style="width:15mm; text-align:center; vertical-align:middle">Cód ERP</th>
                    <th style="width:50mm; text-align:center; vertical-align:middle">Descrição</th>
                    <th style="width:40mm; text-align:center; vertical-align:middle">Fabricante</th>
                    <th style="width:20mm; text-align:center; vertical-align:middle">Lote<br>Validade</th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Saldo Origem</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Qtde Caixa</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Multiplica</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">% Seg</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Requisição</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Cancelada</div></th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;"><div class="col-12 float-start text-start">Atendida</div></th>
                    <th style="width:20mm; text-align:center; vertical-align:middle">Data<br>Atendimento</th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;">Conferida</th>
                    <th style="width:20mm; text-align:center; vertical-align:middle">Data<br>Conferência</th>
                    <th text-rotate="180" style="width:8mm; height:20mm; text-align:center; vertical-align:bottom;">Aprovada</th>
                    <th style="width:20mm; text-align:center; vertical-align:middle">Data<br>Inspeção</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>';
    }

    protected function htmlOcorrencia(object $oco): string
    {
        return $this->pdf->cssBase() . '
        <div class="pdf-page">
            <table class="header-box">
                <tr>
                    <td style="width:20mm; padding-left:1mm; vertical-align:middle;"><img src="' . $this->e($this->logoBack()) . '" class="logo"></td>
                    <td style="text-align:right; padding-right:2mm; vertical-align:middle; font-size:11pt;">
                        <strong>OCORRÊNCIA</strong><br>
                        N° ' . $this->e((string) ($oco->oco_id ?? '')) . '
                    </td>
                </tr>
            </table>

            <div class="box" style="min-height:21mm;">
                <table>
                    <tr>
                        <td style="width:50%;"><span class="label">Tipo:</span> ' . $this->e($oco->tpo_nome ?? '') . '</td>
                        <td style="width:50%;" rowspan="4"><span class="label">Data:</span> ' . $this->e(substr(data_br($oco->oco_data ?? ''), 0, 16)) . '</td>
                    </tr>
                    <tr><td><span class="label">Subtipo:</span> ' . $this->e($oco->sut_nome ?? '') . '</td></tr>
                    <tr><td><span class="label">Produto:</span> ' . $this->e($oco->pro_despro ?? '') . '</td></tr>
                    <tr><td><span class="label">Lote:</span> ' . $this->e($oco->lot_lote ?? '') . '</td></tr>
                </table>
            </div>

            <div class="box" style="min-height:36mm;">
                <strong>Descrição:</strong><br>
                ' . nl2br($this->e($oco->oco_descricao ?? '')) . '
            </div>
        </div>';
    }

    protected function saidaPdf(MymPdf2026 $pdf, string $filename, string $saida = 'base64', ?string $savePath = null)
    {
        $saida = strtolower($saida);

        if ($saida === 'inline') {
            $content = $pdf->Output($filename, 'I');
            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->setBody($content);
        }

        if ($saida === 'download') {
            $content = $pdf->Output($filename, 'S');
            return $this->response->download($filename, $content, true);
        }

        if ($saida === 'save') {
            $path = $savePath ?: WRITEPATH . 'uploads/pdf/' . $filename;
            $pdf->save($path);
            return $this->response->setJSON([
                'arquivo' => $filename,
                'path'    => $path,
            ]);
        }

        // Padrão mantido para AJAX, mas agora com Content-Type correto.
        return $this->response
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->setBody($pdf->outputBase64Json());
    }

    protected function logoBack(): string
    {
        $path = 'assets/images/logo-back.png';
        if (defined('FCPATH') && is_file(FCPATH . $path)) {
            return FCPATH . $path;
        }

        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Relatório Genérico (Gerador de Relatórios)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gera PDF do relatório genérico a partir da configuração salva no banco.
     */
    public function PrintRelatorioGenerico(int $rel_id, string $saida = 'base64')
    {
        $db = db_connect('default');

        $relatorio = $db->table('cfg_relatorios')->where('rel_id', $rel_id)->get()->getFirstRow();
        if (!$relatorio) {
            return $this->response->setStatusCode(404)->setJSON(['erro' => 'Relatório não encontrado']);
        }

        $colunasBD = $db->table('cfg_rel_colunas')->where('rel_id', $rel_id)->orderBy('rco_ordem')->get()->getResult();
        $filtrosBD = $db->table('cfg_rel_filtros')->where('rel_id', $rel_id)->orderBy('rfi_ordem')->get()->getResult();

        $colunas = [];
        foreach ($colunasBD as $col) {
            $colunas[] = [
                'tabela'      => $col->rco_tabela,
                'campo'       => $col->rco_campo,
                'tamanho'     => (int) $col->rco_tamanho,
                'tipo_dado'   => $col->rco_tipo_dado ?? '',
                'label'       => $col->rco_label,
                'alinhamento' => $col->rco_alinhamento,
                'totalizar'   => (int) ($col->rco_totalizar ?? 0),
            ];
        }

        $filtros = [];
        foreach ($filtrosBD as $f) {
            $filtros[] = [
                'campo'       => $f->rfi_campo,
                'tabela'      => $f->rfi_tabela,
                'tipo_filtro' => $f->rfi_tipo_filtro,
                'label'       => $f->rfi_label,
            ];
        }

        $config = [
            'titulo'              => $relatorio->rel_titulo,
            'nome'                => $relatorio->rel_nome ?? '',
            'formato'             => $relatorio->rel_formato,
            'tamanho_fonte'       => (int) $relatorio->rel_tamanho_fonte,
            'tabela_base'         => $relatorio->rel_tabela_base,
            'totalizar_registros' => (int) ($relatorio->rel_totalizar_registros ?? 0),
            'colunas'             => $colunas,
            'filtros'             => $filtros,
        ];

        $orientacao = $relatorio->rel_formato === 'L' ? 'L' : 'P';
        // ANTES (BKP 30/06/2026): $this->pdf = new MymPdf2026(true, true, 'A4', $orientacao);
        // temHeader=false: com $paraPdf=true, htmlRelatorioGenerico() já injeta o cabeçalho
        // (logo/título/subtítulo) via <htmlpageheader> nativo do mPDF — temHeader=true aqui
        // duplicaria com o cabeçalho automático da própria MymPdf2026.
        $this->pdf  = new MymPdf2026(false, true, 'A4', $orientacao);
        $this->pdf->titulo($config['titulo']);

        // ANTES (BKP 30/06/2026): $html = $this->htmlRelatorioGenerico($config, false);
        $html = $this->htmlRelatorioGenerico($config, false, [], true);
        $this->pdf->html($html);

        return $this->saidaPdf($this->pdf, 'relatorio_' . $rel_id . '.pdf', $saida);
    }

    /**
     * Resolve schema, monta e executa o SQL do relatório (com WHERE dos
     * filtros reais quando aplicável) e enriquece $dados com
     * usuario_inc/usuario_alt via MongoDB. Extraído de htmlRelatorioGenerico()
     * pra ser reaproveitado pelos exportadores Excel/Word (ver
     * exportarExcelRelatorio()/exportarWordRelatorio() abaixo), que precisam
     * dos mesmos dados filtrados sem montar HTML.
     *
     * @param array $config  titulo, formato, tamanho_fonte, tabela_base, colunas[], filtros[], totalizar_registros
     * @param bool  $preview true = LIMIT 10 sem WHERE, false = filtros reais
     * @param array $valoresFiltro valores selecionados pelo usuário p/ montar WHERE real
     * @return array{dados: array, colunas: array}
     */
    protected function _buscarDadosRelatorio(array $config, bool $preview = false, array $valoresFiltro = []): array
    {
        $tabelaBase = $config['tabela_base'] ?? '';
        $colunas    = $config['colunas'] ?? [];

        if (empty($tabelaBase) || empty($colunas)) {
            return ['dados' => [], 'colunas' => $colunas];
        }

        $dicDados = new \App\Models\Config\ConfigDicDadosModel();

        // Resolver schema para cada tabela
        $resolverSchema = function (string $tabela) use ($dicDados): string {
            static $cache = [];
            if (!isset($cache[$tabela])) {
                $info = $dicDados->getDbGroupAndSchema($tabela);
                $cache[$tabela] = !empty($info['schema']) ? $info['schema'] . '.' . $tabela : $tabela;
            }
            return $cache[$tabela];
        };

        // ── Buscar dados ────────────────────────────────────────────────
        $dados = [];
        try {
            $tabelaBaseSchema = $resolverSchema($tabelaBase);

            $selCols = [];
            $tabelasJoin = [];
            foreach ($colunas as $col) {
                $tabSchema = $resolverSchema($col['tabela']);
                $selCols[] = "{$tabSchema}.{$col['campo']} AS '{$col['label']}'";
                if ($col['tabela'] !== $tabelaBase && !in_array($col['tabela'], $tabelasJoin)) {
                    $tabelasJoin[] = $col['tabela'];
                }
            }

            $dbGrSche = $dicDados->getDbGroupAndSchema($tabelaBase);
            $dbConn   = db_connect($dbGrSche['dbGroup']);

            $sql = 'SELECT ' . implode(', ', $selCols) . ' FROM ' . $tabelaBaseSchema;

            if (!empty($tabelasJoin)) {
                // Mapa de relacionamentos por FK constraint
                // Chave: tabela referenciada → condição ON
                $relMap = [];

                $buildRelMap = function (string $tab) use ($dicDados, $resolverSchema, &$relMap) {
                    $rels = $dicDados->getRelacionamentos($tab);
                    foreach ($rels['relacionamentos'] as $r) {
                        if (empty($r['REFERENCED_TABLE_NAME'])) continue;

                        // FK direta: $tab.coluna → referenciada.coluna
                        if ($r['TABLE_NAME'] === $tab) {
                            $chave = $tab . '→' . $r['REFERENCED_TABLE_NAME'];
                            if (!isset($relMap[$chave])) {
                                $relMap[$chave] = [
                                    'de'    => $tab,
                                    'para'  => $r['REFERENCED_TABLE_NAME'],
                                    'on'    => $resolverSchema($tab) . '.' . $r['COLUMN_NAME']
                                        . ' = ' . $resolverSchema($r['REFERENCED_TABLE_NAME']) . '.' . $r['REFERENCED_COLUMN_NAME'],
                                ];
                            }
                        }
                    }
                };

                // Carrega relacionamentos da base e de todas as tabelas a ligar
                $buildRelMap($tabelaBase);
                foreach ($tabelasJoin as $tj) {
                    $buildRelMap($tj);
                }

                // Resolve JOINs transitivos
                $joinedTables = [$tabelaBase];
                $pending      = $tabelasJoin;
                $maxIter      = 10;

                while (!empty($pending) && $maxIter-- > 0) {
                    $nextPending = [];

                    foreach ($pending as $tj) {
                        $tabJoinSchema = $resolverSchema($tj);
                        $on = '';

                        foreach ($joinedTables as $jt) {
                            // Caso 1: $jt tem FK para $tj (jt referencia tj)
                            $chave1 = $jt . '→' . $tj;
                            if (isset($relMap[$chave1])) {
                                $on = $relMap[$chave1]['on'];
                                break;
                            }

                            // Caso 2: $tj tem FK para $jt (tj referencia jt — filha)
                            $chave2 = $tj . '→' . $jt;
                            if (isset($relMap[$chave2])) {
                                $on = $relMap[$chave2]['on'];
                                break;
                            }
                        }

                        if ($on) {
                            $sql .= " LEFT JOIN {$tabJoinSchema} ON {$on}";
                            $joinedTables[] = $tj;
                            $buildRelMap($tj);
                        } else {
                            // Descobre tabelas intermediárias que $tj referencia
                            foreach ($relMap as $chave => $rel) {
                                if ($rel['de'] === $tj && !in_array($rel['para'], $joinedTables) && !in_array($rel['para'], $nextPending) && !in_array($rel['para'], $pending)) {
                                    $nextPending[] = $rel['para'];
                                    $buildRelMap($rel['para']);
                                }
                                if ($rel['para'] === $tj && !in_array($rel['de'], $joinedTables) && !in_array($rel['de'], $nextPending) && !in_array($rel['de'], $pending)) {
                                    $nextPending[] = $rel['de'];
                                    $buildRelMap($rel['de']);
                                }
                            }
                            $nextPending[] = $tj;
                        }
                    }

                    $pending = $nextPending;
                }
            }

            // Aplica filtros reais (WHERE), sempre qualificados pela tabela base
            // (rfi_tabela == rel_tabela_base sempre — ver Utils\Relatorio::gerar())
            $bindings = [];
            if (!$preview && !empty($valoresFiltro)) {
                $whereParts = [];
                foreach ($valoresFiltro as $vf) {
                    $campoQualificado = $tabelaBaseSchema . '.' . $vf['campo'];

                    if (($vf['tipo_filtro'] ?? '') === 'DATE') {
                        if (!empty($vf['de']) && !empty($vf['ate'])) {
                            $whereParts[] = "{$campoQualificado} BETWEEN ? AND ?";
                            $bindings[]   = $vf['de'];
                            $bindings[]   = $vf['ate'];
                        }
                    } else {
                        $valores = $vf['valores'] ?? [];
                        if (!empty($valores)) {
                            $placeholders = implode(',', array_fill(0, count($valores), '?'));
                            $whereParts[] = "{$campoQualificado} IN ({$placeholders})";
                            foreach ($valores as $val) {
                                $bindings[] = $val;
                            }
                        }
                    }
                }
                if (!empty($whereParts)) {
                    $sql .= ' WHERE ' . implode(' AND ', $whereParts);
                }
            }

            // Preview: últimos 10 registros (ORDER BY PK DESC + LIMIT, depois inverte)
            if ($preview) {
                $pkPrev = '';
                $camposPrev = $dicDados->getCampos($tabelaBase);
                foreach ($camposPrev as $cp) {
                    if ($cp['COLUMN_KEY'] === 'PRI') {
                        $pkPrev = $cp['COLUMN_NAME'];
                        break;
                    }
                }
                if (!$pkPrev && !empty($camposPrev)) {
                    $pkPrev = $camposPrev[0]['COLUMN_NAME'];
                }
                if ($pkPrev) {
                    $sql .= ' ORDER BY ' . $tabelaBaseSchema . '.' . $pkPrev . ' DESC';
                }
                $sql .= ' LIMIT 10';
            }

            $result = $dbConn->query($sql, $bindings);
            $dados  = $result ? $result->getResultArray() : [];

            // Preview veio DESC, inverte para exibir na ordem correta
            if ($preview && !empty($dados)) {
                $dados = array_reverse($dados);
            }
        } catch (\Throwable $e) {
            $sql   = $sql ?? '';
            $dados = [];
        }

        // ── Preenche usuario_inc/usu_nome (primeiro log) e usuario_alt (último log) via MongoDB
        $temUsuInc = false;
        $temUsuAlt = false;
        $labelsUsuInc = [];
        $labelUsuAlt  = '';
        foreach ($colunas as $col) {
            // usu_nome segue a mesma regra de usuario_inc (primeiro log = quem incluiu)
            if (in_array($col['campo'], ['usuario_inc', 'usu_nome'])) {
                $temUsuInc = true;
                $labelsUsuInc[] = $col['label'];
            }
            if ($col['campo'] === 'usuario_alt') {
                $temUsuAlt = true;
                $labelUsuAlt = $col['label'];
            }
        }

        if (($temUsuInc || $temUsuAlt) && !empty($dados) && !empty($tabelaBase)) {
            // Descobre a tabela real para log — se for view, extrai do VIEW_DEFINITION
            $tabelaLog = $tabelaBase;
            $pkBase    = '';
            $camposBase = $dicDados->getCampos($tabelaBase);
            foreach ($camposBase as $cb) {
                if ($cb['COLUMN_KEY'] === 'PRI') {
                    $pkBase = $cb['COLUMN_NAME'];
                    break;
                }
            }

            // Se não tem PK, é view — busca a tabela que tem o primeiro campo da view como PK
            if (!$pkBase && !empty($camposBase)) {
                $primeiroCampo = $camposBase[0]['COLUMN_NAME'];
                $pkBase = $primeiroCampo;

                // Busca a BASE TABLE onde esse campo é PK — essa é a tabela original da view
                $dbGrView = $dicDados->getDbGroupAndSchema($tabelaBase);
                $dbView   = db_connect($dbGrView['dbGroup']);
                $resPk    = $dbView->table('information_schema.COLUMNS')
                    ->select('TABLE_NAME')
                    ->where('COLUMN_NAME', $primeiroCampo)
                    ->where('COLUMN_KEY', 'PRI')
                    ->where('TABLE_SCHEMA', $dbGrView['schema'])
                    ->get();
                $rowPk = $resPk ? $resPk->getFirstRow() : null;
                if ($rowPk) {
                    $tabelaLog = $rowPk->TABLE_NAME;
                }
            }

            if ($pkBase) {
                $ids = array_column($dados, $pkBase) ?: array_map(fn($r) => $r[array_key_first($r)] ?? '', $dados);
                // MongoDB armazena log_id_registro como string sem zeros à esquerda
                $ids = array_map(fn($v) => (string) (int) $v, array_filter($ids));
                if (!empty($ids)) {
                    if ($temUsuInc && function_exists('buscaLogTabelaFirst')) {
                        // Usa a tabela real (não a view) para buscar log
                        $logsFirst = buscaLogTabelaFirst($tabelaLog, $ids);
                        foreach ($dados as &$row) {
                            // Primeira chave do row (label) → valor → inteiro (sem zeros à esquerda)
                            $id = (string) (int) reset($row);
                            $nome = isset($logsFirst[$id]) ? buscaUsuarioLog($logsFirst[$id]) : '';
                            foreach ($labelsUsuInc as $lbl) {
                                $row[$lbl] = $nome;
                            }
                        }
                        unset($row);
                    }

                    if ($temUsuAlt && function_exists('buscaLogTabela')) {
                        // Usa a tabela real (não a view) para buscar log
                        $logsLast = buscaLogTabela($tabelaLog, $ids);
                        foreach ($dados as &$row) {
                            $id = (string) (int) reset($row);
                            $row[$labelUsuAlt] = isset($logsLast[$id]) ? buscaUsuarioLog($logsLast[$id]) : '';
                        }
                        unset($row);
                    }
                }
            }
        }

        return ['dados' => $dados, 'colunas' => $colunas];
    }

    /**
     * Monta as partes do subtítulo (um "Label: valor" por filtro efetivamente
     * selecionado) — mesma regra usada no HTML/PDF (ver htmlRelatorioGenerico()),
     * reaproveitada aqui pelos exportadores Excel/Word.
     */
    private function _subtituloFiltros(array $filtros, array $valoresFiltro): array
    {
        $subParts = [];
        foreach ($filtros as $f) {
            $vfMatch = null;
            foreach ($valoresFiltro as $vf) {
                if ($vf['campo'] === $f['campo']) {
                    $vfMatch = $vf;
                    break;
                }
            }
            if (!$vfMatch) {
                continue;
            }
            if ($f['tipo_filtro'] === 'DATE') {
                // 'de'/'ate' vêm com hora (00:00:00/23:59:59, ver Utils\Relatorio::
                // _prepararRelatorioFiltrado()) pro BETWEEN cobrir o dia inteiro em
                // colunas DATETIME; no subtítulo mostramos só a data (sem a hora).
                $txt = data_br(substr($vfMatch['de'], 0, 10)) . ' a ' . data_br(substr($vfMatch['ate'], 0, 10));
            } else {
                $txt = implode(', ', $vfMatch['valores']);
            }
            $subParts[] = $f['label'] . ': ' . $txt;
        }
        return $subParts;
    }

    /**
     * Exporta o relatório em Excel (.xlsx) com os mesmos dados/filtros usados
     * no HTML (ver _buscarDadosRelatorio()). Requer phpoffice/phpspreadsheet
     * (composer require phpoffice/phpspreadsheet).
     *
     * @param array $config  mesma estrutura usada em htmlRelatorioGenerico()
     * @param array $valoresFiltro valores selecionados pelo usuário
     * @return string conteúdo binário do .xlsx pronto pra ser devolvido na resposta
     */
    public function exportarExcelRelatorio(array $config, array $valoresFiltro = []): string
    {
        $resultado = $this->_buscarDadosRelatorio($config, false, $valoresFiltro);
        $dados     = $resultado['dados'];
        $colunas   = $resultado['colunas'];

        // Mesma separação usada no HTML/PDF: colunas "linha inteira" (ex.: Observação)
        // não ficam lado a lado — viram uma linha própria mesclada abaixo do registro.
        $colsNormais  = [];
        $colsLinhaInt = [];
        foreach ($colunas as $col) {
            if (($col['comportamento'] ?? 'cortar') === 'linha') {
                $colsLinhaInt[] = $col;
            } else {
                $colsNormais[] = $col;
            }
        }
        $numColunas = max(1, count($colsNormais));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr((string) ($config['titulo'] ?? 'Relatorio'), 0, 31));

        $ultimaColuna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numColunas);

        // Logo (coluna A, linha 1)
        $logoPath = $this->logoBack();
        if (is_file($logoPath)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath($logoPath);
            $drawing->setHeight(45);
            $drawing->setCoordinates('A1');
            $drawing->setWorksheet($sheet);
        }
        $sheet->getRowDimension(1)->setRowHeight(35);

        // Título (linha 1, ao lado da logo)
        $sheet->mergeCells("B1:{$ultimaColuna}1");
        $sheet->setCellValue('B1', $config['titulo'] ?? '');
        $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Subtítulo (linha 2) — só os filtros efetivamente selecionados
        $subParts = $this->_subtituloFiltros($config['filtros'] ?? [], $valoresFiltro);
        if (!empty($subParts)) {
            $sheet->mergeCells("B2:{$ultimaColuna}2");
            $sheet->setCellValue('B2', implode(' | ', $subParts));
            $sheet->getStyle('B2')->getFont()->setItalic(true)->setSize(10);
            $sheet->getStyle('B2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Cabeçalho das colunas (linha 4, com uma linha em branco de respiro)
        $linhaCabecalho = 4;
        $colIdx = 1;
        foreach ($colsNormais as $col) {
            $sheet->setCellValueByColumnAndRow($colIdx, $linhaCabecalho, $col['label']);
            $colIdx++;
        }
        $sheet->getStyle([1, $linhaCabecalho, $numColunas, $linhaCabecalho])->getFont()->setBold(true);
        $sheet->getStyle([1, $linhaCabecalho, $numColunas, $linhaCabecalho])->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DCDCDC');

        // Congela o cabeçalho na tela e repete nas páginas ao imprimir
        $sheet->freezePane('A' . ($linhaCabecalho + 1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $linhaCabecalho);

        $totais       = array_fill(0, count($colsNormais), 0.0);
        $numRegistros = 0;
        $linha        = $linhaCabecalho + 1;
        foreach ($dados as $row) {
            $colIdx = 1;
            foreach ($colsNormais as $idx => $col) {
                $valor = $row[$col['label']] ?? '';
                $sheet->setCellValueByColumnAndRow($colIdx, $linha, $valor);
                if (!empty($col['totalizar']) && is_numeric($valor)) {
                    $totais[$idx] += (float) $valor;
                }
                $colIdx++;
            }
            $linha++;
            $numRegistros++;

            // Colunas "linha inteira" — uma linha mesclada abaixo do registro,
            // igual ao comportamento no HTML/PDF (só se tiver valor).
            foreach ($colsLinhaInt as $col) {
                $valor = $row[$col['label']] ?? '';
                if ($valor === '' || $valor === null) {
                    continue;
                }
                $sheet->mergeCells("A{$linha}:{$ultimaColuna}{$linha}");
                $sheet->setCellValue("A{$linha}", $col['label'] . ': ' . $valor);
                $sheet->getStyle("A{$linha}")->getFont()->setItalic(true)->setSize(9);
                $linha++;
            }
        }

        // Totalizadores por coluna (rco_totalizar) — mesma regra do HTML/PDF
        $temTotalizador = false;
        foreach ($colsNormais as $col) {
            if (!empty($col['totalizar'])) {
                $temTotalizador = true;
                break;
            }
        }
        if ($temTotalizador) {
            $colIdx = 1;
            foreach ($colsNormais as $idx => $col) {
                if (!empty($col['totalizar'])) {
                    $sheet->setCellValueByColumnAndRow($colIdx, $linha, $totais[$idx]);
                    $sheet->getStyleByColumnAndRow($colIdx, $linha)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $colIdx++;
            }
            $sheet->getStyle([1, $linha, $numColunas, $linha])->getFont()->setBold(true);
            $linha++;
        }

        // Total de registros (rel_totalizar_registros)
        if (!empty($config['totalizar_registros'])) {
            $sheet->mergeCells("A{$linha}:{$ultimaColuna}{$linha}");
            $sheet->setCellValue("A{$linha}", 'Total de registros: ' . $numRegistros);
            $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
            $sheet->getStyle("A{$linha}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }

        foreach (range(1, $numColunas) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Exporta o relatório em Word (.docx) com os mesmos dados/filtros usados
     * no HTML. Requer phpoffice/phpword (composer require phpoffice/phpword).
     *
     * @param array $config  mesma estrutura usada em htmlRelatorioGenerico()
     * @param array $valoresFiltro valores selecionados pelo usuário
     * @return string conteúdo binário do .docx pronto pra ser devolvido na resposta
     */
    public function exportarWordRelatorio(array $config, array $valoresFiltro = []): string
    {
        $resultado = $this->_buscarDadosRelatorio($config, false, $valoresFiltro);
        $dados      = $resultado['dados'];
        $colunas    = $resultado['colunas'];
        $paisagem   = ($config['formato'] ?? 'P') === 'L';

        // Mesma separação usada no HTML/PDF: colunas "linha inteira" (ex.: Observação)
        // não ficam lado a lado — viram uma linha própria mesclada abaixo do registro.
        $colsNormais  = [];
        $colsLinhaInt = [];
        foreach ($colunas as $col) {
            if (($col['comportamento'] ?? 'cortar') === 'linha') {
                $colsLinhaInt[] = $col;
            } else {
                $colsNormais[] = $col;
            }
        }
        $numColunas = max(1, count($colsNormais));

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection([
            'orientation' => $paisagem ? 'landscape' : 'portrait',
        ]);

        // Largura das colunas distribuída pelo espaço realmente disponível na página
        // (A4, margem padrão de 1440 twips/1" de cada lado) — antes cada célula tinha
        // 2000 twips fixos, e com várias colunas a soma passava da largura da página,
        // cortando as últimas colunas pra fora da folha.
        $larguraDisponivel = $paisagem ? 13958 : 9026;
        $larguraColuna     = intdiv($larguraDisponivel, $numColunas);

        // Cabeçalho de página (logo + título + subtítulo) — usa o header nativo do
        // Word, que repete IGUAL em toda página (antes ficava só no corpo do texto,
        // por isso só aparecia na 1ª página). Logo e título ficam numa tabela sem
        // borda, lado a lado (mesma altura) — ANTES (BKP 01/07/2026) cada um era
        // adicionado direto no header, o que empilha um embaixo do outro no Word.
        $header = $section->addHeader();
        $tabelaCabecalho = $header->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $tabelaCabecalho->addRow();

        $larguraLogo = 1500;
        $celulaLogo  = $tabelaCabecalho->addCell($larguraLogo, ['valign' => 'center']);
        $logoPath    = $this->logoBack();
        if (is_file($logoPath)) {
            $celulaLogo->addImage($logoPath, ['height' => 40]);
        }

        $celulaTitulo = $tabelaCabecalho->addCell($larguraDisponivel - $larguraLogo, ['valign' => 'center']);
        $celulaTitulo->addText(
            $this->e($config['titulo'] ?? ''),
            ['bold' => true, 'size' => 14],
            ['alignment' => 'center']
        );
        $subParts = $this->_subtituloFiltros($config['filtros'] ?? [], $valoresFiltro);
        if (!empty($subParts)) {
            $celulaTitulo->addText(
                $this->e(implode(' | ', $subParts)),
                ['italic' => true, 'size' => 9, 'color' => '555555'],
                ['alignment' => 'center']
            );
        }

        $table = $section->addTable([
            'borderSize'  => 6,
            'borderColor' => '999999',
            'width'       => 100 * 50,
            'unit'        => 'pct',
        ]);

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($colsNormais as $col) {
            $table->addCell($larguraColuna)->addText($this->e($col['label']), ['bold' => true]);
        }

        $totais       = array_fill(0, count($colsNormais), 0.0);
        $numRegistros = 0;
        foreach ($dados as $row) {
            $table->addRow();
            foreach ($colsNormais as $idx => $col) {
                $valor = $row[$col['label']] ?? '';
                $table->addCell($larguraColuna)->addText($this->e((string) $valor));
                if (!empty($col['totalizar']) && is_numeric($valor)) {
                    $totais[$idx] += (float) $valor;
                }
            }
            $numRegistros++;

            // Colunas "linha inteira" — uma linha só, célula mesclada (gridSpan)
            // abaixo do registro, igual ao comportamento no HTML/PDF.
            foreach ($colsLinhaInt as $col) {
                $valor = $row[$col['label']] ?? '';
                if ($valor === '' || $valor === null) {
                    continue;
                }
                $table->addRow();
                $celula = $table->addCell($larguraDisponivel, ['gridSpan' => $numColunas]);
                $celula->addText($this->e($col['label'] . ': ' . (string) $valor), ['italic' => true, 'size' => 9]);
            }
        }

        // Totalizadores por coluna (rco_totalizar) — mesma regra do HTML/PDF
        $temTotalizador = false;
        foreach ($colsNormais as $col) {
            if (!empty($col['totalizar'])) {
                $temTotalizador = true;
                break;
            }
        }
        if ($temTotalizador) {
            $table->addRow();
            foreach ($colsNormais as $idx => $col) {
                $valorTxt = !empty($col['totalizar']) ? number_format($totais[$idx], 2, ',', '.') : '';
                $table->addCell($larguraColuna)->addText($this->e($valorTxt), ['bold' => true]);
            }
        }

        // Total de registros (rel_totalizar_registros)
        if (!empty($config['totalizar_registros'])) {
            $table->addRow();
            $celula = $table->addCell($larguraDisponivel, ['gridSpan' => $numColunas]);
            $celula->addText(
                $this->e('Total de registros: ' . $numRegistros),
                ['bold' => true],
                ['alignment' => 'right']
            );
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Gera HTML do relatório genérico.
     * Usado para preview (HTML direto) e para PDF real (alimenta mPDF).
     *
     * @param array $config  titulo, formato, tamanho_fonte, tabela_base, colunas[], filtros[], totalizar_registros
     * @param bool  $preview true = LIMIT 10 sem WHERE, false = filtros reais
     * @param array $valoresFiltro valores selecionados pelo usuário p/ montar WHERE real (ver Utils\Relatorio::gerar())
     * @param bool  $paraPdf true = logo via caminho de arquivo (mPDF), false = logo via URL (exibição em navegador)
     */
    // ANTES (BKP 30/06/2026): public function htmlRelatorioGenerico(array $config, bool $preview = false): string
    // ANTES (BKP 30/06/2026): public function htmlRelatorioGenerico(array $config, bool $preview = false, array $valoresFiltro = []): string
    public function htmlRelatorioGenerico(array $config, bool $preview = false, array $valoresFiltro = [], bool $paraPdf = false): string
    {
        $titulo    = $config['titulo'] ?? '';
        $formato   = $config['formato'] ?? 'P';
        $fonte     = $config['tamanho_fonte'] ?? 10;
        $tabelaBase = $config['tabela_base'] ?? '';
        $colunas   = $config['colunas'] ?? [];
        $filtros   = $config['filtros'] ?? [];
        $totReg    = $config['totalizar_registros'] ?? 0;

        if (empty($tabelaBase) || empty($colunas)) {
            return '<div style="padding:10px; color:#999; font-style:italic;">Selecione a tabela base e adicione colunas para visualizar o preview.</div>';
        }

        $largura = $formato === 'L' ? '277mm' : '190mm';
        $alinMap = ['E' => 'left', 'C' => 'center', 'D' => 'right'];

        // ANTES (BKP 30/06/2026): resolução de schema + SQL + WHERE + enriquecimento Mongo
        // ficavam todos aqui dentro; extraídos pra _buscarDadosRelatorio() (ver acima) pra
        // serem reaproveitados por exportarExcelRelatorio()/exportarWordRelatorio().
        $resultado = $this->_buscarDadosRelatorio($config, $preview, $valoresFiltro);
        $dados     = $resultado['dados'];

        // ── Separar colunas normais e linha inteira ─────────────────────
        $colsNormais    = [];
        $colsLinhaInt   = [];
        foreach ($colunas as $idx => $col) {
            $col['_idx'] = $idx;
            if (($col['comportamento'] ?? 'cortar') === 'linha') {
                $colsLinhaInt[] = $col;
            } else {
                $colsNormais[] = $col;
            }
        }
        $numNormais = count($colsNormais);

        // ── Montar HTML ─────────────────────────────────────────────────
        $html = '<div style="font-family:Arial,sans-serif; font-size:' . $fonte . 'pt; max-width:' . $largura . '; margin:0 auto; padding:5px;">';

        // Logo + Título
        // ANTES (BKP 30/06/2026): $logoUrl = $preview ? base_url('assets/images/logo-back.png') : $this->logoBack();
        // $preview e o "gerar" real (Utils\Relatorio::gerar()) exibem HTML direto no navegador
        // (precisam de URL); só a geração de PDF via mPDF (PrintRelatorioGenerico) precisa do
        // caminho de arquivo. Por isso a logo agora depende de $paraPdf, não de $preview.
        $logoUrl = $paraPdf ? $this->logoBack() : base_url('assets/images/logo-back.png');
        $headerHtml = '<table style="width:100%; border-bottom:1px solid #999; margin-bottom:5px;">'
            . '<tr>'
            . '<td style="width:20mm;"><img src="' . $this->e($logoUrl) . '" style="height:15mm;"></td>'
            . '<td style="text-align:center; font-size:16pt; font-weight:bold; vertical-align:middle;">' . $this->e($titulo) . '</td>'
            . '</tr></table>';

        // Subtítulo (filtros) — só mostra os filtros efetivamente selecionados; os
        // demais (sem valor em $valoresFiltro) ficam de fora, em vez de "Campo: Todos".
        if (!empty($filtros)) {
            $subParts = [];
            foreach ($filtros as $f) {
                $vfMatch = null;
                foreach ($valoresFiltro as $vf) {
                    if ($vf['campo'] === $f['campo']) {
                        $vfMatch = $vf;
                        break;
                    }
                }
                // ANTES (BKP 30/06/2026): filtro sem valor selecionado entrava no subtítulo como "Todos"
                if (!$vfMatch) {
                    continue;
                }
                if ($f['tipo_filtro'] === 'DATE') {
                    // 'de'/'ate' vêm com hora (00:00:00/23:59:59, ver Utils\Relatorio::
                    // _prepararRelatorioFiltrado()) pro BETWEEN cobrir o dia inteiro em
                    // colunas DATETIME; no subtítulo mostramos só a data (sem a hora).
                    $txt = data_br(substr($vfMatch['de'], 0, 10)) . ' a ' . data_br(substr($vfMatch['ate'], 0, 10));
                } else {
                    // ANTES (BKP 30/06/2026): mostrava só a contagem ("N selecionado(s)"), não os valores
                    $txt = implode(', ', array_map([$this, 'e'], $vfMatch['valores']));
                }
                $subParts[] = $this->e($f['label']) . ': ' . $txt;
            }
            if (!empty($subParts)) {
                $headerHtml .= '<div style="text-align:center; font-size:14pt; color:#555; margin-bottom:5px;">'
                    . implode(' &nbsp;|&nbsp; ', $subParts) . '</div>';
            }
        }

        // No PDF, o cabeçalho vira um <htmlpageheader> nativo do mPDF — repete
        // IGUAL em toda página. Utils\Relatorio::exportarPdf() constrói a MymPdf2026
        // com $temHeader=false quando $paraPdf=true, pra não duplicar com o cabeçalho
        // automático da lib (era isso que causava logo duplicada na pág.1 e cabeçalho
        // genérico/diferente a partir da pág.2).
        if ($paraPdf) {
            $html .= '<style>@page { header: relatorio-cabecalho; margin-top: 32mm; }</style>'
                . '<htmlpageheader name="relatorio-cabecalho">' . $headerHtml . '</htmlpageheader>';
        } else {
            $html .= $headerHtml;
        }

        // Tabela
        $html .= '<table style="width:100%; border-collapse:collapse; font-size:' . $fonte . 'pt;">';

        // Cabeçalho (só colunas normais)
        $html .= '<thead><tr>';
        foreach ($colsNormais as $col) {
            $alin = $alinMap[$col['alinhamento']] ?? 'left';
            // Largura em ch (caracteres) — respeitando o valor definido pelo usuário
            $larg = (int) ($col['largura'] ?? 0);
            $w    = $larg > 0 ? "width:{$larg}ch; max-width:{$larg}ch;" : '';
            $html .= '<th style="border:1px solid #000; background:#dcdcdc; padding:2px 4px; text-align:' . $alin . '; font-weight:bold; ' . $w . '">'
                . $this->e($col['label']) . '</th>';
        }
        $html .= '</tr></thead>';

        // Dados
        $html .= '<tbody>';
        $totais  = array_fill(0, count($colunas), 0);
        $numRows = 0;

        if (empty($dados)) {
            $html .= '<tr><td colspan="' . $numNormais . '" style="border:1px solid #ccc; padding:8px; text-align:center; color:#999;">'
                . ($preview ? 'Nenhum registro encontrado na tabela base.' : 'Sem dados.') . '</td></tr>';
        } else {
            foreach ($dados as $row) {
                // Linha principal (colunas normais)
                $html .= '<tr>';
                foreach ($colsNormais as $col) {
                    $valor = $row[$col['label']] ?? '';
                    $tipo  = strtolower($col['tipo_dado'] ?? '');

                    if ($valor !== '' && in_array($tipo, ['date', 'datetime', 'timestamp'])) {
                        $valor = function_exists('data_br') ? data_br($valor) : $valor;
                    }

                    $alin    = $alinMap[$col['alinhamento']] ?? 'left';
                    $larg    = (int) ($col['largura'] ?? 0);
                    $w       = $larg > 0 ? "width:{$larg}ch; max-width:{$larg}ch;" : '';
                    $comport = $col['comportamento'] ?? 'cortar';

                    // Quebrar: limita a 3 linhas (3 x largura caracteres), restante corta
                    if ($comport === 'quebrar' && $larg > 0) {
                        $maxChars = $larg * 3;
                        if (mb_strlen((string) $valor) > $maxChars) {
                            $valor = mb_substr((string) $valor, 0, $maxChars) . '...';
                        }
                    }

                    $estilo = 'border:1px solid #ccc; padding:1px 4px; text-align:' . $alin . '; ' . $w;
                    if ($comport === 'cortar') {
                        $estilo .= ' overflow:hidden; white-space:nowrap; text-overflow:ellipsis;';
                    } elseif ($comport === 'quebrar') {
                        $estilo .= ' word-wrap:break-word; overflow:hidden;';
                    }

                    $html .= '<td style="' . $estilo . '">' . $this->e((string) $valor) . '</td>';

                    if ($col['totalizar'] && is_numeric($valor)) {
                        $totais[$col['_idx']] += (float) $valor;
                    }
                }
                $html .= '</tr>';

                // Linhas inteiras (abaixo da linha principal)
                foreach ($colsLinhaInt as $col) {
                    $valor = $row[$col['label']] ?? '';
                    $tipo  = strtolower($col['tipo_dado'] ?? '');

                    if ($valor !== '' && in_array($tipo, ['date', 'datetime', 'timestamp'])) {
                        $valor = function_exists('data_br') ? data_br($valor) : $valor;
                    }

                    if ($valor !== '') {
                        $html .= '<tr><td colspan="' . $numNormais . '" style="border:1px solid #ccc; padding:1px 4px; word-wrap:break-word;">'
                            . '<strong>' . $this->e($col['label']) . ':</strong> '
                            . $this->e((string) $valor) . '</td></tr>';
                    }

                    if ($col['totalizar'] && is_numeric($valor)) {
                        $totais[$col['_idx']] += (float) $valor;
                    }
                }

                $numRows++;
            }
        }
        $html .= '</tbody>';

        // Rodapé (totalizadores)
        $temTotalizador = false;
        foreach ($colunas as $col) {
            if ($col['totalizar']) {
                $temTotalizador = true;
                break;
            }
        }

        if ($temTotalizador || $totReg) {
            $html .= '<tfoot>';

            if ($temTotalizador) {
                $html .= '<tr style="font-weight:bold; background:#f0f0f0;">';
                foreach ($colunas as $idx => $col) {
                    $alin = $alinMap[$col['alinhamento']] ?? 'left';
                    $val  = $col['totalizar'] ? number_format($totais[$idx], 2, ',', '.') : '';
                    $html .= '<td style="border:1px solid #000; padding:2px 4px; text-align:' . $alin . ';">' . $val . '</td>';
                }
                $html .= '</tr>';
            }

            if ($totReg) {
                $html .= '<tr><td colspan="' . count($colunas) . '" style="border:1px solid #000; padding:2px 4px; text-align:right; font-weight:bold;">'
                    . 'Total de registros: ' . $numRows . '</td></tr>';
            }

            $html .= '</tfoot>';
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Documento Genérico (Gerador de Relatórios — rel_tipo_saida=DOCUMENTO)
    //
    //  Diferente do Tabular (que gera um SELECT único, cacheado em
    //  rel_sql_gerado — ver PrintRelatorioGenerico()), o Documento não tem SQL
    //  pré-gerado: a query é montada em tempo de impressão/preview a partir da
    //  config gravada (ou do POST em andamento, no preview do admin) + do
    //  :id_registro — ver docs/desenvolvimento (plano "Novo tipo de saída
    //  Documento no gerador de relatórios").
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gera o PDF de um Documento configurado, para UM registro específico
     * (:id_registro) da tabela base do relatório $rel_id.
     *
     * $saida: inline | download | save | base64 (ver saidaPdf()).
     */
    public function PrintDocumentoGenerico($rel_id, $id_registro, string $saida = 'base64')
    {
        $config = $this->_montarDadosDocumento((int) $rel_id, (int) $id_registro);

        if ($config === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['erro' => 'Relatório do tipo Documento não encontrado, ou registro inválido']);
        }

        $orientacao = ($config['formato'] ?? 'P') === 'L' ? 'L' : 'P';
        $this->pdf  = new MymPdf2026(false, false, 'A4', $orientacao);
        // *claude* metadado do PDF (título da aba/janela do leitor) — usa o
        // nome administrativo do relatório (rel_nome). O TÍTULO IMPRESSO de
        // verdade (valor resolvido dinamicamente de titulo_tabela/titulo_campo)
        // é outra coisa, calculado dentro de htmlDocumentoGenerico() abaixo.
        $this->pdf->titulo($config['rel_nome'] ?? '');

        $html = $this->htmlDocumentoGenerico($config, false);
        $this->pdf->html($html);

        return $this->saidaPdf($this->pdf, 'documento_' . $rel_id . '_' . $id_registro . '.pdf', $saida);
    }

    /**
     * Carrega a configuração gravada de um relatório tipo DOCUMENTO
     * (cfg_relatorios + cfg_rel_camposcab + cfg_rel_colunas_doc +
     * cfg_rel_textoslivres + cfg_rel_joins dos dois grupos) e monta o array
     * $config consumido por htmlDocumentoGenerico()/_buscarDadosDocumento().
     * Reaproveitado só pela impressão real (PrintDocumentoGenerico) — o
     * preview do admin (CfgRelatorio::previewDocumento()) monta o $config
     * equivalente a partir do POST em andamento, porque a config ainda não
     * foi salva nessas tabelas.
     *
     * NÃO carrega cfg_rel_joins — decisão do usuário: o Documento não faz join
     * automático entre rel_tabela_base/rel_tabela_detalhe. Cada item de
     * Cabeçalho/Tabela/Textos Livres (e agora também o Título) já vem com o
     * próprio ['tabela'], podendo ser rel_tabela_base OU rel_tabela_detalhe
     * (Regra B — reforçado pelo picker, ver Buscas::busca_campos_tabela_unica());
     * se for preciso combinar mais tabelas além dessas duas, a saída é criar
     * uma VIEW no banco e apontar rel_tabela_base/rel_tabela_detalhe pra ela.
     *
     * @return array|null null quando rel_id não existe ou não é DOCUMENTO
     */
    private function _montarDadosDocumento(int $rel_id, int $id_registro): ?array
    {
        $db = db_connect('default');

        $relatorio = $db->table('cfg_relatorios')->where('rel_id', $rel_id)->get()->getFirstRow();
        if (!$relatorio || ($relatorio->rel_tipo_saida ?? 'TABULAR') !== 'DOCUMENTO') {
            return null;
        }

        $camposCabBD  = $db->table('cfg_rel_camposcab')->where('rel_id', $rel_id)->orderBy('rcc_ordem')->get()->getResult();
        $colunasDocBD = $db->table('cfg_rel_colunas_doc')->where('rel_id', $rel_id)->orderBy('rct_ordem')->get()->getResult();
        $textosBD     = $db->table('cfg_rel_textoslivres')->where('rel_id', $rel_id)->orderBy('rtx_ordem')->get()->getResult();

        $campos_cab = [];
        foreach ($camposCabBD as $c) {
            $campos_cab[] = [
                'tabela'      => $c->rcc_tabela,
                'campo'       => $c->rcc_campo,
                'label'       => $c->rcc_label,
                'tipo_dado'   => $c->rcc_tipo_dado,
                'largura_col' => $c->rcc_largura_col,
            ];
        }

        $colunas_doc = [];
        foreach ($colunasDocBD as $c) {
            $colunas_doc[] = [
                'tabela'    => $c->rct_tabela,
                'campo'     => $c->rct_campo,
                'label'     => $c->rct_label,
                'tipo_dado' => $c->rct_tipo_dado,
                'largura'   => $c->rct_largura,
            ];
        }

        $textos_livres = [];
        foreach ($textosBD as $t) {
            $textos_livres[] = [
                'tabela' => $t->rtx_tabela ?? '',
                'campo'  => $t->rtx_campo ?? '',
                'texto'  => $t->rtx_texto ?? '',
                'label'  => $t->rtx_label ?? '',
            ];
        }

        return [
            // *claude* Título selecionável (byarq/usuário) — vem de
            // rel_tabela_base OU rel_tabela_detalhe, resolvido junto dos
            // outros campos em _buscarDadosDocumento(). rel_nome é o
            // metadado (título da aba/janela do PDF, não o conteúdo impresso
            // — ver PrintDocumentoGenerico()).
            'titulo_tabela'   => $relatorio->rel_titulo_tabela,
            'titulo_campo'    => $relatorio->rel_titulo,
            'rel_nome'        => $relatorio->rel_nome,
            'formato'         => $relatorio->rel_formato,
            // *claude* feedback do usuário (byarq): controla só a fonte do
            // CORPO (tabela repetível) — cabeçalho/textos livres ficam com
            // fonte fixa, ver htmlDocumentoGenerico().
            'tamanho_fonte'   => (int) ($relatorio->rel_tamanho_fonte ?? 10),
            'tabela_base'     => $relatorio->rel_tabela_base,
            'tabela_detalhe'  => $relatorio->rel_tabela_detalhe,
            'campo_vinculo'   => $relatorio->rel_detalhe_campo_vinculo,
            'id_registro'     => $id_registro,
            'campos_cab'      => $campos_cab,
            'colunas_doc'     => $colunas_doc,
            'textos_livres'   => $textos_livres,
        ];
    }

    /**
     * Gera o HTML do Documento genérico — mesma estrutura visual de
     * htmlAnaRequisicao() (cssBase(), div.pdf-page, table.header-box com
     * logo+título), mas 100% dirigida por $config em vez de hardcoded:
     *  - Cabeçalho: loop sobre campos_cab (label:valor), quebra de linha por
     *    largura_col (col-3/col-4/col-6/col-12 — grid Bootstrap).
     *  - Tabela: <thead>/<tbody> vindos de colunas_doc_labels/linhas.
     *  - Textos Livres: uma caixa por item (label + nl2br(valor)).
     *
     * Usado tanto pela impressão real (PrintDocumentoGenerico, $preview=false,
     * $config vindo de _montarDadosDocumento()) quanto pelo preview do admin
     * (CfgRelatorio::previewDocumento(), $preview=true, $config remontado a
     * partir do POST em andamento — ver ali).
     *
     * Sem join automático entre tabela_base/tabela_detalhe (decisão do
     * usuário) — Regra B: cada item de campos_cab/colunas_doc/textos_livres
     * (e agora também o Título, titulo_tabela/titulo_campo) já vem com o
     * próprio ['tabela'], podendo ser tabela_base OU tabela_detalhe
     * (reforçado no picker, ver Buscas::busca_campos_tabela_unica()). Se for
     * preciso combinar mais tabelas além dessas duas, a saída é criar uma
     * VIEW no banco.
     *
     * O Título é impresso em NEGRITO, SEM rótulo (diferente dos campos de
     * Cabeçalho, que mostram "Label: valor") — logo abaixo, uma 2ª linha
     * "Nº: {id_registro}", as duas alinhadas à direita dentro do
     * header-box.
     *
     * @param array $config titulo_tabela/titulo_campo, formato, tamanho_fonte
     *                      (só afeta o corpo/tabela — cabeçalho, título e
     *                      textos livres usam fonte fixa), tabela_base,
     *                      tabela_detalhe, campo_vinculo, id_registro (null =
     *                      sem dados reais, mostra placeholders), campos_cab[],
     *                      colunas_doc[], textos_livres[]
     */
    public function htmlDocumentoGenerico(array $config, bool $preview = false): string
    {
        $formato       = $config['formato'] ?? 'P';
        // *claude* feedback do usuário (byarq): rel_tamanho_fonte controla só a
        // fonte do CORPO (tabela repetível) — cabeçalho (campos_cab) e textos
        // livres ficam com fonte FIXA, igual htmlAnaRequisicao() hoje (nunca
        // usam $fonteCorpo, só as classes/estilos fixos de cssBase()).
        $fonteCorpo    = (int) ($config['tamanho_fonte'] ?? 10);
        $tabelaBase    = $config['tabela_base'] ?? '';
        $camposCabDef  = $config['campos_cab'] ?? [];
        $colunasDocDef = $config['colunas_doc'] ?? [];
        $textosDef     = $config['textos_livres'] ?? [];

        if (empty($tabelaBase) || (empty($camposCabDef) && empty($colunasDocDef) && empty($textosDef))) {
            return '<div style="padding:10px; color:#999; font-style:italic;">Selecione a tabela base e configure ao menos um campo de Cabeçalho, Tabela ou Rodapé para visualizar o preview.</div>';
        }

        $dados  = $this->_buscarDadosDocumento($config, $config['id_registro'] ?? null);
        $titulo = $dados['titulo'] ?? '';

        // $this->pdf só existe quando chamado a partir de PrintDocumentoGenerico
        // (impressão real); no preview do admin (CfgRelatorio::previewDocumento())
        // a classe é instanciada "solta" (igual ao preview do Tabular), então
        // cssBase() é obtido de uma instância descartável só pra gerar o CSS.
        $css  = isset($this->pdf) ? $this->pdf->cssBase() : (new MymPdf2026(false, false, 'A4', $formato === 'L' ? 'L' : 'P'))->cssBase();
        $logo = $preview ? base_url('assets/images/logo-back.png') : $this->logoBack();

        // ── Cabeçalho (label:valor) ──────────────────────────────────────────
        $camposCabHtml = '';
        foreach ($dados['campos_cab'] as $c) {
            $larguraCol = $c['largura_col'] ?: 'col-3';
            $valor      = $c['valor'];
            $tipo       = strtolower($c['tipo_dado'] ?? '');
            if ($valor !== '' && in_array($tipo, ['date', 'datetime', 'timestamp'])) {
                $valor = function_exists('data_br') ? data_br($valor) : $valor;
            }
            // Rótulo opcional (usuário, 2026-09-23) — sem rótulo, só o valor.
            $rotulo = trim((string) ($c['label'] ?? ''));
            $camposCabHtml .= '<div class="' . $this->e($larguraCol) . ' float-start" style="padding:1mm 2mm; min-height:6mm;">'
                . ($rotulo !== '' ? '<span class="label">' . $this->e($rotulo) . ':</span> ' : '')
                . $this->e((string) $valor)
                . '</div>';
        }

        // ── Tabela ────────────────────────────────────────────────────────────
        $theadHtml = '';
        foreach ($dados['colunas_doc_labels'] as $lbl) {
            $theadHtml .= '<th style="text-align:left;">' . $this->e($lbl) . '</th>';
        }

        $numCols   = max(1, count($dados['colunas_doc_labels']));
        $tbodyHtml = '';
        if (empty($dados['linhas'])) {
            $tbodyHtml .= '<tr><td colspan="' . $numCols . '" style="text-align:center; color:#999;">'
                . ($preview ? 'Nenhum registro encontrado (preencha o "ID de teste" na aba Dados Gerais).' : 'Nenhum registro encontrado.')
                . '</td></tr>';
        } else {
            foreach ($dados['linhas'] as $linha) {
                $tbodyHtml .= '<tr>';
                foreach ($linha as $valor) {
                    $tbodyHtml .= '<td>' . $this->e((string) $valor) . '</td>';
                }
                $tbodyHtml .= '</tr>';
            }
        }

        // ── Rodapé (cfg_rel_textoslivres) ───────────────────────────────────
        // Uma caixa por linha: rótulo (opcional) + texto digitado (opcional)
        // + valor do campo vinculado (opcional), nessa ordem.
        $textosHtml = '';
        foreach ($dados['textos_livres'] as $t) {
            $partes = [];
            $rotulo = trim((string) ($t['label'] ?? ''));
            if ($rotulo !== '') {
                $partes[] = '<strong>' . $this->e($rotulo) . ':</strong>';
            }
            if (trim((string) ($t['texto'] ?? '')) !== '') {
                $partes[] = nl2br($this->e((string) $t['texto']));
            }
            if (!empty($t['tem_campo'])) {
                $partes[] = nl2br($this->e((string) $t['valor']));
            }
            if (empty($partes)) {
                continue;
            }
            $textosHtml .= '<div class="box small" style="min-height:10mm; margin-top:2mm;">'
                . implode('<br>', $partes)
                . '</div>';
        }

        return $css . '
        <div class="pdf-page">
            <table class="header-box">
                <tr>
                    <td style="width:20mm; padding-left:1mm; vertical-align:middle;"><img src="' . $this->e($logo) . '" class="logo"></td>
                    <td style="text-align:right; padding-right:2mm; vertical-align:middle; font-size:11pt;">
                        <strong>' . $this->e($titulo) . '</strong><br>
                        Nº: ' . $this->e((string) ($config['id_registro'] ?? '')) . '
                    </td>
                </tr>
            </table>

            <div class="box" style="min-height:12mm;">
                <div class="row">' . $camposCabHtml . '</div>
            </div>

            <table class="table-border" autosize="1" style="font-size:' . $fonteCorpo . 'pt;">
                <thead><tr>' . $theadHtml . '</tr></thead>
                <tbody>' . $tbodyHtml . '</tbody>
            </table>
            ' . $textosHtml . '
        </div>';
    }

    /**
     * Executa as queries do Documento e devolve os valores já formatados,
     * prontos para htmlDocumentoGenerico(). Sem :id_registro (preview do
     * admin antes de preencher o "ID de teste") ou sem tabela_base, devolve
     * placeholders — mesmo critério do preview do Tabular (10 registros
     * fictícios em _buscarDadosRelatorio()).
     *
     * Regra B (byarq/usuário): Cabeçalho, Tabela E Textos Livres podem ter
     * campos de rel_tabela_base OU rel_tabela_detalhe (cada item já vem com
     * seu próprio ['tabela'] — garantido pelo picker do admin, ver Buscas::
     * busca_campos_tabela_unica()). Nenhuma query faz JOIN entre as duas
     * (decisão do usuário — se precisar de mais tabelas, cria uma VIEW no
     * banco). Duas fontes:
     *  - Query 1 (tabela_base, 1 registro, WHERE pk = :id_registro): supre
     *    os itens de campos_cab/textos_livres/colunas_doc cujo tabela ===
     *    tabela_base. Para colunas_doc, esse valor é ÚNICO e se REPETE em
     *    todas as N linhas da grade (o valor "mora" no cabeçalho, não varia
     *    por linha).
     *  - Query 2 (tabela_detalhe, N linhas, WHERE campo_vinculo =
     *    :id_registro): supre os itens de colunas_doc/campos_cab/
     *    textos_livres cujo tabela === tabela_detalhe. Para campos_cab/
     *    textos_livres (que são "1 valor só", não uma grade), pega o valor
     *    da PRIMEIRA linha retornada (mesmo critério que o módulo antigo
     *    AnaRequisicao hardcoded já usava pra campos repetidos por lote,
     *    exibidos uma vez só no cabeçalho — ex.: "Método"). Limitação
     *    conhecida: se colunas_doc não tiver NENHUM item de tabela_detalhe,
     *    a Query 2 não roda (nada define quantas linhas "N" existem) e a
     *    grade fica vazia mesmo que existam itens de tabela_base configurados.
     *
     * @return array{campos_cab: array, colunas_doc_labels: array, linhas: array, textos_livres: array}
     */
    protected function _buscarDadosDocumento(array $config, ?int $id_registro): array
    {
        $tabelaBase    = $config['tabela_base'] ?? '';
        $tabelaDetalhe = $config['tabela_detalhe'] ?? '';
        $campoVinculo  = $config['campo_vinculo'] ?? '';
        $camposCabDef  = $config['campos_cab'] ?? [];
        $colunasDocDef = $config['colunas_doc'] ?? [];
        $textosDef     = $config['textos_livres'] ?? [];

        $colunasDocLabels = array_map(fn($c) => $c['label'] ?? '', $colunasDocDef);

        // *claude* Título selecionável (byarq/usuário) — mesmo mecanismo dos
        // outros campos: pode vir de tabela_base OU tabela_detalhe.
        $tituloTabela = $config['titulo_tabela'] ?? '';
        $tituloCampo  = $config['titulo_campo']  ?? '';

        if (empty($id_registro) || empty($tabelaBase)) {
            return [
                'titulo'     => 'Exemplo',
                'campos_cab' => array_map(fn($c) => [
                    'label'       => $c['label'] ?? '',
                    'valor'       => 'Exemplo',
                    'largura_col' => $c['largura_col'] ?? 'col-3',
                    'tipo_dado'   => '',
                ], $camposCabDef),
                'colunas_doc_labels' => $colunasDocLabels,
                'linhas'             => empty($colunasDocDef) ? [] : [
                    array_fill(0, count($colunasDocDef), 'Exemplo'),
                    array_fill(0, count($colunasDocDef), 'Exemplo'),
                ],
                'textos_livres' => array_map(fn($t) => [
                    'label'     => $t['label'] ?? '',
                    'texto'     => $t['texto'] ?? '',
                    'tem_campo' => !empty($t['campo']),
                    'valor'     => 'Exemplo',
                ], $textosDef),
            ];
        }

        $dicDados       = new \App\Models\Config\ConfigDicDadosModel();
        $resolverSchema = function (string $tabela) use ($dicDados): string {
            static $cache = [];
            if ($tabela === '') {
                return '';
            }
            if (!isset($cache[$tabela])) {
                $info           = $dicDados->getDbGroupAndSchema($tabela);
                $cache[$tabela] = !empty($info['schema']) ? $info['schema'] . '.' . $tabela : $tabela;
            }
            return $cache[$tabela];
        };

        $tabelaBaseSchema    = $resolverSchema($tabelaBase);
        $tabelaDetalheSchema = $resolverSchema($tabelaDetalhe);

        $camposCabValores = [];
        $textosValores    = [];
        $linhas           = [];
        $tituloValor      = '';

        try {
            // ── Query 1: tabela_base, 1 registro (WHERE pk = :id_registro) ───
            $selColsBase = [];
            foreach ($camposCabDef as $i => $c) {
                if (($c['tabela'] ?? '') === $tabelaBase) {
                    $selColsBase[] = "{$tabelaBaseSchema}.{$c['campo']} AS 'cab_{$i}'";
                }
            }
            // Rodapé: só as linhas com campo vinculado (linha só-texto não consulta nada)
            foreach ($textosDef as $i => $t) {
                if (!empty($t['campo']) && ($t['tabela'] ?? '') === $tabelaBase) {
                    $selColsBase[] = "{$tabelaBaseSchema}.{$t['campo']} AS 'txt_{$i}'";
                }
            }
            // colunas_doc de tabela_base: valor único, repetido em todas as
            // linhas da grade — resolvido mais abaixo, junto da Query 2.
            foreach ($colunasDocDef as $i => $c) {
                if (($c['tabela'] ?? '') === $tabelaBase) {
                    $selColsBase[] = "{$tabelaBaseSchema}.{$c['campo']} AS 'colbase_{$i}'";
                }
            }
            // Título, quando vem de tabela_base.
            if ($tituloTabela === $tabelaBase && !empty($tituloCampo)) {
                $selColsBase[] = "{$tabelaBaseSchema}.{$tituloCampo} AS 'titulo_valor'";
            }

            $rowBase = null;
            if (!empty($selColsBase)) {
                $pkInfo = $dicDados->getCampoChave($tabelaBase);
                $pk     = $pkInfo[0]['COLUMN_NAME'] ?? null;

                if ($pk) {
                    $dbGrSche = $dicDados->getDbGroupAndSchema($tabelaBase);
                    $dbConn   = db_connect($dbGrSche['dbGroup']);

                    $sql = 'SELECT ' . implode(', ', $selColsBase) . ' FROM ' . $tabelaBaseSchema
                        . ' WHERE ' . $tabelaBaseSchema . '.' . $pk . ' = ?';

                    $rowBase = $dbConn->query($sql, [$id_registro])->getRowArray();
                }
            }

            if ($rowBase) {
                foreach ($camposCabDef as $i => $c) {
                    if (($c['tabela'] ?? '') === $tabelaBase) {
                        $camposCabValores[$i] = $rowBase['cab_' . $i] ?? '';
                    }
                }
                foreach ($textosDef as $i => $t) {
                    if (($t['tabela'] ?? '') === $tabelaBase) {
                        $textosValores[$i] = $rowBase['txt_' . $i] ?? '';
                    }
                }
                if ($tituloTabela === $tabelaBase) {
                    $tituloValor = $rowBase['titulo_valor'] ?? '';
                }
            }

            // ── Query 2: tabela_detalhe, N linhas (WHERE campo_vinculo = :id_registro) ──
            $selColsDetalhe = [];
            foreach ($colunasDocDef as $i => $c) {
                if (($c['tabela'] ?? '') === $tabelaDetalhe) {
                    $selColsDetalhe[] = "{$tabelaDetalheSchema}.{$c['campo']} AS 'col_{$i}'";
                }
            }
            foreach ($camposCabDef as $i => $c) {
                if (($c['tabela'] ?? '') === $tabelaDetalhe) {
                    $selColsDetalhe[] = "{$tabelaDetalheSchema}.{$c['campo']} AS 'cabdet_{$i}'";
                }
            }
            foreach ($textosDef as $i => $t) {
                if (!empty($t['campo']) && ($t['tabela'] ?? '') === $tabelaDetalhe) {
                    $selColsDetalhe[] = "{$tabelaDetalheSchema}.{$t['campo']} AS 'txtdet_{$i}'";
                }
            }
            // Título, quando vem de tabela_detalhe.
            if ($tituloTabela === $tabelaDetalhe && !empty($tituloCampo)) {
                $selColsDetalhe[] = "{$tabelaDetalheSchema}.{$tituloCampo} AS 'titulodet_valor'";
            }

            $resultadoDetalhe = [];
            if (!empty($selColsDetalhe) && !empty($tabelaDetalhe) && !empty($campoVinculo)) {
                $dbGrSche = $dicDados->getDbGroupAndSchema($tabelaDetalhe);
                $dbConn   = db_connect($dbGrSche['dbGroup']);

                $sql = 'SELECT ' . implode(', ', $selColsDetalhe) . ' FROM ' . $tabelaDetalheSchema
                    . ' WHERE ' . $tabelaDetalheSchema . '.' . $campoVinculo . ' = ?';

                $resultadoDetalhe = $dbConn->query($sql, [$id_registro])->getResultArray();
            }

            // campos_cab/textos_livres de tabela_detalhe: "1 valor só" — pega
            // a PRIMEIRA linha da grade (mesmo critério do AnaRequisicao
            // hardcoded antigo pra campos repetidos por lote).
            if (!empty($resultadoDetalhe)) {
                $primeiraLinha = $resultadoDetalhe[0];

                foreach ($camposCabDef as $i => $c) {
                    if (($c['tabela'] ?? '') === $tabelaDetalhe) {
                        $camposCabValores[$i] = $primeiraLinha['cabdet_' . $i] ?? '';
                    }
                }
                foreach ($textosDef as $i => $t) {
                    if (($t['tabela'] ?? '') === $tabelaDetalhe) {
                        $textosValores[$i] = $primeiraLinha['txtdet_' . $i] ?? '';
                    }
                }
                if ($tituloTabela === $tabelaDetalhe) {
                    $tituloValor = $primeiraLinha['titulodet_valor'] ?? '';
                }
            }

            // Monta as N linhas da grade "Tabela": colunas de tabela_detalhe
            // vêm de cada linha normalmente; colunas de tabela_base repetem o
            // mesmo valor único ($rowBase) em TODAS as linhas.
            foreach ($resultadoDetalhe as $rowDetalhe) {
                $linha = [];
                foreach ($colunasDocDef as $i => $c) {
                    $tabelaColuna = $c['tabela'] ?? '';

                    if ($tabelaColuna === $tabelaDetalhe) {
                        $valor = $rowDetalhe['col_' . $i] ?? '';
                    } elseif ($tabelaColuna === $tabelaBase) {
                        $valor = $rowBase['colbase_' . $i] ?? '';
                    } else {
                        $valor = '';
                    }

                    $tipo = strtolower($c['tipo_dado'] ?? '');
                    if ($valor !== '' && in_array($tipo, ['date', 'datetime', 'timestamp'])) {
                        $valor = function_exists('data_br') ? data_br($valor) : $valor;
                    }
                    $linha[] = $valor;
                }
                $linhas[] = $linha;
            }
        } catch (\Throwable $e) {
            // Config incompleta/inconsistente (ex.: campo removido da tabela
            // depois de configurado) — não quebra a tela/impressão, só não
            // preenche dados. Mesmo critério do Tabular em _buscarDadosRelatorio().
        }

        $camposCabIdx = array_keys($camposCabDef);
        $textosIdx    = array_keys($textosDef);

        return [
            'titulo'     => $tituloValor,
            'campos_cab' => array_map(fn($c, $i) => [
                'label'       => $c['label'] ?? '',
                'valor'       => $camposCabValores[$i] ?? '',
                'largura_col' => $c['largura_col'] ?? 'col-3',
                'tipo_dado'   => $c['tipo_dado'] ?? '',
            ], $camposCabDef, $camposCabIdx),
            'colunas_doc_labels' => $colunasDocLabels,
            'linhas'             => $linhas,
            'textos_livres'      => array_map(fn($t, $i) => [
                'label'     => $t['label'] ?? '',
                'texto'     => $t['texto'] ?? '',
                'tem_campo' => !empty($t['campo']),
                'valor'     => $textosValores[$i] ?? '',
            ], $textosDef, $textosIdx),
        ];
    }

    protected function e(?string $value): string
    {
        $value = (string) $value;
        $enc = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc !== 'UTF-8') {
            $value = mb_convert_encoding($value, 'UTF-8', $enc ?: 'ISO-8859-1');
        }

        return htmlspecialchars(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
