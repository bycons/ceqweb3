<?
if (!isset($show)) {
    $show = false;
}
?>
<div class="accordion mb-5" id="accProdutos">
    <div class="accordion-item">
        <h2 class="accordion-header" id="headprod<?php echo $id ?? '' ?>">
            <button class="accordion-button" type="button"
                data-bs-target="#collapseprod<?php echo $id ?? '' ?>" aria-expanded="true"
                aria-controls="collapseprod<?php echo $id ?? '' ?>" data-proid="<?php echo $id ?? '' ?>">
                <div class='col-12 text-center'>Produtos</div>
            </button>
        </h2>
        <div id="collapse<?php echo $id ?? '' ?>" class="accordion-collapse collapse show " aria-labelledby="heading<?php echo $id ?? '' ?>" data-bs-parent="#accProdutos">
            <div class="accordion-body p-0" style="max-height:49vh; height:49vh; overflow-y: auto;border-top: 1px solid white;">
                <table class="display compact table table-sm table-striped table-hover table-vertical-borders col-12 no-footer tabela-pequena"  aria-describedby="table_info">
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
                        <?php foreach ($produtos as $idx => $produto): ?>
                            <tr class='linha_produto' id='<?php echo $idx ?>'>
                                <?
                                for ($c = 0; $c < count($produto); $c++) { ?>
                                    <td><?php echo $produto[$c]; ?></td>
                                <?
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>