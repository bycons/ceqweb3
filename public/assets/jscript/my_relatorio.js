/**
 * my_relatorio.js
 * Funções JS para o Gerador de Relatórios (CfgRelatorio)
 *
 * Variáveis globais esperadas (setadas pelo PHP):
 *   var charsLinha, urlCharsLinha, urlCamposFil, urlPreview;
 *   var urlPreviewDocumento, urlAddRelatorio; (*claude* só usadas pelo tipo DOCUMENTO)
 */

// *claude* true quando a tela atual é de um relatório rel_tipo_saida=DOCUMENTO
// (abas Cabeçalho/Tabela/Textos Livres em vez de Filtros/Colunas) — detectado
// pela presença do campo rel_tabela_detalhe, que só é renderizado nesse tipo
// (ver CfgRelatorio::_montaTelaDocumento()). Usado pra não disparar o preview
// do Documento (nem os inicializadores de linha repetível das 3 abas novas)
// numa tela Tabular, já que rel_formato/rel_titulo têm o MESMO name nos dois
// tipos e os handlers abaixo são registrados incondicionalmente.
var ehDocumento = false;

jQuery(function () {
  ehDocumento = jQuery('[name="rel_tabela_detalhe"]').length > 0;

  // ── Inicialização ────────────────────────────────────────────────────
  acerta_botoes_rep("filtros");
  acerta_botoes_rep("colunas");
  acerta_botoes_rep("cabecalho");
  acerta_botoes_rep("tabela");
  acerta_botoes_rep("rodape_doc");

  // Restaura seleção dos selects dependentes nas abas repetíveis (+
  // rel_titulo, que no DOCUMENTO também virou select — ver EntCfgRelatorios)
  jQuery('[name^="rfi_campo"], [name^="rco_campo"], [name^="rcc_campo"], [name^="rct_campo"], [name^="rtx_campo"], [name="rel_titulo"]').each(function () {
    var val = jQuery(this).data("valor") || jQuery(this).data("selec");
    if (val) {
      jQuery(this).selectpicker("val", val);
    }
  });

  // Carrega preview inicial (edit)
  if (ehDocumento) {
    atualizarPreviewDocumento();
  } else {
    atualizarPreview();
  }

  // *claude* monta as opções do select "depende de" (aba Filtros) a partir dos rfi_campo
  // já escolhidos nas OUTRAS linhas — na primeira linha (nenhum outro filtro configurado)
  // não sobra nenhuma opção além de "(Nenhum)".
  atualizaDependeDe();

  // *claude* observa inclusão/remoção de linhas na grade de Filtros (botões + / lixeira)
  // pra manter as opções de "depende de" sempre em dia, sem precisar mexer em addCampo()/exclui_campo()
  var repFiltros = document.getElementById("rep_filtros");
  if (repFiltros && typeof MutationObserver !== "undefined") {
    new MutationObserver(atualizaDependeDe).observe(repFiltros, {
      childList: true,
    });
  }

  // *claude* DOCUMENTO — popula rcc_campo/rct_campo/rtx_campo (crSelect()
  // puro, ver EntCfgRelCamposCab/EntCfgRelColunasDoc/EntCfgRelTextos) com os
  // campos de rel_tabela_base + rel_tabela_detalhe já escolhidos, na carga
  // inicial da tela (add/edit).
  if (ehDocumento) {
    atualizaCamposDocumento();
  }

  // *claude* mesmo padrão do MutationObserver de #rep_filtros acima, mas pras
  // 3 abas do Documento — cobre linha nova adicionada via addCampoCab/
  // addColunaDoc/addTextoLivre (addCampo() genérica, my_fields.js, não
  // sabe popular um crSelect() puro, só um .dependente).
  ["rep_cabecalho", "rep_tabela", "rep_rodape_doc"].forEach(function (id) {
    var container = document.getElementById(id);
    if (container && typeof MutationObserver !== "undefined") {
      new MutationObserver(atualizaCamposDocumento).observe(container, {
        childList: true,
      });
    }
  });

  // ── Ao mudar tabela base: repopula selects de filtro ─────────────────
  jQuery(document).on("change", '[name="rel_tabela_base"]', function () {
    var tabela = jQuery(this).val();
    if (!tabela) return;
    jQuery.get(
      urlCamposFil,
      { busca: tabela },
      function (res) {
        jQuery('[name^="rfi_campo"]').each(function () {
          var sel = jQuery(this);
          var val = sel.val();
          sel.selectpicker("destroy");
          sel.empty().append('<option value="">Selecione...</option>');
          jQuery.each(res, function (i, op) {
            sel.append(
              '<option value="' + op.id + '">' + op.text + "</option>",
            );
          });
          sel.val(val);
          sel.selectpicker();
        });
        // *claude* tabela base mudou -> campos de filtro escolhidos ficaram inválidos,
        // então as opções de "depende de" precisam ser recalculadas também
        atualizaDependeDe();
      },
      "json",
    );

    atualizarPreview();
  });

  // ── Recalcula chars ao mudar formato ou fonte ────────────────────────
  jQuery(document).on(
    "change",
    '[name="rel_formato"], [name="rel_tamanho_fonte"]',
    function () {
      jQuery.get(
        urlCharsLinha,
        {
          // :checked para radio buttons (cr2opcoes)
          formato: jQuery('[name="rel_formato"]:checked').val() || "P",
          fonte: jQuery('[name="rel_tamanho_fonte"]').val(),
        },
        function (res) {
          if (!res.erro) {
            charsLinha = res.chars_por_linha;
            // Atualiza campo visual de chars/linha
            jQuery('[name="rel_chars_display"]').val(charsLinha);
            // Verifica se as colunas cabem na nova orientação/fonte
            verificaLargura();
          }
        },
        "json",
      );

      atualizarPreview();
    },
  );

  // ── Ao selecionar campo de coluna: preenche ocultos ──────────────────
  jQuery(document).on("change", '[name^="rco_campo"]', function () {
    var partes = jQuery(this).val().split("|");
    var tabela = partes[0] || "";
    var tamanho = parseInt(partes[2] || 0);
    var tipo = partes[3] || "";
    var linha = jQuery(this).closest(".table-colunas");

    linha.find('[name^="rco_tabela"]').val(tabela);
    linha.find('[name^="rco_tamanho"]').val(tamanho);
    linha.find('[name^="rco_tipo_dado"]').val(tipo);
    // Largura padrão baseada no tipo: date=12, int/float/double=10, datetime=20, demais=tamanho original
    var tipoLower = tipo.toLowerCase();
    var larguraAuto = tamanho;
    if (tipoLower === "date") {
      larguraAuto = 12;
    } else if (["int", "float", "double", "decimal"].indexOf(tipoLower) >= 0) {
      larguraAuto = 10;
    } else if (["datetime", "timestamp"].indexOf(tipoLower) >= 0) {
      larguraAuto = 20;
    }
    linha.find('[name^="rco_largura"]').val(larguraAuto);

    var numerico =
      ["int", "float", "double", "decimal"].indexOf(tipo.toLowerCase()) >= 0;
    var btnTotal = linha.find('[name^="rco_totalizar"]');
    if (numerico) {
      btnTotal.prop("disabled", false);
    } else {
      btnTotal.filter('[value="0"]').prop("checked", true);
      btnTotal.prop("disabled", true);
    }

    verificaLargura();
    atualizarPreview();
  });

  // ── Ao selecionar campo de filtro: preenche ocultos ──────────────────
  jQuery(document).on("change", '[name^="rfi_campo"]', function () {
    var partes = jQuery(this).val().split("|");
    var linha = jQuery(this).closest(".table-filtros");
    linha.find('[name^="rfi_tabela"]').val(partes[1] || "");
    linha.find('[name^="rfi_tipo_filtro"]').val(partes[2] || "FK");
    // *claude* o campo escolhido aqui pode virar candidato a "pai" de outra linha (ou deixar
    // de sê-lo, se foi trocado) — recalcula as opções de "depende de" em todas as linhas
    atualizaDependeDe();
    atualizarPreview();
  });

  // *claude* o texto do label também é usado como rótulo das opções em "depende de"
  jQuery(document).on("change", '[name^="rfi_label"]', atualizaDependeDe);

  // ── Ao alterar largura ou comportamento: recalcula limite + preview ───
  jQuery(document).on(
    "change",
    '[name^="rco_largura"], [name^="rco_comportamento"]',
    function () {
      verificaLargura();
      atualizarPreview();
    },
  );

  // ── Eventos que disparam preview ─────────────────────────────────────
  jQuery(document).on("blur", '[name="rel_titulo"]', atualizarPreview);
  jQuery(document).on(
    "change",
    '[name="rel_totalizar_registros"], [name^="rco_label"], ' +
      '[name^="rco_alinhamento"], [name^="rco_totalizar"], [name^="rfi_label"]',
    atualizarPreview,
  );

  // ── DOCUMENTO — reload da tela ao trocar rel_tipo_saida ──────────────
  // Como as abas (Filtros/Colunas x Cabeçalho/Tabela/Textos Livres) são
  // montadas no servidor (CfgRelatorio::_montaTelaTabular()/_montaTelaDocumento()),
  // trocar o tipo precisa recarregar a tela — só possível na CRIAÇÃO (rel_id
  // vazio); depois de salvo uma vez o rádio fica travado (setLeitura no
  // servidor, ver EntCfgRelatorios::defCampos()).
  jQuery(document).on("change", '[name="rel_tipo_saida"]', function () {
    if (jQuery('[name="rel_id"]').val()) return;
    if (typeof urlAddRelatorio === "undefined" || !urlAddRelatorio) return;
    var tipo = jQuery('[name="rel_tipo_saida"]:checked').val() || "TABULAR";
    window.location = urlAddRelatorio + "?tipo=" + tipo;
  });

  // ── DOCUMENTO — Regra 4: recalcula "Coluna de Vínculo" ao vivo sempre
  // que a Tabela Cabeçalho OU a Tabela Detalhe mudam. É só feedback visual
  // (campo oculto + texto informativo) — o store() real recalcula do zero
  // no servidor e ignora isso, então não há risco de inconsistência mesmo
  // que este JS fique desatualizado.
  //
  // *claude* Regra B — na mesma troca, repopula rcc_campo/rct_campo/
  // rtx_campo (crSelect() puro) com os campos das duas tabelas juntas.
  jQuery(document).on(
    "change",
    '[name="rel_tabela_base"], [name="rel_tabela_detalhe"]',
    function () {
      if (ehDocumento) {
        atualizaCampoVinculo();
        atualizaCamposDocumento();
      }
    },
  );

  // ── DOCUMENTO — Ao selecionar campo do Cabeçalho: preenche ocultos ───
  jQuery(document).on("change", '[name^="rcc_campo"]', function () {
    var partes = jQuery(this).val().split("|");
    var linha = jQuery(this).closest(".table-cabecalho");
    linha.find('[name^="rcc_tabela"]').val(partes[0] || "");
    linha.find('[name^="rcc_tamanho"]').val(parseInt(partes[2] || 0));
    linha.find('[name^="rcc_tipo_dado"]').val(partes[3] || "");
    atualizarPreviewDocumento();
  });

  // ── DOCUMENTO — Ao selecionar campo da Tabela: preenche ocultos ──────
  jQuery(document).on("change", '[name^="rct_campo"]', function () {
    var partes = jQuery(this).val().split("|");
    var linha = jQuery(this).closest(".table-tabela");
    var tamanho = parseInt(partes[2] || 0);
    linha.find('[name^="rct_tabela"]').val(partes[0] || "");
    linha.find('[name^="rct_tamanho"]').val(tamanho);
    linha.find('[name^="rct_tipo_dado"]').val(partes[3] || "");
    linha.find('[name^="rct_largura"]').val(tamanho);
    atualizarPreviewDocumento();
  });

  // ── DOCUMENTO — Ao selecionar campo do Rodapé: preenche ocultos ──────
  jQuery(document).on("change", '[name^="rtx_campo"]', function () {
    var partes = (jQuery(this).val() || "").split("|");
    var linha = jQuery(this).closest(".table-rodape_doc");
    linha.find('[name^="rtx_tabela"]').val(partes[0] || "");
    atualizarPreviewDocumento();
  });

  // ── DOCUMENTO — demais eventos que disparam o preview do Documento ───
  // (rel_formato/rel_titulo já têm handler próprio acima — reaproveitado
  // aqui só pra disparar TAMBÉM atualizarPreviewDocumento(), que se
  // autolimita a telas Documento via a guarda "ehDocumento" no topo da função)
  jQuery(document).on("blur", '[name="rel_titulo"], [name="id_teste_documento"]', atualizarPreviewDocumento);
  jQuery(document).on(
    "change",
    // *claude* rel_tamanho_fonte incluído aqui — controla a fonte do CORPO
    // (tabela) do Documento (ver CriamPdf2026::htmlDocumentoGenerico()).
    // *claude* rel_titulo incluído aqui também — no DOCUMENTO virou
    // <select> (bootstrap-select), e "blur" pode não disparar de forma
    // confiável num selectpicker; "change" garante o preview atualizar.
    '[name="rel_formato"], [name="rel_tamanho_fonte"], [name="rel_titulo"], [name="id_teste_documento"], [name^="rcc_label"], ' +
      '[name^="rcc_largura_col"], [name^="rct_label"], [name^="rct_largura"], [name^="rtx_label"], [name^="rtx_texto"]',
    atualizarPreviewDocumento,
  );
});

// ── Verifica largura total das colunas (soma rco_largura, ignorando linha inteira) ──
function verificaLargura() {
  if (typeof charsLinha === "undefined" || charsLinha <= 0) return;
  var total = 0;
  jQuery('[name^="rco_largura"]').each(function () {
    var linha = jQuery(this).closest(".table-colunas");
    var comport = linha.find('[name^="rco_comportamento"]').val() || "cortar";
    if (comport !== "linha") {
      total += parseInt(jQuery(this).val() || 0);
    }
  });
  if (total > charsLinha) {
    boxAlert(
      "Atenção: largura total das colunas (" +
        total +
        ") ultrapassa o limite da linha (" +
        charsLinha +
        " caracteres).",
      true,
      "",
      true,
      1,
      false,
      "Largura excedida",
    );
  }
}

// *claude* ── "Depende de" (cascata de filtros) ──────────────────────────
// Recalcula, em TODAS as linhas da grade de Filtros, as opções do select
// "depende de": só os rfi_campo dos filtros que aparecem ANTES da linha atual na
// tela (a ordem de exibição É a ordem de montagem — um filtro nunca pode depender
// de outro que só vai aparecer depois dele). A 1ª linha nunca tem candidato.
// *claude* esconde/mostra um wrapper de campo vencendo o "display:inline-flex !important"
// que fmtDisplay() (MyCampo.php) aplica via classe Bootstrap "d-inline-flex" — jQuery
// .hide()/.show()/.css() só setam display sem !important, então uma classe !important
// sempre ganha deles. Setar o !important direto no style inline é o único jeito de vencer.
function esconderCampo($el) {
  $el.each(function () {
    this.style.setProperty("display", "none", "important");
  });
}
function mostrarCampo($el) {
  $el.each(function () {
    this.style.removeProperty("display");
  });
}

function atualizaDependeDe() {
  // *claude* candidatos acumulados conforme percorre as linhas EM ORDEM — ao processar
  // uma linha, só entram na lista os campos das linhas já processadas (ou seja, as
  // anteriores). Só um .each(): a linha só entra na lista de candidatos DEPOIS de
  // montado o próprio select, então nunca aparece nas próprias opções.
  var candidatosAteAqui = [];

  jQuery(".table-filtros").each(function () {
    var $linha = jQuery(this);
    var campoVal = $linha.find('[name^="rfi_campo"]').val() || "";
    var campo = campoVal.split("|")[0];
    var tipo = $linha.find('[name^="rfi_tipo_filtro"]').val() || "FK";
    var label = $linha.find('[name^="rfi_label"]').val();

    var selPai = $linha.find('[name^="rfi_campo_pai"]');
    if (selPai.length > 0) {
      var wrapperPai = selPai.closest(".row");

      // *claude* esconde "depende de" quando: (a) o filtro é do tipo DATE (usa daterange,
      // não select) ou (b) não existe nenhum candidato válido ainda (ex.: 1ª linha, ou só
      // filtros DATE antes dela) — sem opção nenhuma pra oferecer, não faz sentido mostrar
      // o campo. Limpa qualquer valor que tenha ficado de uma configuração anterior.
      if (tipo === "DATE" || candidatosAteAqui.length === 0) {
        esconderCampo(wrapperPai);
        if (selPai.val()) {
          selPai.selectpicker("val", "");
        }
      } else {
        mostrarCampo(wrapperPai);

        var atual = selPai.val();
        selPai.selectpicker("destroy");
        selPai.empty().append('<option value="">(Nenhum)</option>');
        candidatosAteAqui.forEach(function (c) {
          selPai.append(
            '<option value="' + c.campo + '">' + c.label + "</option>",
          );
        });

        var validas = selPai
          .find("option")
          .map(function () {
            return jQuery(this).val();
          })
          .get();
        selPai.val(validas.indexOf(atual) >= 0 ? atual : "");
        selPai.selectpicker();
      }
    }

    // *claude* só entra pra lista de candidatos AGORA — depois de montado o select desta
    // linha — assim ela mesma nunca aparece nas próprias opções, e as linhas seguintes só
    // enxergam quem veio antes.
    if (campo && tipo !== "DATE") {
      candidatosAteAqui.push({ campo: campo, label: label || campo });
    }
  });
}

// ── Preview com debounce (TABULAR) ────────────────────────────────────────
var previewTimer = null;
function atualizarPreview() {
  // *claude* auto-limita: numa tela Documento (rel_formato/rel_titulo têm o
  // MESMO name dos dois tipos, e os handlers que chamam esta função são
  // registrados incondicionalmente) esta função não deve fazer nada — quem
  // atualiza o preview ali é atualizarPreviewDocumento().
  if (ehDocumento) return;
  if (typeof urlPreview === "undefined" || !urlPreview) return;
  clearTimeout(previewTimer);
  previewTimer = setTimeout(function () {
    var formData = jQuery("#form1").serialize();
    jQuery.post(
      urlPreview,
      formData,
      function (res) {
        if (res.html) {
          jQuery("#prevRelatorio").html(res.html);
        }
      },
      "json",
    );
  }, 500);
}

// ── Preview com debounce (DOCUMENTO) ──────────────────────────────────────
var previewDocTimer = null;
function atualizarPreviewDocumento() {
  // *claude* auto-limita ao inverso de atualizarPreview() — ver comentário lá.
  if (!ehDocumento) return;
  if (typeof urlPreviewDocumento === "undefined" || !urlPreviewDocumento) return;
  clearTimeout(previewDocTimer);
  previewDocTimer = setTimeout(function () {
    var formData = jQuery("#form1").serialize();
    jQuery.post(
      urlPreviewDocumento,
      formData,
      function (res) {
        if (res.html) {
          jQuery("#prevRelatorio").html(res.html);
        }
      },
      "json",
    );
  }, 500);
}

// ── DOCUMENTO — Regra 4 (rel_detalhe_campo_vinculo calculado) ─────────────
// Atualiza o campo oculto "rel_detalhe_campo_vinculo" (o que realmente
// viaja no POST) e o texto informativo "rel_detalhe_campo_vinculo_display"
// (só visual) consultando Buscas::busca_campo_vinculo_rel() ao vivo. Só
// feedback — quem manda de verdade é o recálculo no servidor, dentro de
// CfgRelatorio::_storeDocumento().
function atualizaCampoVinculo() {
  if (typeof urlCampoVinculo === "undefined" || !urlCampoVinculo) return;

  var base = jQuery('[name="rel_tabela_base"]').val();
  var detalhe = jQuery('[name="rel_tabela_detalhe"]').val();
  var $oculto = jQuery('[name="rel_detalhe_campo_vinculo"]');
  var $display = jQuery('[name="rel_detalhe_campo_vinculo_display"]');

  if (!base || !detalhe) {
    $oculto.val("");
    $display.val("(selecione a Tabela Cabeçalho e a Tabela Detalhe)");
    return;
  }

  jQuery.get(
    urlCampoVinculo,
    { base: base, detalhe: detalhe },
    function (res) {
      if (res && !res.erro && res.campo) {
        $oculto.val(res.campo);
        $display.val(res.campo);
      } else {
        $oculto.val("");
        $display.val(
          (res && res.msg) || "Nenhum campo relacionado encontrado.",
        );
      }
      atualizarPreviewDocumento();
    },
    "json",
  );
}

// ── DOCUMENTO — Regra B (Cabeçalho/Tabela/Textos Livres aceitam campos de
// rel_tabela_base OU rel_tabela_detalhe) ──────────────────────────────────
// Repopula rcc_campo/rct_campo/rtx_campo (crSelect() puro — ver
// EntCfgRelCamposCab/EntCfgRelColunasDoc/EntCfgRelTextos) com a UNIÃO dos
// campos de rel_tabela_base + rel_tabela_detalhe, via
// Buscas::busca_campos_tabela_unica(). Mesmo espírito de atualizaDependeDe()
// (aba Filtros), mas sem cascata — só popula as 3 grades.
function atualizaCamposDocumento() {
  if (!ehDocumento) return;
  if (typeof urlCamposDocumento === "undefined" || !urlCamposDocumento) return;

  var base = jQuery('[name="rel_tabela_base"]').val();
  var detalhe = jQuery('[name="rel_tabela_detalhe"]').val();
  if (!base) return;

  var busca = detalhe ? base + "," + detalhe : base;

  jQuery.get(
    urlCamposDocumento,
    { busca: busca },
    function (res) {
      var opcoes = Array.isArray(res) ? res : [];

      jQuery(
        '.table-cabecalho [name^="rcc_campo"], ' +
          '.table-tabela [name^="rct_campo"], ' +
          '.table-rodape_doc [name^="rtx_campo"]',
      ).each(function () {
        // Rodapé: campo vinculado é opcional (linha pode ser só texto)
        var opcional = this.name.indexOf("rtx_campo") === 0;
        _repopulaSelectDocumento(jQuery(this), opcoes, opcional);
      });

      // *claude* rel_titulo (Título selecionável, ver EntCfgRelatorios::
      // defCampos()) — campo ÚNICO, não está dentro de linha repetível
      // nenhuma, então trata como caso avulso aqui (não precisa iterar).
      var $tituloSelect = jQuery('[name="rel_titulo"]');
      if ($tituloSelect.length && $tituloSelect.is("select")) {
        _repopulaSelectDocumento($tituloSelect, opcoes);
      }
    },
    "json",
  );
}

// Reconstrói as <option> de UM select (rcc_campo/rct_campo/rtx_campo) a
// partir da lista devolvida por busca_campos_tabela_unica(), preservando a
// seleção atual se ela ainda estiver entre as opções válidas (senão limpa —
// campo apontava pra uma tabela que deixou de fazer parte do Documento).
// opcional=true mantém a opção vazia "(Nenhum — só texto)" (Rodapé).
function _repopulaSelectDocumento($select, opcoes, opcional) {
  var atual = $select.val();
  $select.selectpicker("destroy");
  $select.empty();

  if (opcional) {
    $select.append(
      jQuery("<option></option>").attr("value", "").text("(Nenhum — só texto)"),
    );
  }

  opcoes.forEach(function (op) {
    $select.append(
      jQuery("<option></option>").attr("value", op.id).text(op.text),
    );
  });

  var validas = $select
    .find("option")
    .map(function () {
      return jQuery(this).val();
    })
    .get();

  $select.val(validas.indexOf(atual) >= 0 ? atual : "");
  $select.addClass("selectpicker");
  $select.selectpicker();
}
