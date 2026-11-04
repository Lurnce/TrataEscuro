<?php
// Formulário compartilhado por novo.php e editar.php
// Espera as variáveis: $pdo, $d (dados), $erros (lista) e $rotulo_botao (texto do botão)
$generos_existentes = $pdo->query("SELECT DISTINCT genero FROM livros
                                    WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")->fetchAll(PDO::FETCH_COLUMN);
?>
<?php if (!empty($erros)): ?>
  <div class="alerta alerta-erro">
    <ul><?php foreach ($erros as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<form method="post" class="card form form-livro">
  <?= csrf_campo() ?>
  <div class="form-grade">
    <label class="col-2">Título
      <input name="titulo" value="<?= e($d['titulo']) ?>" required maxlength="150">
    </label>
    <label class="col-2">Autor
      <input name="autor" value="<?= e($d['autor']) ?>" required maxlength="100">
    </label>
    <label>Ano
      <input name="ano" type="number" min="1000" max="<?= (int)date('Y') + 1 ?>" value="<?= e($d['ano']) ?>">
    </label>
    <label>Gênero
      <input name="genero" list="lista-generos" value="<?= e($d['genero']) ?>" maxlength="50">
      <datalist id="lista-generos">
        <?php foreach ($generos_existentes as $g): ?><option value="<?= e($g) ?>"></option><?php endforeach; ?>
      </datalist>
    </label>
    <label>Quantidade de exemplares
      <input name="quantidade" type="number" min="1" max="9999" value="<?= e($d['quantidade']) ?>" required>
    </label>
  </div>
  <div class="form-acoes">
    <button class="btn btn-primario"><?= e($rotulo_botao) ?></button>
    <a class="btn btn-fantasma" href="index.php">Cancelar</a>
  </div>
</form>
