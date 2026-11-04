<?php
/* =========================================================
   Trata · recomendações de livros
   Combina: gosto do leitor (notas dadas, empréstimos do aluno
   vinculado à conta e gênero favorito do perfil) com a nota
   média e a popularidade de cada livro no acervo.
   ========================================================= */

/**
 * Devolve até $limite livros que o leitor ainda não avaliou nem pegou emprestado,
 * já ordenados do mais para o menos indicado. Cada item traz a chave 'motivo'.
 */
function recomendar_livros(PDO $pdo, int $uid, int $limite = 4): array {
    $livros = $pdo->query(
        "SELECT l.id, l.titulo, l.autor, l.ano, l.genero, l.quantidade,
                (SELECT COUNT(*) FROM emprestimos e
                  WHERE e.livro_id = l.id AND e.data_devolucao IS NULL) AS emprestados,
                (SELECT COUNT(*) FROM emprestimos e WHERE e.livro_id = l.id) AS total_emp,
                (SELECT ROUND(AVG(a.nota), 1) FROM avaliacoes a WHERE a.livro_id = l.id) AS media,
                (SELECT COUNT(*) FROM avaliacoes a WHERE a.livro_id = l.id) AS total_aval
         FROM livros l"
    )->fetchAll();
    if (!$livros) return [];

    $por_id = [];
    foreach ($livros as $l) $por_id[(int)$l['id']] = $l;

    // ---------- Sinais do leitor ----------
    $peso_genero = [];
    $peso_autor  = [];
    $conhecidos  = []; // livros já avaliados ou emprestados

    $c = $pdo->prepare("SELECT livro_id, nota FROM avaliacoes WHERE usuario_id = :u");
    $c->execute([':u' => $uid]);
    foreach ($c->fetchAll() as $av) {
        $id = (int)$av['livro_id'];
        $conhecidos[$id] = true;
        if (!isset($por_id[$id])) continue;
        $nota = (int)$av['nota'];
        // 4 e 5 estrelas puxam livros parecidos; 1 e 2 afastam; 3 é neutro
        $delta = $nota >= 4 ? $nota - 3 : ($nota <= 2 ? -1 : 0);
        if ($por_id[$id]['genero'] !== null && $por_id[$id]['genero'] !== '') {
            $peso_genero[$por_id[$id]['genero']] = ($peso_genero[$por_id[$id]['genero']] ?? 0) + $delta;
        }
        $peso_autor[$por_id[$id]['autor']] = ($peso_autor[$por_id[$id]['autor']] ?? 0) + $delta;
    }

    $c = $pdo->prepare("SELECT DISTINCT e.livro_id
                        FROM emprestimos e JOIN alunos al ON al.id = e.aluno_id
                        WHERE al.usuario_id = :u");
    $c->execute([':u' => $uid]);
    foreach ($c->fetchAll(PDO::FETCH_COLUMN) as $livro_id) {
        $id = (int)$livro_id;
        $conhecidos[$id] = true;
        if (!isset($por_id[$id])) continue;
        // Cada empréstimo conta como interesse
        if ($por_id[$id]['genero'] !== null && $por_id[$id]['genero'] !== '') {
            $peso_genero[$por_id[$id]['genero']] = ($peso_genero[$por_id[$id]['genero']] ?? 0) + 1;
        }
        $peso_autor[$por_id[$id]['autor']] = ($peso_autor[$por_id[$id]['autor']] ?? 0) + 1;
    }

    $c = $pdo->prepare("SELECT genero_favorito FROM usuarios WHERE id = :u");
    $c->execute([':u' => $uid]);
    $favorito = (string)($c->fetchColumn() ?: '');
    if ($favorito !== '') $peso_genero[$favorito] = ($peso_genero[$favorito] ?? 0) + 2;

    // ---------- Média geral das notas (para suavizar livros com poucas avaliações) ----------
    $soma = 0.0; $n = 0;
    foreach ($livros as $l) {
        if ((int)$l['total_aval'] > 0) { $soma += (float)$l['media']; $n++; }
    }
    $media_geral = $n > 0 ? $soma / $n : 3.5;
    $m = 3; // quantas avaliações "valem" a média geral

    // ---------- Pontuação ----------
    $candidatos = [];
    foreach ($livros as $l) {
        $id = (int)$l['id'];
        if (isset($conhecidos[$id])) continue;

        $v     = (int)$l['total_aval'];
        $nota  = $v > 0 ? (float)$l['media'] : $media_geral;
        $bayes = ($v * $nota + $m * $media_geral) / ($v + $m);

        $genero = (string)($l['genero'] ?? '');
        $pg = $genero !== '' ? ($peso_genero[$genero] ?? 0) : 0;
        $pa = $peso_autor[$l['autor']] ?? 0;

        $pts_genero = max(-3, min(4, $pg)) * 0.6;
        $pts_autor  = max(-2, min(3, $pa)) * 0.9;
        $pts_pop    = log(1 + (int)$l['total_emp']) * 0.35;
        $disponivel = (int)$l['quantidade'] - (int)$l['emprestados'] > 0;

        $score = $bayes + $pts_genero + $pts_autor + $pts_pop - ($disponivel ? 0 : 0.3);

        if ($pts_autor > 0 && $pts_autor >= $pts_genero) {
            $motivo = 'Mais de ' . $l['autor'];
        } elseif ($pts_genero > 0) {
            $motivo = 'Porque você curte ' . $genero;
        } elseif ($v >= 1 && (float)$l['media'] >= 4) {
            $motivo = 'Bem avaliado pelos leitores';
        } elseif ((int)$l['total_emp'] >= 1) {
            $motivo = 'Um dos mais emprestados';
        } else {
            $motivo = 'Sugestão do acervo';
        }

        $l['motivo']    = $motivo;
        $l['_pontos']   = $score;
        $candidatos[]   = $l;
    }

    usort($candidatos, function (array $a, array $b): int {
        $cmp = $b['_pontos'] <=> $a['_pontos'];
        return $cmp !== 0 ? $cmp : strcmp((string)$a['titulo'], (string)$b['titulo']);
    });

    return array_slice($candidatos, 0, max(0, $limite));
}
