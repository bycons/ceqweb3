<?php

/**
 * Lista de anexos com upload múltiplo (RN03.16 / RN03.20), padrão validado
 * pelo bydsgn: reaproveita bt-repete/bt-exclui/addCampo() (my_fields.js) —
 * mesmo mecanismo de T9 — e o upload sobe junto com o Salvar geral do form
 * (submeteForm()/executaAjax() já montam FormData automaticamente, sem
 * precisar de upload assíncrono item-a-item).
 *
 * Mirror de partials/pw_acoes_notif.php: os campos já vêm prontos de
 * EntOcoNotifEventoAnexo::campos — esta view só monta o layout, sem criar
 * nenhum MyCampo.
 *
 * $sufixo  'provid' | 'parecer'
 * $linhas  array de arrays de campos (EntOcoNotifEventoAnexo::campos) — a
 *          última linha é sempre a linha em branco para novo upload.
 */
$ultimo = count($linhas) - 1;
?>
<div class="col-12 mt-2">
    <div id="rep_anexo_<?= $sufixo ?>" data-index="<?= $ultimo ?>">
        <?php foreach ($linhas as $i => $linha): ?>
            <div class="row tableDiv table2 mb-2 table-anexo_<?= $sufixo ?> align-items-center" data-index="<?= $i ?>">
                <?php if (isset($linha['exibicao'])): ?>
                    <div class="col-9">
                        <?= $linha['exibicao'] ?>
                        <?= $linha['nva_id'] ?>
                    </div>
                <?php else: ?>
                    <div class="col-9"><?= $linha['arquivo'] ?></div>
                <?php endif; ?>
                <div class="col-3 text-end"><?= $linha['bt_del'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row px-2 pb-2">
        <div class="col-12 text-end">
            <button type="button"
                class="btn btn-outline-success btn-sm bt-repete"
                data-index="<?= $ultimo ?>"
                title="Adicionar Anexo"
                onclick="addCampo('<?= base_url('NotifEvento/addAnexo' . ucfirst($sufixo) . '/') ?>','anexo_<?= $sufixo ?>', this)">
                <i class="fas fa-plus"></i> Adicionar Anexo
            </button>
        </div>
    </div>
</div>