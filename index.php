<?php
require_once __DIR__ . '/conexao.php';
exigir_login();

$uid    = (int)usuario_logado()['id'];
$busca  = parametro('busca');
$genero = parametro('genero');
$like   = '%' . addcslashes($busca, '%_\\') . '%';

$sql = "SELECT l.id, l.titulo, l.autor, l.ano, l.genero, l.quantidade,
               (SELECT COUNT(*) FROM emprestimos e
                 WHERE e.livro_id = l.id AND e.data_devolucao IS NULL) AS emprestados,
               (SELECT ROUND(AVG(a.nota), 1) FROM avaliacoes a WHERE a.livro_id = l.id) AS media,
               (SELECT COUNT(*) FROM avaliacoes a WHERE a.livro_id = l.id) AS total_aval
        FROM livros l
        WHERE (l.titulo LIKE :busca OR l.autor LIKE :busca2)
          AND (:g1 = '' OR l.genero = :g2)
        ORDER BY l.titulo";
$consulta = $pdo->prepare($sql);
$consulta->execute([':busca' => $like, ':busca2' => $like, ':g1' => $genero, ':g2' => $genero]);
$livros = $consulta->fetchAll();

$generos = $pdo->query("SELECT DISTINCT genero FROM livros
                         WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")->fetchAll(PDO::FETCH_COLUMN);

// Recomendações só aparecem na vitrine completa (sem busca nem filtro)
$filtrando = $busca !== '' || $genero !== '';
$sugestoes = $filtrando ? [] : recomendar_livros($pdo, $uid, 4);

$titulo_pagina = 'Acervo';
require_once __DIR__ . '/topo.php';
?>

<div class="page-head">
  <div>
    <h1>Acervo</h1>
    <p class="sub">Encontre seu próximo livro.</p>
  </div>
  <?php if (eh_admin()): ?><a class="btn btn-primario" href="novo.php">Novo livro</a><?php endif; ?>
</div>

<?php if ($sugestoes): ?>
<section class="secao-sugestoes">
  <h2>Para você</h2>
  <div class="grade-livros">
    <?php foreach ($sugestoes as $s): ?>
      <?= card_livro($s, (string)$s['motivo']) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<form method="get" class="busca">
  <input type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por título ou autor" aria-label="Buscar">
  <select name="genero" aria-label="Gênero">
    <option value="">Todos os gêneros</option>
    <?php foreach ($generos as $g): ?>
      <option value="<?= e($g) ?>" <?= $g === $genero ? 'selected' : '' ?>><?= e($g) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primario">Buscar</button>
  <?php if ($filtrando): ?><a class="btn btn-fantasma" href="index.php">Limpar</a><?php endif; ?>
</form>

<?php if (!$livros): ?>
  <div class="vazio">Nenhum livro encontrado. Tente outro termo ou limpe os filtros.</div>
<?php else: ?>
  <?php if (!$filtrando): ?><h2>Todos os livros</h2><?php endif; ?>
  <div class="grade-livros">
    <?php foreach ($livros as $l): ?>
      <?= card_livro($l) ?>
    <?php endforeach; ?>
  </div>
  <p class="total"><?= count($livros) ?> livro(s)</p>
<?php endif; ?>

<?php require_once __DIR__ . '/rodape.php'; ?>
