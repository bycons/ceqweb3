<?
if (!isset($show)) {
    $show = false;
}
if(!isset($maxHeig)){
    $maxHeig = '49vh';
}
?>
<div class="accordion mb-5" id="accProdutos">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headprod<?php echo $produtos[0] ?>">
            <button class="accordion-button" type="button"
                data-bs-target="#collapseprod<?php echo $produtos[0] ?>" aria-expanded="true"
                aria-controls="collapseprod<?php echo $produtos[0] ?>" data-proid="<?php echo $produtos[0] ?>">
                <div class='col-12 text-center'>Produtos</div>
            </button>
        </h2>
        <div id="collapseprod<?php echo $produtos[0] ?>" class="accordion-collapse collapse show" aria-labelledby="headprod<?php echo $produtos[0] ?>" data-bs-parent="#accProdutos">
            <div class="accordion-body p-0" style="max-height:<?php echo $maxHeig; ?>; height:auto; overflow-y: auto;border-top: 1px solid white;">
                <table class="display compact table table-sm table-info table-striped table-hover table-vertical-borders col-12 no-footer dataTable tabela-pequena"  aria-describedby="table_info">
                    <thead class="table-default bg-table-blue-dark col-12 overflow-x-auto">
                        <tr class=' w-100' style='min-height:39px; height:39px;'>
                            <?
                            for ($c = 0; $c < count($colunas); $c++) { ?>
                                <th class="sticky-top text-center align-middle text-wrap"><?php echo $colunas[$c]; ?></th>
                            <?
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody style="max-height: 45vh; overflow-y: auto;">
                        <?
                        if (count($produtos) > 1) {
                            for ($p = 1; $p < count($produtos); $p++) {
                                $prodt = $produtos[$p];
                        ?>
                                <tr class='linha_produto <?php echo($p % 2 == 0) ? 'even' : 'odd'; ?>' id='<?php echo $prodt[0] ?>'>
                                    <?
                                    for ($pp = 1; $pp <= count($colunas); $pp++) { ?>
                                        <td class='px-2 text-<?php echo $alinha[$pp - 1]; ?>'><?php echo $prodt[$pp]; ?></td>
                                    <?
                                    } ?>
                                </tr>
                            <?
                            };
                        } else { ?>
                            <tr>
                                <?
                                for ($c = 0; $c < count($colunas); $c++) { ?>
                                    <td></td>
                                <?
                                } ?>
                            </tr>
                        <?
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>