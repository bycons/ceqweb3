<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CommonModel;
use App\Models\Config\ConfigEtiquetaCampoModel;
use App\Models\Config\ConfigEtiquetaModel;
use App\Models\Config\ConfigImpressoraModel;
use App\Models\Config\ConfigTelaModel;

class CriaEtiquetaZPL extends BaseController
{
    public $data;
    public $etiqueta;
    public $etiquetaCampo;
    public $common;
    public $tela;

    // Layout vindo do BD (mm)
    private float $largura    = 70;  // mm (largura de UMA etiqueta)
    private float $altura     = 35;  // mm (altura de UMA etiqueta)
    private float $esquerda   = 10;  // mm (margem ESQ da LINHA/folha)
    private float $direita    = 10;  // mm (margem DIR da LINHA/folha)
    private float $topo       = 10;  // mm (margem TOPO da LINHA/folha)
    private float $rodape     = 10;  // mm (margem RODAPÉ da LINHA/folha)
    private float $horizontal = 5;   // mm (gap entre etiquetas na MESMA LINHA)
    private float $vertical   = 5;   // mm (gap entre LINHAS – aqui não usamos no ZPL; a impressora avança por gap)
    private int   $colunas    = 1;   // quantas etiquetas por LINHA (ex: 3)
    private int   $linhas     = 1;   // ignorado no rolo (a cada ^XZ avança 1 “linha”)

    // Impressora
    private string $ipZebra = '192.168.0.174';
    private int    $portaZebra = 9100;

    // DPI (ZD230 = 203)
    private int $dpi = 203;
    private float $ppmm;

    public function __construct()
    {
        $this->data          = session()->getFlashdata('dados_classe');
        $this->etiqueta      = new ConfigEtiquetaModel();
        $this->etiquetaCampo = new ConfigEtiquetaCampoModel();
        $this->common        = new CommonModel();
        $this->tela          = new ConfigTelaModel();

        $this->ppmm = $this->dpi / 25.4; // ≈ 7.992
    }

    /* ========================= Helpers ========================= */

    private function mm2dot(float $mm): int
    {
        return (int) round($mm * $this->ppmm);
    }

    private function escZpl(string $s): string
    {
        return str_replace(['^', '\\'], ['\^', '\\\\'], $s);
    }

    private function alturaLinhaFromTamanho(int $tamPt): int
    {
        // mesma “regra” que você usava: (~ (tamanho/8)*3 mm) -> dots
        $mm = $tamPt * (25.4 / 72);
        return $this->mm2dot($mm);
    }

    /* ================== Montagem do ZPL nativo ================== */
    /**
     * Gera UM ÚNICO formato (^XA ... ^XZ) contendo TODAS as etiquetas do array $dados,
     * distribuídas em fileiras com até $this->colunas por fileira.
     */
    private function buildLinha(array $dados, array $camp, $modelo = false): string
    {
        if (empty($dados)) return '';

        // tamanhos em dots
        $etqW = $this->mm2dot($this->largura);    // largura de UMA etiqueta
        $etqH = $this->mm2dot($this->altura);     // altura de UMA etiqueta
        $ml   = $this->mm2dot($this->esquerda);   // margem esquerda
        $mr   = $this->mm2dot($this->direita);    // margem direita
        $mt   = $this->mm2dot($this->topo);       // margem topo (apenas 1x no total)
        $mb   = $this->mm2dot($this->rodape);     // margem rodapé (apenas 1x no total)
        $gh   = $this->mm2dot($this->horizontal); // “gutter” horizontal entre colunas
        $gv   = isset($this->vertical) ? $this->mm2dot($this->vertical) : 0; // gutter vertical opcional

        $cols = max(1, (int)$this->colunas);

        // Pré-calcula X de cada coluna
        $xCols = [];
        for ($c = 0; $c < $cols; $c++) {
            $xCols[$c] = $ml + $c * ($etqW + $gh);
        }

        // Agrupa em fileiras
        $fileiras = array_chunk($dados, $cols);
        $totalRows = count($fileiras);

        // Largura total (uma fileira)
        $pw = $ml + ($cols * $etqW) + (($cols - 1) * $gh) + $mr;
        $PW_MAX = 3045; // limite do Labelary (15" em 203 DPI)
        $pw = min($pw, $PW_MAX);
        // Altura total (todas as fileiras)
        // mt + (etqH+gv)*totalRows - gv (pois não tem gutter após a última) + mb
        $ll = $mt + ($totalRows * $etqH) + (max(0, $totalRows - 1) * $gv) + $mb;

        // Abre um ÚNICO formato
        $z = "^XA^CI28^PW{$pw}^LL{$ll}^LH0,0";

        // (Opcional) charset e densidade
        // $z .= "^CI28"; // se sua Zebra suportar UTF-8
        // $z .= "^PR6^MD10";

        foreach ($fileiras as $r => $grupo) {
            $baseYRow = $mt + $r * ($etqH + $gv);

            foreach ($grupo as $i => $registro) {
                // segurança
                if ($i >= $cols) break;

                $baseX = $xCols[$i];

                // (Opcional) borda de cada etiqueta
                // $z .= "^FO{$baseX},{$baseYRow}^GB{$etqW},{$etqH},1^FS";
                // imprime o conteúdo de UMA etiqueta naquela posição
                $z .= $this->buildConteudoEtiquetaEm($baseX, $baseYRow, $registro, $camp, $etqW, $etqH, $modelo);
            }
        }

        // Fecha o formato único
        $z .= "^XZ";
        return $z;
    }

    private function buildConteudoEtiquetaEm(int $baseX, int $baseY, $registro, array $camp, int $etqW, int $etqH, bool $modelo): string
    {
        $z = '';
        $registro = (array) $registro;
        $yCursor  = 0;
        $ocupouPct = 0; // largura ocupada na linha (em % da etiqueta)
        // inicialize fora do loop
        $linhaPctOcupado = 0;               // 0..100
        $linhaAlturaMax  = 0;               // em dots
        $EPS             = 0.0001;          // evita quebra por arredondamento

        foreach ($camp as $prop) {
            $caracteres = (int) ($prop['etc_caracteres'] ?? 30);
            $colPct     = max(1, (int) ($prop['etc_colunas'] ?? 100));
            $alinh      = $prop['etc_alinhamento'] ?? 'L'; // L/C/R/J
            $negrito    = ($prop['etc_negrito'] ?? 'N') === 'S';
            $fonte      = $prop['etc_fonte'] ?? '0';       // mantido p/ compat., usamos ^A@ abaixo
            $tamPt      = (int) ($prop['etc_tamanho'] ?? 24);
            $linhas     = (int) ($prop['etc_linhas'] ?? 1);
            $isBar      = ($prop['etc_codbar'] ?? 'N') === 'S';
            $tipoCampo  = $prop['etc_campo'] ?? '';        // '0','1','2','3' ou nome do campo
            $rotulo     = $prop['etc_rotulo'] ?? '';

            $partes = explode(' ', $fonte, 2);

            // Colocar tudo em maiúsculo
            $font = strtoupper($partes[0]);
            if ($font == 'TIMES') {
                $font = 'TIMESNR';
            }
            $font = $font . '.TTF';

            $largDisp = (int) floor($etqW * ($colPct / 100));

            // Se estourar 100% na linha, quebra “visual” para a próxima linha de layout
            if ((($ocupouPct + $colPct) > 100) && $ocupouPct > 0) {
                $yCursor += max($this->alturaLinhaFromTamanho($tamPt), $this->mm2dot(2)); // avança uma linha
                $ocupouPct = 0;
            }

            $xBase = $baseX + (int) floor(($ocupouPct / 100) * $etqW);
            if ($modelo) { // se for modelo desenha a borda
                $z .= "^FO{$baseX},{$baseY}^GB" . ($etqW - 1) . "," . ($etqH - 1) . ",2^FS";
            }

            // Linha horizontal?
            if ($tipoCampo === '1') {
                $z .= "^FO{$xBase}," . ($baseY + $yCursor) . "^GB{$largDisp},1,1^FS";
                $yCursor  += max(1, $this->mm2dot(0.6));
                $ocupouPct = 0;
                continue;
            }

            // Resolve o texto
            if ($tipoCampo === '99') { // texto livre
                $linhas = 1;
                $texto  = (string) $rotulo;
            } elseif ($tipoCampo === '2') { // data atual
                $linhas = 1;
                $texto  = date('d/m/Y');
            } elseif ($tipoCampo === '3') { // data e hora atual
                $linhas = 1;
                $texto  = date('d/m/Y H:i:s');
            } else {
                if ($rotulo === 'Sem Rótulo' || $rotulo === '.') $rotulo = '';
                $val = trim((string) ($registro[$tipoCampo] ?? ''));
                if (isValidDate($val)) {
                    $val = data_br($val);
                }
                if ($caracteres > 0 && $linhas == 1) $val = mb_substr($val, 0, $caracteres);
                $texto = trim(($rotulo ? "$rotulo " : '') . $val);
            }

            $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

            // ---------- CÓDIGO DE BARRAS ----------
            if ($isBar) {
                $alturaMM = $tamPt * 25.4 / 72;
                $barH     = max(40, $this->mm2dot($alturaMM));

                $dados = $texto !== '' ? $texto : str_repeat('0', max(8, $caracteres));

                $areaW = (int) $etqW;

                // 90% da largura
                $barTargetW = (int) floor($areaW * 0.9);

                // Quiet zone (~2mm)
                $quiet = max(16, (int) $this->mm2dot(2));

                $usableW = max(10, $barTargetW - (2 * $quiet));

                $lenDados = max(strlen($dados), max(8, (int)$caracteres));
                $modules  = 35 + (11 * $lenDados);

                // 🔥 cálculo base
                $modW = (int) floor($usableW / $modules);

                // limites para 203 DPI
                $modW = min(4, $modW); // só limita máximo primeiro

                // ⚠️ garante que cabe
                while ($modW > 1 && ($modules * $modW) > $usableW) {
                    $modW--;
                }

                // fallback mínimo
                if ($modW < 1) {
                    $modW = 1;
                }

                $realBarW = $modules * $modW;

                // centralização
                $xPos = $xBase + (int) round(($areaW - $realBarW) / 2);

                $yPos = $baseY + $yCursor;

                $z .= "^FO{$xPos},{$yPos}";
                $z .= "^BY{$modW},2.5";
                $z .= "^BCN,{$barH},N,N,N";
                $z .= "^FD" . $this->escZpl($dados) . "^FS";

                $yCursor += ($barH + $this->mm2dot(1));
                $ocupouPct = 0;
                continue;
            }
            // ---------- TEXTO ----------
            $hDots = $this->alturaLinhaFromTamanho($tamPt);
            $wDots = (int) round($hDots * 0.35);
            $wDots = 0;

            // Espaço vertical entre linhas (mínimo 2mm)
            $espacoExtra = $this->mm2dot($hDots * (10 / 100));
            $lineStep    = $hDots + $espacoExtra;

            $yBaseLocal = $baseY + $yCursor;

            if ($linhas > 1 && $caracteres > 0) {
                // quebra em blocos fixos e força exatamente $linhas linhas (preenchendo com vazio)
                $charsPerLine = max(1, (int)$caracteres);
                $maxLines     = max(1, (int)$linhas);

                // Quebra (preserva UTF-8 se tiver mbstring)
                $partes = str_split($this->escZpl($texto), $charsPerLine);

                // Garante que tenha exatamente $maxLines elementos
                while (count($partes) < $maxLines) {
                    $partes[] = ''; // linha vazia
                }
                // debug($partes);

                // Altura de linha: altura da fonte + opcional leading
                $leading  = $this->mm2dot($hDots * (2 / 100)); // ex.: $this->mm2dot(0.6) se quiser espaço extra
                $lineStep = $hDots + $leading;

                $yBase = $baseY + $yCursor;

                for ($i = 0; $i < $maxLines; $i++) {
                    $yLine = $yBase + ($i * $lineStep);
                    $z .= "^FO{$xBase},{$yLine}^A@N,{$hDots},{$wDots},E:{$font}";
                    $z .= "^FB{$largDisp},1,0,{$alinh},0";
                    $part = $partes[$i];
                    $z .= "^FD{$part}^FS";
                    if ($negrito) {
                        $z .= "^FO{$xBase},{$yLine}^A@N,{$hDots},{$wDots},E:{$font}";
                        $z .= "^FB{$largDisp},1,0," . ($alinh ?: 'L') . ",0";
                        $z .= "^FD{$part}^FS";
                    }
                }
                $yCursor  += ($lineStep * ($maxLines - 1));
            } else {
                $itemPct   = (float)$colPct;        // % do container do item
                $itemW     = (int) round($etqW * ($itemPct / 100));   // largura (dots)
                $itemH     = (int) $hDots;          // altura do item (ou calcule conforme o caso)

                // 1) Quebra de linha *antes de imprimir* SE não couber
                if (($linhaPctOcupado + $itemPct) > (100 + $EPS)) {
                    // avança para próxima linha usando a MAIOR altura coletada
                    // $yCursor += ($linhaAlturaMax ?: ($hDots + $this->mm2dot(1.2)));
                    $linhaPctOcupado = 0;
                    $linhaAlturaMax  = 0;
                }

                // 2) Posição X baseada no % já ocupado
                // $xPos = $xBase + (int) round(($etqW * $linhaPctOcupado) / 100);
                $xPos = $xBase;

                // 3) Imprime o item (ex.: texto com ^A@ + ^FB em 1 linha)
                $z .= "^FO{$xPos}," . ($baseY + $yCursor) . "^A@N,{$hDots},{$wDots},E:{$font}";
                // debug($alinh);
                $z .= "^FB{$itemW},2,0,{$alinh},0";
                // debug("^FB{$itemW},2,0,{$alinh},0");
                $z .= "^FD" . $this->escZpl($texto) . "^FS";

                // negrito fake opcional
                if (!empty($negrito)) {
                    $z .= "^FO" . ($xPos + 1) . "," . ($baseY + $yCursor) . "^A@N,{$hDots},{$wDots},E:{$font}";
                    $z .= "^FB{$itemW},2,0," . ($alinh ?: 'L') . ",0";
                    $z .= "^FD" . $this->escZpl($texto) . "^FS";
                }

                // 4) Atualiza ocupação e altura da linha
                $linhaPctOcupado += $itemPct;
                if ($linhaPctOcupado > 100) {
                    $linhaPctOcupado = 100;
                } // clamp por segurança
                $linhaAlturaMax = max($linhaAlturaMax, $itemH);

                // 5) NÃO avance $yCursor aqui.
                //    Se a linha “fechou” (==100), você só avança na chegada do próximo item,
                //    pelo passo (1) acima. Isso garante que 30% + 40% fiquem na MESMA linha.
            }
            // Avança para próxima “linha de layout” dentro da etiqueta
            // $yCursor   += max($hDots, $this->mm2dot(2.5));
            $ocupouPct += $colPct;
            if ($ocupouPct >= 100) {
                $ocupouPct = 0;
                $yCursor   += $hDots + $this->mm2dot(0.3);
            }
        }
        // debug($z);
        return $z;
    }


    /**
     * Quebra $dados em LINHAS de até $this->colunas, e junta tudo.
     */
    private function buildLoteEmLinhas(array $dados, array $camp): string
    {
        $out = '';
        $linha = [];
        foreach ($dados as $registro) {
            $linha[] = $registro;
            if (count($linha) === $this->colunas) {
                $out .= $this->buildLinha($linha, $camp);
                $linha = [];
            }
        }
        if ($linha) {
            $out .= $this->buildLinha($linha, $camp);
        }
        return $out;
    }

    /* ===================== Ações públicas ===================== */

    /**
     * PREVIEW: renderiza UMA LINHA (até let_colunas itens) em PNG via Labelary.
     * Mantém rota/assinatura que você já usa.
     */
    // public function emiteEtiqueta($etq_id, $chave = false, $qtia = 1)
    // {
    //     $etiq = $this->etiqueta->getEtiqueta($etq_id);
    //     $camp = $this->etiquetaCampo->getEtiquetaCampo($etq_id);
    //     if (!$etiq) return;

    //     $etq = $etiq[0];
    //     $this->largura    = (float) $etq->let_largura;
    //     $this->altura     = (float) $etq->let_altura;
    //     $this->esquerda   = (float) $etq->let_marg_esquerda;
    //     $this->direita    = (float) $etq->let_marg_direita;
    //     $this->topo       = (float) $etq->let_marg_superior;
    //     $this->rodape     = (float) $etq->let_marg_inferior;
    //     $this->horizontal = (float) $etq->let_distancia_h;
    //     $this->vertical   = (float) $etq->let_distancia_v;
    //     $this->colunas    = (int)   $etq->let_colunas;
    //     $this->linhas     = (int)   $etq->let_linhas;

    //     // Busca 1 linha de dados (até N colunas) — mesma fonte que você já usa
    //     $modelo = false;
    //     if ($chave === false || $chave == 'false') {
    //         $fields = array_column($camp, 'etc_campo');
    //         $telid  = $etq->tel_id;
    //         $telas  = (array) $this->tela->getTelaId($telid)[0] ?? null;

    //         if ($telas && !empty($telas['tel_model'])) {
    //             $model = $telas['tel_model'];
    //             $model_atual = model("App\\Models\\" . substr($model, 0, 6) . "\\" . $model);
    //             $view   = $model_atual->view;
    //             if (isset($model_atual->viewoutra)) {
    //                 $view   = $model_atual->viewoutra;
    //             }
    //             $dados = $this->common->getListaTabela($model_atual->DBGroup, $view, $fields, false, 10);
    //         } else {
    //             $dados = array_fill(0, $this->colunas, []);
    //         }
    //         $modelo = true;
    //     } else {
    //         $dados = cache()->get($chave) ?: [];
    //         // debug($all, true);
    //         // $dados = array_slice($all, 0, max(1, $this->colunas));
    //         // debug($dados, true);
    //         if (!$dados) $dados = [[]];
    //     }

    //     // ZPL de UMA LINHA (até N colunas)
    //     $zplLinha = $this->buildLinha($dados, $camp, $modelo);
    //     // debug($zplLinha, true);
    //     // echo $zplLinha;
    //     // exit;
    //     // Tamanho da LINHA (em polegadas) pro Labelary
    //     // garante que todos os formatos terminam em ^XZ
    //     $cols = max(1, (int)$this->colunas);
    //     $totalRows = (int)ceil(count($dados) / $cols);

    //     $etqW = $this->mm2dot($this->largura);
    //     $ml   = $this->mm2dot($this->esquerda);
    //     $mr   = $this->mm2dot($this->direita);
    //     $gh   = $this->mm2dot($this->horizontal);
    //     $pwDots = $ml + ($cols * $etqW) + (($cols - 1) * $gh) + $mr;
    //     $PW_MAX = 3045; // limite do Labelary (15" em 203 DPI)
    //     $pwDots = min($pwDots, $PW_MAX);

    //     $etqH = $this->mm2dot($this->altura);
    //     $mt   = $this->mm2dot($this->topo);
    //     $mb   = $this->mm2dot($this->rodape);
    //     $gv   = isset($this->vertical) ? $this->mm2dot($this->vertical) : 0;

    //     $llDots = $mt + ($totalRows * $etqH) + (max(0, $totalRows - 1) * $gv) + $mb;
    //     $LL_MAX = 3045; // 🔥 CORREÇÃO
    //     $llDots = min($llDots, $LL_MAX);

    //     $larg_in = round($pwDots / $this->dpi, 3);
    //     $altu_in = round($llDots / $this->dpi, 3);

    //     // se sua impressora for 203dpi, 8dpmm; 300dpi, 12dpmm
    //     $dpmm = max(1, (int)round($this->dpi / 25.4));

    //     $url = "http://api.labelary.com/v1/printers/{$dpmm}dpmm/labels/{$larg_in}x{$altu_in}/0/";


    //     $ch  = curl_init($url);
    //     curl_setopt_array($ch, [
    //         CURLOPT_POST           => true,
    //         CURLOPT_POSTFIELDS     => $zplLinha, // stream completo com todas as ^XA…^XZ
    //         CURLOPT_HTTPHEADER     => ['Accept: image/png'],
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_TIMEOUT        => 30,
    //     ]);

    //     $png  = curl_exec($ch);
    //     $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    //     $err  = curl_error($ch);
    //     curl_close($ch);

    //     if ($code !== 200 || !$png) {
    //         // se uma página falhar, retorna o erro dessa página
    //         return $this->response->setJSON([
    //             'erro'   => $url . '<br>Falha no preview (Labelary) ' . $code . '<br>' . $err,
    //             'http'   => $code,
    //             'pagina' => 0,
    //             'detalhe' => $err ?: '',
    //         ]);
    //     }

    //     $imagem = base64_encode($png);

    //     // retorna TODAS as imagens
    //     return $this->response->setJSON(['imagem' => $imagem]);
    // }

    public function emiteEtiqueta(int $etq_id, string|bool $chave = false, int $qtia = 1)
    {
        $etiq = $this->etiqueta->getEtiqueta($etq_id);
        $camp = $this->etiquetaCampo->getEtiquetaCampo($etq_id);

        if (!$etiq) {
            return;
        }

        $etq = $etiq[0];

        // Configuração
        $this->largura    = (float) $etq->let_largura;
        $this->altura     = (float) $etq->let_altura;
        $this->esquerda   = (float) $etq->let_marg_esquerda;
        $this->direita    = (float) $etq->let_marg_direita;
        $this->topo       = (float) $etq->let_marg_superior;
        $this->rodape     = (float) $etq->let_marg_inferior;
        $this->horizontal = (float) $etq->let_distancia_h;
        $this->vertical   = (float) $etq->let_distancia_v;
        $this->colunas    = max(1, (int) $etq->let_colunas);

        $modelo = false;

        // 🔥 1 etiqueta base
        if ($chave === false || $chave === 'false') {

            $fields = array_column($camp, 'etc_campo');
            $telid  = $etq->tel_id;
            $telas  = (array) ($this->tela->getTelaId($telid)[0] ?? null);

            if ($telas && !empty($telas['tel_model'])) {

                $model = $telas['tel_model'];
                $model_atual = model("App\\Models\\" . substr($model, 0, 6) . "\\" . $model);

                $view = $model_atual->view ?? null;
                if (isset($model_atual->viewoutra)) {
                    $view = $model_atual->viewoutra;
                }

                $dadosBase = $this->common->getListaTabela(
                    $model_atual->DBGroup,
                    $view,
                    $fields,
                    false,
                    3
                );
            } else {
                $dadosBase = [[]];
            }

            $modelo = true;
        } else {
            $redis = \Config\Services::redis();

            $dadosJson = $redis->get($chave);

            $dadosBase = $dadosJson
                ? json_decode($dadosJson, true)
                : [[]];
            // $dadosBase = cache()->get($chave) ?: [[]];
        }

        // garante 1 item
        $item = is_array($dadosBase[0] ?? null) || is_object($dadosBase[0] ?? null)
            ? $dadosBase[0]
            : $dadosBase;

        // =========================================================
        // 🔥 PREVIEW (até 5 linhas)
        // =========================================================
        $qtia = max(1, (int) $qtia);

        $maxLinhasPreview = 2;
        $maxItensPreview  = $this->colunas * $maxLinhasPreview;

        $previewQtd = min($qtia, $maxItensPreview);

        // 🔥 monta várias etiquetas dentro da mesma label
        $dadosPreview = array_fill(0, $previewQtd, $item);

        $zpl = $this->buildLinha($dadosPreview, $camp, $modelo);

        // =========================================================
        // 🔥 IMPRESSÃO REAL (usa PQ)
        // =========================================================
        $zplPrint = preg_replace(
            '/\^XZ\s*$/',
            "^PQ{$qtia}\n^XZ",
            rtrim($this->buildLinha([$item], $camp, $modelo))
        );

        // =========================================================
        // 📏 DIMENSÕES BASEADAS NO PREVIEW
        // =========================================================
        $totalRows = (int) ceil($previewQtd / $this->colunas);

        $etqW = $this->mm2dot($this->largura);
        $ml   = $this->mm2dot($this->esquerda);
        $mr   = $this->mm2dot($this->direita);
        $gh   = $this->mm2dot($this->horizontal);

        $pwDots = $ml + ($this->colunas * $etqW) + (($this->colunas - 1) * $gh) + $mr;
        $pwDots = min($pwDots, 3045);

        $etqH = $this->mm2dot($this->altura);
        $mt   = $this->mm2dot($this->topo);
        $mb   = $this->mm2dot($this->rodape);
        $gv   = $this->mm2dot($this->vertical);

        $llDots = $mt + ($totalRows * $etqH) + (max(0, $totalRows - 1) * $gv) + $mb;
        $llDots = min($llDots, 3045);

        $larg_in = round($pwDots / $this->dpi, 3);
        $altu_in = round($llDots / $this->dpi, 3);

        $dpmm = max(1, (int) round($this->dpi / 25.4));

        $url = "http://api.labelary.com/v1/printers/{$dpmm}dpmm/labels/{$larg_in}x{$altu_in}/0/";

        // =========================================================
        // 🔄 REQUEST LABELARY
        // =========================================================
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $zpl,
            CURLOPT_HTTPHEADER     => ['Accept: image/png'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $png  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);

        curl_close($ch);

        if ($code !== 200 || !$png) {
            return $this->response->setJSON([
                'erro' => $url . '<br>Falha no preview (Labelary) ' . $code . '<br>' . $err,
            ]);
        }

        return $this->response->setJSON([
            'imagem' => base64_encode($png),
            'preview' => $previewQtd,
            'total'   => $qtia
        ]);
    }
    /**
     * IMPRESSÃO: quebra os dados em linhas de até let_colunas e envia cada LINHA (^XA…^XZ) pra Zebra.
     */
    public function imprimeEtiqueta($etq_id, $chave = false, $qtia = 1, $imp_id = false)
    {
        if (!$imp_id) {
            return $this->response->setJSON(['erro' => 'Impressora não definida.']);
        }
        $etiq = $this->etiqueta->getEtiqueta($etq_id);
        if (!$etiq) {
            return $this->response->setJSON(['erro' => 'Layout não encontrado.']);
        }
        $camp = $this->etiquetaCampo->getEtiquetaCampo($etq_id);
        $printers = new ConfigImpressoraModel();
        // debug('Imp ' . $imp_id);
        $impr = $printers->getImpressora($imp_id);
        // debug($impr, true);
        $this->ipZebra = trim($impr->imp_ip);
        $this->portaZebra = intval($impr->imp_porta);
        $nomeimpressora = $impr->imp_nome;

        $etq = $etiq[0];
        $this->largura    = (float) $etq->let_largura;
        $this->altura     = (float) $etq->let_altura;
        $this->esquerda   = (float) $etq->let_marg_esquerda;
        $this->direita    = (float) $etq->let_marg_direita;
        $this->topo       = (float) $etq->let_marg_superior;
        $this->rodape     = (float) $etq->let_marg_inferior;
        $this->horizontal = (float) $etq->let_distancia_h;
        $this->vertical   = (float) $etq->let_distancia_v;
        $this->colunas    = (int)   $etq->let_colunas;
        $this->linhas     = (int)   $etq->let_linhas;

        // Busca o LOTE completo (mesma fonte de dados)
        if ($chave === false || $chave == 'false') {
            $fields = array_column($camp, 'etc_campo');
            $telid  = $etq->tel_id;
            $telas  = $this->tela->getTelaId($telid)[0] ?? null;

            if ($telas && !empty($telas->tel_model)) {
                $model = $telas->tel_model;
                $model_atual = model("App\\Models\\" . substr($model, 0, 6) . "\\" . $model);
                $view   = $model_atual->view;
                if (isset($model_atual->viewoutra)) {
                    $view   = $model_atual->viewoutra;
                }
                $dados = $this->common->getListaTabela($model_atual->DBGroup, $view, $fields, false, 10);
            } else {
                $dados = array_fill(0, $this->colunas, []);
            }
            $modelo = true;
        } else {
            $redis = \Config\Services::redis();

            $dadosJson = $redis->get($chave);

            $all = $dadosJson
                ? json_decode($dadosJson, true)
                : [[]];
            // $all = cache()->get($chave) ?: [];
            // debug($all);
            $item = is_array($all[0] ?? null) || is_object($all[0] ?? null)
                ? $all[0]
                : $all;

            // 🔥 multiplica pela quantidade
            $dados = array_fill(0, $qtia, $item);

            // $dados = array_slice($all, 0, max(1, $this->colunas));
            // debug($dados, true);
            if (!$dados) $dados = [[]];
        }
        // debug($dados, true);

        // Monta ZPL (em LINHAS)
        $zpl = $this->buildLoteEmLinhas($dados, $camp);
        // debug($zpl, true);
        // Envia para a impressora
        $sock = @fsockopen($this->ipZebra, $this->portaZebra, $eno, $estr, 5);
        if (!$sock) {
            return $this->response->setJSON(['erro' => "Conexão com a impressora $nomeimpressora falhou: $estr ($eno)"]);
        }
        stream_set_blocking($sock, true);
        fwrite($sock, $zpl); // com vários ^XA...^XZ
        fclose($sock);

        return $this->response->setJSON(['status' => "Etiquetas enviadas para Impressora $nomeimpressora"]);
    }


    public function previewEtiquetaViaAjax()
    {
        $json = $this->request->getJSON(true) ?? json_decode($this->request->getBody(), true);
        if (!$json || !isset($json['let_id'], $json['tel_id'], $json['campos'])) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Dados inválidos.']);
        }

        $let_id = $json['let_id'];
        $tel_id = $json['tel_id'];
        $camp   = $json['campos'];

        $etiq = $this->etiqueta->getEtiquetaLayout($let_id);

        if (!$etiq) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Layout não encontrado.']);
        }

        $etq = (array) $etiq[0];
        $this->largura    = (float) $etq['let_largura'];
        $this->altura     = (float) $etq['let_altura'];
        $this->esquerda   = (float) $etq['let_marg_esquerda'];
        $this->direita    = (float) $etq['let_marg_direita'];
        $this->topo       = (float) $etq['let_marg_superior'];
        $this->rodape     = (float) $etq['let_marg_inferior'];
        $this->horizontal = (float) $etq['let_distancia_h'];
        $this->vertical   = (float) $etq['let_distancia_v'];
        $this->colunas    = (int)   $etq['let_colunas'];
        $this->linhas     = (int)   $etq['let_linhas'];

        // pega até N=colunas registros pra compor UMA LINHA de preview
        $dados = [];
        $telas = (array) $this->tela->getTelaId($tel_id)[0] ?? null;
        if ($telas && !empty($telas['tel_model'])) {
            $model = $telas['tel_model'];
            $model_atual = model("App\\Models\\" . substr($model, 0, 6) . "\\" . $model);
            $view   = $model_atual->view;
            if (isset($model_atual->viewoutra)) {
                $view   = $model_atual->viewoutra;
            }
            $fields = array_filter(array_column($camp, 'etc_campo'), fn($f) => $f !== '0' && $f !== '1');
            $dados = $this->common->getListaTabela($model_atual->DBGroup, $view, $fields, false, max(1, 5));
        }
        if (!$dados) $dados = [[]];

        $zplLinha = $this->buildLinha($dados, $camp, true);

        $cols = max(1, (int)$this->colunas);
        $totalRows = (int)ceil(count($dados) / $cols);

        $etqW = $this->mm2dot($this->largura);
        $ml   = $this->mm2dot($this->esquerda);
        $mr   = $this->mm2dot($this->direita);
        $gh   = $this->mm2dot($this->horizontal);
        $pwDots = $ml + ($cols * $etqW) + (($cols - 1) * $gh) + $mr;

        $etqH = $this->mm2dot($this->altura);
        $mt   = $this->mm2dot($this->topo);
        $mb   = $this->mm2dot($this->rodape);
        $gv   = isset($this->vertical) ? $this->mm2dot($this->vertical) : 0;

        $llDots = $mt + ($totalRows * $etqH) + (max(0, $totalRows - 1) * $gv) + $mb;

        $larg_in = round($pwDots / $this->dpi, 3);
        $altu_in = round($llDots / $this->dpi, 3);

        // se sua impressora for 203dpi, 8dpmm; 300dpi, 12dpmm
        $dpmm = max(1, (int)round($this->dpi / 25.4));

        $url = "http://api.labelary.com/v1/printers/{$dpmm}dpmm/labels/{$larg_in}x{$altu_in}/0/";


        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $zplLinha, // stream completo com todas as ^XA…^XZ
            CURLOPT_HTTPHEADER     => ['Accept: image/png'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $png  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code !== 200 || !$png) {
            // se uma página falhar, retorna o erro dessa página
            return $this->response->setJSON([
                'erro'   => 'Falha no preview (Labelary)',
                'http'   => $code,
                'detalhe' => $err ?: '',
            ]);
        }

        $imagem = base64_encode($png);

        // retorna TODAS as imagens
        return $this->response->setJSON(['imagem' => $imagem]);
    }
}
