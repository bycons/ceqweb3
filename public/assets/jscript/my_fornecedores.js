/**
 * my_fornecedores.js
 * Módulo Fornecedores — T42 (Desvio de Qualidade) / T43 (Notificação de Evento).
 *
 * Reaproveita 100% o mecanismo de etiqueta já existente em my_default.js
 * (gerarEtiquetaZPL() / openImgModal()) — ver GeraEtiqueta() nos
 * controllers App\Controllers\Fornecedores\NotifDesvio (e, futuramente,
 * NotifEvento). Não introduz nenhum componente novo de seleção de etiqueta.
 *
 * Mirror de geraEiquetaProd() (my_default.js, ~linha 1976), mas sem depender
 * de um campo de quantidade na tela (a listagem sempre imprime 1 etiqueta
 * por clique — RN03.15 de T42 / RN04.1 de T43).
 */
function geraEiquetaGenerico(obj, url, qtia = 1) {
  jQuery(obj).removeClass("btn-outline-dark");
  jQuery(obj).addClass("btn-outline-danger");
  jQuery.getJSON(url, function (res) {
    if (res.erro) {
      boxAlert(res.erro, true, "");
      return;
    }
    gerarEtiquetaZPL(res.link, false, res.chave, qtia);
  });
}

/**
 * mostraNotivisaNum
 * RN03.18/RN03.19 (T43) — mostra/oculta o campo nev_notivisa_num conforme o
 * toggle Notivisa (EntOcoNotifEvento::defCampos(), funcChan do
 * crCheckbox()). Reaproveita o mecanismo genérico já existente
 * `mostraOcultaCampo(obj, regra, fields)` (my_fields.js) — o mesmo usado em
 * outros campos condicionais por checkbox do projeto (ex.:
 * Preproces\InspecaoProd) — sem reimplementar show/hide na mão.
 */
function mostraNotivisaNum(obj) {
  mostraOcultaCampo(obj, "S", "nev_notivisa_num");
}

/**
 * excluiAnexoExistente
 * RN03.16/RN03.20 (T43) — exclui um anexo JÁ PERSISTIDO (diferente de
 * exclui_campo(), que só remove uma linha ainda não salva do form). Confirma
 * via boxAlert (nunca confirm() nativo — rascunho-runtime-js.md), chama
 * NotifEvento::deleteAnexo() via executaAjax() (nunca jQuery.ajax cru), e
 * remove a linha do DOM só se o back confirmar sucesso.
 *
 * @param {object} obj    - elemento clicado (botão Excluir), usado pra achar a linha (.row) a remover
 * @param {number} nva_id - PK de oco_notif_evento_anexo
 * @param {string} sufixo - "anexo_provid" | "anexo_parecer" (mesmo nome do container usado por addCampo()/exclui_campo())
 */
/**
 * confirmaSelecaoProdutos
 * RN03.1 (T43) — botão "Selecionar Produtos" do título de NotifEvento::add():
 * confere se há ao menos 1 checkbox ndv_id[] marcado e envia o form1 via POST
 * nativo (form.submit(), fora do fluxo AJAX de bt_salvar/submeteForm, que
 * espera JSON) para NotifEvento::selecionaProdutos(), que responde com a
 * página completa do cadastro de 4 abas.
 */
function confirmaSelecaoProdutos() {
  if (jQuery('input[name="ndv_id[]"]:checked').length === 0) {
    boxAlert("Selecione ao menos um produto para notificar", true, "");
    return;
  }
  var form = document.getElementById("form1");
  form.action = window.location.origin + "/NotifEvento/selecionaProdutos";
  form.submit();
}

async function excluiAnexoExistente(obj, nva_id, sufixo) {
  const confirmado = await boxAlert(
    "Confirma a exclusão deste anexo?",
    false,
    "",
    false,
    1,
    true,
    "Excluir Anexo",
  );

  if (!confirmado) {
    return;
  }

  const url = window.location.origin + "/NotifEvento/deleteAnexo/" + nva_id;
  retornoAjax = false;
  executaAjax(url, "json");

  if (retornoAjax && !retornoAjax.erro) {
    jQuery(obj).closest(".row").remove();
    jQuery("#form1").attr("data-alter", true);
    mostranoToast(retornoAjax.msg);
  } else if (retornoAjax) {
    boxAlert(retornoAjax.msg, true, "");
  }
}
