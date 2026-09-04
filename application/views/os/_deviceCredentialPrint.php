<?php if (! empty($deviceCredential) && ! empty($deviceCredential['valid'])) : ?>
    <div class="subtitle">CREDENCIAL DO APARELHO - VIA TÉCNICA</div>
    <div class="dados">
        <?php if ($deviceCredential['data']['tipo'] === Device_credential::TYPE_NONE) : ?>
            <div><strong>SEM SENHA</strong></div>
        <?php elseif ($deviceCredential['data']['tipo'] === Device_credential::TYPE_TEXT) : ?>
            <div><strong>Senha/PIN:</strong> <?= html_escape($deviceCredential['data']['texto']) ?></div>
        <?php elseif ($deviceCredential['data']['tipo'] === Device_credential::TYPE_PATTERN) : ?>
            <?php
            $printGrid = (int) $deviceCredential['data']['grade'];
            $printSequence = $deviceCredential['data']['sequencia'];
            $printGap = 80 / max(1, $printGrid - 1);
            $printCoordinates = [];
            foreach ($printSequence as $printPoint) {
                $printRow = intdiv($printPoint - 1, $printGrid);
                $printColumn = ($printPoint - 1) % $printGrid;
                $printCoordinates[] = (10 + ($printColumn * $printGap)) . ',' . (10 + ($printRow * $printGap));
            }
            ?>
            <div><strong><?= html_escape($deviceCredential['data']['descricao']) ?></strong></div>
            <svg viewBox="0 0 100 100" width="170" height="170" role="img" aria-label="Diagrama estático do padrão">
                <polyline points="<?= html_escape(implode(' ', $printCoordinates)) ?>"
                    fill="none" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <?php for ($printPoint = 1; $printPoint <= ($printGrid * $printGrid); $printPoint++) : ?>
                    <?php
                    $printRow = intdiv($printPoint - 1, $printGrid);
                    $printColumn = ($printPoint - 1) % $printGrid;
                    $printX = 10 + ($printColumn * $printGap);
                    $printY = 10 + ($printRow * $printGap);
                    $printSelected = in_array($printPoint, $printSequence, true);
                    ?>
                    <circle cx="<?= $printX ?>" cy="<?= $printY ?>" r="4"
                        fill="<?= $printSelected ? '#222' : '#fff' ?>" stroke="#222" stroke-width="1" />
                    <text x="<?= $printX ?>" y="<?= $printY ?>" text-anchor="middle" dominant-baseline="central"
                        font-family="Arial, sans-serif" font-size="4" fill="<?= $printSelected ? '#fff' : '#222' ?>"><?= $printPoint ?></text>
                <?php endfor; ?>
            </svg>
        <?php else : ?>
            <div><strong>Não informada</strong></div>
        <?php endif; ?>
    </div>
<?php elseif (! empty($deviceCredential) && empty($deviceCredential['valid'])) : ?>
    <div class="subtitle">CREDENCIAL DO APARELHO - VIA TÉCNICA</div>
    <div class="dados"><strong>Indisponível:</strong> <?= html_escape($deviceCredential['error']) ?></div>
<?php endif; ?>
