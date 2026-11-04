<?php
// Visual "Escuro": cartão de livro em formato de linha (lista)

/**
 * Monta o cartão de um livro. Espera as chaves: id, titulo, autor, genero, quantidade,
 * emprestados, media e total_aval. $motivo (opcional) explica uma recomendação.
 */
function card_livro(array $l, string $motivo = ''): string {
    $disp = max(0, (int)$l['quantidade'] - (int)$l['emprestados']);
    $cor  = capa_cor((string)$l['titulo']);
    $href = 'livro.php?id=' . (int)$l['id'];
    $sub  = (string)$l['autor'] . (!empty($l['genero']) ? ' · ' . $l['genero'] : '');

    $h  = '<a class="livro" href="' . e($href) . '">';
    $h .= '<div class="capa capa-' . $cor . '" aria-hidden="true"><span class="capa-titulo">'
        . e(mb_strtoupper(mb_substr((string)$l['titulo'], 0, 1))) . '</span></div>';
    $h .= '<div class="livro-corpo">';
    $h .= '<span class="livro-titulo">' . e($l['titulo']) . '</span>';
    $h .= '<span class="livro-autor">' . e($sub) . '</span>';
    if ($motivo !== '') $h .= '<small class="motivo">' . e($motivo) . '</small>';
    $h .= '</div>';
    $h .= '<div class="livro-lado">';
    if ((int)($l['total_aval'] ?? 0) > 0) {
        $h .= '<div class="nota-linha">' . estrelas($l['media'])
            . '<small>' . number_format((float)$l['media'], 1, ',', '.') . '</small></div>';
    } else {
        $h .= '<div class="nota-linha"><small class="mudo">Sem avaliações</small></div>';
    }
    $h .= '<span class="disp ' . ($disp > 0 ? 'disp-sim' : 'disp-nao') . '">'
        . ($disp > 0 ? 'Disponível' : 'Emprestado') . '</span>';
    $h .= '</div></a>';
    return $h;
}
