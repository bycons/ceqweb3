/**
 * buscaSaldo
 * Processa a busca de Saldos de Estoque
 */
async function buscaSaldo() {
  // bloqueiaTela();
  var codDep = jQuery.trim(jQuery("#codDep").val());
  var codPro = jQuery.trim(jQuery("#codPro").val());
  urlBusca = "SaldoEstoque/lista";
  dados = { codDep: codDep, codPro: codPro };
  try {
    const retornoAjax = await executaAjaxWait(urlBusca, "json", dados);
    montaListaSaldo(retornoAjax);
  } catch (error) {
    console.log("Erro na requisição AJAX:", error);
  }
  // retornoAjax = executaAjaxWait(urlBusca, 'json', dados, 'montaListaSaldo(retornoAjax)');
  // // desBloqueiaTela();
  // if (retornoAjax) {
  // }
  // desBloqueiaTela();
}

function montaListaSaldo(dados) {
  jQuery("#table").DataTable().destroy();
  removeLinhas("table", 0);

  linha = "";
  for (var index in dados) {
    item = dados[index];
    linha = linha + "<tr>";
    linha = linha + "<td class='align-middle'>" + item.codDep + "</td>";
    linha = linha + "<td class='align-middle'>" + item.Coderp + "</td>";
    linha = linha + "<td class='align-middle'>" + item.DescProduto + "</td>";
    linha = linha + "<td class='align-middle'>" + item.lote + "</td>";
    linha =
      linha +
      "<td class='align-middle' data-sort='" +
      item.validadeord +
      "'>" +
      item.validade +
      "</td>";
    linha =
      linha + "<td class='align-middle text-end pe-4'>" + item.saldo + "</td>";
    linha = linha + "<td class='align-middle text-start'>" + item.und + "</td>";
    linha =
      linha +
      "<td class='align-middle' data-sort='" +
      item.entradaord +
      "'>" +
      item.entrada +
      "</td>";
    linha = linha + "</tr>";
  }
  jQuery("#table tbody").append(linha);
  dtResult("table");
}

function montaListaLogs(dados) {
  jQuery("#table").DataTable().destroy();
  removeLinhas("table", 0);

  linha = "";
  descri = "";
  for (var index in dados) {
    item = dados[index];
    descri = item.titulo + "<br>" + item.detalhe;

    linha = linha + "<tr>";
    linha = linha + "<td class='align-middle'>" + item.data + "</td>";
    linha = linha + "<td class='align-middle'>" + item.usuario + "</td>";
    linha = linha + "<td class='align-middle'>" + item.tela + "</td>";
    linha = linha + "<td class='align-middle'>" + descri + "</td>";
    linha = linha + "<td class='align-middle'>" + item.metodo + "</td>";
    linha = linha + "<td class='align-middle'>" + item.registro + "</td>";
    linha = linha + "<td class='align-middle'>" + item.acesso + "</td>";
    linha = linha + "<td class='align-middle'>" + item.ip + "</td>";
    linha = linha + "</tr>";
  }
  jQuery("#table tbody").append(linha);
  dtResult("table");
}

function dtResult(tabela) {
  // monta o Datable
  var table = jQuery("#" + tabela).DataTable({
    responsive: false,
    sPaginationType: "full_numbers",
    aaSorting: [],
    columnDefs: [
      // { "max-width": "8em", "targets": ['all'] },
      { "min-width": "8em", targets: ["all"] },
      { width: "8em", targets: ["all"] },
      { className: "text-wrap text-nowrap", targets: ["all"] },
    ],
    buttons: {
      dom: {
        button: {
          className: "btn btn-outline-primary wauto",
          style: "max-width: 31.5px!important;",
        },
      },
      buttons: [
        {
          extend: "searchBuilder",
          text: '<i class="fas fa-filter" aria-hidden="true"></i>',
          titleAttr: "Filtrar",
          config: {
            text: '<i class="fas fa-filter" aria-hidden="true"></i>',
            id: "bt_filtro",
            columns: [":not(.acao)", ":visible"],
            defaultCondition: "Igual",
          },
          preDefined: {
            criteria: [
              {
                condition: "Igual",
              },
            ],
          },
        },
        {
          extend: "excelHtml5",
          text: '<i class="far fa-file-excel" aria-hidden="true"></i>',
          titleAttr: "Exportar para Excel",
          title: function () {
            return document.title + " - " + jQuery("#legenda").text();
          },
          filename: function () {
            return document.title + " - " + jQuery("#legenda").text();
          },
          exportOptions: {
            columns: [":not(.acao)"],
          },
        },
        {
          extend: "pdfHtml5",
          text: '<i class="far fa-file-pdf" aria-hidden="true"></i>',
          titleAttr: "Exportar para PDF",
          title: function () {
            return document.title + " - " + jQuery("#legenda").text();
          },
          filename: function () {
            return document.title + " - " + jQuery("#legenda").text();
          },
          exportOptions: {
            columns: [":not(.acao)"],
          },
        },
        {
          extend: "print",
          text: '<i class="fas fa-print" aria-hidden="true"></i>',
          titleAttr: "Enviar para Impressora",
          title: function () {
            return document.title + " - " + jQuery("#legenda").text();
          },
          exportOptions: {
            columns: [":not(.acao)"],
          },
        },
      ],
    },
    // header options
    orderCellsTop: true,
    fixedHeader: true,
    bSortCellsTop: true,
    pageLength: 50,
    bPaginate: true,
    scrollY: "58vh !important",
    bProcessing: true,
    bScrollCollapse: true,
    deferRender: true,
    sDom: "ftrBip",
    language: {
      url: "assets/jscript/datatables-lang/pt-BR.json",
    },
    fnDrawCallback: function (oSettings) {
      jQuery("table#" + tabela + " > tbody > tr")
        .on("mouseover", function () {
          jQuery(this).children().find(".btn").addClass("hover");
        })
        .on("mouseleave", function () {
          jQuery(this).children().find(".btn").removeClass("hover");
        });
    },
  });
}

function removeLinhas(idTabela, indice = 1) {
  if (indice != 1) {
    jQuery("#" + idTabela + " tbody")
      .children()
      .remove();
  } else {
    jQuery("#" + idTabela)
      .find("tr:gt(" + indice + ")")
      .remove();
  }
}

/**
 * buscaSaldo
 * Processa a busca de Saldos de Estoque
 */
async function buscaLogUser() {
  // bloqueiaTela();
  var usuario = jQuery("#log_usuario").val();
  if (usuario == "" || usuario == null) {
    boxAlert("Informe o Usuário", true, "", true, 1, false, "Alerta");
  } else {
    var periodo = jQuery.trim(jQuery("#log_periodo").val());
    var startDate = periodo.substr(0, 10); //jQuery("#periodo").data('daterangepicker').startDate.format('DD/MM/YYYY');
    var endDate = periodo.substr(-10); //jQuery("#periodo").data('daterangepicker').endDate.format('DD/MM / YYYY');
    urlBusca = "CfgLoguser/lista";
    dados = {
      usuario: usuario,
      periodo: periodo,
      inicio: startDate,
      fim: endDate,
    };
    try {
      const retornoAjax = await executaAjaxWait(urlBusca, "json", dados);
      montaListaLogs(retornoAjax);
    } catch (error) {
      console.log("Erro na requisição AJAX:", error);
    }
  }
}

async function buscaAnaliseMP() {
  var $selProd = jQuery("select[name^='codPro']");
  var codProArr = $selProd.val() || [];
  var codPro = codProArr.join(",");
  var codLot = jQuery.trim(jQuery("#codLot").val());
  var perAnalise = jQuery.trim(jQuery("#perAnalise").val());

  urlBusca = "AnaliseMP/lista";
  dados = { codPro: codPro, codLot: codLot, perAnalise: perAnalise };

  try {
    const retornoAjax = await executaAjaxWait(urlBusca, "json", dados);
    montaListaAnaliseMP(retornoAjax);
  } catch (error) {
    console.log("Erro na requisição AJAX:", error);
  }
}

function montaListaAnaliseMP(dados) {
  jQuery("#table").DataTable().destroy();
  removeLinhas("table", 0);

  let linha = "";
  let loteAnterior = null;

  for (let index in dados) {
    let item = dados[index];

    let eventos =
      item.historico && item.historico.length > 0
        ? item.historico
        : [
            {
              dataHora: item.dataHora,
              dataHoraord: item.dataHora,
              status: item.status,
              usuario: item.usuario,
            },
          ];

    // Detecta início de um novo grupo (produto/lote diferente do anterior)
    let novoGrupo = loteAnterior !== null && loteAnterior !== item.ana_id;
    loteAnterior = item.ana_id;

    eventos.forEach(function (mov, idx) {
      // Só marca a PRIMEIRA linha de cada grupo com a borda separadora
      let classeGrupo =
        novoGrupo && idx === 0 ? " class='grupo-separador'" : "";

      linha += "<tr data-ana-id='" + item.ana_id + "'" + classeGrupo + ">";
      linha += "<td class='align-middle'>" + item.Produto + "</td>";
      linha += "<td class='align-middle'>" + item.Fabricante + "</td>";
      linha += "<td class='align-middle'>" + item.lote + "</td>";
      linha +=
        "<td class='align-middle' data-sort='" +
        item.validadeord +
        "'>" +
        item.validade +
        "</td>";
      linha +=
        "<td class='align-middle' data-sort='" +
        mov.dataHoraord +
        "'>" +
        mov.dataHora +
        "</td>";
      linha += "<td class='align-middle'>" + mov.status + "</td>";
      linha += "<td class='align-middle'>" + mov.usuario + "</td>";
      linha += "</tr>";
    });
  }

  jQuery("#table tbody").append(linha);

  dtResult("table");
  jQuery("#table").DataTable().order([]).draw();
}